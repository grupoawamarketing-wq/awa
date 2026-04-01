<?php

/**
 * Plugin to redirect standard customer registration to B2B registration
 * When B2B mode is set to "strict", all registrations must go through B2B flow
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer\Account;

use Magento\Customer\Controller\Account\CreatePost;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\ScopeInterface;

class CreatePostRedirect
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param RedirectInterface $redirect
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        RedirectInterface $redirect
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->redirect = $redirect;
    }

    /**
     * Redirect to B2B registration if strict mode is enabled
     *
     * @param CreatePost $subject
     * @param callable $proceed
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function aroundExecute(
        CreatePost $subject,
        callable $proceed
    ) {
        $b2bMode = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/general/b2b_mode',
            ScopeInterface::SCOPE_STORE
        );

        $isEnabled = $this->scopeConfig->isSetFlag(
            'grupoawamotos_b2b/general/enabled',
            ScopeInterface::SCOPE_STORE
        );

        // If strict mode, block standard registration
        if ($isEnabled && $b2bMode === 'strict') {
            $this->messageManager->addNoticeMessage(
                __('Apenas cadastros empresariais (B2B) são aceitos nesta loja. Por favor, utilize o formulário de cadastro B2B.')
            );

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('b2b/register');
            return $resultRedirect;
        }

        // Mixed mode - allow standard registration
        return $proceed();
    }
}
