<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\PeriodeSensible;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Base des tableaux de bord de l'instruction (StatsValidateur, StatsAdmin) :
 * dossiers en attente triés par urgence, compteurs et carte des sites.
 *
 * Les durées (délais, attentes, séjours) sont calculées en PHP, en jours calendaires.
 */
abstract class StatsInstruction
{
    /** Un dossier sans décision depuis plus de N jours est en retard. */
    public const JOURS_RETARD = 7;

    /** Un séjour qui commence dans N jours ou moins sans décision est imminent. */
    public const JOURS_IMMINENT = 14;

    /** Demandes prises en compte : hors brouillons, limitées aux sites du validateur. */
    protected Builder $base;

    public function __construct(protected User $user)
    {
        $this->base = Demande::query()->horsBrouillon()->forStatsViewer($user);
    }

    /**
     * Données envoyées à la page Dashboard.
     */
    abstract public function donnees(): array;

    /**
     * Dossiers soumis ou en analyse, triés par urgence :
     * séjours imminents (date d'arrivée la plus proche d'abord), puis retards (plus ancien d'abord), puis le reste.
     */
    protected function dossiersEnAttente(): Collection
    {
        $aujourdhui = today();

        return (clone $this->base)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', ['soumise', 'en_analyse']))
            ->with(['user:id,name', 'site:id,nom', 'statut:id,code,libelle'])
            ->get(['id', 'user_id', 'site_id', 'statut_id', 'date_debut', 'date_fin', 'date_soumission', 'created_at', 'resultats_analyse'])
            ->map(function (Demande $d) use ($aujourdhui) {
                $avant = $d->date_debut ? DelaiDecision::joursEntre($aujourdhui, $d->date_debut) : null;
                $attente = $d->date_soumission ? DelaiDecision::joursEntre($d->date_soumission, $aujourdhui) : 0;

                return [
                    'id' => $d->id,
                    'reference' => $d->reference,
                    'demandeur' => $d->user?->name,
                    'site_id' => $d->site_id,
                    'site' => $d->site?->nom,
                    'statut' => $d->statut?->only(['code', 'libelle']),
                    'date_debut' => $d->date_debut?->toDateString(),
                    'date_fin' => $d->date_fin?->toDateString(),
                    'jours_avant' => $avant,
                    'attente' => $attente,
                    'imminent' => $avant !== null && $avant <= self::JOURS_IMMINENT,
                    'retard' => $attente > self::JOURS_RETARD,
                    'conflit' => ! empty($d->resultats_analyse),
                ];
            })
            ->sortBy(fn ($d) => [
                $d['imminent'] ? 0 : ($d['retard'] ? 1 : 2),
                $d['imminent'] ? $d['jours_avant'] : -$d['attente'],
            ])
            ->values();
    }

    /**
     * Statistiques principales : demandes reçues (hors brouillons), en attente d'une décision
     * finale (soumises, en analyse ou chez le demandeur pour complément) et validées.
     * Reçues = en attente + validées + rejetées.
     */
    protected function principales(): array
    {
        $parCode = (clone $this->base)
            ->join('t_statuts', 't_statuts.id', '=', 't_demandes.statut_id')
            ->selectRaw('t_statuts.code as code, COUNT(*) as total')
            ->groupBy('t_statuts.code')
            ->pluck('total', 'code')
            ->map(fn ($n) => (int) $n);

        $nb = fn (string ...$codes) => collect($codes)->sum(fn ($code) => $parCode[$code] ?? 0);
        $decidees = $nb('validee', 'rejetee');

        return [
            'recues' => $parCode->sum(),
            'recues_mois' => (clone $this->base)->where('t_demandes.date_soumission', '>=', now()->startOfMonth())->count(),
            'en_attente' => $nb('soumise', 'en_analyse', 'a_completer'),
            'chez_demandeur' => $nb('a_completer'),
            'validees' => $nb('validee'),
            'rejetees' => $nb('rejetee'),
            // Sur les seuls dossiers décidés : les dossiers en attente ne font pas baisser le taux.
            'taux_validation' => $decidees > 0 ? (int) round($nb('validee') / $decidees * 100) : null,
        ];
    }

    protected function compteurs(Collection $enAttente): array
    {
        return [
            'en_attente' => $enAttente->count(),
            'non_pris' => $enAttente->where('statut.code', 'soumise')->count(),
            'imminents' => $enAttente->where('imminent', true)->count(),
            'retards' => $enAttente->where('retard', true)->count(),
            'conflits' => $enAttente->where('conflit', true)->count(),
            'attente_demandeur' => (clone $this->base)->whereHas('statut', fn ($q) => $q->where('code', 'a_completer'))->count(),
        ];
    }

    /**
     * Carte : sites affichés, charge de chacun et périodes sensibles pour le curseur de date.
     */
    protected function carte(Collection $enAttente): array
    {
        $colonnes = ['t_sites.id', 't_sites.nom', 't_sites.description', 't_sites.polygone_coordonnees'];
        $sites = $this->user->role === 'admin' ? Site::all($colonnes) : $this->user->sites()->get($colonnes);

        return [
            'sites' => $sites,
            'charge_sites' => $enAttente->groupBy('site_id')->map(fn ($ds) => [
                'en_attente' => $ds->count(),
                'retards' => $ds->where('retard', true)->count(),
                'conflits' => $ds->where('conflit', true)->count(),
            ]),
            'periodes' => PeriodeSensible::whereIn('site_id', $sites->pluck('id'))
                ->where('date_fin', '>=', today()->toDateString())
                ->orderBy('date_debut')
                ->get()
                ->map(fn (PeriodeSensible $p) => [
                    'id' => $p->id,
                    'site_id' => $p->site_id,
                    'date_debut' => $p->date_debut->toDateString(),
                    'date_fin' => $p->date_fin->toDateString(),
                    'niveau_restriction' => $p->niveau_restriction,
                    'description' => $p->description,
                ]),
        ];
    }
}
