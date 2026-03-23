<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Cron;

use GrupoAwamotos\B2B\Cron\ExpireQuotes;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ExpireQuotes cron job
 *
 * Validates that the bulk UPDATE pattern is used instead of N individual
 * quote->save() calls (N queries → 2 queries max).
 *
 * @covers \GrupoAwamotos\B2B\Cron\ExpireQuotes
 */
class ExpireQuotesTest extends TestCase
{
    private ExpireQuotes $subject;
    private CollectionFactory&MockObject $collectionFactory;
    private Config&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private ResourceConnection&MockObject $resource;
    private AdapterInterface&MockObject $connection;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);

        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->connection->method('getTableName')
            ->with('grupoawamotos_b2b_quote_request')
            ->willReturn('grupoawamotos_b2b_quote_request');

        $this->subject = new ExpireQuotes(
            $this->collectionFactory,
            $this->config,
            $this->logger,
            $this->resource
        );
    }

    // ====================================================================
    // Early-exit guards
    // ====================================================================

    public function testSkipsWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->connection->expects($this->never())->method('update');

        $this->subject->execute();
    }

    public function testSkipsWhenQuoteModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(false);

        $this->connection->expects($this->never())->method('update');

        $this->subject->execute();
    }

    // ====================================================================
    // Bulk UPDATE: no per-row save() calls
    // ====================================================================

    public function testExpiresByExpiresAtWithSingleUpdate(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(true);
        $this->config->method('getQuoteExpiryDays')->willReturn(0);

        // Must call update() exactly once (for expires_at condition)
        $this->connection
            ->expects($this->once())
            ->method('update')
            ->willReturn(5);

        // CollectionFactory must never be used (replaced by direct connection)
        $this->collectionFactory->expects($this->never())->method('create');

        $this->subject->execute();
    }

    public function testExpiresByBothConditionsWhenExpiryDaysSet(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(true);
        $this->config->method('getQuoteExpiryDays')->willReturn(30);

        // Must call update() exactly twice (expires_at condition AND null+age condition)
        $this->connection
            ->expects($this->exactly(2))
            ->method('update')
            ->willReturnOnConsecutiveCalls(3, 2);

        $this->subject->execute();
    }

    public function testSkipsSecondUpdateWhenExpiryDaysIsZero(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(true);
        $this->config->method('getQuoteExpiryDays')->willReturn(0);

        // Only 1 update (for expires_at), no second update for implicitly expired
        $this->connection
            ->expects($this->once())
            ->method('update')
            ->willReturn(0);

        $this->subject->execute();
    }

    // ====================================================================
    // Status target value
    // ====================================================================

    public function testUpdatesStatusToExpired(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(true);
        $this->config->method('getQuoteExpiryDays')->willReturn(0);

        $capturedData = [];
        $this->connection
            ->method('update')
            ->willReturnCallback(static function (string $table, array $data) use (&$capturedData): int {
                $capturedData[] = $data;
                return 1;
            });

        $this->subject->execute();

        $this->assertNotEmpty($capturedData);
        $this->assertSame('expired', $capturedData[0]['status']);
    }

    // ====================================================================
    // Logging
    // ====================================================================

    public function testLogsInfoWhenQuotesExpired(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(true);
        $this->config->method('getQuoteExpiryDays')->willReturn(0);

        $this->connection->method('update')->willReturn(7);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('7'));

        $this->subject->execute();
    }

    public function testDoesNotLogInfoWhenZeroExpired(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(true);
        $this->config->method('getQuoteExpiryDays')->willReturn(0);

        $this->connection->method('update')->willReturn(0);

        // No info log when count == 0
        $this->logger->expects($this->never())->method('info');

        $this->subject->execute();
    }

    // ====================================================================
    // Table name
    // ====================================================================

    public function testUsesCorrectTableName(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isQuoteEnabled')->willReturn(true);
        $this->config->method('getQuoteExpiryDays')->willReturn(0);

        $this->connection
            ->expects($this->once())
            ->method('getTableName')
            ->with('grupoawamotos_b2b_quote_request')
            ->willReturn('grupoawamotos_b2b_quote_request');

        $this->connection->method('update')->willReturn(0);

        $this->subject->execute();
    }
}
