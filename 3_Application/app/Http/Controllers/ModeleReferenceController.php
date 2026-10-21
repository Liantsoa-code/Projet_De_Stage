<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModeleReferenceRequest;
use App\Models\ModeleReference;
use App\Support\ModelesReference;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Modèles des documents officiels de la page Références. Le PDF est ouvert aux validateurs
 * et aux admins ; la création, la modification, l'aperçu et la suppression sont réservés aux admins
 * (gates sur les routes).
 */
class ModeleReferenceController extends Controller
{
    public function show(ModeleReference $modele)
    {
        return ModelesReference::pdf($modele->titre, $modele->contenu)
            ->stream(ModelesReference::nomFichier($modele->titre));
    }

    public function create()
    {
        return Inertia::render('References/ModeleForm', ['modele' => null, 'icones' => ModelesReference::ICONES]);
    }

    public function store(ModeleReferenceRequest $request)
    {
        ModeleReference::create($request->validated());

        return redirect()->route('references.index')->with('message', 'Modèle ajouté.');
    }

    public function edit(ModeleReference $modele)
    {
        return Inertia::render('References/ModeleForm', ['modele' => $modele, 'icones' => ModelesReference::ICONES]);
    }

    public function update(ModeleReferenceRequest $request, ModeleReference $modele)
    {
        $modele->update($request->validated());

        return redirect()->route('references.index')->with('message', 'Modèle modifié.');
    }

    public function destroy(ModeleReference $modele)
    {
        $modele->delete();

        return redirect()->route('references.index')->with('message', "Modèle « {$modele->titre} » supprimé.");
    }

    /**
     * PDF du texte en cours de saisie, avant enregistrement.
     */
    public function apercu(Request $request)
    {
        $valide = $request->validate([
            'titre' => 'nullable|string|max:150',
            'contenu' => 'required|string|max:' . ModelesReference::CONTENU_MAX,
        ]);

        $titre = $valide['titre'] ?? 'Sans titre';

        return ModelesReference::pdf($titre, $valide['contenu'])->stream(ModelesReference::nomFichier($titre));
    }
}
