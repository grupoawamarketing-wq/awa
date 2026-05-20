<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model\Registration;

use GrupoAwamotos\B2B\Model\Registration\AttributionResolver;
use PHPUnit\Framework\TestCase;

class AttributionResolverTest extends TestCase
{
    private AttributionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AttributionResolver();
    }

    public function testFromParamsCapturesUtmAndCname(): void
    {
        $data = $this->resolver->fromParams([
            'cname' => 'google-ads-motos',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'revendedor-sp',
            'utm_content' => 'banner-1',
            'utm_term' => 'pecas moto',
            'registration_landing' => '/b2b/register/?utm_source=google',
            'vendedor' => '42',
        ]);

        $attrs = $data->toCustomerAttributes();

        self::assertSame('google-ads-motos', $attrs['b2b_registration_campaign']);
        self::assertSame('google', $attrs['b2b_utm_source']);
        self::assertSame('cpc', $attrs['b2b_utm_medium']);
        self::assertSame('revendedor-sp', $attrs['b2b_utm_campaign']);
        self::assertSame(42, $data->erpSellerCode);
        self::assertTrue($data->hasAttendantReferral());
    }

    public function testFromParamsSanitizesHtml(): void
    {
        $data = $this->resolver->fromParams([
            'utm_source' => '<script>alert(1)</script>facebook',
        ]);

        self::assertSame('alert(1)facebook', $data->utmSource);
    }
}
