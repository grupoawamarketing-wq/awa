<?php
declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model\ResourceModel\Alert;

use GrupoAwamotos\LogMonitoring\Model\Alert;
use GrupoAwamotos\LogMonitoring\Model\ResourceModel\Alert as AlertResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(Alert::class, AlertResource::class);
    }

    public function addActiveFilter(): self
    {
        return $this->addFieldToFilter('status', ['neq' => 'resolved']);
    }

    public function addSeverityFilter(string $severity): self
    {
        return $this->addFieldToFilter('severity', $severity);
    }

    public function addTypeFilter(string $alertType): self
    {
        return $this->addFieldToFilter('alert_type', $alertType);
    }

    public function addCriticalFilter(): self
    {
        return $this->addFieldToFilter('severity', ['in' => ['critical', 'high']]);
    }

    public function orderByLatest(): self
    {
        return $this->setOrder('last_occurrence', 'DESC');
    }

    public function orderBySeverity(): self
    {
        return $this->setOrder([
            'FIELD(severity, "critical", "high", "medium", "low")',
            'last_occurrence DESC'
        ]);
    }
}