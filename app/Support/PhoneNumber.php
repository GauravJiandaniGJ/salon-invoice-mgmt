<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Indian phone numbers, stored as E.164 without "+" (e.g. "919876543210").
 */
final class PhoneNumber
{
    /**
     * Rules (PLAN §9): strip non-digits; 10 digits starting 6–9 → prefix 91;
     * 11 digits starting 0 → drop 0, prefix 91; 12 digits starting 91 → keep; else invalid.
     *
     * @throws InvalidArgumentException
     */
    public static function normalise(?string $input): string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) {
            return '91'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91') && preg_match('/^91[6-9]/', $digits)) {
            return $digits;
        }

        throw new InvalidArgumentException('Enter a valid 10-digit Indian mobile number.');
    }

    public static function isValid(?string $input): bool
    {
        try {
            self::normalise($input);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** "919876543210" → "+91 98765 43210" */
    public static function display(?string $normalised): string
    {
        if (! $normalised || strlen($normalised) !== 12) {
            return (string) $normalised;
        }

        return '+91 '.substr($normalised, 2, 5).' '.substr($normalised, 7);
    }

    /** "919876543210" → "98XXXX3210" */
    public static function masked(?string $normalised): string
    {
        if (! $normalised || strlen($normalised) !== 12) {
            return (string) $normalised;
        }

        $local = substr($normalised, 2);

        return substr($local, 0, 2).'XXXX'.substr($local, -4);
    }
}
