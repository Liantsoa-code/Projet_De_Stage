<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Statut;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applique la décision d'un validateur ou d'un admin sur une demande : changement de statut,
 * historique et notifications, ensemble ou pas du tout. Utilisée par la fiche d'instruction
 * et par le traitement d'un signalement transformé en demande de complément.
 */
class DecisionDemande
{
    /**
     * Retourne null si la décision est appliquée, sinon le motif du refus.
     * Verrou sur la demande : de deux décisions simultanées, seule la première s'applique,
     * la seconde est contrôlée sur le statut à jour.
     */
    public static function appliquer(Demande $demande, Statut $nouveauStatut, User $decideur, string $commentaire, ?string $partie = null): ?string
    {
        // Partie à compléter : seulement pour une demande de complément
        $partie = $nouveauStatut->code === 'a_completer' ? $partie : null;

        return DB::transaction(function () use ($demande, $nouveauStatut, $decideur, $commentaire, $partie) {
            $demande = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();
            $demande->load('statut', 'site');

            if (! DemandeStatutWorkflow::canTransition($demande->statut, $nouveauStatut)) {
                return DemandeStatutWorkflow::isTerminal($demande->statut)
                    ? 'Cette demande est clôturée et ne peut plus être modifiée.'
                    : ($nouveauStatut->code === 'a_completer'
                        ? 'Un complément ne peut être demandé que sur un dossier soumis ou en analyse.'
                        : 'Vous ne pouvez pas rétrograder le statut ni le laisser inchangé.');
            }

            // La validation fige les documents relus : ils doivent avoir été générés.
            if ($nouveauStatut->code === 'validee' && ! DocumentsOfficiels::sontGeneres($demande)) {
                return 'Générez et relisez l\'autorisation, le cahier des charges et la convention avant de valider le dossier.';
            }

            // Dossier renvoyé ou refusé : les documents préparés ne servent plus (ils seront regénérés
            // à partir du dossier complété).
            if (in_array($nouveauStatut->code, ['a_completer', 'rejetee'], true)) {
                $demande->documentsOfficiels()->delete();
            }

            $ancienStatutId = $demande->statut_id;

            $demande->update(['statut_id' => $nouveauStatut->id]);

            HistoriqueStatut::create([
                'demande_id' => $demande->id,
                'user_id' => $decideur->id,
                'ancien_statut_id' => $ancienStatutId,
                'nouveau_statut_id' => $nouveauStatut->id,
                'commentaire' => $commentaire,
                'partie' => $partie,
                'date_changement' => now(),
            ]);

            // Notification (cloche + email) du demandeur et de l'équipe du site
            DemandeNotifications::decision($demande, $nouveauStatut, $decideur, $commentaire, $partie);

            return null;
        });
    }

    /**
     * Le décideur peut-il renvoyer ce dossier au demandeur pour complément ?
     */
    public static function complementPossible(Demande $demande): bool
    {
        $aCompleter = Statut::where('code', 'a_completer')->first();

        return $aCompleter !== null && DemandeStatutWorkflow::canTransition($demande->statut, $aCompleter);
    }
}
