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
    private const WARNING_COOLDOWN_SECONDS = 21600;
    private ConnectionInterface $readConnection;
    private Helper $helper;
    private LoggerInterface $logger;
    private ?\PDO $writeConnection = null;
    private bool $writePermissionChecked = false;
    private bool $writePermissionGranted = true;

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
     * Get all ERP client codes currently registered in Sectra validator.
     *
     * @return int[]
     */
    public function getRegisteredClientCodes(): array
    {
        try {
            $rows = $this->readConnection->query(
                "SELECT CHAVE FROM GR_INTEGRACAOVALIDADOR WHERE INTEGRACAOORIGEM = :origem",
                [':origem' => self::ORIGEM_CLIENTE]
            );

            $codes = [];
            foreach ($rows as $row) {
                $code = (int) ($row['CHAVE'] ?? 0);
                if ($code > 0) {
                    $codes[$code] = true;
                }
            }

            return array_map('intval', array_keys($codes));
        } catch (\Exception $e) {
            if ($this->shouldLogWarningWithCooldown('b2b_registered_codes_fetch')) {
                $this->logger->warning('[B2B Registration] Failed to fetch registered client codes: ' . $e->getMessage());
            }
            return [];
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
            $this->logger->info('[B2B Registration] Write connection not available — skipping auto-registration for client ' . $erpClientCode);
            return false;
        }

        try {
            $validadorHash = $this->getClientValidatorHash($erpClientCode);

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
            $enderecoHash = $this->getAddressValidatorHash($erpClientCode);

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
            if ($this->isInsertPermissionDeniedError($e)) {
                $this->writePermissionChecked = true;
                $this->writePermissionGranted = false;
                $this->writeConnection = null;
                $this->logger->warning(
                    '[B2B Registration] INSERT permission denied on GR_INTEGRACAOVALIDADOR. '
                    . 'Automatic registration disabled for current process.'
                );
                return false;
            }

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
            $hash = $this->getClientValidatorHash($code);
            $hashEnd = $this->getAddressValidatorHash($code);

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
     * Build the Sectra validator hash for a B2B client.
     */
    public function getClientValidatorHash(int $erpClientCode): string
    {
        return $this->buildValidatorHash([
            'CODIGO' => $erpClientCode,
            'source' => 'magento_b2b',
        ]);
    }

    /**
     * Build the Sectra validator hash for the default B2B address.
     */
    public function getAddressValidatorHash(int $erpClientCode): string
    {
        return $this->buildValidatorHash([
            'CODIGO' => $erpClientCode,
            'ENDERECO' => 1,
            'source' => 'magento_b2b',
        ]);
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
        if ($this->writePermissionChecked && !$this->writePermissionGranted) {
            return null;
        }

        if ($this->writeConnection !== null) {
            return $this->writeConnection;
        }

        if (!$this->helper->isWriteConnectionEnabled()) {
            return null;
        }

        $writeUser = $this->helper->getWriteUsername();
        $writePass = $this->helper->getWritePassword();
        $readUser = $this->helper->getUsername();

        if (empty($writeUser)) {
            return null;
        }

        if (
            $this->isSameSqlServerUser($writeUser, $readUser)
            && $this->shouldLogWarningWithCooldown('b2b_write_user_matches_read')
        ) {
            $this->logger->warning(
                '[B2B Registration] Write connection is using the same SQL Server user as the read connection. '
                . 'If this user does not have INSERT on GR_INTEGRACAOVALIDADOR, disable write_connection or '
                . 'configure a dedicated write-enabled user.'
            );
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
            $this->writeConnection->exec('USE [' . str_replace(']', ']]', $database) . ']');

            if (!$this->validateWritePermission($this->writeConnection)) {
                $this->writeConnection = null;
                return null;
            }

            $this->logger->info('[B2B Registration] Write connection established');
            return $this->writeConnection;
        } catch (\Exception $e) {
            $this->logger->error('[B2B Registration] Write connection failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate INSERT permission on Sectra validator table.
     */
    private function validateWritePermission(\PDO $pdo): bool
    {
        if ($this->writePermissionChecked) {
            return $this->writePermissionGranted;
        }

        $this->writePermissionChecked = true;

        try {
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM fn_my_permissions('dbo.GR_INTEGRACAOVALIDADOR', 'OBJECT') "
                . "WHERE permission_name = 'INSERT'"
            );
            $canInsert = (int) $stmt->fetchColumn();

            $this->writePermissionGranted = $canInsert > 0;

            if (!$this->writePermissionGranted) {
                if ($this->shouldLogWarningWithCooldown('b2b_insert_permission')) {
                    $this->logger->warning(
                        '[B2B Registration] Write user has no INSERT permission on dbo.GR_INTEGRACAOVALIDADOR.'
                    );
                }
            }
        } catch (\Exception $e) {
            // Keep flow resilient when permission metadata cannot be queried.
            $this->writePermissionGranted = true;
            if ($this->shouldLogWarningWithCooldown('b2b_insert_permission_check_error')) {
                $this->logger->warning(
                    '[B2B Registration] Could not verify INSERT permission: ' . $e->getMessage()
                );
            }
        }

        return $this->writePermissionGranted;
    }

    /**
     * Detect SQL Server INSERT permission denial.
     */
    private function isInsertPermissionDeniedError(\Throwable $exception): bool
    {
        return stripos($exception->getMessage(), 'INSERT permission was denied') !== false;
    }

    private function isSameSqlServerUser(string $leftUser, string $rightUser): bool
    {
        $leftUser = trim($leftUser);
        $rightUser = trim($rightUser);

        if ($leftUser === '' || $rightUser === '') {
            return false;
        }

        return strcasecmp($leftUser, $rightUser) === 0;
    }

    /**
     * @param array<string, int|string> $payload
     */
    private function buildValidatorHash(array $payload): string
    {
        return strtoupper(md5((string) json_encode($payload)));
    }

    /**
     * Limit repetitive warnings across cron runs.
     */
    private function shouldLogWarningWithCooldown(string $key): bool
    {
        $basePath = defined('BP') ? BP : sys_get_temp_dir();
        $lockDir = rtrim($basePath, '/') . '/var/locks';

        if (!is_dir($lockDir) && !@mkdir($lockDir, 0777, true) && !is_dir($lockDir)) {
            return true;
        }

        $file = $lockDir . '/erp_warn_' . preg_replace('/[^a-z0-9_]+/i', '_', $key) . '.lock';
        $handle = @fopen($file, 'c+');

        if ($handle === false) {
            return true;
        }

        @chmod($file, 0666);

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return true;
        }

        try {
            rewind($handle);
            $last = (int) trim((string) stream_get_contents($handle));
            $now = time();

            if ($last > 0 && ($now - $last) < self::WARNING_COOLDOWN_SECONDS) {
                return false;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $now);
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
