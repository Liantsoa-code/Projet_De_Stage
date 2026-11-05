<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pièce justificative exigée pour un type de demande (ex. « Plan de vol drone »).
 * Une pièce réutilisable (pièce d'identité, assurance…) déjà déposée par le demandeur
 * est reprise dans ses demandes suivantes (DemandeDossier::piecesReutilisables).
 */
class DocumentRequis extends Model
{
    protected $table = 't_documents_requis';

    protected $fillable = ['type_demande_id', 'libelle', 'reutilisable'];

    protected $casts = [
        'reutilisable' => 'boolean',
    ];

    public function typeDemande()
    {
        return $this->belongsTo(TypeDemande::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
