<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Périodes de restriction d'un site : « bloquant » refuse la soumission, « avertissement » la signale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_periode_sensibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('t_sites')->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('description', 255);
            $table->enum('niveau_restriction', ['bloquant', 'avertissement']);
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE t_periode_sensibles ADD CONSTRAINT chk_t_periode_sensibles_dates CHECK (date_fin >= date_debut)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_periode_sensibles');
    }
};
