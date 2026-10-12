<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PeriodeSensible;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PeriodeSensibleController extends Controller
{
    public function index(Request $request)
    {
        $siteId = $request->input('site_id');
        // Par défaut, seules les périodes en cours ou à venir ; « passees=1 » affiche aussi l'historique.
        $avecPassees = $request->boolean('passees');

        $periodes = PeriodeSensible::with('site:id,nom')
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when(! $avecPassees, fn ($q) => $q->where('date_fin', '>=', now()->toDateString()))
            ->orderBy('date_debut')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/PeriodeSensibles/Index', [
            'periodes' => $periodes,
            'sites' => Site::orderBy('nom')->get(['id', 'nom']),
            'filters' => ['site_id' => $siteId, 'passees' => $avecPassees],
        ]);
    }

    public function create()
    {
        $sites = Site::all(['id', 'nom']);
        return Inertia::render('Admin/PeriodeSensibles/Create', ['sites' => $sites]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'description' => 'required|string|max:255',
            'niveau_restriction' => 'required|in:bloquant,avertissement',
        ]);

        PeriodeSensible::create($validated);
        return redirect()->route('admin.periode-sensibles.index')->with('message', 'Période ajoutée avec succès.');
    }

    public function edit(PeriodeSensible $periode_sensible)
    {
        $sites = Site::all(['id', 'nom']);
        return Inertia::render('Admin/PeriodeSensibles/Edit', ['periode' => $periode_sensible, 'sites' => $sites]);
    }

    public function update(Request $request, PeriodeSensible $periode_sensible)
    {
        $validated = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'description' => 'required|string|max:255',
            'niveau_restriction' => 'required|in:bloquant,avertissement',
        ]);

        $periode_sensible->update($validated);
        return redirect()->route('admin.periode-sensibles.index')->with('message', 'Période modifiée avec succès.');
    }

    public function destroy(PeriodeSensible $periode_sensible)
    {
        $periode_sensible->delete();
        return redirect()->route('admin.periode-sensibles.index')->with('message', 'Période supprimée avec succès.');
    }
    
}
