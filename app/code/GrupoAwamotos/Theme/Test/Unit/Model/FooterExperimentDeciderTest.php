<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Model;

use GrupoAwamotos\Theme\Model\FooterExperimentDecider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\Theme\Model\FooterExperimentDecider
 */
class FooterExperimentDeciderTest extends TestCase
{
    private FooterExperimentDecider $subject;

    protected function setUp(): void
    {
        $this->subject = new FooterExperimentDecider();
    }

    public function testCalculateBucketIsDeterministic(): void
    {
        $first = $this->subject->calculateBucket('session:footer-123');
        $second = $this->subject->calculateBucket('session:footer-123');

        $this->assertSame($first, $second);
        $this->assertGreaterThanOrEqual(0, $first);
        $this->assertLessThan(100, $first);
    }

    public function testDecideReturnsControlWhenExperimentIsDisabled(): void
    {
        $result = $this->subject->decide('customer:42', 'home5_footer_v1', false, 100, 'treatment');

        $this->assertFalse($result['active']);
        $this->assertSame('control', $result['variant']);
        $this->assertSame('home5_footer_v1', $result['seed']);
    }

    public function testDecideActivatesVariantWhenBucketIsInsideRollout(): void
    {
        $bucket = $this->subject->calculateBucket('home5_footer_v1|customer:42');
        $rollout = min(100, $bucket + 1);
        $result = $this->subject->decide('customer:42', 'home5_footer_v1', true, $rollout, 'trust-bar-v2');

        $this->assertTrue($result['active']);
        $this->assertSame('trust-bar-v2', $result['variant']);
    }

    public function testNormalizeVariantCodeFallsBackToDefault(): void
    {
        $this->assertSame('treatment', $this->subject->normalizeVariantCode('@@@'));
    }

    public function testNormalizeRolloutPercentageClampsOutsideBounds(): void
    {
        $this->assertSame(0, $this->subject->normalizeRolloutPercentage(-5));
        $this->assertSame(100, $this->subject->normalizeRolloutPercentage(150));
    }

    public function testNormalizeVariantSeedFallsBackToDefault(): void
    {
        $this->assertSame('home5_footer_v1', $this->subject->normalizeVariantSeed('@@@'));
    }
}
