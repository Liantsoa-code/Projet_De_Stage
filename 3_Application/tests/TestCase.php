<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Les tests vérifient toutes les pages, quel que soit le réglage de config/pages.php.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['pages' => array_map(fn () => true, config('pages'))]);
    }

    /**
     * Champs du formulaire de demande exigés à la soumission, en plus de l'aire protégée, du type,
     * des dates, de l'équipe, du synopsis et des pièces : coordonnées (I), titre et objectifs (II),
     * pièce d'identité et acceptation des engagements (V).
     */
    protected function champsFormulaire(): array
    {
        return [
            'organisation' => 'Wild Lens Productions',
            'fonction' => 'Réalisatrice',
            'adresse' => 'Lot II M 85 Analakely, Antananarivo',
            'telephone' => '+261 34 12 345 67',
            'titre' => 'La faune de la forêt',
            'objectifs' => 'Réaliser un documentaire de 26 minutes.',
            'piece_identite' => '101 211 045 678',
            'engagement' => '1',
        ];
    }
}
