<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Cron: Maintain OpenCart bridge tables for Sectra integration.
 *
 * Keeps oc_customer_id_map and oc_customer_b2b_confirmed in sync with
 * Magento customer_entity so that new B2B customers and their orders
 * are visible to Sectra through the oc_order VIEW.
 *
 * Handles:
 *  - Auto-mapping new B2B customers to oc_customer_id_map (entity_id+200000)
 *  - Auto-confirming new B2B customers in oc_customer_b2b_confirmed
 *  - Syncing oc_customer TABLE with Magento customer data (for Sectra reads/writes)
 *  - Recovery of oc_customer_b2b_confirmed after Sectra's periodic TRUNCATE
 *
 * Schedule: every 5 minutes (via crontab.xml)
 */
class SyncOpenCartBridge
{
    /** @var int Offset applied to Magento entity_id to generate OpenCart-compatible customer_id */
    private const OC_CUSTOMER_ID_OFFSET = 200000;

    /** @var int[] B2B customer group IDs */
    private const B2B_GROUP_IDS = [4, 5, 6, 7];
    private const WARNING_COOLDOWN_SECONDS = 21600;
    private const SQL_EXPORT_DIR = '/var/log';
    private const SQL_EXPORT_PREFIX = 'erp_register_clients_auto_';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Helper $helper,
        private readonly B2BClientRegistration $b2bRegistration,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            $mapped = $this->syncCustomerIdMap();
            $confirmed = $this->syncB2bConfirmed();
            $synced = $this->syncCustomerTable();
            $recovered = $this->recoverAfterTruncate();
            $registered = $this->registerPendingOrderClients();

