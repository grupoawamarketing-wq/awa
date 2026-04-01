<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use Psr\Log\LoggerInterface;

/**
 * Admin AJAX Controller - Filtered Purchase History
 */
class FilteredHistoryData extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::customers';

    private JsonFactory $jsonFactory;
    private PurchaseHistory $purchaseHistory;
    private ProductSuggestion $productSuggestion;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        PurchaseHistory $purchaseHistory,
        ProductSuggestion $productSuggestion,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->purchaseHistory = $purchaseHistory;
        $this->productSuggestion = $productSuggestion;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $customerCode = (int) $this->getRequest()->getParam('id');
        if (!$customerCode) {
            return $result->setData([
                'success' => false,
                'message' => 'Código do cliente não informado.'
            ]);
        }

        try {
            $period = $this->getRequest()->getParam('period', 'month');
            $daysBack = match ($period) {
                'month' => 30,
                'quarter' => 90,
                'semester' => 180,
                'year' => 365,
                'all' => null,
                default => 30,
            };

            $page = max((int) $this->getRequest()->getParam('page', 1), 1);
            $limit = min(max((int) $this->getRequest()->getParam('limit', 20), 1), 100);
            $offset = ($page - 1) * $limit;

            $filters = [
                'days_back' => $daysBack,
                'min_price' => (float) $this->getRequest()->getParam('min_price', 0),
                'max_price' => (float) $this->getRequest()->getParam('max_price', 0),
                'sort_by' => $this->getRequest()->getParam('sort_by', 'date_desc'),
                'limit' => $limit,
                'offset' => $offset,
            ];

            $data = $this->purchaseHistory->getFilteredHistory($customerCode, $filters);

            // Enrich items with Magento data
            $items = $data['items'] ?? [];
            if (!empty($items)) {
                $items = $this->productSuggestion->enrichWithMagentoData($items);
            }

            $totalCount = $data['total_count'] ?? 0;
            $totalPages = $limit > 0 ? (int) ceil($totalCount / $limit) : 1;

            return $result->setData([
                'success' => true,
                'items' => $items,
                'total_count' => $totalCount,
                'page' => $page,
                'total_pages' => $totalPages,
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Admin] Filtered history error: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => 'Erro ao carregar histórico. Tente novamente ou verifique os logs.'
            ]);
        }
    }
}
