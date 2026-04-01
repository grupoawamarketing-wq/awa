<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Api\ProductSyncInterface;
use Psr\Log\LoggerInterface;

class Products extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::sync';

    private JsonFactory $jsonFactory;
    private ProductSyncInterface $productSync;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ProductSyncInterface $productSync,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->productSync = $productSync;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $syncResult = $this->productSync->syncAll();
            return $result->setData(array_merge(['success' => true], $syncResult));
        } catch (\Exception $e) {
            $this->logger->error('[ERP Sync] Product sync failed', ['exception' => $e]);
            return $result->setData(['success' => false, 'message' => __('Erro na sincroniza\u00e7\u00e3o de produtos. Verifique os logs.')]);
        }
    }
}
