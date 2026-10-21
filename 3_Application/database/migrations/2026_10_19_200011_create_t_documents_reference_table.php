<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents déposés par les validateurs et les admins sur la page Références (avis signés,
 * courriers types, notes internes…), partagés avec toute l'équipe. Le fichier est stocké
 * hors du dossier public ; si le compte de l'auteur est supprimé, le document reste, sans auteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_documents_reference', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('nom_fichier');
            $table->string('chemin');
            $table->string('type_mime', 100);
            $table->unsignedInteger('taille'); // en octets
            $table->foreignId('user_id')->nullable()->constrained('t_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_documents_reference');
    }
};
