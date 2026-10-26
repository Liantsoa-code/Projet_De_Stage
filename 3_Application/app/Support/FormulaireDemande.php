<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\TypeDemande;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Formulaire de demande d'autorisation rempli, au format du formulaire papier de Fanamby :
 * I. demandeur, II. projet, V. engagement du demandeur, VI. décision de Fanamby.
 * Les cases cochées sont celles du dossier ; la section VI reste vierge tant qu'aucun avis n'est rendu.
 */
class FormulaireDemande
{
    public static function pdf(Demande $demande)
    {
        return Pdf::loadView('pdf.formulaire_demande', self::donnees($demande));
    }

    /**
     * Variables de la vue PDF : la demande, toutes les cases des listes du formulaire (types de projet,
     * aires protégées), les engagements et la décision (avis favorable = validation, défavorable = rejet).
     */
    public static function donnees(Demande $demande): array
    {
        $demande->loadMissing('user', 'site', 'typeDemande');

        return [
            'demande' => $demande,
            'types' => TypeDemande::orderBy('id')->get(['id', 'libelle', 'precision_requise']),
            'sites' => Site::orderBy('nom')->get(['id', 'nom']),
            'engagements' => Demande::ENGAGEMENTS,
            'decision' => self::decision($demande),
        ];
    }

    /**
     * Dernière décision définitive : validation (avis favorable) ou rejet (avis défavorable).
     */
    public static function decision(Demande $demande): ?HistoriqueStatut
    {
        return HistoriqueStatut::with('user:id,name,fonction', 'nouveauStatut:id,code')
            ->where('demande_id', $demande->id)
            ->whereHas('nouveauStatut', fn ($q) => $q->whereIn('code', ['validee', 'rejetee']))
            ->latest('date_changement')
            ->latest('id')
            ->first();
    }
}
