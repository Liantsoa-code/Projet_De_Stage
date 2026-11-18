<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed
     *
     * 1. Données de référence (statuts, types de projet, 5 aires protégées et leur contour) : PlateformeSeeder.
     * 2. Hors production seulement : 4 comptes de démonstration (mot de passe « password »), adresse déjà vérifiée.
     *
     * En production, l'administrateur se crée avec « php artisan plateforme:admin ».
     * Jeu de test complet (dossiers, historique sur 18 mois) : « php artisan donnees:test ».
     */
    public function run(): void
    {
        $this->call(PlateformeSeeder::class);

        if (app()->isProduction()) {
            $this->command?->warn('Production : aucun compte de démonstration créé. Créez l\'administrateur avec « php artisan plateforme:admin ».');

            return;
        }

        $comptes = [
            ['name' => 'Admin Fanamby', 'email' => 'admin@fanamby.org', 'role' => 'admin'],
            // Le validateur figure dans la section VI du formulaire (organisation, fonction)
            ['name' => 'Jean Validateur', 'email' => 'validateur@fanamby.org', 'role' => 'validateur',
                'organisation' => 'Association Fanamby', 'fonction' => 'Responsable des autorisations'],
            ['name' => 'Alice Demandeur', 'email' => 'alice@exemple.com', 'role' => 'demandeur'],
            // Observateur : suit tous les dossiers sans décider, et signale les problèmes
            ['name' => 'Nirina Observatrice', 'email' => 'observateur@fanamby.org', 'role' => 'observateur',
                'organisation' => 'Association Fanamby', 'fonction' => 'Chargée de suivi et évaluation'],
        ];

        foreach ($comptes as $compte) {
            $user = User::firstOrCreate(['email' => $compte['email']], $compte + ['password' => Hash::make('password')]);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        }

        // Le validateur de démonstration est chargé de la première aire protégée
        User::where('email', 'validateur@fanamby.org')->first()
            ->sites()->syncWithoutDetaching(Site::orderBy('id')->limit(1)->pluck('id'));
    }
}
