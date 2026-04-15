<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Attendant\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant\Collection as AttendantCollection;
use Psr\Log\LoggerInterface;

class Collection extends AttendantCollection implements SearchResultInterface
{
    /**
     * @var AggregationInterface
     */
    private AggregationInterface $aggregations;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param string $mainTable
     * @param string $resourceModel
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'grupoawamotos_b2b_attendants',
        string $resourceModel = \GrupoAwamotos\B2B\Model\ResourceModel\Attendant::class,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_mainTable = $mainTable;
        $this->_setIdFieldName('attendant_id');
        $this->setModel(Document::class);
        $this->_init(Document::class, $resourceModel);
    }

    /**
     * @inheritDoc
     */
    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    /**
     * @inheritDoc
     */
    public function setAggregations($aggregations): static
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): static
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    /**
     * @inheritDoc
     */
    public function setTotalCount($totalCount): static
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setItems(?array $items = null): static
    {
        return $this;
    }
}
