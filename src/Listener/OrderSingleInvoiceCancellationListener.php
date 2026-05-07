<?php

namespace ControleOnline\Listener;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\Status;
use ControleOnline\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;

class OrderSingleInvoiceCancellationListener
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        if (!$entityManager instanceof EntityManagerInterface) {
            return;
        }

        $unitOfWork = $entityManager->getUnitOfWork();
        $canceledInvoiceStatus = $this->resolveCanceledInvoiceStatus($entityManager);
        if (!$canceledInvoiceStatus instanceof Status) {
            return;
        }

        $invoiceMetadata = $entityManager->getClassMetadata(Invoice::class);

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Order || !$this->didOrderTransitionToCanceled($entity, $unitOfWork)) {
                continue;
            }

            foreach (
                $this->invoiceService->cancelSingleOrderInvoicesForCanceledOrder(
                    $entity,
                    $canceledInvoiceStatus
                ) as $invoice
            ) {
                $unitOfWork->recomputeSingleEntityChangeSet($invoiceMetadata, $invoice);
            }
        }
    }

    private function didOrderTransitionToCanceled(Order $order, UnitOfWork $unitOfWork): bool
    {
        $changeSet = $unitOfWork->getEntityChangeSet($order);
        if (!isset($changeSet['status']) || !is_array($changeSet['status'])) {
            return false;
        }

        $previousStatus = $changeSet['status'][0] ?? null;
        $nextStatus = $changeSet['status'][1] ?? null;

        return !$this->isCanceledStatus($previousStatus) && $this->isCanceledStatus($nextStatus);
    }

    private function resolveCanceledInvoiceStatus(EntityManagerInterface $entityManager): ?Status
    {
        $repository = $entityManager->getRepository(Status::class);

        foreach ([
            ['context' => 'invoice', 'realStatus' => 'canceled', 'status' => 'canceled'],
            ['context' => 'invoice', 'realStatus' => 'canceled'],
            ['context' => 'invoice', 'realStatus' => 'cancelled'],
        ] as $criteria) {
            $status = $repository->findOneBy($criteria);
            if ($status instanceof Status) {
                return $status;
            }
        }

        return null;
    }

    private function isCanceledStatus(mixed $status): bool
    {
        if (!$status instanceof Status) {
            return false;
        }

        $normalizedStatus = strtolower(trim((string) $status->getStatus()));
        $normalizedRealStatus = strtolower(trim((string) $status->getRealStatus()));

        return in_array($normalizedStatus, ['canceled', 'cancelled'], true)
            || in_array($normalizedRealStatus, ['canceled', 'cancelled'], true);
    }
}
