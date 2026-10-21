<?php

namespace App\Http\Controllers;

use App\Models\DocumentReference;
use App\Models\ModeleReference;
use App\Support\ModelesReference;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Page Références : modèles des documents officiels et documents déposés par l'équipe,
 * réservés aux validateurs et aux admins (route protégée par la gate is-validateur).
 */
class ReferenceController extends Controller
{
    public function index()
    {
        $modeles = ModeleReference::orderBy('id')
            ->get()
            ->map(fn (ModeleReference $m) => [
                'id' => $m->id,
                'titre' => $m->titre,
                'description' => $m->description,
                'usage' => $m->usage,
                'icone' => $m->icone,
                'automatique' => $m->automatique,
                'champs' => ModelesReference::champs($m->contenu),
            ]);

        $documents = DocumentReference::with('auteur:id,name')
            ->latest()
            ->get()
            ->map(fn (DocumentReference $d) => [
                'id' => $d->id,
                'titre' => $d->titre,
                'description' => $d->description,
                'nom_fichier' => $d->nom_fichier,
                'type_mime' => $d->type_mime,
                'taille' => $d->taille,
                'auteur' => $d->auteur?->name,
                'cree_le' => $d->created_at,
                'peut_supprimer' => Gate::allows('delete', $d),
            ]);

        return Inertia::render('References/Index', [
            'modeles' => $modeles,
            'documents' => $documents,
            'peutGererModeles' => Gate::allows('is-admin'),
        ]);
    }
}