            if ($mapped > 0 || $confirmed > 0 || $synced > 0 || $recovered > 0 || $registered > 0) {
                $this->logger->info('[ERP Cron] OpenCart bridge sync completed', [
                    'new_mappings' => $mapped,
                    'new_confirmations' => $confirmed,
                    'customer_table_synced' => $synced,
                    'truncate_recovery' => $recovered,
                    'b2b_registrations' => $registered,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->critical('[ERP Cron] OpenCart bridge sync failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Auto-map new B2B customers that are not yet in oc_customer_id_map.
     *
     * Uses entity_id + 200000 as old_oc_customer_id (same convention as
     * the original create_opencart_views.sql script).
     */
    private function syncCustomerIdMap(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $groupIds = implode(',', self::B2B_GROUP_IDS);

        $sql = "
            INSERT INTO oc_customer_id_map (old_oc_customer_id, old_email, old_cnpj, magento_customer_id)
            SELECT
                ce.entity_id + :offset AS old_oc_customer_id,
                COALESCE(ce.email, '') AS old_email,
                COALESCE(
                    REPLACE(REPLACE(REPLACE(REPLACE(ce.taxvat, '.', ''), '/', ''), '-', ''), ' ', ''),
                    ''
                ) AS old_cnpj,
                ce.entity_id AS magento_customer_id
            FROM customer_entity ce
            WHERE ce.group_id IN ({$groupIds})
              AND ce.entity_id NOT IN (
                  SELECT magento_customer_id
                  FROM oc_customer_id_map
                  WHERE magento_customer_id IS NOT NULL
              )
            ON DUPLICATE KEY UPDATE
                old_email = VALUES(old_email),
                old_cnpj = VALUES(old_cnpj),
                magento_customer_id = VALUES(magento_customer_id)
        ";

        $stmt = $connection->query($sql, ['offset' => self::OC_CUSTOMER_ID_OFFSET]);

        return (int) $stmt->rowCount();
    }

    /**
     * Auto-confirm new B2B customers in oc_customer_b2b_confirmed.
     *
     * Ensures every mapped B2B customer is also confirmed, so their orders
     * pass through the oc_order VIEW's INNER JOIN chain.
     */
    private function syncB2bConfirmed(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $groupIds = implode(',', self::B2B_GROUP_IDS);

        $sql = "
            INSERT IGNORE INTO oc_customer_b2b_confirmed (customer_id, synced_at)
            SELECT
                map.old_oc_customer_id,
                NOW()
            FROM oc_customer_id_map map
            INNER JOIN customer_entity ce ON ce.entity_id = map.magento_customer_id
            WHERE ce.group_id IN ({$groupIds})
              AND map.old_oc_customer_id NOT IN (
                  SELECT customer_id FROM oc_customer_b2b_confirmed
              )
        ";

        $stmt = $connection->query($sql);

        return (int) $stmt->rowCount();
    }

    /**
     * Sync oc_customer TABLE with Magento customer data.
     *
     * Inserts new customers and updates changed data (name, email, phone, taxvat)
     * so that Sectra's "Exportar Clientes" and "Importar Pedidos" work correctly.
     * The oc_customer table was converted from a VIEW to a real TABLE to allow
     * Sectra to INSERT/UPDATE rows during "Exportar Clientes".
     */
    private function syncCustomerTable(): int
    {
        $connection = $this->resourceConnection->getConnection();

        $sql = "
            INSERT INTO oc_customer (
                customer_id, customer_group_id, store_id, language_id,
                firstname, lastname, email, telephone, fax, password, salt,
                cart, wishlist, newsletter, address_id, custom_field, ip,
                status, safe, token, code, date_added
            )
            SELECT
                map.old_oc_customer_id AS customer_id,
                CASE WHEN ce.taxvat IS NOT NULL AND ce.taxvat != '' THEN 2 ELSE 1 END,
                ce.store_id,
                2 AS language_id,
                COALESCE(ce.firstname, '') AS firstname,
                COALESCE(ce.lastname, '') AS lastname,
                COALESCE(ce.email, '') AS email,
                COALESCE(
                    REPLACE(REPLACE(REPLACE(REPLACE(ca.telephone, '(', ''), ')', ''), '-', ''), ' ', ''),
                    ''
                ) AS telephone,
                '' AS fax,
                '' AS password,
                '' AS salt,
                NULL AS cart,
                NULL AS wishlist,
                0 AS newsletter,
                0 AS address_id,
                CONCAT(
                    '{\"6\":\"',
                    REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(ce.taxvat, ''), '.', ''), '/', ''), '-', ''), ' ', ''),
                    '\",\"2\":\"\",\"3\":\"\",\"1\":\"\"}'
                ) AS custom_field,
                '' AS ip,
                1 AS status,
                1 AS safe,
                '' AS token,
                '' AS code,
                ce.created_at AS date_added
            FROM oc_customer_id_map map
            INNER JOIN customer_entity ce ON ce.entity_id = map.magento_customer_id
            LEFT JOIN customer_address_entity ca ON ca.parent_id = ce.entity_id
                AND ca.entity_id = (
                    SELECT MIN(ca2.entity_id)
                    FROM customer_address_entity ca2
                    WHERE ca2.parent_id = ce.entity_id
                )
            WHERE map.magento_customer_id IS NOT NULL
            ON DUPLICATE KEY UPDATE
                firstname = VALUES(firstname),
                lastname = VALUES(lastname),
                email = VALUES(email),
                telephone = VALUES(telephone),
                custom_field = VALUES(custom_field),
                customer_group_id = VALUES(customer_group_id)
        ";

        $stmt = $connection->query($sql);

        return (int) $stmt->rowCount();
    }

    /**
     * Register pending-order B2B clients in Sectra's GR_INTEGRACAOVALIDADOR.
     *
     * For each non-imported active order, checks if the customer's erp_code
     * is registered with the correct B2B INTEGRACAOORIGEM. If write access
     * is available, registers automatically; otherwise logs the missing codes.
     */
    private function registerPendingOrderClients(): int
    {
        $connection = $this->resourceConnection->getConnection();

        $erpCodes = $connection->fetchCol("
            SELECT DISTINCT erp_attr.value
            FROM sales_order so
            INNER JOIN customer_entity ce ON ce.entity_id = so.customer_id
            INNER JOIN customer_entity_varchar erp_attr
                ON erp_attr.entity_id = ce.entity_id
                AND erp_attr.attribute_id = (
                    SELECT attribute_id FROM eav_attribute
                    WHERE attribute_code = 'erp_code'
                      AND entity_type_id = (
                          SELECT entity_type_id FROM eav_entity_type
                          WHERE entity_type_code = 'customer'
                      )
                )
            WHERE so.state NOT IN ('canceled', 'closed')
              AND erp_attr.value IS NOT NULL
              AND erp_attr.value != ''
              AND CAST(erp_attr.value AS UNSIGNED) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM oc_order_imported oi WHERE oi.order_id = so.entity_id
              )
        ");

        if (empty($erpCodes)) {
            return 0;
        }

        $registered = 0;
        $missing = [];
        $writeAccess = $this->b2bRegistration->hasWriteAccess();

        foreach ($erpCodes as $code) {
            $code = (int) $code;
            if ($code <= 0 || $this->b2bRegistration->isClientRegistered($code)) {
                continue;
            }

            if ($writeAccess) {
                if ($this->b2bRegistration->registerClient($code)) {
                    $registered++;
                    continue;
                }

                // Write access may have been revoked (e.g. permission denied during INSERT).
                $writeAccess = $this->b2bRegistration->hasWriteAccess();
            }

            if (!$writeAccess) {
                $missing[] = $code;
            }
        }

        if (!empty($missing)) {
            sort($missing);
            $missingMessage = '[ERP Cron] B2B clients missing Sectra registration (no write access): '
                . implode(', ', $missing)
                . ' — run generateRegistrationSQL() or enable write connection';

            $payloadHash = md5(implode(',', $missing));
            if ($this->shouldLogWarningWithCooldown('b2b_missing_clients', $payloadHash)) {
                $context = [];
                $sqlExportFile = $this->exportMissingClientsSql($missing);
                if ($sqlExportFile !== null) {
                    $context['sql_export_file'] = $sqlExportFile;
                }
                $this->logger->warning($missingMessage, $context);
            }
        }

        return $registered;
    }

