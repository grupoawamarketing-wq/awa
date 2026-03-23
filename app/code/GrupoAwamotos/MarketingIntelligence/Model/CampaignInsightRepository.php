<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\CampaignInsightRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Api\Data\CampaignInsightInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight as CampaignInsightResource;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CampaignInsightRepository implements CampaignInsightRepositoryInterface
{
    public function __construct(
        private readonly CampaignInsightResource $resource,
        private readonly CampaignInsightFactory $insightFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    public function getById(int $insightId): CampaignInsightInterface
    {
        $insight = $this->insightFactory->create();
        $this->resource->load($insight, $insightId);

        if (!$insight->getInsightId()) {
            throw new NoSuchEntityException(
                __('Campaign Insight with ID "%1" does not exist.', $insightId)
            );
        }

        return $insight;
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

    public function save(CampaignInsightInterface $insight): CampaignInsightInterface
    {
        try {
            /** @var CampaignInsight $insight */
            $this->resource->save($insight);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save campaign insight: %1', $e->getMessage()),
                $e
            );
        }

        return $insight;
    }

    public function delete(CampaignInsightInterface $insight): bool
    {
        try {
            /** @var CampaignInsight $insight */
            $this->resource->delete($insight);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete campaign insight: %1', $e->getMessage()),
                $e
            );
        }

        return true;
    }
}
