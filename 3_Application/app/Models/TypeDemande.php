<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeDemande extends Model
{
    protected $table = 't_type_demandes';

    protected $fillable = ['libelle', 'precision_requise'];

    protected $casts = ['precision_requise' => 'boolean'];

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    public function documentsRequis()
    {
        return $this->hasMany(DocumentRequis::class)->orderBy('id');
    }

    public function reglesSpecifiques()
    {
        return $this->hasMany(RegleSpecifique::class)->orderBy('id');
    }
}
