<?php

namespace App\Support;

use App\Mail\DemandeNotificationMail;
use App\Models\Demande;
use App\Models\Notification;
use App\Models\Statut;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Notifications envoyées à chaque étape d'une demande : dans la cloche et par email.
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
     * Décision d'un validateur ou d'un admin : le demandeur est prévenu,
     * ainsi que les autres validateurs du site et les admins.
     */
    public static function decision(Demande $demande, Statut $nouveauStatut, User $decideur, string $commentaire): void
    {
        self::envoyer(
            [$demande->user_id],
            $demande,
            DemandeNotificationMessage::forStatutChange($demande, $nouveauStatut, $decideur, $commentaire),
        );

        self::envoyer(
            DemandeDecisionRules::notifiableTeamForDecision($demande, $decideur)->pluck('id'),
            $demande,
            DemandeNotificationMessage::forStatutChangeToEquipe($demande, $nouveauStatut, $decideur, $commentaire),
        );
    }

    /**
     * Notification dans la cloche, doublée d'un email.
     *
     * @param  iterable<int>  $userIds
     */
    private static function envoyer(iterable $userIds, Demande $demande, string $message): void
    {
        $envoi = now();

        foreach (User::whereKey(collect($userIds))->get() as $user) {
            Notification::create([
                'user_id' => $user->id,
                'demande_id' => $demande->id,
                'contenu' => $message,
                'lu' => false,
                'date_envoi' => $envoi,
            ]);

            Mail::to($user)->queue(new DemandeNotificationMail($demande, $user, $message));
        }
    }
}
