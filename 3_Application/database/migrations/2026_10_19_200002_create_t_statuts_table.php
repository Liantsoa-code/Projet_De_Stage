<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des statuts du workflow (App\Support\DemandeStatutWorkflow). Les six statuts sont
 * insérés ici : l'application ne fonctionne pas sans eux, le code les retrouve par leur code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_statuts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $maintenant = now();

        DB::table('t_statuts')->insert(array_map(fn (array $statut) => $statut + [
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ], [
            ['code' => 'brouillon', 'libelle' => 'Brouillon', 'description' => 'Demande en cours de rédaction'],
            ['code' => 'soumise', 'libelle' => 'Soumise', 'description' => 'Demande envoyée pour validation'],
            ['code' => 'en_analyse', 'libelle' => 'En cours d\'analyse', 'description' => 'Le validateur étudie la demande'],
            ['code' => 'validee', 'libelle' => 'Validée', 'description' => 'Demande acceptée'],
            ['code' => 'rejetee', 'libelle' => 'Rejetée', 'description' => 'Demande refusée'],
            ['code' => 'a_completer', 'libelle' => 'À compléter', 'description' => 'Des compléments sont demandés au demandeur'],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('t_statuts');
    }
};
