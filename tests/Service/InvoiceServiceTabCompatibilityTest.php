<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\{Invoice, Order, OrderInvoice, People, Status};
use ControleOnline\Service\{BraspagService, InvoiceService, OrderCommercialContextService, OrderPrintService, OrderProductQueueService, OrderService, PeopleService, StatusService};
use ControleOnline\Service\Client\WebsocketClient;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\SerializerInterface;

/** Executes the actual payment, financial-parent and promotion routines.
 * Persistence, device policy and external notifications are isolated boundaries.
 * The failing contracts intentionally expose incompatibilities before any fix.
 */
final class InvoiceServiceTabCompatibilityTest extends TestCase
{
    public function testPartialPaymentLeavesTabAndDraftUnchanged(): void
    {
        [$service, $root, $sale, $draft, $manager] = $this->fixture('tab', 4, true);
        $manager->expects(self::never())->method('flush');
        $service->payOrder($root);
        self::assertSame('tab', $root->getOrderType());
        self::assertSame('cart', $draft->getOrderType());
        self::assertSame('open', $root->getStatus()->getStatus());
        self::assertSame('preparing', $sale->getStatus()->getStatus());
    }

    public function testFullPaymentPreservesTheTabIdentity(): void
    {
        [$service, $root] = $this->fixture('tab', 10);
        $service->payOrder($root);
        self::assertSame('tab', $root->getOrderType(), 'Payment must not turn the parent tab into a sale.');
    }

    public function testFullPaymentDoesNotPromoteAnUnsentDraft(): void
    {
        [$service, $root, $sale, $draft] = $this->fixture('tab', 10, true);
        $service->payOrder($root);
        self::assertSame('cart', $draft->getOrderType(), 'An unsent draft must not be sold by tab payment.');
        self::assertSame('open', $draft->getStatus()->getStatus());
    }

    public function testFullPaymentIgnoresCanceledChildren(): void
    {
        [$service, $root, $sale, $draft, $manager, $canceled] = $this->fixture('tab', 10, false, true);
        $service->payOrder($root);
        self::assertSame('canceled', $canceled->getStatus()->getRealStatus());
        self::assertSame('cart', $canceled->getOrderType());
    }

    public function testPaymentThroughASentChildResolvesItsParentTab(): void
    {
        [$service, $root, $sale] = $this->fixture('tab', 10);
        $service->payOrder($sale);
        self::assertSame('paid', $sale->getStatus()->getStatus());
        self::assertSame('tab', $root->getOrderType());
    }

    public function testStandaloneCartPaymentStillPromotesTheCart(): void
    {
        [$service, $root] = $this->fixture('cart', 10);
        $service->payOrder($root);
        self::assertSame('sale', $root->getOrderType());
        self::assertSame('paid', $root->getStatus()->getStatus());
    }

    public function testPaymentUsesSentConsumptionInsteadOfAnOldDraftInclusiveTotal(): void
    {
        [$service, $root, $sale, $draft, $manager, $canceled] = $this->fixture('tab', 10, true, true);
        $root->setPrice(22);
        $service->payOrder($root);
        self::assertSame(10.0, (float) $root->getPrice());
        self::assertSame('paid', $root->getStatus()->getStatus());
        self::assertSame('cart', $draft->getOrderType());
        self::assertSame('canceled', $canceled->getStatus()->getRealStatus());
    }

    public function testAlreadyClosedSalesAreNotReopenedByTabPayment(): void
    {
        [$service, $root, $sale] = $this->fixture('tab', 10);
        $sale->setStatus($this->makeStatus('closed', 'closed'));
        $service->payOrder($root);
        self::assertSame('closed', $sale->getStatus()->getRealStatus());
        self::assertSame('closed', $sale->getStatus()->getStatus());
        self::assertSame('paid', $root->getStatus()->getStatus());
    }

