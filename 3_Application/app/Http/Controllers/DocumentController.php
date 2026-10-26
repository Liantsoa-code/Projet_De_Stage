<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function show(Document $document)
    {
        // Phase 6 : Accès sécurisé aux documents (Point 6 des règles)
        // Validateur : uniquement les demandes des sites qui lui sont assignés (DemandePolicy::consulterFichiers).
        Gate::authorize('consulterFichiers', $document->demande);

        // Vérifier si le fichier existe
        if (!Storage::exists($document->chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::download($document->chemin, $document->nom_fichier);
    }
}
