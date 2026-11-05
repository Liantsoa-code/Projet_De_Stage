<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos des aires protégées :
 * - t_sites.lien_photos : banque d'images complète, hébergée ailleurs (album de Fanamby) ;
 * - t_photos_site : quelques photos déposées par les admins, réduites à l'envoi (grande image et vignette,
 *   App\Support\PhotosSite) ; supprimées avec leur aire protégée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_sites', function (Blueprint $table) {
            $table->string('lien_photos', 500)->nullable()->after('capacite_accueil');
        });

        Schema::create('t_photos_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('t_sites')->cascadeOnDelete();
            $table->string('chemin', 255);          // grande image (1600 px au plus)
            $table->string('chemin_vignette', 255); // vignette de la galerie (480 px au plus)
            $table->unsignedSmallInteger('largeur');
            $table->unsignedSmallInteger('hauteur');
            $table->string('legende', 255)->nullable();
            $table->string('credit', 150)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        // La liste des sites de la page d'accueil, gardée en cache, doit reprendre le nombre de photos et le lien
        Cache::forget(Site::CACHE_ACCUEIL);
    }

    public function down(): void
    {
        Schema::dropIfExists('t_photos_site');
        Schema::table('t_sites', fn (Blueprint $table) => $table->dropColumn('lien_photos'));
    }
};
