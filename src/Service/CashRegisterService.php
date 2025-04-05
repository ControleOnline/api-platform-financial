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
        private DeviceService $deviceService

    ) {}

    public function generateData(Device $device, People $provider)
    {
        $orderProductRepository = $this->entityManager->getRepository(OrderProduct::class);

        $queryBuilder = $orderProductRepository->createQueryBuilder('op')
            ->addSelect('p.product AS product_name')
            ->addSelect('p.description AS product_description')
            ->addSelect('p.sku AS product_sku')
            ->addSelect('SUM(op.quantity) AS quantity')
            ->addSelect('op.price AS order_product_price')
            ->addSelect('SUM(op.total) AS order_product_total')
            ->join('op.product', 'p')
            ->join('op.order', 'o')
            ->join('o.invoice', 'oi')
            ->join('oi.invoice', 'i')
            ->andWhere('o.device = :device')
            ->andWhere('o.provider = :provider')
            ->andWhere('p.type IN(:type)')
            ->groupBy('p.id')
            ->orderBy('p.product', 'ASC');

        $queryBuilder
            ->setParameter('type', ['product', 'custom'])
            ->setParameter('device', $device->getId())
            ->setParameter('provider', $provider->getId());

        $deviceConfig =  $this->deviceService->discoveryDeviceConfig(
            $device,
            $provider
        )->getConfigs(true);


        if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id']))
            $queryBuilder->andWhere('i.id > :minId')
                ->setParameter('minId',  $deviceConfig['cash-wallet-open-id']);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']) && $deviceConfig['cash-wallet-closed-id'] > 0)
            $queryBuilder->andWhere('i.id < :maxId')
                ->setParameter('maxId',  $deviceConfig['cash-wallet-closed-id']);

        return $queryBuilder->getQuery()->getArrayResult();
    }

    public function generatePrintData(Device $device, People $provider, string $printType, string $deviceType)
    {
        $products = $this->generateData($device, $provider);

        $this->printService->addLine("RELATÓRIO DE CAIXA");
        $this->printService->addLine(date('d/m/Y H:i'));
        $this->printService->addLine($provider->getName());
        $this->printService->addLine("", "", "-");
        $total = 0;
        foreach ($products as $product) {
            $quantity = $product['quantity'];
            $productName = $product['product_name'];
            $price = $product['order_product_price'];
            $subtotal = $product['order_product_total'];
            $total += $subtotal;
            $this->printService->addLine(
                "$quantity X " . $productName,
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
