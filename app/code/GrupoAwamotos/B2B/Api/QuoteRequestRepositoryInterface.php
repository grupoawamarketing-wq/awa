<?php

/**
 * Quote Request Repository Interface
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface QuoteRequestRepositoryInterface
{
    /**
     * Save quote request
     *
     * @param QuoteRequestInterface $quoteRequest
     * @return QuoteRequestInterface
     */
    public function save(QuoteRequestInterface $quoteRequest): QuoteRequestInterface;

    /**
     * Get quote request by ID
     *
     * @param int $requestId
     * @return QuoteRequestInterface
     */
    public function getById(int $requestId): QuoteRequestInterface;

    /**
     * Get quote requests list
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \GrupoAwamotos\B2B\Api\Data\QuoteRequestSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete quote request
     *
     * @param QuoteRequestInterface $quoteRequest
     * @return bool
     */
    public function delete(QuoteRequestInterface $quoteRequest): bool;

    /**
     * Delete quote request by ID
     *
     * @param int $requestId
     * @return bool
     */
    public function deleteById(int $requestId): bool;

    /**
     * Create quote request from cart
     *
     * @param int|null $customerId
     * @param array $customerData
     * @param string|null $message
     * @return QuoteRequestInterface
     */
    public function createFromCart(?int $customerId, array $customerData, ?string $message = null): QuoteRequestInterface;

    /**
     * Update quote request status
     *
     * @param int $requestId
     * @param string $status
     * @param string|null $adminNotes
     * @return QuoteRequestInterface
     */
    public function updateStatus(int $requestId, string $status, ?string $adminNotes = null): QuoteRequestInterface;

    /**
     * Set quoted prices for items
     *
     * @param int $requestId
     * @param array $itemPrices [item_id => quoted_price]
     * @param float|null $quotedTotal
     * @return QuoteRequestInterface
     */
    public function setQuotedPrices(int $requestId, array $itemPrices, ?float $quotedTotal = null): QuoteRequestInterface;

    /**
     * Convert quote request to Magento quote/order
     *
     * @param int $requestId
     * @return int Order ID
     */
    public function convertToOrder(int $requestId): int;
}
