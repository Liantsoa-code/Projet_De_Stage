<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convention de collaboration : troisième document officiel, préparé avec l'autorisation et le cahier
 * des charges et remis au bénéficiaire à la validation (App\Support\DocumentsOfficiels).
 * - CHECK du type de t_documents_officiels élargi à « convention » ;
 * - modèle « Convention de collaboration » ajouté à la page Références, d'après la convention papier de Fanamby.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->typesAutorises(['autorisation', 'cahier_charges', 'convention']);

        DB::table('t_modeles_reference')->insertOrIgnore([
            'titre' => 'Convention de collaboration',
            'description' => "Accord entre Fanamby et le bénéficiaire d'une autorisation : objet, période, redevance, affectation des recettes et obligations de chaque partie.",
            'usage' => "Produite par la plateforme avec l'autorisation et le cahier des charges à la validation d'un dossier ; ce modèle en montre la structure et les textes par défaut.",
            'icone' => 'users',
            'automatique' => true,
            'contenu' => <<<'TXT'
# Convention de collaboration

Entre les soussignés :

**Association Fanamby**, organisme gestionnaire de l'aire protégée de [aire protégée], sise au Lot II M 63 AR Bis Antsakaviro, 101 Antananarivo, Madagascar, Tél. +261 20 78 636 61, représentée par [nom et fonction du représentant], ci-après désignée « Fanamby » ;

Et

**[nom du bénéficiaire]**, [fonction], [organisation], demeurant à [adresse], Tél. [téléphone], e-mail : [adresse e-mail], CIN / passeport : [numéro], ci-après désigné(e) « le Bénéficiaire » ;

Il a été convenu ce qui suit :

## Article 1 – Objet de la convention
La présente convention a pour objet de définir les conditions de collaboration entre les Parties dans le cadre de la réalisation du projet « [titre du projet] » ([type de projet]) dans l'aire protégée de [aire protégée], aux dates précisées à l'article 3.

## Article 2 – Avis favorable
Par la signature de la présente convention, les Parties reconnaissent que Fanamby a accordé son avis favorable à la réalisation du projet (dossier [FNB-AAAA-NNNN]). La présente convention vaut cadre officiel de collaboration entre Fanamby et le Bénéficiaire pour l'exécution des activités autorisées aux dates prévues.

## Article 3 – Période et modalités des opérations
Arrivée sur le terrain le [date d'arrivée] ; activités du [date de début] au [date de fin] ([nombre] jours) ; équipe de [nombre] personne(s). Les opérations comprennent notamment :
- [modalité, une par ligne]

Toute modification substantielle du calendrier devra être validée préalablement par Fanamby.

## Article 4 – Redevance financière
En contrepartie de l'autorisation d'exploitation temporaire du site pour les besoins du projet, le Bénéficiaire s'engage à verser à Fanamby la somme de **[montant] Ariary par jour**, soit pour [nombre] jours d'activités : **[total] Ariary**.

Le paiement devra être effectué selon les modalités communiquées par Fanamby avant le démarrage effectif des activités.

## Article 5 – Affectation des recettes
Fanamby assure la perception des recettes issues de la présente convention et procède à leur affectation selon la répartition suivante :
- Conservation et gestion écologique (40 %) : activités de conservation, surveillance et patrouilles, lutte contre les feux illicites, restauration de l'aire protégée, gestion administrative et fonctionnement du site ;
- Développement local (40 %) : projets sociaux et communautaires, infrastructures de base, appui aux chaînes de valeur locales, entretien des pistes et circuits, équipements communautaires ;
- Gouvernance territoriale (20 %) : appui aux Fokontany, Communes et Région concernés, coordination et redevabilité territoriale, médiation et gestion des conflits, communication et transparence.

## Article 6 – Obligations du Bénéficiaire
Le Bénéficiaire s'engage à :
- respecter strictement les consignes données par Fanamby et les autorités compétentes ;
- ne causer aucune dégradation du site, de la biodiversité ou des infrastructures ;
- limiter les nuisances sonores et environnementales ;
- respecter les zones autorisées de circulation et de survol ;
- remettre le site dans son état initial après l'intervention ;
- transmettre à Fanamby une copie des images produites, selon des modalités à convenir ;
- disposer de toutes les autorisations techniques nécessaires au matériel utilisé (drone notamment).

## Article 7 – Obligations de Fanamby
Fanamby s'engage à :
- faciliter l'accès au site selon les règles applicables ;
- assurer l'encadrement local nécessaire ;
- percevoir et gérer les recettes conformément à l'article 5 ;
- veiller à la compatibilité des activités avec les objectifs de conservation du site.

## Article 8 – Responsabilité
Le Bénéficiaire demeure seul responsable :
- de son équipe technique et logistique ;
- du matériel utilisé ;
- des assurances nécessaires ;
- de tout dommage causé aux tiers ou au site du fait de ses activités.

## Article 9 – Litiges
Tout différend relatif à l'exécution ou à l'interprétation de la présente convention fera d'abord l'objet d'un règlement amiable entre les Parties. À défaut, compétence est attribuée aux juridictions compétentes de Madagascar.

## Article 10 – Entrée en vigueur
La présente convention prend effet à compter de sa date de signature par les Parties et demeure valable jusqu'à la clôture complète des obligations réciproques.

Fait à [lieu], le [date de signature], en deux exemplaires originaux.

**Pour Fanamby**
[nom et fonction du signataire]
Signature et cachet :

**Le Bénéficiaire**
[nom du bénéficiaire]
Signature :
TXT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('t_modeles_reference')->where('titre', 'Convention de collaboration')->delete();
        DB::table('t_documents_officiels')->where('type', 'convention')->delete();
        $this->typesAutorises(['autorisation', 'cahier_charges']);
    }

    /**
     * Remplace le CHECK des types de documents officiels (MySQL 8.0.19+ et MariaDB 10.2+ : DROP CONSTRAINT).
     *
     * @param  array<int, string>  $types
     */
    private function typesAutorises(array $types): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $liste = implode(', ', array_map(fn (string $t) => "'{$t}'", $types));
        DB::statement('ALTER TABLE t_documents_officiels DROP CONSTRAINT chk_t_documents_officiels_type');
        DB::statement("ALTER TABLE t_documents_officiels ADD CONSTRAINT chk_t_documents_officiels_type CHECK (type IN ({$liste}))");
    }
};
