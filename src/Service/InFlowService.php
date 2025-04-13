<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;

class InFlowService
{
    private $qb;
    private $filters = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private PrintService $printService,
        private ConfigService $configService,
        private DeviceService $deviceService
    ) {}

    public function getPayments($filters): array
    {
        $this->filters = $filters;
        $this->createBaseQuery();
        $results = $this->qb->getQuery()->getResult();
        error_log($this->qb->getQuery()->getSQL());
        return $this->getResult($results);
    }

    private function createBaseQuery()
    {
        $subQueryBuilder = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT i.price AS price')
            ->addSelect('dw.id AS dwalletId', 'dw.wallet AS dwallet')
            ->addSelect('ow.id AS owalletId', 'ow.wallet AS owallet')
            ->addSelect('pt.id AS paymentTypeId', 'pt.paymentType AS paymentType')
            ->from(Invoice::class, 'i')
            ->join('i.destinationWallet', 'dw')
            ->join('i.paymentType', 'pt')
            ->leftJoin('i.sourceWallet', 'ow');

        $this->applyCommonFilters($subQueryBuilder);

        $this->qb = $this->entityManager->createQueryBuilder()
            ->select('SUM(sub.price) AS totalPrice')
            ->addSelect('sub.dwalletId', 'sub.dwallet')
            ->addSelect('sub.owalletId', 'sub.owallet')
            ->addSelect('sub.paymentTypeId', 'sub.paymentType')
            ->from('(' . $subQueryBuilder->getQuery()->getDQL() . ')', 'sub')
            ->groupBy('sub.dwalletId', 'sub.paymentTypeId', 'sub.owalletId');

        foreach ($subQueryBuilder->getParameters() as $parameter) {
            $this->qb->setParameter($parameter->getName(), $parameter->getValue());
        }
    }

    private function getResult($results): array
    {
        $data = [];
        foreach ($results as $row) {
            $oWalletId = $row['owalletId'];
            $dWalletId = $oWalletId ?: $row['dwalletId'];
            $paymentTypeId = $row['paymentTypeId'];
            $totalPrice = (float) $row['totalPrice'];

            if (!isset($data['wallet'][$dWalletId])) {
                $data['wallet'][$dWalletId] = [
                    'wallet' => $row['owallet'] ?: $row['dwallet'],
                    'payment' => [],
                    'total' => 0.0,
                ];
            }

            if ($row['owallet']) {
                $data['wallet'][$dWalletId]['withdrawal-wallet'] = $row['dwallet'];
            }

            if (!isset($data['wallet'][$dWalletId]['payment'][$paymentTypeId])) {
                $data['wallet'][$dWalletId]['payment'][$paymentTypeId] = [
                    'payment' => $row['paymentType'],
                    'inflow' => 0.0,
                    'withdrawal' => 0,
                ];
            }

            if ($oWalletId === null) {
                $data['wallet'][$dWalletId]['payment'][$paymentTypeId]['inflow'] += $totalPrice;
            } else {
                $data['wallet'][$dWalletId]['payment'][$paymentTypeId]['withdrawal'] += $totalPrice;
            }

            $data['wallet'][$dWalletId]['total'] = array_sum(
                array_map(
                    fn($payment) => $payment['inflow'] - $payment['withdrawal'],
                    $data['wallet'][$dWalletId]['payment']
                )
            );

            if (!isset($data['total'])) {
                $data['total'] = 0.0;
            }

            $data['total'] += $oWalletId === null ? $totalPrice : -$totalPrice;
        }

        return $data;
    }

    private function applyCommonFilters($qb = null): void
    {
        $qb = $qb ?: $this->qb;
        $this->applyDeviceFilter($qb);
        $this->applyCashRegisterFilter($qb);
        $this->applyReceiverFilter($qb);
    }

    private function applyReceiverFilter($qb): void
    {
        if (isset($this->filters['receiver'])) {
            $qb->andWhere('i.receiver = :receiver')
                ->setParameter('receiver', $this->filters['receiver']);
        }
    }

    private function applyDeviceFilter($qb): void
    {
        if (isset($this->filters['device.device'])) {
            $qb->join('i.device', 'd')
                ->andWhere('d.device = :device')
                ->setParameter('device', $this->filters['device.device']);
        }
    }

    private function applyCashRegisterFilter($qb): void
    {
        if (isset($this->filters['device.device']) && isset($this->filters['receiver'])) {
            $device = $this->entityManager->getRepository(Device::class)->findOneBy(['device' => $this->filters['device.device']]);
            $people = $this->entityManager->getRepository(People::class)->find($this->filters['receiver']);
            
            if ($device && $people) {
                $device_config = $this->deviceService->discoveryDeviceConfig(
                    $device,
                    $people
                )->getConfigs(true);

                if ($device_config && isset($device_config['cash-wallet-open-id'])) {
                    $qb->andWhere('i.id > :idGt')
                        ->setParameter('idGt', $device_config['cash-wallet-open-id']);
                }

                if ($device_config && isset($device_config['cash-wallet-closed-id']) && $device_config['cash-wallet-closed-id'] > 0) {
                    $qb->andWhere('i.id <= :idLt')
                        ->setParameter('idLt', $device_config['cash-wallet-closed-id']);
                }
            }
        }
    }
}