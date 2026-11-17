<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande de dérogation : une demande dont les dates tombent sur une période bloquante n'est plus refusée,
 * elle est transmise à Fanamby (bouton « Contacter Fanamby : demander une dérogation ») avec la
 * justification du demandeur. Un validateur du site ou un admin l'accepte, propose d'autres dates ou la refuse.
 * - t_demandes.derogation_motif : pourquoi ces dates sont indispensables ; vide hors dérogation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_demandes', function (Blueprint $table) {
            $table->text('derogation_motif')->nullable()->after('clarification');
        });
    }

    public function down(): void
    {
        Schema::table('t_demandes', fn (Blueprint $table) => $table->dropColumn('derogation_motif'));
    }
};
