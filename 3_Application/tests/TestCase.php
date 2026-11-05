<?php

namespace Tests;

use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use ZipArchive;

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
     * des dates d'activités, de l'équipe, du synopsis et des pièces : coordonnées (I), titre et objectifs (II),
     * arrivée sur le terrain (aujourd'hui, donc avant toute date d'activités), pièce d'identité,
     * acceptation des engagements et signature par le nom complet du demandeur (V).
     */
    protected function champsFormulaire(?User $demandeur = null): array
    {
        return [
            'organisation' => 'Wild Lens Productions',
            'fonction' => 'Réalisatrice',
            'adresse' => 'Lot II M 85 Analakely, Antananarivo',
            'telephone' => '+261 34 12 345 67',
            'titre' => 'La faune de la forêt',
            'objectifs' => 'Réaliser un documentaire de 26 minutes.',
            'date_arrivee' => now()->toDateString(),
            'piece_identite' => '101 211 045 678',
            'engagement' => '1',
            'signature' => $demandeur?->name,
        ];
    }

    /**
     * Texte du corps d'un document Word téléchargé. Le fichier doit s'ouvrir et son XML être valide :
     * un texte saisi mal échappé (« & », « < ») rendrait le document illisible dans Word.
     */
    protected function texteWord(TestResponse $response): string
    {
        $fichier = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($fichier, $response->streamedContent());

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($fichier) === true, 'Le fichier Word ne s\'ouvre pas.');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($fichier);

        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml), 'Le contenu du fichier Word est invalide.');

        return $document->textContent;
    }
}
