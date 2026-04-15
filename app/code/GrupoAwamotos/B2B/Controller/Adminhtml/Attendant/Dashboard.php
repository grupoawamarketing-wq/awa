<?php

/**
 * Controller Admin — Dashboard pessoal do Atendente.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Attendant;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Dashboard extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::attendants';

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
        $resultPage->setActiveMenu('GrupoAwamotos_B2B::attendant_dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('Meu Painel — Atendente'));
        return $resultPage;
    }
}
