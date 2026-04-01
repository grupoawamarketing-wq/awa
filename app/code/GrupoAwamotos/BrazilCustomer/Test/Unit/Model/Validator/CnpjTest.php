<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Test\Unit\Model\Validator;

use GrupoAwamotos\BrazilCustomer\Model\Validator\Cnpj;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\BrazilCustomer\Model\Validator\Cnpj
 */
class CnpjTest extends TestCase
{
    private Cnpj $validator;

    protected function setUp(): void
    {
        $this->validator = new Cnpj();
    }

    // ====================================================================
    // validate — CNPJs válidos
    // ====================================================================

    /**
     * @dataProvider validCnpjProvider
     */
    public function testValidateTrueForKnownValidCnpjs(string $cnpj): void
    {
        $this->assertTrue($this->validator->validate($cnpj));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validCnpjProvider(): array
    {
        return [
            'sem máscara' => ['11222333000181'],
            'com máscara' => ['11.222.333/0001-81'],
            'outro válido' => ['11444777000161'],
            'com máscara 2' => ['11.444.777/0001-61'],
            'gerado 1' => ['45997418000153'],
            'gerado 2' => ['07691236000160'],
        ];
    }

    // ====================================================================
    // validate — CNPJs inválidos
    // ====================================================================

    /**
     * @dataProvider invalidCnpjProvider
     */
    public function testValidateFalseForInvalidCnpjs(string $cnpj): void
    {
        $this->assertFalse($this->validator->validate($cnpj));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCnpjProvider(): array
    {
        return [
            'todos iguais 0' => ['00000000000000'],
            'todos iguais 1' => ['11111111111111'],
            'todos iguais 2' => ['22222222222222'],
            'todos iguais 3' => ['33333333333333'],
            'todos iguais 4' => ['44444444444444'],
            'todos iguais 5' => ['55555555555555'],
            'todos iguais 6' => ['66666666666666'],
            'todos iguais 7' => ['77777777777777'],
            'todos iguais 8' => ['88888888888888'],
            'todos iguais 9' => ['99999999999999'],
            'muito curto' => ['1122233300018'],
            'muito longo' => ['112223330001811'],
            'dígito 1 errado' => ['11222333000191'],
            'dígito 2 errado' => ['11222333000182'],
            'string vazia' => [''],
            'letras' => ['abcdefghijklmn'],
            'misto' => ['11.222.333/0001-00'],
        ];
    }

    // ====================================================================
    // format
    // ====================================================================

    public function testFormatAppliesMask(): void
    {
        $this->assertSame('11.222.333/0001-81', $this->validator->format('11222333000181'));
    }

    public function testFormatWorksWithAlreadyFormattedInput(): void
    {
        $this->assertSame('11.222.333/0001-81', $this->validator->format('11.222.333/0001-81'));
    }

    public function testFormatReturnsCleanedInputIfNot14Digits(): void
    {
        $this->assertSame('1122233300018', $this->validator->format('1122233300018'));
    }

    public function testFormatReturnsCleanedInputIfTooLong(): void
    {
        $this->assertSame('112223330001811', $this->validator->format('112223330001811'));
    }

    public function testFormatStripsNonNumericCharacters(): void
    {
        $this->assertSame('11.222.333/0001-81', $this->validator->format('11-222-333+0001/81'));
    }
}
