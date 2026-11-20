<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Charge l'arborescence de catégories de démonstration.
 */
class CategoryFixtures extends Fixture
{
    /**
     * Préfixe commun des références Doctrine créées pour les catégories.
     */
    public const REFERENCE_PREFIX = 'category_';

    /**
     * Clés des catégories feuilles utilisées par les fixtures de produits.
     *
     * @var string[]
     */
    public const LEAF_CATEGORY_KEYS = [
        'ordinateurs',
        'peripheriques',
        'stockage',
        'smartphones',
        'accessoires_mobile',
        'chargeurs',
        'casques',
        'enceintes',
        'microphones',
        'eclairage',
        'mobilier',
        'accessoires_gaming',
        'streaming',
        'teletravail',
        'ergonomie',
    ];

    /**
     * Crée les catégories parentes et leurs sous-catégories.
     *
     * @param ObjectManager $manager Gestionnaire d'objets Doctrine.
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->seed(74000);

        /**
         * @var array<string, array{
         *     name: string,
         *     children: array<string, string>
         * }> $categoriesTree
         */
        $categoriesTree = [
            'informatique' => [
                'name' => 'Informatique',
                'children' => [
                    'ordinateurs' => 'Ordinateurs',
                    'peripheriques' => 'Périphériques',
                    'stockage' => 'Stockage',
                ],
            ],
            'telephonie' => [
                'name' => 'Téléphonie',
                'children' => [
                    'smartphones' => 'Smartphones',
                    'accessoires_mobile' => 'Accessoires mobile',
                    'chargeurs' => 'Chargeurs',
                ],
            ],
            'audio' => [
                'name' => 'Audio',
                'children' => [
                    'casques' => 'Casques',
                    'enceintes' => 'Enceintes',
                    'microphones' => 'Microphones',
                ],
            ],
            'maison' => [
                'name' => 'Maison',
                'children' => [
                    'eclairage' => 'Éclairage',
                    'mobilier' => 'Mobilier',
                ],
            ],
            'gaming' => [
                'name' => 'Gaming',
                'children' => [
                    'accessoires_gaming' => 'Accessoires gaming',
                    'streaming' => 'Streaming',
                ],
            ],
            'bureau' => [
                'name' => 'Bureau',
                'children' => [
                    'teletravail' => 'Télétravail',
                    'ergonomie' => 'Ergonomie',
                ],
            ],
        ];

        foreach ($categoriesTree as $key => $data) {
            $category = (new Category())
                ->setName($data['name'])
                ->setDescription($faker->sentence(10))
                ->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 year', '-1 month')));

            $manager->persist($category);
            $this->addReference(self::REFERENCE_PREFIX.$key, $category);

            foreach ($data['children'] as $childKey => $childName) {
                $childCategory = (new Category())
                    ->setName($childName)
                    ->setDescription($faker->sentence(10))
                    ->setCreatedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 year', '-1 month')))
                    ->setParent($category);

                $manager->persist($childCategory);
                $this->addReference(self::REFERENCE_PREFIX.$childKey, $childCategory);
            }
        }

        $manager->flush();
    }
}
