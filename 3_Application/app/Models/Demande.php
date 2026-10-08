<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Demande extends Model
{
    protected $fillable = [
        'user_id', 'site_id', 'type_demande_id', 'description', 
        'date_debut', 'date_fin', 'equipe_nombre', 'statut_id', 
        'date_soumission', 'resultats_analyse'
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_soumission' => 'datetime',
        'resultats_analyse' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function site() { return $this->belongsTo(Site::class); }
    public function typeDemande() { return $this->belongsTo(TypeDemande::class); }
    public function statut() { return $this->belongsTo(Statut::class); }
    public function documents() { return $this->hasMany(Document::class); }
    public function historiqueStatuts() { return $this->hasMany(HistoriqueStatut::class); }

    /**
     * Exclut les brouillons : ils ne sont visibles que par leur auteur.
     */
    public function scopeHorsBrouillon(Builder $query): Builder
    {
        return $query->whereHas('statut', fn ($q) => $q->where('code', '!=', 'brouillon'));
    }

    /**
     * Limite les statistiques tableau de bord : admin = tout, validateur = ses sites uniquement.
     */
    public function scopeForStatsViewer(Builder $query, User $viewer): Builder
    {
        if ($viewer->role === 'admin') {
            return $query;
        }

        if ($viewer->role !== 'validateur') {
            return $query->whereRaw('0 = 1');
        }

        $siteIds = $viewer->sites()->pluck('sites.id');

        if ($siteIds->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('demandes.site_id', $siteIds);
    }
}
