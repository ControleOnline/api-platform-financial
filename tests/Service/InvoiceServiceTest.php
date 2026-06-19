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
    public function testCancelSingleOrderInvoicesForCanceledOrderCancelsUnitaryInvoice(): void
    {
        $service = $this->createServiceWithoutConstructor();
        $order = new Order();
        $order->setStatus($this->createStatus('canceled', 'canceled', 'order'));

        $invoice = new Invoice();
        $invoice->setStatus($this->createStatus('pending', 'waiting payment', 'invoice'));

        $this->linkOrderToInvoice($order, $invoice, 50.05);

        $canceledInvoiceStatus = $this->createStatus('canceled', 'canceled', 'invoice');
        $canceledInvoices = $service->cancelSingleOrderInvoicesForCanceledOrder(
            $order,
            $canceledInvoiceStatus
        );

        self::assertSame([$invoice], $canceledInvoices);
        self::assertSame($canceledInvoiceStatus, $invoice->getStatus());
    }

    public function testCancelSingleOrderInvoicesForCanceledOrderKeepsGroupedInvoiceActive(): void
    {
        $service = $this->createServiceWithoutConstructor();
        $order = new Order();
        $order->setStatus($this->createStatus('canceled', 'canceled', 'order'));

        $otherOrder = new Order();
        $otherOrder->setStatus($this->createStatus('open', 'open', 'order'));

        $invoice = new Invoice();
        $pendingInvoiceStatus = $this->createStatus('pending', 'waiting payment', 'invoice');
        $invoice->setStatus($pendingInvoiceStatus);

        $this->linkOrderToInvoice($order, $invoice, 36.65);
        $this->linkOrderToInvoice($otherOrder, $invoice, 50.05);

        $canceledInvoices = $service->cancelSingleOrderInvoicesForCanceledOrder(
            $order,
            $this->createStatus('canceled', 'canceled', 'invoice')
        );

        self::assertSame([], $canceledInvoices);
        self::assertSame($pendingInvoiceStatus, $invoice->getStatus());
    }

    public function testCancelSingleOrderInvoicesForCanceledOrderIgnoresActiveOrders(): void
    {
        $service = $this->createServiceWithoutConstructor();
        $order = new Order();
        $order->setStatus($this->createStatus('open', 'open', 'order'));

        $invoice = new Invoice();
        $pendingInvoiceStatus = $this->createStatus('pending', 'waiting payment', 'invoice');
        $invoice->setStatus($pendingInvoiceStatus);

        $this->linkOrderToInvoice($order, $invoice, 49.90);

        $canceledInvoices = $service->cancelSingleOrderInvoicesForCanceledOrder(
            $order,
            $this->createStatus('canceled', 'canceled', 'invoice')
        );

        self::assertSame([], $canceledInvoices);
        self::assertSame($pendingInvoiceStatus, $invoice->getStatus());
    }

    public function testPayOrderConvertsPaidCartToSaleAndClosesWhenNothingIsPending(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $statusService = $this->createMock(StatusService::class);
        $queueService = $this->createMock(OrderProductQueueService::class);
        $closedStatus = $this->createStatus('closed', 'closed', 'order');

        $provider = $this->createMock(\ControleOnline\Entity\People::class);
        $provider
            ->method('getId')
            ->willReturn(7);

        $order = new Order();
        $order->setProvider($provider);
        $order->setStatus($this->createStatus('open', 'open', 'order'));
        $order->setOrderType(OrderService::ORDER_TYPE_CART);
        $order->setPrice(42.50);
        $this->setEntityId(Order::class, $order, 501);

        $invoice = new Invoice();
        $invoice->setStatus($this->createStatus('closed', 'paid', 'invoice'));
        $invoice->setPrice(42.50);
        $this->linkOrderToInvoice($order, $invoice, 42.50);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'findBy'])
            ->getMock();
        $repository
            ->expects(self::once())
            ->method('find')
            ->with(501)
            ->willReturn($order);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['mainOrderId' => 501])
            ->willReturn([]);

        $entityManager
            ->expects(self::exactly(2))
            ->method('getRepository')
            ->with(Order::class)
            ->willReturn($repository);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($order);
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $statusService
            ->expects(self::once())
            ->method('discoveryStatus')
            ->with('closed', 'closed', 'order')
            ->willReturn($closedStatus);

        $orderService = $this->buildOrderServiceForPayment(
            $entityManager,
            $statusService,
            $queueService
        );
        $orderService
            ->expects(self::once())
            ->method('dispatchOrderCreated')
            ->with($order);

        $queueService
            ->expects(self::once())
            ->method('syncByOrderStatus')
            ->with($order);

        $service = $this->buildInvoiceServiceForPayment(
            $entityManager,
            $statusService,
            $orderService,
            $queueService
        );

        $service->payOrder($order);

        self::assertSame(OrderService::ORDER_TYPE_SALE, $order->getOrderType());
        self::assertSame($closedStatus, $order->getStatus());
    }

    public function testPayOrderKeepsPreparingWhenDeliveryIsPending(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $statusService = $this->createMock(StatusService::class);
        $queueService = $this->createMock(OrderProductQueueService::class);
        $preparingStatus = $this->createStatus('open', 'preparing', 'order');

        $provider = $this->createMock(\ControleOnline\Entity\People::class);
        $provider
            ->method('getId')
            ->willReturn(7);

        $order = new Order();
        $order->setProvider($provider);
        $order->setStatus($this->createStatus('open', 'open', 'order'));
        $order->setOrderType(OrderService::ORDER_TYPE_CART);
        $order->setPrice(42.50);
        $order->setAddressDestination($this->createMock(Address::class));
        $this->setEntityId(Order::class, $order, 502);

        $invoice = new Invoice();
        $invoice->setStatus($this->createStatus('closed', 'paid', 'invoice'));
        $invoice->setPrice(42.50);
        $this->linkOrderToInvoice($order, $invoice, 42.50);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'findBy'])
            ->getMock();
        $repository
            ->expects(self::once())
            ->method('find')
            ->with(502)
            ->willReturn($order);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['mainOrderId' => 502])
            ->willReturn([]);

        $entityManager
            ->expects(self::exactly(2))
            ->method('getRepository')
            ->with(Order::class)
            ->willReturn($repository);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($order);
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $statusService
            ->expects(self::once())
            ->method('discoveryStatus')
            ->with('open', 'preparing', 'order')
            ->willReturn($preparingStatus);

        $orderService = $this->buildOrderServiceForPayment(
            $entityManager,
            $statusService,
            $queueService
        );
        $orderService
            ->expects(self::once())
            ->method('dispatchOrderCreated')
            ->with($order);

        $queueService
            ->expects(self::once())
            ->method('syncByOrderStatus')
            ->with($order);

        $service = $this->buildInvoiceServiceForPayment(
            $entityManager,
            $statusService,
            $orderService,
            $queueService
        );

        $service->payOrder($order);

        self::assertSame(OrderService::ORDER_TYPE_SALE, $order->getOrderType());
        self::assertSame($preparingStatus, $order->getStatus());
    }

    public function testPayOrderKeepsPreparingWhenQueueIsPending(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $statusService = $this->createMock(StatusService::class);
        $queueService = $this->createMock(OrderProductQueueService::class);
        $preparingStatus = $this->createStatus('open', 'preparing', 'order');
        $queueStatus = $this->createStatus('open', 'open', 'order_product_queue');

        $provider = $this->createMock(\ControleOnline\Entity\People::class);
        $provider
            ->method('getId')
            ->willReturn(7);

        $order = new Order();
        $order->setProvider($provider);
        $order->setStatus($this->createStatus('open', 'open', 'order'));
        $order->setOrderType(OrderService::ORDER_TYPE_CART);
        $order->setPrice(42.50);
        $this->setEntityId(Order::class, $order, 503);

        $orderProduct = new OrderProduct();
        $orderProduct->setOrder($order);
        $order->addOrderProduct($orderProduct);

        $orderProductQueue = new OrderProductQueue();
        $orderProductQueue->setStatus($queueStatus);
        $orderProduct->addOrderProductQueue($orderProductQueue);

        $invoice = new Invoice();
        $invoice->setStatus($this->createStatus('closed', 'paid', 'invoice'));
        $invoice->setPrice(42.50);
        $this->linkOrderToInvoice($order, $invoice, 42.50);

        $repository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'findBy'])
            ->getMock();
        $repository
            ->expects(self::once())
            ->method('find')
            ->with(503)
            ->willReturn($order);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['mainOrderId' => 503])
            ->willReturn([]);

        $entityManager
            ->expects(self::exactly(2))
            ->method('getRepository')
            ->with(Order::class)
            ->willReturn($repository);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($order);
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $statusService
            ->expects(self::once())
            ->method('discoveryStatus')
            ->with('open', 'preparing', 'order')
            ->willReturn($preparingStatus);

        $orderService = $this->buildOrderServiceForPayment(
            $entityManager,
            $statusService,
            $queueService
        );
        $orderService
            ->expects(self::once())
            ->method('dispatchOrderCreated')
            ->with($order);

        $queueService
            ->expects(self::once())
            ->method('syncByOrderStatus')
            ->with($order);

        $service = $this->buildInvoiceServiceForPayment(
            $entityManager,
            $statusService,
            $orderService,
            $queueService
        );

        $service->payOrder($order);

        self::assertSame(OrderService::ORDER_TYPE_SALE, $order->getOrderType());
        self::assertSame($preparingStatus, $order->getStatus());
    }

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
                $requestStack,
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
