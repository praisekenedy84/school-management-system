<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal English number-to-words for receipt "amount in words" (no package
 * is installed for this; see composer.json). Handles 0 .. 999,999,999,999 and
 * a 2-decimal cents part rendered as "and NN/100". Currency-aware via the
 * $currency label appended to the integer part.
 *
 * Intentionally simple — not a full i18n money formatter. If precise
 * localized (EN/SW) wording is later required, swap in a dedicated package.
 */
class NumberToWords
{
    private const UNITS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight',
        'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen',
        'sixteen', 'seventeen', 'eighteen', 'nineteen',
    ];

    private const TENS = [
        '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy',
        'eighty', 'ninety',
    ];

    private const SCALES = [
        ['value' => 1000000000, 'name' => 'billion'],
        ['value' => 1000000, 'name' => 'million'],
        ['value' => 1000, 'name' => 'thousand'],
    ];

    public static function money(string|float|int $amount, string $currency = 'TZS'): string
    {
        // Normalize to 2dp string without float drift in the split.
        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $cents] = explode('.', $normalized);

        $words = ucfirst(self::convert((int) $whole))." {$currency}";

        if ($cents !== '00') {
            $words .= " and {$cents}/100";
        }

        return $words.' only';
    }

    private static function convert(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        if ($number < 100) {
            $tens = self::TENS[intdiv($number, 10)];
            $rest = $number % 10;

            return $rest === 0 ? $tens : "{$tens}-".self::UNITS[$rest];
        }

        if ($number < 1000) {
            $hundreds = self::UNITS[intdiv($number, 100)].' hundred';
            $rest = $number % 100;

            return $rest === 0 ? $hundreds : "{$hundreds} ".self::convert($rest);
        }

        foreach (self::SCALES as $scale) {
            if ($number >= $scale['value']) {
                $count = intdiv($number, $scale['value']);
                $rest = $number % $scale['value'];
                $words = self::convert($count).' '.$scale['name'];

                return $rest === 0 ? $words : "{$words} ".self::convert($rest);
            }
        }

        return self::UNITS[0];
    }
}
