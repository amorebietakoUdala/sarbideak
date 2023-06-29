<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class PinType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('oldPin',TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(4),
                    new Regex('/^\d{4}$/')  
                ],
                'label' => 'label.oldPin',
            ])
            ->add('newPin',TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(4),
                    new Regex('/^\d{4}$/')  
                ],
                'label' => 'label.newPin',
            ])
            ->add('secret',HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('customerReference',HiddenType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
