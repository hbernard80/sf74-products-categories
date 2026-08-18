<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de création et de modification d'une catégorie.
 */
class CategoryType extends AbstractType
{
    /**
     * Construit les champs de catégorie et exclut la catégorie courante de ses parents possibles.
     *
     * @param FormBuilderInterface $builder Constructeur du formulaire Symfony.
     * @param array<string, mixed> $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Category|null $category */
        $category = $options['data'] ?? null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Nom de la catégorie',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'maxlength' => 4000,
                    'placeholder' => 'Description de la catégorie',
                    'rows' => 5,
                ],
            ])
            ->add('parent', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie parente',
                'placeholder' => 'Aucune catégorie parente',
                'required' => false,
                'query_builder' => static function (CategoryRepository $repository) use ($category) {
                    $queryBuilder = $repository
                        ->createQueryBuilder('category')
                        ->orderBy('category.name', 'ASC');

                    if ($category?->getId()) {
                        $queryBuilder
                            ->andWhere('category.id != :currentCategoryId')
                            ->setParameter('currentCategoryId', $category->getId());
                    }

                    return $queryBuilder;
                },
            ])
            ->add('products', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'label' => 'Produits associés',
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'size' => 6,
                ],
            ])
            ->add('createdAt', DateTimeType::class, [
                'label' => 'Créée le',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('updatedAt', DateTimeType::class, [
                'label' => 'Modifiée le',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
        ;
    }

    /**
     * Associe ce formulaire à l'entité Category.
     *
     * @param OptionsResolver $resolver Résolveur des options du formulaire.
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
