<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductControllerTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    /** @var EntityRepository<Product> */
    private EntityRepository $productRepository;

    private string $path = '/product';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->productRepository = $this->manager->getRepository(Product::class);

        // Les actions d'écriture sont réservées aux administrateurs.
        $this->removeAllUsers($this->manager);
        $this->client->loginUser($this->createUser($this->manager, 'admin@test.local', roles: [User::ROLE_ADMIN]));

        foreach ($this->productRepository->findAll() as $product) {
            $this->manager->remove($product);
        }

        $this->manager->flush();

        // Les sous-catégories d'abord, pour respecter la clé étrangère parent_id.
        foreach ($this->manager->getRepository(Category::class)->findAll() as $category) {
            $category->setParent(null);
        }
        $this->manager->flush();

        foreach ($this->manager->getRepository(Category::class)->findAll() as $category) {
            $this->manager->remove($category);
        }
        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->request('GET', $this->path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Produits');
    }

    /**
     * Les caractères `%`, `_` et `!` saisis sont cherchés littéralement, pas comme jokers.
     */
    public function testSearchTreatsWildcardsLiterally(): void
    {
        foreach (['Remise 50%', 'Remise 500 euros', 'code_promo', 'codeXpromo', 'Prix!', 'Prix 0'] as $name) {
            $this->createProduct($name);
        }

        $expectations = [
            '50%' => ['Remise 50%'],
            '_' => ['code_promo'],
            '%' => ['Remise 50%'],
            '!' => ['Prix!'],
            '0' => ['Prix 0', 'Remise 50%', 'Remise 500 euros'],
        ];

        foreach ($expectations as $search => $expected) {
            $crawler = $this->client->request('GET', $this->path, ['q' => (string) $search]);
            self::assertResponseIsSuccessful();

            $names = $crawler->filter('tbody tr td:nth-child(2)')->each(static fn ($cell): string => trim($cell->text()));
            sort($names);

            self::assertSame($expected, $names, sprintf('Recherche « %s »', $search));
        }
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%s/new', $this->path));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="product[createdAt]"]');
        self::assertSelectorNotExists('[name="product[updatedAt]"]');

        $this->client->submitForm('Enregistrer', [
            'product[name]' => 'Produit test',
            'product[description]' => 'Description test',
            'product[price]' => '12.50',
            'product[stock]' => '4',
        ]);

        self::assertResponseRedirects($this->path);
        self::assertSame(1, $this->productRepository->count([]));

        $product = $this->productRepository->findOneBy(['name' => 'Produit test']);
        self::assertNotNull($product?->getCreatedAt());
        self::assertEquals($product->getCreatedAt(), $product->getUpdatedAt());
    }

    public function testShow(): void
    {
        $product = $this->createProduct('Produit visible');

        $this->client->request('GET', sprintf('%s/%d', $this->path, $product->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Produit : Produit visible');
    }

    public function testEdit(): void
    {
        $product = $this->createProduct('Produit initial');

        $this->client->request('GET', sprintf('%s/%d/edit', $this->path, $product->getId()));
        self::assertSelectorNotExists('[name="product[createdAt]"]');
        self::assertSelectorNotExists('[name="product[updatedAt]"]');

        $this->client->submitForm('Enregistrer', [
            'product[name]' => 'Produit modifié',
            'product[description]' => 'Description modifiée',
            'product[price]' => '15.99',
            'product[stock]' => '8',
        ]);

        self::assertResponseRedirects($this->path);

        $this->manager->clear();
        $product = $this->productRepository->find($product->getId());
        self::assertSame('Produit modifié', $product?->getName());
        self::assertEquals(new \DateTimeImmutable('2026-01-01 10:00:00'), $product->getCreatedAt());
        self::assertGreaterThan(new \DateTimeImmutable('2026-01-01 10:00:00'), $product->getUpdatedAt());
    }

    public function testEditKeepsCategoriesFromSeveralParents(): void
    {
        $computing = $this->createCategory('Informatique');
        $laptops = $this->createCategory('Ordinateurs', $computing);
        $audio = $this->createCategory('Audio');
        $headphones = $this->createCategory('Casques', $audio);

        $product = $this->createProduct('Produit multi-catégories');
        $product->addCategory($laptops)->addCategory($headphones);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s/%d/edit', $this->path, $product->getId()));
        $this->client->submitForm('Enregistrer', [
            'product[name]' => 'Produit multi-catégories modifié',
        ]);

        self::assertResponseRedirects($this->path);
        self::assertSame(['Casques', 'Ordinateurs'], $this->findCategoryNames($product->getId()));
    }

    public function testEditKeepsRootCategory(): void
    {
        $computing = $this->createCategory('Informatique');
        $this->createCategory('Ordinateurs', $computing);

        $product = $this->createProduct('Produit en catégorie racine');
        $product->addCategory($computing);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s/%d/edit', $this->path, $product->getId()));
        $this->client->submitForm('Enregistrer', [
            'product[name]' => 'Produit en catégorie racine modifié',
        ]);

        self::assertResponseRedirects($this->path);
        self::assertSame(['Informatique'], $this->findCategoryNames($product->getId()));
    }

    public function testRemove(): void
    {
        $product = $this->createProduct('Produit à supprimer');

        $this->client->request('GET', sprintf('%s/%d', $this->path, $product->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects($this->path);
        self::assertSame(0, $this->productRepository->count([]));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Le produit a bien été supprimé.');
    }

    public function testRemoveWithInvalidCsrfTokenKeepsProductAndWarns(): void
    {
        $product = $this->createProduct('Produit protégé');

        $this->client->request('POST', sprintf('%s/%d', $this->path, $product->getId()), ['_token' => 'jeton-invalide']);

        self::assertResponseRedirects(sprintf('%s/%d', $this->path, $product->getId()));
        self::assertSame(1, $this->productRepository->count([]));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Le produit n\'a pas été supprimé');
    }

    private function createCategory(string $name, ?Category $parent = null): Category
    {
        $category = (new Category())
            ->setName($name)
            ->setParent($parent)
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'))
        ;

        $this->manager->persist($category);
        $this->manager->flush();

        return $category;
    }

    /**
     * Recharge le produit depuis la base et retourne les noms triés de ses catégories.
     *
     * @return list<string>
     */
    private function findCategoryNames(?int $productId): array
    {
        $this->manager->clear();
        $product = $this->productRepository->find($productId);
        self::assertNotNull($product);

        $names = array_map(
            static fn (Category $category): string => (string) $category->getName(),
            array_values($product->getCategories()->toArray()),
        );
        sort($names);

        return $names;
    }

    private function createProduct(string $name): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setDescription('Description test')
            ->setPrice('12.50')
            ->setStock(4)
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'))
        ;

        $this->manager->persist($product);
        $this->manager->flush();

        return $product;
    }
}
