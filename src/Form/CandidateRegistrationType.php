<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\CandidateRegistrationDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CandidateRegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'To\'liq ism-familiya',
                'attr' => [
                    'placeholder' => 'Masalan: O\'tkir Hoshimov',
                    'autocomplete' => 'name',
                    'class' => 'w-full px-3.5 py-3 bg-zinc-900/90 border border-zinc-700 rounded-sm text-zinc-100 font-sans text-sm focus:outline-none focus:border-zinc-400 transition-colors',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Telefon raqam (+998)',
                'attr' => [
                    'placeholder' => '+998 90 123 45 67',
                    'autocomplete' => 'tel',
                    'class' => 'w-full px-3.5 py-3 bg-zinc-900/90 border border-zinc-700 rounded-sm text-zinc-100 font-mono text-sm focus:outline-none focus:border-zinc-400 transition-colors',
                ],
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'Saralash jarayonida qatnashish va natijalarni qayta ishlashga roziman',
                'required' => true,
                'attr' => [
                    'class' => 'mt-1 w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-zinc-200 focus:ring-0',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CandidateRegistrationDto::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'candidate_registration',
        ]);
    }
}
