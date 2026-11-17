<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function markAsRead(Notification $notification)
    {
        // Sécurité : l'utilisateur ne peut marquer que ses propres notifications
        if ((int) $notification->user_id !== (int) auth()->id()) {
            return back();
        }

        $notification->update(['lu' => true]);

        if ($notification->demande) {
            // Sa propre demande → fiche demandeur ; sinon → fiche d'analyse (validateur, admin, observateur).
            // Complément ou signalement visant une partie : directement à cette étape du formulaire,
            // tant que le demandeur peut encore modifier son dossier
            if ((int) $notification->demande->user_id === (int) auth()->id()) {
                return $notification->etape && Gate::allows('update', $notification->demande)
                    ? redirect()->route('demandes.edit', ['demande' => $notification->demande_id, 'etape' => $notification->etape])
                    : redirect()->route('demandes.show', $notification->demande_id);
            }

            if (in_array(auth()->user()->role, ['admin', 'validateur', 'observateur'], true)) {
                return redirect()->route('validateur.demandes.show', $notification->demande_id);
            }

            return redirect()->route('demandes.index');
        }

        return back();
    }

    public function markAllAsRead()
    {
        // Seules les notifications de l'utilisateur connecté sont concernées
        Notification::where('user_id', auth()->id())
            ->where('lu', false)
            ->update(['lu' => true]);

        return back();
    }
}
