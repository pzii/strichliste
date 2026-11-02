<?php

namespace App\Tests\Form;

use App\Form\CurrencyAmountType;
use App\Service\MoneyParser;
use App\Service\SettingsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

class CurrencyAmountTypeTest extends TestCase
{
    public function testZeroPrecisionAcceptsWholeAmounts(): void
    {
        $parser = $this->makeParser(0);
        $form = $this->makeForm($parser);
        $form->submit('10');

        self::assertCount(0, $form->getErrors());
        self::assertSame(10, $parser->parseToMinorUnits($form->getData()));
    }

    public function testZeroPrecisionRejectsFractionalAmountsBeforeRounding(): void
    {
        $form = $this->makeForm($this->makeParser(0));
        $form->submit('10.5');

        self::assertGreaterThan(0, count($form->getErrors()));
    }

    public function testTwoFractionDigitsStillAcceptDecimalAmounts(): void
    {
        $parser = $this->makeParser(2);
        $form = $this->makeForm($parser);
        $form->submit('10.5');

        self::assertCount(0, $form->getErrors());
        self::assertSame(1050, $parser->parseToMinorUnits($form->getData()));
    }

    public function testSixFractionDigitsSetTheFormScale(): void
    {
        $parser = $this->makeParser(6);
        $form = $this->makeForm($parser);
        $form->submit('10.123456');

        self::assertCount(0, $form->getErrors());
        self::assertSame(10123456, $parser->parseToMinorUnits($form->getData()));
    }

    private function makeParser(int $fractionDigits): MoneyParser
    {
        return new MoneyParser(new SettingsService([
            'i18n' => ['currency' => ['symbol' => '€', 'fractionDigits' => $fractionDigits]],
        ]));
    }

    /**
     * @return FormInterface<mixed>
     */
    private function makeForm(MoneyParser $parser): FormInterface
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addType(new CurrencyAmountType($parser))
            ->getFormFactory();

        return $factory->create(CurrencyAmountType::class, null, ['currency' => false]);
    }
}
