<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Adminhtml\Customer;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use GrupoAwamotos\ERPIntegration\Model\Opportunity\Classifier;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;

/**
 * Admin Block - ERP Customer View
 */
class View extends Template
{
    protected $_template = 'GrupoAwamotos_ERPIntegration::customer/view.phtml';

    private PurchaseHistory $purchaseHistory;
    private ProductSuggestion $productSuggestion;
    private Classifier $classifier;
    private RfmCalculator $rfmCalculator;
    private ?int $customerCode = null;

    public function __construct(
        Context $context,
        PurchaseHistory $purchaseHistory,
        ProductSuggestion $productSuggestion,
        Classifier $classifier,
        RfmCalculator $rfmCalculator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->purchaseHistory = $purchaseHistory;
        $this->productSuggestion = $productSuggestion;
        $this->classifier = $classifier;
        $this->rfmCalculator = $rfmCalculator;
        $this->customerCode = (int) $this->getRequest()->getParam('id');
    }

    /**
     * Get customer code
     */
    public function getCustomerCode(): int
    {
        return $this->customerCode;
    }

    /**
     * Get customer info
     */
    public function getCustomerInfo(): ?array
    {
        return $this->purchaseHistory->getCustomerInfo($this->customerCode);
    }

    /**
     * Get purchase summary
     */
    public function getPurchaseSummary(): array
    {
        return $this->purchaseHistory->getCustomerSummary($this->customerCode);
    }

    /**
     * Get last orders
     */
    public function getLastOrders(int $limit = 20): array
    {
        return $this->purchaseHistory->getLastOrders($this->customerCode, $limit);
    }

    /**
     * Get most purchased products
     */
    public function getMostPurchasedProducts(int $limit = 20): array
    {
        return $this->purchaseHistory->getMostPurchasedProducts($this->customerCode, $limit);
    }

    /**
     * Get product suggestions
     */
    public function getSuggestions(int $limit = 10): array
    {
        return $this->productSuggestion->getSuggestions($this->customerCode, $limit);
    }

    /**
     * Get purchase frequency
     */
    public function getPurchaseFrequency(): array
    {
        return $this->purchaseHistory->getPurchaseFrequency($this->customerCode);
    }

    /**
     * Get monthly purchase trend for charts
     */
    public function getMonthlyTrend(int $months = 12): array
    {
        try {
            return $this->purchaseHistory->getMonthlyTrend($this->customerCode, $months);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Format price
     */
    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    /**
     * Format date
     */
    public function formatErpDate(?string $date): string
    {
        if (empty($date)) {
            return '-';
        }
        try {
            return (new \DateTime($date))->format('d/m/Y');
        } catch (\Exception $e) {
            return substr($date, 0, 10);
        }
    }

    /**
     * Get status label
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'F' => 'Faturado',
            'E' => 'Entregue',
            'V' => 'Em Separação',
            'S' => 'Saiu p/ Entrega',
            'W' => 'Aguardando',
            'A' => 'Aberto',
            'P' => 'Pendente',
            'L' => 'Liberado',
            'B' => 'Bloqueado',
            'C' => 'Cancelado',
            'X' => 'Excluído',
            default => $status,
        };
    }

    /**
     * Get status CSS class
     */
    public function getStatusClass(string $status): string
    {
        return match ($status) {
            'F', 'E' => 'success',
            'V', 'S', 'L' => 'info',
            'A', 'W' => 'warning',
            'P', 'B' => 'warning',
            'C', 'X' => 'error',
            default => '',
        };
    }

    /**
     * Get back URL
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    /**
     * Get RFM data for customer
     */
    public function getRfmData(): ?array
    {
        try {
            return $this->rfmCalculator->getCustomerRfm($this->customerCode);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get opportunity summary counts
     */
    public function getOpportunitySummary(): array
    {
        try {
            return $this->classifier->getSummary($this->customerCode);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get opportunity type label
     */
    public function getOpportunityTypeLabel(string $type): string
    {
        return Classifier::getTypeLabel($type);
    }

    /**
     * Get opportunity type badge color
     */
    public function getOpportunityTypeBadgeColor(string $type): string
    {
        return Classifier::getTypeBadgeColor($type);
    }
}
