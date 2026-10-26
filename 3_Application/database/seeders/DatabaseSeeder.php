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

        // 2. Types de projet du formulaire de demande (pièces réutilisables d'une demande à l'autre ;
        //    « Autre » demande une précision au demandeur)
        $reutilisables = ['Copie de la CIN ou du passeport', "Attestation d'assurance"];
        $pieces = ['Synopsis / Brief du projet', 'Planning de tournage', 'Copie de la CIN ou du passeport'];
        $types = [
            'Long métrage' => ['documents' => [...$pieces, "Attestation d'assurance"], 'regles' => ['Équipe limitée à 25 personnes sur le site']],
            'Court métrage' => ['documents' => $pieces, 'regles' => ['Équipe limitée à 10 personnes']],
            'Documentaire' => ['documents' => $pieces, 'regles' => ['Drone interdit au-dessus des zones de nidification']],
            'Émission télé' => ['documents' => [...$pieces, "Attestation d'assurance"], 'regles' => ['Équipe limitée à 15 personnes']],
            'Projet étudiant' => ['documents' => [...$pieces, "Attestation de l'établissement"], 'regles' => ['Encadrement par un enseignant ou un guide agréé']],
            'Publicité' => ['documents' => [...$pieces, "Attestation d'assurance"], 'regles' => ["Mention de l'aire protégée dans les crédits"]],
            'Corporatif' => ['documents' => $pieces, 'regles' => []],
            'Clip musical' => ['documents' => [...$pieces, "Attestation d'assurance"], 'regles' => ["Musique amplifiée interdite dans l'aire protégée"]],
            'Reportage' => ['documents' => $pieces, 'regles' => []],
            'Téléfilm' => ['documents' => [...$pieces, "Attestation d'assurance"], 'regles' => ['Équipe limitée à 25 personnes sur le site']],
            'Photographie institutionnelle' => ['documents' => ['Synopsis / Brief du projet', 'Planning des prises de vue', 'Copie de la CIN ou du passeport'], 'regles' => []],
            'Autre' => ['documents' => ['Synopsis / Brief du projet', 'Planning des prises de vue', 'Copie de la CIN ou du passeport'], 'regles' => [], 'precision_requise' => true],
        ];
        foreach ($types as $libelle => $listes) {
            $type = TypeDemande::create(['libelle' => $libelle, 'precision_requise' => $listes['precision_requise'] ?? false]);
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
            'organisation' => 'Association Fanamby',
            'fonction' => 'Responsable des autorisations', // figure dans la section VI du formulaire
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
