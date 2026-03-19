<?php
/**
 * Controller para formulário de cadastro B2B
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Register;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
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

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if ($this->customerSession->isLoggedIn()) {
            $redirect = $this->redirectFactory->create();

            return $redirect->setPath('b2b/account/dashboard');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Cadastro B2B - Pessoa Jurídica'));

        return $resultPage;
    }
}
