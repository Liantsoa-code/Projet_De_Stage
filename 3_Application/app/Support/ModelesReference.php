<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Modèles des documents officiels (page Références) : mise en forme du contenu saisi par les admins
 * et rendu PDF. Chaque PDF porte la mention « Modèle », sans valeur officielle.
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
     * HTML du contenu pour la vue PDF. Tout le texte saisi est échappé avant la mise en forme.
     */
    public static function html(string $contenu): string
    {
        $html = [];
        $bloc = [];
        $type = null;

        $fermer = function () use (&$html, &$bloc, &$type) {
            if ($bloc) {
                $html[] = match ($type) {
                    'liste' => '<ul>' . implode('', array_map(fn ($ligne) => "<li>{$ligne}</li>", $bloc)) . '</ul>',
                    'droite' => '<p class="droite">' . implode('<br>', $bloc) . '</p>',
                    default => '<p>' . implode('<br>', $bloc) . '</p>',
                };
            }
            $bloc = [];
            $type = null;
        };

        foreach (preg_split('/\R/u', $contenu) as $ligne) {
            $ligne = trim($ligne);

            if ($ligne === '') {
                $fermer();
                continue;
            }
            if (preg_match('/^(#{1,2})\s+(.+)$/u', $ligne, $titre)) {
                $fermer();
                $balise = strlen($titre[1]) === 1 ? 'h1' : 'h3';
                $html[] = "<{$balise}>" . self::enLigne($titre[2]) . "</{$balise}>";
                continue;
            }
            if ($ligne === '...') {
                $fermer();
                $html[] = '<div class="ligne"></div>';
                continue;
            }

            [$nouveau, $texte] = match (true) {
                str_starts_with($ligne, '- ') => ['liste', substr($ligne, 2)],
                str_starts_with($ligne, '>') => ['droite', ltrim(substr($ligne, 1))],
                default => ['texte', $ligne],
            };
            if ($type !== null && $type !== $nouveau) {
                $fermer();
            }
            $type = $nouveau;
            $bloc[] = self::enLigne($texte);
        }
        $fermer();

        return implode("\n", $html);
    }

    private static function enLigne(string $texte): string
    {
        $texte = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($texte));

        return preg_replace('/\[([^\[\]]+)\]/u', '<span class="champ">[$1]</span>', $texte);
    }

    public static function pdf(string $titre, string $contenu)
    {
        return Pdf::loadView('pdf.modele_reference', [
            'titre' => $titre,
            'html' => self::html($contenu),
        ]);
    }

    public static function nomFichier(string $titre): string
    {
        return 'modele_' . (Str::slug($titre, '_') ?: 'document') . '.pdf';
    }
}
