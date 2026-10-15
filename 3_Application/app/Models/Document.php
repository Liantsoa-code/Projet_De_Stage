<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    // type_document : libellé de la pièce requise, figé au moment du dépôt (il ne suit pas un renommage).
    protected $fillable = ['demande_id', 'document_requis_id', 'nom_fichier', 'chemin', 'type_document'];

    public function demande()
    {
        return $this->belongsTo(Demande::class);
    }

    public function documentRequis()
    {
        return $this->belongsTo(DocumentRequis::class);
    }
}
