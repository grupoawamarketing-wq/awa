<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class AbandonedCartCommercialManagement
{
    public function __construct(
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function markAsTreated(int $entityId, int $adminUserId): void
    {
        $row = $this->loadRow($entityId);
        if ($row === null) {
            throw new LocalizedException(__('Carrinho abandonado não encontrado.'));
        }

        $customerId = (int) ($row['customer_id'] ?? 0);
        if ($customerId > 0 && !$this->portfolioScope->canAccessCustomer($customerId)) {
            throw new LocalizedException(__('Carrinho fora do seu escopo comercial.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');

        $connection->update(
            $table,
            [
                'commercial_contact_status' => 'treated',
                'commercial_treated_at' => date('Y-m-d H:i:s'),
                'commercial_treated_by' => $adminUserId,
            ],
            ['entity_id = ?' => $entityId]
        );
    }

    /**
     * @throws LocalizedException
     */
    public function markInContact(int $entityId, int $adminUserId): void
    {
        $row = $this->loadRow($entityId);
        if ($row === null) {
            throw new LocalizedException(__('Carrinho abandonado não encontrado.'));
        }

        $customerId = (int) ($row['customer_id'] ?? 0);
        if ($customerId > 0 && !$this->portfolioScope->canAccessCustomer($customerId)) {
            throw new LocalizedException(__('Carrinho fora do seu escopo comercial.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');

        $connection->update(
            $table,
            ['commercial_contact_status' => 'in_contact'],
            ['entity_id = ?' => $entityId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadRow(int $entityId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');
        if (!$connection->isTableExists($table)) {
            return null;
        }

        $select = $connection->select()->from($table)->where('entity_id = ?', $entityId)->limit(1);
        $row = $connection->fetchRow($select);

        return $row ?: null;
    }
}
