<?php
/**
 * Shopping List Index Controller
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\ShoppingList;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;

class Index implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @param PageFactory $pageFactory
     * @param CustomerSession $customerSession
     * @param RedirectFactory $redirectFactory
     */
    public function __construct(
        PageFactory $pageFactory,
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/account/login');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Minhas Listas de Compras'));

        return $page;
    }
}
