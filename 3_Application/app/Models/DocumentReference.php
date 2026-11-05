<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Document déposé sur la page Références, partagé entre validateurs et admins.
 * Le fichier est sur le disque privé (storage/app/private/references).
 */
class DocumentReference extends Model
{
    protected $table = 't_documents_reference';

    protected $fillable = ['titre', 'description', 'nom_fichier', 'chemin', 'type_mime', 'taille', 'user_id'];

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
