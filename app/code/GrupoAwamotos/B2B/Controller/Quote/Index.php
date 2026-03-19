<?php
/**
 * Quote Request Form Controller
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quote;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;

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
     * @var Config
     */
    private $config;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        PageFactory $pageFactory,
        CustomerSession $customerSession,
        Config $config,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        RequestInterface $request
    ) {
        $this->pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->request = $request;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        // Verificar se o módulo está habilitado
        if (!$this->config->isQuoteEnabled()) {
            $redirect = $this->redirectFactory->create();
            $this->messageManager->addNoticeMessage(__('O sistema de cotação não está disponível no momento.'));
            return $redirect->setPath('/');
        }

        // Verificar se visitantes podem solicitar cotação
        if (!$this->customerSession->isLoggedIn() && !$this->config->allowGuestsQuote()) {
            $redirect = $this->redirectFactory->create();
            $this->messageManager->addNoticeMessage(__('Faça login para solicitar uma cotação.'));
            return $redirect->setPath('b2b/account/login');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Solicitar Cotação'));

        return $page;
    }
}
