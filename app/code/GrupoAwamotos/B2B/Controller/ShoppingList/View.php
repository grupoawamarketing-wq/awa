<?php
/**
 * View Shopping List Controller
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\ShoppingList;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use GrupoAwamotos\B2B\Model\ShoppingListService;

class View implements HttpGetActionInterface
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
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var ShoppingListService
     */
    private $shoppingListService;

    /**
     * @param PageFactory $pageFactory
     * @param CustomerSession $customerSession
     * @param RedirectFactory $redirectFactory
     * @param RequestInterface $request
     * @param ManagerInterface $messageManager
     * @param ShoppingListService $shoppingListService
     */
    public function __construct(
        PageFactory $pageFactory,
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory,
        RequestInterface $request,
        ManagerInterface $messageManager,
        ShoppingListService $shoppingListService
    ) {
        $this->pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->shoppingListService = $shoppingListService;
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

        $listId = (int)$this->request->getParam('id');

        if (!$listId) {
            $this->messageManager->addErrorMessage(__('Lista não especificada.'));
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/shoppinglist');
        }

        try {
            $list = $this->shoppingListService->getList($listId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Lista não encontrada.'));
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/shoppinglist');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set($list->getName());

        return $page;
    }
}
