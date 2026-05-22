<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model\Registration;

use GrupoAwamotos\B2B\Model\Registration\B2bPhoneNormalizer;
use PHPUnit\Framework\TestCase;

class B2bPhoneNormalizerTest extends TestCase
{
    private B2bPhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new B2bPhoneNormalizer();
    }

    public function testNormalizesMobilePhone(): void
    {
        $this->assertSame('(11) 99999-8888', $this->normalizer->normalize('(11) 99999-8888'));
        $this->assertSame('(11) 99999-8888', $this->normalizer->normalize('11999998888'));
    }

    public function testNormalizesLandlinePhone(): void
    {
        $this->assertSame('(19) 3242-3871', $this->normalizer->normalize('1932423871'));
    }

    public function testValidatesBrazilianPhone(): void
    {
        $this->assertTrue($this->normalizer->isValidBrazilianPhone('11999998888'));
        $this->assertTrue($this->normalizer->isValidBrazilianPhone('1932423871'));
        $this->assertFalse($this->normalizer->isValidBrazilianPhone('123'));
        $this->assertFalse($this->normalizer->isValidBrazilianPhone(''));
    }
}
