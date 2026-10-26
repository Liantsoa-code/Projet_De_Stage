<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Décideurs à prévenir pour une demande. Qui peut consulter ou décider : voir DemandePolicy.
 */
class DemandeDecisionRules
{
    /**
     * Utilisateurs à notifier lors d'une nouvelle soumission (hors demandeur).
     * Demandeur classique → validateurs du site.
     * Admin ou validateur demandeur → autres admins ou validateurs du site concerné.
     *
     * @return Collection<int, User>
     */
    public static function notifiableDecidersForNewDemande(Demande $demande): Collection
    {
        $demande->loadMissing('user');
        $creator = $demande->user;
        $siteId = $demande->site_id;

        $query = User::query()->where('id', '!=', $creator->id);

        if ($creator->role === 'demandeur') {
            return $query
                ->where('role', 'validateur')
                ->whereHas('sites', fn ($q) => $q->where('t_sites.id', $siteId))
                ->get();
        }

        return $query
            ->where(function ($q) use ($siteId) {
                $q->where('role', 'admin')
                    ->orWhere(function ($q2) use ($siteId) {
                        $q2->where('role', 'validateur')
                            ->whereHas('sites', fn ($q3) => $q3->where('t_sites.id', $siteId));
                    });
            })
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
        $siteId = $demande->site_id;

        return User::query()
            ->whereNotIn('id', [$decideur->id, $demande->user_id])
            ->where(function ($q) use ($siteId) {
                $q->where('role', 'admin')
                    ->orWhere(function ($q2) use ($siteId) {
                        $q2->where('role', 'validateur')
                            ->whereHas('sites', fn ($q3) => $q3->where('t_sites.id', $siteId));
                    });
            })
            ->get();
    }
}
