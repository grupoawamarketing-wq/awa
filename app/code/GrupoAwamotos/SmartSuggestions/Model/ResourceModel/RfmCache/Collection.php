<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\ResourceModel\RfmCache;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\SmartSuggestions\Model\RfmCache as RfmCacheModel;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\RfmCache as RfmCacheResource;

/**
 * RFM Cache Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'rfm_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(RfmCacheModel::class, RfmCacheResource::class);
    }

    /**
     * Filter by segment
     */
    public function addSegmentFilter(string $segment): self
    {
        return $this->addFieldToFilter('segment', $segment);
    }

    /**
     * Filter by RFM score range
     */
    public function addScoreRangeFilter(int $minScore, int $maxScore): self
    {
        $this->getSelect()->where(
            '(r_score + f_score + m_score) BETWEEN ? AND ?',
            [$minScore, $maxScore]
        );
        return $this;
    }

    /**
     * Get statistics by segment
     */
    public function getSegmentStats(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [
                'segment',
                'count' => 'COUNT(*)',
                'avg_revenue' => 'AVG(monetary)',
                'total_revenue' => 'SUM(monetary)',
                'avg_orders' => 'AVG(frequency)',
                'avg_recency' => 'AVG(recency_days)'
            ])
            ->group('segment')
            ->order('COUNT(*) DESC');

        return $connection->fetchAll($select);
    }
}
