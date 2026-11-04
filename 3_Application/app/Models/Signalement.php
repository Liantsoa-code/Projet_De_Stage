<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Problème signalé sur un dossier par un observateur, un validateur ou un admin. Interne à l'équipe :
 * le demandeur ne le voit pas. Ouvert tant qu'un validateur du site ou un admin ne l'a pas traité.
 */
class Signalement extends Model
{
    protected $table = 't_signalements';

    /** Parties du dossier qu'un signalement peut viser (CHECK de la table), avec leur libellé. */
    public const PARTIES = [
        'demandeur' => 'Coordonnées du demandeur',
        'projet' => 'Projet',
        'sejour' => 'Dates et équipe',
        'pieces' => 'Pièces jointes',
        'analyse' => 'Analyse automatique',
        'documents' => 'Documents officiels',
        'suivi' => 'Suivi du dossier',
        'autre' => 'Autre',
    ];

    protected $fillable = ['demande_id', 'user_id', 'partie', 'message', 'reponse', 'traite_par', 'traite_le'];

    protected $casts = [
        'traite_le' => 'datetime',
    ];

    public function demande()
    {
        return $this->belongsTo(Demande::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function scopeOuverts(Builder $query): Builder
    {
        return $query->whereNull('traite_le');
    }

    /**
     * Données d'un signalement pour les pages (fiche d'instruction, tableau de bord de l'observateur).
     */
    public function pourPage(): array
    {
        return [
            'id' => $this->id,
            'partie' => $this->partie,
            'partie_libelle' => self::PARTIES[$this->partie] ?? $this->partie,
            'message' => $this->message,
            'auteur' => $this->auteur?->only(['id', 'name', 'role']),
            'created_at' => $this->created_at,
            'ouvert' => $this->traite_le === null,
            'reponse' => $this->reponse,
            'traite_par' => $this->traitePar?->only(['id', 'name', 'role']),
            'traite_le' => $this->traite_le,
        ];
    }
}
