<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\TypeDemande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TypeDemandeController extends Controller
{
    public function index()
    {
        $types = TypeDemande::with('documentsRequis:id,type_demande_id,libelle,reutilisable')->get();
        return Inertia::render('Admin/TypeDemandes/Index', ['types' => $types]);
    }

    public function create()
    {
        return Inertia::render('Admin/TypeDemandes/Create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateType($request);

        DB::transaction(function () use ($validated) {
            $type = TypeDemande::create(['libelle' => $validated['libelle']]);
            $this->syncListes($type, $validated);
        });

        return redirect()->route('admin.type-demandes.index')->with('message', 'Type de demande ajouté.');
    }

    public function edit(TypeDemande $type_demande)
    {
        $type_demande->load(['documentsRequis:id,type_demande_id,libelle,reutilisable', 'reglesSpecifiques:id,type_demande_id,libelle']);

        return Inertia::render('Admin/TypeDemandes/Edit', ['type' => $type_demande]);
    }

    public function update(Request $request, TypeDemande $type_demande)
    {
        $validated = $this->validateType($request);

        DB::transaction(function () use ($validated, $type_demande) {
            $type_demande->update(['libelle' => $validated['libelle']]);
            $this->syncListes($type_demande, $validated);
        });

        return redirect()->route('admin.type-demandes.index')->with('message', 'Type de demande modifié.');
    }

    /**
     * Pièces requises et règles : une ligne par élément ({id, libelle}), l'id est conservé
     * quand on renomme une pièce, pour que les fichiers déjà déposés restent rattachés.
     * Une pièce peut être marquée réutilisable : elle est alors reprise d'un dossier précédent du demandeur.
     */
    private function validateType(Request $request): array
    {
        return $request->validate([
            'libelle' => 'required|string|max:100',
            'documents_requis' => 'array',
            'documents_requis.*.id' => 'nullable|integer',
            'documents_requis.*.libelle' => 'required|string|max:150|distinct:ignore_case',
            'documents_requis.*.reutilisable' => 'boolean',
            'regles_specifiques' => 'array',
            'regles_specifiques.*.id' => 'nullable|integer',
            'regles_specifiques.*.libelle' => 'required|string|max:255',
        ], [
            'documents_requis.*.libelle.required' => 'Chaque pièce doit avoir un nom.',
            'documents_requis.*.libelle.distinct' => 'Cette pièce figure déjà dans la liste.',
            'regles_specifiques.*.libelle.required' => 'Chaque règle doit avoir un texte.',
        ]);
    }

    private function syncListes(TypeDemande $type, array $validated): void
    {
        TypeDemande::syncListe($type->documentsRequis(), $validated['documents_requis'] ?? []);
        TypeDemande::syncListe($type->reglesSpecifiques(), $validated['regles_specifiques'] ?? []);
    }

    public function destroy(TypeDemande $type_demande)
    {
        // La base refuse la suppression (RESTRICT) : on explique pourquoi plutôt que d'afficher une erreur SQL.
        $nbDemandes = Demande::where('type_demande_id', $type_demande->id)->count();
        if ($nbDemandes > 0) {
            return redirect()->route('admin.type-demandes.index')
                ->with('error', "Impossible de supprimer « {$type_demande->libelle} » : {$nbDemandes} demande(s) l'utilisent.");
        }

        $type_demande->delete();
        return redirect()->route('admin.type-demandes.index')->with('message', 'Type de demande supprimé.');
    }
}
