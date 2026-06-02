<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\SectraQueue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::sectra_queue';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_B2B::platform_sectra_queue');
        $page->getConfig()->getTitle()->prepend(__('Fila Sectra — Pedidos ERP'));

        return $page;
    }
}
