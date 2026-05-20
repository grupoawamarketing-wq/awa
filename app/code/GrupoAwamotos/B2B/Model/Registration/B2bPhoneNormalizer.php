<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

/**
 * Normalizes Brazilian phone numbers for B2B registration.
 */
class B2bPhoneNormalizer
{
    public const MIN_DIGITS = 10;
    public const MAX_DIGITS = 11;

    /**
     * Returns digits-only representation.
     */
    public function digitsOnly(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    public function isValidBrazilianPhone(string $phone): bool
    {
        $digits = $this->digitsOnly($phone);
        $length = strlen($digits);

        if ($length < self::MIN_DIGITS || $length > self::MAX_DIGITS) {
            return false;
        }

        $ddd = (int) substr($digits, 0, 2);

        return $ddd >= 11 && $ddd <= 99;
    }

    /**
     * Removes visual mask while preserving DDD in formatted output.
     */
    public function normalize(string $phone): string
    {
        $digits = $this->digitsOnly($phone);

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return trim($phone);
    }
}
