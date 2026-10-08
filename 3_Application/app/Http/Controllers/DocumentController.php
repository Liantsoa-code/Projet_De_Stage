<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\DemandeDecisionRules;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function show(Document $document)
    {
        // Phase 6 : Accès sécurisé aux documents (Point 6 des règles)
        // Validateur : uniquement les demandes des sites qui lui sont assignés.
        if (! DemandeDecisionRules::canUserView(Auth::user(), $document->demande)) {
            abort(403, 'Accès non autorisé à ce document.');
        }

        // Vérifier si le fichier existe
        if (!Storage::exists($document->chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::download($document->chemin, $document->nom_fichier);
    }
}
