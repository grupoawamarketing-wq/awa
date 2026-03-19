<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

class CnpjResolver
{
    public function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) === 14 ? $digits : '';
    }

    public function resolveFromValues(string ...$values): string
    {
        foreach ($values as $value) {
            $cnpj = $this->normalize($value);
            if ($cnpj !== '') {
                return $cnpj;
            }
        }

        return '';
    }
}
