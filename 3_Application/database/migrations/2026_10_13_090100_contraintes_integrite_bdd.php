<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contraintes du modèle physique :
 * - CHECK sur les dates et l'effectif (la base refuse une donnée incohérente, quel que soit le code) ;
 * - RESTRICT : un site ou un type de demande encore utilisé ne peut plus effacer les demandes en cascade ;
 * - index de la cloche (notifications non lues d'un utilisateur, triées par date), lue à chaque page.
 */
return new class extends Migration
{
    private const CHECKS = [
        'demandes' => [
            'chk_demandes_dates' => 'date_fin >= date_debut',
            'chk_demandes_equipe' => 'equipe_nombre >= 1',
        ],
        'periode_sensibles' => [
            'chk_periode_sensibles_dates' => 'date_fin >= date_debut',
        ],
    ];

    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropForeign(['type_demande_id']);
        });

        Schema::table('demandes', function (Blueprint $table) {
            $table->foreign('site_id')->references('id')->on('sites')->restrictOnDelete();
            $table->foreign('type_demande_id')->references('id')->on('type_demandes')->restrictOnDelete();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'lu', 'date_envoi'], 'notifications_user_lu_envoi_index');
        });

        // Les CHECK sont appliqués par MySQL (8.0.16+) et MariaDB (10.2+) ; un NULL les satisfait (brouillon).
        if ($this->supporteChecks()) {
            foreach (self::CHECKS as $table => $contraintes) {
                foreach ($contraintes as $nom => $condition) {
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$nom} CHECK ({$condition})");
                }
            }
        }
    }

    public function down(): void
    {
        if ($this->supporteChecks()) {
            foreach (self::CHECKS as $table => $contraintes) {
                foreach (array_keys($contraintes) as $nom) {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$nom}");
                }
            }
        }

        // La clé étrangère user_id peut s'appuyer sur l'index composite : on lui rend d'abord son propre index.
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasIndex('notifications', 'notifications_user_id_foreign')) {
                $table->index('user_id', 'notifications_user_id_foreign');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_lu_envoi_index');
        });

        Schema::table('demandes', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropForeign(['type_demande_id']);
        });

        Schema::table('demandes', function (Blueprint $table) {
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('type_demande_id')->references('id')->on('type_demandes')->cascadeOnDelete();
        });
    }

    private function supporteChecks(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
