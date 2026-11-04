<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Statut « Retirée » : le demandeur annule une demande déjà envoyée, avant toute décision
 * (App\Support\DemandeStatutWorkflow::peutEtreRetiree). Statut définitif, comme une décision.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('t_statuts')->insertOrIgnore([
            'code' => 'retiree',
            'libelle' => 'Retirée',
            'description' => 'Demande annulée par le demandeur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('t_statuts')->where('code', 'retiree')->delete();
    }
};
