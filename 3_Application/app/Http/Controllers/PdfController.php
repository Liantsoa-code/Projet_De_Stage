<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Support\DocumentsOfficiels;
use App\Support\FormatDocument;
use App\Support\FormulaireDemande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Documents officiels : générés seulement pour une demande validée, et uniquement pour
 * le demandeur, un admin ou un validateur du site (DemandePolicy::telechargerPdf).
 * Le texte est celui relu et signé par le validateur (DocumentsOfficiels).
 * Le formulaire de demande rempli est disponible dès l'envoi du dossier (FormulaireDemande).
 * Chaque document existe en PDF ; la version Word, modifiable, est réservée à qui instruit le dossier
 * (DemandePolicy::telechargerWord) : le demandeur n'obtient que le PDF.
 */
class PdfController extends Controller
{
    public function autorisation(Request $request, Demande $demande)
    {
        return $this->document($request, $demande, 'autorisation');
    }

    public function cahierDesCharges(Request $request, Demande $demande)
    {
        return $this->document($request, $demande, 'cahier_charges');
    }

    /**
     * Formulaire de demande rempli, disponible dès l'envoi du dossier (DemandePolicy::telechargerFormulaire).
     */
    public function formulaire(Request $request, Demande $demande)
    {
        Gate::authorize('telechargerFormulaire', $demande);
        $this->autoriserFormat($request, $demande);

        return FormatDocument::reponse(
            $request,
            'formulaire_demande_' . $demande->id,
            fn () => FormulaireDemande::pdf($demande),
            fn () => FormulaireDemande::word($demande),
        );
    }

    private function document(Request $request, Demande $demande, string $type)
    {
        Gate::authorize('telechargerPdf', $demande);
        $this->autoriserFormat($request, $demande);

        return FormatDocument::reponse(
            $request,
            DocumentsOfficiels::FICHIERS[$type] . '_' . $demande->id,
            fn () => DocumentsOfficiels::pdf($demande, $type),
            fn () => DocumentsOfficiels::word($demande, $type),
        );
    }

    /**
     * La version Word se modifie : elle n'est remise qu'à qui instruit le dossier (DemandePolicy::telechargerWord).
     */
    private function autoriserFormat(Request $request, Demande $demande): void
    {
        if (FormatDocument::demande($request) === 'docx') {
            Gate::authorize('telechargerWord', $demande);
        }
    }
}
