<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use Psr\Log\LoggerInterface;

class ResetCircuit extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::sync';

    private JsonFactory $jsonFactory;
    private CircuitBreaker $circuitBreaker;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CircuitBreaker $circuitBreaker,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->circuitBreaker = $circuitBreaker;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $this->circuitBreaker->reset();

            return $result->setData([
                'success' => true,
                'message' => 'Circuit Breaker resetado com sucesso',
                'stats' => $this->circuitBreaker->getStats(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Circuit breaker reset failed', ['exception' => $e]);
            return $result->setData([
                'success' => false,
                'message' => __('Erro ao resetar Circuit Breaker. Verifique os logs.'),
            ]);
        }
    }
}
