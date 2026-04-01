<?php

/**
 * Plugin to redirect customer registration page to B2B registration
 * When B2B mode is set to "strict"
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer\Account;

use Magento\Customer\Controller\Account\Create;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\ScopeInterface;

class CreateRedirect
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
     * @param ScopeConfigInterface $scopeConfig
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * Redirect to B2B registration page if strict mode is enabled
     *
     * @param Create $subject
     * @param callable $proceed
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function aroundExecute(
        Create $subject,
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

        // If strict mode, redirect to B2B registration
        if ($isEnabled && $b2bMode === 'strict') {
            $this->messageManager->addNoticeMessage(
                __('Esta loja aceita apenas cadastros empresariais (B2B). Por favor, preencha o formulário abaixo com os dados da sua empresa.')
            );

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('b2b/register');
            return $resultRedirect;
        }

        // Mixed mode - show standard registration
        return $proceed();
    }
}
