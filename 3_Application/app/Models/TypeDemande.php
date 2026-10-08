<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDemande extends Model
{
    protected $fillable = ['libelle', 'documents_requis', 'regles_specifiques'];

    protected $casts = [
        'documents_requis' => 'array',
        'regles_specifiques' => 'array',
    ];

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }
}
