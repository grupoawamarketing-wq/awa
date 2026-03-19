<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Registers B2B clients in Sectra's GR_INTEGRACAOVALIDADOR table.
 *
 * The Sectra "Importar Pedidos AWA" feature validates that a client exists
 * in GR_INTEGRACAOVALIDADOR for the "OpenCardB2B - Cadastro de Cliente" origin
 * before importing a web order. Clients not registered will fail with
 * "Cliente não foi encontrado".
 *
 * This service uses a separate write-enabled connection (configured in admin)
 * to INSERT client registrations. Falls back gracefully when write access
 * is not available.
 */
class B2BClientRegistration
{
    /** OpenCardB2B - Cadastro de Cliente */
    private const ORIGEM_CLIENTE = '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7';

    /** OpenCardB2B - Cadastro de Cliente Endereço */
    private const ORIGEM_ENDERECO = 'FEB11981-5319-49EB-9F1E-4BA02BD22B90';

    private ConnectionInterface $readConnection;
    private Helper $helper;
    private LoggerInterface $logger;
    private ?\PDO $writeConnection = null;

    public function __construct(
        ConnectionInterface $readConnection,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->readConnection = $readConnection;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Check if a client is registered in Sectra B2B integration
     * with the correct INTEGRACAOORIGEM (OpenCardB2B - Cadastro de Cliente).
     */
    public function isClientRegistered(int $erpClientCode): bool
    {
        if ($erpClientCode <= 0) {
            return false;
        }

        try {
            $result = $this->readConnection->fetchOne(
                "SELECT CHAVE FROM GR_INTEGRACAOVALIDADOR
                 WHERE CHAVE = :code AND INTEGRACAOORIGEM = :origem",
                [':code' => (string) $erpClientCode, ':origem' => self::ORIGEM_CLIENTE]
            );
            return $result !== null;
        } catch (\Exception $e) {
            $this->logger->warning('[B2B Registration] Check failed: ' . $e->getMessage());
            return true; // Assume registered to avoid false negatives
        }
    }

    /**
     * Register a client in Sectra B2B integration (GR_INTEGRACAOVALIDADOR)
     *
     * @return bool True if registered successfully, false otherwise
     */
    public function registerClient(int $erpClientCode): bool
    {
        if ($erpClientCode <= 0) {
            return false;
        }

        // Already registered?
        if ($this->isClientRegistered($erpClientCode)) {
            $this->logger->info("[B2B Registration] Client $erpClientCode already registered");
            return true;
        }

        $pdo = $this->getWriteConnection();
        if (!$pdo) {
            $this->logger->warning('[B2B Registration] Write connection not available');
            return false;
        }

        try {
            $validadorHash = strtoupper(md5(json_encode([
                'CODIGO' => $erpClientCode,
                'source' => 'magento_b2b',
                'ts' => date('Y-m-d'),
            ])));

            // 1. Register client
            $stmt = $pdo->prepare(
                "INSERT INTO GR_INTEGRACAOVALIDADOR
                 (INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)
                 VALUES (:origem, :chave, :validador, :ext, GETDATE())"
            );
            $stmt->execute([
                ':origem' => self::ORIGEM_CLIENTE,
                ':chave' => (string) $erpClientCode,
                ':validador' => $validadorHash,
                ':ext' => (string) $erpClientCode,
            ]);

            // 2. Register address
            $enderecoHash = strtoupper(md5(json_encode([
                'CODIGO' => $erpClientCode,
                'ENDERECO' => 1,
                'source' => 'magento_b2b',
                'ts' => date('Y-m-d'),
            ])));

            // Get next CHAVEEXTERNA for address
            $stmtMax = $pdo->prepare(
                "SELECT MAX(CAST(CHAVEEXTERNA AS INT))
                 FROM GR_INTEGRACAOVALIDADOR WHERE INTEGRACAOORIGEM = :origem"
            );
            $stmtMax->execute([':origem' => self::ORIGEM_ENDERECO]);
            $maxExt = (int) $stmtMax->fetchColumn();
            $nextExt = $maxExt > 0 ? $maxExt + 1 : 20000;

            $stmt2 = $pdo->prepare(
                "INSERT INTO GR_INTEGRACAOVALIDADOR
                 (INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)
                 VALUES (:origem, :chave, :validador, :ext, GETDATE())"
            );
            $stmt2->execute([
                ':origem' => self::ORIGEM_ENDERECO,
                ':chave' => $erpClientCode . ';1',
                ':validador' => $enderecoHash,
                ':ext' => (string) $nextExt,
            ]);

            $this->logger->info("[B2B Registration] Client $erpClientCode registered successfully");
            return true;
        } catch (\Exception $e) {
            $this->logger->error('[B2B Registration] Failed to register client ' . $erpClientCode . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of unregistered clients from pending Magento orders
     *
     * @return array<int, array{erp_code: int, razao: string, cgc: string}>
     */
    public function getUnregisteredClients(array $erpClientCodes): array
    {
        $unregistered = [];

        foreach ($erpClientCodes as $code) {
            $code = (int) $code;
            if ($code <= 0 || $this->isClientRegistered($code)) {
                continue;
            }

            try {
                $cli = $this->readConnection->fetchOne(
                    "SELECT RAZAO, CGC FROM FN_FORNECEDORES WHERE CODIGO = :cod AND CKCLIENTE = 'S'",
                    [':cod' => $code]
                );
                if ($cli) {
                    $unregistered[] = [
                        'erp_code' => $code,
                        'razao' => trim($cli['RAZAO'] ?? ''),
                        'cgc' => trim($cli['CGC'] ?? ''),
                    ];
                }
            } catch (\Exception $e) {
                // Skip this client
            }
        }

        return $unregistered;
    }

    /**
     * Generate SQL INSERT statements for manual execution by DBA
     */
    public function generateRegistrationSQL(array $erpClientCodes): string
    {
        $unregistered = $this->getUnregisteredClients($erpClientCodes);

        if (empty($unregistered)) {
            return "-- Todos os clientes ja estao registrados no validador Sectra.\n";
        }

        $sql = "-- SQL gerado automaticamente pelo Magento B2B\n";
        $sql .= "-- Data: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Executar no SQL Server Management Studio com permissao de escrita\n\n";
        $sql .= "BEGIN TRANSACTION;\n\n";

        // Get next address CHAVEEXTERNA
        try {
            $maxExt = (int) $this->readConnection->fetchColumn(
                "SELECT MAX(CAST(CHAVEEXTERNA AS INT)) FROM GR_INTEGRACAOVALIDADOR WHERE INTEGRACAOORIGEM = :o",
                [':o' => self::ORIGEM_ENDERECO]
            );
        } catch (\Exception $e) {
            $maxExt = 19999;
        }
        $nextExt = $maxExt + 1;

        foreach ($unregistered as $c) {
            $code = $c['erp_code'];
            $hash = strtoupper(md5(json_encode(['CODIGO' => $code, 'source' => 'magento_b2b'])));
            $hashEnd = strtoupper(md5(json_encode(['CODIGO' => $code, 'ENDERECO' => 1, 'source' => 'magento_b2b'])));

            $sql .= "-- Cliente: " . $c['razao'] . " (CNPJ: " . $c['cgc'] . ")\n";
            $sql .= "INSERT INTO GR_INTEGRACAOVALIDADOR (INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)\n";
            $sql .= "VALUES ('" . self::ORIGEM_CLIENTE . "', '$code', '$hash', '$code', GETDATE());\n";
            $sql .= "INSERT INTO GR_INTEGRACAOVALIDADOR (INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)\n";
            $sql .= "VALUES ('" . self::ORIGEM_ENDERECO . "', '$code;1', '$hashEnd', '$nextExt', GETDATE());\n\n";
            $nextExt++;
        }

        $sql .= "COMMIT;\n";
        return $sql;
    }

    /**
     * Check if write connection is available
     */
    public function hasWriteAccess(): bool
    {
        return $this->getWriteConnection() !== null;
    }

    /**
     * Get or create write-enabled PDO connection
     */
    private function getWriteConnection(): ?\PDO
    {
        if ($this->writeConnection !== null) {
            return $this->writeConnection;
        }

        if (!$this->helper->isWriteConnectionEnabled()) {
            return null;
        }

        $writeUser = $this->helper->getWriteUsername();
        $writePass = $this->helper->getWritePassword();

        if (empty($writeUser)) {
            return null;
        }

        try {
            $host = $this->helper->getHost();
            $port = $this->helper->getPort();
            $database = $this->helper->getDatabase();

            $dsn = sprintf('dblib:host=%s:%d;charset=UTF-8', $host, $port);
            $this->writeConnection = new \PDO($dsn, $writeUser, $writePass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 15,
            ]);
            $this->writeConnection->exec('USE ' . $database);

            $this->logger->info('[B2B Registration] Write connection established');
            return $this->writeConnection;
        } catch (\Exception $e) {
            $this->logger->error('[B2B Registration] Write connection failed: ' . $e->getMessage());
            return null;
        }
    }
}
