<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Support\DocumentsOfficiels;
use Illuminate\Support\Facades\Gate;

/**
 * Documents officiels : générés seulement pour une demande validée, et uniquement pour
 * le demandeur, un admin ou un validateur du site (DemandePolicy::telechargerPdf).
 * Le texte est celui relu et signé par le validateur (DocumentsOfficiels).
 */
class PdfController extends Controller
{
    public function autorisation(Demande $demande)
    {
        return $this->document($demande, 'autorisation');
    }

    public function cahierDesCharges(Demande $demande)
    {
        return $this->document($demande, 'cahier_charges');
    }

    private function document(Demande $demande, string $type)
    {
        Gate::authorize('telechargerPdf', $demande);

        return DocumentsOfficiels::pdf($demande, $type)
            ->stream(DocumentsOfficiels::FICHIERS[$type] . '_' . $demande->id . '.pdf');
    }
}
