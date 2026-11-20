<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CategoryControllerTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    /** @var EntityRepository<Category> */
    private EntityRepository $categoryRepository;

    private string $path = '/category';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->categoryRepository = $this->manager->getRepository(Category::class);

        // Les actions d'écriture sont réservées aux administrateurs.
        $this->removeAllUsers($this->manager);
        $this->client->loginUser($this->createUser($this->manager, 'admin@test.local', roles: [User::ROLE_ADMIN]));

        foreach ($this->categoryRepository->findAll() as $category) {
            $this->manager->remove($category);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->request('GET', $this->path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Catégories');
    }

    /**
     * Les caractères `%`, `_` et `!` saisis sont cherchés littéralement, pas comme jokers.
     */
    public function testSearchTreatsWildcardsLiterally(): void
    {
        foreach (['Soldes 100%', 'Soldes 1000', 'high_tech', 'highXtech', 'Promo!'] as $name) {
            $this->createCategory($name);
        }

        $expectations = [
            '100%' => ['Soldes 100%'],
            '_' => ['high_tech'],
            '!' => ['Promo!'],
            '0' => ['Soldes 100%', 'Soldes 1000'],
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
        self::assertSelectorNotExists('[name="category[createdAt]"]');
        self::assertSelectorNotExists('[name="category[updatedAt]"]');

        $this->client->submitForm('Enregistrer', [
            'category[name]' => 'Catégorie test',
            'category[description]' => 'Description test',
        ]);

        self::assertResponseRedirects($this->path);
        self::assertSame(1, $this->categoryRepository->count([]));

        $category = $this->categoryRepository->findOneBy(['name' => 'Catégorie test']);
        self::assertNotNull($category?->getCreatedAt());
        self::assertEquals($category->getCreatedAt(), $category->getUpdatedAt());
    }

    public function testShow(): void
    {
        $category = $this->createCategory('Catégorie visible');

        $this->client->request('GET', sprintf('%s/%d', $this->path, $category->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Catégorie : Catégorie visible');
    }

    public function testEdit(): void
    {
        $category = $this->createCategory('Catégorie initiale');

        $this->client->request('GET', sprintf('%s/%d/edit', $this->path, $category->getId()));
        self::assertSelectorNotExists('[name="category[createdAt]"]');
        self::assertSelectorNotExists('[name="category[updatedAt]"]');

        $this->client->submitForm('Enregistrer', [
            'category[name]' => 'Catégorie modifiée',
            'category[description]' => 'Description modifiée',
        ]);

        self::assertResponseRedirects($this->path);

        $this->manager->clear();
        $category = $this->categoryRepository->find($category->getId());
        self::assertSame('Catégorie modifiée', $category?->getName());
        self::assertEquals(new \DateTimeImmutable('2026-01-01 10:00:00'), $category->getCreatedAt());
        self::assertGreaterThan(new \DateTimeImmutable('2026-01-01 10:00:00'), $category->getUpdatedAt());
    }

    public function testRemove(): void
    {
        $category = $this->createCategory('Catégorie à supprimer');

        $this->client->request('GET', sprintf('%s/%d', $this->path, $category->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects($this->path);
        self::assertSame(0, $this->categoryRepository->count([]));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'La catégorie a bien été supprimée.');
    }

    public function testRemoveWithInvalidCsrfTokenKeepsCategoryAndWarns(): void
    {
        $category = $this->createCategory('Catégorie protégée');

        $this->client->request('POST', sprintf('%s/%d', $this->path, $category->getId()), ['_token' => 'jeton-invalide']);

        self::assertResponseRedirects(sprintf('%s/%d', $this->path, $category->getId()));
        self::assertSame(1, $this->categoryRepository->count([]));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'La catégorie n\'a pas été supprimée');
    }

    public function testEditParentChoicesExcludeCategoryAndDescendants(): void
    {
        $root = $this->createCategory('Racine');
        $child = $this->createCategory('Enfant', $root);
        $this->createCategory('Petit-enfant', $child);
        $this->createCategory('Autre racine');

        $crawler = $this->client->request('GET', sprintf('%s/%d/edit', $this->path, $root->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Aucune catégorie parente', 'Autre racine'],
            $crawler->filter('#category_parent option')->each(static fn ($option): string => trim($option->text())),
        );
    }

    public function testEditRejectsDescendantAsParent(): void
    {
        $root = $this->createCategory('Racine');
        $child = $this->createCategory('Enfant', $root);
        $grandChild = $this->createCategory('Petit-enfant', $child);

        $crawler = $this->client->request('GET', sprintf('%s/%d/edit', $this->path, $root->getId()));
        $form = $crawler->selectButton('Enregistrer')->form();
        $form->disableValidation();
        $form['category[parent]'] = (string) $grandChild->getId();
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        $this->manager->clear();
        self::assertNull($this->categoryRepository->find($root->getId())?->getParent());
    }

    public function testValidatorRejectsHierarchyLoop(): void
    {
        $root = (new Category())->setName('Racine');
        $child = (new Category())->setName('Enfant')->setParent($root);
        $root->setParent($child);

        $violations = static::getContainer()->get('validator')->validate($root);

        self::assertCount(1, $violations);
        self::assertSame('parent', $violations[0]->getPropertyPath());
    }

    private function createCategory(string $name, ?Category $parent = null): Category
    {
        $category = (new Category())
            ->setName($name)
            ->setParent($parent)
            ->setDescription('Description test')
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'))
        ;

        $this->manager->persist($category);
        $this->manager->flush();

        return $category;
    }
}
