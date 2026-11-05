<?php

namespace App\Support;

use App\Models\Demande;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Délais de décision (soumission → validation ou rejet), en jours calendaires.
 * Partagé entre les tableaux de bord et la timeline d'une demande.
 */
class DelaiDecision
{
    /** En dessous de ce nombre de décisions, le délai d'un site n'est pas représentatif. */
    public const MIN_DECISIONS_SITE = 3;

    /**
     * Décisions finales (validée / rejetée) avec leur délai en jours depuis la soumission.
     *
     * @return Collection<int, array{id: int, site_id: int, decision: string, jours: int}>
     */
    public static function decisions(Builder $base): Collection
    {
        return (clone $base)
            ->join('t_historique_statuts as h', 'h.demande_id', '=', 't_demandes.id')
            ->join('t_statuts as decision', 'decision.id', '=', 'h.nouveau_statut_id')
            ->whereIn('decision.code', ['validee', 'rejetee'])
            ->whereNotNull('t_demandes.date_soumission')
            ->get(['t_demandes.id', 't_demandes.site_id', 't_demandes.date_soumission', 'h.date_changement as date_decision', 'decision.code as decision'])
            ->map(fn (Demande $d) => [
                'id' => $d->id,
                'site_id' => $d->site_id,
                'decision' => $d->getAttribute('decision'),
                'jours' => self::joursEntre($d->date_soumission, Carbon::parse($d->getAttribute('date_decision'))),
            ]);
    }

    /**
     * Délai habituel de décision pour un site : celui du site s'il compte assez de décisions,
     * sinon celui de toute la plateforme (null s'il n'y a encore aucune décision).
     *
     * @return Closure(int): ?float
     */
    public static function habituelParSite(): Closure
    {
        $decisions = self::decisions(Demande::query());
        $global = self::mediane($decisions);
        $parSite = $decisions->groupBy('site_id')
            ->filter(fn ($ds) => $ds->count() >= self::MIN_DECISIONS_SITE)
            ->map(fn ($ds) => self::mediane($ds));

        return fn (int $siteId) => $parSite[$siteId] ?? $global;
    }

    public static function mediane(Collection $decisions): ?float
    {
        return $decisions->isEmpty() ? null : round($decisions->median('jours'), 1);
    }

    /** Nombre de jours calendaires entre deux dates (signé). */
    public static function joursEntre($debut, $fin): int
    {
        return (int) round($debut->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay()));
    }
}
