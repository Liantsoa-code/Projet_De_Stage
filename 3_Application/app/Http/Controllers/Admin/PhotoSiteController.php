<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoSite;
use App\Models\Site;
use App\Support\PhotosSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Galerie d'une aire protégée, gérée depuis sa fiche (admins) : une photo par envoi, réduite aussitôt.
 */
class PhotoSiteController extends Controller
{
    public function store(Request $request, Site $site)
    {
        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:' . PhotosSite::POIDS_MAX,
            'legende' => 'nullable|string|max:255',
            'credit' => 'nullable|string|max:150',
        ], [
            'photo.max' => 'La photo ne doit pas dépasser 15 Mo.',
            'photo.image' => 'Le fichier doit être une photo (JPG, PNG ou WebP).',
            'photo.mimes' => 'Le fichier doit être une photo (JPG, PNG ou WebP).',
        ]);

        if ($site->photos()->count() >= PhotosSite::MAX) {
            return back()->withErrors(['photo' => 'Une aire protégée compte au plus ' . PhotosSite::MAX . ' photos : supprimez-en une avant d\'en ajouter.']);
        }

        PhotosSite::enregistrer($site, $validated['photo'], $validated['legende'] ?? null, $validated['credit'] ?? null);

        return back()->with('message', 'Photo ajoutée.');
    }

    public function update(Request $request, Site $site, PhotoSite $photo)
    {
        $photo->update($request->validate([
            'legende' => 'nullable|string|max:255',
            'credit' => 'nullable|string|max:150',
        ]));

        return back()->with('message', 'Légende enregistrée.');
    }

    /**
     * Nouvel ordre de la galerie : identifiants des photos du site, dans l'ordre voulu.
     */
    public function ordonner(Request $request, Site $site)
    {
        $ids = $request->validate([
            'ordre' => 'required|array',
            'ordre.*' => ['integer', Rule::exists('t_photos_site', 'id')->where('site_id', $site->id)],
        ])['ordre'];

        DB::transaction(function () use ($site, $ids) {
            foreach (array_values($ids) as $rang => $id) {
                $site->photos()->whereKey($id)->update(['ordre' => $rang + 1]);
            }
        });

        return back();
    }

    public function destroy(Site $site, PhotoSite $photo)
    {
        PhotosSite::supprimer($photo);

        return back()->with('message', 'Photo supprimée.');
    }
}
