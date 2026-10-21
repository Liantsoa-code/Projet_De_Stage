<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aires protégées (« site » dans le code) : nom, description et contour en coordonnées GPS
 * (JSON [{lat, lng}, …], lu d'un bloc par la carte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_sites', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->json('polygone_coordonnees')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_sites');
    }
};
