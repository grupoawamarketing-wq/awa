<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Controller\Adminhtml\Consent;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_WhatsAppCommerce::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_WhatsAppCommerce::consent_log');
        $page->getConfig()->getTitle()->prepend(__('WhatsApp - Consent Log (LGPD)'));

        return $page;
    }
}
