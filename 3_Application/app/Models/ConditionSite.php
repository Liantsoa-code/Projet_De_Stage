<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Règle permanente d'une aire protégée (ex. « Présence d'un guide local agréé »),
 * proposée dans les conditions particulières de chaque autorisation sur ce site.
 */
class ConditionSite extends Model
{
    protected $table = 't_conditions_site';

    protected $fillable = ['site_id', 'libelle'];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
