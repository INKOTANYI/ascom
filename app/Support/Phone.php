<?php

namespace App\Support;

/** Rwandan mobile numbers, stored as +2507XXXXXXXX (design: users.phone). */
class Phone
{
    public const PATTERN = '/^\+2507[2389]\d{7}$/';

    public static function looksLikePhone(string $value): bool
    {
        return (bool) preg_match('/^[\d\s+()-]{9,16}$/', $value);
    }

    /** "0788 123 456", "788123456" or "+250 788 123 456" all become "+250788123456". */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($digits, '250')) {
            $digits = substr($digits, 3);
        }
        $digits = ltrim($digits, '0');

        return '+250'.$digits;
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && (bool) preg_match(self::PATTERN, $value);
    }
}
