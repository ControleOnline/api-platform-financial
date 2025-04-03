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
        private ConfigService $configService
    ) {}

    public function generateData(Device $device, People $provider)
    {
        $orderProductRepository = $this->entityManager->getRepository(OrderProduct::class);

        $queryBuilder = $orderProductRepository->createQueryBuilder('op')
            ->addSelect('p.product AS product_name')
            ->addSelect('p.description AS product_description')
            ->addSelect('p.sku AS product_sku')
            ->addSelect('SUM(op.quantity) AS quantity')
            ->addSelect('SUM(op.price) AS order_product_price')
            ->addSelect('SUM(op.total) AS order_product_total')
            ->join('op.product', 'p')
            ->join('op.order', 'o')
            ->join('o.invoice', 'oi')
            ->join('oi.invoice', 'i')
            ->andWhere('o.device = :device')
            ->andWhere('o.provider = :provider')
            ->groupBy('p.id')
            ->orderBy('p.product', 'ASC');

        $queryBuilder
            ->setParameter('device', $device->getId())
            ->setParameter('provider', $provider->getId());


        $deviceConfig = $device->getConfigs(true);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-open-id']))
            $queryBuilder->andWhere('i.id > :minId')
                ->setParameter('minId',  $deviceConfig['cash-wallet-open-id']);

        if ($deviceConfig && isset($deviceConfig['cash-wallet-closed-id']))
            $queryBuilder->andWhere('i.id < :maxId')
                ->setParameter('maxId',  $deviceConfig['cash-wallet-closed-id']);


        return $queryBuilder->getQuery()->getArrayResult();
    }

    public function generatePrintData(Device $device, People $provider, string $printType, string $deviceType)
    {
        $this->generateData($device, $provider);
    }
}
