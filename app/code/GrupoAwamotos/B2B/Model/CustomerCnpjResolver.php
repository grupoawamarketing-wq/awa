<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Resolve CNPJ from customer attributes with consistent priority and normalization.
 */
class CustomerCnpjResolver
{
    /** @var list<string> */
    private const ATTRIBUTE_PRIORITY = [
        'b2b_cnpj',
        'cnpj',
    ];

    public function __construct(
        private readonly CnpjValidator $cnpjValidator
    ) {
    }

    /**
     * Returns 14-digit CNPJ or null when absent/invalid.
     */
    public function resolveDigits(CustomerInterface $customer): ?string
    {
        foreach (self::ATTRIBUTE_PRIORITY as $attributeCode) {
            $attribute = $customer->getCustomAttribute($attributeCode);
            if ($attribute === null) {
                continue;
            }

            $digits = $this->normalizeToDigits((string) $attribute->getValue());
            if ($digits !== null) {
                return $digits;
            }
        }

        $taxvatDigits = $this->normalizeToDigits((string) ($customer->getTaxvat() ?? ''));
        if ($taxvatDigits !== null) {
            return $taxvatDigits;
        }

        return null;
    }

    public function isValidCnpj(string $digits): bool
    {
        return $this->cnpjValidator->validateLocal($digits);
    }

    /**
     * @return array{digits: string, source: string}|null
     */
    public function resolveWithSource(CustomerInterface $customer): ?array
    {
        foreach (self::ATTRIBUTE_PRIORITY as $attributeCode) {
            $attribute = $customer->getCustomAttribute($attributeCode);
            if ($attribute === null) {
                continue;
            }

            $digits = $this->normalizeToDigits((string) $attribute->getValue());
            if ($digits !== null) {
                return ['digits' => $digits, 'source' => $attributeCode];
            }
        }

        $taxvatDigits = $this->normalizeToDigits((string) ($customer->getTaxvat() ?? ''));
        if ($taxvatDigits !== null) {
            return ['digits' => $taxvatDigits, 'source' => 'taxvat'];
        }

        return null;
    }

    private function normalizeToDigits(string $value): ?string
    {
        $digits = $this->cnpjValidator->clean($value);
        if ($digits === '' || strlen($digits) !== 14) {
            return null;
        }

        return $digits;
    }
}
