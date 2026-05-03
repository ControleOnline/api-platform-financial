<?php

namespace ControleOnline\Tests\Entity;

use ControleOnline\Entity\Invoice;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    public function testInvoiceTypeDefaultsToInvoice(): void
    {
        $invoice = new Invoice();

        self::assertSame(Invoice::TYPE_INVOICE, $invoice->getInvoiceType());
    }

    public function testInvoiceTypeAcceptsKnownTypesAndNormalizesCase(): void
    {
        $invoice = new Invoice();
        $invoice->setInvoiceType('DISCOUNT');

        self::assertSame(Invoice::TYPE_DISCOUNT, $invoice->getInvoiceType());
    }

    public function testInvoiceTypeFallsBackToInvoiceForUnknownValues(): void
    {
        $invoice = new Invoice();
        $invoice->setInvoiceType('service_fee');

        self::assertSame(Invoice::TYPE_INVOICE, $invoice->getInvoiceType());
    }
}
