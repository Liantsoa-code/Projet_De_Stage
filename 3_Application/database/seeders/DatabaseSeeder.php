<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Statut;
use App\Models\Site;
use App\Models\TypeDemande;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Création des Statuts de base
        $statuts = [
            ['code' => 'brouillon', 'libelle' => 'Brouillon', 'description' => 'Demande en cours de rédaction'],
            ['code' => 'soumise', 'libelle' => 'Soumise', 'description' => 'Demande envoyée pour validation'],
            ['code' => 'en_analyse', 'libelle' => 'En cours d\'analyse', 'description' => 'Le validateur étudie la demande'],
            ['code' => 'validee', 'libelle' => 'Validée', 'description' => 'Demande acceptée'],
            ['code' => 'rejetee', 'libelle' => 'Rejetée', 'description' => 'Demande refusée'],
            ['code' => 'a_completer', 'libelle' => 'À compléter', 'description' => 'Des compléments sont demandés au demandeur'],
        ];
        // firstOrCreate : les statuts sont déjà insérés par la migration de t_statuts.
        foreach ($statuts as $statut) {
            Statut::firstOrCreate(['code' => $statut['code']], $statut);
        }

        // 2. Création des Types de Demande (pièces réutilisables d'une demande à l'autre)
        $reutilisables = ['Carte d'identité', 'Attestation d'assurance'];
        $types = [
            'Recherche scientifique' => [
                'documents' => ['Protocole de recherche', 'Carte d\'identité'],
                'regles' => ['Équipe limitée à 10 personnes'],
            ],
            'Tournage documentaire' => [
                'documents' => ['Scénario', 'Plan de vol drone', 'Attestation d\'assurance'],
                'regles' => ['Équipe limitée à 5 personnes'],
            ],
        ];
        foreach ($types as $libelle => $listes) {
            $type = TypeDemande::create(['libelle' => $libelle]);
            foreach ($listes['documents'] as $document) {
                $type->documentsRequis()->create(['libelle' => $document, 'reutilisable' => in_array($document, $reutilisables, true)]);
            }
            foreach ($listes['regles'] as $regle) {
                $type->reglesSpecifiques()->create(['libelle' => $regle]);
            }
        }

        // 3. Création des Sites (Aires Protégées)
        $siteLokobe = Site::create([
            'nom' => 'Réserve de Lokobe',
            'description' => 'Forêt dense humide de basse altitude.',
            'polygone_coordonnees' => [['lat' => -13.39, 'lng' => 48.31], ['lat' => -13.41, 'lng' => 48.33]]
        ]);
        
        $siteMarojejy = Site::create([
            'nom' => 'Parc National de Marojejy',
            'description' => 'Massif montagneux recouvert de forêts denses.',
            'polygone_coordonnees' => [['lat' => -14.43, 'lng' => 49.73], ['lat' => -14.45, 'lng' => 49.75]]
        ]);

        // 4. Création des Utilisateurs de test
        // Admin
        User::create([
            'name' => 'Admin Fanamby',
            'email' => 'admin@fanamby.org',
            'password' => Hash::make('password'), // le mot de passe est "password"
            'role' => 'admin',
        ]);

        // Validateur
        $validateur = User::create([
            'name' => 'Jean Validateur',
            'email' => 'validateur@fanamby.org',
            'password' => Hash::make('password'),
            'role' => 'validateur',
        ]);
        // On assigne le validateur à la Réserve de Lokobe
        $validateur->sites()->attach($siteLokobe->id);

        // Demandeur
        User::create([
            'name' => 'Alice Demandeur',
            'email' => 'alice@exemple.com',
            'password' => Hash::make('password'),
            'role' => 'demandeur',
        ]);
    }
}
