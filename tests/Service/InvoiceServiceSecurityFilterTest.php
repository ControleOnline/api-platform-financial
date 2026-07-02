<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\People;
use ControleOnline\Service\BraspagService;
use ControleOnline\Service\InvoiceService;
use ControleOnline\Service\OrderPrintService;
use ControleOnline\Service\OrderProductQueueService;
use ControleOnline\Service\OrderService;
use ControleOnline\Service\PeopleService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class InvoiceServiceSecurityFilterTest extends TestCase
{
    public function testSecurityFilterGroupsCompanyAccessBeforeReceiverFilter(): void
    {
        $companies = [$this->createCompany(1), $this->createCompany(2)];
        $peopleService = $this->createMock(PeopleService::class);
        $peopleService
            ->expects(self::once())
            ->method('getMyCompanies')
            ->willReturn($companies);

        $service = $this->createService(
            $peopleService,
            Request::create('/invoices?receiver=%2Fpeople%2F7', 'GET')
        );

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['andWhere', 'setParameter'])
            ->getMock();

        $whereClauses = [];
        $parameters = [];

        $queryBuilder
            ->expects(self::exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (string $expression) use (&$whereClauses, $queryBuilder) {
                $whereClauses[] = $expression;

                return $queryBuilder;
            });

        $queryBuilder
            ->expects(self::exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
                $parameters[$name] = $value;

                return $queryBuilder;
            });

        $service->securityFilter($queryBuilder, InvoiceService::class, 'api_platform', 'invoice');

        self::assertSame(
            [
                '(invoice.payer IN(:companies) OR invoice.receiver IN(:companies))',
                'invoice.receiver IN(:receiver)',
            ],
            $whereClauses
        );
        self::assertSame([1, 2], $parameters['companies']);
        self::assertSame(7, $parameters['receiver']);
    }

    public function testSecurityFilterBlocksWhenNoCompaniesAreAccessible(): void
    {
        $peopleService = $this->createMock(PeopleService::class);
        $peopleService
            ->expects(self::once())
            ->method('getMyCompanies')
            ->willReturn([]);

        $service = $this->createService(
            $peopleService,
            Request::create('/invoices', 'GET')
        );

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['andWhere', 'setParameter'])
            ->getMock();

        $queryBuilder
            ->expects(self::once())
            ->method('andWhere')
            ->with('1 = 0')
            ->willReturnCallback(function () use ($queryBuilder) {
                return $queryBuilder;
            });
        $queryBuilder->expects(self::never())->method('setParameter');

        $service->securityFilter($queryBuilder, InvoiceService::class, 'api_platform', 'invoice');
    }

    private function createService(PeopleService $peopleService, Request $request): InvoiceService
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new InvoiceService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(TokenStorageInterface::class),
            $peopleService,
            $requestStack,
            $this->createMock(BraspagService::class),
            $this->createMock(StatusService::class),
            $this->createMock(OrderPrintService::class),
            $this->createMock(OrderService::class),
            $this->createMock(OrderProductQueueService::class)
        );
    }

    private function createCompany(int $id): People
    {
        $company = $this->createMock(People::class);
        $company
            ->method('getId')
            ->willReturn($id);

        return $company;
    }
}
