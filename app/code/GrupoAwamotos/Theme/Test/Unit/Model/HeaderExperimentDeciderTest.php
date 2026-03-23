<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Model;

use GrupoAwamotos\Theme\Model\HeaderExperimentDecider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\Theme\Model\HeaderExperimentDecider
 */
class HeaderExperimentDeciderTest extends TestCase
{
    private HeaderExperimentDecider $subject;

    protected function setUp(): void
    {
        $this->subject = new HeaderExperimentDecider();
    }

    public function testCalculateBucketIsDeterministic(): void
    {
        $first = $this->subject->calculateBucket('session:abc123');
        $second = $this->subject->calculateBucket('session:abc123');

        $this->assertSame($first, $second);
        $this->assertGreaterThanOrEqual(0, $first);
        $this->assertLessThan(100, $first);
    }

    public function testDecideReturnsControlWhenExperimentIsDisabled(): void
    {
        $result = $this->subject->decide('customer:42', false, 100, 'v2');

        $this->assertFalse($result['active']);
        $this->assertSame('control', $result['variant']);
    }

    public function testDecideActivatesVariantWhenBucketIsInsideRollout(): void
    {
        $bucket = $this->subject->calculateBucket('customer:42');
        $rollout = min(100, $bucket + 1);
        $result = $this->subject->decide('customer:42', true, $rollout, 'compact-topbar');

        $this->assertTrue($result['active']);
        $this->assertSame('compact-topbar', $result['variant']);
    }

    public function testNormalizeVariantCodeFallsBackToDefault(): void
    {
        $this->assertSame('v2', $this->subject->normalizeVariantCode('@@@'));
    }

    public function testNormalizeRolloutPercentageClampsOutsideBounds(): void
    {
        $this->assertSame(0, $this->subject->normalizeRolloutPercentage(-5));
        $this->assertSame(100, $this->subject->normalizeRolloutPercentage(150));
    }
}