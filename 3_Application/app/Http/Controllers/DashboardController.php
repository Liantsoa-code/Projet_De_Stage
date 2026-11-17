<?php

namespace App\Http\Controllers;

use App\Support\PeriodeStats;
use App\Support\StatsAdmin;
use App\Support\StatsDemandeur;
use App\Support\StatsObservateur;
use App\Support\StatsValidateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Tableau de bord adapté au rôle. Les calculs sont dans app/Support :
 * - demandeur : suivre ses dossiers et savoir quoi faire (StatsDemandeur) ;
 * - validateur : traiter sa file de dossiers par ordre d'urgence (StatsValidateur) ;
 * - admin : piloter le service — délais, organisation, fréquentation des sites (StatsAdmin) ;
 * - observateur : suivre tous les dossiers et ses signalements, sans décision (StatsObservateur).
 * L'équipe choisit la période observée (« du », « au » : date d'envoi des demandes), voir PeriodeStats.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();
        $periode = PeriodeStats::depuis($request);

        $stats = match ($user->role) {
            'admin' => new StatsAdmin($user, $periode),
            'validateur' => new StatsValidateur($user, $periode),
            'observateur' => new StatsObservateur($user, $periode),
            default => new StatsDemandeur($user),
        };

        return Inertia::render('Dashboard', $stats->donnees() + ['periode' => $periode->pourPage()]);
    }
}
