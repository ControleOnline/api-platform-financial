<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
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

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('totalPrice', 'totalPrice');
        $rsm->addScalarResult('dwalletId', 'dwalletId');
        $rsm->addScalarResult('dwallet', 'dwallet');
        $rsm->addScalarResult('owalletId', 'owalletId');
        $rsm->addScalarResult('owallet', 'owallet');
        $rsm->addScalarResult('paymentTypeId', 'paymentTypeId');
        $rsm->addScalarResult('paymentType', 'paymentType');

        $sql = '
            SELECT 
                SUM(sub.price) AS totalPrice,
                sub.dwallet_id AS dwalletId,
                sub.dwallet AS dwallet,
                sub.owallet_id AS owalletId,
                sub.owallet AS owallet,
                sub.payment_type_id AS paymentTypeId,
                sub.payment_type AS paymentType
            FROM (
                SELECT DISTINCT
                    i.id,
                    i.price,
                    dw.id AS dwallet_id,
                    dw.wallet AS dwallet,
                    ow.id AS owallet_id,
                    ow.wallet AS owallet,
                    pt.id AS payment_type_id,
                    pt.payment_type AS payment_type
                FROM invoice i
                INNER JOIN wallet dw ON i.destination_wallet_id = dw.id
                INNER JOIN payment_type pt ON i.payment_type_id = pt.id
                LEFT JOIN wallet ow ON i.source_wallet_id = ow.id
                WHERE 1=1
        ';

        if (isset($this->filters['receiver'])) {
            $sql .= ' AND i.receiver_id = :receiver';
        }

        if (isset($this->filters['device.device'])) {
            $sql .= ' AND i.device_id IN (SELECT d.id FROM device d WHERE d.device = :device)';
        }

        if (isset($this->filters['device.device']) && isset($this->filters['receiver'])) {
            $device = $this->entityManager->getRepository(Device::class)->findOneBy(['device' => $this->filters['device.device']]);
            $people = $this->entityManager->getRepository(People::class)->find($this->filters['receiver']);

            if ($device && $people) {
                $device_config = $this->deviceService->discoveryDeviceConfig(
                    $device,
                    $people
                )->getConfigs(true);

                if ($device_config && isset($device_config['cash-wallet-open-id'])) {
                    $sql .= ' AND i.id > :idGt';
                }

                if ($device_config && isset($device_config['cash-wallet-closed-id']) && $device_config['cash-wallet-closed-id'] > 0) {
                    $sql .= ' AND i.id <= :idLt';
                }
            }
        }

        $sql .= '
            ) sub
            GROUP BY sub.dwallet_id, sub.payment_type_id, sub.owallet_id
        ';

        $query = $this->entityManager->createNativeQuery($sql, $rsm);

        if (isset($this->filters['receiver'])) {
            $query->setParameter('receiver', $this->filters['receiver']);
        }

        if (isset($this->filters['device.device'])) {
            $query->setParameter('device', $this->filters['device.device']);
        }

        if (isset($this->filters['device.device']) && isset($this->filters['receiver'])) {
            $device = $this->entityManager->getRepository(Device::class)->findOneBy(['device' => $this->filters['device.device']]);
            $people = $this->entityManager->getRepository(People::class)->find($this->filters['receiver']);

            if ($device && $people) {
                $device_config = $this->deviceService->discoveryDeviceConfig(
                    $device,
                    $people
                )->getConfigs(true);

                if ($device_config && isset($device_config['cash-wallet-open-id'])) {
                    $query->setParameter('idGt', $device_config['cash-wallet-open-id']);
                }

                if ($device_config && isset($device_config['cash-wallet-closed-id']) && $device_config['cash-wallet-closed-id'] > 0) {
                    $query->setParameter('idLt', $device_config['cash-wallet-closed-id']);
                }
            }
        }

        $results = $query->getArrayResult();
        return $this->getResult($results);
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
}
