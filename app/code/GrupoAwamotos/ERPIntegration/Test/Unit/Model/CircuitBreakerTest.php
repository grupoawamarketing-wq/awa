<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreakerState;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreakerOpenException;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;
    private CacheInterface|MockObject $cache;
    private LoggerInterface|MockObject $logger;

    /** @var array<string, string> In-memory cache store */
    private array $cacheStore = [];

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheStore = [];

        // Wire up cache mock to behave like a real cache
        $this->cache->method('load')
            ->willReturnCallback(function (string $key) {
                return $this->cacheStore[$key] ?? false;
            });

        $this->cache->method('save')
            ->willReturnCallback(function (string $data, string $key) {
                $this->cacheStore[$key] = $data;
                return true;
            });

        $this->breaker = new CircuitBreaker($this->cache, $this->logger, 'test_service');
    }

    // ─── Initial state ───────────────────────────────────────────────

    public function testInitialStateIsClosed(): void
    {
        $this->assertEquals(CircuitBreakerState::CLOSED, $this->breaker->getState());
    }

    public function testIsAvailableWhenClosed(): void
    {
        $this->assertTrue($this->breaker->isAvailable());
    }

    // ─── Recording failures ──────────────────────────────────────────

    public function testSingleFailureKeepsCircuitClosed(): void
    {
        $this->breaker->recordFailure(new \RuntimeException('fail'));

        $this->assertEquals(CircuitBreakerState::CLOSED, $this->breaker->getState());
        $this->assertTrue($this->breaker->isAvailable());
    }

    public function testFiveConsecutiveFailuresOpenCircuit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException("fail #{$i}"));
        }

        $this->assertEquals(CircuitBreakerState::OPEN, $this->breaker->getState());
        $this->assertFalse($this->breaker->isAvailable());
    }

    public function testFourFailuresKeepsCircuitClosed(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $this->assertEquals(CircuitBreakerState::CLOSED, $this->breaker->getState());
    }

    public function testFailureWithNullException(): void
    {
        $this->breaker->recordFailure(null);
        $this->assertEquals(CircuitBreakerState::CLOSED, $this->breaker->getState());
    }

    // ─── Recording successes ─────────────────────────────────────────

    public function testSuccessResetsFailureCount(): void
    {
        // 4 failures (one below threshold)
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        // Success resets
        $this->breaker->recordSuccess();

        // Another 4 failures should NOT open (count reset by success)
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $this->assertEquals(CircuitBreakerState::CLOSED, $this->breaker->getState());
    }

    // ─── Open → Half-open transition ─────────────────────────────────

    public function testOpenCircuitBlocksRequests(): void
    {
        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $this->assertFalse($this->breaker->isAvailable());
    }

    public function testOpenCircuitTransitionsToHalfOpenAfterTimeout(): void
    {
        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        // Simulate timeout by manipulating cache data
        $cacheKey = 'erp_circuit_breaker_test_service';
        $data = json_decode($this->cacheStore[$cacheKey], true);
        $data['opened_at'] = time() - 61; // 61 seconds ago (> 60s timeout)
        $this->cacheStore[$cacheKey] = json_encode($data);

        // Now isAvailable should transition to half-open and return true
        $this->assertTrue($this->breaker->isAvailable());
        $this->assertEquals(CircuitBreakerState::HALF_OPEN, $this->breaker->getState());
    }

    public function testOpenCircuitStaysOpenBeforeTimeout(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        // opened_at is now(), so timeout hasn't passed
        $this->assertFalse($this->breaker->isAvailable());
    }

    // ─── Half-open behavior ──────────────────────────────────────────

    public function testHalfOpenClosesAfterThreeSuccesses(): void
    {
        // Go to half-open
        $this->goToHalfOpen();

        // 3 successes should close the circuit
        $this->breaker->recordSuccess();
        $this->breaker->recordSuccess();
        $this->breaker->recordSuccess();

        $this->assertEquals(CircuitBreakerState::CLOSED, $this->breaker->getState());
    }

    public function testHalfOpenReopensOnFailure(): void
    {
        $this->goToHalfOpen();

        // Any failure in half-open immediately re-opens
        $this->breaker->recordFailure(new \RuntimeException('still failing'));

        $this->assertEquals(CircuitBreakerState::OPEN, $this->breaker->getState());
    }

    public function testHalfOpenTwoSuccessesThenFailureReopens(): void
    {
        $this->goToHalfOpen();

        $this->breaker->recordSuccess();
        $this->breaker->recordSuccess();
        // Not 3 yet, then failure
        $this->breaker->recordFailure(new \RuntimeException('fail'));

        $this->assertEquals(CircuitBreakerState::OPEN, $this->breaker->getState());
    }

    // ─── getStats ────────────────────────────────────────────────────

    public function testGetStatsReturnsAllKeys(): void
    {
        $stats = $this->breaker->getStats();

        $this->assertArrayHasKey('state', $stats);
        $this->assertArrayHasKey('failure_count', $stats);
        $this->assertArrayHasKey('success_count', $stats);
        $this->assertArrayHasKey('last_failure_time', $stats);
        $this->assertArrayHasKey('opened_at', $stats);
        $this->assertArrayHasKey('failure_threshold', $stats);
        $this->assertArrayHasKey('success_threshold', $stats);
        $this->assertArrayHasKey('open_timeout', $stats);
        $this->assertArrayHasKey('time_until_half_open', $stats);
    }

    public function testGetStatsReflectsFailureCount(): void
    {
        $this->breaker->recordFailure(new \RuntimeException('f1'));
        $this->breaker->recordFailure(new \RuntimeException('f2'));

        $stats = $this->breaker->getStats();
        $this->assertEquals(2, $stats['failure_count']);
    }

    public function testGetStatsTimeUntilHalfOpenIsZeroWhenClosed(): void
    {
        $stats = $this->breaker->getStats();
        $this->assertEquals(0, $stats['time_until_half_open']);
    }

    public function testGetStatsTimeUntilHalfOpenIsPositiveWhenOpen(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $stats = $this->breaker->getStats();
        $this->assertGreaterThan(0, $stats['time_until_half_open']);
        $this->assertLessThanOrEqual(60, $stats['time_until_half_open']);
    }

    // ─── reset ───────────────────────────────────────────────────────

    public function testResetClosesOpenCircuit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }
        $this->assertEquals(CircuitBreakerState::OPEN, $this->breaker->getState());

        $this->breaker->reset();

        $this->assertEquals(CircuitBreakerState::CLOSED, $this->breaker->getState());
        $this->assertTrue($this->breaker->isAvailable());
    }

    public function testResetClearsState(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $this->breaker->reset();

        $stats = $this->breaker->getStats();
        $this->assertEquals(0, $stats['failure_count']);
        $this->assertEquals(0, $stats['success_count']);
        $this->assertNull($stats['opened_at']);
    }

    // ─── execute ─────────────────────────────────────────────────────

    public function testExecuteRunsOperationAndRecordsSuccess(): void
    {
        $result = $this->breaker->execute(fn() => 'ok');

        $this->assertEquals('ok', $result);
    }

    public function testExecuteRethrowsExceptionAndRecordsFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->breaker->execute(function () {
            throw new \RuntimeException('boom');
        });
    }

    public function testExecuteCountsFailuresFromExceptions(): void
    {
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->breaker->execute(function () {
                    throw new \RuntimeException('fail');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertEquals(CircuitBreakerState::OPEN, $this->breaker->getState());
    }

    public function testExecuteThrowsCircuitBreakerOpenExceptionWhenOpen(): void
    {
        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $this->expectException(CircuitBreakerOpenException::class);
        $this->expectExceptionMessage('test_service');

        $this->breaker->execute(fn() => 'should not run');
    }

    public function testExecuteCallsFallbackWhenOpen(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $result = $this->breaker->execute(
            fn() => 'should not run',
            fn() => 'fallback value'
        );

        $this->assertEquals('fallback value', $result);
    }

    public function testExecuteDoesNotCallFallbackWhenClosed(): void
    {
        $fallbackCalled = false;

        $result = $this->breaker->execute(
            fn() => 'normal',
            function () use (&$fallbackCalled) {
                $fallbackCalled = true;
                return 'fallback';
            }
        );

        $this->assertEquals('normal', $result);
        $this->assertFalse($fallbackCalled);
    }

    // ─── Service name isolation ──────────────────────────────────────

    public function testDifferentServiceNamesAreIsolated(): void
    {
        $breaker2 = new CircuitBreaker($this->cache, $this->logger, 'other_service');

        // Open breaker for test_service
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        $this->assertFalse($this->breaker->isAvailable());
        $this->assertTrue($breaker2->isAvailable()); // other_service is unaffected
    }

    // ─── Logging ─────────────────────────────────────────────────────

    public function testLogsErrorWhenCircuitOpens(): void
    {
        $this->logger->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                $this->stringContains('Circuit opened'),
                $this->anything()
            );

        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }
    }

    public function testLogsInfoWhenCircuitCloses(): void
    {
        $this->goToHalfOpen();

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Circuit closed'),
                $this->anything()
            );

        $this->breaker->recordSuccess();
        $this->breaker->recordSuccess();
        $this->breaker->recordSuccess();
    }

    public function testLogsWarningWhenHalfOpenFailure(): void
    {
        $this->goToHalfOpen();

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                $this->stringContains('re-opened'),
                $this->anything()
            );

        $this->breaker->recordFailure(new \RuntimeException('still down'));
    }

    // ─── CircuitBreakerState ─────────────────────────────────────────

    public function testCircuitBreakerStateGetStates(): void
    {
        $states = CircuitBreakerState::getStates();
        $this->assertContains('closed', $states);
        $this->assertContains('open', $states);
        $this->assertContains('half_open', $states);
        $this->assertCount(3, $states);
    }

    public function testCircuitBreakerStateIsValid(): void
    {
        $this->assertTrue(CircuitBreakerState::isValid('closed'));
        $this->assertTrue(CircuitBreakerState::isValid('open'));
        $this->assertTrue(CircuitBreakerState::isValid('half_open'));
        $this->assertFalse(CircuitBreakerState::isValid('invalid'));
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function goToHalfOpen(): void
    {
        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(new \RuntimeException('fail'));
        }

        // Simulate timeout
        $cacheKey = 'erp_circuit_breaker_test_service';
        $data = json_decode($this->cacheStore[$cacheKey], true);
        $data['opened_at'] = time() - 61;
        $this->cacheStore[$cacheKey] = json_encode($data);

        // Trigger transition to half-open
        $this->breaker->isAvailable();
        $this->assertEquals(CircuitBreakerState::HALF_OPEN, $this->breaker->getState());
    }
}
