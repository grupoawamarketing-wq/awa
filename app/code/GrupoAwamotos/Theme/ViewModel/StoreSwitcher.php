<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class StoreSwitcher implements ArgumentInterface
{
    private StoreManagerInterface $storeManager;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    public function getCurrentStore()
    {
        return $this->storeManager->getStore();
    }

    public function getFlagUrl(string $storeCode): ?string
    {
        $flag = $this->scopeConfig->getValue(
            'general/country/flag',
            ScopeInterface::SCOPE_STORE,
            $storeCode
        );

        if (!$flag) {
            return null;
        }

        $store = $this->storeManager->getStore($storeCode);
        return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'logo/' . ltrim($flag, '/');
    }
}
