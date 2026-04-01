<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\Connection;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ConnectionTest extends TestCase
{
    private Connection $connection;
    private Helper|MockObject $helper;
    private LoggerInterface|MockObject $logger;
    private CircuitBreaker|MockObject $circuitBreaker;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->circuitBreaker = $this->createMock(CircuitBreaker::class);
        $this->circuitBreaker->method('isAvailable')->willReturn(true);

        $this->connection = new Connection($this->helper, $this->logger, $this->circuitBreaker);
    }

    public function testGetAvailableDriversReturnsArray(): void
    {
        $drivers = $this->connection->getAvailableDrivers();

        $this->assertIsArray($drivers);
    }

    public function testHasAvailableDriverChecksDrivers(): void
    {
        // This will depend on the actual PDO drivers installed
        $result = $this->connection->hasAvailableDriver();

        $this->assertIsBool($result);
    }

    public function testIsConnectedReturnsFalseInitially(): void
    {
        $this->assertFalse($this->connection->isConnected());
    }

    public function testDisconnectResetsConnection(): void
    {
        $this->connection->disconnect();

        $this->assertFalse($this->connection->isConnected());
    }

    public function testGetConnectionThrowsExceptionWhenNoDrivers(): void
    {
        // Create a connection instance that will have no drivers
        $connection = new class ($this->helper, $this->logger, $this->circuitBreaker) extends Connection {
            public function getAvailableDrivers(): array
            {
                return [];
            }

            public function hasAvailableDriver(): bool
            {
                return false;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nenhum driver SQL Server disponível');

        $connection->getConnection();
    }

    public function testTestConnectionReturnsFailureWhenNoDrivers(): void
    {
        // Create a connection instance with no drivers
        $connection = new class ($this->helper, $this->logger, $this->circuitBreaker) extends Connection {
            public function getAvailableDrivers(): array
            {
                return [];
            }

            public function hasAvailableDriver(): bool
            {
                return false;
            }
        };

        $result = $connection->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Nenhum driver', $result['message']);
        $this->assertArrayHasKey('required_extensions', $result);
        $this->assertArrayHasKey('installation_tips', $result);
    }

    /**
     * @dataProvider transientErrorProvider
     */
    public function testIsTransientErrorDetectsTransientErrors(string $message, int $code, bool $expected): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(Connection::class);
        $method = $reflection->getMethod('isTransientError');
        $method->setAccessible(true);

        $exception = new \PDOException($message, $code);

        $result = $method->invoke($this->connection, $exception);

        $this->assertEquals($expected, $result);
    }

    public static function transientErrorProvider(): array
    {
        return [
            'timeout error' => ['Connection timeout expired', -2, true],
            'connection problem' => ['Connection problem', -1, true],
            'network timeout' => ['The query timed out', 0, true],
            'connection reset' => ['Connection was reset', 10054, true],
            'temporary unavailable' => ['Server temporarily unavailable', 0, true],
            'deadlock' => ['Deadlock detected', 0, true],
            'normal sql error' => ['Syntax error in SQL', 0, false],
            'constraint violation' => ['Foreign key constraint violation', 0, false],
        ];
    }

    /**
     * @dataProvider backoffDelayProvider
     */
    public function testCalculateBackoffDelayReturnsReasonableValues(int $attempt, int $minExpected, int $maxExpected): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(Connection::class);
        $method = $reflection->getMethod('calculateBackoffDelay');
        $method->setAccessible(true);

        // Run multiple times to account for jitter
        $delays = [];
        for ($i = 0; $i < 100; $i++) {
            $delays[] = $method->invoke($this->connection, $attempt);
        }

        $avgDelay = array_sum($delays) / count($delays);

        // Check that average is within expected range (accounting for jitter)
        $this->assertGreaterThanOrEqual($minExpected * 0.7, $avgDelay);
        $this->assertLessThanOrEqual($maxExpected * 1.3, $avgDelay);
    }

    public static function backoffDelayProvider(): array
    {
        return [
            'first attempt' => [1, 75, 125],    // ~100ms
            'second attempt' => [2, 150, 250],  // ~200ms
            'third attempt' => [3, 300, 500],   // ~400ms
        ];
    }

    public function testBuildDsnForSqlsrvDriver(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(Connection::class);
        $method = $reflection->getMethod('buildDsn');
        $method->setAccessible(true);

        $dsn = $method->invoke($this->connection, 'sqlsrv', '192.168.1.1', 1433, 'testdb');

        $this->assertStringContainsString('sqlsrv:', $dsn);
        $this->assertStringContainsString('Server=192.168.1.1,1433', $dsn);
        $this->assertStringContainsString('Database=testdb', $dsn);
        $this->assertStringContainsString('TrustServerCertificate=1', $dsn);
    }

    public function testBuildDsnForDblibDriver(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(Connection::class);
        $method = $reflection->getMethod('buildDsn');
        $method->setAccessible(true);

        $dsn = $method->invoke($this->connection, 'dblib', '192.168.1.1', 1433, 'testdb');

        $this->assertStringContainsString('dblib:', $dsn);
        $this->assertStringContainsString('host=192.168.1.1:1433', $dsn);
    }

    public function testBuildDsnThrowsExceptionForUnsupportedDriver(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(Connection::class);
        $method = $reflection->getMethod('buildDsn');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver: invalid');

        $method->invoke($this->connection, 'invalid', '192.168.1.1', 1433, 'testdb');
    }

    public function testSanitizeIdentifierRemovesSpecialChars(): void
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(Connection::class);
        $method = $reflection->getMethod('sanitizeIdentifier');
        $method->setAccessible(true);

        $this->assertEquals('test_db', $method->invoke($this->connection, 'test_db'));
        $this->assertEquals('testdb', $method->invoke($this->connection, 'test-db'));
        $this->assertEquals('testdb', $method->invoke($this->connection, 'test.db'));
        // Sanitization removes all non-alphanumeric except underscore
        $this->assertEquals('testDROPTABLE', $method->invoke($this->connection, 'test;DROP TABLE--'));
    }

    public function testGetDriversToTryReturnsPreferredFirst(): void
    {
        // Use reflection to access private method and property
        $reflection = new \ReflectionClass(Connection::class);

        // Set available drivers
        $property = $reflection->getProperty('availableDrivers');
        $property->setAccessible(true);
        $property->setValue($this->connection, ['sqlsrv', 'dblib', 'odbc']);

        $method = $reflection->getMethod('getDriversToTry');
        $method->setAccessible(true);

        // Test with specific preferred driver
        $result = $method->invoke($this->connection, 'dblib');
        $this->assertEquals(['dblib'], $result);

        // Test with auto
        $result = $method->invoke($this->connection, 'auto');
        $this->assertEquals(['sqlsrv', 'dblib', 'odbc'], $result);

        // Test with unavailable driver falls back to all
        $result = $method->invoke($this->connection, 'mysql');
        $this->assertEquals(['sqlsrv', 'dblib', 'odbc'], $result);
    }
}
