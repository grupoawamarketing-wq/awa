<?php

/**
 * Block para página de login B2B
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use GrupoAwamotos\B2B\Model\AuthLogoResolver;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Header\Logo;

class Login extends Template
{
    private Logo $logo;
    private AuthLogoResolver $authLogoResolver;

    public function __construct(
        Context $context,
        Logo $logo,
        AuthLogoResolver $authLogoResolver,
        array $data = []
    ) {
        $this->logo = $logo;
        $this->authLogoResolver = $authLogoResolver;
        parent::__construct($context, $data);
    }

    public function getPostActionUrl(): string
    {
        return $this->getUrl('customer/account/loginPost');
    }

    public function getForgotPasswordUrl(): string
    {
        return $this->getUrl('b2b/account/forgotpassword');
    }

    public function getB2BRegisterUrl(): string
    {
        return $this->getUrl('b2b/register');
    }

    public function getClaimAccountUrl(): string
    {
        return $this->getUrl('b2b/account/claim');
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
