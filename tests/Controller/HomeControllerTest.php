<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class HomeControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="fr"]');
    }

    /**
     * La locale Symfony (`fr_CA`) est convertie au format BCP 47 attendu par l'attribut `lang` (`fr-CA`).
     */
    public function testHtmlLangUsesBcp47Format(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $request = Request::create('/');
        $request->setLocale('fr_CA');
        $container->get('request_stack')->push($request);

        $html = $container->get('twig')->render('base.html.twig');

        self::assertStringContainsString('<html lang="fr-CA">', $html);
    }

    /**
     * Les scripts de rechargement à chaud ne dépendent d'aucune version flottante d'un CDN.
     */
    public function testHotReloadScriptsArePinned(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $request = Request::create('/', server: ['FRANKENPHP_HOT_RELOAD' => '/.well-known/mercure?topic=hot-reload']);
        $container->get('request_stack')->push($request);

        $crawler = new Crawler($container->get('twig')->render('base.html.twig'));

        $idiomorph = $crawler->filter('script[src*="idiomorph"]');
        self::assertCount(1, $idiomorph);
        self::assertStringContainsString('idiomorph@0.7.4/', (string) $idiomorph->attr('src'));
        self::assertStringStartsWith('sha384-', (string) $idiomorph->attr('integrity'));
        self::assertSame('anonymous', $idiomorph->attr('crossorigin'));

        // Tout script externe doit avoir une empreinte SRI.
        $crawler->filter('script[src^="http"]')->each(
            static fn (Crawler $script) => self::assertNotNull($script->attr('integrity'), (string) $script->attr('src')),
        );

        // frankenphp-hot-reload est résolu par l'importmap et servi localement.
        self::assertStringContainsString("import 'frankenphp-hot-reload';", $crawler->html());
        $importmap = json_decode($crawler->filter('script[type="importmap"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertStringStartsWith('/assets/', $importmap['imports']['frankenphp-hot-reload']);
    }
}
