<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\People;
use ControleOnline\Service\PeopleService;
use ControleOnline\Service\WalletPaymentTypeService;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class WalletPaymentTypeServiceTest extends TestCase
{
    public function testSecurityFilterScopesToRequestedCompany(): void
    {
        $company = $this->createStub(People::class);
        $company->method('getId')->willReturn(3);

        $peopleService = $this->createMock(PeopleService::class);
        $peopleService->expects(self::once())
            ->method('getMyCompanies')
            ->willReturn([$company]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/wallet_payment_types', 'GET', [
            'people' => '/people/3',
        ]));

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getRootAliases',
                'getAllAliases',
                'innerJoin',
                'andWhere',
                'setParameter',
            ])
            ->getMock();

        $queryBuilder->method('getRootAliases')->willReturn(['walletPaymentType']);
        $queryBuilder->method('getAllAliases')->willReturn(['walletPaymentType']);

        $queryBuilder->expects(self::exactly(2))
            ->method('innerJoin')
            ->willReturnCallback(static fn () => $queryBuilder);

        $whereClauses = [];
        $parameters = [];
        $queryBuilder->expects(self::exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (mixed $expression) use (&$whereClauses, $queryBuilder) {
                $whereClauses[] = (string) $expression;

                return $queryBuilder;
            });
        $queryBuilder->expects(self::once())
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $queryBuilder) {
                $parameters[$name] = $value;

                return $queryBuilder;
            });

        $service = new WalletPaymentTypeService($peopleService, $requestStack);
        $service->securityFilter($queryBuilder, null, 'api_platform', 'walletPaymentType');

        self::assertSame([
            'walletPaymentTypeWalletSecurityFilter.people = :walletPaymentTypeCompany',
            'walletPaymentTypePaymentTypeSecurityFilter.people = :walletPaymentTypeCompany',
        ], $whereClauses);
        self::assertSame(3, $parameters['walletPaymentTypeCompany']);
    }

    public function testSecurityFilterBlocksCompaniesOutsideAccessibleScope(): void
    {
        $company = $this->createStub(People::class);
        $company->method('getId')->willReturn(3);

        $peopleService = $this->createMock(PeopleService::class);
        $peopleService->expects(self::once())
            ->method('getMyCompanies')
            ->willReturn([$company]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/wallet_payment_types', 'GET', [
            'people' => '/people/9',
        ]));

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getRootAliases',
                'getAllAliases',
                'innerJoin',
                'andWhere',
                'setParameter',
            ])
            ->getMock();

        $queryBuilder->method('getRootAliases')->willReturn(['walletPaymentType']);
        $queryBuilder->method('getAllAliases')->willReturn(['walletPaymentType']);
        $queryBuilder->expects(self::exactly(2))
            ->method('innerJoin')
            ->willReturnCallback(static fn () => $queryBuilder);
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->with('1 = 0')
            ->willReturnCallback(static fn () => $queryBuilder);
        $queryBuilder->expects(self::never())->method('setParameter');

        $service = new WalletPaymentTypeService($peopleService, $requestStack);
        $service->securityFilter($queryBuilder, null, 'api_platform', 'walletPaymentType');
    }
}
