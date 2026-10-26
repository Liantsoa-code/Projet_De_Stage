<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Texte modifiable d'un document officiel (autorisation ou cahier des charges), préparé à la
 * génération et figé une fois le dossier validé. Champs : voir App\Support\DocumentsOfficiels.
 */
class DocumentOfficiel extends Model
{
    protected $table = 't_documents_officiels';

    protected $fillable = ['demande_id', 'type', 'contenu', 'genere_par'];

    protected $casts = [
        'contenu' => 'array',
    ];

    public function demande()
    {
        return $this->belongsTo(Demande::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'genere_par');
    }
}
