<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['user_id', 'demande_id', 'contenu', 'lu', 'date_envoi'];

    protected $casts = [
        'lu' => 'boolean',
        'date_envoi' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function demande() { return $this->belongsTo(Demande::class); }
}
