<?php

/**
 * Block para pagina de recuperacao de senha B2B
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use GrupoAwamotos\B2B\Model\AuthLogoResolver;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Header\Logo;

class ForgotPassword extends Template
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

    /**
     * POST action URL — uses standard Magento forgotpasswordpost
     */
    public function getPostActionUrl(): string
    {
        return $this->getUrl('customer/account/forgotpasswordpost');
    }

    public function getLoginUrl(): string
    {
        return $this->getUrl('b2b/account/login');
    }

    public function getRegisterUrl(): string
    {
        return $this->getUrl('b2b/register');
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
