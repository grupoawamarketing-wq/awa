<?php

declare(strict_types=1);

namespace Awa\RealTimeDashboard\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Awa\RealTimeDashboard\Model\DashboardDataProvider;

class Data extends Action
{
    const ADMIN_RESOURCE = 'Awa_RealTimeDashboard::dashboard';

    protected $jsonFactory;
    protected $dataProvider;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        DashboardDataProvider $dataProvider
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->dataProvider = $dataProvider;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $data = $this->dataProvider->getData();
        return $result->setData($data);
    }
}
