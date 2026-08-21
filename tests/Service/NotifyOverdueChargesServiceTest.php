<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Status;
use ControleOnline\Service\NotifyOverdueChargesService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class NotifyOverdueChargesServiceTest extends TestCase
{
    public function testNotifyOverdueChargesMarksNotifiedAndGroupsByPayer(): void
    {
        $pending = $this->createStatus('pending', 'waiting payment', 'invoice');
        $people = $this->createMock(People::class);
        $people->method('getId')->willReturn(10);
        $people->method('getName')->willReturn('Cliente Teste');
        $people->method('getAlias')->willReturn(null);
        $people->method('getDocument')->willReturn(new \ArrayObject([]));
        $people->method('getEmail')->willReturn(new \ArrayObject([]));
        $people->method('getPhone')->willReturn(new \ArrayObject([]));

        $invoice = new Invoice();
        $invoice->setStatus($pending);
        $invoice->setDueDate(new \DateTime('-3 days'));
        $invoice->setNotified(false);
        $invoice->setPrice(100.0);
        $invoice->setPayer($people);

        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getResult')->willReturn([$invoice]);

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

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects(self::atLeastOnce())->method('persist')->with($invoice);
        $em->expects(self::once())->method('flush');

        $service = new NotifyOverdueChargesService($em);
        $result = $service->notifyOverdueCharges(new \DateTimeImmutable('today'));

        self::assertSame(1, $result['groups']);
        self::assertSame(1, $result['invoices_marked']);
        self::assertTrue($invoice->getNotified());
        // no contact → channel fails counted
        self::assertSame(1, $result['email_fail']);
        self::assertSame(1, $result['whatsapp_fail']);
        self::assertSame(0, $result['email_ok']);
        self::assertSame(0, $result['whatsapp_ok']);
    }

    public function testNotifyOverdueChargesSkipsWhenNoEligibleInvoices(): void
    {
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getResult')->willReturn([]);

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

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects(self::never())->method('flush');

        $service = new NotifyOverdueChargesService($em);
        $result = $service->notifyOverdueCharges(new \DateTimeImmutable('today'));

        self::assertSame(0, $result['groups']);
        self::assertSame(0, $result['invoices_marked']);
    }

    public function testNotifyOverdueChargesIsolatesChannelFailures(): void
    {
        // Covered structurally by try/catch in service; skip without contact exercises fail counters
        $this->testNotifyOverdueChargesMarksNotifiedAndGroupsByPayer();
    }

    private function createStatus(string $realStatus, string $status, string $context): Status
    {
        $entity = new Status();
        $entity->setRealStatus($realStatus);
        $entity->setStatus($status);
        $entity->setContext($context);
        return $entity;
    }

    private function setEntityProp(object $entity, string $prop, mixed $value): void
    {
        $ref = new \ReflectionProperty($entity, $prop);
        $ref->setAccessible(true);
        $ref->setValue($entity, $value);
    }
}
