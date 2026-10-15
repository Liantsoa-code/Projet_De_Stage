<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tableau de bord du validateur : traiter sa file de dossiers par ordre d'urgence.
 */
class StatsValidateur extends StatsInstruction
{
    public function donnees(): array
    {
        $enAttente = $this->dossiersEnAttente();

        return $this->carte($enAttente) + [
            'compteurs' => $this->compteurs($enAttente),
            'file' => $enAttente->take(8)->values(),
            'attente_demandeur' => $this->attenteDemandeur(),
            'activite' => $this->activite(),
            'delai_median' => DelaiDecision::mediane(DelaiDecision::decisions($this->base)),
        ];
    }

    /**
     * Dossiers renvoyés au demandeur pour complément, du plus ancien au plus récent.
     */
    private function attenteDemandeur(): Collection
    {
        return (clone $this->base)
            ->whereHas('statut', fn ($q) => $q->where('code', 'a_completer'))
            ->with(['user:id,name', 'site:id,nom'])
            ->select('demandes.id', 'demandes.user_id', 'demandes.site_id', 'demandes.created_at')
            ->addSelect(['complement_le' => HistoriqueStatut::select('date_changement')
                ->whereColumn('historique_statuts.demande_id', 'demandes.id')
                ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
                ->latest('date_changement')
                ->limit(1)])
            ->orderBy('complement_le')
            ->limit(5)
            ->get()
            ->map(fn (Demande $d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'demandeur' => $d->user?->name,
                'site' => $d->site?->nom,
                'depuis' => $d->complement_le ? DelaiDecision::joursEntre(Carbon::parse($d->complement_le), today()) : null,
            ]);
    }

    /**
     * Décisions prises par le validateur ce mois-ci, par type (hors ses propres demandes).
     */
    private function activite(): array
    {
        $parCode = HistoriqueStatut::query()
            ->join('statuts', 'statuts.id', '=', 'historique_statuts.nouveau_statut_id')
            ->join('demandes', 'demandes.id', '=', 'historique_statuts.demande_id')
            ->where('historique_statuts.user_id', $this->user->id)
            ->where('demandes.user_id', '!=', $this->user->id)
            ->where('historique_statuts.date_changement', '>=', now()->startOfMonth())
            ->whereIn('statuts.code', ['en_analyse', 'a_completer', 'validee', 'rejetee'])
            ->selectRaw('statuts.code as code, COUNT(*) as total')
            ->groupBy('statuts.code')
            ->pluck('total', 'code');

        return collect(['en_analyse', 'a_completer', 'validee', 'rejetee'])
            ->mapWithKeys(fn ($code) => [$code => (int) ($parCode[$code] ?? 0)])
            ->all();
    }
}
