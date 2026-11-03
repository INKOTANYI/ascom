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

    /** "+250788123456" becomes "+250 788 123 456". */
    public static function display(?string $value): ?string
    {
        if (! $value || ! preg_match('/^\+250(\d{3})(\d{3})(\d{3})$/', $value, $m)) {
            return $value;
        }

        return "+250 {$m[1]} {$m[2]} {$m[3]}";
    }

    /** The 9 digits after +250, as the phone input expects them. */
    public static function local(?string $value): string
    {
        return $value ? substr($value, 4) : '';
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && (bool) preg_match(self::PATTERN, $value);
    }
}
