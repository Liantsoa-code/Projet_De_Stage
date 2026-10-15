<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique : la suppression du compte d'un validateur ou d'un admin ne doit pas être
     * bloquée par ses décisions passées. Elles sont conservées, sans auteur.
     */
    public function up(): void
    {
        Schema::table('historique_statuts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('historique_statuts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('historique_statuts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Échoue si des décisions de comptes supprimés existent encore (user_id nul).
        Schema::table('historique_statuts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
