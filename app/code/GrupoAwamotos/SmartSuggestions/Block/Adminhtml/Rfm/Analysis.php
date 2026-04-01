<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Block\Adminhtml\Rfm;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;

/**
 * RFM Analysis Block
 */
class Analysis extends Template
{
    protected $_template = 'GrupoAwamotos_SmartSuggestions::rfm/analysis.phtml';

    private RfmCalculatorInterface $rfmCalculator;
    private ?string $currentSegment = null;
    private int $page = 1;
    private int $pageSize = 50;

    public function __construct(
        Context $context,
        RfmCalculatorInterface $rfmCalculator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->rfmCalculator = $rfmCalculator;

        $this->currentSegment = $this->getRequest()->getParam('segment');
        $this->page = max(1, (int)$this->getRequest()->getParam('page', 1));
    }

    /**
     * Get all segments with statistics
     */
    public function getSegments(): array
    {
        return $this->rfmCalculator->getSegmentStatistics();
    }

    /**
     * Get customers for current segment
     */
    public function getCustomers(): array
    {
        if (!$this->currentSegment) {
            return $this->rfmCalculator->calculateAll();
        }

        return $this->rfmCalculator->getCustomersBySegment($this->currentSegment, 500);
    }

    /**
     * Get paginated customers
     */
    public function getPaginatedCustomers(): array
    {
        $customers = $this->getCustomers();
        $offset = ($this->page - 1) * $this->pageSize;

        return array_slice($customers, $offset, $this->pageSize);
    }

    /**
     * Get total pages
     */
    public function getTotalPages(): int
    {
        return (int)ceil(count($this->getCustomers()) / $this->pageSize);
    }

    /**
     * Get current page
     */
    public function getCurrentPage(): int
    {
        return $this->page;
    }

    /**
     * Get current segment filter
     */
    public function getCurrentSegment(): ?string
    {
        return $this->currentSegment;
    }

    /**
     * Get recommendations for a segment
     */
    public function getRecommendations(string $segment): array
    {
        return $this->rfmCalculator->getRecommendations($segment);
    }

    /**
     * Get segment filter URL
     */
    public function getSegmentUrl(?string $segment): string
    {
        return $this->getUrl('*/*/*', ['segment' => $segment, 'page' => 1]);
    }

    /**
     * Get page URL
     */
    public function getPageUrl(int $page): string
    {
        return $this->getUrl('*/*/*', ['segment' => $this->currentSegment, 'page' => $page]);
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
     * Get RFM explanation
     */
    public function getRfmExplanation(): array
    {
        return [
            'R' => [
                'name' => 'Recência',
                'description' => 'Há quantos dias foi a última compra',
                'interpretation' => 'Quanto menor, melhor (cliente comprou recentemente)'
            ],
            'F' => [
                'name' => 'Frequência',
                'description' => 'Quantos pedidos o cliente fez',
                'interpretation' => 'Quanto maior, melhor (cliente compra com frequência)'
            ],
            'M' => [
                'name' => 'Monetário',
                'description' => 'Valor total gasto pelo cliente',
                'interpretation' => 'Quanto maior, melhor (cliente gasta mais)'
            ]
        ];
    }
}
