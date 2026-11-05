<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache de l'application (CACHE_STORE=database) et verrous associés — voir config/cache.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('t_cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_cache_locks');
        Schema::dropIfExists('t_cache');
    }
};
