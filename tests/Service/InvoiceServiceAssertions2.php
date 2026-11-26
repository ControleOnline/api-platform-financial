<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Address;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoice;
use ControleOnline\Entity\OrderProduct;
use ControleOnline\Entity\OrderProductQueue;
use ControleOnline\Entity\Status;
use ControleOnline\Service\BraspagService;
use ControleOnline\Service\InvoiceService;
use ControleOnline\Service\OrderPrintService;
use ControleOnline\Service\OrderProductQueueService;
use ControleOnline\Service\OrderService;
use ControleOnline\Service\PeopleService;
use ControleOnline\Service\StatusService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/** Methods shared by the original class; contracts and visibility are unchanged. */
trait InvoiceServiceAssertions2
{
    public function testMarkOverdueInvoicesUpdatesEligibleAndIsIdempotentOnExcludedStatuses(): void
    {
        $overdueStatus = $this->createStatus('overdue', 'em atraso', 'invoice');
        $pendingInvoice = new Invoice();
        $pendingInvoice->setStatus($this->createStatus('pending', 'waiting payment', 'invoice'));
        $pendingInvoice->setDueDate(new \DateTime('-2 days'));

        $paidInvoice = new Invoice();
        $paidInvoice->setStatus($this->createStatus('closed', 'paid', 'invoice'));
        $paidInvoice->setDueDate(new \DateTime('-2 days'));

        $alreadyOverdue = new Invoice();
        $alreadyOverdue->setStatus($overdueStatus);
        $alreadyOverdue->setDueDate(new \DateTime('-5 days'));

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([$pendingInvoice, $paidInvoice, $alreadyOverdue]);

        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'join', 'where', 'andWhere', 'setParameter', 'getQuery'])
            ->getMock();
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturn($qb);
        $entityManager->expects(self::atLeastOnce())->method('persist')->with($pendingInvoice);
        $entityManager->expects(self::once())->method('flush');

        $statusService = $this->createMock(StatusService::class);
        $statusService->expects(self::once())
            ->method('discoveryStatus')
            ->with('overdue', 'em atraso', 'invoice')
            ->willReturn($overdueStatus);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/invoices'));

        $markService = new \ControleOnline\Service\MarkOverdueInvoicesService($entityManager, $statusService);
        $service = new InvoiceService(
            $entityManager,
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(PeopleService::class),
            $requestStack,
            $this->createMock(BraspagService::class),
            $statusService,
            $this->createMock(OrderPrintService::class),
            $this->createMock(OrderService::class),
            $this->createMock(OrderProductQueueService::class),
            null,
            $markService,
            null
        );

        $result = $service->markOverdueInvoices(new \DateTimeImmutable('today'));

        self::assertSame(1, $result['updated']);
        self::assertSame(2, $result['skipped']);
        self::assertSame(0, $result['errors']);
        self::assertSame($overdueStatus, $pendingInvoice->getStatus());
        self::assertSame('closed', $paidInvoice->getStatus()->getRealStatus());
        self::assertSame($overdueStatus, $alreadyOverdue->getStatus());
    }
}
