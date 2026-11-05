<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôle « observateur » : suit tous les dossiers en lecture seule, sans jamais décider, et signale
 * les problèmes. Les signalements (t_signalements) sont internes à l'équipe : le demandeur ne les voit pas.
 * - un signalement porte sur une partie du dossier (CHECK), avec un message ;
 * - il est traité par un validateur du site ou un admin (réponse, auteur, date) ; ouvert tant que traite_le est vide ;
 * - l'auteur ou celui qui l'a traité peut supprimer son compte : le signalement reste, sans nom (SET NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_users', function (Blueprint $table) {
            $table->enum('role', ['demandeur', 'validateur', 'admin', 'observateur'])->default('demandeur')->change();
        });

        Schema::create('t_signalements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('t_demandes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('t_users')->nullOnDelete();
            $table->string('partie', 20);
            $table->text('message');
            $table->text('reponse')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('t_users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();

            // Signalements ouverts d'un dossier (bandeau, compteurs)
            $table->index(['demande_id', 'traite_le']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE t_signalements ADD CONSTRAINT chk_t_signalements_partie CHECK (partie IN ('demandeur', 'projet', 'sejour', 'pieces', 'analyse', 'documents', 'suivi', 'autre'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_signalements');

        // Les observateurs redeviennent demandeurs avant de retirer le rôle
        DB::table('t_users')->where('role', 'observateur')->update(['role' => 'demandeur']);
        Schema::table('t_users', function (Blueprint $table) {
            $table->enum('role', ['demandeur', 'validateur', 'admin'])->default('demandeur')->change();
        });
    }
};
