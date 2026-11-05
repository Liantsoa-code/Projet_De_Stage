<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Données de démonstration des règles de l'analyse sur les 5 aires protégées réelles : capacité d'accueil,
 * règles permanentes et condition proposée des périodes d'avertissement (retrouvées par leur description,
 * celles de donnees_test.sql). Exemples fictifs, à remplacer par les règles de Fanamby ; mêmes valeurs
 * que donnees_test.sql. Sans effet sur une aire protégée absente, peut être relancé :
 * php artisan db:seed --class=ReglesAnalyseSeeder
 */
class ReglesAnalyseSeeder extends Seeder
{
    public const REGLES = [
        'Andrafiamena - Andavakoera' => [
            'capacite_accueil' => 8,
            'conditions' => ["Accès aux grottes uniquement avec un guide agréé de l'aire protégée"],
            'periodes' => ['Reproduction des lémuriens : groupes réduits, pas de bruit' => 'Groupes de 4 personnes au plus près des lémuriens, à 20 m au moins, sans bruit ni flash'],
        ],
        'Anjozorobe - Angavo' => [
            'capacite_accueil' => 15,
            'conditions' => ["Présence d'un guide local agréé pendant tout le séjour"],
            'periodes' => ['Régénération du corridor : accès limité' => 'Circulation sur les sentiers balisés uniquement, aucun accès aux parcelles en régénération'],
        ],
        'Loky - Manambato' => [
            'capacite_accueil' => 12,
            'conditions' => ['Aucun prélèvement de faune, de flore ou de minéraux'],
            'periodes' => ['Nidification des oiseaux marins sur le littoral' => 'Aucune présence sur les plages de nidification entre 17 h et 7 h'],
        ],
        'Menabe Antimena' => [
            'capacite_accueil' => 10,
            'conditions' => ['Feu et bivouac uniquement sur les campements aménagés'],
            'periodes' => ['Surveillance renforcée des feux de brousse' => "Aucun feu ni groupe électrogène ; signalement de l'équipe au poste de garde à l'arrivée"],
        ],
        'Allée des Baobabs' => [
            'capacite_accueil' => 40,
            'conditions' => ['Aucun survol de drone au-dessus des visiteurs', 'Véhicules garés sur le parking aménagé uniquement'],
            'periodes' => [],
        ],
    ];

    public function run(): void
    {
        foreach (self::REGLES as $nom => $regles) {
            // NouveauxSitesSeeder écrit certains noms sans accent (« Allee des Baobabs »)
            $site = Site::whereIn('nom', [$nom, Str::ascii($nom)])->first();
            if (! $site) {
                continue;
            }

            $site->update(['capacite_accueil' => $regles['capacite_accueil']]);
            foreach ($regles['conditions'] as $libelle) {
                $site->conditions()->firstOrCreate(['libelle' => $libelle]);
            }
            foreach ($regles['periodes'] as $description => $condition) {
                $site->periodeSensibles()
                    ->where('description', $description)
                    ->where('niveau_restriction', 'avertissement')
                    ->update(['condition_proposee' => $condition]);
            }
        }
    }
}
