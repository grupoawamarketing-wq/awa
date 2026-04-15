<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\HealthCheckInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Health Check — verifica se todos os componentes do WhatsApp Commerce estão operacionais.
 */
class WhatsAppHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerMetadataInterface $customerMetadata,
        private readonly MessageSender $messageSender,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function check(): array
    {
        $checks = [];
        $allHealthy = true;

        $moduleEnabled = $this->config->isEnabled();
        $checks['module_enabled'] = [
            'status' => $moduleEnabled ? 'ok' : 'warning',
            'message' => $moduleEnabled ? 'Module is enabled' : 'Module is disabled in admin config',
        ];
        if (!$moduleEnabled) {
            $allHealthy = false;
        }

        $tableCheck = $this->checkConsentTable();
        $checks['consent_table'] = $tableCheck;
        if ($tableCheck['status'] !== 'ok') {
            $allHealthy = false;
        }

        $eavCheck = $this->checkOptinAttribute();
        $checks['optin_attribute'] = $eavCheck;
        if ($eavCheck['status'] !== 'ok') {
            $allHealthy = false;
        }

        $apiCheck = $this->checkWhatsAppApi();
        $checks['whatsapp_api'] = $apiCheck;
        if ($apiCheck['status'] !== 'ok') {
            $allHealthy = false;
        }

        $stats = $this->getOptinStats();
        $checks['optin_stats'] = [
            'status' => 'info',
            'message' => sprintf(
                '%d customers opted-in, %d total consent records',
                $stats['opted_in'],
                $stats['total_records']
            ),
            'data' => $stats,
        ];

        return [
            'healthy' => $allHealthy,
            'timestamp' => date('c'),
            'checks' => $checks,
        ];
    }

    private function checkConsentTable(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_whatsapp_consent_log');

            if ($connection->isTableExists($tableName)) {
                return ['status' => 'ok', 'message' => 'Table awa_whatsapp_consent_log exists'];
            }

            return ['status' => 'error', 'message' => 'Table awa_whatsapp_consent_log not found'];
        } catch (\Exception $e) {
            $this->logger->error('Health check: consent table check failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => 'DB connection error'];
        }
    }

    private function checkOptinAttribute(): array
    {
        try {
            $attribute = $this->customerMetadata->getAttributeMetadata('whatsapp_optin');
            return [
                'status' => 'ok',
                'message' => sprintf('Attribute whatsapp_optin exists (code: %s)', $attribute->getAttributeCode()),
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['status' => 'error', 'message' => 'Attribute whatsapp_optin not found'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Attribute check failed: ' . $e->getMessage()];
        }
    }

    private function checkWhatsAppApi(): array
    {
        try {
            $result = $this->messageSender->testConnection();
            $success = (bool) ($result['success'] ?? false);

            return [
                'status' => $success ? 'ok' : 'error',
                'message' => $success
                    ? 'WhatsApp API connected'
                    : ('WhatsApp API unreachable: ' . ($result['message'] ?? 'unknown error')),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Health check: WhatsApp API check failed', ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => 'WhatsApp API check error: ' . $e->getMessage()];
        }
    }

    private function getOptinStats(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_whatsapp_consent_log');

            if (!$connection->isTableExists($tableName)) {
                return ['opted_in' => 0, 'total_records' => 0];
            }

            $select = $connection->select()
                ->from($tableName, [
                    'total_records' => new \Zend_Db_Expr('COUNT(*)'),
                    'opted_in' => new \Zend_Db_Expr('SUM(CASE WHEN optin = 1 THEN 1 ELSE 0 END)'),
                ]);

            $row = $connection->fetchRow($select);

            return [
                'opted_in' => (int) ($row['opted_in'] ?? 0),
                'total_records' => (int) ($row['total_records'] ?? 0),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Health check: optin stats failed', ['error' => $e->getMessage()]);
            return ['opted_in' => 0, 'total_records' => 0];
        }
    }
}
