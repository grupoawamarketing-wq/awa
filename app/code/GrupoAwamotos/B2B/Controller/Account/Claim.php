<?php
/**
 * Controller para página de claim de conta (GET)
 * Para clientes que já compraram offline e precisam de acesso online
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Account;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;

class Claim implements HttpGetActionInterface
{
    private PageFactory $resultPageFactory;
    private CustomerSession $customerSession;
    private RedirectFactory $redirectFactory;

    public function __construct(
        PageFactory $resultPageFactory,
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
    }

    public function execute()
    {
        if ($this->customerSession->isLoggedIn()) {
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/account/dashboard');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Ativar Acesso Online'));

        return $resultPage;
    }
}
