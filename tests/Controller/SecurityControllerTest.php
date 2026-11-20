<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private Product $product;
    private Category $category;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();

        foreach ($this->manager->getRepository(Product::class)->findAll() as $product) {
            $this->manager->remove($product);
        }
        $this->manager->flush();

        foreach ($this->manager->getRepository(Category::class)->findAll() as $category) {
            $category->setParent(null);
        }
        $this->manager->flush();

        foreach ($this->manager->getRepository(Category::class)->findAll() as $category) {
            $this->manager->remove($category);
        }
        $this->removeAllUsers($this->manager);

        $this->category = (new Category())->setName('Catégorie publique');
        $this->product = (new Product())->setName('Produit public')->setPrice('10.00')->setStock(1);
        $this->manager->persist($this->category);
        $this->manager->persist($this->product);
        $this->manager->flush();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function writeRoutes(): iterable
    {
        yield 'nouveau produit' => ['GET', '/product/new'];
        yield 'modifier produit' => ['GET', '/product/{product}/edit'];
        yield 'supprimer produit' => ['POST', '/product/{product}'];
        yield 'nouvelle catégorie' => ['GET', '/category/new'];
        yield 'modifier catégorie' => ['GET', '/category/{category}/edit'];
        yield 'supprimer catégorie' => ['POST', '/category/{category}'];
    }

    #[DataProvider('writeRoutes')]
    public function testAnonymousIsRedirectedToLogin(string $method, string $path): void
    {
        $this->client->request($method, $this->resolvePath($path));

        self::assertResponseRedirects('/login');
    }

    #[DataProvider('writeRoutes')]
    public function testNonAdminIsForbidden(string $method, string $path): void
    {
        $this->client->loginUser($this->createUser($this->manager, 'user@test.local'));
        $this->client->request($method, $this->resolvePath($path));

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonNumericIdRoutes(): iterable
    {
        yield 'voir produit' => ['GET', '/product/abc'];
        yield 'modifier produit' => ['GET', '/product/abc/edit'];
        yield 'supprimer produit' => ['POST', '/product/abc'];
        yield 'voir catégorie' => ['GET', '/category/abc'];
        yield 'modifier catégorie' => ['GET', '/category/1abc/edit'];
        yield 'supprimer catégorie' => ['POST', '/category/-1'];
    }

    /**
     * Un identifiant non numérique ne correspond à aucune route : 404 directe,
     * sans passer par le pare-feu (redirection vers /login) ni par Doctrine.
     */
    #[DataProvider('nonNumericIdRoutes')]
    public function testNonNumericIdIsNotFound(string $method, string $path): void
    {
        $this->client->request($method, $path);

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnonymousCanReadButSeesNoWriteButtons(): void
    {
        foreach (['/', '/product', '/category', '/product/'.$this->product->getId(), '/category/'.$this->category->getId()] as $path) {
            $crawler = $this->client->request('GET', $path);

            self::assertResponseIsSuccessful($path);
            self::assertCount(0, $crawler->filter('a[href$="/new"], a[href$="/edit"], form[action*="/product/"][method="post"], form[action*="/category/"][method="post"]'), $path);
            self::assertSelectorExists('a[href="/login"]');
        }
    }

    public function testAdminCanLogInWithFormAndSeesWriteButtons(): void
    {
        $this->createUser($this->manager, 'admin@test.local', 'admin-password-123', [User::ROLE_ADMIN]);

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            '_username' => 'admin@test.local',
            '_password' => 'admin-password-123',
        ]);
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/product');
        self::assertSelectorExists('a[href="/product/new"]');
        self::assertSelectorExists('form[action="/logout"][method="post"]');
        self::assertSelectorTextContains('header', 'admin@test.local');
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->createUser($this->manager, 'admin@test.local', 'admin-password-123', [User::ROLE_ADMIN]);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'admin@test.local',
            '_password' => 'mauvais-mot-de-passe',
        ]);
        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        self::assertSelectorNotExists('form[action="/logout"]');
    }

    public function testUserCanLogOutWithForm(): void
    {
        $this->client->loginUser($this->createUser($this->manager, 'user@test.local'));

        $this->client->request('GET', '/');
        $this->client->submitForm('Déconnexion');
        self::assertResponseRedirects('/');

        $this->client->followRedirect();
        self::assertSelectorExists('a[href="/login"]');
        self::assertSelectorNotExists('form[action="/logout"]');
    }

    /**
     * Une simple requête GET (lien, image piégée sur un autre site) ne doit pas déconnecter.
     */
    public function testLogoutWithGetIsRejected(): void
    {
        $this->client->loginUser($this->createUser($this->manager, 'user@test.local'));

        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(405);

        $this->client->request('GET', '/');
        self::assertSelectorExists('form[action="/logout"]');
    }

    /**
     * Un POST sans jeton CSRF valide (formulaire forgé sur un autre site) ne doit pas déconnecter.
     */
    public function testLogoutWithoutCsrfTokenIsRejected(): void
    {
        $this->client->loginUser($this->createUser($this->manager, 'user@test.local'));

        $this->client->request('POST', '/logout', ['_csrf_token' => 'invalide'], [], ['HTTP_ORIGIN' => 'https://evil.example']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/');
        self::assertSelectorExists('form[action="/logout"]');
    }

    private function resolvePath(string $path): string
    {
        return strtr($path, [
            '{product}' => (string) $this->product->getId(),
            '{category}' => (string) $this->category->getId(),
        ]);
    }
}
