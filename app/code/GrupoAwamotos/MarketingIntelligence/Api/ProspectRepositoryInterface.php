<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api;

use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface ProspectRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $prospectId): ProspectInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getByCnpj(string $cnpj): ProspectInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * @throws CouldNotSaveException
     */
    public function save(ProspectInterface $prospect): ProspectInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(ProspectInterface $prospect): bool;
}
