<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Customer;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Dedicated Suggested Cart Page Controller
 */
class SuggestedCart implements HttpGetActionInterface
{
    private PageFactory $pageFactory;
    private CustomerSession $customerSession;
    private RedirectFactory $redirectFactory;

    public function __construct(
        PageFactory $pageFactory,
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
    }

    public function execute()
    {
        // Redirect if not logged in
        if (!$this->customerSession->isLoggedIn()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('customer/account/login');
            return $redirect;
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Carrinho Sugerido'));

        return $page;
    }
}
