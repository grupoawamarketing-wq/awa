<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Model\Chat;

use GrupoAwamotos\LiveChat\Model\Config\WidgetExperimentConfig;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Session\SessionManagerInterface;

class WidgetExperimentAssigner
{
    public const VARIANT_CONTROL = 'control';
    public const VARIANT_TREATMENT = 'treatment';

    public function __construct(
        private readonly WidgetExperimentConfig $config,
        private readonly CustomerSession $customerSession,
        private readonly SessionManagerInterface $sessionManager
    ) {
    }

    public function getVariant(): string
    {
        if (!$this->config->isExperimentEnabled()) {
            return self::VARIANT_CONTROL;
        }

        $rolloutPercentage = $this->config->getRolloutPercentage();
        if ($rolloutPercentage <= 0) {
            return self::VARIANT_CONTROL;
        }

        if ($rolloutPercentage >= 100) {
            return self::VARIANT_TREATMENT;
        }

        $identity = $this->resolveIdentity();
        if ($identity === '') {
            return self::VARIANT_CONTROL;
        }

        return $this->resolveBucket($identity) < $rolloutPercentage
            ? self::VARIANT_TREATMENT
            : self::VARIANT_CONTROL;
    }

    public function shouldDeferInit(): bool
    {
        return $this->getVariant() === self::VARIANT_TREATMENT;
    }

    private function resolveIdentity(): string
    {
        if ($this->customerSession->isLoggedIn()) {
            $customerId = (int) $this->customerSession->getCustomerId();
            if ($customerId > 0) {
                return 'customer:' . $customerId;
            }
        }

        $sessionId = trim((string) $this->sessionManager->getSessionId());

        return $sessionId !== '' ? 'session:' . $sessionId : '';
    }

    private function resolveBucket(string $identity): int
    {
        $hash = crc32($this->config->getVariantSeed() . '|' . $identity);

        return ((int) sprintf('%u', $hash)) % 100;
    }
}
