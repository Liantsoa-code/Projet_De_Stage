<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Site;
use Illuminate\Http\Request;
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
        $validated = $request->validate([
            'nom' => 'required|string|max:150',
            'description' => 'nullable|string',
            'polygone_coordonnees' => 'nullable|array', // Peut venir sous forme JSON/Array depuis la carte
        ]);

        Site::create($validated);
        return redirect()->route('admin.sites.index')->with('message', 'Aire protégée créée avec succès.');
    }

    public function edit(Site $site)
    {
        return Inertia::render('Admin/Sites/Edit', ['site' => $site]);
    }

    public function update(Request $request, Site $site)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:150',
            'description' => 'nullable|string',
            'polygone_coordonnees' => 'nullable|array',
        ]);

        $site->update($validated);
        return redirect()->route('admin.sites.index')->with('message', 'Aire protégée modifiée avec succès.');
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
