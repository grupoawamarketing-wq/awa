<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Block\Adminhtml\Suggestions;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\SmartSuggestions\Api\SuggestionEngineInterface;
use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;

/**
 * Suggestions Index Block
 */
class Index extends Template
{
    protected $_template = 'GrupoAwamotos_SmartSuggestions::suggestions/index.phtml';

    private SuggestionEngineInterface $suggestionEngine;
    private RfmCalculatorInterface $rfmCalculator;

    public function __construct(
        Context $context,
        SuggestionEngineInterface $suggestionEngine,
        RfmCalculatorInterface $rfmCalculator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->suggestionEngine = $suggestionEngine;
        $this->rfmCalculator = $rfmCalculator;
    }

    /**
     * Get top opportunities
     */
    public function getTopOpportunities(int $limit = 20): array
    {
        return $this->suggestionEngine->getTopOpportunities($limit);
    }

    /**
     * Generate cart suggestion for a customer
     */
    public function getCartSuggestion(int $customerId): array
    {
        return $this->suggestionEngine->generateCartSuggestion($customerId);
    }

    /**
     * Get customers for selection
     */
    public function getCustomersForSelection(): array
    {
        $customers = $this->rfmCalculator->calculateAll();
        // Return top 100 by RFM score for dropdown
        return array_slice($customers, 0, 100);
    }

    /**
     * Format price
     */
    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    /**
     * Format number
     */
    public function formatNumber($number): string
    {
        return number_format((int)$number, 0, ',', '.');
    }

    /**
     * Get generate URL
     */
    public function getGenerateUrl(): string
    {
        return $this->getUrl('*/*/generate');
    }
}
