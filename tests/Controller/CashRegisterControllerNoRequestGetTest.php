<?php

namespace ControleOnline\Tests\Controller;

use PHPUnit\Framework\TestCase;

class CashRegisterControllerNoRequestGetTest extends TestCase
{
    public function testIncomeAndMonthlyStatementsUseQueryBagNotRequestGet(): void
    {
        $file = dirname(__DIR__, 2) . '/src/Controller/CashRegisterController.php';
        $this->assertFileExists($file);
        $source = file_get_contents($file);

        $this->assertDoesNotMatchRegularExpression(
            '/\$request->get\(/',
            $source,
            'CashRegisterController must not call Request::get()'
        );
        $this->assertMatchesRegularExpression(
            '/\$year\s*=\s*\$request->query->get\('year'\);/',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/\$month\s*=\s*\$request->query->get\('month'\);/',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/\$people\s*=\s*\$request->query->get\('people'\);/',
            $source
        );
        $this->assertSame(
            2,
            substr_count($source, "$request->query->get('year')")
        );
    }
}
