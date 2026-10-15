<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Règle à respecter pour un type de demande, reprise dans le cahier des charges.
 */
class RegleSpecifique extends Model
{
    protected $table = 'regles_specifiques';

    protected $fillable = ['type_demande_id', 'libelle'];

    public function typeDemande()
    {
        return $this->belongsTo(TypeDemande::class);
    }
}
