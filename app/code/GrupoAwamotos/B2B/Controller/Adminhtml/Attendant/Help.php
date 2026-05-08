<?php

/**
 * Controller Admin — Central de Ajuda do Atendente
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Attendant;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Help extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::attendant_self';

    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_B2B::attendant_help');
        $resultPage->getConfig()->getTitle()->prepend(__('Central de Ajuda — Atendentes'));
        return $resultPage;
    }
}
