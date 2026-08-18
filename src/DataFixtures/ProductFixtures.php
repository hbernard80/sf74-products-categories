<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Charge un catalogue de produits de démonstration.
 */
class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Préfixe commun des références Doctrine créées pour les produits.
     */
    public const REFERENCE_PREFIX = 'product_';

    /**
     * Crée des produits rattachés à une à trois catégories feuilles.
     *
     * @param ObjectManager $manager Gestionnaire d'objets Doctrine.
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->seed(74001);

        /**
         * @var string[] $productNames
         */
        $productNames = [
            'Ordinateur portable',
            'Clavier mécanique',
            'Souris sans fil',
            'Smartphone',
            'Coque de protection',
            'Casque Bluetooth',
            'Enceinte connectée',
            'Webcam Full HD',
            'Fauteuil ergonomique',
            'Manette sans fil',
            'Lampe LED',
            'Chargeur USB-C',
            'Écran 27 pouces',
            'Support ordinateur',
            'Microphone USB',
            'Tablette graphique',
            'Routeur Wi-Fi',
            'Disque SSD externe',
            'Station d’accueil',
            'Tapis de souris XXL',
        ];

        for ($i = 0; $i < 30; $i++) {
            $createdAt = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months', '-1 week'));
            $updatedAt = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween($createdAt->format('Y-m-d H:i:s'), 'now'));
            $productCategoryKeys = $faker->randomElements(CategoryFixtures::LEAF_CATEGORY_KEYS, $faker->numberBetween(1, 3));

            $product = (new Product())
                ->setName($faker->randomElement($productNames).' '.$faker->bothify('##?'))
                ->setDescription($faker->paragraph(3))
                ->setPrice((string) $faker->randomFloat(2, 9, 999))
                ->setStock($faker->numberBetween(0, 150))
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            foreach ($productCategoryKeys as $categoryKey) {
                /** @var Category $category */
                $category = $this->getReference(CategoryFixtures::REFERENCE_PREFIX.$categoryKey, Category::class);
                $product->addCategory($category);
            }

            $manager->persist($product);
            $this->addReference(self::REFERENCE_PREFIX.$i, $product);
        }

        $manager->flush();
    }

    /**
     * Déclare la dépendance aux catégories afin que les références existent.
     *
     * @return array<class-string<Fixture>> Liste des fixtures à charger avant les produits.
     */
    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }
}
