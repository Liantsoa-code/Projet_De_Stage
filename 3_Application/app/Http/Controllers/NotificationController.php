<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

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
            // Sa propre demande → fiche demandeur ; sinon → fiche d'analyse (validateur / admin).
            if ((int) $notification->demande->user_id === (int) auth()->id()) {
                return redirect()->route('demandes.show', $notification->demande_id);
            }

            if (in_array(auth()->user()->role, ['admin', 'validateur'], true)) {
                return redirect()->route('validateur.demandes.show', $notification->demande_id);
            }

            return redirect()->route('demandes.index');
        }

        return back();
    }
}
