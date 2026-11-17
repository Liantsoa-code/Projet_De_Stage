<?php

namespace App\Http\Controllers\Validateur;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Signalement;
use App\Models\Statut;
use App\Support\DecisionDemande;
use App\Support\DemandeNotifications;
use App\Support\PartieDossier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Signalements internes d'un dossier : un observateur, un validateur ou un admin signale un problème ;
 * un validateur du site ou un admin le traite avec une réponse. Droits dans DemandePolicy (signaler,
 * traiterSignalement). Le demandeur est prévenu de chaque signalement (sans le nom de son auteur).
 * Un décideur peut traiter le signalement en renvoyant le dossier au demandeur pour complément :
 * le dossier passe « à compléter » et le demandeur peut corriger la partie signalée.
 */
class SignalementController extends Controller
{
    public function store(Request $request, Demande $demande)
    {
        Gate::authorize('signaler', $demande);

        $validated = $request->validate([
            'partie' => ['required', Rule::in(array_keys(Signalement::PARTIES))],
            'message' => 'required|string|max:2000',
        ], [
            'message.required' => 'Décrivez le problème constaté.',
        ]);

        $signalement = $demande->signalements()->create($validated + ['user_id' => $request->user()->id]);
        DemandeNotifications::signalement($signalement);

        return back()->with('message', 'Signalement envoyé aux validateurs de l\'aire protégée, aux administrateurs et au demandeur.');
    }

    public function traiter(Request $request, Demande $demande, Signalement $signalement)
    {
        Gate::authorize('traiterSignalement', $demande);

        $complement = $request->boolean('demander_complement');
        if ($complement) {
            // Renvoyer le dossier est une décision : jamais sur sa propre demande
            Gate::authorize('decider', $demande);
        }

        $validated = $request->validate([
            'reponse' => 'nullable|string|max:2000',
            'partie' => [Rule::requiredIf($complement), 'nullable', Rule::in(array_keys(PartieDossier::COMPLEMENT))],
        ], [
            'partie.required' => 'Indiquez la partie du dossier à compléter.',
        ]);
        $reponse = trim($validated['reponse'] ?? '');

        // Verrou sur le signalement : traité une seule fois, et le dossier renvoyé avec lui ou pas du tout
        $erreur = DB::transaction(function () use ($request, $demande, $signalement, $complement, $validated, $reponse) {
            $signalement = Signalement::whereKey($signalement->id)->lockForUpdate()->firstOrFail();

            if ($signalement->traite_le) {
                return 'Ce signalement a déjà été traité.';
            }

            if ($complement) {
                $partie = $validated['partie'];
                // Le demandeur lit la réponse du validateur, à défaut le problème signalé
                $erreur = DecisionDemande::appliquer(
                    $demande,
                    Statut::where('code', 'a_completer')->firstOrFail(),
                    $request->user(),
                    $reponse !== '' ? $reponse : $signalement->message,
                    $partie,
                );
                if ($erreur !== null) {
                    return $erreur;
                }

                $reponse = 'Complément demandé au demandeur (' . PartieDossier::libelleComplement($partie) . ')'
                    . ($reponse !== '' ? " : {$reponse}" : '.');
            }

            $signalement->update([
                'reponse' => $reponse !== '' ? $reponse : null,
                'traite_par' => $request->user()->id,
                'traite_le' => now(),
            ]);
            DemandeNotifications::signalementTraite($signalement);

            return null;
        });

        if ($erreur !== null) {
            return back()->with('error', $erreur);
        }

        return back()->with('message', $complement
            ? 'Signalement traité : le dossier est renvoyé au demandeur, qui peut maintenant corriger la partie concernée.'
            : 'Signalement marqué comme traité.');
    }
}
