<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Customer;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use GrupoAwamotos\ERPIntegration\Model\CnpjResolver;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\RequestInterface;

/**
 * AJAX Controller for Filtered Purchase History
 */
class FilteredHistory implements HttpGetActionInterface
{
    private JsonFactory $jsonFactory;
    private CustomerSession $customerSession;
    private PurchaseHistory $purchaseHistory;
    private ProductSuggestion $productSuggestion;
    private CnpjResolver $cnpjResolver;
    private Helper $helper;
    private RequestInterface $request;

    public function __construct(
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        PurchaseHistory $purchaseHistory,
        ProductSuggestion $productSuggestion,
        CnpjResolver $cnpjResolver,
        Helper $helper,
        RequestInterface $request
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->purchaseHistory = $purchaseHistory;
        $this->productSuggestion = $productSuggestion;
        $this->cnpjResolver = $cnpjResolver;
        $this->helper = $helper;
        $this->request = $request;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->helper->isEnabled() || !$this->helper->isSuggestionsEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => 'Funcionalidade desabilitada'
            ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => 'Cliente não autenticado'
            ]);
        }

        $customer = $this->customerSession->getCustomer();
        $cnpj = $this->resolveCustomerCnpj($customer);

        if (empty($cnpj)) {
            return $result->setData([
                'success' => false,
                'message' => 'CNPJ não encontrado'
            ]);
        }

        $customerCode = $this->purchaseHistory->getCustomerCodeByCnpj($cnpj);

        if (!$customerCode) {
            return $result->setData([
                'success' => false,
                'message' => 'Cliente não encontrado no ERP'
            ]);
        }

        // Parse filter parameters
        $page = max((int)$this->request->getParam('page', 1), 1);
        $limit = min(max((int)$this->request->getParam('limit', 20), 1), 100);
        $offset = ($page - 1) * $limit;

        $filters = [
            'period_days' => (int)$this->request->getParam('period_days', 0),
            'min_freq' => (int)$this->request->getParam('min_freq', 0),
            'max_freq' => (int)$this->request->getParam('max_freq', 0),
            'min_price' => (float)$this->request->getParam('min_price', 0),
            'max_price' => (float)$this->request->getParam('max_price', 0),
            'sort_by' => $this->request->getParam('sort_by', 'days_since_last'),
            'sort_dir' => $this->request->getParam('sort_dir', 'ASC'),
            'limit' => $limit,
            'offset' => $offset,
        ];

        $historyData = $this->purchaseHistory->getFilteredHistory($customerCode, $filters);

        // Enrich items with Magento data (sku field mapping)
        $enrichedItems = [];
        if (!empty($historyData['items'])) {
            // Map 'sku' to 'codigo_material' for enrichWithMagentoData compatibility
            $mapped = array_map(function ($item) {
                $item['codigo_material'] = $item['sku'];
                $item['descricao'] = $item['name'];
                return $item;
            }, $historyData['items']);

            $enrichedItems = $this->productSuggestion->enrichWithMagentoData($mapped);
        }

        $totalCount = $historyData['total_count'];
        $totalPages = (int)ceil($totalCount / $limit);

        return $result->setData([
            'success' => true,
            'items' => $enrichedItems,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'filters_applied' => $filters,
        ]);
    }

    private function resolveCustomerCnpj(object $customer): string
    {
        return $this->cnpjResolver->resolveFromValues(
            (string) $customer->getData('b2b_cnpj'),
            (string) $customer->getTaxvat()
        );
    }
}
