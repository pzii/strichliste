<?php

namespace App\Tests\Service;

use App\Service\MoneyParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure value-logic test: MoneyParser needs no container or DB.
 */
class MoneyParserTest extends TestCase
{
    private MoneyParser $parser;

    protected function setUp(): void
    {
        $this->parser = $this->makeParser();
    }

    private function makeParser(int $fractionDigits = 2): MoneyParser
    {
        return new MoneyParser(new \App\Service\SettingsService([
            'i18n' => [
                'currency' => [
                    'fractionDigits' => $fractionDigits,
                    'symbol' => '€',
                ],
            ],
        ]));
    }

    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function parseProvider(): iterable
    {
        // comma and dot decimals both mean the same money
        yield 'comma decimal' => ['5,99', 599];
        yield 'dot decimal' => ['5.99', 599];

        // single dot + exactly three trailing digits is ambiguous (1000 vs 1.0) — refused by design
        yield 'ambiguous 1.000' => ['1.000', null];
        yield 'ambiguous 1,000' => ['1,000', null];

        // mixed separators: rightmost is the decimal mark, the other is grouping
        yield 'grouped with comma decimal' => ['1.234,56', 123456];
        yield 'grouped with dot decimal' => ['1,234.56', 123456];

        // repeated separator can only be grouping
        yield 'repeated dot grouping' => ['1.234.567', 123456700];
        yield 'repeated comma grouping' => ['1,234,567', 123456700];

        // currency symbols and whitespace are stripped before parsing
        yield 'euro prefixed' => ['€ 2,50', 250];
        yield 'dollar prefixed' => ['$3.00', 300];

        // 1.50 must round to 150 cents, not 149 (float artifact guard)
        yield '1.50 not 149' => ['1.50', 150];
        yield '1,50 not 149' => ['1,50', 150];

        // empty / null / garbage all fail
        yield 'empty string' => ['', null];
        yield 'null' => [null, null];
        yield 'garbage' => ['abc', null];
        yield 'only symbol' => ['€', null];

        // narrow no-break space (U+202F) and regular no-break space (U+00A0) as thousands sep get stripped
        yield 'narrow nbsp grouping' => ["1\u{202f}234,56", 123456];
        yield 'regular nbsp grouping' => ["1\u{00a0}234,56", 123456];

        // numeric (non-string) input is coerced
        yield 'integer input' => [5, 500];
        yield 'float input' => [5.99, 599];

        // plain integer string, no separators
        yield 'plain integer' => ['7', 700];

        // four trailing digits after a single separator: it's a decimal mark
        yield 'four trailing decimals' => ['1.2345', 123];
    }

    #[DataProvider('parseProvider')]
    public function testParseToMinorUnits(mixed $input, ?int $expected): void
    {
        self::assertSame($expected, $this->parser->parseToMinorUnits($input));
    }

    public function testZeroFractionDigitsRequireWholeAmounts(): void
    {
        $parser = $this->makeParser(0);

        self::assertSame(10, $parser->parseToMinorUnits('10'));
        self::assertSame(-10, $parser->parseToMinorUnits('-10'));
        self::assertSame(10, $parser->parseToMinorUnits('€ 10'));
        self::assertNull($parser->parseToMinorUnits('10.5'));
        self::assertNull($parser->parseToMinorUnits('10,5'));
        self::assertSame(10, $parser->parseToMinorUnits('10.0'));
        self::assertNull($parser->parseToMinorUnits('1e-1'));
        self::assertSame(10.0, $parser->minorToMajorUnits(10));
    }

    public function testSixFractionDigitsAndRoundsExcessPrecision(): void
    {
        $parser = $this->makeParser(6);

        self::assertSame(1234567, $parser->parseToMinorUnits('1.234567'));
        self::assertSame(1234568, $parser->parseToMinorUnits('1.2345678'));
        self::assertSame(1, $parser->parseToMinorUnits('0.000001'));
    }

    public function testDefaultPrecisionPreservesFloatRoundingBehavior(): void
    {
        // 1.50 * 100 is 149.99999... in IEEE float; round() rescues it
        self::assertSame(150, $this->parser->parseToMinorUnits(1.50));
        self::assertSame(599, $this->parser->parseToMinorUnits(5.99));
        self::assertSame(0, $this->parser->parseToMinorUnits(0.0));
        self::assertSame(-250, $this->parser->parseToMinorUnits(-2.5));
    }
}
