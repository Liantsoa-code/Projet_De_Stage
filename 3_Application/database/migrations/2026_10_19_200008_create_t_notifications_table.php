<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications internes (cloche). L'index (user_id, lu, date_envoi) sert la requête lue à chaque
 * page — non lues d'un utilisateur, triées par date — et la clé étrangère user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('demande_id')->nullable()->constrained('t_demandes')->cascadeOnDelete();
            $table->text('contenu');
            $table->boolean('lu')->default(false);
            $table->timestamp('date_envoi')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'lu', 'date_envoi'], 't_notifications_user_lu_envoi_index');
            $table->foreign('user_id')->references('id')->on('t_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_notifications');
    }
};
