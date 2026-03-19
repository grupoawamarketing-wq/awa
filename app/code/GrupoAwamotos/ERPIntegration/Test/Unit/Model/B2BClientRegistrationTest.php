<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration
 */
class B2BClientRegistrationTest extends TestCase
{
    private B2BClientRegistration $subject;
    private ConnectionInterface&MockObject $readConnection;
    private Helper&MockObject $helper;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->readConnection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->helper->method('isWriteConnectionEnabled')->willReturn(false);

        $this->subject = new B2BClientRegistration(
            $this->readConnection,
            $this->helper,
            $this->logger
        );
    }

    public function testIsClientRegisteredReturnsFalseForInvalidCode(): void
    {
        $this->readConnection->expects($this->never())->method('fetchOne');

        $this->assertFalse($this->subject->isClientRegistered(0));
    }

    public function testIsClientRegisteredReturnsTrueWhenRowExists(): void
    {
        $this->readConnection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('GR_INTEGRACAOVALIDADOR'),
                [':code' => '123']
            )
            ->willReturn(['CHAVE' => '123']);

        $this->assertTrue($this->subject->isClientRegistered(123));
    }

    public function testIsClientRegisteredReturnsTrueWhenLookupFails(): void
    {
        $this->readConnection->method('fetchOne')
            ->willThrowException(new \RuntimeException('ERP indisponivel'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Check failed'));

        $this->assertTrue($this->subject->isClientRegistered(123));
    }

    public function testRegisterClientReturnsTrueWhenAlreadyRegistered(): void
    {
        $this->readConnection->method('fetchOne')->willReturn(['CHAVE' => '456']);

        $this->assertTrue($this->subject->registerClient(456));
    }

    public function testRegisterClientReturnsFalseWhenWriteConnectionUnavailable(): void
    {
        $this->readConnection->method('fetchOne')->willReturn(null);
        $this->helper->method('isWriteConnectionEnabled')->willReturn(false);

        $this->assertFalse($this->subject->registerClient(456));
    }

    public function testRegisterClientCreatesClientAndAddressRecords(): void
    {
        $this->readConnection->method('fetchOne')->willReturn(null);

        $pdo = $this->createMock(\PDO::class);
        $clientStatement = $this->createMock(\PDOStatement::class);
        $maxStatement = $this->createMock(\PDOStatement::class);
        $addressStatement = $this->createMock(\PDOStatement::class);

        $pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnCallback(static function (string $sql) use ($clientStatement, $maxStatement, $addressStatement) {
                if (str_contains($sql, 'MAX(CAST(CHAVEEXTERNA AS INT))')) {
                    return $maxStatement;
                }

                if (str_contains($sql, 'VALUES (:origem, :chave, :validador, :ext, GETDATE())')) {
                    static $insertCall = 0;
                    $insertCall++;

                    return $insertCall === 1 ? $clientStatement : $addressStatement;
                }

                throw new \RuntimeException('SQL inesperado no teste');
            });

        $clientStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function (array $params): bool {
                return $params[':origem'] === '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7'
                    && $params[':chave'] === '456'
                    && $params[':ext'] === '456'
                    && strlen($params[':validador']) === 32;
            }))
            ->willReturn(true);

        $maxStatement->expects($this->once())
            ->method('execute')
            ->with([':origem' => 'FEB11981-5319-49EB-9F1E-4BA02BD22B90'])
            ->willReturn(true);
        $maxStatement->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('20005');

        $addressStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function (array $params): bool {
                return $params[':origem'] === 'FEB11981-5319-49EB-9F1E-4BA02BD22B90'
                    && $params[':chave'] === '456;1'
                    && $params[':ext'] === '20006'
                    && strlen($params[':validador']) === 32;
            }))
            ->willReturn(true);

        $this->injectWriteConnection($pdo);

        $this->assertTrue($this->subject->registerClient(456));
    }

    public function testGetUnregisteredClientsReturnsOnlyMissingSectraClients(): void
    {
        $this->readConnection->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params = []): ?array {
                if (str_contains($sql, 'GR_INTEGRACAOVALIDADOR')) {
                    return ($params[':code'] ?? '') === '202' ? ['CHAVE' => '202'] : null;
                }

                if (str_contains($sql, 'FN_FORNECEDORES')) {
                    return ['RAZAO' => 'AWA COMERCIO', 'CGC' => '12.345.678/0001-99'];
                }

                return null;
            });

        $result = $this->subject->getUnregisteredClients([101, 202, 0]);

        $this->assertCount(1, $result);
        $this->assertSame(101, $result[0]['erp_code']);
        $this->assertSame('AWA COMERCIO', $result[0]['razao']);
    }

    public function testGenerateRegistrationSqlReturnsCommentWhenEverythingIsRegistered(): void
    {
        $this->readConnection->method('fetchOne')->willReturn(['CHAVE' => '101']);

        $sql = $this->subject->generateRegistrationSQL([101]);

        $this->assertStringContainsString('Todos os clientes ja estao registrados', $sql);
    }

    public function testGenerateRegistrationSqlBuildsStatementsForUnregisteredClients(): void
    {
        $this->readConnection->method('fetchOne')
            ->willReturnCallback(static function (string $sql): ?array {
                if (str_contains($sql, 'GR_INTEGRACAOVALIDADOR')) {
                    return null;
                }

                return ['RAZAO' => 'CLIENTE TESTE', 'CGC' => '99.999.999/0001-00'];
            });
        $this->readConnection->method('fetchColumn')->willReturn('30000');

        $sql = $this->subject->generateRegistrationSQL([789]);

        $this->assertStringContainsString('BEGIN TRANSACTION;', $sql);
        $this->assertStringContainsString("'789'", $sql);
        $this->assertStringContainsString("'789;1'", $sql);
        $this->assertStringContainsString("'30001'", $sql);
        $this->assertStringContainsString('CLIENTE TESTE', $sql);
        $this->assertStringContainsString('COMMIT;', $sql);
    }

    private function injectWriteConnection(\PDO $pdo): void
    {
        $reflection = new \ReflectionProperty(B2BClientRegistration::class, 'writeConnection');
        $reflection->setAccessible(true);
        $reflection->setValue($this->subject, $pdo);
    }
}
