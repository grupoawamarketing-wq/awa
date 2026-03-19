<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model\Api;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\Api\CustomerPullManagement;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\ERPIntegration\Model\Api\CustomerPullManagement
 */
class CustomerPullManagementTest extends TestCase
{
    private CustomerPullManagement $subject;
    private ConnectionInterface&MockObject $connection;
    private B2BClientRegistration&MockObject $b2bRegistration;
    private ResourceConnection&MockObject $resourceConnection;
    private AdapterInterface&MockObject $db;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->b2bRegistration = $this->createMock(B2BClientRegistration::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->db = $this->createMock(AdapterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->resourceConnection->method('getConnection')->willReturn($this->db);

        $this->subject = new CustomerPullManagement(
            $this->connection,
            $this->b2bRegistration,
            $this->resourceConnection,
            $this->logger
        );
    }

    public function testGetB2BCustomersReturnsPaginatedPayload(): void
    {
        $this->db->method('fetchAll')->willReturn([
            [
                'entity_id' => 1,
                'email' => 'cliente@awa.com',
                'firstname' => 'Cliente',
                'lastname' => 'AWA',
                'group_id' => 4,
                'created_at' => '2026-03-16 10:00:00',
                'erp_code' => '1001',
            ],
        ]);
        $this->db->method('fetchOne')->willReturn('1');
        $this->b2bRegistration->method('isClientRegistered')->with(1001)->willReturn(true);

        $result = $this->subject->getB2BCustomers(10, 0);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['total_count']);
        $this->assertSame(1, $result[0]['returned_count']);
        $this->assertSame(1001, $result[0]['customers'][0]['erp_code']);
        $this->assertTrue($result[0]['customers'][0]['registered_in_b2b']);
    }

    public function testGetUnregisteredCustomersFiltersRegisteredClients(): void
    {
        $this->db->method('fetchAll')->willReturn([
            [
                'entity_id' => 1,
                'email' => 'a@awa.com',
                'firstname' => 'A',
                'lastname' => 'Um',
                'group_id' => 4,
                'created_at' => '2026-03-16 10:00:00',
                'erp_code' => '10',
            ],
            [
                'entity_id' => 2,
                'email' => 'b@awa.com',
                'firstname' => 'B',
                'lastname' => 'Dois',
                'group_id' => 4,
                'created_at' => '2026-03-16 10:01:00',
                'erp_code' => '20',
            ],
        ]);
        $this->b2bRegistration->method('isClientRegistered')
            ->willReturnCallback(static fn (int $erpCode): bool => $erpCode === 20);

        $result = $this->subject->getUnregisteredCustomers(10);

        $this->assertCount(1, $result[0]['customers']);
        $this->assertSame(10, $result[0]['customers'][0]['erp_code']);
        $this->assertSame(1, $result[0]['total_unregistered']);
    }

    public function testGetCustomerByErpCodeReturnsPayloadWithErpData(): void
    {
        $this->db->method('fetchRow')->willReturn([
            'entity_id' => 3,
            'email' => 'erp@awa.com',
            'firstname' => 'ERP',
            'lastname' => 'Cliente',
            'group_id' => 4,
            'created_at' => '2026-03-16 11:00:00',
            'erp_code' => '333',
        ]);
        $this->b2bRegistration->method('isClientRegistered')->with(333)->willReturn(false);
        $this->connection->method('fetchOne')->willReturn([
            'RAZAO' => 'CLIENTE ERP LTDA',
            'CGC' => '11.222.333/0001-44',
            'INSCEST' => '12345',
            'FILIAL' => 1,
            'VENDPREF' => 7,
            'CONDPAGTO' => 15,
            'FATORPRECO' => 24,
            'TRANSPPREF' => 3,
            'TPFATOR' => 'P',
            'PERCFATOR' => 5.5,
        ]);

        $result = $this->subject->getCustomerByErpCode(333);

        $this->assertSame(333, $result[0]['erp_code']);
        $this->assertFalse($result[0]['registered_in_b2b']);
        $this->assertSame('CLIENTE ERP LTDA', $result[0]['erp_data']['razao']);
        $this->assertSame(24, $result[0]['erp_data']['fator_preco']);
    }

    public function testGetCustomerByErpCodeThrowsWhenCustomerDoesNotExist(): void
    {
        $this->db->method('fetchRow')->willReturn(false);

        $this->expectException(NoSuchEntityException::class);

        $this->subject->getCustomerByErpCode(999);
    }

    public function testGetRegistrationSqlGeneratesStatementsOnlyForMissingCodes(): void
    {
        $this->db->method('fetchCol')->willReturn(['10', '20', '30']);
        $this->connection->method('query')->willReturn([
            ['CHAVE' => '20'],
        ]);
        $this->connection->method('fetchColumn')->willReturn('900');

        $result = $this->subject->getRegistrationSQL(10);

        $this->assertSame(2, $result[0]['count']);
        $this->assertStringContainsString("'10'", $result[0]['sql']);
        $this->assertStringContainsString("'30'", $result[0]['sql']);
        $this->assertStringNotContainsString("VALUES('7D4C6FBD-62CF-427F-A0ED-3C06602F05D7','20'", $result[0]['sql']);
        $this->assertStringContainsString("'901'", $result[0]['sql']);
        $this->assertStringContainsString("'902'", $result[0]['sql']);
    }

    public function testGetHealthStatusReturnsDegradedWhenSqlServerPingFails(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 AS ping')
            ->willThrowException(new \RuntimeException('timeout'));

        $this->db->method('fetchOne')
            ->willReturnCallback(static function (string $sql) {
                if ($sql === 'SELECT 1') {
                    return '1';
                }

                if (str_contains($sql, 'COUNT(*) FROM customer_entity_varchar')) {
                    return '200';
                }

                if (str_contains($sql, 'COUNT(*) FROM sales_order')) {
                    return '5';
                }

                return '0';
            });
        $this->connection->method('fetchColumn')
            ->willReturnOnConsecutiveCalls('50', '2026-03-16 12:00:00');

        $result = $this->subject->getHealthStatus();

        $this->assertSame('degraded', $result[0]['status']);
        $this->assertSame('error', $result[0]['checks']['sql_server']['status']);
        $this->assertSame('warning', $result[0]['checks']['b2b_sync']['status']);
        $this->assertSame(150, $result[0]['checks']['b2b_sync']['unregistered']);
        $this->assertSame('warning', $result[0]['checks']['pending_orders']['status']);
        $this->assertSame(5, $result[0]['checks']['pending_orders']['count']);
    }
}
