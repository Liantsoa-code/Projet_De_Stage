<?php

namespace App\Http\Controllers;

use App\Support\PeriodeStats;
use App\Support\StatsAdmin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Rapport d'activité en PDF (admin et observateur) : les indicateurs de la période du tableau de bord,
 * calculés par StatsAdmin::rapport(), donc identiques à l'écran.
 */
class RapportActiviteController extends Controller
{
    public function __invoke(Request $request)
    {
        $periode = PeriodeStats::depuis($request);

        $pdf = Pdf::loadView('pdf.rapport_activite', (new StatsAdmin(Auth::user(), $periode))->rapport() + [
            'periode' => $periode,
            'auteur' => Auth::user(),
        ]);

        return $pdf->stream('Rapport_activite_Fanamby_' . now()->format('Y-m-d') . '.pdf');
    }
}
