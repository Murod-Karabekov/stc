<?php

declare(strict_types=1);

namespace App\Service;

final class PhoneNormalizer
{
    /**
     * Normalizes an Uzbek phone number to standard canonical +998XXXXXXXXX format.
     * Accepts: +998901234567, 998901234567, 901234567, +998 (90) 123-45-67.
     *
     * @throws \InvalidArgumentException if invalid
     */
    public static function normalize(string $raw): string
    {
        // Strip everything except digits and leading plus
        $cleaned = preg_replace('/[^\d+]/', '', trim($raw)) ?? '';

        if (str_starts_with($cleaned, '+')) {
            $digits = substr($cleaned, 1);
        } else {
            $digits = $cleaned;
        }

        // If user entered 9 digits (e.g. 901234567), prepend 998
        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        }

        // Must be exactly 12 digits starting with 998 and valid mobile code (90, 91, 93, 94, 95, 97, 98, 99, 33, 88, 77, 50, etc.)
        if (strlen($digits) !== 12 || !str_starts_with($digits, '998')) {
            throw new \InvalidArgumentException('Telefon raqami O\'zbekiston formati (+998) bo\'yicha 12 ta raqamdan iborat bo\'lishi kerak.');
        }

        return '+' . $digits;
    }

    public static function isValid(string $raw): bool
    {
        try {
            self::normalize($raw);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
