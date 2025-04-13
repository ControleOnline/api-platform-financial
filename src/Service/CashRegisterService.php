<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Device;
use ControleOnline\Entity\OrderProduct;
use ControleOnline\Entity\People;
use Doctrine\ORM\EntityManagerInterface;

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
        $orderProductRepository = $this->entityManager->getRepository(OrderProduct::class);

        $subQueryBuilder = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT op.quantity AS quantity')
            ->addSelect('op.total AS total')
            ->addSelect('p.product AS product_name')
            ->addSelect('p.description AS product_description')
            ->addSelect('p.sku AS product_sku')
            ->addSelect('op.price AS order_product_price')
            ->from(OrderProduct::class, 'op')
            ->join('op.product', 'p')
            ->join('op.order', 'o')
            ->join('o.invoice', 'oi')
            ->join('oi.invoice', 'i')
            ->join('i.device', 'd')
            ->andWhere('d.id = :device')
            ->andWhere('o.provider = :provider')
            ->andWhere('i.receiver = :provider')
            ->andWhere('p.type IN(:type)');

        $deviceConfig = $this->deviceService->discoveryDeviceConfig(
            $device,
            $provider
        )->getConfigs(true);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id'])) {
            $subQueryBuilder->andWhere('i.id > :minId')
                ->setParameter('minId', $deviceConfig['cash-wallet-open-id']);
        }

        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0) {
            $subQueryBuilder->andWhere('i.id <= :maxId')
                ->setParameter('maxId', $deviceConfig['cash-wallet-closed-id']);
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('sub.product_name')
            ->addSelect('sub.product_description')
            ->addSelect('sub.product_sku')
            ->addSelect('SUM(sub.quantity) AS quantity')
            ->addSelect('MIN(sub.order_product_price) AS order_product_price')
            ->addSelect('SUM(sub.total) AS order_product_total')
            ->from('(' . $subQueryBuilder->getQuery()->getDQL() . ')', 'sub')
            ->groupBy('sub.product_name')
            ->addGroupBy('sub.product_description')
            ->addGroupBy('sub.product_sku')
            ->orderBy('sub.product_name', 'ASC');

        $queryBuilder
            ->setParameter('type', ['product', 'custom', 'manufactured'])
            ->setParameter('device', $device->getId())
            ->setParameter('provider', $provider->getId());

        if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id'])) {
            $queryBuilder->setParameter('minId', $deviceConfig['cash-wallet-open-id']);
        }
        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0) {
            $queryBuilder->setParameter('maxId', $deviceConfig['cash-wallet-closed-id']);
        }

        error_log($queryBuilder->getQuery()->getSQL());
        return $queryBuilder->getQuery()->getArrayResult();
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