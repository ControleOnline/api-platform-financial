<?php

namespace ControleOnline\Tests\Service;

use ApiPlatform\Metadata\Operation;
use ControleOnline\Entity\Invoice;
use ControleOnline\Service\InvoiceFinancialSummaryResolver;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class InvoiceFinancialSummaryResolverTest extends TestCase
{
    public function testPaidAmountUsesInvoiceStatusPaidOnly(): void
    {
        $selectedExpressions = [];
        $parameters = [];

        $summaryQuery = $this->createStub(Query::class);
        $summaryQuery
            ->method('getOneOrNullResult')
            ->willReturn([
                'totalAmount' => '100.50',
                'paidAmount' => '80.25',
                'openAmount' => '20.25',
            ]);

        $summaryQueryBuilder = $this->createStub(QueryBuilder::class);
        $summaryQueryBuilder
            ->method('select')
            ->willReturnCallback(function (...$expressions) use (&$selectedExpressions, $summaryQueryBuilder) {
                $selectedExpressions = $expressions;

                return $summaryQueryBuilder;
            });
        $summaryQueryBuilder->method('from')->willReturnSelf();
        $summaryQueryBuilder->method('leftJoin')->willReturnSelf();
        $summaryQueryBuilder->method('andWhere')->willReturnSelf();
        $summaryQueryBuilder->method('expr')->willReturn(new Expr());
        $summaryQueryBuilder
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $summaryQueryBuilder) {
                $parameters[$name] = $value;

                return $summaryQueryBuilder;
            });
        $summaryQueryBuilder->method('getQuery')->willReturn($summaryQuery);

        $filteredIdsQueryBuilder = $this->createStub(QueryBuilder::class);
        $filteredIdsQueryBuilder
            ->method('getDQL')
            ->willReturn('SELECT filtered_invoice.id FROM '.Invoice::class.' filtered_invoice');
        $filteredIdsQueryBuilder
            ->method('getParameters')
            ->willReturn(new ArrayCollection());

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager
            ->method('createQueryBuilder')
            ->willReturn($summaryQueryBuilder);

        $resolver = new InvoiceFinancialSummaryResolver($manager);
        $summary = $resolver->resolve(
            $this->createStub(Operation::class),
            Invoice::class,
            [],
            $filteredIdsQueryBuilder
        );

        $selectedSql = implode(' ', $selectedExpressions);
        self::assertStringContainsString('summary_status.status = :paidStatus', $selectedSql);
        self::assertStringNotContainsString('summary_status.realStatus = :paidStatus', $selectedSql);
        self::assertSame('paid', $parameters['paidStatus'] ?? null);
        self::assertSame([
            'totalAmount' => 100.50,
            'paidAmount' => 80.25,
            'openAmount' => 20.25,
        ], $summary);
    }
}
