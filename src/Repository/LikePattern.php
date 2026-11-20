<?php

namespace App\Repository;

/**
 * Construit des motifs LIKE à partir d'une saisie utilisateur.
 *
 * Les caractères `%` et `_` saisis sont recherchés littéralement au lieu d'agir
 * comme jokers. Le caractère d'échappement `!` est utilisé plutôt que `\`, dont
 * le traitement diffère selon les plateformes (MySQL l'interprète déjà dans les
 * chaînes, SQLite non) : la requête DQL doit donc préciser `ESCAPE '!'`.
 *
 * Exemple : `->andWhere("LOWER(p.name) LIKE LOWER(:search) ESCAPE '!'")`
 *           `->setParameter('search', LikePattern::contains($search))`
 */
final class LikePattern
{
    /** Caractère d'échappement à déclarer dans la clause `ESCAPE` de la requête. */
    public const ESCAPE_CHAR = '!';

    private function __construct()
    {
    }

    /**
     * Retourne un motif « contient » où la saisie est prise au pied de la lettre.
     *
     * @param string $search Texte saisi par l'utilisateur.
     *
     * @return string Motif de la forme `%texte échappé%`.
     */
    public static function contains(string $search): string
    {
        return '%'.self::escape($search).'%';
    }

    /**
     * Échappe les jokers LIKE (`%`, `_`) et le caractère d'échappement lui-même.
     *
     * @param string $value Texte à échapper.
     *
     * @return string Texte utilisable littéralement dans un motif LIKE.
     */
    public static function escape(string $value): string
    {
        // strtr remplace en une seule passe : un `!` ajouté n'est pas ré-échappé.
        return strtr($value, [
            self::ESCAPE_CHAR => self::ESCAPE_CHAR.self::ESCAPE_CHAR,
            '%' => self::ESCAPE_CHAR.'%',
            '_' => self::ESCAPE_CHAR.'_',
        ]);
    }
}
