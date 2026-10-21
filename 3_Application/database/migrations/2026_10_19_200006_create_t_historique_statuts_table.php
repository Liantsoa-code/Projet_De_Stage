<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal de chaque changement de statut d'une demande, soumission comprise. L'auteur devient vide
 * si son compte est supprimé : la décision reste dans l'historique du dossier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_historique_statuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('t_demandes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('t_users')->nullOnDelete();
            $table->foreignId('ancien_statut_id')->nullable()->constrained('t_statuts');
            $table->foreignId('nouveau_statut_id')->constrained('t_statuts');
            $table->text('commentaire')->nullable();
            $table->timestamp('date_changement')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_historique_statuts');
    }
};
