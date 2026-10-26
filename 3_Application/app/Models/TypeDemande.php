<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class TypeDemande extends Model
{
    protected $table = 't_type_demandes';

    protected $fillable = ['libelle', 'precision_requise'];

    protected $casts = ['precision_requise' => 'boolean'];

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
     * Les autres champs validés de la ligne (ex. reutilisable pour une pièce) sont enregistrés tels quels.
     *
     * @param  array<int, array{id?: int|null, libelle: string}>  $lignes
     */
    public static function syncListe(HasMany $relation, array $lignes): void
    {
        $gardees = collect($lignes)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $relation->clone()->whereNotIn('id', $gardees)->get()->each->delete();

        foreach ($lignes as $ligne) {
            $valeurs = ['libelle' => trim($ligne['libelle'])] + Arr::except($ligne, ['id', 'libelle']);
            $existante = empty($ligne['id']) ? null : $relation->clone()->whereKey($ligne['id'])->first();

            $existante ? $existante->update($valeurs) : $relation->create($valeurs);
        }
    }
}
