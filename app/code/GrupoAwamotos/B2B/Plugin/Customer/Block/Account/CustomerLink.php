<?php

/**
 * Plugin to hide standard customer registration link in header
 * When B2B mode is set to "strict"
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer\Block\Account;

use Magento\Customer\Block\Account\Customer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CustomerLink
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Hide registration link in strict mode
     *
     * @param Customer $subject
     * @param string $result
     * @return string
     */
    public function afterGetCreateAccountUrl(
        Customer $subject,
        $result
    ) {
        $b2bMode = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/general/b2b_mode',
            ScopeInterface::SCOPE_STORE
        );

        $isEnabled = $this->scopeConfig->isSetFlag(
            'grupoawamotos_b2b/general/enabled',
            ScopeInterface::SCOPE_STORE
        );

        // If strict mode, return B2B registration URL
        if ($isEnabled && $b2bMode === 'strict') {
            return $subject->getUrl('b2b/register');
        }

        return $result;
    }
}
