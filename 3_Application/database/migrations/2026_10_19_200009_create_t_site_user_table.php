<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Association validateur ↔ aires protégées qu'il instruit (table de liaison, clé composée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_site_user', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('site_id')->constrained('t_sites')->cascadeOnDelete();

            // La clé primaire sert aussi à la clé étrangère user_id (en tête).
            $table->primary(['user_id', 'site_id']);
            $table->foreign('user_id')->references('id')->on('t_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_site_user');
    }
};
