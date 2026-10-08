<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Support\DemandeDecisionRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class PdfController extends Controller
{
    public function autorisation(Demande $demande)
    {
        $this->assertCanDownload($demande);

        $pdf = Pdf::loadView('pdf.autorisation', ['demande' => $demande]);
        return $pdf->stream('autorisation_' . $demande->id . '.pdf');
    }

    public function cahierDesCharges(Demande $demande)
    {
        $this->assertCanDownload($demande);

        $pdf = Pdf::loadView('pdf.cahier_charges', ['demande' => $demande]);
        return $pdf->stream('cahier_des_charges_' . $demande->id . '.pdf');
    }

    /**
     * Les documents officiels ne sont générés que pour une demande validée,
     * et uniquement pour le demandeur, un admin ou un validateur du site.
     */
    private function assertCanDownload(Demande $demande): void
    {
        if (! DemandeDecisionRules::canUserView(Auth::user(), $demande)) {
            abort(403);
        }

        if ($demande->statut?->code !== 'validee') {
            abort(403, 'Les documents officiels ne peuvent être générés que si la demande est validée.');
        }
    }
}
