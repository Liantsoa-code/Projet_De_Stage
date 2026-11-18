<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeSensible extends Model
{
    protected $table = 't_periode_sensibles';

    protected $fillable = ['site_id', 'date_debut', 'date_fin', 'description', 'niveau_restriction', 'condition_proposee'];

    protected $casts = [
        // AAAA-MM-JJ, comme les dates des demandes (voir Demande::$casts)
        'date_debut' => 'date:Y-m-d',
        'date_fin' => 'date:Y-m-d',
    ];

    public function site() { return $this->belongsTo(Site::class); }
}
