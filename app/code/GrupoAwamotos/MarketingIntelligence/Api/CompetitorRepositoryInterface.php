<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api;

use GrupoAwamotos\MarketingIntelligence\Api\Data\CompetitorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface CompetitorRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $competitorId): CompetitorInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * @throws CouldNotSaveException
     */
    public function save(CompetitorInterface $competitor): CompetitorInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(CompetitorInterface $competitor): bool;
}
