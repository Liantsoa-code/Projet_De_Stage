<?php

namespace App\Policies;

use App\Models\Demande;
use App\Models\User;
use App\Support\DemandeStatutWorkflow;
use Illuminate\Auth\Access\Response;

/**
 * Qui peut faire quoi sur une demande. Appelée par Gate::authorize() dans les contrôleurs
 * et par les Form Requests ; Laravel la rattache automatiquement au modèle Demande.
 */
class DemandePolicy
{
    /**
     * Espace demandeur : seul l'auteur consulte sa demande.
     */
    public function view(User $user, Demande $demande): Response
    {
        return $this->estAuteur($user, $demande)
            ? Response::allow()
            : Response::deny('Accès non autorisé à cette demande.');
    }

    /**
     * Seuls les brouillons et les dossiers « à compléter » sont modifiables, par leur auteur.
     */
    public function update(User $user, Demande $demande): Response
    {
        if (! $this->estAuteur($user, $demande)) {
            return Response::deny('Accès non autorisé à cette demande.');
        }

        return DemandeStatutWorkflow::isEditableByDemandeur($demande->statut)
            ? Response::allow()
            : Response::deny('Cette demande ne peut plus être modifiée.');
    }

    /**
     * Seuls les brouillons sont supprimables, par leur auteur.
     */
    public function delete(User $user, Demande $demande): Response
    {
        if (! $this->estAuteur($user, $demande)) {
            return Response::deny('Accès non autorisé à cette demande.');
        }

        return $demande->statut?->code === 'brouillon'
            ? Response::allow()
            : Response::deny('Seuls les brouillons peuvent être supprimés.');
    }

    /**
     * Retirer (annuler) une demande envoyée, par son auteur, tant qu'aucune décision n'est prise.
     */
    public function retirer(User $user, Demande $demande): Response
    {
        if (! $this->estAuteur($user, $demande)) {
            return Response::deny('Accès non autorisé à cette demande.');
        }

        return DemandeStatutWorkflow::peutEtreRetiree($demande->statut)
            ? Response::allow()
            : Response::deny('Seule une demande envoyée et pas encore décidée peut être annulée.');
    }

    /**
     * Ouvrir le dossier côté instruction, en lecture : un admin ou un observateur (toutes les aires protégées),
     * ou un validateur rattaché au site. Un brouillon n'est pas encore soumis : il n'existe pas pour l'équipe (404).
     */
    public function suivre(User $user, Demande $demande): Response
    {
        if ($demande->statut?->code === 'brouillon') {
            return Response::denyAsNotFound();
        }

        if (in_array($user->role, ['admin', 'observateur'], true)) {
            return Response::allow();
        }

        return $user->role === 'validateur' && $user->sites()->whereKey($demande->site_id)->exists()
            ? Response::allow()
            : Response::deny('Accès non autorisé à cette demande.');
    }

    /**
     * Instruire le dossier (décider, préparer les documents, traiter les signalements) : le suivre,
     * sans être observateur. L'observateur suit les dossiers sans jamais décider.
     */
    public function instruire(User $user, Demande $demande): Response
    {
        $acces = $this->suivre($user, $demande);

        if ($acces->denied()) {
            return $acces;
        }

        return $user->role === 'observateur'
            ? Response::deny("Un observateur suit les dossiers sans prendre de décision.")
            : Response::allow();
    }

    /**
     * Rendre une décision : il faut pouvoir instruire le dossier, et jamais sur sa propre demande.
     */
    public function decider(User $user, Demande $demande): Response
    {
        $acces = $this->instruire($user, $demande);

        if ($acces->denied()) {
            return $acces;
        }

        return $this->estAuteur($user, $demande)
            ? Response::deny('Vous ne pouvez pas valider ou modifier le statut de votre propre demande.')
            : Response::allow();
    }

    /**
     * Documents officiels en cours de relecture (avant la signature) : l'équipe qui suit le dossier,
     * sauf son auteur, qui ne les reçoit qu'à la validation.
     */
    public function consulterProjets(User $user, Demande $demande): Response
    {
        $acces = $this->suivre($user, $demande);

        if ($acces->denied()) {
            return $acces;
        }

        return $this->estAuteur($user, $demande)
            ? Response::deny('Vous recevrez les documents de votre demande à sa validation.')
            : Response::allow();
    }

    /**
     * Signaler un problème sur un dossier : quiconque le suit (observateur, validateur du site, admin),
     * sauf son auteur.
     */
    public function signaler(User $user, Demande $demande): Response
    {
        return $this->consulterProjets($user, $demande);
    }

    /**
     * Traiter (clore avec une réponse) un signalement : un validateur du site ou un admin.
     */
    public function traiterSignalement(User $user, Demande $demande): Response
    {
        return $this->instruire($user, $demande);
    }

    /**
     * Télécharger les pièces jointes : l'auteur, ou quelqu'un qui suit le dossier (observateur compris).
     */
    public function consulterFichiers(User $user, Demande $demande): Response
    {
        return $this->estAuteur($user, $demande) || $this->suivre($user, $demande)->allowed()
            ? Response::allow()
            : Response::deny('Accès non autorisé à ce document.');
    }

    /**
     * PDF officiels : les mêmes personnes que pour les pièces jointes, et seulement pour une demande validée.
     */
    public function telechargerPdf(User $user, Demande $demande): Response
    {
        $acces = $this->consulterFichiers($user, $demande);

        if ($acces->denied()) {
            return $acces;
        }

        return $demande->statut?->code === 'validee'
            ? Response::allow()
            : Response::deny('Les documents officiels ne peuvent être générés que si la demande est validée.');
    }

    /**
     * Formulaire de demande rempli (PDF) : les mêmes personnes que pour les pièces jointes,
     * une fois la demande envoyée (l'engagement du demandeur est pris à l'envoi).
     */
    public function telechargerFormulaire(User $user, Demande $demande): Response
    {
        $acces = $this->consulterFichiers($user, $demande);

        if ($acces->denied()) {
            return $acces;
        }

        return $demande->statut?->code !== 'brouillon'
            ? Response::allow()
            : Response::deny('Le formulaire est disponible une fois la demande envoyée.');
    }

    /**
     * Version Word, modifiable, des documents d'une demande : seulement pour qui instruit le dossier sans
     * en être l'auteur. Le demandeur reçoit le PDF, pour ne pas pouvoir retoucher son autorisation.
     */
    public function telechargerWord(User $user, Demande $demande): Response
    {
        return ! $this->estAuteur($user, $demande) && $this->instruire($user, $demande)->allowed()
            ? Response::allow()
            : Response::deny('Le demandeur reçoit les documents en PDF uniquement.');
    }

    private function estAuteur(User $user, Demande $demande): bool
    {
        return (int) $demande->user_id === (int) $user->id;
    }
}
