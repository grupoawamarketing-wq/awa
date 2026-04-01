<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Integration;

use GrupoAwamotos\ERPIntegration\Model\Connection;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ERP Connection
 *
 * Note: These tests require a properly configured ERP connection.
 * Skip these tests if running in CI/CD without ERP access.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 */
class ConnectionIntegrationTest extends TestCase
{
    private ?Connection $connection = null;
    private ?Helper $helper = null;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->helper = $objectManager->get(Helper::class);
        $this->connection = $objectManager->get(Connection::class);
    }

    /**
     * @group erp_connection
     */
    public function testConnectionCanBeEstablished(): void
    {
        // Skip if ERP is not enabled
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        // Skip if no drivers available
        if (!$this->connection->hasAvailableDriver()) {
            $this->markTestSkipped('No SQL Server drivers available');
        }

        $result = $this->connection->testConnection();

        if (!$result['success']) {
            $this->markTestSkipped('ERP connection not available: ' . $result['message']);
        }

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['database']);
        $this->assertGreaterThan(0, $result['table_count']);
    }

    /**
     * @group erp_connection
     * @depends testConnectionCanBeEstablished
     */
    public function testQueryReturnsResults(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        $result = $this->connection->query('SELECT 1 AS test_value');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['test_value']);
    }

    /**
     * @group erp_connection
     * @depends testConnectionCanBeEstablished
     */
    public function testFetchOneReturnsSingleRow(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        $result = $this->connection->fetchOne('SELECT @@VERSION AS version');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('version', $result);
        $this->assertStringContainsString('SQL Server', $result['version']);
    }

    /**
     * @group erp_connection
     * @depends testConnectionCanBeEstablished
     */
    public function testTransactionCommits(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        // Begin transaction
        $this->assertTrue($this->connection->beginTransaction());

        // Execute a SELECT (safe operation)
        $result = $this->connection->query('SELECT 1 AS test');
        $this->assertNotEmpty($result);

        // Commit transaction
        $this->assertTrue($this->connection->commit());
    }

    /**
     * @group erp_connection
     * @depends testConnectionCanBeEstablished
     */
    public function testTransactionRollsBack(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        // Begin transaction
        $this->assertTrue($this->connection->beginTransaction());

        // Execute a SELECT (safe operation)
        $result = $this->connection->query('SELECT 1 AS test');
        $this->assertNotEmpty($result);

        // Rollback transaction
        $this->assertTrue($this->connection->rollback());
    }

    /**
     * @group erp_connection
     * @depends testConnectionCanBeEstablished
     */
    public function testCanQueryProductsTable(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        // Check if MT_MATERIAL table exists
        $tableExists = $this->connection->fetchOne(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'MT_MATERIAL'"
        );

        if (!$tableExists || $tableExists['cnt'] == 0) {
            $this->markTestSkipped('MT_MATERIAL table does not exist');
        }

        $result = $this->connection->fetchOne('SELECT TOP 1 CODIGO, DESCRICAO FROM MT_MATERIAL');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('CODIGO', $result);
        $this->assertArrayHasKey('DESCRICAO', $result);
    }

    /**
     * @group erp_connection
     * @depends testConnectionCanBeEstablished
     */
    public function testCanQueryCustomersTable(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        // Check if FN_FORNECEDORES table exists
        $tableExists = $this->connection->fetchOne(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'FN_FORNECEDORES'"
        );

        if (!$tableExists || $tableExists['cnt'] == 0) {
            $this->markTestSkipped('FN_FORNECEDORES table does not exist');
        }

        $result = $this->connection->fetchOne('SELECT TOP 1 CODIGO, RAZAO FROM FN_FORNECEDORES');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('CODIGO', $result);
        $this->assertArrayHasKey('RAZAO', $result);
    }

    /**
     * @group erp_connection
     * @depends testConnectionCanBeEstablished
     */
    public function testCanQueryStockTable(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        // Check if MT_ESTOQUEMEDIA table exists
        $tableExists = $this->connection->fetchOne(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'MT_ESTOQUEMEDIA'"
        );

        if (!$tableExists || $tableExists['cnt'] == 0) {
            $this->markTestSkipped('MT_ESTOQUEMEDIA table does not exist');
        }

        $filial = $this->helper->getStockFilial();
        $result = $this->connection->fetchOne(
            'SELECT TOP 1 MATERIAL, QTDE FROM MT_ESTOQUEMEDIA WHERE FILIAL = :filial',
            [':filial' => $filial]
        );

        if (!$result) {
            $this->markTestSkipped('No stock data available for filial ' . $filial);
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('MATERIAL', $result);
        $this->assertArrayHasKey('QTDE', $result);
    }

    /**
     * @group erp_connection
     */
    public function testReconnectsAfterDisconnect(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        if (!$this->connection->hasAvailableDriver()) {
            $this->markTestSkipped('No SQL Server drivers available');
        }

        // First query
        $result1 = $this->connection->query('SELECT 1 AS test');
        $this->assertNotEmpty($result1);

        // Disconnect
        $this->connection->disconnect();
        $this->assertFalse($this->connection->isConnected());

        // Query again - should reconnect automatically
        $result2 = $this->connection->query('SELECT 2 AS test');
        $this->assertNotEmpty($result2);
        $this->assertEquals(2, $result2[0]['test']);
    }
}
