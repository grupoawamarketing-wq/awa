<?php

/**
 * Plugin to redirect standard Magento forgot-password page to B2B version
 * When B2B mode is set to "strict"
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer\Account;

use Magento\Customer\Controller\Account\ForgotPassword;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Store\Model\ScopeInterface;

class ForgotPasswordRedirectPlugin
{
    private ScopeConfigInterface $scopeConfig;
    private RedirectFactory $resultRedirectFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RedirectFactory $resultRedirectFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    /**
     * Redirect to B2B forgot-password page if strict mode is enabled
     */
    public function aroundExecute(ForgotPassword $subject, callable $proceed)
    {
        $isEnabled = $this->scopeConfig->isSetFlag(
            'grupoawamotos_b2b/general/enabled',
            ScopeInterface::SCOPE_STORE
        );

        $b2bMode = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/general/b2b_mode',
            ScopeInterface::SCOPE_STORE
        );

        if ($isEnabled && $b2bMode === 'strict') {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('b2b/account/forgotpassword');
            return $resultRedirect;
        }

        return $proceed();
    }
}
