<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog\Collection;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog\CollectionFactory;

class SyncLog extends Template
{
    private CollectionFactory $collectionFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
    }

    public function getLogs(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(100);
        return $collection;
    }

    public function getSyncProductsUrl(): string
    {
        return $this->getUrl('erpintegration/sync/products');
    }

    public function getSyncStockUrl(): string
    {
        return $this->getUrl('erpintegration/sync/stock');
    }

    public function getSyncCustomersUrl(): string
    {
        return $this->getUrl('erpintegration/sync/customers');
    }
}
