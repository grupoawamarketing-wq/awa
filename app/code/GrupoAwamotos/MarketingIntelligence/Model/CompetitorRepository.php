<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\CompetitorRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Api\Data\CompetitorInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Competitor as CompetitorResource;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Competitor\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CompetitorRepository implements CompetitorRepositoryInterface
{
    public function __construct(
        private readonly CompetitorResource $resource,
        private readonly CompetitorFactory $competitorFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    public function getById(int $competitorId): CompetitorInterface
    {
        $competitor = $this->competitorFactory->create();
        $this->resource->load($competitor, $competitorId);

        if (!$competitor->getCompetitorId()) {
            throw new NoSuchEntityException(
                __('Competitor with ID "%1" does not exist.', $competitorId)
            );
        }

        return $competitor;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function save(CompetitorInterface $competitor): CompetitorInterface
    {
        try {
            /** @var Competitor $competitor */
            $this->resource->save($competitor);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save competitor: %1', $e->getMessage()),
                $e
            );
        }

        return $competitor;
    }

    public function delete(CompetitorInterface $competitor): bool
    {
        try {
            /** @var Competitor $competitor */
            $this->resource->delete($competitor);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete competitor: %1', $e->getMessage()),
                $e
            );
        }

        return true;
    }
}
