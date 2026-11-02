<?php

namespace App\Service;

// accepts comma and dot decimals using the configured currency precision
class MoneyParser
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @return int|null amount in the configured minor unit, or null if invalid
     */
    public function parseToMinorUnits(mixed $input): ?int
    {
        if (null === $input || '' === $input) {
            return null;
        }

        if (!is_string($input)) {
            $input = (string) $input;
        }
        $clean = trim($input);
        $symbol = (string) $this->settings->getOrDefault('i18n.currency.symbol', '');
        if ('' !== $symbol) {
            $clean = str_replace($symbol, '', $clean);
        }
        $clean = preg_replace('/[\s\x{00a0}\x{202f}€$£¥]/u', '', $clean);
        if ('' === $clean || null === $clean) {
            return null;
        }
        $fractionDigits = $this->getFractionDigits();
        $fractionPart = null;
        $dotCount = substr_count($clean, '.');
        $commaCount = substr_count($clean, ',');

        if ($dotCount > 0 && $commaCount > 0) {
            // rightmost separator is the decimal mark, the other is grouping ("1.234,56")
            $decimalAt = max(strrpos($clean, '.'), strrpos($clean, ','));
            $fractionPart = substr($clean, $decimalAt + 1);
            $clean = preg_replace('/[.,]/', '', substr($clean, 0, $decimalAt))
                   .'.'.substr($clean, $decimalAt + 1);
        } else {
            $sep = $dotCount > 0 ? '.' : ($commaCount > 0 ? ',' : null);
            if (null !== $sep) {
                if ($dotCount + $commaCount > 1) {
                    // repeated separator can only be grouping ("1.234.567")
                    $clean = str_replace($sep, '', $clean);
                } else {
                    $digitsAfter = strlen($clean) - strrpos($clean, $sep) - 1;
                    if (3 === $digitsAfter) {
                        // "1.000" is ambiguous (1000 vs 1.0) — refuse rather than book an amount off by x1000
                        return null;
                    }
                    $fractionPart = substr($clean, strrpos($clean, $sep) + 1);
                    $clean = str_replace($sep, '.', $clean);
                }
            }
        }

        if (!is_numeric($clean) || (null !== $fractionPart && '' === $fractionPart)) {
            return null;
        }

        if (0 === $fractionDigits && null !== $fractionPart && '' !== trim($fractionPart, '0')) {
            return null;
        }

        if (0 === $fractionDigits && false !== strpbrk($clean, 'eE')) {
            return null;
        }

        return $this->majorToMinorUnits((float) $clean);
    }

    public function getFractionDigits(): int
    {
        $fractionDigits = $this->settings->getOrDefault('i18n.currency.fractionDigits', 2);
        if (!is_int($fractionDigits) || $fractionDigits < 0 || $fractionDigits > 6) {
            throw new \LogicException('i18n.currency.fractionDigits must be an integer between 0 and 6.');
        }

        return $fractionDigits;
    }

    public function getMinorUnitsPerMajorUnit(): int
    {
        return 10 ** $this->getFractionDigits();
    }

    public function majorToMinorUnits(float $amount): int
    {
        $scaled = $amount * $this->getMinorUnitsPerMajorUnit();
        if (0 === $this->getFractionDigits() && floor($scaled) !== $scaled) {
            throw new \InvalidArgumentException('Fractional amounts are not supported by this currency.');
        }

        return (int) round($scaled);
    }

    public function minorToMajorUnits(int $amount): float
    {
        return $amount / $this->getMinorUnitsPerMajorUnit();
    }
}
