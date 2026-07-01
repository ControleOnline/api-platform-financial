<?php

namespace ControleOnline\Tests\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ControleOnline\Doctrine\Extension\InvoiceSecurityExtension;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\Wallet;
use ControleOnline\Service\InvoiceService;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class InvoiceSecurityExtensionTest extends TestCase
{
    public function testAppliesSecurityFilterToInvoiceCollection(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['invoice']);

        $invoiceService->expects(self::once())
            ->method('securityFilter')
            ->with($queryBuilder, Invoice::class, 'api_platform', 'invoice');

        $extension = new InvoiceSecurityExtension($invoiceService);
        $extension->applyToCollection(
            $queryBuilder,
            $this->createMock(QueryNameGeneratorInterface::class),
            Invoice::class
        );
    }

    public function testAppliesSecurityFilterToInvoiceItem(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['invoice']);

        $invoiceService->expects(self::once())
            ->method('securityFilter')
            ->with($queryBuilder, Invoice::class, 'api_platform', 'invoice');

        $extension = new InvoiceSecurityExtension($invoiceService);
        $extension->applyToItem(
            $queryBuilder,
            $this->createMock(QueryNameGeneratorInterface::class),
            Invoice::class,
            ['id' => 1]
        );
    }

    public function testIgnoresOtherResources(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $invoiceService->expects(self::never())
            ->method('securityFilter');

        $extension = new InvoiceSecurityExtension($invoiceService);
        $extension->applyToCollection(
            $queryBuilder,
            $this->createMock(QueryNameGeneratorInterface::class),
            Wallet::class
        );
    }
}
