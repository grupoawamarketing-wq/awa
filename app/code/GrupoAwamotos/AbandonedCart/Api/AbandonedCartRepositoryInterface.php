<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Api;

use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface AbandonedCartRepositoryInterface
{
    public function save(AbandonedCartInterface $abandonedCart): AbandonedCartInterface;

    public function getById(int $id): AbandonedCartInterface;

    public function getByQuoteId(int $quoteId): ?AbandonedCartInterface;

    public function delete(AbandonedCartInterface $abandonedCart): bool;

    public function deleteById(int $id): bool;

    public function getList(SearchCriteriaInterface $criteria);

    public function getPendingForEmail(int $emailNumber, int $limit = 100): array;

    public function markAsRecovered(int $quoteId): bool;
}
