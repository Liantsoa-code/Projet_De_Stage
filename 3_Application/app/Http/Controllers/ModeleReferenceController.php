<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModeleReferenceRequest;
use App\Models\ModeleReference;
use App\Support\FormatDocument;
use App\Support\ModelesReference;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Modèles des documents officiels de la page Références. Le document (PDF ou Word) est ouvert aux validateurs
 * et aux admins ; la création, la modification, l'aperçu et la suppression sont réservés aux admins
 * (gates sur les routes).
 */
class ModeleReferenceController extends Controller
{
    public function show(Request $request, ModeleReference $modele)
    {
        return $this->document($request, $modele->titre, $modele->contenu);
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
     * Document (PDF ou Word) du texte en cours de saisie, avant enregistrement.
     */
    public function apercu(Request $request)
    {
        $valide = $request->validate([
            'titre' => 'nullable|string|max:150',
            'contenu' => 'required|string|max:' . ModelesReference::CONTENU_MAX,
        ]);

        return $this->document($request, $valide['titre'] ?? 'Sans titre', $valide['contenu']);
    }

    private function document(Request $request, string $titre, string $contenu)
    {
        return FormatDocument::reponse(
            $request,
            ModelesReference::nomFichier($titre),
            fn () => ModelesReference::pdf($titre, $contenu),
            fn () => ModelesReference::word($titre, $contenu),
        );
    }
}
