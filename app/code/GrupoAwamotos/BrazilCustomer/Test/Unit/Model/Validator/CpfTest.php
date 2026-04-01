<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Test\Unit\Model\Validator;

use GrupoAwamotos\BrazilCustomer\Model\Validator\Cpf;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\BrazilCustomer\Model\Validator\Cpf
 */
class CpfTest extends TestCase
{
    private Cpf $validator;

    protected function setUp(): void
    {
        $this->validator = new Cpf();
    }

    // ====================================================================
    // validate — CPFs válidos conhecidos
    // ====================================================================

    /**
     * @dataProvider validCpfProvider
     */
    public function testValidateTrueForKnownValidCpfs(string $cpf): void
    {
        $this->assertTrue($this->validator->validate($cpf));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validCpfProvider(): array
    {
        return [
            'sem máscara' => ['52998224725'],
            'com máscara' => ['529.982.247-25'],
            'outro válido' => ['11144477735'],
            'com máscara 2' => ['111.444.777-35'],
            'gerado 1' => ['45532929807'],
            'gerado 2' => ['07691236433'],
        ];
    }

    // ====================================================================
    // validate — CPFs inválidos
    // ====================================================================

    /**
     * @dataProvider invalidCpfProvider
     */
    public function testValidateFalseForInvalidCpfs(string $cpf): void
    {
        $this->assertFalse($this->validator->validate($cpf));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCpfProvider(): array
    {
        return [
            'todos iguais 0' => ['00000000000'],
            'todos iguais 1' => ['11111111111'],
            'todos iguais 2' => ['22222222222'],
            'todos iguais 3' => ['33333333333'],
            'todos iguais 4' => ['44444444444'],
            'todos iguais 5' => ['55555555555'],
            'todos iguais 6' => ['66666666666'],
            'todos iguais 7' => ['77777777777'],
            'todos iguais 8' => ['88888888888'],
            'todos iguais 9' => ['99999999999'],
            'muito curto' => ['1234567890'],
            'muito longo' => ['123456789012'],
            'dígito 1 errado' => ['52998224715'],
            'dígito 2 errado' => ['52998224726'],
            'string vazia' => [''],
            'letras' => ['abcdefghijk'],
            'misto' => ['529.982.247-00'],
        ];
    }

    // ====================================================================
    // format
    // ====================================================================

    public function testFormatAppliesMask(): void
    {
        $this->assertSame('529.982.247-25', $this->validator->format('52998224725'));
    }

    public function testFormatWorksWithAlreadyFormattedInput(): void
    {
        $this->assertSame('529.982.247-25', $this->validator->format('529.982.247-25'));
    }

    public function testFormatReturnsCleanedInputIfNotElevenDigits(): void
    {
        // Input com 10 dígitos — retorna sem formatar
        $this->assertSame('1234567890', $this->validator->format('1234567890'));
    }

    public function testFormatReturnsCleanedInputIfTooLong(): void
    {
        $this->assertSame('123456789012', $this->validator->format('123456789012'));
    }

    public function testFormatStripsNonNumericCharacters(): void
    {
        $this->assertSame('529.982.247-25', $this->validator->format('529-982-247/25'));
    }
}
