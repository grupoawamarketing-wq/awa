<?php

/**
 * Block para página de claim de conta
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use GrupoAwamotos\B2B\Model\AuthLogoResolver;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Header\Logo;

class Claim extends Template
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

    public function getFormActionUrl(): string
    {
        return $this->getUrl('b2b/account/claimPost');
    }

    public function getLoginUrl(): string
    {
        return $this->getUrl('b2b/account/login');
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
