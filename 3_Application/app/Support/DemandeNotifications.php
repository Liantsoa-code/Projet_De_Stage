<?php

namespace App\Support;

use App\Mail\DemandeNotificationMail;
use App\Models\Demande;
use App\Models\Notification;
use App\Models\Signalement;
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
     * Soumission : accusé de réception au demandeur, alerte aux décideurs du site et aux observateurs.
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

        self::envoyer(
            DemandeDecisionRules::observateurs($demande)->pluck('id'),
            $demande,
            DemandeNotificationMessage::forNewDemandeToObservateur($demande),
        );
    }

    /**
     * Nouveau signalement : les validateurs du site et les admins sont prévenus, ainsi que le demandeur
     * (sauf s'il en est l'auteur), amené par sa notification à la partie signalée.
     */
    public static function signalement(Signalement $signalement): void
    {
        $signalement->loadMissing('demande.site', 'demande.statut', 'auteur');
        $demande = $signalement->demande;

        self::envoyer(
            DemandeDecisionRules::notifiablesForSignalement($demande, $signalement->auteur)->pluck('id'),
            $demande,
            DemandeNotificationMessage::forSignalement($signalement),
        );

        if ((int) $demande->user_id !== (int) $signalement->user_id) {
            self::envoyer(
                [$demande->user_id],
                $demande,
                DemandeNotificationMessage::forSignalementToDemandeur($signalement),
                PartieDossier::etape($signalement->partie),
            );
        }
    }

    /**
     * Signalement traité : son auteur est prévenu, avec la réponse (sauf s'il l'a traité lui-même).
     */
    public static function signalementTraite(Signalement $signalement): void
    {
        $signalement->loadMissing('demande.site', 'traitePar');

        if ($signalement->user_id && (int) $signalement->user_id !== (int) $signalement->traite_par) {
            self::envoyer([$signalement->user_id], $signalement->demande, DemandeNotificationMessage::forSignalementTraite($signalement));
        }
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
     * Demande retirée par son auteur : les décideurs du site n'ont plus à l'examiner.
     */
    public static function retrait(Demande $demande, string $motif): void
    {
        self::envoyer(
            DemandeDecisionRules::notifiableDecidersForNewDemande($demande)->pluck('id'),
            $demande,
            DemandeNotificationMessage::forRetraitToValidateur($demande, $motif),
        );
    }

    /**
     * Décision d'un validateur ou d'un admin : le demandeur est prévenu,
     * ainsi que les autres validateurs du site et les admins.
     * Pour un complément, la notification du demandeur l'amène à la partie à compléter.
     */
    public static function decision(Demande $demande, Statut $nouveauStatut, User $decideur, string $commentaire, ?string $partie = null): void
    {
        self::envoyer(
            [$demande->user_id],
            $demande,
            DemandeNotificationMessage::forStatutChange($demande, $nouveauStatut, $decideur, $commentaire, $partie),
            $nouveauStatut->code === 'a_completer' ? PartieDossier::etape($partie) : null,
        );

        self::envoyer(
            DemandeDecisionRules::notifiableTeamForDecision($demande, $decideur)->pluck('id'),
            $demande,
            DemandeNotificationMessage::forStatutChangeToEquipe($demande, $nouveauStatut, $decideur, $commentaire),
        );
    }

    /**
     * Notification dans la cloche, doublée d'un email. $etape : étape du formulaire de demande
     * où la notification amène le demandeur (voir NotificationController).
     *
     * @param  iterable<int>  $userIds
     */
    private static function envoyer(iterable $userIds, Demande $demande, string $message, ?string $etape = null): void
    {
        $envoi = now();

        foreach (User::whereKey(collect($userIds))->get() as $user) {
            Notification::create([
                'user_id' => $user->id,
                'demande_id' => $demande->id,
                'contenu' => $message,
                'etape' => $etape,
                'lu' => false,
                'date_envoi' => $envoi,
            ]);

            Mail::to($user)->queue(new DemandeNotificationMail($demande, $user, $message));
        }
    }
}
