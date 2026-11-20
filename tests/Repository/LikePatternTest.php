<?php

namespace App\Tests\Repository;

use App\Repository\LikePattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LikePatternTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function patterns(): iterable
    {
        yield 'texte simple' => ['clavier', '%clavier%'];
        yield 'pourcentage' => ['50%', '%50!%%'];
        yield 'souligné' => ['code_promo', '%code!_promo%'];
        yield 'caractère d\'échappement' => ['a!b', '%a!!b%'];
        yield 'antislash conservé' => ['a\\b', '%a\\b%'];
        yield 'combinaison' => ['!%_', '%!!!%!_%'];
    }

    #[DataProvider('patterns')]
    public function testContainsEscapesWildcards(string $search, string $expected): void
    {
        self::assertSame($expected, LikePattern::contains($search));
    }
}
