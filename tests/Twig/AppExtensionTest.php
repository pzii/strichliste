<?php

namespace App\Tests\Twig;

use App\Service\MoneyParser;
use App\Service\SettingsService;
use App\Twig\AppExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * AppExtension's filters are pure given a SettingsService; the service just wraps
 * an array, so no container is needed.
 */
class AppExtensionTest extends TestCase
{
    private function makeExtension(int $fractionDigits = 2): AppExtension
    {
        // mirror config/strichliste.yaml: en locale and configured currency precision
        $settings = new SettingsService([
            'i18n' => [
                'language' => 'en',
                'currency' => [
                    'symbol' => '€',
                    'fractionDigits' => $fractionDigits,
                ],
            ],
        ]);

        return new AppExtension($settings, new MoneyParser($settings));
    }

    public function testCurrencyFormatPositiveGetsLeadingPlus(): void
    {
        self::assertSame('+€25.77', $this->makeExtension()->currencyFormat(2577));
    }

    public function testCurrencyFormatNegativeGetsLeadingMinus(): void
    {
        self::assertSame('-€8.52', $this->makeExtension()->currencyFormat(-852));
    }

    public function testCurrencyFormatZeroHasNoSign(): void
    {
        self::assertSame('€0.00', $this->makeExtension()->currencyFormat(0));
    }

    public function testCurrencyFormatOmitsFractionDigitsWhenConfigured(): void
    {
        self::assertSame('+€10', $this->makeExtension(0)->currencyFormat(10));
    }

    public function testCurrencyFormatHonoursSixFractionDigits(): void
    {
        self::assertSame('+€1.234567', $this->makeExtension(6)->currencyFormat(1234567));
    }

    public function testCurrencyInputUsesConfiguredPrecision(): void
    {
        foreach ([0 => '50', 2 => '0.50', 6 => '0.000050'] as $fractionDigits => $expected) {
            $twig = new Environment(new ArrayLoader(['amount' => '{{ amount|currency_input }}']));
            $twig->addExtension($this->makeExtension($fractionDigits));

            self::assertSame($expected, $twig->render('amount', ['amount' => 50]));
        }
    }

    public function testCurrencyFormatUnsignedDropsSign(): void
    {
        self::assertSame('€25.77', $this->makeExtension()->currencyFormat(2577, null, false));
        self::assertSame('€8.52', $this->makeExtension()->currencyFormat(-852, null, false));
    }

    public function testCurrencyFormatNullIsEmptyString(): void
    {
        self::assertSame('', $this->makeExtension()->currencyFormat(null));
    }

    public function testCurrencyFormatHonoursExplicitSymbol(): void
    {
        self::assertSame('+$25.77', $this->makeExtension()->currencyFormat(2577, '$'));
    }

    /**
     * @return iterable<string, array{int|null, string}>
     */
    public static function balanceClassProvider(): iterable
    {
        yield 'positive' => [100, 'is-positive'];
        yield 'negative' => [-100, 'is-negative'];
        yield 'zero' => [0, 'is-zero'];
        yield 'null' => [null, 'is-zero'];
    }

    #[DataProvider('balanceClassProvider')]
    public function testBalanceClass(?int $cents, string $expected): void
    {
        self::assertSame($expected, $this->makeExtension()->balanceClass($cents));
    }
}
