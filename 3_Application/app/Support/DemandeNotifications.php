<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\Notification;
use App\Models\Statut;
use App\Models\User;

/**
 * Notifications internes (cloche) envoyées à chaque étape d'une demande.
 * Les textes sont dans DemandeNotificationMessage, le choix des décideurs dans DemandeDecisionRules.
 */
class DemandeNotifications
{
    /**
     * Soumission : accusé de réception au demandeur, et alerte aux décideurs du site.
     */
    public static function soumission(Demande $demande): void
    {
        $demande->load(['site', 'user', 'typeDemande']);

        self::envoyer([$demande->user_id], $demande, DemandeNotificationMessage::forSubmission($demande));

        self::envoyer(
            DemandeDecisionRules::notifiableDecidersForNewDemande($demande)->pluck('id'),
            $demande,
            DemandeNotificationMessage::forNewDemandeToValidateur($demande),
        );
    }

    /**
     * Complément renvoyé : prévient les décideurs du site ainsi que la personne qui a demandé les compléments.
     */
    public static function complementRenvoye(Demande $demande): void
    {
        $demandeurDuComplement = $demande->historiqueStatuts()
            ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
            ->latest('date_changement')
            ->first()?->user;

        $decideurs = DemandeDecisionRules::notifiableDecidersForNewDemande($demande)
            ->when($demandeurDuComplement, fn ($c) => $c->push($demandeurDuComplement))
            ->unique('id')
            ->reject(fn (User $u) => (int) $u->id === (int) $demande->user_id);

        self::envoyer($decideurs->pluck('id'), $demande, DemandeNotificationMessage::forComplementToValidateur($demande));
    }

    /**
     * Décision d'un validateur ou d'un admin : le demandeur est prévenu.
     */
    public static function decision(Demande $demande, Statut $nouveauStatut, User $decideur, string $commentaire): void
    {
        self::envoyer(
            [$demande->user_id],
            $demande,
            DemandeNotificationMessage::forStatutChange($demande, $nouveauStatut, $decideur, $commentaire),
        );
    }

    /**
     * @param  iterable<int>  $userIds
     */
    private static function envoyer(iterable $userIds, Demande $demande, string $message): void
    {
        $envoi = now();

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'demande_id' => $demande->id,
                'contenu' => $message,
                'lu' => false,
                'date_envoi' => $envoi,
            ]);
        }
    }
}
