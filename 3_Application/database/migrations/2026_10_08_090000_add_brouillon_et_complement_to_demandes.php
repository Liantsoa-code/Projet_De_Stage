<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brouillons : les dates ne sont obligatoires qu'à la soumission.
     * Compléments : nouveau statut « À compléter » (dossier renvoyé au demandeur).
     */
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->date('date_debut')->nullable()->change();
            $table->date('date_fin')->nullable()->change();
        });

        $statuts = [
            ['code' => 'brouillon', 'libelle' => 'Brouillon', 'description' => 'Demande en cours de rédaction'],
            ['code' => 'a_completer', 'libelle' => 'À compléter', 'description' => 'Des compléments sont demandés au demandeur'],
        ];

        foreach ($statuts as $statut) {
            if (! DB::table('statuts')->where('code', $statut['code'])->exists()) {
                DB::table('statuts')->insert($statut + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('statuts')
            ->where('code', 'a_completer')
            ->whereNotExists(fn ($q) => $q->from('demandes')->whereColumn('demandes.statut_id', 'statuts.id'))
            ->delete();

        Schema::table('demandes', function (Blueprint $table) {
            $table->date('date_debut')->nullable(false)->change();
            $table->date('date_fin')->nullable(false)->change();
        });
    }
};
