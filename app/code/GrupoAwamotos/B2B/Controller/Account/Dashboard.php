<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Dashboard implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $resultPageFactory,
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    /**
     * @return Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('customer/account/login');
            return $redirect;
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Minha Conta B2B'));

        return $resultPage;
    }
}
