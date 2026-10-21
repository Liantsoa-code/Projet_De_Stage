<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes utilisateurs (avec leur rôle), jetons de réinitialisation du mot de passe et sessions.
 * Toutes les tables de la base portent le préfixe t_ (voir config/auth.php et config/session.php
 * pour les tables utilisées directement par Laravel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['demandeur', 'validateur', 'admin'])->default('demandeur');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('t_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('t_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_sessions');
        Schema::dropIfExists('t_password_reset_tokens');
        Schema::dropIfExists('t_users');
    }
};
