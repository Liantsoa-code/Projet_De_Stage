<?php

namespace App\Models;

use App\Support\AnalyseDemande;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Demande extends Model
{
    protected $table = 't_demandes';

    protected $fillable = [
        'user_id', 'site_id', 'type_demande_id', 'titre', 'type_precision',
        'description', 'objectifs', 'clarification', 'date_arrivee', 'date_debut', 'date_fin', 'equipe_nombre',
        'statut_id', 'date_soumission', 'resultats_analyse', 'derogation_motif',
    ];

    /**
     * Section V du formulaire : engagements que le demandeur accepte à chaque envoi du dossier.
     * Repris dans le formulaire, son PDF et le cahier des charges.
     */
    public const ENGAGEMENTS = [
        'Respecter les règles de conduite dans les aires protégées',
        'Ne pas perturber l\'écosystème (faune et flore) et respecter les règles et coutumes des communautés locales.',
        'Ne pas diffuser d\'informations ou images sensibles sans accord préalable de Fanamby',
        'Remettre à Fanamby une copie du produit final',
    ];

    /** Durée maximale d'une demande, de l'arrivée sur le terrain (à défaut du début des activités) à la fin. */
    public const DUREE_MAX_MOIS = 6;

    /** Coordonnées du demandeur (sections I et V), enregistrées sur son compte. */
    public const COORDONNEES = ['organisation', 'fonction', 'adresse', 'telephone', 'piece_identite'];

    protected $casts = [
        'date_arrivee' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_soumission' => 'datetime',
        'resultats_analyse' => 'array',
    ];

    /**
     * Arrivée de l'équipe sur le terrain, ou début des activités si elle n'est pas renseignée (brouillon).
     * Les activités (date_debut → date_fin) restent la période analysée et autorisée.
     */
    public function arrivee(): ?Carbon
    {
        return $this->date_arrivee ?? $this->date_debut;
    }

    /** La référence est envoyée au front avec chaque demande. */
    protected $appends = ['reference', 'derogation'];

    /**
     * Référence officielle FNB-AAAA-NNNN (année de création), seule source pour les pages et les PDF.
     * Vide si la demande est chargée sans son identifiant ou sa date de création.
     */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => $this->id && $this->created_at
            ? sprintf('FNB-%s-%04d', $this->created_at->format('Y'), $this->id)
            : null);
    }

    /**
     * Type de projet tel qu'affiché : « Autre (Photographie commerciale) » quand le demandeur l'a précisé.
     */
    public function typeProjet(): string
    {
        $libelle = (string) $this->typeDemande?->libelle;

        return $this->type_precision ? "{$libelle} ({$this->type_precision})" : $libelle;
    }

    public function user() { return $this->belongsTo(User::class); }
    public function site() { return $this->belongsTo(Site::class); }
    public function typeDemande() { return $this->belongsTo(TypeDemande::class); }
    public function statut() { return $this->belongsTo(Statut::class); }
    public function documents() { return $this->hasMany(Document::class); }
    public function historiqueStatuts() { return $this->hasMany(HistoriqueStatut::class); }
    public function documentsOfficiels() { return $this->hasMany(DocumentOfficiel::class); }
    public function signalements() { return $this->hasMany(Signalement::class); }

    /**
     * Demande de complément en cours (dernier passage « à compléter »), tant que le dossier n'est pas renvoyé.
     */
    public function complementEnCours(): ?HistoriqueStatut
    {
        if ($this->statut?->code !== 'a_completer') {
            return null;
        }

        return $this->historiqueStatuts()
            ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
            ->latest('date_changement')
            ->latest('id')
            ->first();
    }

    /**
     * Demande de dérogation : l'analyse figée à l'envoi relève une période bloquante. Le dossier a été
     * transmis avec la justification du demandeur (derogation_motif) ; Fanamby accepte ou non.
     */
    public function getDerogationAttribute(): bool
    {
        return collect($this->resultats_analyse ?? [])
            ->contains(fn ($c) => is_array($c) && AnalyseDemande::niveau($c) === 'bloquant');
    }

    /**
     * Le validateur attend-il une clarification, à saisir dans « Informations sur la demande » ?
     */
    public function clarificationDemandee(): bool
    {
        return $this->complementEnCours()?->partie === 'clarification';
    }

    /**
     * Exclut les brouillons : ils ne sont visibles que par leur auteur.
     */
    public function scopeHorsBrouillon(Builder $query): Builder
    {
        return $query->whereHas('statut', fn ($q) => $q->where('code', '!=', 'brouillon'));
    }

    /**
     * Limite les statistiques tableau de bord : admin et observateur = tout, validateur = ses sites uniquement.
     */
    public function scopeForStatsViewer(Builder $query, User $viewer): Builder
    {
        if (in_array($viewer->role, ['admin', 'observateur'], true)) {
            return $query;
        }

        if ($viewer->role !== 'validateur') {
            return $query->whereRaw('0 = 1');
        }

        $siteIds = $viewer->sites()->pluck('t_sites.id');

        if ($siteIds->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('t_demandes.site_id', $siteIds);
    }
}
