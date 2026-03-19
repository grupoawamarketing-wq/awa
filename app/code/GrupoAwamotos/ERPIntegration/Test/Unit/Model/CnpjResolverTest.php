<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\CnpjResolver;
use PHPUnit\Framework\TestCase;

class CnpjResolverTest extends TestCase
{
    private CnpjResolver $subject;

    protected function setUp(): void
    {
        $this->subject = new CnpjResolver();
    }

    public function testNormalizeReturnsOnlyDigitsForValidCnpj(): void
    {
        $this->assertSame('12345678000190', $this->subject->normalize('12.345.678/0001-90'));
    }

    public function testNormalizeReturnsEmptyStringForCpfLikeValue(): void
    {
        $this->assertSame('', $this->subject->normalize('123.456.789-01'));
    }

    public function testResolveFromValuesReturnsFirstValidCnpj(): void
    {
        $this->assertSame(
            '99888777000166',
            $this->subject->resolveFromValues('', '123.456.789-01', '99.888.777/0001-66')
        );
    }

    public function testResolveFromValuesReturnsEmptyStringWhenNoValidCnpjExists(): void
    {
        $this->assertSame('', $this->subject->resolveFromValues('', '123.456.789-01'));
    }
}
