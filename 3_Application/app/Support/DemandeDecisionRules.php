<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\User;
use Illuminate\Support\Collection;

class DemandeDecisionRules
{
    /**
     * Un admin ou validateur ne peut pas rendre de décision sur sa propre demande.
     */
    public static function canUserDecide(User $user, Demande $demande): bool
    {
        if (! in_array($user->role, ['admin', 'validateur'], true)) {
            return false;
        }

        return (int) $demande->user_id !== (int) $user->id;
    }

    /**
     * Accès en lecture à une demande et à ses fichiers (pièces jointes, PDF) :
     * le demandeur, un admin, ou un validateur rattaché au site de la demande.
     */
    public static function canUserView(User $user, Demande $demande): bool
    {
        if ((int) $demande->user_id === (int) $user->id) {
            return true;
        }

        // Un brouillon reste privé tant qu'il n'est pas soumis.
        if ($demande->statut?->code === 'brouillon') {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'validateur') {
            return false;
        }

        return $user->sites()->where('sites.id', $demande->site_id)->exists();
    }

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
                ->whereHas('sites', fn ($q) => $q->where('sites.id', $siteId))
                ->get();
        }

        return $query
            ->where(function ($q) use ($siteId) {
                $q->where('role', 'admin')
                    ->orWhere(function ($q2) use ($siteId) {
                        $q2->where('role', 'validateur')
                            ->whereHas('sites', fn ($q3) => $q3->where('sites.id', $siteId));
                    });
            })
            ->get();
    }
}
