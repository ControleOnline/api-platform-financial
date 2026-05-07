<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Order;
use ControleOnline\Entity\OrderInvoice;
use ControleOnline\Entity\Status;
use ControleOnline\Service\InvoiceService;
use PHPUnit\Framework\TestCase;

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
}
