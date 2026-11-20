<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Formulaire de création et de modification d'un produit.
 */
class ProductType extends AbstractType
{
    /**
     * @param CategoryRepository $categoryRepository Repository utilisé pour charger les catégories.
     * @param UrlGeneratorInterface $urlGenerator Générateur d'URL Symfony pour l'appel AJAX.
     */
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    /**
     * Construit le formulaire produit et adapte les sous-catégories à la catégorie parente choisie.
     *
     * @param FormBuilderInterface $builder Constructeur du formulaire Symfony.
     * @param array<string, mixed> $options
     *
     * @return void
     */
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
        ;

        $subcategoriesFieldId = $builder->getName().'_categories';

        /**
         * @param \Symfony\Component\Form\FormInterface $form Formulaire à compléter.
         * @param Category|null $parentCategory Catégorie parente sélectionnée.
         *
         * @return void
         */
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

        /**
         * Les choix proposés sont les sous-catégories du parent sélectionné, auxquelles on ajoute
         * les catégories déjà retenues : sinon, celles rattachées à un autre parent (ou une
         * catégorie racine) disparaîtraient de la liste et seraient retirées à l'enregistrement.
         *
         * @param \Symfony\Component\Form\FormInterface $form Formulaire à compléter.
         * @param Category|null $parentCategory Catégorie parente sélectionnée.
         * @param iterable<Category> $selectedCategories Catégories déjà associées ou soumises.
         *
         * @return void
         */
        $formModifier = function ($form, ?Category $parentCategory, iterable $selectedCategories = []): void {
            $choices = [];

            foreach ($selectedCategories as $category) {
                $choices[$category->getId()] = $category;
            }

            if ($parentCategory) {
                foreach ($this->categoryRepository->findChildrenByParent($parentCategory) as $category) {
                    $choices[$category->getId()] = $category;
                }
            }

            $choices = array_values($choices);
            usort($choices, static fn (Category $a, Category $b): int => strcmp(self::categoryLabel($a), self::categoryLabel($b)));

            $form->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => static fn (Category $category): string => self::categoryLabel($category),
                'choices' => $choices,
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
            $formModifier(
                $event->getForm(),
                $parentCategory,
                $product instanceof Product ? $product->getCategories() : [],
            );
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($formModifier): void {
            $data = $event->getData();
            $parentCategory = null;
            $selectedCategories = [];

            if (is_array($data) && !empty($data['parentCategory'])) {
                $parentCategory = $this->categoryRepository->find($data['parentCategory']);
            }

            if (is_array($data) && !empty($data['categories']) && is_array($data['categories'])) {
                $selectedCategories = $this->categoryRepository->findBy(['id' => array_values($data['categories'])]);
            }

            $formModifier($event->getForm(), $parentCategory, $selectedCategories);
        });
    }

    /**
     * Libellé d'une catégorie précédé de son parent, pour distinguer les catégories de branches différentes.
     *
     * @param Category $category Catégorie à afficher.
     *
     * @return string Libellé du type « Parent › Enfant ».
     */
    private static function categoryLabel(Category $category): string
    {
        $parent = $category->getParent();

        return $parent ? $parent->getName().' › '.$category->getName() : (string) $category->getName();
    }

    /**
     * Associe ce formulaire à l'entité Product.
     *
     * @param OptionsResolver $resolver Résolveur des options du formulaire.
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
