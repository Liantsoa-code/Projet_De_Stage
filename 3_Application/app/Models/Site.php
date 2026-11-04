<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Site extends Model
{
    protected $table = 't_sites';

    /** Clé de cache de la liste des sites affichée sur la page d'accueil. */
    public const CACHE_ACCUEIL = 'sites.accueil';

    protected $fillable = ['nom', 'description', 'capacite_accueil', 'lien_photos', 'polygone_coordonnees'];

    protected $casts = [
        'polygone_coordonnees' => 'array',
        'capacite_accueil' => 'integer',
    ];

    protected static function booted(): void
    {
        // Créer, modifier ou supprimer un site vide la liste mise en cache pour la page d'accueil.
        static::saved(fn () => Cache::forget(self::CACHE_ACCUEIL));
        static::deleted(fn () => Cache::forget(self::CACHE_ACCUEIL));
    }

    /**
     * Sites de la page d'accueil publique, avec leur contour et de quoi proposer leurs photos. Ils changent
     * rarement : la liste est gardée en cache une journée (tableau simple, le cache n'accepte pas d'objets).
     */
    public static function pourAccueil(): array
    {
        return Cache::remember(self::CACHE_ACCUEIL, now()->addDay(), fn () => self::select(['id', 'nom', 'description', 'polygone_coordonnees', 'lien_photos'])
            ->withCount('photos as nb_photos')
            ->orderBy('nom')
            ->get()
            ->toArray());
    }

    public function validateurs()
    {
        return $this->belongsToMany(User::class, 't_site_user');
    }

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    public function periodeSensibles()
    {
        return $this->hasMany(PeriodeSensible::class);
    }

    /** Règles permanentes de l'aire protégée, reprises par l'analyse des demandes. */
    public function conditions()
    {
        return $this->hasMany(ConditionSite::class)->orderBy('id');
    }

    /** Photos de la galerie « Voir les photos », dans l'ordre choisi par l'admin. */
    public function photos()
    {
        return $this->hasMany(PhotoSite::class);
    }
}
