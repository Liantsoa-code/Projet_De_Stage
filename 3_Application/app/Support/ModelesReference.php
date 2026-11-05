<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;

/**
 * Modèles des documents officiels (page Références) : mise en forme du contenu saisi par les admins
 * et rendu PDF ou Word.
 *
 * Conventions du contenu, ligne par ligne :
 *   # Titre          titre du document        ## Sous-titre   titre de partie
 *   - élément        liste à puces            > texte         bloc aligné à droite (date, destinataire, signature)
 *   ...              ligne pointillée à remplir à la main
 * Une ligne vide sépare les paragraphes. Dans le texte : **gras** et [champ à compléter].
 */
class ModelesReference
{
    /** Icônes proposées pour un modèle (voir resources/js/Components/Icon.vue). */
    public const ICONES = [
        'seal-check', 'stamp', 'file-text', 'prohibit', 'envelope-simple', 'files', 'file-pdf', 'check-circle',
        'warning', 'shield-check', 'microscope', 'film-slate', 'map-trifold', 'tree-evergreen', 'users', 'calendar-x',
    ];

    /** Longueur maximale du contenu d'un modèle. */
    public const CONTENU_MAX = 20000;

    /**
     * Champs à compléter : textes entre crochets, sans doublon, dans l'ordre d'apparition.
     *
     * @return array<int, string>
     */
    public static function champs(string $contenu): array
    {
        preg_match_all('/\[([^\[\]\r\n]+)\]/u', $contenu, $trouves);

        return array_values(array_unique(array_map('trim', $trouves[1])));
    }

    /**
     * Blocs du contenu, comme blocsModele (resources/js/utils/documents.js) : h1, h3, pointilles,
     * liste, droite ou texte, chacun avec ses lignes. Une ligne est une suite de morceaux de texte brut,
     * que chaque rendu (HTML du PDF, Word) échappe et met en forme.
     *
     * @return array<int, array{type: string, lignes: array<int, array<int, array{texte: string, gras: bool, champ: bool}>>}>
     */
    public static function blocs(string $contenu): array
    {
        $blocs = [];
        $bloc = null;

        $fermer = function () use (&$blocs, &$bloc) {
            if ($bloc) {
                $blocs[] = $bloc;
            }
            $bloc = null;
        };

        foreach (preg_split('/\R/u', $contenu) as $ligne) {
            $ligne = trim($ligne);

            if ($ligne === '') {
                $fermer();
                continue;
            }
            if (preg_match('/^(#{1,2})\s+(.+)$/u', $ligne, $titre)) {
                $fermer();
                $blocs[] = ['type' => strlen($titre[1]) === 1 ? 'h1' : 'h3', 'lignes' => [self::enLigne($titre[2])]];
                continue;
            }
            if ($ligne === '...') {
                $fermer();
                $blocs[] = ['type' => 'pointilles', 'lignes' => []];
                continue;
            }

            [$type, $texte] = match (true) {
                str_starts_with($ligne, '- ') => ['liste', substr($ligne, 2)],
                str_starts_with($ligne, '>') => ['droite', ltrim(substr($ligne, 1))],
                default => ['texte', $ligne],
            };
            if ($bloc && $bloc['type'] !== $type) {
                $fermer();
            }
            $bloc ??= ['type' => $type, 'lignes' => []];
            $bloc['lignes'][] = self::enLigne($texte);
        }
        $fermer();

        return $blocs;
    }

    /**
     * Morceaux d'une ligne : texte, **gras** et [champ] (le champ garde ses crochets), comme enLigne en JS.
     *
     * @return array<int, array{texte: string, gras: bool, champ: bool}>
     */
    private static function enLigne(string $texte): array
    {
        $morceaux = [];

        foreach (preg_split('/\*\*(.+?)\*\*/u', $texte, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $partie) {
            foreach (preg_split('/(\[[^\[\]]+\])/u', $partie, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $morceau) {
                $morceaux[] = ['texte' => $morceau, 'gras' => $i % 2 === 1, 'champ' => (bool) preg_match('/^\[[^\[\]]+\]$/u', $morceau)];
            }
        }

        return $morceaux;
    }

    /**
     * HTML du contenu pour la vue PDF. Tout le texte saisi est échappé avant la mise en forme.
     */
    public static function html(string $contenu): string
    {
        $ligne = fn (array $morceaux) => implode('', array_map(function (array $morceau) {
            $html = e($morceau['texte']);
            $html = $morceau['champ'] ? "<span class=\"champ\">{$html}</span>" : $html;

            return $morceau['gras'] ? "<strong>{$html}</strong>" : $html;
        }, $morceaux));

        return implode("\n", array_map(fn (array $bloc) => match ($bloc['type']) {
            'h1', 'h3' => "<{$bloc['type']}>" . $ligne($bloc['lignes'][0]) . "</{$bloc['type']}>",
            'pointilles' => '<div class="ligne"></div>',
            'liste' => '<ul>' . implode('', array_map(fn ($l) => '<li>' . $ligne($l) . '</li>', $bloc['lignes'])) . '</ul>',
            'droite' => '<p class="droite">' . implode('<br>', array_map($ligne, $bloc['lignes'])) . '</p>',
            default => '<p>' . implode('<br>', array_map($ligne, $bloc['lignes'])) . '</p>',
        }, self::blocs($contenu)));
    }

    public static function pdf(string $titre, string $contenu)
    {
        return Pdf::loadView('pdf.modele_reference', [
            'titre' => $titre,
            'html' => self::html($contenu),
        ]);
    }

    public static function word(string $titre, string $contenu): PhpWord
    {
        return DocumentsWord::modele($titre, $contenu);
    }

    /**
     * Nom du fichier téléchargé, sans extension (elle dépend du format).
     */
    public static function nomFichier(string $titre): string
    {
        return 'modele_' . (Str::slug($titre, '_') ?: 'document');
    }
}
