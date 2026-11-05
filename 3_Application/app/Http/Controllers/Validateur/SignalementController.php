<?php

namespace App\Http\Controllers\Validateur;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Signalement;
use App\Support\DemandeNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Signalements internes d'un dossier : un observateur, un validateur ou un admin signale un problème ;
 * un validateur du site ou un admin le traite avec une réponse. Droits dans DemandePolicy (signaler,
 * traiterSignalement) ; le demandeur ne voit jamais les signalements.
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

        return back()->with('message', 'Signalement envoyé aux validateurs de l\'aire protégée et aux administrateurs.');
    }

    public function traiter(Request $request, Demande $demande, Signalement $signalement)
    {
        Gate::authorize('traiterSignalement', $demande);

        if ($signalement->traite_le) {
            return back()->with('error', 'Ce signalement a déjà été traité.');
        }

        $validated = $request->validate(['reponse' => 'nullable|string|max:2000']);

        $signalement->update([
            'reponse' => trim($validated['reponse'] ?? '') ?: null,
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
        ]);
        DemandeNotifications::signalementTraite($signalement);

        return back()->with('message', 'Signalement marqué comme traité.');
    }
}
