<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ErpCodeResolver
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly ?SyncLogResource $syncLogResource = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function resolveForCustomerId(int $customerId, ?CustomerInterface $customer = null): ?int
    {
        if ($customerId <= 0) {
            return null;
        }

        try {
            $customer = $customer ?? $this->customerRepository->getById($customerId);
            $attribute = $customer->getCustomAttribute('erp_code');
            $erpCode = ($attribute && $attribute->getValue()) ? $attribute->getValue() : null;

            if ($erpCode !== null && is_numeric($erpCode)) {
                return (int) $erpCode;
            }

            if ($this->syncLogResource !== null) {
                $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
                if ($erpCode !== null && is_numeric($erpCode)) {
                    return (int) $erpCode;
                }
            }

            return $this->resolveFromEntityMap($customerId);
        } catch (\Throwable $exception) {
            $this->getLogger()->error('[B2B ErpCodeResolver] Failed to resolve ERP code.', [
                'customer_id' => $customerId,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveFromEntityMap(int $customerId): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('grupoawamotos_erp_entity_map');

        if (!$connection->isTableExists($tableName)) {
            return null;
        }

        $select = $connection->select()
            ->from($tableName, ['erp_code'])
            ->where('entity_type = ?', 'customer')
            ->where('magento_entity_id = ?', $customerId)
            ->where('erp_code IS NOT NULL')
            ->limit(1);

        $erpCode = $connection->fetchOne($select);

        return ($erpCode !== false && $erpCode !== null && is_numeric($erpCode)) ? (int) $erpCode : null;
    }

    private function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }
}
