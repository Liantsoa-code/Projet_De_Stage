<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = ['nom', 'description', 'polygone_coordonnees'];

    protected $casts = [
        'polygone_coordonnees' => 'array',
    ];

    public function validateurs()
    {
        return $this->belongsToMany(User::class);
    }

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    public function periodeSensibles()
    {
        return $this->hasMany(PeriodeSensible::class);
    }
}
