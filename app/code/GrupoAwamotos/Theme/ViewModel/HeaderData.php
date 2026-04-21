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
     * @var array<string, string>
     */
    private array $configValueCache = [];

    /**
     * @var array<string, bool>
     */
    private array $configFlagCache = [];

    private ?string $stickyLogoUrl = null;

    private ?bool $stickyHeaderEnabled = null;

    private ?bool $headerExperimentEnabled = null;

    private ?int $headerExperimentRolloutPercentage = null;

    private ?string $headerExperimentSeed = null;

    /**
     * Check if sticky header is enabled in theme configuration.
     *
     * @return bool
     */
    public function isStickyHeaderEnabled(): bool
    {
        if ($this->stickyHeaderEnabled !== null) {
            return $this->stickyHeaderEnabled;
        }

        $this->stickyHeaderEnabled = $this->getConfigFlag(self::XML_PATH_STICKY_ENABLE);

        return $this->stickyHeaderEnabled;
    }

    /**
     * Get the sticky logo URL if configured.
     *
     * @return string
     */
    public function getStickyLogoUrl(): string
    {
        if ($this->stickyLogoUrl !== null) {
            return $this->stickyLogoUrl;
        }

        $logo = $this->getConfigValue(self::XML_PATH_STICKY_LOGO);

        $normalizedLogo = $this->normalizeLogoPath($logo);
        if ($normalizedLogo === '') {
            $this->stickyLogoUrl = '';

            return '';
        }

        try {
            $store = $this->storeManager->getStore();
            $mediaUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

            $this->stickyLogoUrl = rtrim($mediaUrl, '/') . '/' . self::UPLOAD_DIR . '/' . $normalizedLogo;

            return $this->stickyLogoUrl;
        } catch (\Throwable) {
            $this->stickyLogoUrl = '';

            return '';
        }
    }

    public function isHeaderExperimentEnabled(): bool
    {
        if ($this->headerExperimentEnabled !== null) {
            return $this->headerExperimentEnabled;
        }

        $this->headerExperimentEnabled = $this->getConfigFlag(self::XML_PATH_HEADER_EXPERIMENT_ENABLED);

        return $this->headerExperimentEnabled;
    }

    public function getHeaderExperimentRolloutPercentage(): int
    {
        if ($this->headerExperimentRolloutPercentage !== null) {
            return $this->headerExperimentRolloutPercentage;
        }

        $value = (int) $this->getConfigValue(self::XML_PATH_HEADER_EXPERIMENT_ROLLOUT);

        if ($value < 0) {
            $this->headerExperimentRolloutPercentage = 0;

            return $this->headerExperimentRolloutPercentage;
        }

        if ($value > 100) {
            $this->headerExperimentRolloutPercentage = 100;

            return $this->headerExperimentRolloutPercentage;
        }

        $this->headerExperimentRolloutPercentage = $value;

        return $this->headerExperimentRolloutPercentage;
    }

    public function getHeaderExperimentSeed(): string
    {
        if ($this->headerExperimentSeed !== null) {
            return $this->headerExperimentSeed;
        }

        $seed = $this->getConfigValue(self::XML_PATH_HEADER_EXPERIMENT_SEED);
        $this->headerExperimentSeed = trim($seed) !== '' ? trim($seed) : 'home5_header_v1';

        return $this->headerExperimentSeed;
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

    private function getConfigValue(string $path): string
    {
        if (!array_key_exists($path, $this->configValueCache)) {
            $this->configValueCache[$path] = (string) $this->scopeConfig->getValue(
                $path,
                ScopeInterface::SCOPE_STORE
            );
        }

        return $this->configValueCache[$path];
    }

    private function getConfigFlag(string $path): bool
    {
        if (!array_key_exists($path, $this->configFlagCache)) {
            $this->configFlagCache[$path] = $this->scopeConfig->isSetFlag(
                $path,
                ScopeInterface::SCOPE_STORE
            );
        }

        return $this->configFlagCache[$path];
    }
}
