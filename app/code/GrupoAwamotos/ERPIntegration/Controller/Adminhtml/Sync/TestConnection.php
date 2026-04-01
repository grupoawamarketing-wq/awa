<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;

class TestConnection extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::config';

    private JsonFactory $jsonFactory;
    private ConnectionInterface $connection;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ConnectionInterface $connection
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->connection = $connection;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $data = $this->connection->testConnection();
        return $result->setData($data);
    }
}
