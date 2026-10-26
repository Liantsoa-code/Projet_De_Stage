<?php

namespace App\Policies;

use App\Models\DocumentReference;
use App\Models\User;

/**
 * Documents de la page Références. La consultation et le dépôt sont ouverts à tous les
 * validateurs et admins (gate is-validateur sur les routes) ; la suppression est réservée
 * à l'auteur du dépôt et aux admins.
 */
class DocumentReferencePolicy
{
    public function delete(User $user, DocumentReference $document): bool
    {
        return $user->role === 'admin' || $document->user_id === $user->id;
    }
}
