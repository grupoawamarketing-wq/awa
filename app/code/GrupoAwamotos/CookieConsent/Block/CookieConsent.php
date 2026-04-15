<?php

declare(strict_types=1);

namespace GrupoAwamotos\CookieConsent\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class CookieConsent extends Template
{
    private const XML_PATH_ENABLED = 'grupoawamotos_cookieconsent/general/enabled';
    private const XML_PATH_POLICY_URL = 'grupoawamotos_cookieconsent/general/policy_url';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->_scopeConfig->getValue(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getPolicyUrl(): string
    {
        $url = (string) $this->_scopeConfig->getValue(
            self::XML_PATH_POLICY_URL,
            ScopeInterface::SCOPE_STORE
        );

        return $url ?: $this->getUrl('politica-de-privacidade');
    }
}
