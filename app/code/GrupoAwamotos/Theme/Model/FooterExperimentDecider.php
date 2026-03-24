<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Model;

class FooterExperimentDecider
{
    private const CONTROL_VARIANT = 'control';
    private const DEFAULT_VARIANT = 'treatment';
    private const DEFAULT_VARIANT_SEED = 'home5_footer_v1';
    private const EXPERIMENT_CODE = 'footer_progressive_rollout';

    /**
     * @return array<string, int|string|bool>
     */
    public function decide(
        string $visitorSeed,
        string $variantSeed,
        bool $enabled,
        int $rolloutPercentage,
        string $variantCode
    ): array {
        $normalizedSeed = $this->normalizeVariantSeed($variantSeed);
        $normalizedRollout = $this->normalizeRolloutPercentage($rolloutPercentage);
        $normalizedVariant = $this->normalizeVariantCode($variantCode);
        $bucket = $this->calculateBucket($normalizedSeed . '|' . $visitorSeed);
        $active = $enabled && $normalizedRollout > 0 && $bucket < $normalizedRollout;

        return [
            'experiment' => self::EXPERIMENT_CODE,
            'enabled' => $enabled,
            'active' => $active,
            'is_active' => $active,
            'bucket' => $bucket,
            'seed' => $normalizedSeed,
            'rollout_percentage' => $normalizedRollout,
            'variant' => $active ? $normalizedVariant : self::CONTROL_VARIANT, // phpcs:ignore Squiz.Operators.ComparisonOperatorUsage
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
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-_');

        return $normalized !== '' ? $normalized : self::DEFAULT_VARIANT;
    }

    public function normalizeVariantSeed(string $variantSeed): string
    {
        $normalized = strtolower(trim($variantSeed));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-_');

        return $normalized !== '' ? $normalized : self::DEFAULT_VARIANT_SEED;
    }

    public function getDefaultVariantCode(): string
    {
        return self::DEFAULT_VARIANT;
    }
}
