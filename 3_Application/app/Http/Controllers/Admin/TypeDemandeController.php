<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\TypeDemande;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TypeDemandeController extends Controller
{
    public function index()
    {
        $types = TypeDemande::all();
        return Inertia::render('Admin/TypeDemandes/Index', ['types' => $types]);
    }

    public function create()
    {
        return Inertia::render('Admin/TypeDemandes/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'libelle' => 'required|string|max:100',
            'documents_requis' => 'nullable|string',
            'regles_specifiques' => 'nullable|string',
        ]);

        // Convertir la chaîne en tableau (séparée par des virgules)
        $documents = $validated['documents_requis'] ? array_map('trim', explode(',', $validated['documents_requis'])) : [];
        $regles = $validated['regles_specifiques'] ? array_map('trim', explode(',', $validated['regles_specifiques'])) : [];

        TypeDemande::create([
            'libelle' => $validated['libelle'],
            'documents_requis' => $documents,
            'regles_specifiques' => $regles,
        ]);

        return redirect()->route('admin.type-demandes.index')->with('message', 'Type de demande ajouté.');
    }

    public function edit(TypeDemande $type_demande)
    {
        return Inertia::render('Admin/TypeDemandes/Edit', ['type' => $type_demande]);
    }

    public function update(Request $request, TypeDemande $type_demande)
    {
        $validated = $request->validate([
            'libelle' => 'required|string|max:100',
            'documents_requis' => 'nullable|string',
            'regles_specifiques' => 'nullable|string',
        ]);

        $documents = $validated['documents_requis'] ? array_map('trim', explode(',', $validated['documents_requis'])) : [];
        $regles = $validated['regles_specifiques'] ? array_map('trim', explode(',', $validated['regles_specifiques'])) : [];

        $type_demande->update([
            'libelle' => $validated['libelle'],
            'documents_requis' => $documents,
            'regles_specifiques' => $regles,
        ]);

        return redirect()->route('admin.type-demandes.index')->with('message', 'Type de demande modifié.');
    }

    public function destroy(TypeDemande $type_demande)
    {
        // La clé étrangère est en cascade : supprimer le type effacerait les demandes associées.
        $nbDemandes = Demande::where('type_demande_id', $type_demande->id)->count();
        if ($nbDemandes > 0) {
            return redirect()->route('admin.type-demandes.index')
                ->with('error', "Impossible de supprimer « {$type_demande->libelle} » : {$nbDemandes} demande(s) l'utilisent.");
        }

        $type_demande->delete();
        return redirect()->route('admin.type-demandes.index')->with('message', 'Type de demande supprimé.');
    }
}
