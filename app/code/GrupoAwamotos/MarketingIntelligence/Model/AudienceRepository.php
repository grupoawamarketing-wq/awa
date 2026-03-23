<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\AudienceRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Api\Data\AudienceInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Audience as AudienceResource;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Audience\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class AudienceRepository implements AudienceRepositoryInterface
{
    public function __construct(
        private readonly AudienceResource $resource,
        private readonly AudienceFactory $audienceFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    public function getById(int $audienceId): AudienceInterface
    {
        $audience = $this->audienceFactory->create();
        $this->resource->load($audience, $audienceId);

        if (!$audience->getAudienceId()) {
            throw new NoSuchEntityException(
                __('Audience with ID "%1" does not exist.', $audienceId)
            );
        }

        return $audience;
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

    public function save(AudienceInterface $audience): AudienceInterface
    {
        try {
            /** @var Audience $audience */
            $this->resource->save($audience);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save audience: %1', $e->getMessage()),
                $e
            );
        }

        return $audience;
    }

    public function delete(AudienceInterface $audience): bool
    {
        try {
            /** @var Audience $audience */
            $this->resource->delete($audience);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete audience: %1', $e->getMessage()),
                $e
            );
        }

        return true;
    }
}
