<?php

namespace App\Http\Controllers;

use App\Models\PhotoSite;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;

/**
 * Galerie « Voir les photos » d'une aire protégée, publique comme la carte de la page d'accueil.
 * Rien n'est chargé avant le clic : la liste arrive en JSON, les vignettes puis chaque grande image à la demande.
 */
class PhotoSiteController extends Controller
{
    public function index(Site $site)
    {
        return response()->json([
            'nom' => $site->nom,
            'lien_photos' => $site->lien_photos,
            'photos' => $site->photos()->orderBy('ordre')->orderBy('id')->get(['id', 'largeur', 'hauteur', 'legende', 'credit']),
        ]);
    }

    /**
     * Fichier réduit (grande image ou vignette). Une photo ne change jamais de fichier (une nouvelle photo
     * a un nouvel identifiant) : le navigateur peut la garder un mois.
     */
    public function fichier(PhotoSite $photo, string $taille)
    {
        $chemin = $taille === 'vignette' ? $photo->chemin_vignette : $photo->chemin;
        abort_unless(Storage::exists($chemin), 404);

        return Storage::response($chemin, null, ['Cache-Control' => 'public, max-age=2592000']);
    }
}
