<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents officiels générés avant la validation (autorisation, cahier des charges) : le validateur
 * relit et modifie leurs textes, puis valide le dossier en signant. Les données du dossier (demandeur,
 * aire protégée, dates, équipe) ne sont pas copiées ici : les PDF les lisent toujours sur la demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_documents_officiels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id');
            $table->string('type', 30);
            $table->json('contenu');
            $table->foreignId('genere_par')->nullable()->constrained('t_users')->nullOnDelete();
            $table->timestamps();

            // Un document de chaque type par demande ; l'index sert aussi à la clé étrangère demande_id.
            $table->unique(['demande_id', 'type']);
            $table->foreign('demande_id')->references('id')->on('t_demandes')->cascadeOnDelete();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE t_documents_officiels ADD CONSTRAINT chk_t_documents_officiels_type CHECK (type IN ('autorisation', 'cahier_charges'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_documents_officiels');
    }
};
