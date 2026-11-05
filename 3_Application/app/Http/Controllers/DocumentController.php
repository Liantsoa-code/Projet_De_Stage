<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

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

    /**
     * Toutes les pièces jointes d'une demande dans un seul zip, mêmes droits qu'une pièce seule.
     * Chaque fichier est préfixé par le libellé de sa pièce (« Protocole - protocole.pdf »).
     */
    public function zip(Demande $demande)
    {
        Gate::authorize('consulterFichiers', $demande);

        $documents = $demande->documents()->orderBy('id')->get()->filter(fn (Document $d) => Storage::exists($d->chemin));
        abort_if($documents->isEmpty(), 404, 'Aucune pièce jointe à télécharger.');

        $chemin = tempnam(sys_get_temp_dir(), 'pieces');
        $zip = new ZipArchive();
        $zip->open($chemin, ZipArchive::OVERWRITE);

        $noms = [];
        foreach ($documents as $document) {
            $nom = Str::of($document->type_document . ' - ' . $document->nom_fichier)->replaceMatches('#[\\\\/:*?"<>|]#', '_')->toString();
            // Deux fichiers du même nom (pièce renvoyée en complément) : « (2) » avant l'extension
            $base = $nom;
            for ($i = 2; isset($noms[$nom]); $i++) {
                $nom = pathinfo($base, PATHINFO_FILENAME) . " ({$i})" . (pathinfo($base, PATHINFO_EXTENSION) ? '.' . pathinfo($base, PATHINFO_EXTENSION) : '');
            }
            $noms[$nom] = true;
            $zip->addFromString($nom, Storage::get($document->chemin));
        }
        $zip->close();

        return response()->download($chemin, "Pieces_{$demande->reference}.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
