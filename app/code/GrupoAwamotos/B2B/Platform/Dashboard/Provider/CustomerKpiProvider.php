<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard\Provider;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ErpCustomerSyncStatus;
use GrupoAwamotos\B2B\Platform\Dashboard\B2bDashboardScopeHelper;
use GrupoAwamotos\B2B\Platform\Dashboard\DashboardFilter;
use GrupoAwamotos\B2B\Platform\Dashboard\KpiValue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

/**
 * KPIs de clientes B2B — read-only via EAV.
 */
class CustomerKpiProvider implements KpiProviderInterface
{
    /** @var array<string, int>|null */
    private ?array $attributeIds = null;

    /** @var string[] */
    private const ERP_PENDING_STATUSES = [
        ErpCustomerSyncStatus::AWAITING_ERP_VALIDATION,
        ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION,
        ErpCustomerSyncStatus::PENDING_ERP_CREATION,
        ErpCustomerSyncStatus::PROSPECT_MAGENTO,
        ErpCustomerSyncStatus::PROSPECT_SENT_SECTRA,
    ];

    /** @var string[] */
    private const ERP_VALIDATED_STATUSES = [
        ErpCustomerSyncStatus::VALIDATED_IN_ERP,
        ErpCustomerSyncStatus::CUSTOMER_VALIDATED_IN_ERP,
        ErpCustomerSyncStatus::LINKED_EXISTING,
        ErpCustomerSyncStatus::LINKED_BY_CNPJ,
    ];

    public function __construct(
        private readonly B2bDashboardScopeHelper $scopeHelper,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getData(DashboardFilter $filter): array
    {
        $attrs = $this->resolveAttributeIds();
        if ($attrs === []) {
            return [
                'pending_erp_validation' => KpiValue::unavailable()->toArray(),
                'validated_in_erp' => KpiValue::unavailable()->toArray(),
                'commercial_pending' => KpiValue::unavailable()->toArray(),
                'new_customers_period' => KpiValue::unavailable()->toArray(),
            ];
        }

        return [
            'pending_erp_validation' => KpiValue::available(
                $this->countByErpStatus($attrs, self::ERP_PENDING_STATUSES)
            )->toArray(),
            'validated_in_erp' => KpiValue::available(
                $this->countByErpStatus($attrs, self::ERP_VALIDATED_STATUSES)
            )->toArray(),
            'commercial_pending' => KpiValue::available($this->countCommercialPending($attrs))->toArray(),
            'new_customers_period' => KpiValue::available(
                $this->countNewCustomers($filter)
            )->toArray(),
        ];
    }

    /**
     * @param array<string, int> $attrs
     * @param string[] $statuses
     */
    private function countByErpStatus(array $attrs, array $statuses): int
    {
        if (!isset($attrs['erp_customer_sync_status'])) {
            return 0;
        }

        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
        $select = $this->scopeHelper->createB2bCustomerSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['cnt' => new Expression('COUNT(DISTINCT ce.entity_id)')])
            ->joinInner(
                ['erp' => $varcharTable],
                'erp.entity_id = ce.entity_id AND erp.attribute_id = ' . (int) $attrs['erp_customer_sync_status'],
                []
            )
            ->where('erp.value IN (?)', $statuses);

        return (int) $this->resourceConnection->getConnection()->fetchOne($select);
    }

    /**
     * @param array<string, int> $attrs
     */
    private function countCommercialPending(array $attrs): int
    {
        $connection = $this->resourceConnection->getConnection();
        $pendingGroup = 7;
        $count = 0;

        $select = $this->scopeHelper->createB2bCustomerSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['cnt' => new Expression('COUNT(DISTINCT ce.entity_id)')])
            ->where('ce.group_id = ?', $pendingGroup);
        $count += (int) $connection->fetchOne($select);

        if (isset($attrs['b2b_approval_status'])) {
            $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
            $selectApproval = $this->scopeHelper->createB2bCustomerSelect();
            $selectApproval->reset(\Magento\Framework\DB\Select::COLUMNS)
                ->columns(['cnt' => new Expression('COUNT(DISTINCT ce.entity_id)')])
                ->joinInner(
                    ['ap' => $varcharTable],
                    'ap.entity_id = ce.entity_id AND ap.attribute_id = ' . (int) $attrs['b2b_approval_status'],
                    []
                )
                ->where('ap.value IN (?)', [
                    ApprovalStatus::STATUS_PENDING,
                    ApprovalStatus::STATUS_DATA_REVIEW,
                ]);
            $count += (int) $connection->fetchOne($selectApproval);
        }

        return $count;
    }

    private function countNewCustomers(DashboardFilter $filter): int
    {
        $select = $this->scopeHelper->createB2bCustomerSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['cnt' => new Expression('COUNT(DISTINCT ce.entity_id)')])
            ->where('ce.created_at >= ?', $filter->getDateFromDatetime())
            ->where('ce.created_at <= ?', $filter->getDateToDatetime());

        $b2bCount = (int) $this->resourceConnection->getConnection()->fetchOne($select);

        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $attrs = $this->resolveAttributeIds();

        if (!isset($attrs['b2b_cnpj'])) {
            return $b2bCount;
        }

        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');
        $selectCnpj = $connection->select()
            ->from(['ce' => $customerTable], ['cnt' => new Expression('COUNT(DISTINCT ce.entity_id)')])
            ->joinInner(
                ['cnpj' => $varcharTable],
                'cnpj.entity_id = ce.entity_id AND cnpj.attribute_id = ' . (int) $attrs['b2b_cnpj'] . " AND cnpj.value != ''",
                []
            )
            ->where('ce.created_at >= ?', $filter->getDateFromDatetime())
            ->where('ce.created_at <= ?', $filter->getDateToDatetime());

        $this->scopeHelper->applyCustomerScope($selectCnpj);

        return max($b2bCount, (int) $connection->fetchOne($selectCnpj));
    }

    /**
     * @return array<string, int>
     */
    private function resolveAttributeIds(): array
    {
        if ($this->attributeIds !== null) {
            return $this->attributeIds;
        }

        $connection = $this->resourceConnection->getConnection();
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');
        $codes = ['erp_customer_sync_status', 'b2b_approval_status', 'b2b_cnpj'];
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($eavTable, ['attribute_code', 'attribute_id'])
                ->where('entity_type_id = ?', 1)
                ->where('attribute_code IN (?)', $codes)
        );

        $this->attributeIds = array_map('intval', $rows);

        return $this->attributeIds;
    }
}
