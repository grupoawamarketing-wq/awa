<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class HeaderData implements ArgumentInterface
{
    private const XML_PATH_STICKY_ENABLE = 'themeoption/header/sticky_enable';
    private const XML_PATH_STICKY_LOGO = 'themeoption/header/sticky_logo';
    private const XML_PATH_HEADER_EXPERIMENT_ENABLED = 'grupoawamotos_theme/header_experiment/enabled';
    private const XML_PATH_HEADER_EXPERIMENT_ROLLOUT = 'grupoawamotos_theme/header_experiment/rollout_percentage';
    private const XML_PATH_HEADER_EXPERIMENT_SEED = 'grupoawamotos_theme/header_experiment/variant_seed';
    private const UPLOAD_DIR = 'rokanthemes/stickylogo';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Check if sticky header is enabled in theme configuration.
     *
     * @return bool
     */
    public function isStickyHeaderEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_STICKY_ENABLE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the sticky logo URL if configured.
     *
     * @return string
     */
    public function getStickyLogoUrl(): string
    {
        $logo = (string) $this->scopeConfig->getValue(
            self::XML_PATH_STICKY_LOGO,
            ScopeInterface::SCOPE_STORE
        );

        $normalizedLogo = $this->normalizeLogoPath($logo);
        if ($normalizedLogo === '') {
            return '';
        }

        try {
            $store = $this->storeManager->getStore();
            $mediaUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

            return rtrim($mediaUrl, '/') . '/' . self::UPLOAD_DIR . '/' . $normalizedLogo;
        } catch (\Throwable) {
            return '';
        }
    }

    public function isHeaderExperimentEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HEADER_EXPERIMENT_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getHeaderExperimentRolloutPercentage(): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_HEADER_EXPERIMENT_ROLLOUT,
            ScopeInterface::SCOPE_STORE
        );

        if ($value < 0) {
            return 0;
        }

        if ($value > 100) {
            return 100;
        }

        return $value;
    }

    public function getHeaderExperimentSeed(): string
    {
        $seed = (string) $this->scopeConfig->getValue(
            self::XML_PATH_HEADER_EXPERIMENT_SEED,
            ScopeInterface::SCOPE_STORE
        );

        return trim($seed) !== '' ? trim($seed) : 'home5_header_v1';
    }

    private function normalizeLogoPath(string $logo): string
    {
        $normalized = trim($logo);
        if ($normalized === '') {
            return '';
        }

        $normalized = ltrim($normalized, '/');
        $prefixPattern = '#^' . preg_quote(self::UPLOAD_DIR, '#') . '/?#';
        $normalized = (string) preg_replace($prefixPattern, '', $normalized);

        return trim($normalized);
    }
}
