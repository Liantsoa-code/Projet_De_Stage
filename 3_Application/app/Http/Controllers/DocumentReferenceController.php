<?php

namespace App\Http\Controllers;

use App\Models\DocumentReference;
use App\Support\DemandeDossier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Documents déposés sur la page Références : dépôt, consultation (affichage dans le navigateur
 * ou téléchargement) et suppression. Accès réservé aux validateurs et aux admins par la route.
 */
class DocumentReferenceController extends Controller
{
    public function store(Request $request)
    {
        $valide = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'fichier' => 'required|file|mimes:' . DemandeDossier::EXTENSIONS_AUTORISEES . '|max:20480',
        ], [
            'titre.required' => 'Donnez un titre au document.',
            'titre.max' => 'Le titre ne doit pas dépasser 255 caractères.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
            'fichier.required' => 'Choisissez un fichier.',
            'fichier.file' => 'Le fichier envoyé est invalide.',
            'fichier.uploaded' => "Le fichier n'a pas pu être envoyé (20 Mo maximum).",
            'fichier.mimes' => 'Le fichier doit être au format PDF, image (JPG, PNG) ou Word/OpenDocument.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 20 Mo.',
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('references');

        // Le fichier n'est gardé que si l'enregistrement en base réussit.
        try {
            DocumentReference::create([
                'titre' => trim($valide['titre']),
                'description' => filled($valide['description'] ?? null) ? trim($valide['description']) : null,
                'nom_fichier' => $fichier->getClientOriginalName(),
                'chemin' => $chemin,
                'type_mime' => $fichier->getMimeType(),
                'taille' => $fichier->getSize(),
                'user_id' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            Storage::delete($chemin);
            throw $e;
        }

        return back()->with('message', 'Document ajouté aux références.');
    }

    /**
     * Fichier affiché dans le navigateur (PDF, image), ou téléchargé avec ?telecharger=1.
     */
    public function show(Request $request, DocumentReference $document)
    {
        abort_unless(Storage::exists($document->chemin), 404, 'Fichier introuvable.');

        return Storage::response(
            $document->chemin,
            $document->nom_fichier,
            ['Content-Type' => $document->type_mime, 'X-Content-Type-Options' => 'nosniff'],
            $request->boolean('telecharger') ? 'attachment' : 'inline',
        );
    }

    public function destroy(DocumentReference $document)
    {
        Gate::authorize('delete', $document);

        $document->delete();
        Storage::delete($document->chemin);

        return back()->with('message', 'Document supprimé.');
    }
}
