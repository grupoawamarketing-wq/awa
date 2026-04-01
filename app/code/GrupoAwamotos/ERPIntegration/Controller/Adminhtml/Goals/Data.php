<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Goals;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Model\Goals\Manager as GoalsManager;

/**
 * Get Goals Data via AJAX with filters
 */
class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::goals';

    private JsonFactory $jsonFactory;
    private GoalsManager $goalsManager;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        GoalsManager $goalsManager
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->goalsManager = $goalsManager;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $filters = [
                'period_start' => $this->getRequest()->getParam('period_start'),
                'period_end' => $this->getRequest()->getParam('period_end'),
                'seller' => $this->getRequest()->getParam('seller'),
                'category' => $this->getRequest()->getParam('category'),
                'region' => $this->getRequest()->getParam('region'),
                'customer_segment' => $this->getRequest()->getParam('customer_segment'),
            ];

            $data = $this->goalsManager->getGoalsData($filters);

            return $result->setData([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Erro ao carregar dados: ' . $e->getMessage()
            ]);
        }
    }
}