    /**
     * Export SQL with pending B2B registrations to help DBA/manual recovery.
     *
     * @param int[] $missingCodes
     */
    private function exportMissingClientsSql(array $missingCodes): ?string
    {
        $basePath = defined('BP') ? BP : sys_get_temp_dir();
        $exportDir = rtrim($basePath, '/') . self::SQL_EXPORT_DIR;

        if (!is_dir($exportDir) && !@mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
            return null;
        }

        try {
            $sql = $this->b2bRegistration->generateRegistrationSQL($missingCodes);
            $filename = self::SQL_EXPORT_PREFIX . gmdate('Ymd_His') . '.sql';
            $absolutePath = $exportDir . '/' . $filename;

            if (@file_put_contents($absolutePath, $sql, LOCK_EX) === false) {
                return null;
            }

            @chmod($absolutePath, 0664);
            return $absolutePath;
        } catch (\Throwable $e) {
            $this->logger->warning('[ERP Cron] Could not export B2B registration SQL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Re-insert B2B customers with active orders after Sectra's TRUNCATE.
     *
     * Sectra periodically runs "Importar Clientes Prospect" which TRUNCATEs
     * oc_customer_b2b_confirmed and re-inserts only its approved clients.
     * This method acts as a safety net: if a mapped B2B customer has a
     * non-imported active order but is missing from b2b_confirmed, re-insert.
     *
     * Complements the MySQL Event protect_b2b_active_orders (60s interval)
     * with broader coverage (all B2B customers, not just active orders).
     */
    private function recoverAfterTruncate(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $groupIds = implode(',', self::B2B_GROUP_IDS);

        $sql = "
            INSERT IGNORE INTO oc_customer_b2b_confirmed (customer_id, synced_at)
            SELECT DISTINCT map.old_oc_customer_id,
                   COALESCE(
                       (SELECT MAX(bc.synced_at) FROM oc_customer_b2b_confirmed bc),
                       NOW()
                   )
            FROM sales_order so
            INNER JOIN oc_customer_id_map map ON map.magento_customer_id = so.customer_id
            WHERE so.customer_group_id IN ({$groupIds})
              AND so.state NOT IN ('canceled', 'closed')
              AND NOT EXISTS (
                  SELECT 1 FROM oc_order_imported oi WHERE oi.order_id = so.entity_id
              )
              AND map.old_oc_customer_id NOT IN (
                  SELECT customer_id FROM oc_customer_b2b_confirmed
              )
        ";

        $stmt = $connection->query($sql);

        return (int) $stmt->rowCount();
    }

    /**
     * Limit repetitive warnings across cron runs.
     *
     * Always logs immediately when payload hash changes.
     */
    private function shouldLogWarningWithCooldown(string $key, string $payloadHash): bool
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
            $raw = trim((string) stream_get_contents($handle));
            [$lastTsRaw, $lastHash] = array_pad(explode('|', $raw, 2), 2, '');
            $lastTs = (int) $lastTsRaw;
            $now = time();

            if ($lastHash !== '' && $lastHash !== $payloadHash) {
                $lastTs = 0;
            }

            if ($lastTs > 0 && ($now - $lastTs) < self::WARNING_COOLDOWN_SECONDS) {
                return false;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $now . '|' . $payloadHash);
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
