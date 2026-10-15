<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

/**
 * Documents officiels : générés seulement pour une demande validée, et uniquement pour
 * le demandeur, un admin ou un validateur du site (DemandePolicy::telechargerPdf).
 */
class PdfController extends Controller
{
    public function autorisation(Demande $demande)
    {
        Gate::authorize('telechargerPdf', $demande);

        $pdf = Pdf::loadView('pdf.autorisation', ['demande' => $demande]);
        return $pdf->stream('autorisation_' . $demande->id . '.pdf');
    }

    public function cahierDesCharges(Demande $demande)
    {
        Gate::authorize('telechargerPdf', $demande);

        $pdf = Pdf::loadView('pdf.cahier_charges', ['demande' => $demande]);
        return $pdf->stream('cahier_des_charges_' . $demande->id . '.pdf');
    }
}
