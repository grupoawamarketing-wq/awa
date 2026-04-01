<?php

/**
 * Block para formulário de cadastro B2B
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Register;

use GrupoAwamotos\B2B\Model\AuthLogoResolver;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\ScopeInterface;
use Magento\Theme\Block\Html\Header\Logo;

class Form extends Template
{
    /**
     * @var CustomerSession
     */
    private $customerSession;
    private Logo $logo;
    private AuthLogoResolver $authLogoResolver;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        Logo $logo,
        AuthLogoResolver $authLogoResolver,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->logo = $logo;
        $this->authLogoResolver = $authLogoResolver;
        parent::__construct($context, $data);
    }

    /**
     * Get minimum password length
     *
     * @return int
     */
    public function getMinimumPasswordLength(): int
    {
        return (int) $this->_scopeConfig->getValue(
            'customer/password/minimum_password_length',
            ScopeInterface::SCOPE_STORE
        ) ?: 8;
    }

    /**
     * Get required character classes number
     *
     * @return int
     */
    public function getRequiredCharacterClassesNumber(): int
    {
        return (int) $this->_scopeConfig->getValue(
            'customer/password/required_character_classes_number',
            ScopeInterface::SCOPE_STORE
        ) ?: 3;
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->getUrl('b2b/register/save');
    }

    /**
     * Get login URL
     *
     * @return string
     */
    public function getLoginUrl(): string
    {
        return $this->getUrl('b2b/account/login');
    }

    /**
     * Get customer dashboard URL
     *
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return $this->getUrl('b2b/account/dashboard');
    }

    public function getLogoSrc(): string
    {
        $resolved = trim($this->authLogoResolver->getLogoSrc());
        return $resolved !== '' ? $resolved : $this->logo->getLogoSrc();
    }

    public function getLogoAlt(): string
    {
        $resolved = trim($this->authLogoResolver->getLogoAlt());
        return $resolved !== '' ? $resolved : $this->logo->getLogoAlt();
    }

    public function getHomeUrl(): string
    {
        return $this->getUrl('');
    }
}
