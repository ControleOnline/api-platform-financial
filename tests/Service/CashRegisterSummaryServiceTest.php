<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Service\CashRegisterSummaryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CashRegisterSummaryServiceTest extends TestCase
{
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-06 15:30:00'); // Friday
    }

    public function testTodayAndYesterday(): void
    {
        [$s, $e] = CashRegisterSummaryService::resolvePeriod('today', null, null, $this->now());
        self::assertSame(['2026-11-06', '2026-11-07'], [$s->format('Y-m-d'), $e->format('Y-m-d')]);
        [$s, $e] = CashRegisterSummaryService::resolvePeriod('yesterday', null, null, $this->now());
        self::assertSame(['2026-11-05', '2026-11-06'], [$s->format('Y-m-d'), $e->format('Y-m-d')]);
    }

    public function testWeekStartsOnMonday(): void
    {
        [$s, $e] = CashRegisterSummaryService::resolvePeriod('week', null, null, $this->now());
        self::assertSame(['2026-11-02', '2026-11-09'], [$s->format('Y-m-d'), $e->format('Y-m-d')]);
    }

    public function testMonthAndCustomAreInclusive(): void
    {
        [$s, $e] = CashRegisterSummaryService::resolvePeriod('month', null, null, $this->now());
        self::assertSame(['2026-11-01', '2026-12-01'], [$s->format('Y-m-d'), $e->format('Y-m-d')]);
        [$s, $e] = CashRegisterSummaryService::resolvePeriod('custom', '2026-10-01', '2026-10-31');
        self::assertSame(['2026-10-01', '2026-11-01'], [$s->format('Y-m-d'), $e->format('Y-m-d')]);
    }

    public function testInvalidPeriodsAreRejected(): void
    {
        foreach ([['custom', null, null], ['custom', '2026-10-31', '2026-10-01'], ['foo', null, null]] as [$p, $f, $t]) {
            try {
                CashRegisterSummaryService::resolvePeriod($p, $f, $t);
                self::fail('expected exception');
            } catch (BadRequestHttpException) {
                self::assertTrue(true);
            }
        }
    }

    public function testFormatTotalsByPaymentType(): void
    {
        $rows = [
            ['payment_type_id' => 1, 'payment_type' => 'Pix', 'inflow' => '100.50', 'withdrawal' => '0', 'quantity' => '3'],
            ['payment_type_id' => 2, 'payment_type' => 'Dinheiro', 'inflow' => '50', 'withdrawal' => '20', 'quantity' => '2'],
        ];
        $r = CashRegisterSummaryService::format($rows, new \DateTimeImmutable('2026-11-06'), new \DateTimeImmutable('2026-11-07'), false, 6);
        self::assertSame(6, $r['userId']);
        self::assertSame('user', $r['scope']);
        self::assertSame('2026-11-06', $r['to']);
        self::assertSame(30.0, $r['payments'][1]['balance']);
        self::assertSame(150.5, $r['total']['inflow']);
        self::assertSame(130.5, $r['total']['balance']);
        self::assertSame(5, $r['total']['quantity']);
    }

    public function testDevicesAreGroupedAndNoDeviceRowKeepsTheTotal(): void
    {
        $devices = CashRegisterSummaryService::formatDevices([
            ['device_id' => '4', 'device' => 'abcdef123456', 'alias' => 'Balcao', 'inflow' => '80.25', 'withdrawal' => '10', 'quantity' => '3'],
            ['device_id' => null, 'device' => null, 'alias' => null, 'inflow' => '19.75', 'withdrawal' => '0', 'quantity' => '1'],
        ]);

        self::assertSame('Balcao (123456)', $devices[0]['label']);
        self::assertSame(70.25, $devices[0]['balance']);
        self::assertNull($devices[1]['deviceId']);
        self::assertNull($devices[1]['label']);
        self::assertSame(90.0, round(array_sum(array_column($devices, 'balance')), 2));
    }

    public function testDeviceLabelFallsBackToIdentifier(): void
    {
        self::assertSame('123456', CashRegisterSummaryService::deviceLabel(1, '', 'abcdef123456'));
        self::assertSame('7', CashRegisterSummaryService::deviceLabel(7, null, null));
    }

    public function testCrossBreakdownNestsSubCardsThatAddUpToTheParent(): void
    {
        $result = [
            'payments' => [
                ['paymentTypeId' => 1, 'paymentType' => 'Pix', 'balance' => 100.0],
                ['paymentTypeId' => null, 'paymentType' => '-', 'balance' => 5.0],
            ],
            'devices' => [
                ['deviceId' => 4, 'balance' => 80.0],
                ['deviceId' => null, 'balance' => 25.0],
            ],
        ];
        $cross = [
            ['payment_type_id' => '1', 'payment_type' => 'Pix', 'device_id' => '4', 'device' => 'abcdef123456', 'alias' => 'Balcao', 'inflow' => '80', 'withdrawal' => '0', 'quantity' => '2'],
            ['payment_type_id' => '1', 'payment_type' => 'Pix', 'device_id' => null, 'device' => null, 'alias' => null, 'inflow' => '20', 'withdrawal' => '0', 'quantity' => '1'],
            ['payment_type_id' => null, 'payment_type' => '-', 'device_id' => null, 'device' => null, 'alias' => null, 'inflow' => '5', 'withdrawal' => '0', 'quantity' => '1'],
        ];

        $r = CashRegisterSummaryService::attachCross($result, $cross);

        self::assertCount(2, $r['payments'][0]['devices']);
        self::assertSame(100.0, round(array_sum(array_column($r['payments'][0]['devices'], 'balance')), 2));
        self::assertSame('Balcao (123456)', $r['payments'][0]['devices'][0]['label']);
        self::assertNull($r['payments'][0]['devices'][1]['deviceId']);
        self::assertSame(5.0, $r['payments'][1]['devices'][0]['balance']);
        self::assertSame(['Pix'], array_column($r['devices'][0]['payments'], 'paymentType'));
        self::assertSame(25.0, round(array_sum(array_column($r['devices'][1]['payments'], 'balance')), 2));
    }

    public function testCrossBreakdownDefaultsToEmptyLists(): void
    {
        $r = CashRegisterSummaryService::attachCross(
            ['payments' => [['paymentTypeId' => 9]], 'devices' => [['deviceId' => 9]]],
            []
        );
        self::assertSame([], $r['payments'][0]['devices']);
        self::assertSame([], $r['devices'][0]['payments']);
    }
}
