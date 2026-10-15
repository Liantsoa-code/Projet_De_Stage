<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pièce justificative exigée pour un type de demande (ex. « Plan de vol drone »).
 */
class DocumentRequis extends Model
{
    protected $table = 'documents_requis';

    protected $fillable = ['type_demande_id', 'libelle'];

    public function typeDemande()
    {
        return $this->belongsTo(TypeDemande::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
