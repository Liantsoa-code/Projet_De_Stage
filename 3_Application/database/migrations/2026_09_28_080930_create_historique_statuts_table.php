<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historique_statuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('ancien_statut_id')->nullable()->constrained('statuts');
            $table->foreignId('nouveau_statut_id')->constrained('statuts');
            $table->text('commentaire')->nullable();
            $table->timestamp('date_changement')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_statuts');
    }
};
