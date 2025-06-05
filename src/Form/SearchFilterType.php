<?php

// src/Form/SearchFilterType.php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // Text field for free‐text query
            ->add('q', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Search...',
                    // Stimulus will listen to input on this field
                    'data-action' => 'input->search#update',
                    'data-search-target' => 'query',
                ],
            ])
            // First slider (e.g., minPrice)
            ->add('threshold', RangeType::class, [
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => 10,
                    'data-action' => 'input->search#update',
                    'data-search-target' => 'min',
                ],
            ])
            // Second slider (e.g., maxPrice)
            ->add('semanticRatio', RangeType::class, [
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'data-action' => 'input->search#update',
                    'data-search-target' => 'max',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        // no data class: we’ll just read from request->query
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
