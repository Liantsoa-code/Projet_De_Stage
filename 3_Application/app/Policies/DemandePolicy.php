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
     * Ouvrir le dossier côté validation : un admin, ou un validateur rattaché au site.
     * Un brouillon n'est pas encore soumis : il n'existe pas pour les décideurs (404).
     */
    public function instruire(User $user, Demande $demande): Response
    {
        if ($demande->statut?->code === 'brouillon') {
            return Response::denyAsNotFound();
        }

        if ($user->role === 'admin') {
            return Response::allow();
        }

        return $user->role === 'validateur' && $user->sites()->whereKey($demande->site_id)->exists()
            ? Response::allow()
            : Response::deny('Accès non autorisé à cette demande.');
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
     * Télécharger les pièces jointes : l'auteur, ou quelqu'un qui peut instruire le dossier.
     */
    public function consulterFichiers(User $user, Demande $demande): Response
    {
        return $this->estAuteur($user, $demande) || $this->instruire($user, $demande)->allowed()
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

    private function estAuteur(User $user, Demande $demande): bool
    {
        return (int) $demande->user_id === (int) $user->id;
    }
}
