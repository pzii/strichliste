<?php

namespace App\Twig;

use App\Service\MoneyParser;
use App\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    /** @var array<string, \NumberFormatter> */
    private array $formatters = [];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly MoneyParser $moneyParser,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('currency_format', $this->currencyFormat(...)),
            new TwigFilter('currency_input', $this->currencyInput(...)),
            new TwigFilter('balance_class', $this->balanceClass(...)),
        ];
    }

    // paths the setting() Twig function may read; secrets like paypal.recipient stay out
    private const array TEMPLATE_SETTING_ALLOWLIST = [
        'i18n.',
        'common.',
        'payment.splitInvoice.enabled',
        'paypal.sandbox',
        'paypal.fee',
    ];

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', function (string $path, mixed $default = null) {
                $allowed = array_any(self::TEMPLATE_SETTING_ALLOWLIST, fn ($prefix) => $path === $prefix || str_starts_with($path, $prefix));
                if (!$allowed) {
                    // silently fall back rather than leak the value or throw
                    return $default;
                }

                return $this->settings->getOrDefault($path, $default);
            }),
        ];
    }

    // Signed (default) prefixes positive amounts; unsigned is for plain prices.
    public function currencyFormat(?int $amount, ?string $currencySymbol = null, bool $signed = true): string
    {
        if (null === $amount) {
            return '';
        }
        $symbol = $currencySymbol ?: $this->settings->getOrDefault('i18n.currency.symbol', '€');
        $locale = (string) $this->settings->getOrDefault('i18n.language', 'en');
        $fractionDigits = $this->moneyParser->getFractionDigits();

        $sign = '';
        if ($signed) {
            $sign = $amount > 0 ? '+' : ($amount < 0 ? '-' : '');
        }
        $abs = $this->moneyParser->minorToMajorUnits(abs($amount));

        if (!isset($this->formatters[$locale])) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $fractionDigits);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $fractionDigits);
            $this->formatters[$locale] = $formatter;
        }
        $number = $this->formatters[$locale]->format($abs);
        if (false === $number) {
            $number = number_format($abs, $fractionDigits, '.', ',');
        }

        return $sign.$symbol.$number;
    }

    public function currencyInput(int $minorUnits): string
    {
        $fractionDigits = $this->moneyParser->getFractionDigits();

        return number_format($this->moneyParser->minorToMajorUnits($minorUnits), $fractionDigits, '.', '');
    }

    public function balanceClass(?int $amount): string
    {
        if (null === $amount || 0 === $amount) {
            return 'is-zero';
        }

        return $amount > 0 ? 'is-positive' : 'is-negative';
    }
}
