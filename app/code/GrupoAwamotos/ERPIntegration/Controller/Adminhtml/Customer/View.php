<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;

/**
 * Admin Controller - View ERP Customer Details
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::customers';

    private PageFactory $resultPageFactory;
    private JsonFactory $jsonFactory;
    private PurchaseHistory $purchaseHistory;
    private ProductSuggestion $productSuggestion;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $jsonFactory,
        PurchaseHistory $purchaseHistory,
        ProductSuggestion $productSuggestion
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonFactory = $jsonFactory;
        $this->purchaseHistory = $purchaseHistory;
        $this->productSuggestion = $productSuggestion;
    }

    public function execute()
    {
        $customerCode = (int) $this->getRequest()->getParam('id');

        if (!$customerCode) {
            $this->messageManager->addErrorMessage(__('Código do cliente não informado.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        // Check if AJAX request
        if ($this->getRequest()->isAjax()) {
            return $this->getAjaxResponse($customerCode);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_ERPIntegration::customers');
        $resultPage->getConfig()->getTitle()->prepend(__('Detalhes do Cliente ERP #%1', $customerCode));

        return $resultPage;
    }

    private function getAjaxResponse(int $customerCode)
    {
        $result = $this->jsonFactory->create();

        $type = $this->getRequest()->getParam('type', 'all');

        $data = ['success' => true];

        switch ($type) {
            case 'info':
                $data['customer'] = $this->purchaseHistory->getCustomerInfo($customerCode);
                break;
            case 'summary':
                $data['summary'] = $this->purchaseHistory->getCustomerSummary($customerCode);
                break;
            case 'orders':
                $data['orders'] = $this->purchaseHistory->getLastOrders($customerCode, 20);
                break;
            case 'products':
                $data['products'] = $this->purchaseHistory->getMostPurchasedProducts($customerCode, 20);
                break;
            case 'suggestions':
                $data['suggestions'] = $this->productSuggestion->getSuggestions($customerCode, 10);
                break;
            default:
                $data['customer'] = $this->purchaseHistory->getCustomerInfo($customerCode);
                $data['summary'] = $this->purchaseHistory->getCustomerSummary($customerCode);
                $data['orders'] = $this->purchaseHistory->getLastOrders($customerCode, 10);
                $data['products'] = $this->purchaseHistory->getMostPurchasedProducts($customerCode, 10);
                $data['suggestions'] = $this->productSuggestion->getSuggestions($customerCode, 5);
        }

        return $result->setData($data);
    }
}
