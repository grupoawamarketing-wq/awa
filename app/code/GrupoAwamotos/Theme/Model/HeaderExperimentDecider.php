<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Model;

class HeaderExperimentDecider
{
    private const CONTROL_VARIANT = 'control';
    private const DEFAULT_VARIANT = 'v2';
    private const EXPERIMENT_CODE = 'header_progressive_rollout';

    /**
     * @return array<string, int|string|bool>
     */
    public function decide(string $visitorSeed, bool $enabled, int $rolloutPercentage, string $variantCode): array
    {
        $normalizedRollout = $this->normalizeRolloutPercentage($rolloutPercentage);
        $normalizedVariant = $this->normalizeVariantCode($variantCode);
        $bucket = $this->calculateBucket($visitorSeed);
        $active = $enabled && $normalizedRollout > 0 && $bucket < $normalizedRollout;

        return [
            'experiment' => self::EXPERIMENT_CODE,
            'enabled' => $enabled,
            'active' => $active,
            'bucket' => $bucket,
            'rollout_percentage' => $normalizedRollout,
            'variant' => $active ? $normalizedVariant : self::CONTROL_VARIANT,
            'control_variant' => self::CONTROL_VARIANT,
        ];
    }

    public function calculateBucket(string $visitorSeed): int
    {
        $normalizedSeed = trim($visitorSeed) !== '' ? $visitorSeed : 'guest:anonymous';
        $hash = hash('sha256', $normalizedSeed);

        return (int) (hexdec(substr($hash, 0, 8)) % 100);
    }

    public function normalizeRolloutPercentage(int $rolloutPercentage): int
    {
        return max(0, min(100, $rolloutPercentage));
    }

    public function normalizeVariantCode(string $variantCode): string
    {
        $normalized = strtolower(trim($variantCode));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?: '';
        $normalized = trim($normalized, '-_');

        return $normalized !== '' ? $normalized : self::DEFAULT_VARIANT;
    }
}
