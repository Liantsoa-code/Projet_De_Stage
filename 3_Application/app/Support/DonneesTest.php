<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\Document;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Jeu de données de test (database/donnees_test.sql), chargé par la commande « php artisan donnees:test » :
 * le script SQL remplace toutes les données, puis les pièces jointes absentes du disque reçoivent un PDF
 * de démonstration et l'analyse des demandes envoyées est refaite avec les règles actuelles.
 */
class DonneesTest
{
    public const FICHIER = 'database/donnees_test.sql';

    /**
     * Exécute le script instruction par instruction : une erreur arrête le chargement et désigne l'instruction fautive.
     *
     * @return int nombre d'instructions exécutées
     */
    public static function charger(): int
    {
        $instructions = self::instructions(file_get_contents(base_path(self::FICHIER)));

        foreach ($instructions as $sql) {
            DB::unprepared($sql);
        }

        // Les aires protégées ont été remplacées : la liste de l'accueil en cache aussi.
        Cache::forget(Site::CACHE_ACCUEIL);

        return count($instructions);
    }

    /**
     * Instructions d'un script SQL : chacune se termine par « ; » en fin de ligne ; les lignes vides
     * et les commentaires (« -- ») entre deux instructions sont ignorés.
     *
     * @return array<int, string>
     */
    public static function instructions(string $script): array
    {
        $instructions = [];
        $courante = [];

        foreach (preg_split('/\R/u', $script) as $ligne) {
            $texte = trim($ligne);
            if (! $courante && ($texte === '' || str_starts_with($texte, '--'))) {
                continue;
            }

            $courante[] = $ligne;
            if (str_ends_with($texte, ';')) {
                $instructions[] = implode("\n", $courante);
                $courante = [];
            }
        }

        return $instructions;
    }

    /**
     * Un PDF de démonstration pour chaque pièce jointe dont le fichier manque : le script ne crée que les lignes
     * de t_documents. Les fichiers déjà présents sont gardés, aucun fichier n'est supprimé.
     *
     * @return int nombre de fichiers créés
     */
    public static function piecesFactices(): int
    {
        $creees = 0;

        Document::with('demande:id,created_at')->orderBy('id')->each(function (Document $document) use (&$creees) {
            if (Storage::exists($document->chemin)) {
                return;
            }

            $html = '<body style="font-family: DejaVu Sans, sans-serif; padding: 40px">'
                . '<h1 style="font-size: 22px">' . e($document->type_document) . '</h1>'
                . '<p>Pièce de démonstration du dossier ' . e($document->demande?->reference) . ', créée pour le jeu de données de test.</p>'
                . '<p style="color: #666">Fichier : ' . e($document->nom_fichier) . '</p></body>';

            Storage::put($document->chemin, Pdf::loadHTML($html)->output());
            $creees++;
        });

        return $creees;
    }

    /**
     * Analyse des demandes envoyées refaite avec les règles actuelles : le script l'enregistre au format
     * d'avant le moteur (périodes sensibles seulement). Les brouillons ne sont analysés qu'à leur envoi.
     *
     * @return int nombre de demandes analysées
     */
    public static function analyser(): int
    {
        $demandes = Demande::horsBrouillon()->whereNotNull('date_debut')->whereNotNull('date_fin')->orderBy('id')->get();

        foreach ($demandes as $demande) {
            $analyse = AnalyseDemande::analyser([
                'site_id' => $demande->site_id,
                'date_debut' => $demande->date_debut->toDateString(),
                'date_fin' => $demande->date_fin->toDateString(),
                'equipe_nombre' => $demande->equipe_nombre,
            ], $demande->user_id, $demande->id);

            // Les dates de modification du jeu de test restent celles du script.
            $demande->timestamps = false;
            $demande->update(['resultats_analyse' => $analyse['contraintes']]);
        }

        return $demandes->count();
    }
}
