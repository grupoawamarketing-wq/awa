<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Customer;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use GrupoAwamotos\ERPIntegration\Model\Opportunity\Classifier;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\RequestInterface;

/**
 * AJAX Controller for Opportunity Classification
 */
class OpportunityClassifier implements HttpGetActionInterface
{
    private JsonFactory $jsonFactory;
    private CustomerSession $customerSession;
    private PurchaseHistory $purchaseHistory;
    private ProductSuggestion $productSuggestion;
    private Classifier $classifier;
    private Helper $helper;
    private RequestInterface $request;

    public function __construct(
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        PurchaseHistory $purchaseHistory,
        ProductSuggestion $productSuggestion,
        Classifier $classifier,
        Helper $helper,
        RequestInterface $request
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->purchaseHistory = $purchaseHistory;
        $this->productSuggestion = $productSuggestion;
        $this->classifier = $classifier;
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
        $cnpj = $customer->getData('b2b_cnpj') ?: $customer->getTaxvat();

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

        // Parse parameters
        $opportunityType = $this->request->getParam('opportunity_type', 'all');
        if (!isset(Classifier::TYPES[$opportunityType])) {
            $opportunityType = 'all';
        }

        $page = max((int)$this->request->getParam('page', 1), 1);
        $limit = min(max((int)$this->request->getParam('limit', 20), 1), 100);
        $offset = ($page - 1) * $limit;

        $filters = [
            'sort_by' => $this->request->getParam('sort_by', 'days_since_last'),
            'sort_dir' => $this->request->getParam('sort_dir', 'ASC'),
            'min_price' => (float)$this->request->getParam('min_price', 0),
            'max_price' => (float)$this->request->getParam('max_price', 0),
            'limit' => $limit,
            'offset' => $offset,
        ];

        $data = $this->classifier->classify($customerCode, $opportunityType, $filters);

        // Enrich items with Magento data
        $enrichedItems = [];
        if (!empty($data['items'])) {
            $mapped = array_map(function ($item) {
                $item['codigo_material'] = $item['sku'];
                $item['descricao'] = $item['name'];
                return $item;
            }, $data['items']);

            $enrichedItems = $this->productSuggestion->enrichWithMagentoData($mapped);
        }

        $totalCount = $data['total_count'];
        $totalPages = $limit > 0 ? (int)ceil($totalCount / $limit) : 1;

        return $result->setData([
            'success' => true,
            'items' => $enrichedItems,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'opportunity_type' => $opportunityType,
            'type_labels' => Classifier::TYPES,
        ]);
    }
}
