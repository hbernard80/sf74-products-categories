<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductType extends AbstractType
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Nom du produit',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'maxlength' => 4000,
                    'placeholder' => 'Description du produit',
                    'rows' => 5,
                ],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'EUR',
                'html5' => true,
                'input' => 'string',
                'invalid_message' => 'Veuillez saisir un prix valide.',
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'max' => 99999.99,
                    'step' => '0.01',
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'invalid_message' => 'Veuillez saisir un nombre entier valide.',
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('createdAt', DateTimeType::class, [
                'label' => 'Créé le',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('updatedAt', DateTimeType::class, [
                'label' => 'Modifié le',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
        ;

        $subcategoriesFieldId = $builder->getName().'_categories';

        $addParentCategoryField = function ($form, ?Category $parentCategory = null) use ($subcategoriesFieldId): void {
            $form->add('parentCategory', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'choices' => $this->categoryRepository->findParentCategories(),
                'data' => $parentCategory,
                'label' => 'Catégorie parente',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Choisir une catégorie parente',
                'attr' => [
                    'data-controller' => 'product-categories',
                    'data-action' => 'change->product-categories#update',
                    'data-product-categories-url-value' => $this->urlGenerator->generate('app_category_subcategories'),
                    'data-product-categories-subcategories-field-value' => $subcategoriesFieldId,
                ],
            ]);
        };

        $formModifier = function ($form, ?Category $parentCategory): void {
            $form->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'choices' => $parentCategory ? $this->categoryRepository->findChildrenByParent($parentCategory) : [],
                'label' => 'Sous-catégories',
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'size' => 6,
                ],
            ]);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($addParentCategoryField, $formModifier): void {
            $product = $event->getData();
            $selectedCategory = $product instanceof Product ? $product->getCategories()->first() : null;
            $selectedCategory = $selectedCategory instanceof Category ? $selectedCategory : null;
            $parentCategory = $selectedCategory?->getParent() ?? $selectedCategory;

            $addParentCategoryField($event->getForm(), $parentCategory);
            $formModifier($event->getForm(), $parentCategory);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($formModifier): void {
            $data = $event->getData();
            $parentCategory = null;

            if (is_array($data) && !empty($data['parentCategory'])) {
                $parentCategory = $this->categoryRepository->find($data['parentCategory']);
            }

            $formModifier($event->getForm(), $parentCategory);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
