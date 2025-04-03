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
        private PrintService $printService
    ) {}

    public function generateData(Device $device, People $provider)
    {
        $orderProductRepository = $this->entityManager->getRepository(OrderProduct::class);

        $queryBuilder = $orderProductRepository->createQueryBuilder('op')
            ->select('p.product AS product_name')
            ->addSelect('p.sku AS product_sku')
            ->addSelect('SUM(op.quantity) AS quantity')
            ->addSelect('SUM(op.price) AS order_product_price')
            ->addSelect('SUM(op.total) AS order_product_total')
            ->join('op.product', 'p')
            ->join('op.order', 'o')
            ->where('o.device = :device')
            ->andWhere('o.provider = :provider')
            ->andWhere('op.id > :minId')
            ->andWhere('op.id < :maxId')
            ->groupBy('p.id')
            ->orderBy('p.product', 'ASC');

        $queryBuilder
            ->setParameter('minId', 0)
            ->setParameter('maxId', 9999999999)
            ->setParameter('device', $device->getId())
            ->setParameter('provider', $provider->getId());

        return $queryBuilder->getQuery()->getArrayResult();
    }

    public function generatePrintData(Device $device, People $provider, string $printType, string $deviceType)
    {
        $this->generateData($device, $provider);
    }
}
