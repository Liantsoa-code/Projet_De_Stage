<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = ['demande_id', 'nom_fichier', 'chemin', 'type_document'];

    public function demande()
    {
        return $this->belongsTo(Demande::class);
    }
}
