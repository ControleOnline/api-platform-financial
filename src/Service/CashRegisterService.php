<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\OrderProduct;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Spool;
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

        $deviceConfig = $this->deviceService->discoveryDeviceConfig($device, $provider)->getConfigs(true);

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
                SUM(op.quantity) AS quantity,
                op.price AS order_product_price,
                SUM(op.total) AS order_product_total
            FROM order_product op 
            INNER JOIN product p ON op.product_id = p.id
            WHERE op.order_id IN
            (';
        $sql .= $this->inFlowService->getSubquery($deviceConfig);
        $sql .= ') AND p.type IN (:type)
            GROUP BY p.id, p.product, p.description, p.sku
            ORDER BY p.product ASC
        ';

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query
            ->setParameter('type', ['product', 'custom', 'manufactured'])
            ->setParameter('device', $device->getDevice())
            ->setParameter('provider', $provider->getId());

        if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id']) && $deviceConfig['cash-wallet-open-id'] > 0)
            $query->setParameter('minId', $deviceConfig['cash-wallet-open-id']);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0)
            $query->setParameter('maxId', $deviceConfig['cash-wallet-closed-id']);

        return $query->getArrayResult();
    }

    public function generatePrintData(Device $device, People $provider):Spool
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

        return $this->printService->generatePrintData($device);
    }
}
