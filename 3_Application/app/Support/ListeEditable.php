<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Enregistrement d'une liste saisie avec le composant ListeEditable.vue (pièces et règles d'un type de projet,
 * règles permanentes d'une aire protégée) : une ligne par élément (1NF).
 */
class ListeEditable
{
    /**
     * Aligne une liste sur les lignes saisies : les lignes gardent leur id quand on les renomme,
     * les nouvelles sont créées, celles qui ont disparu sont supprimées.
     * Les autres champs validés de la ligne (ex. reutilisable pour une pièce) sont enregistrés tels quels.
     *
     * @param  array<int, array{id?: int|null, libelle: string}>  $lignes
     */
    public static function synchroniser(HasMany $relation, array $lignes): void
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
