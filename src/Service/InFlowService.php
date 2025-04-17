<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\DeviceConfig;
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


    public function getSubquery($deviceConfig)
    {
        $sql = 'SELECT DISTINCT 
        o.id AS order_id
    FROM
        invoice i
    JOIN order_invoice oi ON oi.invoice_id = i.id
    JOIN orders o ON o.id = oi.order_id
    JOIN device d ON d.id = i.device_id
    WHERE
        1 = 1 
        AND i.receiver_id = :provider
        AND d.id = :device ';
        if ($deviceConfig && !empty($deviceConfig['cash-wallet-open-id']) && $deviceConfig['cash-wallet-open-id'] > 0)
            $sql .= 'AND i.id > :minId ';
        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0)
            $sql .= ' AND i.id <= :maxId ';

        return $sql;
    }

    public function getPayments($filters): array
    {
        $this->filters = $filters;
        $deviceConfig = null;
        $device = $this->entityManager->getRepository(Device::class)->findOneBy(['device' => $this->filters['device.device']]);
        $people = $this->entityManager->getRepository(People::class)->find($this->filters['receiver']);

        if ($device && $people)
            $deviceConfig = $this->deviceService->discoveryDeviceConfig(
                $device,
                $people
            )->getConfigs(true);

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
                dw.id AS dwalletId,
                dw.wallet AS dwallet,
                ow.id AS owalletId,
                ow.wallet AS owallet,
                pt.id AS paymentTypeId,
                pt.payment_type AS paymentType
            FROM
                (';
        $sql .= $this->getSubquery($deviceConfig);
        $sql .= ') sub
            JOIN invoice i ON i.id = sub.invoice_id
            JOIN wallet dw ON i.destination_wallet_id = dw.id
            JOIN payment_type pt ON i.payment_type_id = pt.id
            LEFT JOIN wallet ow ON i.source_wallet_id = ow.id
            GROUP BY
                dw.id,
                dw.wallet,
                ow.id,
                ow.wallet,
                pt.id,
                pt.payment_type
        ';


        $query = $this->entityManager->createNativeQuery($sql, $rsm);

        if (isset($this->filters['receiver']))
            $query->setParameter('provider', $this->filters['receiver']);


        if (isset($this->filters['device.device']))
            $query->setParameter('device', $this->filters['device.device']);

        if ($deviceConfig && !empty($deviceConfig['cash-wallet-open-id']) && $deviceConfig['cash-wallet-open-id'] > 0)
            $query->setParameter('minId', $deviceConfig['cash-wallet-open-id']);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0)
            $query->setParameter('maxId', $deviceConfig['cash-wallet-closed-id']);

        $results = $query->getArrayResult();
        return $this->formatResult($results);
    }

    private function formatResult($results): array
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
