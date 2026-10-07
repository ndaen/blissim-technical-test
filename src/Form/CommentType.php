<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CommentType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('author', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(max: 100, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Commentaire',
                'attr' => ['rows' => 4],
                'constraints' => [
                    new NotBlank(message: 'Le commentaire est obligatoire.'),
                    new Length(max: 1000, maxMessage: 'Le commentaire ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ]);
    }
}
