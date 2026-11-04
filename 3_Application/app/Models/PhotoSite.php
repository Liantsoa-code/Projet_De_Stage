<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Photo d'une aire protégée, en deux tailles réduites à l'envoi (App\Support\PhotosSite).
 * Les fichiers sont servis par PhotoSiteController (la page d'accueil est publique).
 */
class PhotoSite extends Model
{
    protected $table = 't_photos_site';

    protected $fillable = ['site_id', 'chemin', 'chemin_vignette', 'largeur', 'hauteur', 'legende', 'credit', 'ordre'];

    protected $hidden = ['chemin', 'chemin_vignette'];

    protected $appends = ['url', 'url_vignette'];

    protected static function booted(): void
    {
        // Le nombre de photos de chaque site est dans la liste gardée en cache pour la page d'accueil.
        static::saved(fn () => Cache::forget(Site::CACHE_ACCUEIL));
        static::deleted(fn () => Cache::forget(Site::CACHE_ACCUEIL));
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => route('sites.photos.fichier', [$this->id, 'grande']));
    }

    protected function urlVignette(): Attribute
    {
        return Attribute::get(fn () => route('sites.photos.fichier', [$this->id, 'vignette']));
    }
}
