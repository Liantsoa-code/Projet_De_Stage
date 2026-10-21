<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle de document officiel (page Références), géré par les admins.
 * Le contenu est mis en forme par App\Support\ModelesReference.
 */
class ModeleReference extends Model
{
    protected $table = 't_modeles_reference';

    protected $fillable = ['titre', 'description', 'usage', 'icone', 'automatique', 'contenu'];

    protected $casts = [
        'automatique' => 'boolean',
    ];
}
