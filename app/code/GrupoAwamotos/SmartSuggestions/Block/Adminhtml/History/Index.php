<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Block\Adminhtml\History;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory\CollectionFactory;
use GrupoAwamotos\SmartSuggestions\Model\SuggestionHistory;

/**
 * History Index Block
 */
class Index extends Template
{
    protected $_template = 'GrupoAwamotos_SmartSuggestions::history/index.phtml';

    private CollectionFactory $collectionFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get history collection
     */
    public function getHistoryCollection()
    {
        $collection = $this->collectionFactory->create();

        // Apply filters from request
        $status = $this->getRequest()->getParam('status');
        if ($status) {
            $collection->addStatusFilter($status);
        }

        $channel = $this->getRequest()->getParam('channel');
        if ($channel) {
            $collection->addChannelFilter($channel);
        }

        $dateFrom = $this->getRequest()->getParam('date_from');
        $dateTo = $this->getRequest()->getParam('date_to');
        if ($dateFrom && $dateTo) {
            $collection->addDateRangeFilter($dateFrom, $dateTo);
        }

        // Pagination
        $page = (int) $this->getRequest()->getParam('page', 1);
        $limit = 50;
        $collection->setPageSize($limit);
        $collection->setCurPage($page);

        $collection->setOrder('created_at', 'DESC');

        return $collection;
    }

    /**
     * Get conversion statistics
     */
    public function getConversionStats(): array
    {
        $collection = $this->collectionFactory->create();
        return $collection->getConversionStats();
    }

    /**
     * Get available statuses
     */
    public function getAvailableStatuses(): array
    {
        return SuggestionHistory::getAvailableStatuses();
    }

    /**
     * Get status label
     */
    public function getStatusLabel(string $status): string
    {
        $statuses = $this->getAvailableStatuses();
        return isset($statuses[$status]) ? (string) $statuses[$status] : $status;
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(string $status): string
    {
        $classes = [
            'generated' => 'smart-badge-info',
            'sent' => 'smart-badge-primary',
            'delivered' => 'smart-badge-primary',
            'read' => 'smart-badge-warning',
            'converted' => 'smart-badge-success',
            'expired' => 'smart-badge-secondary',
            'send_failed' => 'smart-badge-danger'
        ];

        return $classes[$status] ?? 'smart-badge-secondary';
    }

    /**
     * Format price
     */
    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    /**
     * Format date for history display
     */
    public function formatHistoryDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        return date('d/m/Y H:i', strtotime($date));
    }

    /**
     * Get current page
     */
    public function getCurrentPage(): int
    {
        return (int) $this->getRequest()->getParam('page', 1);
    }

    /**
     * Get total pages
     */
    public function getTotalPages(): int
    {
        $collection = $this->getHistoryCollection();
        return (int) ceil($collection->getSize() / 50);
    }

    /**
     * Get export URL
     */
    public function getExportUrl(): string
    {
        return $this->getUrl('*/export/history');
    }
}
