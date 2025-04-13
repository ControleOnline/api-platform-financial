<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\OrderProduct;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class CashRegisterService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrintService $printService,
        private ConfigService $configService,
        private InFlowService $inFlowService,
        private DeviceService $deviceService
    ) {}

    public function generateData(Device $device, People $provider)
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('product_name', 'product_name');
        $rsm->addScalarResult('product_description', 'product_description');
        $rsm->addScalarResult('product_sku', 'product_sku');
        $rsm->addScalarResult('quantity', 'quantity');
        $rsm->addScalarResult('order_product_price', 'order_product_price');
        $rsm->addScalarResult('order_product_total', 'order_product_total');

        $sql = '
            SELECT 
                p.product AS product_name,
                p.description AS product_description,
                p.sku AS product_sku,
                SUM(sub.quantity) AS quantity,
                MIN(sub.price) AS order_product_price,
                SUM(sub.total) AS order_product_total
            FROM (
                SELECT DISTINCT
                    op.id,
                    op.quantity,
                    op.price,
                    op.total,
                    op.product_id
                FROM order_product op
                INNER JOIN orders o ON op.order_id = o.id
                INNER JOIN order_invoice oi ON o.id = oi.order_id
                INNER JOIN invoice i ON oi.invoice_id = i.id
                INNER JOIN device d ON i.device_id = d.id
                WHERE d.id = :device
                    AND o.provider_id = :provider
                    AND i.receiver_id = :provider
            ) sub
            INNER JOIN product p ON sub.product_id = p.id
            WHERE p.type IN (:type)
            GROUP BY p.id, p.product, p.description, p.sku
            ORDER BY p.product ASC
        ';

        $query = $this->entityManager->createNativeQuery($sql, $rsm);

        $query
            ->setParameter('type', ['product', 'custom', 'manufactured'])
            ->setParameter('device', $device->getId())
            ->setParameter('provider', $provider->getId());

        $deviceConfig = $this->deviceService->discoveryDeviceConfig(
            $device,
            $provider
        )->getConfigs(true);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id'])) {
            $sql = str_replace(
                'WHERE d.id = :device',
                'WHERE d.id = :device AND i.id > :minId',
                $sql
            );
            $query = $this->entityManager->createNativeQuery($sql, $rsm);
            $query
                ->setParameter('type', ['product', 'custom', 'manufactured'])
                ->setParameter('device', $device->getId())
                ->setParameter('provider', $provider->getId())
                ->setParameter('minId', $deviceConfig['cash-wallet-open-id']);
        }

        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0) {
            $sql = str_replace(
                'WHERE d.id = :device',
                'WHERE d.id = :device AND i.id <= :maxId',
                $sql
            );
            if (isset($deviceConfig['cash-wallet-open-id'])) {
                $sql = str_replace(
                    'AND i.id > :minId',
                    'AND i.id > :minId AND i.id <= :maxId',
                    $sql
                );
            }
            $query = $this->entityManager->createNativeQuery($sql, $rsm);
            $query
                ->setParameter('type', ['product', 'custom', 'manufactured'])
                ->setParameter('device', $device->getId())
                ->setParameter('provider', $provider->getId())
                ->setParameter('maxId', $deviceConfig['cash-wallet-closed-id']);
            if (isset($deviceConfig['cash-wallet-open-id'])) {
                $query->setParameter('minId', $deviceConfig['cash-wallet-open-id']);
            }
        }

        return $query->getArrayResult();
    }

    public function generatePrintData(Device $device, $provider, string $printType, string $deviceType)
    {
        $products = $this->generateData($device, $provider);

        $filters = [
            'device.device' => $device->getDevice(),
            'receiver' => $provider->getId()
        ];
        $paymentData = $this->inFlowService->getPayments($filters);

        $this->printService->addLine("RELATÓRIO DE CAIXA");
        $this->printService->addLine(date('d/m/Y H:i'));
        $this->printService->addLine($provider->getName());
        $this->printService->addLine("", "", "-");

        foreach ($paymentData['wallet'] as $walletId => $wallet) {
            $this->printService->addLine(strtoupper($wallet['wallet']) . ":");

            foreach ($wallet['payment'] as $payment) {
                if ($payment['inflow'] > 0) {
                    $this->printService->addLine(
                        "  " . $payment['payment'],
                        "R$ " . number_format($payment['inflow'], 2, ',', '.'),
                        "."
                    );
                }
                if ($payment['withdrawal'] > 0) {
                    $this->printService->addLine(
                        "  Sangria " . $wallet['withdrawal-wallet'],
                        "R$ " . number_format($payment['withdrawal'], 2, ',', '.'),
                        "."
                    );
                }
            }

            $this->printService->addLine(
                "  Total",
                "R$ " . number_format($wallet['total'], 2, ',', '.'),
                "."
            );
            $this->printService->addLine("", "", "-");
        }

        $total = 0;
        $this->printService->addLine("PRODUTOS:");
        foreach ($products as $product) {
            $quantity = $product['quantity'];
            $productName = substr($product['product_name'], 0, 20);
            $subtotal = $product['order_product_total'];
            $total += $subtotal;

            $this->printService->addLine(
                "  $quantity X " . $productName,
                "R$ " . number_format($subtotal, 2, ',', '.'),
                "."
            );
        }

        $this->printService->addLine("", "", "-");
        $this->printService->addLine(
            "TOTAL",
            "R$ " . number_format($total, 2, ',', '.'),
            " "
        );
        $this->printService->addLine("", "", "-");

        return $this->printService->generatePrintData($printType, $deviceType);
    }
}