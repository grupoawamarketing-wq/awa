<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Api;

use GrupoAwamotos\MarketingIntelligence\Api\Data\CampaignInsightInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface CampaignInsightRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $insightId): CampaignInsightInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * @throws CouldNotSaveException
     */
    public function save(CampaignInsightInterface $insight): CampaignInsightInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(CampaignInsightInterface $insight): bool;
}
