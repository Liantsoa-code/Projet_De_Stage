<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use Illuminate\Database\Seeder;

/**
 * Données de référence d'une plateforme neuve, sans aucun compte ni dossier : utilisable en production.
 *
 *     php artisan db:seed --class=PlateformeSeeder --force
 *
 * - statuts du workflow (déjà insérés par les migrations, complétés au besoin) ;
 * - types de projet du formulaire, avec leurs pièces exigées et leurs règles ;
 * - les 5 aires protégées gérées par Fanamby, avec leur contour réel simplifié (aires_protegees.json).
 *
 * Relançable sans risque : un statut, un type ou une aire déjà présent (même nom) n'est ni dupliqué
 * ni modifié. Les changements faits ensuite par l'admin dans la plateforme sont donc conservés.
 * L'administrateur se crée à part : php artisan plateforme:admin
 */
class PlateformeSeeder extends Seeder
{
    /** Contours des aires protégées : [{ nom, description, polygone_coordonnees: [{lat, lng}, ...] }, ...] */
    public const FICHIER_AIRES = __DIR__.'/aires_protegees.json';

    public function run(): void
    {
        $this->statuts();
        $this->typesDeProjet();
        $this->airesProtegees();
    }

    private function statuts(): void
    {
        $statuts = [
            ['code' => 'brouillon', 'libelle' => 'Brouillon', 'description' => 'Demande en cours de rédaction'],
            ['code' => 'soumise', 'libelle' => 'Soumise', 'description' => 'Demande envoyée pour validation'],
            ['code' => 'en_analyse', 'libelle' => 'En cours d\'analyse', 'description' => 'Le validateur étudie la demande'],
            ['code' => 'validee', 'libelle' => 'Validée', 'description' => 'Demande acceptée'],
            ['code' => 'rejetee', 'libelle' => 'Rejetée', 'description' => 'Demande refusée'],
            ['code' => 'a_completer', 'libelle' => 'À compléter', 'description' => 'Des compléments sont demandés au demandeur'],
            ['code' => 'retiree', 'libelle' => 'Retirée', 'description' => 'Demande annulée par le demandeur'],
        ];

        foreach ($statuts as $statut) {
            Statut::firstOrCreate(['code' => $statut['code']], $statut);
        }
    }

    /**
     * Types de projet proposés dans le formulaire de demande. Les pièces marquées réutilisables
     * sont reprises d'une demande à l'autre ; « Autre » demande une précision au demandeur.
     */
    private function typesDeProjet(): void
    {
        $synopsis = 'Synopsis / Brief du projet';
        $planning = 'Planning de tournage';
        $identite = 'Copie de la CIN ou du passeport';
        $assurance = "Attestation d'assurance";
        $drone = "Drone soumis à l'autorisation de l'Aviation civile de Madagascar";
        $equipe25 = 'Équipe limitée à 25 personnes sur le site';
        $remiseEnEtat = 'Décors démontés et site remis en état après le tournage';
        $sentiers = 'Prises de vue sur les sentiers balisés uniquement';

        $types = [
            'Long métrage' => [[$synopsis, $planning, $identite, $assurance], [$equipe25, $remiseEnEtat, $drone]],
            'Court métrage' => [[$synopsis, $planning, $identite], ['Équipe limitée à 10 personnes', $drone]],
            'Documentaire' => [[$synopsis, $planning, $identite], ['Aucune approche de la faune à moins de 10 m', 'Drone interdit au-dessus des zones de nidification']],
            'Émission télé' => [[$synopsis, $planning, $identite, $assurance], ['Équipe limitée à 15 personnes', "Pas d'éclairage artificiel la nuit en forêt"]],
            'Projet étudiant' => [[$synopsis, $planning, $identite, "Attestation de l'établissement"], ['Encadrement par un enseignant ou un guide agréé', 'Équipe limitée à 8 personnes']],
            'Publicité' => [[$synopsis, $planning, $identite, $assurance], ['Aucun produit mis en scène au contact de la faune ou de la flore', "Mention de l'aire protégée dans les crédits", $drone]],
            'Corporatif' => [[$synopsis, $planning, $identite], [$sentiers]],
            'Clip musical' => [[$synopsis, $planning, $identite, $assurance], ["Musique amplifiée interdite dans l'aire protégée", 'Équipe limitée à 10 personnes']],
            'Reportage' => [[$synopsis, $planning, $identite], ['Interviews des communautés avec leur consentement préalable']],
            'Téléfilm' => [[$synopsis, $planning, $identite, $assurance], [$equipe25, $remiseEnEtat]],
            'Photographie institutionnelle' => [[$synopsis, 'Planning des prises de vue', $identite], [$sentiers]],
            'Autre' => [[$synopsis, 'Planning des prises de vue', $identite], ["Accompagnement par un agent de l'Association"]],
        ];
        $reutilisables = [$identite, $assurance];

        foreach ($types as $libelle => [$pieces, $regles]) {
            $type = TypeDemande::firstOrCreate(['libelle' => $libelle], ['precision_requise' => $libelle === 'Autre']);

            // Type déjà présent : ses pièces et règles ont pu être modifiées par l'admin, on n'y touche pas.
            if (! $type->wasRecentlyCreated) {
                continue;
            }

            foreach ($pieces as $piece) {
                $type->documentsRequis()->create(['libelle' => $piece, 'reutilisable' => in_array($piece, $reutilisables, true)]);
            }
            foreach ($regles as $regle) {
                $type->reglesSpecifiques()->create(['libelle' => $regle]);
            }
        }
    }

    /**
     * Aires protégées et leur contour (affiché sur la carte d'accueil et dans les documents officiels).
     * La capacité d'accueil, les conditions et les périodes sensibles se règlent ensuite par l'admin.
     */
    private function airesProtegees(): void
    {
        $aires = json_decode(file_get_contents(self::FICHIER_AIRES), true, 512, JSON_THROW_ON_ERROR);

        foreach ($aires as $aire) {
            // Créer un site vide aussi le cache de la carte d'accueil (Site::booted).
            $site = Site::firstOrCreate(['nom' => $aire['nom']], [
                'description' => $aire['description'],
                'polygone_coordonnees' => $aire['polygone_coordonnees'],
            ]);

            $this->command?->line(($site->wasRecentlyCreated ? '  ajoutée   ' : '  existante ').$aire['nom']
                .' ('.count($aire['polygone_coordonnees']).' points de contour)');
        }
    }
}
