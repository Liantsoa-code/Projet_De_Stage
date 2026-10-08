<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoriqueStatut extends Model
{
    protected $fillable = ['demande_id', 'user_id', 'ancien_statut_id', 'nouveau_statut_id', 'commentaire', 'date_changement'];

    protected $casts = [
        'date_changement' => 'datetime',
    ];

    public function demande() { return $this->belongsTo(Demande::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function ancienStatut() { return $this->belongsTo(Statut::class, 'ancien_statut_id'); }
    public function nouveauStatut() { return $this->belongsTo(Statut::class, 'nouveau_statut_id'); }
}
