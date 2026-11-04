<?php

namespace App\Http\Controllers;

use App\Support\StatsAdmin;
use App\Support\StatsDemandeur;
use App\Support\StatsObservateur;
use App\Support\StatsValidateur;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Tableau de bord adapté au rôle. Les calculs sont dans app/Support :
 * - demandeur : suivre ses dossiers et savoir quoi faire (StatsDemandeur) ;
 * - validateur : traiter sa file de dossiers par ordre d'urgence (StatsValidateur) ;
 * - admin : piloter le service — délais, organisation, fréquentation des sites (StatsAdmin) ;
 * - observateur : suivre tous les dossiers et ses signalements, sans décision (StatsObservateur).
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();

        $stats = match ($user->role) {
            'admin' => new StatsAdmin($user),
            'validateur' => new StatsValidateur($user),
            'observateur' => new StatsObservateur($user),
            default => new StatsDemandeur($user),
        };

        return Inertia::render('Dashboard', $stats->donnees());
    }
}
