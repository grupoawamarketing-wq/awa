<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Whatsapp;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;

/**
 * Admin controller for testing WhatsApp Z-API connection
 */
class Test extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::config';

    private JsonFactory $jsonFactory;
    private ZApiClient $zapiClient;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ZApiClient $zapiClient
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->zapiClient = $zapiClient;
    }

    /**
     * Execute test action
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();
        $action = $this->getRequest()->getParam('action', 'test');

        try {
            if ($action === 'send_test') {
                // Send test message
                $phone = $this->getRequest()->getParam('phone');

                if (empty($phone)) {
                    return $result->setData([
                        'success' => false,
                        'message' => 'Telefone nao informado',
                    ]);
                }

                $testResult = $this->zapiClient->testConnection($phone);

                return $result->setData([
                    'success' => $testResult['success'],
                    'message' => $testResult['message'],
                    'phone' => $testResult['phone_connected'] ?? null,
                ]);
            } else {
                // Just test connection status
                $testResult = $this->zapiClient->testConnection();

                return $result->setData([
                    'success' => $testResult['success'],
                    'message' => $testResult['message'],
                    'phone' => $testResult['phone_connected'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage(),
            ]);
        }
    }
}
