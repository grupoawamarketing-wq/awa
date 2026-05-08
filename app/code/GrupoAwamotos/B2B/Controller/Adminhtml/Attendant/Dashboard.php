<?php

/**
 * Controller Admin — Dashboard pessoal do Atendente.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Attendant;

use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;

class Dashboard extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::attendant_self';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CurrentAttendant $currentAttendant,
        private readonly RedirectFactory $redirectFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->currentAttendant->isAttendant()) {
            $this->messageManager->addErrorMessage(__('Acesso restrito a atendentes.'));
            return $this->redirectFactory->create()->setPath('adminhtml/dashboard');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_B2B::attendant_dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('Meu Painel — Atendente'));
        return $resultPage;
    }
}
