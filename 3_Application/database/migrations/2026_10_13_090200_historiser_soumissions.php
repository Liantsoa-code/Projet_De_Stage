<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RG09 : la soumission est un changement de statut comme les autres, elle entre dans l'historique.
 * Rattrapage des demandes déjà soumises avant cette règle : une entrée « → soumise » à la date de
 * soumission, au nom du demandeur. Ainsi demandes.statut_id est toujours le dernier statut historisé
 * (ou « brouillon » s'il n'y a pas encore d'historique).
 */
return new class extends Migration
{
    public function up(): void
    {
        $soumise = DB::table('statuts')->where('code', 'soumise')->value('id');
        $brouillon = DB::table('statuts')->where('code', 'brouillon')->value('id');

        if (! $soumise) {
            return;
        }

        $demandes = DB::table('demandes')
            ->when($brouillon, fn ($q) => $q->where('statut_id', '!=', $brouillon))
            ->whereNotExists(function ($q) use ($soumise) {
                $q->select(DB::raw(1))
                    ->from('historique_statuts')
                    ->whereColumn('historique_statuts.demande_id', 'demandes.id')
                    ->where('historique_statuts.nouveau_statut_id', $soumise);
            })
            ->get(['id', 'user_id', 'date_soumission', 'created_at']);

        foreach ($demandes as $demande) {
            $date = $demande->date_soumission ?? $demande->created_at ?? now();

            DB::table('historique_statuts')->insert([
                'demande_id' => $demande->id,
                'user_id' => $demande->user_id,
                'ancien_statut_id' => null,
                'nouveau_statut_id' => $soumise,
                'commentaire' => null,
                'date_changement' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    public function down(): void
    {
        $soumise = DB::table('statuts')->where('code', 'soumise')->value('id');

        if ($soumise) {
            DB::table('historique_statuts')->where('nouveau_statut_id', $soumise)->delete();
        }
    }
};
