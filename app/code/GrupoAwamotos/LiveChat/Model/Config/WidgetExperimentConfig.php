<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class WidgetExperimentConfig
{
    private const XML_PATH_ENABLED = 'grupoawamotos_livechat/widget_experiment/enabled';
    private const XML_PATH_ROLLOUT = 'grupoawamotos_livechat/widget_experiment/rollout_percentage';
    private const XML_PATH_VARIANT_SEED = 'grupoawamotos_livechat/widget_experiment/variant_seed';
    private const DEFAULT_VARIANT_SEED = 'livechat_widget_v1';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isExperimentEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getRolloutPercentage(): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_ROLLOUT,
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

    public function getVariantSeed(): string
    {
        $seed = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_VARIANT_SEED,
            ScopeInterface::SCOPE_STORE
        ));

        return $seed !== '' ? $seed : self::DEFAULT_VARIANT_SEED;
    }
}
