<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Block\Adminhtml\Customer\Tab;

use GrupoAwamotos\TawkIntegration\Model\ResourceModel\ChatLog\CollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class ChatLog extends Template
{
    protected $_template = 'GrupoAwamotos_TawkIntegration::customer/tab/chat_log.phtml';

    private CollectionFactory $collectionFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return \GrupoAwamotos\TawkIntegration\Model\ResourceModel\ChatLog\Collection
     */
    public function getChatLogs()
    {
        $customerId = (int) $this->getRequest()->getParam('id');
        if ($customerId <= 0) {
            return $this->collectionFactory->create();
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->setOrder('created_at', 'DESC')
            ->setPageSize(50);
        return $collection;
    }

    public function getCustomerId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }
}
