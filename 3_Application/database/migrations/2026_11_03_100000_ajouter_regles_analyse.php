<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Données des règles du moteur d'analyse des demandes (App\Support\AnalyseDemande) :
 * - t_sites.capacite_accueil : nombre maximal de personnes présentes en même temps sur l'aire protégée
 *   (vide : pas de limite) ;
 * - t_periode_sensibles.condition_proposee : condition proposée quand un séjour chevauche une période
 *   d'avertissement (vide : condition générale) ;
 * - t_conditions_site : règles permanentes de l'aire protégée (1NF : une ligne par règle), proposées
 *   dans les conditions particulières de chaque autorisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_sites', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacite_accueil')->nullable()->after('description');
        });

        Schema::table('t_periode_sensibles', function (Blueprint $table) {
            $table->string('condition_proposee', 255)->nullable()->after('niveau_restriction');
        });

        Schema::create('t_conditions_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('t_sites')->cascadeOnDelete();
            $table->string('libelle', 255);
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE t_sites ADD CONSTRAINT chk_t_sites_capacite CHECK (capacite_accueil >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_conditions_site');

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE t_sites DROP CHECK chk_t_sites_capacite');
        }

        Schema::table('t_periode_sensibles', fn (Blueprint $table) => $table->dropColumn('condition_proposee'));
        Schema::table('t_sites', fn (Blueprint $table) => $table->dropColumn('capacite_accueil'));
    }
};
