<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Customer;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\RequestInterface;

/**
 * AJAX Controller for Customer Suggestions
 */
class Suggestions implements HttpGetActionInterface
{
    private JsonFactory $jsonFactory;
    private CustomerSession $customerSession;
    private PurchaseHistory $purchaseHistory;
    private ProductSuggestion $productSuggestion;
    private Helper $helper;
    private RequestInterface $request;

    public function __construct(
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        PurchaseHistory $purchaseHistory,
        ProductSuggestion $productSuggestion,
        Helper $helper,
        RequestInterface $request
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->purchaseHistory = $purchaseHistory;
        $this->productSuggestion = $productSuggestion;
        $this->helper = $helper;
        $this->request = $request;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Check if enabled
        if (!$this->helper->isEnabled() || !$this->helper->isSuggestionsEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => 'Sugestões desabilitadas'
            ]);
        }

        // Check if logged in
        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => 'Cliente não autenticado'
            ]);
        }

        // Get customer CNPJ
        $customer = $this->customerSession->getCustomer();
        $cnpj = $customer->getData('b2b_cnpj') ?: $customer->getTaxvat();

        if (empty($cnpj)) {
            return $result->setData([
                'success' => false,
                'message' => 'CNPJ não encontrado'
            ]);
        }

        // Get ERP customer code
        $customerCode = $this->purchaseHistory->getCustomerCodeByCnpj($cnpj);

        if (!$customerCode) {
            return $result->setData([
                'success' => false,
                'message' => 'Cliente não encontrado no ERP'
            ]);
        }

        // Get type of data requested
        $type = $this->request->getParam('type', 'all');
        $limit = (int)$this->request->getParam('limit', 10);
        $limit = min(max($limit, 1), 50); // Between 1 and 50

        $data = ['success' => true];

        switch ($type) {
            case 'summary':
                $data['summary'] = $this->purchaseHistory->getCustomerSummary($customerCode);
                break;

            case 'orders':
                $data['orders'] = $this->purchaseHistory->getLastOrders($customerCode, $limit);
                break;

            case 'purchased':
                $data['purchased'] = $this->purchaseHistory->getMostPurchasedProducts($customerCode, $limit);
                break;

            case 'suggestions':
                $data['suggestions'] = $this->productSuggestion->getSuggestions($customerCode, $limit);
                break;

            case 'reorder':
                $data['reorder'] = $this->productSuggestion->getReorderSuggestions($customerCode, $limit);
                break;

            case 'trending':
                $data['trending'] = $this->productSuggestion->getTrendingProducts($limit);
                break;

            case 'all':
            default:
                $data['customer'] = $this->purchaseHistory->getCustomerInfo($customerCode);
                $data['summary'] = $this->purchaseHistory->getCustomerSummary($customerCode);
                $data['orders'] = $this->purchaseHistory->getLastOrders($customerCode, 5);
                $data['purchased'] = $this->purchaseHistory->getMostPurchasedProducts($customerCode, 10);
                $data['suggestions'] = $this->productSuggestion->getSuggestions($customerCode, 10);
                break;
        }

        return $result->setData($data);
    }
}
