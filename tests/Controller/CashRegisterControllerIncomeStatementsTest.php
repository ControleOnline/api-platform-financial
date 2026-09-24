<?php

namespace ControleOnline\Tests\Controller;

use ControleOnline\Controller\CashRegisterController;
use ControleOnline\Entity\Invoice;
use ControleOnline\Entity\People;
use ControleOnline\Service\CashRegisterService;
use ControleOnline\Service\HydratorService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CashRegisterControllerIncomeStatementsTest extends TestCase
{
    public function testGetIncomeStatementsReadsQueryBagWithoutRequestGet(): void
    {
        $people = $this->createMock(People::class);
        $dre = [['account' => 'receita', 'total' => 10]];

        $peopleRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();
        $peopleRepository
            ->expects(self::once())
            ->method('find')
            ->with('123')
            ->willReturn($people);

        $invoiceRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['getDRE'])
            ->getMock();
        $invoiceRepository
            ->expects(self::once())
            ->method('getDRE')
            ->with($people, '2026', null)
            ->willReturn($dre);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::exactly(2))
            ->method('getRepository')
            ->willReturnMap([
                [Invoice::class, $invoiceRepository],
                [People::class, $peopleRepository],
            ]);

        $hydratorService = $this->createMock(HydratorService::class);
        $hydratorService
            ->expects(self::once())
            ->method('result')
            ->with($dre)
            ->willReturn(['response' => ['data' => $dre, 'success' => true]]);

        $controller = new CashRegisterController(
            $entityManager,
            $this->createMock(CashRegisterService::class),
            $hydratorService
        );

        $request = Request::create('/income_statements', 'GET', [
            'people' => '123',
            'year' => '2026',
        ]);

        self::assertSame('123', $request->query->get('people'));
        self::assertSame('2026', $request->query->get('year'));

        $source = file_get_contents((new \\ReflectionClass(CashRegisterController::class))->getFileName());
        self::assertDoesNotMatchRegularExpression(
            '/\\$request->get\\(/',
            $source,
            'CashRegisterController must not call Request::get()'
        );
        self::assertMatchesRegularExpression(
            '/\\$request->query->get\\(\\'year\\'\\)/',
            $source
        );

        $response = $controller->getIncomeStatements($request);
        $payload = json_decode($response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['response']['success']);
        self::assertSame($dre, $payload['response']['data']);
    }
}
