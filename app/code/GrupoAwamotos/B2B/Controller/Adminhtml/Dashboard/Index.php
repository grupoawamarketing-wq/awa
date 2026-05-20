<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Dashboard;

use GrupoAwamotos\B2B\Platform\Model\Config\PlatformConfig;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::platform_dashboard_view';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly PlatformConfig $platformConfig
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if (!$this->platformConfig->isExecutiveDashboardEnabled()) {
            $resultRedirect = $this->resultRedirectFactory->create();

            return $resultRedirect->setPath('awa_commercial/commercialdashboard/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_B2B::platform_dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('Dashboard B2B'));

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE)
            || $this->_authorization->isAllowed('GrupoAwamotos_B2B::commercial_dashboard');
    }
}
