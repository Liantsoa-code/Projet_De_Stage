<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces jointes d'une demande. document_requis_id relie la pièce à la pièce exigée qu'elle fournit
 * (vide si cette pièce a été retirée du type depuis) ; type_document garde le libellé du jour du dépôt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('t_demandes')->cascadeOnDelete();
            $table->foreignId('document_requis_id')->nullable()->constrained('t_documents_requis')->nullOnDelete();
            $table->string('nom_fichier', 255);
            $table->string('chemin', 255);
            $table->string('type_document', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_documents');
    }
};
