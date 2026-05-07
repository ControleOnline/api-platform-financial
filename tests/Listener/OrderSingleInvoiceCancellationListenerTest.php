<?php

namespace ControleOnline\Tests\Listener;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\Status;
use ControleOnline\Listener\OrderSingleInvoiceCancellationListener;
use ControleOnline\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

class OrderSingleInvoiceCancellationListenerTest extends TestCase
{
    public function testOnFlushCancelsUnitaryInvoicesWhenOrderTransitionsToCanceled(): void
    {
        $order = new Order();
        $previousStatus = $this->createStatus('open', 'open', 'order');
        $nextStatus = $this->createStatus('canceled', 'canceled', 'order');
        $canceledInvoiceStatus = $this->createStatus('canceled', 'canceled', 'invoice');
        $invoice = new Invoice();
        $metadata = new ClassMetadata(Invoice::class);

        $statusRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $statusRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'context' => 'invoice',
                'realStatus' => 'canceled',
                'status' => 'canceled',
            ])
            ->willReturn($canceledInvoiceStatus);

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService
            ->expects(self::once())
            ->method('cancelSingleOrderInvoicesForCanceledOrder')
            ->with($order, $canceledInvoiceStatus)
            ->willReturn([$invoice]);

        $unitOfWork = $this->getMockBuilder(UnitOfWork::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getScheduledEntityUpdates',
                'getEntityChangeSet',
                'recomputeSingleEntityChangeSet',
            ])
            ->getMock();
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$order]);
        $unitOfWork
            ->expects(self::once())
            ->method('getEntityChangeSet')
            ->with($order)
            ->willReturn([
                'status' => [$previousStatus, $nextStatus],
            ]);
        $unitOfWork
            ->expects(self::once())
            ->method('recomputeSingleEntityChangeSet')
            ->with($metadata, $invoice);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Status::class)
            ->willReturn($statusRepository);
        $entityManager
            ->expects(self::once())
            ->method('getClassMetadata')
            ->with(Invoice::class)
            ->willReturn($metadata);

        $listener = new OrderSingleInvoiceCancellationListener($invoiceService);
        $listener->onFlush(new OnFlushEventArgs($entityManager));
    }

    private function createStatus(string $realStatus, string $status, string $context): Status
    {
        $entity = new Status();
        $entity->setRealStatus($realStatus);
        $entity->setStatus($status);
        $entity->setContext($context);

        return $entity;
    }
}
