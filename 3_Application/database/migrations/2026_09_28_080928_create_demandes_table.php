<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('type_demande_id')->constrained('type_demandes')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('equipe_nombre')->default(1);
            $table->foreignId('statut_id')->constrained('statuts')->onDelete('restrict');
            $table->timestamp('date_soumission')->nullable();
            $table->json('resultats_analyse')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
