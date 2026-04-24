<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Followup;

use GrupoAwamotos\B2B\Model\ResourceModel\Followup\Collection;
use GrupoAwamotos\B2B\Model\ResourceModel\Followup\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class ListAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::followup';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        $attendantId = (int) $this->getRequest()->getParam('attendant_id');

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();

        if ($customerId) {
            $collection->addFieldToFilter('customer_id', $customerId);
        }

        if ($attendantId) {
            $collection->addFieldToFilter('attendant_id', $attendantId);
        }

        $collection->setOrder('created_at', 'DESC')->setPageSize(50);

        $items = [];
        foreach ($collection as $item) {
            $items[] = $item->getData();
        }

        return $result->setData(['success' => true, 'items' => $items]);
    }
}
