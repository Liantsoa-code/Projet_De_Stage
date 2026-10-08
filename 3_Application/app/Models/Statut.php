<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Statut extends Model
{
    protected $fillable = ['code', 'libelle', 'description'];

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }
}
