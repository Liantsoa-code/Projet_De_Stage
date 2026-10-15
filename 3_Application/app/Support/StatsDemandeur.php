<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\User;

/**
 * Tableau de bord du demandeur : suivre ses dossiers et savoir quoi faire.
 */
class StatsDemandeur
{
    public function __construct(private User $user)
    {
    }

    /**
     * Données envoyées à la page Dashboard.
     */
    public function donnees(): array
    {
        $mesDemandes = $this->user->demandes()
            ->with([
                'site:id,nom',
                'typeDemande:id,libelle',
                'typeDemande.documentsRequis:id,type_demande_id',
                'statut:id,code,libelle',
                'documents:id,demande_id,document_requis_id',
            ])
            ->latest('updated_at')
            ->get();

        $aCompleter = $mesDemandes->where('statut.code', 'a_completer')->values();
        // Un seul chargement groupé de l'historique commenté (au lieu d'une requête par demande).
        $aCompleter->load(['historiqueStatuts' => fn ($q) => $q->whereNotNull('commentaire')->with('user:id,name')->latest('date_changement')]);
        $aCompleter->each(function ($d) {
            $dernier = $d->historiqueStatuts->first();
            $d->setAttribute('dernier_commentaire', $dernier?->commentaire);
            $d->setAttribute('commentaire_par', $dernier?->user?->name);
            $d->setAttribute('commentaire_le', $dernier?->date_changement);
            $d->unsetRelation('historiqueStatuts');
        });

        // Délai habituel de décision : celui du site s'il est représentatif, sinon celui de la plateforme.
        $enInstruction = $mesDemandes->whereIn('statut.code', ['soumise', 'en_analyse']);
        $delaiPour = $enInstruction->isNotEmpty() ? DelaiDecision::habituelParSite() : fn () => null;

        $mesDemandes->each(function (Demande $d) use ($enInstruction, $delaiPour) {
            if ($enInstruction->contains($d)) {
                $d->setAttribute('delai_habituel', $delaiPour($d->site_id));
                $d->setAttribute('attente', $d->date_soumission ? DelaiDecision::joursEntre($d->date_soumission, today()) : null);
            }
            if ($d->statut->code === 'brouillon') {
                $fournis = $d->documents->pluck('document_requis_id')->all();
                $d->setAttribute('pieces_manquantes', count(array_diff($d->typeDemande?->documentsRequis->modelKeys() ?? [], $fournis)));
            }
            $d->unsetRelation('documents');
        });

        return [
            'a_completer' => $aCompleter,
            'en_cours' => $mesDemandes->whereIn('statut.code', ['brouillon', 'soumise', 'en_analyse', 'a_completer'])->values(),
            'sejours' => $mesDemandes
                ->where('statut.code', 'validee')
                ->filter(fn ($d) => $d->date_fin && $d->date_fin->gte(today()))
                ->sortBy('date_debut')
                ->values(),
        ];
    }
}
