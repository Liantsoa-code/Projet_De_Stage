<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Types d'activité et leurs deux listes (1NF : une ligne par élément, pas de liste JSON) :
 * - t_documents_requis : pièces à joindre, libellé unique par type ; une pièce « réutilisable »
 *   (pièce d'identité, assurance…) déjà déposée par le demandeur est reprise dans ses demandes suivantes ;
 * - t_regles_specifiques : règles reprises dans le cahier des charges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_type_demandes', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100);
            $table->timestamps();
        });

        Schema::create('t_documents_requis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_demande_id');
            $table->string('libelle', 150);
            $table->boolean('reutilisable')->default(false);
            $table->timestamps();

            // L'index unique sert aussi à la clé étrangère (type_demande_id en tête).
            $table->unique(['type_demande_id', 'libelle']);
            $table->foreign('type_demande_id')->references('id')->on('t_type_demandes')->cascadeOnDelete();
        });

        Schema::create('t_regles_specifiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_demande_id')->constrained('t_type_demandes')->cascadeOnDelete();
            $table->string('libelle', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_regles_specifiques');
        Schema::dropIfExists('t_documents_requis');
        Schema::dropIfExists('t_type_demandes');
    }
};
