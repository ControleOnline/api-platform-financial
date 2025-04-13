<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
as Security;

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
        $this->qb = $this->entityManager->createQueryBuilder()
            ->select('SUM(i.price) as totalPrice')
            ->addSelect('dw.id as dwalletId', 'dw.wallet as dwallet')
            ->addSelect('ow.id as owalletId', 'ow.wallet as owallet')
            ->addSelect('pt.id as paymentTypeId', 'pt.paymentType')
            ->from(Invoice::class, 'i')
            ->join('i.order', 'oi')
            ->join('oi.order', 'o')
            ->join('i.destinationWallet', 'dw')
            ->join('i.paymentType', 'pt')
            ->leftJoin('i.sourceWallet', 'ow')
            ->groupBy('ow.id, pt.id, dw.id');

        $this->applyCommonFilters();
    }

    private function getResult($results): array
    {
        $data = [];
        foreach ($results as $row) {
            $oWalletId = $row['owalletId'];
            $dWalletId = $oWalletId ?: $row['dwalletId'];
            $paymentTypeId = $row['paymentTypeId'];
            $totalPrice = (float) $row['totalPrice'];

            if (!isset($data['wallet'][$dWalletId]))
                $data['wallet'][$dWalletId] = [
                    'wallet' =>  $row['owallet'] ?: $row['dwallet'],
                    'payment' => [],
                    'total' => 0.0,
                ];


            if ($row['owallet'])
                $data['wallet'][$dWalletId]['withdrawal-wallet'] = $row['dwallet'];



            if (!isset($data['wallet'][$dWalletId]['payment'][$paymentTypeId])) {
                $data['wallet'][$dWalletId]['payment'][$paymentTypeId] = [
                    'payment' => $row['paymentType'],
                    'inflow' => 0.0,
                    'withdrawal' => 0,
                ];
            }

            if ($oWalletId === null)

                $data['wallet'][$dWalletId]['payment'][$paymentTypeId]['inflow'] += $totalPrice;
            else
                $data['wallet'][$dWalletId]['payment'][$paymentTypeId]['withdrawal'] += $totalPrice;


            $data['wallet'][$dWalletId]['total'] = array_sum(
                array_map(
                    fn($payment) => $payment['inflow'] - $payment['withdrawal'],
                    $data['wallet'][$dWalletId]['payment']
                )
            );

            if (!isset($data['total']))
                $data['total'] = 0.0;

            $data['total'] += $oWalletId === null ? $totalPrice : -$totalPrice;
        }

        return $data;
    }

    private function applyCommonFilters(): void
    {
        $this->applyDeviceFilter();
        $this->applyCashRegisterFilter();
        $this->applyReceiverFilter();
    }
    private function applyReceiverFilter(): void
    {
        if (isset($this->filters['receiver']))
            $this->qb->andWhere('i.receiver = :receiver')
                ->setParameter('receiver', $this->filters['receiver']);
    }

    private function applyDeviceFilter(): void
    {
        if (isset($this->filters['device.device'])) {
            $this->qb
                ->join('i.device', 'd')
                ->andWhere('d.device = :device')
                ->setParameter('device', $this->filters['device.device']);
        }
    }

    private function applyCashRegisterFilter(): void
    {
        if (isset($this->filters['device.device']) && isset($this->filters['receiver'])) {
            $device_config = null;
            $device = $this->entityManager->getRepository(Device::class)->findOneBy(['device' => $this->filters['device.device']]);
            $people = $this->entityManager->getRepository(People::class)->find($this->filters['receiver']);
            if ($device && $people)
                $device_config =  $this->deviceService->discoveryDeviceConfig(
                    $device,
                    $people
                )->getConfigs(true);

            if ($device_config && isset($device_config['cash-wallet-open-id']))
                $this->qb->andWhere('i.id > :idGt')
                    ->setParameter('idGt',  $device_config['cash-wallet-open-id']);
        }
    }
}
