<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arrivée de l'équipe sur le terrain, distincte du début des activités (installation, repérage).
 * Elle sert à la timeline, aux comptes à rebours et à la présence sur le terrain ; l'analyse de la
 * demande porte toujours sur les activités (date_debut → date_fin).
 * Demandes existantes : arrivée = début des activités. CHECK : arrivée au plus tard au début des activités.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_demandes', function (Blueprint $table) {
            $table->date('date_arrivee')->nullable()->after('objectifs');
        });

        DB::table('t_demandes')->whereNotNull('date_debut')->update(['date_arrivee' => DB::raw('date_debut')]);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE t_demandes ADD CONSTRAINT chk_t_demandes_arrivee CHECK (date_arrivee <= date_debut)');
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE t_demandes DROP CHECK chk_t_demandes_arrivee');
        }

        Schema::table('t_demandes', fn (Blueprint $table) => $table->dropColumn('date_arrivee'));
    }
};
