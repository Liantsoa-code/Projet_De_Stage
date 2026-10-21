<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeSensible extends Model
{
    protected $table = 't_periode_sensibles';

    protected $fillable = ['site_id', 'date_debut', 'date_fin', 'description', 'niveau_restriction'];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function site() { return $this->belongsTo(Site::class); }
}
