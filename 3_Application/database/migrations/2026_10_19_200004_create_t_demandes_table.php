<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes d'accès : section II du formulaire (titre, type de projet et sa précision, résumé du
 * synopsis dans description, objectifs, aire protégée, période). Les champs du projet et les dates
 * sont facultatifs tant que la demande est un brouillon ;
 * statut_id est toujours le dernier statut historisé (ou « brouillon » sans historique).
 * - RESTRICT : un site, un type ou un statut encore utilisé ne peut pas être supprimé ;
 * - CHECK : fin après début, équipe d'au moins une personne (un NULL les satisfait : brouillon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('t_users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('t_sites')->restrictOnDelete();
            $table->foreignId('type_demande_id')->constrained('t_type_demandes')->restrictOnDelete();
            $table->string('titre', 200)->nullable();
            $table->string('type_precision', 150)->nullable(); // « Autre (précisez) »
            $table->text('description')->nullable(); // résumé du synopsis
            $table->text('objectifs')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->integer('equipe_nombre')->default(1);
            $table->foreignId('statut_id')->constrained('t_statuts')->restrictOnDelete();
            $table->timestamp('date_soumission')->nullable();
            $table->json('resultats_analyse')->nullable();
            $table->timestamps();
        });

        // Les CHECK sont appliqués par MySQL (8.0.16+) et MariaDB (10.2+).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE t_demandes ADD CONSTRAINT chk_t_demandes_dates CHECK (date_fin >= date_debut)');
            DB::statement('ALTER TABLE t_demandes ADD CONSTRAINT chk_t_demandes_equipe CHECK (equipe_nombre >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_demandes');
    }
};
