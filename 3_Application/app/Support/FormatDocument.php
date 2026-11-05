<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Format des documents produits par la plateforme, choisi à chaque téléchargement par le paramètre
 * « format » : PDF (par défaut, ouvert dans le navigateur) ou Word (.docx, modifiable avant impression).
 */
class FormatDocument
{
    /** Formats proposés, avec leur type MIME. */
    public const FORMATS = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Format demandé, PDF s'il n'est pas précisé. Un format inconnu renvoie une 404.
     */
    public static function demande(Request $request): string
    {
        $format = $request->input('format') ?? 'pdf';

        abort_unless(is_string($format) && isset(self::FORMATS[$format]), 404);

        return $format;
    }

    /**
     * Document dans le format demandé : seule cette version est produite.
     *
     * @param  string  $nom  nom du fichier, sans extension
     * @param  Closure(): \Barryvdh\DomPDF\PDF  $pdf
     * @param  Closure(): PhpWord  $word
     */
    public static function reponse(Request $request, string $nom, Closure $pdf, Closure $word)
    {
        if (self::demande($request) === 'pdf') {
            return $pdf()->stream("{$nom}.pdf");
        }

        $document = $word();

        return response()->streamDownload(
            fn () => IOFactory::createWriter($document, 'Word2007')->save('php://output'),
            "{$nom}.docx",
            ['Content-Type' => self::FORMATS['docx']],
        );
    }
}
