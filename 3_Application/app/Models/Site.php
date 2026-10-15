<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Site extends Model
{
    /** Clé de cache de la liste des sites affichée sur la page d'accueil. */
    public const CACHE_ACCUEIL = 'sites.accueil';

    protected $fillable = ['nom', 'description', 'polygone_coordonnees'];

    protected $casts = [
        'polygone_coordonnees' => 'array',
    ];

    protected static function booted(): void
    {
        // Créer, modifier ou supprimer un site vide la liste mise en cache pour la page d'accueil.
        static::saved(fn () => Cache::forget(self::CACHE_ACCUEIL));
        static::deleted(fn () => Cache::forget(self::CACHE_ACCUEIL));
    }

    /**
     * Sites de la page d'accueil publique, avec leur contour. Ils changent rarement :
     * la liste est gardée en cache une journée (tableau simple, le cache n'accepte pas d'objets).
     */
    public static function pourAccueil(): array
    {
        return Cache::remember(self::CACHE_ACCUEIL, now()->addDay(), fn () => self::orderBy('nom')
            ->get(['id', 'nom', 'description', 'polygone_coordonnees'])
            ->toArray());
    }

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
