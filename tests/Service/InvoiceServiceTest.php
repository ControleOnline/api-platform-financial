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

class InvoiceServiceTest extends TestCase
{
    use InvoiceServiceAssertions1, InvoiceServiceAssertions2;


    private function createServiceWithoutConstructor(): InvoiceService
    {
        return (new \ReflectionClass(InvoiceService::class))->newInstanceWithoutConstructor();
    }

    private function createStatus(string $realStatus, string $status, string $context): Status
    {
        $entity = new Status();
        $entity->setRealStatus($realStatus);
        $entity->setStatus($status);
        $entity->setContext($context);

        return $entity;
    }

    private function linkOrderToInvoice(Order $order, Invoice $invoice, float $realPrice): void
    {
        $orderInvoice = new OrderInvoice();
        $orderInvoice->setOrder($order);
        $orderInvoice->setInvoice($invoice);
        $orderInvoice->setRealPrice($realPrice);

        $order->addInvoice($orderInvoice);
        $invoice->addOrder($orderInvoice);
    }

    private function buildInvoiceServiceForPayment(
        EntityManagerInterface $entityManager,
        StatusService $statusService,
        OrderService $orderService,
        OrderProductQueueService $orderProductQueueService
    ): InvoiceService {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/invoices'));

        return new InvoiceService(
            $entityManager,
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(PeopleService::class),
            $requestStack,
            $this->createMock(BraspagService::class),
            $statusService,
            $this->createMock(OrderPrintService::class),
            $orderService,
            $orderProductQueueService
        );
    }

    private function buildOrderServiceForPayment(
        EntityManagerInterface $entityManager,
        StatusService $statusService,
        OrderProductQueueService $orderProductQueueService
    ): OrderService {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/orders'));

        return $this->getMockBuilder(OrderService::class)
            ->onlyMethods(['dispatchOrderCreated'])
            ->setConstructorArgs([
                $entityManager,
                $this->createMock(TokenStorageInterface::class),
                $this->createMock(PeopleService::class),
                $statusService,
                $orderProductQueueService,
                $this->createMock(\ControleOnline\Service\Client\WebsocketClient::class),
                $this->createMock(MessageBusInterface::class),
                $this->createMock(\Symfony\Component\Serializer\SerializerInterface::class),
                $requestStack,
                $this->createMock(\ControleOnline\Service\OrderCommercialContextService::class),
                null,
            ])
            ->getMock();
    }

    private function setEntityId(string $className, object $entity, int $id): void
    {
        $property = new \ReflectionProperty($className, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
