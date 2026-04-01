<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Test\Unit\Model\Forecast;

use GrupoAwamotos\SmartSuggestions\Model\Forecast\SalesProjection;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for Sales Projection (Forecast Service)
 *
 * @covers \GrupoAwamotos\SmartSuggestions\Model\Forecast\SalesProjection
 */
class SalesProjectionTest extends TestCase
{
    private SalesProjection $subject;
    private ConnectionInterface&MockObject $connectionMock;
    private LoggerInterface&MockObject $loggerMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(ConnectionInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->subject = new SalesProjection(
            $this->connectionMock,
            $this->loggerMock
        );
    }

    // ============ projectMonthClosing ============

    public function testProjectMonthClosingReturnsValidStructure(): void
    {
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));
        $lastYear = date('Y-m', strtotime('-1 year'));

        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql, array $params = []) use ($currentMonth, $lastMonth, $lastYear) {
                // Month sales query
                if (str_contains($sql, 'COALESCE(SUM')) {
                    $month = $params[0] ?? '';
                    if ($month === $currentMonth) {
                        return [['total' => 150000.00]];
                    }
                    if ($month === $lastMonth) {
                        return [['total' => 200000.00]];
                    }
                    if ($month === $lastYear) {
                        return [['total' => 180000.00]];
                    }
                    return [['total' => 0]];
                }
                // Day of week weights query
                if (str_contains($sql, 'DATEPART(WEEKDAY')) {
                    return [
                        ['day_of_week' => 1, 'avg_sales' => 5000],
                        ['day_of_week' => 2, 'avg_sales' => 8000],
                        ['day_of_week' => 3, 'avg_sales' => 9000],
                        ['day_of_week' => 4, 'avg_sales' => 8500],
                        ['day_of_week' => 5, 'avg_sales' => 9500],
                        ['day_of_week' => 6, 'avg_sales' => 7000],
                        ['day_of_week' => 7, 'avg_sales' => 3000],
                    ];
                }
                // Trend query
                if (str_contains($sql, 'last_30') && str_contains($sql, 'prev_30')) {
                    return [['last_30' => 250000, 'prev_30' => 230000]];
                }
                return [];
            });

        $result = $this->subject->projectMonthClosing();

        $this->assertArrayHasKey('current_month', $result);
        $this->assertArrayHasKey('days_passed', $result);
        $this->assertArrayHasKey('days_remaining', $result);
        $this->assertArrayHasKey('actual_sales', $result);
        $this->assertArrayHasKey('projection', $result);
        $this->assertArrayHasKey('confidence_interval', $result);
        $this->assertArrayHasKey('goal', $result);
        $this->assertArrayHasKey('comparison', $result);
        $this->assertArrayHasKey('trend', $result);
    }

    public function testProjectMonthClosingProjectionHasThreeScenarios(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COALESCE(SUM')) {
                    return [['total' => 100000.00]];
                }
                if (str_contains($sql, 'DATEPART')) {
                    return [];
                }
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 200000, 'prev_30' => 180000]];
                }
                return [];
            });

        $result = $this->subject->projectMonthClosing();

        $this->assertArrayHasKey('pessimistic', $result['projection']);
        $this->assertArrayHasKey('realistic', $result['projection']);
        $this->assertArrayHasKey('optimistic', $result['projection']);

        // Pessimistic <= realistic <= optimistic
        $this->assertLessThanOrEqual(
            $result['projection']['realistic'],
            $result['projection']['pessimistic']
        );
        $this->assertLessThanOrEqual(
            $result['projection']['optimistic'],
            $result['projection']['realistic']
        );
    }

    public function testProjectMonthClosingConfidenceInterval(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COALESCE(SUM')) {
                    return [['total' => 100000.00]];
                }
                if (str_contains($sql, 'DATEPART')) {
                    return [];
                }
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 200000, 'prev_30' => 200000]];
                }
                return [];
            });

        $result = $this->subject->projectMonthClosing();

        $this->assertArrayHasKey('lower', $result['confidence_interval']);
        $this->assertArrayHasKey('upper', $result['confidence_interval']);
        $this->assertLessThanOrEqual(
            $result['confidence_interval']['upper'],
            $result['confidence_interval']['lower']
        );
    }

    public function testProjectMonthClosingTrendDirection(): void
    {
        // Growing trend
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COALESCE(SUM')) {
                    return [['total' => 100000.00]];
                }
                if (str_contains($sql, 'DATEPART')) {
                    return [];
                }
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 250000, 'prev_30' => 200000]];
                }
                return [];
            });

        $result = $this->subject->projectMonthClosing();

        $this->assertEquals('up', $result['trend']['direction']);
        $this->assertGreaterThan(0, $result['trend']['percentage']);
    }

    public function testProjectMonthClosingReturnsEmptyProjectionOnException(): void
    {
        $this->connectionMock
            ->method('query')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Forecast Error'));

        $result = $this->subject->projectMonthClosing();

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(0, $result['actual_sales']);
    }

    public function testProjectMonthClosingGoalData(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COALESCE(SUM')) {
                    return [['total' => 150000.00]];
                }
                if (str_contains($sql, 'DATEPART')) {
                    return [];
                }
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 200000, 'prev_30' => 200000]];
                }
                return [];
            });

        $result = $this->subject->projectMonthClosing();

        $this->assertArrayHasKey('value', $result['goal']);
        $this->assertArrayHasKey('remaining', $result['goal']);
        $this->assertArrayHasKey('daily_target', $result['goal']);
        $this->assertArrayHasKey('achievable', $result['goal']);
        $this->assertIsBool($result['goal']['achievable']);
    }

    // ============ projectNextMonth ============

    public function testProjectNextMonthReturnsValidStructure(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'YEAR(p.DTPEDIDO)')) {
                    return [
                        ['year' => 2025, 'month' => 6, 'total' => 180000, 'orders' => 90, 'customers' => 50],
                        ['year' => 2025, 'month' => 7, 'total' => 200000, 'orders' => 95, 'customers' => 55],
                        ['year' => 2025, 'month' => 8, 'total' => 190000, 'orders' => 88, 'customers' => 48],
                        ['year' => 2025, 'month' => 9, 'total' => 210000, 'orders' => 100, 'customers' => 60],
                        ['year' => 2025, 'month' => 10, 'total' => 220000, 'orders' => 105, 'customers' => 62],
                        ['year' => 2025, 'month' => 11, 'total' => 230000, 'orders' => 110, 'customers' => 65],
                        ['year' => 2025, 'month' => 12, 'total' => 280000, 'orders' => 130, 'customers' => 75],
                        ['year' => 2026, 'month' => 1, 'total' => 200000, 'orders' => 95, 'customers' => 56],
                        ['year' => 2026, 'month' => 2, 'total' => 210000, 'orders' => 100, 'customers' => 58],
                    ];
                }
                return [];
            });

        $result = $this->subject->projectNextMonth();

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('projection', $result);
        $this->assertArrayHasKey('base_value', $result);
        $this->assertArrayHasKey('seasonal_factor', $result);
        $this->assertArrayHasKey('growth_factor', $result);
        $this->assertArrayHasKey('range', $result);
        $this->assertArrayHasKey('min', $result['range']);
        $this->assertArrayHasKey('max', $result['range']);
    }

    public function testProjectNextMonthProjectionIsPositive(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'YEAR(p.DTPEDIDO)')) {
                    return [
                        ['year' => 2026, 'month' => 1, 'total' => 200000, 'orders' => 95, 'customers' => 56],
                        ['year' => 2026, 'month' => 2, 'total' => 210000, 'orders' => 100, 'customers' => 58],
                    ];
                }
                return [];
            });

        $result = $this->subject->projectNextMonth();

        $this->assertGreaterThan(0, $result['projection']);
        $this->assertGreaterThan(0, $result['base_value']);
    }

    public function testProjectNextMonthRangeIsValid(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'YEAR(p.DTPEDIDO)')) {
                    return [
                        ['year' => 2026, 'month' => 1, 'total' => 200000, 'orders' => 95, 'customers' => 56],
                        ['year' => 2026, 'month' => 2, 'total' => 210000, 'orders' => 100, 'customers' => 58],
                    ];
                }
                return [];
            });

        $result = $this->subject->projectNextMonth();

        $this->assertLessThan($result['projection'], $result['range']['min']);
        $this->assertGreaterThan($result['projection'], $result['range']['max']);
    }

    public function testProjectNextMonthReturnsEmptyOnException(): void
    {
        $this->connectionMock
            ->method('query')
            ->willThrowException(new \RuntimeException('DB Error'));

        $this->loggerMock
            ->expects($this->atLeastOnce())
            ->method('error');

        $result = $this->subject->projectNextMonth();

        $this->assertEmpty($result);
    }

    public function testProjectNextMonthReturnsEmptyWhenNoHistory(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturn([]);

        $result = $this->subject->projectNextMonth();

        $this->assertEmpty($result);
    }

    // ============ getDailySalesTrend ============

    public function testGetDailySalesTrendReturnsValidStructure(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'CONVERT(VARCHAR(10)')) {
                    $data = [];
                    for ($i = 30; $i >= 1; $i--) {
                        $date = date('Y-m-d', strtotime("-{$i} days"));
                        $data[] = [
                            'date' => $date,
                            'total' => (float)rand(5000, 15000),
                            'orders' => rand(5, 20)
                        ];
                    }
                    return $data;
                }
                // Trend query
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 200000, 'prev_30' => 190000]];
                }
                return [];
            });

        $result = $this->subject->getDailySalesTrend(30);

        $this->assertArrayHasKey('dates', $result);
        $this->assertArrayHasKey('sales', $result);
        $this->assertArrayHasKey('orders', $result);
        $this->assertArrayHasKey('moving_avg', $result);
        $this->assertArrayHasKey('forecast', $result);
        $this->assertArrayHasKey('summary', $result);
    }

    public function testGetDailySalesTrendMovingAverage(): void
    {
        $dailyData = [];
        for ($i = 10; $i >= 1; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dailyData[] = ['date' => $date, 'total' => 10000.00, 'orders' => 10];
        }

        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) use ($dailyData) {
                if (str_contains($sql, 'CONVERT(VARCHAR(10)')) {
                    return $dailyData;
                }
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 100000, 'prev_30' => 100000]];
                }
                return [];
            });

        $result = $this->subject->getDailySalesTrend(10);

        // First 6 entries should be null (not enough data for 7-day MA)
        $this->assertNull($result['moving_avg'][0]);
        // 7th entry onwards should have value
        $this->assertNotNull($result['moving_avg'][6]);
    }

    public function testGetDailySalesTrendForecast7Days(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'CONVERT(VARCHAR(10)')) {
                    return [
                        ['date' => '2026-02-15', 'total' => 10000.00, 'orders' => 10]
                    ];
                }
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 200000, 'prev_30' => 200000]];
                }
                return [];
            });

        $result = $this->subject->getDailySalesTrend();

        $this->assertCount(7, $result['forecast']);
        foreach ($result['forecast'] as $forecast) {
            $this->assertArrayHasKey('date', $forecast);
            $this->assertArrayHasKey('projected', $forecast);
        }
    }

    public function testGetDailySalesTrendSummary(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'CONVERT(VARCHAR(10)')) {
                    return [
                        ['date' => '2026-02-18', 'total' => 5000.00, 'orders' => 5],
                        ['date' => '2026-02-19', 'total' => 15000.00, 'orders' => 15],
                        ['date' => '2026-02-20', 'total' => 10000.00, 'orders' => 10],
                    ];
                }
                if (str_contains($sql, 'last_30')) {
                    return [['last_30' => 200000, 'prev_30' => 200000]];
                }
                return [];
            });

        $result = $this->subject->getDailySalesTrend(3);

        $this->assertEquals(30000.00, $result['summary']['total']);
        $this->assertEquals(10000.00, $result['summary']['average']);
        $this->assertEquals(15000.00, $result['summary']['max']);
        $this->assertEquals(5000.00, $result['summary']['min']);
    }

    public function testGetDailySalesTrendReturnsEmptyOnException(): void
    {
        $this->connectionMock
            ->method('query')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $result = $this->subject->getDailySalesTrend();

        $this->assertEmpty($result);
    }

    // ============ getMonthlyComparison ============

    public function testGetMonthlyComparisonReturnsValidStructure(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturn([
                ['year' => 2025, 'month' => 3, 'total' => 180000, 'orders' => 90, 'customers' => 50],
                ['year' => 2025, 'month' => 4, 'total' => 200000, 'orders' => 100, 'customers' => 55],
            ]);

        $result = $this->subject->getMonthlyComparison();

        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertArrayHasKey('month', $item);
            $this->assertArrayHasKey('month_name', $item);
            $this->assertArrayHasKey('year', $item);
            $this->assertArrayHasKey('total', $item);
            $this->assertArrayHasKey('orders', $item);
            $this->assertArrayHasKey('customers', $item);
            $this->assertArrayHasKey('ticket_medio', $item);
        }
    }

    public function testGetMonthlyComparisonTicketMedio(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturn([
                ['year' => 2025, 'month' => 5, 'total' => 100000, 'orders' => 50, 'customers' => 30],
            ]);

        $result = $this->subject->getMonthlyComparison();

        $this->assertEquals(2000.00, $result[0]['ticket_medio']);
    }

    public function testGetMonthlyComparisonTicketMedioZeroOrders(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturn([
                ['year' => 2025, 'month' => 1, 'total' => 0, 'orders' => 0, 'customers' => 0],
            ]);

        $result = $this->subject->getMonthlyComparison();

        $this->assertEquals(0, $result[0]['ticket_medio']);
    }

    public function testGetMonthlyComparisonMonthNameInPortuguese(): void
    {
        $this->connectionMock
            ->method('query')
            ->willReturn([
                ['year' => 2025, 'month' => 1, 'total' => 100000, 'orders' => 50, 'customers' => 30],
                ['year' => 2025, 'month' => 12, 'total' => 280000, 'orders' => 130, 'customers' => 75],
            ]);

        $result = $this->subject->getMonthlyComparison();

        $this->assertEquals('Janeiro', $result[0]['month_name']);
        $this->assertEquals('Dezembro', $result[1]['month_name']);
    }

    public function testGetMonthlyComparisonReturnsEmptyOnException(): void
    {
        $this->connectionMock
            ->method('query')
            ->willThrowException(new \RuntimeException('Error'));

        $result = $this->subject->getMonthlyComparison();

        $this->assertEmpty($result);
    }
}
