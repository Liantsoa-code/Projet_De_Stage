<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\Signalement;
use App\Models\Statut;
use App\Models\User;

class DemandeNotificationMessage
{
    /**
     * Message destiné au demandeur lors d'un changement de statut.
     */
    public static function forStatutChange(
        Demande $demande,
        Statut $nouveauStatut,
        User $decideur,
        string $commentaire,
        ?string $partie = null
    ): string {
        $site = $demande->site->nom;
        $commentaire = trim($commentaire);
        $aCompleter = PartieDossier::libelleComplement($partie);

        $message = match ($nouveauStatut->code) {
            'validee' => "Votre demande d'accès à « {$site} » a été acceptée. "
                . 'Retrouvez votre autorisation, votre cahier des charges et la convention de collaboration dans Mes demandes.',

            'rejetee' => "Votre demande d'accès à « {$site} » n'a pas été retenue cette fois. "
                . 'Le détail de la décision est disponible dans votre dossier.',

            'en_analyse' => "Votre dossier pour « {$site} » est en cours d'examen. "
                . 'Vous serez prévenu dès qu\'une décision sera prise.',

            'soumise' => "Votre demande pour « {$site} » a bien été enregistrée et est en attente de traitement.",

            'a_completer' => "Votre dossier pour « {$site} » doit être complété avant de poursuivre son examen"
                . ($aCompleter ? " ({$aCompleter})" : '') . '. '
                . (PartieDossier::etape($partie)
                    ? 'Ouvrez cette notification pour aller directement à la partie à compléter.'
                    : 'Ouvrez-le depuis Mes demandes pour fournir les éléments demandés.'),

            default => "Votre demande pour « {$site} » a été mise à jour : {$nouveauStatut->libelle}.",
        };

        if ($commentaire !== '' && in_array($nouveauStatut->code, ['validee', 'rejetee', 'en_analyse', 'a_completer'], true)) {
            $message .= ' Message de l\'équipe : « '.$commentaire.' »';
        }

        if ($decideur->role === 'admin' && $nouveauStatut->code !== 'soumise') {
            $message .= ' (Décision prise par l\'administration.)';
        }

        return $message;
    }

    /**
     * Informe les autres validateurs du site et les admins d'une décision prise par un collègue.
     */
    public static function forStatutChangeToEquipe(
        Demande $demande,
        Statut $nouveauStatut,
        User $decideur,
        string $commentaire
    ): string {
        $demande->loadMissing('user', 'site');
        $commentaire = trim($commentaire);

        $message = "Le dossier #{$demande->id} de {$demande->user->name} pour « {$demande->site->nom} » "
            . "est passé au statut « {$nouveauStatut->libelle} » (décision de {$decideur->name}).";

        if ($commentaire !== '') {
            $message .= ' Commentaire : « '.$commentaire.' »';
        }

        return $message;
    }

    /**
     * Accusé de réception après soumission d'une nouvelle demande.
     */
    public static function forSubmission(Demande $demande): string
    {
        $site = $demande->site->nom;

        if ($demande->derogation) {
            return "Nous avons bien reçu votre demande de dérogation pour « {$site} » : vos dates tombent pendant une période d'interdiction. "
                . 'L\'équipe Fanamby examinera votre justification ; elle pourra accepter, vous proposer d\'autres dates ou refuser.';
        }

        return "Nous avons bien reçu votre demande d'accès à « {$site} ». "
            . 'Elle sera examinée prochainement ; vous recevrez une notification dès qu\'il y aura du nouveau.';
    }

    /**
     * Début des alertes à l'équipe : une demande sur une période bloquante est annoncée comme dérogation.
     */
    private static function nouvelle(Demande $demande): string
    {
        return $demande->derogation ? 'Demande de dérogation (période d\'interdiction)' : 'Nouvelle demande';
    }

    /**
     * Alerte les décideurs quand le demandeur renvoie un dossier complété.
     */
    public static function forComplementToValidateur(Demande $demande): string
    {
        $demande->loadMissing('user', 'site');

        return "{$demande->user->name} a complété son dossier #{$demande->id} pour « {$demande->site->nom} ». "
            . 'Il est de nouveau en attente de votre décision.';
    }

    /**
     * Prévient les décideurs qu'une demande a été retirée par son auteur.
     */
    public static function forRetraitToValidateur(Demande $demande, string $motif): string
    {
        $demande->loadMissing('user', 'site');

        $message = "{$demande->user->name} a annulé sa demande {$demande->reference} pour « {$demande->site->nom} ». "
            . 'Le dossier est clôturé : aucune décision n\'est attendue.';

        if ($motif !== '') {
            $message .= ' Motif : « '.$motif.' »';
        }

        return $message;
    }

    /**
     * Prévient les observateurs d'une nouvelle demande, à suivre sans décision.
     */
    public static function forNewDemandeToObservateur(Demande $demande): string
    {
        $demande->loadMissing('user', 'typeDemande', 'site');

        return self::nouvelle($demande) . " pour « {$demande->site->nom} » : {$demande->user->name} ({$demande->typeProjet()}). "
            . 'Ouvrez le dossier pour en suivre l\'instruction.';
    }

    /**
     * Prévient les décideurs du site qu'un problème a été signalé sur un dossier.
     */
    public static function forSignalement(Signalement $signalement): string
    {
        $demande = $signalement->demande;
        $auteur = $signalement->auteur?->name ?? 'Un membre de l\'équipe';
        $partie = Signalement::PARTIES[$signalement->partie] ?? $signalement->partie;

        return "{$auteur} signale un problème sur le dossier {$demande->reference} pour « {$demande->site->nom} » ({$partie}) : « {$signalement->message} »";
    }

    /**
     * Prévient le demandeur qu'un point de sa demande a été signalé par l'équipe, sans nommer son auteur.
     */
    public static function forSignalementToDemandeur(Signalement $signalement): string
    {
        $demande = $signalement->demande;
        $partie = Signalement::PARTIES[$signalement->partie] ?? $signalement->partie;
        $message = "L'équipe Fanamby signale un point à revoir sur votre demande {$demande->reference} pour « {$demande->site->nom} » ({$partie}) : « {$signalement->message} »";

        if (! DemandeStatutWorkflow::isEditableByDemandeur($demande->statut)) {
            // Dossier en instruction : le demandeur ne peut le corriger que si l'équipe le lui renvoie
            return "{$message} Si une correction est nécessaire, l'équipe vous renverra le dossier pour que vous puissiez le modifier.";
        }

        return PartieDossier::etape($signalement->partie)
            ? "{$message} Ouvrez cette notification pour aller directement à la partie concernée."
            : $message;
    }

    /**
     * Prévient l'auteur d'un signalement qu'il a été traité, avec la réponse.
     */
    public static function forSignalementTraite(Signalement $signalement): string
    {
        $demande = $signalement->demande;
        $message = "Votre signalement sur le dossier {$demande->reference} a été traité par " . ($signalement->traitePar?->name ?? 'l\'équipe') . '.';

        return $signalement->reponse ? "{$message} Réponse : « {$signalement->reponse} »" : $message;
    }

    /**
     * Alerte les validateurs rattachés au site lors d'une nouvelle soumission.
     */
    public static function forNewDemandeToValidateur(Demande $demande): string
    {
        $demande->loadMissing('user', 'typeDemande', 'site');

        $site = $demande->site->nom;
        $demandeur = $demande->user->name;
        $projet = $demande->typeProjet();

        return self::nouvelle($demande) . " à traiter pour « {$site} » : {$demandeur} ({$projet}). "
            . 'Ouvrez le dossier pour examiner les pièces et rendre une décision.';
    }
}
