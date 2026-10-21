<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tableau de bord de l'admin : piloter le service (délais, organisation, fréquentation des sites).
 */
class StatsAdmin extends StatsInstruction
{
    /** Objectif de délai de décision suivi par l'admin. */
    public const OBJECTIF_JOURS = 15;

    public function donnees(): array
    {
        $enAttente = $this->dossiersEnAttente();
        $decisions = DelaiDecision::decisions($this->base);

        $nbDecisions = $decisions->count();
        $validees = $decisions->where('decision', 'validee')->count();
        $avecComplement = $nbDecisions === 0 ? 0 : HistoriqueStatut::whereIn('demande_id', $decisions->pluck('id'))
            ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
            ->distinct()
            ->count('demande_id');

        return $this->carte($enAttente) + [
            'principales' => $this->principales(),
            'compteurs' => $this->compteurs($enAttente),
            'urgents' => $enAttente->filter(fn ($d) => $d['imminent'] || $d['retard'] || $d['conflit'])->take(6)->values(),
            'qualite' => [
                'delai_median' => DelaiDecision::mediane($decisions),
                'objectif_jours' => self::OBJECTIF_JOURS,
                'pct_objectif' => $this->pourcentage($decisions->where('jours', '<=', self::OBJECTIF_JOURS)->count(), $nbDecisions),
                'decisions' => $nbDecisions,
                // Taux calculé sur les seuls dossiers décidés : les dossiers en attente ne le font pas baisser.
                'taux_validation' => $this->pourcentage($validees, $nbDecisions),
                'pct_complement' => $this->pourcentage($avecComplement, $nbDecisions),
            ],
            'terrain' => $this->surLeTerrain(),
            'sites_sans_validateur' => Site::whereDoesntHave('validateurs', fn ($q) => $q->where('role', 'validateur'))
                ->orderBy('nom')
                ->get(['id', 'nom']),
            'charge_validateurs' => $this->chargeValidateurs($enAttente),
            'frequentation' => $this->frequentation(),
            'par_mois' => $this->parMois(),
            'par_type' => (clone $this->base)
                ->join('t_type_demandes', 't_demandes.type_demande_id', '=', 't_type_demandes.id')
                ->selectRaw('t_type_demandes.libelle as nom, COUNT(t_demandes.id) as total')
                ->groupBy('t_type_demandes.id', 't_type_demandes.libelle')
                ->orderByDesc('total')
                ->get()
                ->toArray(),
            'total_users' => User::count(),
        ];
    }

    /**
     * Séjours validés en cours aujourd'hui : qui est sur le terrain, et combien.
     */
    private function surLeTerrain(): array
    {
        $jour = today()->toDateString();
        $sejours = Demande::query()
            ->whereHas('statut', fn ($q) => $q->where('code', 'validee'))
            ->where('date_debut', '<=', $jour)
            ->where('date_fin', '>=', $jour)
            ->with(['user:id,name', 'site:id,nom'])
            ->orderBy('date_fin')
            ->get(['id', 'user_id', 'site_id', 'date_debut', 'date_fin', 'equipe_nombre', 'created_at']);

        return [
            'sejours' => $sejours->count(),
            'personnes' => (int) $sejours->sum('equipe_nombre'),
            'liste' => $sejours->take(6)->map(fn (Demande $d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'demandeur' => $d->user?->name,
                'site' => $d->site?->nom,
                'equipe' => $d->equipe_nombre,
                'date_fin' => $d->date_fin->toDateString(),
            ])->values(),
        ];
    }

    /**
     * Dossiers en attente sur les sites de chaque validateur.
     */
    private function chargeValidateurs(Collection $enAttente): Collection
    {
        $parSite = $enAttente->countBy('site_id');

        return User::where('role', 'validateur')
            ->with('sites:id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $v) => [
                'id' => $v->id,
                'nom' => $v->name,
                'sites' => $v->sites->count(),
                'en_attente' => $v->sites->sum(fn ($s) => $parSite[$s->id] ?? 0),
            ])
            ->sortByDesc('en_attente')
            ->values();
    }

    /**
     * Fréquentation autorisée sur les 12 prochains mois, par site :
     * séjours, personnes, et personnes × jours (la pression humaine réelle sur le site).
     */
    private function frequentation(): Collection
    {
        return Demande::query()
            ->whereHas('statut', fn ($q) => $q->where('code', 'validee'))
            ->where('date_fin', '>=', today()->toDateString())
            ->where('date_debut', '<=', today()->addYear()->toDateString())
            ->with('site:id,nom')
            ->get(['id', 'site_id', 'date_debut', 'date_fin', 'equipe_nombre'])
            ->groupBy('site_id')
            ->map(fn ($ds) => [
                'nom' => $ds->first()->site?->nom,
                'sejours' => $ds->count(),
                'personnes' => (int) $ds->sum('equipe_nombre'),
                'personnes_jours' => (int) $ds->sum(fn ($d) => $d->equipe_nombre * (DelaiDecision::joursEntre($d->date_debut, $d->date_fin) + 1)),
            ])
            ->sortByDesc('personnes_jours')
            ->values();
    }

    /**
     * Demandes reçues par mois : année en cours et année précédente, pour comparaison.
     */
    private function parMois(): array
    {
        $annee = (int) date('Y');
        $parMois = ['courante' => array_fill(0, 12, 0), 'precedente' => array_fill(0, 12, 0)];

        (clone $this->base)
            ->whereYear('t_demandes.created_at', '>=', $annee - 1)
            ->get(['t_demandes.created_at'])
            ->each(function (Demande $d) use (&$parMois, $annee) {
                $parMois[$d->created_at->year === $annee ? 'courante' : 'precedente'][$d->created_at->month - 1]++;
            });

        return $parMois;
    }

    private function pourcentage(int $part, int $total): ?int
    {
        return $total > 0 ? (int) round($part / $total * 100) : null;
    }
}
