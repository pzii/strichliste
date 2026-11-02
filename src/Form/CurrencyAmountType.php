<?php

namespace App\Form;

use App\Service\MoneyParser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class CurrencyAmountType extends AbstractType
{
    public function __construct(private readonly MoneyParser $moneyParser)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (0 !== $this->moneyParser->getFractionDigits()) {
            return;
        }

        $moneyParser = $this->moneyParser;
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($moneyParser): void {
            $value = $event->getData();
            if (null !== $value && '' !== $value && null === $moneyParser->parseToMinorUnits($value)) {
                $event->getForm()->addError(new FormError('money.integral_amount'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('scale', $this->moneyParser->getFractionDigits());
    }

    public function getParent(): string
    {
        return MoneyType::class;
    }
}
