<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Helper;

use GrupoAwamotos\Theme\Model\HeaderExperimentDecider;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\ScopeInterface;

class HeaderExperiment extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'grupoawamotos_theme/header_experiment/enabled';
    private const XML_PATH_ROLLOUT_PERCENTAGE = 'grupoawamotos_theme/header_experiment/rollout_percentage';
    private const XML_PATH_VARIANT_SEED = 'grupoawamotos_theme/header_experiment/variant_seed';

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly SessionManagerInterface $sessionManager,
        private readonly HeaderExperimentDecider $decider
    ) {
        parent::__construct($context);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRolloutPercentage(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_ROLLOUT_PERCENTAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->decider->normalizeRolloutPercentage($value);
    }

    public function getVariantCode(?int $storeId = null): string
    {
        return $this->decider->getDefaultVariantCode();
    }

    public function getVariantSeed(?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_VARIANT_SEED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->decider->normalizeVariantSeed($value);
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function getPayload(?int $storeId = null): array
    {
        return $this->decider->decide(
            $this->resolveVisitorSeed(),
            $this->getVariantSeed($storeId),
            $this->isEnabled($storeId),
            $this->getRolloutPercentage($storeId),
            $this->getVariantCode($storeId)
        );
    }

    private function resolveVisitorSeed(): string
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId > 0) {
            return 'customer:' . $customerId;
        }

        $sessionId = trim((string) $this->sessionManager->getSessionId());
        if ($sessionId !== '') {
            return 'session:' . $sessionId;
        }

        return 'guest:anonymous';
    }
}
