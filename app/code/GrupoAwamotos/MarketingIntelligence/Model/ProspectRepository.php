<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model;

use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterface;
use GrupoAwamotos\MarketingIntelligence\Api\ProspectRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect as ProspectResource;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ProspectRepository implements ProspectRepositoryInterface
{
    public function __construct(
        private readonly ProspectResource $resource,
        private readonly ProspectFactory $prospectFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    public function getById(int $prospectId): ProspectInterface
    {
        $prospect = $this->prospectFactory->create();
        $this->resource->load($prospect, $prospectId);

        if (!$prospect->getProspectId()) {
            throw new NoSuchEntityException(
                __('Prospect with ID "%1" does not exist.', $prospectId)
            );
        }

        return $prospect;
    }

    public function getByCnpj(string $cnpj): ProspectInterface
    {
        $prospect = $this->prospectFactory->create();
        $this->resource->load($prospect, $cnpj, 'cnpj');

        if (!$prospect->getProspectId()) {
            throw new NoSuchEntityException(
                __('Prospect with CNPJ "%1" does not exist.', $cnpj)
            );
        }

        return $prospect;
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

    public function save(ProspectInterface $prospect): ProspectInterface
    {
        try {
            /** @var Prospect $prospect */
            $this->resource->save($prospect);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save prospect: %1', $e->getMessage()),
                $e
            );
        }

        return $prospect;
    }

    public function delete(ProspectInterface $prospect): bool
    {
        try {
            /** @var Prospect $prospect */
            $this->resource->delete($prospect);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete prospect: %1', $e->getMessage()),
                $e
            );
        }

        return true;
    }
}
