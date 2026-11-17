<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partie visée par une demande de complément, et partie du formulaire à ouvrir depuis une notification :
 * - t_historique_statuts.partie : ce que le validateur demande de compléter (pièce manquante, pièce à
 *   remplacer, coordonnées…), vide pour les autres décisions et les anciens compléments (CHECK) ;
 * - t_notifications.etape : étape du formulaire de demande où la notification amène le demandeur
 *   (vide = fiche de la demande) ;
 * - t_demandes.clarification : réponse du demandeur à un point à clarifier (complément « clarification »),
 *   saisie dans la partie « Informations sur la demande ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_demandes', function (Blueprint $table) {
            $table->text('clarification')->nullable()->after('objectifs');
        });

        Schema::table('t_historique_statuts', function (Blueprint $table) {
            $table->string('partie', 20)->nullable()->after('commentaire');
        });

        Schema::table('t_notifications', function (Blueprint $table) {
            $table->string('etape', 20)->nullable()->after('contenu');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE t_historique_statuts ADD CONSTRAINT chk_t_historique_statuts_partie CHECK (partie IS NULL OR partie IN ('demandeur', 'projet', 'clarification', 'sejour', 'piece_manquante', 'piece_a_remplacer', 'autre'))");
            DB::statement("ALTER TABLE t_notifications ADD CONSTRAINT chk_t_notifications_etape CHECK (etape IS NULL OR etape IN ('demandeur', 'projet', 'aire', 'pieces'))");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // DROP CONSTRAINT : accepté par MySQL 8.0.19+ et MariaDB 10.2+ (DROP CHECK ne l'est pas par MariaDB)
            DB::statement('ALTER TABLE t_historique_statuts DROP CONSTRAINT chk_t_historique_statuts_partie');
            DB::statement('ALTER TABLE t_notifications DROP CONSTRAINT chk_t_notifications_etape');
        }

        Schema::table('t_historique_statuts', fn (Blueprint $table) => $table->dropColumn('partie'));
        Schema::table('t_notifications', fn (Blueprint $table) => $table->dropColumn('etape'));
        if (Schema::hasColumn('t_demandes', 'clarification')) {
            Schema::table('t_demandes', fn (Blueprint $table) => $table->dropColumn('clarification'));
        }
    }
};
