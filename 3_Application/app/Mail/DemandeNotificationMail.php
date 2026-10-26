<?php

namespace App\Mail;

use App\Models\Demande;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Copie par email d'une notification interne (cloche). Mise en file d'attente et envoyée
 * seulement après la validation de la transaction, pour ne jamais annoncer une décision annulée.
 */
class DemandeNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $lien;

    public function __construct(public Demande $demande, public User $destinataire, public string $contenu)
    {
        $this->afterCommit();

        // Le demandeur ouvre son dossier, l'équipe ouvre la fiche d'instruction
        $this->lien = (int) $destinataire->id === (int) $demande->user_id
            ? route('demandes.show', $demande)
            : route('validateur.demandes.show', $demande);
    }

    public function envelope(): Envelope
    {
        $this->demande->loadMissing('site');

        return new Envelope(
            subject: "Dossier #{$this->demande->id} – {$this->demande->site->nom}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.demande-notification');
    }
}
