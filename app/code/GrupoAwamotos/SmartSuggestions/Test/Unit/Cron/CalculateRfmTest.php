<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Test\Unit\Cron;

use GrupoAwamotos\SmartSuggestions\Cron\CalculateRfm;
use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;
use GrupoAwamotos\SmartSuggestions\Helper\Config;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\RfmCache as RfmCacheResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CalculateRfm Cron Job
 *
 * @covers \GrupoAwamotos\SmartSuggestions\Cron\CalculateRfm
 */
class CalculateRfmTest extends TestCase
{
    private CalculateRfm $subject;
    private RfmCalculatorInterface&MockObject $rfmCalculatorMock;
    private Config&MockObject $configMock;
    private LoggerInterface&MockObject $loggerMock;
    private RfmCacheResource&MockObject $rfmCacheResourceMock;

    protected function setUp(): void
    {
        $this->rfmCalculatorMock = $this->createMock(RfmCalculatorInterface::class);
        $this->configMock = $this->createMock(Config::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->rfmCacheResourceMock = $this->createMock(RfmCacheResource::class);

        $this->subject = new CalculateRfm(
            $this->rfmCalculatorMock,
            $this->configMock,
            $this->loggerMock,
            $this->rfmCacheResourceMock
        );
    }

    public function testExecuteSkipsWhenModuleDisabled(): void
    {
        $this->configMock->method('isEnabled')->willReturn(false);

        $this->rfmCalculatorMock
            ->expects($this->never())
            ->method('calculateAll');

        $this->subject->execute();
    }

    public function testExecuteSkipsWhenRfmCronDisabled(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isRfmCronEnabled')->willReturn(false);

        $this->rfmCalculatorMock
            ->expects($this->never())
            ->method('calculateAll');

        $this->subject->execute();
    }

    public function testExecuteCalculatesAndCachesResults(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isRfmCronEnabled')->willReturn(true);

        $mockResults = [
            [
                'customer_id' => 1,
                'customer_name' => 'Cliente A',
                'cnpj' => '12345',
                'phone' => '16991234567',
                'city' => 'SP',
                'state' => 'SP',
                'r_score' => 5,
                'f_score' => 4,
                'm_score' => 5,
                'rfm_score' => '545',
                'segment' => 'Champions',
                'recency_days' => 3,
                'frequency' => 20,
                'monetary' => 50000.00,
                'last_purchase' => '2026-02-17'
            ],
        ];

        $this->rfmCalculatorMock
            ->expects($this->once())
            ->method('calculateAll')
            ->willReturn($mockResults);

        $this->rfmCacheResourceMock
            ->expects($this->once())
            ->method('bulkUpsert')
            ->with($this->isType('array'));

        $this->subject->execute();
    }

    public function testExecuteSkipsCacheWhenNoResults(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isRfmCronEnabled')->willReturn(true);

        $this->rfmCalculatorMock
            ->method('calculateAll')
            ->willReturn([]);

        $this->rfmCacheResourceMock
            ->expects($this->never())
            ->method('bulkUpsert');

        $this->subject->execute();
    }

    public function testExecuteLogsStartAndCompletion(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isRfmCronEnabled')->willReturn(true);

        $this->rfmCalculatorMock
            ->method('calculateAll')
            ->willReturn([]);

        $this->loggerMock
            ->expects($this->atLeast(2))
            ->method('info');

        $this->subject->execute();
    }

    public function testExecuteHandlesExceptionGracefully(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isRfmCronEnabled')->willReturn(true);

        $this->rfmCalculatorMock
            ->method('calculateAll')
            ->willThrowException(new \RuntimeException('Calculator error'));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('RFM calculation cron failed'),
                $this->arrayHasKey('error')
            );

        // Should not throw
        $this->subject->execute();
    }

    public function testExecuteLogsSegmentDistribution(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isRfmCronEnabled')->willReturn(true);

        $mockResults = [
            [
                'customer_id' => 1, 'customer_name' => 'A', 'cnpj' => '1',
                'city' => 'SP', 'state' => 'SP', 'r_score' => 5, 'f_score' => 5,
                'm_score' => 5, 'rfm_score' => '555', 'segment' => 'Champions',
                'recency_days' => 1, 'frequency' => 50, 'monetary' => 100000.00,
                'last_purchase' => '2026-02-19'
            ],
            [
                'customer_id' => 2, 'customer_name' => 'B', 'cnpj' => '2',
                'city' => 'RJ', 'state' => 'RJ', 'r_score' => 1, 'f_score' => 1,
                'm_score' => 1, 'rfm_score' => '111', 'segment' => 'Lost',
                'recency_days' => 365, 'frequency' => 1, 'monetary' => 100.00,
                'last_purchase' => '2025-02-20'
            ],
        ];

        $this->rfmCalculatorMock
            ->method('calculateAll')
            ->willReturn($mockResults);

        // Last info call should include segment distribution
        $this->loggerMock
            ->expects($this->atLeast(3))
            ->method('info');

        $this->subject->execute();
    }

    public function testExecuteCacheEntryHasRequiredFields(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isRfmCronEnabled')->willReturn(true);

        $mockResults = [
            [
                'customer_id' => 42,
                'customer_name' => '',
                'cnpj' => '99999',
                'phone' => null,
                'city' => 'Campinas',
                'state' => 'SP',
                'r_score' => 3,
                'f_score' => 3,
                'm_score' => 3,
                'rfm_score' => '333',
                'segment' => 'Loyal',
                'recency_days' => 30,
                'frequency' => 15,
                'monetary' => 25000.00,
                'last_purchase' => '2026-01-20'
            ],
        ];

        $this->rfmCalculatorMock
            ->method('calculateAll')
            ->willReturn($mockResults);

        $this->rfmCacheResourceMock
            ->expects($this->once())
            ->method('bulkUpsert')
            ->with($this->callback(function (array $entries) {
                $entry = $entries[0];
                // Empty customer_name should be replaced with fallback
                $this->assertEquals('Cliente #42', $entry['customer_name']);
                $this->assertEquals(42, $entry['erp_customer_id']);
                $this->assertEquals('Loyal', $entry['segment']);
                $this->assertArrayHasKey('calculated_at', $entry);
                return true;
            }));

        $this->subject->execute();
    }
}
