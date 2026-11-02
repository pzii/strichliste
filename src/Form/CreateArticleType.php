<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class CreateArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'articles.fields.name',
                'attr' => ['maxlength' => 255],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 1, max: 255)],
            ])
            // The form type applies the configured currency precision.
            ->add('amount', CurrencyAmountType::class, [
                'label' => 'articles.fields.amount',
                'currency' => false,
                'constraints' => [new Assert\NotBlank(), new Assert\Positive(), new Assert\LessThanOrEqual(1000000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
