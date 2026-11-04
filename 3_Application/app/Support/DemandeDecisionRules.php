<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Décideurs à prévenir pour une demande. Qui peut consulter ou décider : voir DemandePolicy.
 */
class DemandeDecisionRules
{
    /**
     * Utilisateurs à notifier lors d'une nouvelle soumission ou d'un complément renvoyé :
     * les admins, qui instruisent toutes les aires protégées, et les validateurs du site concerné,
     * hors auteur de la demande (on ne statue jamais sur sa propre demande).
     *
     * @return Collection<int, User>
     */
    public static function notifiableDecidersForNewDemande(Demande $demande): Collection
    {
        return self::decideursDuSite($demande->site_id)
            ->where('id', '!=', $demande->user_id)
            ->get();
    }

    /**
     * Équipe à tenir informée d'un changement de statut : validateurs du site et admins,
     * hors auteur de la décision et hors demandeur (prévenu par son propre message).
     *
     * @return Collection<int, User>
     */
    public static function notifiableTeamForDecision(Demande $demande, User $decideur): Collection
    {
        return self::decideursDuSite($demande->site_id)
            ->whereNotIn('id', [$decideur->id, $demande->user_id])
            ->get();
    }

    /**
     * Observateurs à prévenir d'une nouvelle demande (ils suivent toutes les aires protégées),
     * hors auteur de la demande.
     *
     * @return Collection<int, User>
     */
    public static function observateurs(Demande $demande): Collection
    {
        return User::where('role', 'observateur')->where('id', '!=', $demande->user_id)->get();
    }

    /**
     * Destinataires d'un signalement : validateurs du site et admins, hors auteur du signalement
     * et hors auteur de la demande (les signalements sont internes à l'équipe).
     *
     * @return Collection<int, User>
     */
    public static function notifiablesForSignalement(Demande $demande, User $auteur): Collection
    {
        return self::decideursDuSite($demande->site_id)
            ->whereNotIn('id', [$auteur->id, $demande->user_id])
            ->get();
    }

    /**
     * Admins et validateurs rattachés au site.
     */
    private static function decideursDuSite(int $siteId): Builder
    {
        return User::query()->where(function ($q) use ($siteId) {
            $q->where('role', 'admin')
                ->orWhere(function ($q2) use ($siteId) {
                    $q2->where('role', 'validateur')
                        ->whereHas('sites', fn ($q3) => $q3->where('t_sites.id', $siteId));
                });
        });
    }
}