    public function testDetailsAndFinancialBalanceUseTheSameConsumptionWithoutWriting(): void
    {
        [$service, $root, $sale, $draft, $manager, $canceled, $orderService] = $this->fixture('tab', 4, true, true);
        $root->setPrice(22);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::never())->method('flush');
        self::assertSame($root, $orderService->resolveFinancialOrder($sale));
        self::assertSame(10.0, (float) $root->getPrice());
        $root->setPrice(22);
        $orderService->prepareOrderDetailsRead($root);
        self::assertSame(10.0, (float) $root->getPrice());
    }

    public function testExistingPriceRefreshExcludesDraftsAndCanceledSales(): void
    {
        [$service, $root, $sale, $draft, $manager, $canceled, $orderService] = $this->fixture('tab', 4, true, true);
        $manager->expects(self::exactly(2))->method('persist')->with($root);
        $manager->expects(self::exactly(2))->method('flush');
        $draft->setPrice(5000);
        $orderService->syncSettlementOrderPrice($draft);
        self::assertSame(10.0, (float) $root->getPrice());
        $sale->setStatus($this->makeStatus('canceled', 'canceled'));
        $orderService->syncSettlementOrderPrice($sale);
        self::assertSame(0.0, (float) $root->getPrice());
    }

    public function testClosingAPaidTabDoesNotPromoteItsGroupingOrder(): void
    {
        [$service, $root, $sale, $draft, $manager, $canceled, $orderService] = $this->fixture('tab', 10);
        $service->payOrder($root);
        // The existing delivered action calls this promotion before applying closed.
        self::assertFalse($orderService->convertDraftOrderToSale($root));
        self::assertSame('tab', $root->getOrderType());
    }

    public function testClosedLinkedTabStaysClosedButItsSentSalesAreSettled(): void
    {
        [$service, $root, $sale, $draft, $manager, $canceled, $orderService, $linkedTab, $linkedSale] =
            $this->fixture('tab', 17, false, false, true);
        $service->payOrder($root);
        self::assertSame(17.0, (float) $root->getPrice());
        self::assertSame('closed', $linkedTab->getStatus()->getRealStatus());
        self::assertSame('tab', $linkedTab->getOrderType());
        self::assertSame('paid', $linkedSale->getStatus()->getStatus());
    }

    private function fixture(string $rootType, float $paid, bool $withDraft = false, bool $withCanceled = false, bool $withClosedTab = false): array
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $statusService = $this->createMock(StatusService::class);
        $statusService->method('discoveryStatus')->willReturnCallback(fn ($real, $name, $context) => $this->makeStatus($real, $name, $context));
        $queueService = $this->createMock(OrderProductQueueService::class);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/invoices'));
        $provider = new People();
        (new \ReflectionProperty(People::class, 'id'))->setValue($provider, 7);
        $root = $this->order(100, $rootType, 10, $provider);
        $sale = $this->order(101, 'sale', 10, $provider, $root, 'preparing');
        $draft = $this->order(102, 'cart', 5, $provider, $root);
        $canceled = $this->order(103, 'cart', 7, $provider, $root, 'canceled');
        $linkedTab = $this->order(104, 'tab', 999, $provider, $root, 'closed');
        $linkedSale = $this->order(105, 'sale', 7, $provider, $linkedTab, 'preparing');
        $children = $rootType === 'tab' ? [$sale] : [];
        if ($withDraft) $children[] = $draft;
        if ($withCanceled) $children[] = $canceled;
        if ($withClosedTab) $children[] = $linkedTab;
        $all = [100 => $root, 101 => $sale, 102 => $draft, 103 => $canceled, 104 => $linkedTab, 105 => $linkedSale];
        $repository = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()
            ->onlyMethods(['find', 'findBy', 'createQueryBuilder'])->getMock();
        $repository->method('find')->willReturnCallback(fn ($id) => $all[$id] ?? null);
        $repository->method('findBy')->willReturnCallback(fn ($criteria) => ($criteria['mainOrderId'] ?? null) === 100 ? $children
            : (($criteria['mainOrderId'] ?? null) === 104 && $withClosedTab ? [$linkedSale] : []));
        $manager->method('getRepository')->with(Order::class)->willReturn($repository);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);
        $builder = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)->disableOriginalConstructor()
            ->onlyMethods(['select', 'leftJoin', 'andWhere', 'setParameter', 'getQuery'])->getMock();
        foreach (['select', 'leftJoin', 'andWhere', 'setParameter'] as $method) $builder->method($method)->willReturnSelf();
        $builder->method('getQuery')->willReturn($query);
        $repository->method('createQueryBuilder')->willReturn($builder);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(function ($sql, $params) use ($children, $linkedSale, $withClosedTab) {
            $rows = $withClosedTab ? array_merge($children, [$linkedSale]) : $children;
            return array_map(static fn (Order $order) => [
                'id' => $order->getId(), 'order_type' => $order->getOrderType(),
                'price' => $order->getPrice(), 'real_status' => $order->getStatus()->getRealStatus(),
            ], array_values(array_filter($rows, static fn (Order $order) =>
                in_array($order->getMainOrderId(), $params['parent_ids'], true))));
        });
        $manager->method('getConnection')->willReturn($connection);
        $invoice = (new Invoice())->setPrice($paid)->setStatus($this->makeStatus('closed', 'paid', 'invoice'));
        $link = (new OrderInvoice())->setOrder($root)->setInvoice($invoice)->setRealPrice($paid);
        $root->addInvoice($link);
        $invoice->addOrder($link);
        $security = $this->createMock(TokenStorageInterface::class);
        $peopleService = $this->createMock(PeopleService::class);
        $orderService = $this->getMockBuilder(OrderService::class)->onlyMethods(['dispatchOrderCreated'])
            ->setConstructorArgs([$manager, $security, $peopleService, $statusService, $queueService,
                $this->createMock(WebsocketClient::class), $this->createMock(MessageBusInterface::class),
                $this->createMock(SerializerInterface::class), $requestStack,
                $this->createMock(OrderCommercialContextService::class)])->getMock();
        $service = new InvoiceService($manager, $security, $peopleService, $requestStack,
            $this->createMock(BraspagService::class), $statusService, $this->createMock(OrderPrintService::class),
            $orderService, $queueService);
        return [$service, $root, $sale, $draft, $manager, $canceled, $orderService, $linkedTab, $linkedSale];
    }

    private function order(int $id, string $type, float $price, People $provider, ?Order $parent = null, string $status = 'open'): Order
    {
        $order = (new Order())->setApp('POS')->setOrderType($type)->setPrice($price)->setProvider($provider)
            ->setStatus($this->makeStatus(in_array($status, ['closed', 'canceled'], true) ? $status : 'open', $status));
        (new \ReflectionProperty(Order::class, 'id'))->setValue($order, $id);
        if ($parent) $order->setMainOrder($parent)->setMainOrderId($parent->getId());
        return $order;
    }

    private function makeStatus(string $real, string $name, string $context = 'order'): Status
    {
        return (new Status())->setRealStatus($real)->setStatus($name)->setContext($context);
    }
}
