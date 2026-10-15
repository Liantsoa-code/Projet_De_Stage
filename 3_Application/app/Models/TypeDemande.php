<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeDemande extends Model
{
    protected $fillable = ['libelle'];

    public function demandes()
    {
        return $this->hasMany(Demande::class);
    }

    public function documentsRequis()
    {
        return $this->hasMany(DocumentRequis::class)->orderBy('id');
    }

    public function reglesSpecifiques()
    {
        return $this->hasMany(RegleSpecifique::class)->orderBy('id');
    }

    /**
     * Aligne une liste (pièces ou règles) sur les lignes saisies : les lignes gardent leur id
     * quand on les renomme, les nouvelles sont créées, celles qui ont disparu sont supprimées.
     *
     * @param  array<int, array{id?: int|null, libelle: string}>  $lignes
     */
    public static function syncListe(HasMany $relation, array $lignes): void
    {
        $gardees = collect($lignes)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $relation->clone()->whereNotIn('id', $gardees)->get()->each->delete();

        foreach ($lignes as $ligne) {
            $libelle = trim($ligne['libelle']);
            $existante = empty($ligne['id']) ? null : $relation->clone()->whereKey($ligne['id'])->first();

            $existante ? $existante->update(['libelle' => $libelle]) : $relation->create(['libelle' => $libelle]);
        }
    }
}
