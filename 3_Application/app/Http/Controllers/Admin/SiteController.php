<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Site;
use App\Support\ListeEditable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $sites = Site::query()
            ->withCount('demandes')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('nom', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")))
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Sites/Index', [
            'sites' => $sites,
            'filters' => ['q' => $q],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Sites/Create');
    }

    public function store(Request $request)
    {
        $validated = $this->valider($request);

        DB::transaction(function () use ($validated) {
            $site = Site::create(Arr::except($validated, 'conditions'));
            ListeEditable::synchroniser($site->conditions(), $validated['conditions'] ?? []);
        });

        return redirect()->route('admin.sites.index')->with('message', 'Aire protégée créée avec succès.');
    }

    public function edit(Site $site)
    {
        $site->load('conditions:id,site_id,libelle');

        return Inertia::render('Admin/Sites/Edit', ['site' => $site]);
    }

    public function update(Request $request, Site $site)
    {
        $validated = $this->valider($request);

        DB::transaction(function () use ($site, $validated) {
            $site->update(Arr::except($validated, 'conditions'));
            ListeEditable::synchroniser($site->conditions(), $validated['conditions'] ?? []);
        });

        return redirect()->route('admin.sites.index')->with('message', 'Aire protégée modifiée avec succès.');
    }

    /**
     * Fiche d'une aire protégée. La capacité d'accueil et les règles permanentes servent à l'analyse des demandes.
     */
    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => 'required|string|max:150',
            'description' => 'nullable|string',
            'capacite_accueil' => 'nullable|integer|min:1|max:10000',
            'polygone_coordonnees' => 'nullable|array', // Peut venir sous forme JSON/Array depuis la carte
            'conditions' => 'array',
            'conditions.*.id' => 'nullable|integer',
            'conditions.*.libelle' => 'required|string|max:255',
        ], [
            'conditions.*.libelle.required' => 'Chaque règle doit avoir un texte.',
        ], [
            'capacite_accueil' => "capacité d'accueil",
        ]);
    }

    public function destroy(Site $site)
    {
        // La base refuse la suppression (RESTRICT) : on explique pourquoi plutôt que d'afficher une erreur SQL.
        $nbDemandes = Demande::where('site_id', $site->id)->count();
        if ($nbDemandes > 0) {
            return redirect()->route('admin.sites.index')
                ->with('error', "Impossible de supprimer « {$site->nom} » : {$nbDemandes} demande(s) y sont rattachées.");
        }

        $site->delete();
        return redirect()->route('admin.sites.index')->with('message', 'Aire protégée supprimée avec succès.');
    }


}
