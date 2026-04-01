<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistory;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory as SuggestionHistoryResource;

/**
 * Suggestion History Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'history_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(SuggestionHistory::class, SuggestionHistoryResource::class);
    }

    /**
     * Filter by customer
     */
    public function addCustomerFilter(int $customerId): self
    {
        return $this->addFieldToFilter('customer_id', $customerId);
    }

    /**
     * Filter by status
     */
    public function addStatusFilter(string $status): self
    {
        return $this->addFieldToFilter('status', $status);
    }

    /**
     * Filter by channel
     */
    public function addChannelFilter(string $channel): self
    {
        return $this->addFieldToFilter('channel', $channel);
    }

    /**
     * Filter by date range
     */
    public function addDateRangeFilter(string $from, string $to): self
    {
        return $this->addFieldToFilter('created_at', ['from' => $from, 'to' => $to]);
    }

    /**
     * Get conversion statistics
     */
    public function getConversionStats(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [
                'total' => 'COUNT(*)',
                'sent' => 'SUM(CASE WHEN status IN ("sent", "delivered", "read", "converted") THEN 1 ELSE 0 END)',
                'converted' => 'SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END)',
                'total_suggested' => 'SUM(total_value)',
                'total_converted' => 'SUM(CASE WHEN status = "converted" THEN conversion_value ELSE 0 END)'
            ]);

        $result = $connection->fetchRow($select);

        $conversionRate = $result['sent'] > 0
            ? ($result['converted'] / $result['sent']) * 100
            : 0;

        return [
            'total_suggestions' => (int) $result['total'],
            'total_sent' => (int) $result['sent'],
            'total_converted' => (int) $result['converted'],
            'conversion_rate' => round($conversionRate, 2),
            'total_suggested_value' => (float) $result['total_suggested'],
            'total_converted_value' => (float) $result['total_converted']
        ];
    }
}
