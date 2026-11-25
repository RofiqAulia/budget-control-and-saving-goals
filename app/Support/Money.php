<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount as Indonesian Rupiah, e.g. 5400000 => "Rp5,400,000".
     */
    public static function format(float|int|string|null $amount, bool $withSymbol = true, bool $decimals = false): string
    {
        $value = (float) ($amount ?? 0);

        $formatted = number_format(
            $value,
            $decimals ? 2 : 0,
            '.',   // decimal separator
            ','    // thousands separator, matching the spreadsheet
        );

        return $withSymbol ? 'Rp'.$formatted : $formatted;
    }

    /**
     * Short form for chart axes and cards: 5_400_000 => "5.4M".
     */
    public static function compact(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $abs = abs($value);

        return match (true) {
            $abs >= 1_000_000_000 => number_format($value / 1_000_000_000, 1, '.', '').'B',
            $abs >= 1_000_000 => number_format($value / 1_000_000, 1, '.', '').'M',
            $abs >= 1_000 => number_format($value / 1_000, 0, '.', '').'K',
            default => number_format($value, 0, '.', ''),
        };
    }

    /**
     * Parse user input such as "5.400.000", "Rp 5,400,000", "5400000" into a float.
     */
    public static function parse(string|float|int|null $input): float
    {
        if ($input === null || $input === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', (string) $input);

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        if ($hasDot && $hasComma) {
            // Whichever separator comes last is the decimal separator.
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);   // 1.234,56
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);   // 1,234.56
            }
        } elseif ($hasComma) {
            // "5,400,000" is thousands; "5400,50" is a decimal.
            $clean = substr_count($clean, ',') > 1
                ? str_replace(',', '', $clean)
                : str_replace(',', '.', $clean);
        } elseif ($hasDot) {
            if (substr_count($clean, '.') > 1) {
                $clean = str_replace('.', '', $clean);   // 5.400.000
            } else {
                // Single dot: "5400.50" is a decimal, "5.400" is thousands.
                [$whole, $fraction] = explode('.', $clean);
                if (strlen($fraction) === 3) {
                    $clean = $whole.$fraction;
                }
            }
        }

        return (float) $clean;
    }
}
