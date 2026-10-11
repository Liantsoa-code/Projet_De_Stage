# Plateforme d'accès aux aires protégées — Fondation FANAMBY

Application web de gestion des demandes d'accès aux aires protégées gérées par la Fondation FANAMBY (Madagascar).
Chercheurs, équipes de tournage et partenaires y déposent leurs demandes. Les validateurs les instruisent. Une fois la demande validée, l'autorisation officielle et le cahier des charges sont générés en PDF.

**Stack :** Laravel 13 · Vue 3 · Inertia.js 2 · TailwindCSS 3 · MapLibre GL · Chart.js · DomPDF
**Base de données :** MySQL / MariaDB uniquement : `projet_stage` pour l'application, `projet_stage_test` pour les tests automatisés.

---

## Sommaire

1. [Rôles](#1-rôles)
2. [Cycle de vie d'une demande](#2-cycle-de-vie-dune-demande)
3. [Fonctionnalités](#3-fonctionnalités)
4. [Règles de sécurité](#4-règles-de-sécurité)
5. [Organisation des dossiers](#5-organisation-des-dossiers)
6. [Tests automatisés](#6-tests-automatisés)
7. [Installation et lancement](#7-installation-et-lancement)

---

## 1. Rôles

| Rôle | Ce qu'il peut faire |
|---|---|
| **Demandeur** | Crée, enregistre en brouillon, complète et suit ses propres demandes. Télécharge ses PDF officiels une fois la demande validée. |
| **Validateur** | Fait tout ce que fait un demandeur. Instruit en plus les demandes des **sites qui lui sont assignés** : décision, demande de complément, export CSV. |
| **Admin** | Fait tout ce que fait un validateur, sur **tous les sites**. Gère en plus les sites, les périodes sensibles, les types d'activité et les rôles des utilisateurs. |

Les rôles sont contrôlés par trois *gates* déclarées dans `AppServiceProvider` : `is-admin`, `is-validateur` (admin inclus) et `is-demandeur`.

---

## 2. Cycle de vie d'une demande

```
brouillon ──► soumise ──► en_analyse ──► validée
                 │            │      └─► rejetée
                 └──► à compléter ◄──┘
                         │
                         └──► (renvoyée par le demandeur) ──► en_analyse
```

| Statut | Signification |
|---|---|
| `brouillon` | En cours de rédaction. Visible uniquement par son auteur. Peut être supprimé. |
| `soumise` | Envoyée, en attente de prise en charge. |
| `en_analyse` | Un validateur étudie le dossier. |
| `a_completer` | Le validateur demande des corrections ou des pièces. Le dossier revient au demandeur. |
| `validee` | Acceptée. **Statut final.** Les PDF officiels deviennent disponibles. |
| `rejetee` | Refusée. **Statut final.** |

Règles du workflow (encodées dans `app/Support/DemandeStatutWorkflow.php`) :
- un statut ne peut qu'avancer : pas de retour en arrière, pas de « changement » vers le même statut ;
- aucune décision n'est possible sur un brouillon ni sur un dossier clôturé (validé ou rejeté) ;
- un complément ne peut être demandé que depuis `soumise` ou `en_analyse` ;
- un dossier « à compléter » renvoyé par le demandeur repasse directement en `en_analyse`.

Chaque changement de statut est historisé : auteur, date, ancien et nouveau statut, commentaire.

---

## 3. Fonctionnalités

### 3.1 Espace public

- **Page d'accueil** (`/`) : présentation du service en 3 étapes, carte interactive de toutes les aires protégées (polygones, marqueurs animés, fiche du site au clic avec sa surface). La liste des sites permet de centrer la carte sur l'un d'eux.
- **Authentification** (Laravel Breeze) : inscription avec indicateurs de robustesse et de confirmation du mot de passe, connexion, déconnexion, mot de passe oublié et réinitialisation, vérification de l'adresse e-mail, confirmation du mot de passe. Les champs mot de passe ont un bouton afficher/masquer.

### 3.2 Demandeur

**Tableau de bord** — « où en sont mes dossiers, et que dois-je faire ? »
- Encadrés d'alerte pour chaque dossier « à compléter », avec le dernier commentaire du validateur, son auteur et la date.
- Liste des demandes en cours, avec une frise d'avancement du statut et la prochaine étape :
  - brouillon : nombre de **pièces encore à joindre**, dates à préciser, ou « prêt à être envoyé » ;
  - en instruction : nombre de jours d'attente et **délai habituel de décision** (médiane du site s'il compte au moins 3 décisions, sinon celle de la plateforme).
- Séjours validés à venir, avec téléchargement direct des PDF.

**Mes demandes** (`/demandes`)
- Liste paginée (10 par page) avec onglets de filtre par statut et compteur par statut.
- Actions selon le statut : *Continuer* (brouillon), *Compléter* (à compléter), PDF (validée).

**Nouvelle demande / modification** (`/demandes/create`, `/demandes/{id}/edit`)
- Choix du site, du type d'activité, des dates, de la taille de l'équipe et description du projet.
- **Pièces justificatives dynamiques** : la liste des documents exigés dépend du type d'activité choisi. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, ODT, 20 Mo maximum par fichier.
- **Carte de choix du site** : toutes les aires protégées sont affichées. Un clic sur une zone ou un marqueur choisit le site (synchronisé avec la liste déroulante), et le survol affiche son nom, sa surface et son état sur les dates choisies.
- **Carte colorée selon les dates** : dès que les dates sont saisies, chaque site prend la couleur de son état (vert = ouvert, hachures ocre = période sensible, hachures rouges = période bloquante), avec une légende. Le demandeur voit d'un coup d'œil quels sites sont ouverts sur son séjour.
- **Fiche du site choisi** : nom, surface calculée à partir du contour, description.
- **Vérification du calendrier pendant la saisie** : les périodes sensibles à venir du site sont affichées et les chevauchements avec les dates choisies sont signalés immédiatement (avertissement ou blocage).
- **Brouillon** : seuls le site et le type sont obligatoires. Le brouillon peut être repris plus tard, et les pièces déjà envoyées sont conservées.
- **Soumission** : tous les champs et toutes les pièces sont exigés (sauf celles déjà fournies). La date de début ne peut pas être passée.
- **Complément** : le commentaire du validateur s'affiche en haut du formulaire. Le demandeur peut joindre un message, puis le dossier est renvoyé en analyse.
- Un nouveau fichier remplace l'ancien du même type. Si le type d'activité change, les pièces qui ne correspondent plus sont supprimées.

**Détail d'une demande** (`/demandes/{id}`)
- Informations du séjour, pièces jointes téléchargeables, historique complet des statuts et des commentaires.
- Mise en avant de la demande de complément en cours.
- Boutons *Reprendre le brouillon*, *Compléter le dossier* et *Supprimer* (brouillon uniquement, avec confirmation).
- Ouverture des PDF officiels lorsque la demande est validée.

### 3.3 Validateur et admin (instruction)

Définitions communes : un dossier est **en retard** après 7 jours sans décision ; une **arrivée est imminente** quand le séjour commence dans 14 jours ou moins (ou a déjà commencé) sans décision. Les brouillons sont exclus de toutes les statistiques, et un validateur ne voit que ses sites.

**Tableau de bord du validateur** — « qu'est-ce que je dois traiter maintenant ? »
- Indicateurs : dossiers à traiter (dont non pris en charge), arrivées imminentes, retards, conflits de calendrier, dossiers chez le demandeur (compléments demandés).
- **Ma file de traitement** : les dossiers en attente triés par urgence (arrivées imminentes d'abord, puis retards, puis les autres), avec pour chacun l'arrivée (« dans 5 j », « dépassée de 2 j »), le nombre de jours d'attente et l'alerte de période sensible.
- **En attente du demandeur** : compléments demandés, les plus anciens d'abord, avec le nombre de jours écoulés (pour relancer).
- **Mon activité du mois** : prises en charge, compléments demandés, validations, rejets, et délai médian de décision sur ses sites.
- Carte de ses sites (voir ci-dessous).

**Tableau de bord de l'admin** — « le service fonctionne-t-il, et quel est l'effet sur les aires protégées ? »
- Indicateurs : **délai médian de décision** et part des dossiers décidés en 15 jours ou moins ; **taux de validation** (calculé sur les seuls dossiers décidés) et part des dossiers passés par un complément ; dossiers en attente (arrivées imminentes, retards) ; **personnes sur le terrain aujourd'hui**.
- **Points d'attention** : sites sans validateur (les nouvelles demandes n'y sont notifiées à personne), puis dossiers urgents, en retard ou en conflit.
- Histogramme des demandes reçues par mois, **comparé à l'année précédente**.
- **Fréquentation autorisée** sur les 12 prochains mois, par site : séjours, personnes et **personnes × jours** (la pression humaine réelle sur le site).
- **Charge des validateurs** : dossiers en attente sur les sites de chacun.
- **Sur le terrain aujourd'hui** : séjours en cours (demandeur, site, équipe, date de fin).
- Répartition par type d'activité.
- Carte de toutes les aires protégées (voir ci-dessous).

**Carte du tableau de bord** (admin et validateur)
- **Curseur de date** sur l'année à venir : les sites restreints ce jour-là sont colorés (hachures ocre = période sensible, hachures rouges = bloquant), avec un résumé (« 2 bloqués · 1 sensible ») et un raccourci vers la prochaine période.
- **Charge par site** : un cercle bleu dont la taille suit le nombre de dossiers en attente, avec un contour rouge si l'un d'eux est en retard ou en conflit de calendrier. La fiche du site au clic détaille ces chiffres.

**Dossiers à valider / Toutes les demandes** (`/validateur/demandes`)
- Liste paginée (15 par page) avec recherche (nom ou e-mail du demandeur, site, numéro `#id`) et filtres par statut, par site et par période (du / au).
- Indicateur visuel des dossiers qui chevauchent une période sensible.
- **Export CSV** des demandes filtrées, compatible Excel (séparateur `;`, encodage UTF-8 avec BOM). Il contient demandeur, site, dates, statut, date de décision, délai en jours et nombre de chevauchements.

**Dossier complet** (`/validateur/demandes/{id}`)
- Identité du demandeur, séjour, équipe, date de dépôt, alertes de chevauchement, pièces jointes (avec signalement des pièces manquantes), historique.
- Alerte lorsque le dossier attend une décision depuis plus de 7 jours.
- **Formulaire de décision** : seules les transitions autorisées sont proposées (*Prendre en charge*, *Demander un complément*, *Valider*, *Rejeter*). Le commentaire est obligatoire.
- Un admin ou un validateur ne peut pas statuer sur **sa propre demande** : un autre décideur doit le faire.

### 3.4 Administration (admin uniquement)

| Écran | Fonctionnalités |
|---|---|
| **Sites protégés** (`/admin/sites`) | Liste avec recherche et nombre de demandes par site. Création et modification avec **dessin du polygone sur carte** (outil polygone, déplacement des points, effacement). Suppression impossible si des demandes y sont rattachées. |
| **Périodes sensibles** (`/admin/periode-sensibles`) | Périodes de restriction par site, de niveau **bloquant** (la soumission est refusée) ou **avertissement** (la soumission est acceptée mais le conflit est signalé). Filtre par site. Par défaut, seules les périodes en cours ou à venir sont affichées ; une option montre aussi l'historique. |
| **Types de demandes** (`/admin/type-demandes`) | Types d'activité avec leur liste de documents requis et leurs règles spécifiques (saisies séparées par des virgules). Suppression impossible si des demandes utilisent le type. |
| **Utilisateurs** (`/admin/users`) | Liste avec recherche (nom, e-mail) et filtre par rôle. Changement de rôle et **affectation des sites** aux validateurs. Les affectations sont retirées si l'utilisateur n'est plus validateur. |

### 3.5 Fonctions transverses

- **Notifications internes** : cloche avec compteur de non-lues dans l'en-tête. Le demandeur est notifié à la soumission et à chaque décision ; les décideurs du site sont notifiés à chaque nouvelle demande et à chaque complément renvoyé. Un clic marque la notification comme lue et ouvre le bon dossier selon le rôle.
- **PDF officiels** (DomPDF), générés à la volée pour une demande validée :
  - *Autorisation officielle d'accès* : en-tête Fondation FANAMBY, bénéficiaire, site, dates, équipe ;
  - *Cahier des charges* : règles spécifiques au type d'activité, avertissements de l'analyse des périodes, engagement et signature du demandeur.
  Les PDF s'ouvrent dans un nouvel onglet via une requête authentifiée, ce qui évite l'interception par les gestionnaires de téléchargement.
- **Profil** : modification du nom et de l'e-mail, changement de mot de passe, suppression du compte (avec confirmation par mot de passe).
- **Référence officielle** des demandes au format `FAN-AAAA-NNNN`.
- **Fonds de carte** sur toutes les cartes de sites : plan, satellite (Esri World Imagery) ou relief (OpenTopoMap), plus un bouton plein écran.
- **Vue 3D du terrain** : le bouton « Voir en 3D » de la fiche d'un site (ou « Voir le terrain en 3D » dans le formulaire de demande) incline la carte sur le site avec le relief (exagéré ×1,5) et l'ombrage des pentes. Un clic droit + glisser fait pivoter la vue ; « Revenir en 2D » remet la carte à plat. Les altitudes (Terrain Tiles d'AWS, issues de SRTM) ne sont téléchargées qu'au premier passage en 3D.
- **Squelettes de chargement** pendant la navigation (tableau de bord, liste, formulaire, détail), affichés seulement si la réponse tarde plus de 120 ms.
- **Filtres synchronisés avec l'URL** : les filtres survivent au rechargement et peuvent être partagés ; la recherche texte est temporisée (350 ms).
- **Interface responsive** avec menu latéral (repliable sur mobile), charte graphique FANAMBY (vert forêt, rouge réservé aux blocages, latérite, rivière) et respect de l'option « réduire les animations » du système.

---

## 4. Règles de sécurité

| Règle | Où elle est appliquée |
|---|---|
| Un demandeur ne voit, ne modifie et ne supprime que **ses** demandes. | `DemandeController::assertOwner` |
| Seuls les brouillons et les dossiers « à compléter » sont modifiables ; seuls les brouillons sont supprimables. | `DemandeController`, `DemandeStatutWorkflow::isEditableByDemandeur` |
| Un brouillon est invisible côté validation (erreur 404). | `Validateur\DemandeController::assertCanAccessDemande` |
| Un validateur n'accède qu'aux demandes de ses sites assignés. | `Validateur\DemandeController`, `Demande::scopeForStatsViewer` |
| Pas d'auto-validation : on ne statue jamais sur sa propre demande. | `DemandeDecisionRules::canUserDecide` |
| Pièces jointes stockées hors du dossier public (`storage/app/private`), servies uniquement après contrôle d'accès. | `DocumentController`, `DemandeDecisionRules::canUserView` |
| PDF officiels uniquement pour une demande **validée** et pour les personnes autorisées. | `PdfController` |
| Une période sensible **bloquante** empêche la soumission. | `DemandeDossier::analysePeriodes` |
| Les notifications ne peuvent être marquées comme lues que par leur destinataire. | `NotificationController` |
| Les popups de la carte insèrent le nom et la description des sites comme texte, jamais comme HTML (protection XSS). | `DashboardMap.vue` |

---

## 5. Organisation des dossiers

### Racine de `3_Application/`

| Élément | Rôle |
|---|---|
| `app/` | Logique métier PHP (modèles, contrôleurs, règles). |
| `bootstrap/` | Démarrage de Laravel : routage, middleware Inertia, providers. |
| `config/` | Configuration de Laravel. |
| `database/` | Migrations, seeders et données géographiques des sites. |
| `public/` | Racine web : point d'entrée, images, build Vite. |
| `resources/` | Interface Vue, CSS et templates Blade (page racine et PDF). |
| `routes/` | Définition des URL. |
| `storage/` | Fichiers générés à l'exécution (pièces jointes, logs, cache). |
| `tests/` | Tests automatisés PHPUnit. |
| `vendor/`, `node_modules/` | Dépendances PHP et JavaScript installées (non versionnées). |
| `Site_Fanamby/` | Fichiers sources **GeoJSON / QGIS** des 5 aires protégées, dans leurs projections d'origine. |
| `process_geojson.py` | Script Python (pyproj) qui convertit ces GeoJSON en coordonnées GPS WGS84 et produit `database/seeders/site_coordinates.json`. |
| `insert_sites.sql` | Script SQL d'insertion directe des aires protégées (alternative au seeder). |
| `image geo/` | Captures d'écran de référence des périmètres des sites. |
| `ICONS/` | Bibliothèque d'icônes Phosphor complète (SVG, PNG, polices). Fichiers de référence uniquement : les icônes utilisées sont déjà recopiées dans `Icon.vue`. |
| `composer.json`, `package.json` | Dépendances PHP et JavaScript. |
| `vite.config.js`, `tailwind.config.js`, `postcss.config.js` | Compilation du front-end et thème Tailwind (couleurs FANAMBY, polices Inter et JetBrains Mono). |
| `phpunit.xml` | Configuration des tests : base MySQL dédiée `projet_stage_test`, vidée et recréée à chaque lancement. |
| `AGENTS.md`, `CLAUDE.md` | Consignes pour les assistants de code. |

### `app/`

#### `app/Models/` — tables de la base

| Modèle | Rôle |
|---|---|
| `User` | Utilisateur avec un rôle (`admin`, `validateur`, `demandeur`). Lié aux sites qu'il valide (table pivot `site_user`). |
| `Site` | Aire protégée : nom, description, polygone de coordonnées (JSON `[{lat, lng}, …]`). |
| `Demande` | Demande d'accès : demandeur, site, type, statut, dates, équipe, description, date de soumission, résultats de l'analyse des périodes (JSON). Portées `horsBrouillon()` et `forStatsViewer()`. |
| `TypeDemande` | Type d'activité (ex. « Recherche scientifique ») avec ses documents requis et ses règles spécifiques (JSON). |
| `Statut` | Référentiel des 6 statuts du workflow. |
| `Document` | Pièce jointe d'une demande (nom d'origine, chemin privé, type de document). |
| `PeriodeSensible` | Période de restriction sur un site, de niveau `bloquant` ou `avertissement`. |
| `HistoriqueStatut` | Journal d'audit de chaque changement de statut. |
| `Notification` | Notification interne liée à une demande (indicateur `lu`). |

#### `app/Http/Controllers/`

| Contrôleur | Rôle |
|---|---|
| `DashboardController` | Tableau de bord adapté au rôle : suivi des dossiers (demandeur), file de traitement par urgence (validateur), indicateurs de pilotage, organisation et fréquentation (admin). Les durées sont calculées en PHP, en jours calendaires. |
| `DemandeController` | Espace demandeur : liste, création, brouillon, modification, complément, suppression, envoi des notifications. |
| `DocumentController` | Téléchargement sécurisé des pièces jointes. |
| `PdfController` | Génération de l'autorisation et du cahier des charges. |
| `NotificationController` | Marque une notification comme lue et redirige vers le bon dossier. |
| `ProfileController` | Profil utilisateur (Breeze). |
| `Admin/SiteController` | CRUD des sites, avec recherche. |
| `Admin/PeriodeSensibleController` | CRUD des périodes sensibles, avec filtres. |
| `Admin/TypeDemandeController` | CRUD des types d'activité. |
| `Admin/UserController` | Rôles et affectation des sites aux validateurs. |
| `Validateur/DemandeController` | Liste filtrée, dossier complet, décision, export CSV. |
| `Auth/*` | Authentification Laravel Breeze (9 contrôleurs). |


#### `app/Support/` — règles métier réutilisables

| Classe | Rôle |
|---|---|
| `DemandeStatutWorkflow` | Transitions de statut autorisées, statuts finaux, statuts modifiables par le demandeur. |
| `DemandeDecisionRules` | Qui peut décider, qui peut consulter un dossier, qui notifier lors d'une nouvelle demande. |
| `DemandeDossier` | Analyse des chevauchements avec les périodes sensibles ; enregistrement, remplacement et suppression des pièces jointes. |
| `DemandeNotificationMessage` | Textes des notifications en français pour chaque événement. |

#### Autres

- `app/Http/Middleware/HandleInertiaRequests.php` : partage avec toutes les pages l'utilisateur connecté, les messages flash et les notifications non lues.
- `app/Http/Requests/Auth/LoginRequest.php` : validation de la connexion avec limitation des tentatives.
- `app/Providers/AppServiceProvider.php` : déclaration des gates de rôle et préchargement des fichiers Vite.

### `bootstrap/`

- `app.php` : branche les routes et ajoute le middleware Inertia au groupe web.
- `providers.php` : liste des providers chargés.
- `cache/` : caches générés automatiquement.

### `config/`

Configuration standard de Laravel : `app`, `auth`, `cache`, `database`, `filesystems` (disque `local` = `storage/app/private` pour les pièces jointes), `logging`, `mail`, `queue`, `services`, `session`.

### `database/`

**`migrations/`** — schéma complet, dans l'ordre :

1. `users`, `password_reset_tokens`, `sessions`
2. `cache`, `cache_locks`
3. `jobs`, `job_batches`, `failed_jobs`
4. `sites`
5. `statuts`
6. `type_demandes`
7. `demandes`
8. `documents`
9. `historique_statuts`
10. `periode_sensibles`
11. `notifications`
12. `site_user` (pivot validateurs ↔ sites)
13. Brouillons et compléments : dates rendues facultatives, ajout des statuts `brouillon` et `a_completer`

**`seeders/`**

- `DatabaseSeeder` : 6 statuts, 2 types d'activité (*Recherche scientifique*, *Tournage documentaire*), 2 sites de démonstration et 3 comptes de test :

  | E-mail | Rôle | Mot de passe |
  |---|---|---|
  | `admin@fanamby.org` | admin | `password` |
  | `validateur@fanamby.org` | validateur (Réserve de Lokobe) | `password` |
  | `alice@exemple.com` | demandeur | `password` |

- `NouveauxSitesSeeder` : crée ou met à jour les 5 aires protégées réelles de FANAMBY à partir de `site_coordinates.json`.
- `site_coordinates.json` : polygones GPS produits par `process_geojson.py`.

**`factories/UserFactory.php`** : utilisateurs fictifs pour les tests.

**`donnees_test.sql`** : jeu de données complet pour tester l'application à la main. Il **vide les tables puis les remplit**, et peut être relancé à tout moment pour revenir à l'état de départ. Les dates sont relatives au jour d'exécution, donc les retards, arrivées imminentes et séjours en cours restent cohérents.
- 7 comptes (mot de passe `password`) : `admin@fanamby.org`, `validateur@fanamby.org` (Andrafiamena, Anjozorobe, Loky), `hery@fanamby.org` (Anjozorobe, Menabe), et les demandeurs `alice@`, `rado@`, `sarah@`, `tiana@exemple.com`. L'Allée des Baobabs n'a volontairement aucun validateur.
- Les 5 aires protégées avec leurs contours réels (simplifiés à ~100 m), 4 types d'activité et 8 périodes sensibles (passées, en cours, à venir, bloquantes ou non).
- 22 demandes couvrant tous les statuts et cas : brouillons incomplets, arrivées imminentes ou dépassées, retards, conflits de calendrier, compléments en attente (dont une pièce manquante), séjours en cours, demandes déposées par un admin et par un validateur (auto-validation interdite), décisions de l'année précédente.
- Historique des décisions et notifications cohérents avec ces dossiers.

```bash
mysql -u root projet_stage < database/donnees_test.sql
```

Les pièces jointes sont des lignes en base. Les fichiers correspondants doivent exister dans `storage/app/private/demandes/{id}/`, sinon leur téléchargement affiche « Fichier introuvable ».

### `public/`

| Élément | Rôle |
|---|---|
| `index.php` | Point d'entrée de toutes les requêtes HTTP. |
| `assets/` | Logo FANAMBY (192 px) et image de fond des pages de connexion (1200 px et 2000 px). |
| `build/` | JavaScript et CSS compilés par `npm run build` (non versionné). |
| `hot` | Créé par `npm run dev`. S'il reste présent alors que le serveur Vite est arrêté, les pages s'affichent vides : il faut le supprimer. |
| `favicon.ico`, `robots.txt` | Icône et consignes pour les moteurs de recherche. |

### `resources/`

#### `resources/css/app.css`

Formes et espacements inspirés du `DESIGN.md` de Linear ([awesome-design-md](https://github.com/VoltAgent/awesome-design-md)), en gardant les couleurs et les polices FANAMBY :
- **arrondis** `--r-xs` 4px (petits éléments), 6px (étiquettes), `--r-sm` 8px (boutons, champs), `--r` 12px (cartes), `--r-lg` 16px, `--r-pill` (onglets, badges de statut) ;
- **espacements** sur une base de 4px (`--s-1` à `--s-12`), cartes rembourrées à 24px, 24px entre les blocs ;
- **profondeur** par niveaux de surface et filets fins, sans ombre (sauf les menus flottants) ;
- **composants** : boutons compacts 8×14px, champs 8×12px, anneau de focus de 2px, onglets en contrôle segmenté où l'onglet choisi est surélevé ;
- **mise en page** : contenu limité à 1280px (`--contenu-max`), barre du haut de 56px, zones tactiles de 40 à 44px sur écran tactile.

Feuille de style globale : variables de la charte FANAMBY, typographie, tableaux, formulaires, boutons, badges de statut (une forme différente par statut, pas seulement une couleur), hachures des périodes sensibles, barres de filtres, onglets, messages flash, squelettes de chargement et pages d'authentification.

#### `resources/js/`

| Élément | Rôle |
|---|---|
| `app.js` | Démarre l'application Vue et Inertia, charge les pages à la demande, active Ziggy (fonction `route()` côté Vue). |
| `bootstrap.js` | Configure axios. |

**`Pages/`** — un fichier par écran

| Dossier / fichier | Écran |
|---|---|
| `Welcome.vue` | Accueil public avec carte des sites. |
| `Dashboard.vue` | Tableau de bord : en-tête et choix de l'espace selon le rôle, styles communs. |
| `Dashboard/Partials/` | `EspaceDemandeur`, `EspaceValidateur`, `EspaceAdmin` (un espace par rôle, voir 3.2 et 3.3), `CarteSites` (carte, curseur de date et légende) et `LigneDossier` (ligne d'un dossier en attente avec ses alertes). |
| `Auth/` | Connexion, inscription, mot de passe oublié, réinitialisation, confirmation du mot de passe, vérification de l'e-mail. |
| `Profile/` | `Edit.vue` et ses 3 sous-formulaires dans `Partials/` (informations, mot de passe, suppression du compte). |
| `Demandes/` | `Index` (mes demandes), `Create` (création et modification, brouillon, complément), `Show` (détail). |
| `Validateur/Demandes/` | `Index` (liste filtrée et export), `Show` (dossier et décision). |
| `Admin/Sites/` | `Index`, `Create`, `Edit` (avec dessin du polygone). |
| `Admin/PeriodeSensibles/` | `Index`, `Create`, `Edit`. |
| `Admin/TypeDemandes/` | `Index`, `Create`, `Edit`. |
| `Admin/Users/` | `Index`, `Edit` (rôle et sites). |

**`Layouts/`**

- `AuthenticatedLayout.vue` : menu latéral organisé selon le rôle (Mon espace, Instruction, Administration), cloche de notifications, menu mobile, messages flash, squelette pendant les changements de page.
- `GuestLayout.vue` : mise en page des écrans d'authentification (formulaire et photo de fond).

**`Components/`**

| Composant | Rôle |
|---|---|
| `DashboardMap.vue` | Carte MapLibre de plusieurs sites : polygones, marqueurs animés, fiches au clic (avec surface), cadrage automatique, centrage sur un site, choix du fond de carte, plein écran, vue 3D du terrain (`voir3d`). Options facultatives : `etats` (coloration selon une date ou des dates), `charges` (cercles de charge), et le mode formulaire (`selectable`, `selectedId`) : choix du site au clic, mise en avant du site choisi, couleurs et hachures selon les dates. Chargée seulement quand elle est affichée (tableau de bord). |
| `MapComponent.vue` | Carte d'un seul site avec dessin du polygone (outil MapboxDraw, chargé uniquement en mode édition), utilisée par les pages admin des sites. |
| `Icon.vue` | Jeu d'icônes Phosphor (style *fill*) intégré directement en SVG. |
| `StatutSteps.vue` | Frise d'avancement d'une demande (Déposée → Analyse → Décision). |
| `PageSkeleton.vue`, `TableSkeleton.vue` | Squelettes de chargement d'une page entière ou d'un tableau. |
| `Pagination.vue` | Pagination des listes Laravel. |
| `PasswordInput.vue` | Champ mot de passe avec bouton afficher/masquer. |
| `Modal.vue` | Fenêtre de confirmation (suppression du compte). |
| `TextInput`, `InputLabel`, `InputError`, `Checkbox`, `PrimaryButton`, `SecondaryButton`, `DangerButton` | Éléments de formulaire de base. |
| `ApplicationLogo`, `Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink` | Hérités de Breeze, **plus utilisés** (peuvent être supprimés). |

**`composables/`**

- `useListFilters.js` : synchronise les filtres d'une liste avec l'URL (recherche temporisée à 350 ms, autres filtres immédiats, réinitialisation).
- `useNavigation.js` : détecte les navigations Inertia en cours et choisit le squelette à afficher selon la page de destination.

**`utils/`**

- `statut.js` : classe de badge par statut, référence `FAN-AAAA-NNNN`, formatage des dates, nombre de jours écoulés.
- `openPdf.js` : récupère un PDF avec la session de l'utilisateur et l'ouvre dans un nouvel onglet.
- `carte.js` : outils des cartes : contrôle « Plan / Satellite / Relief », calcul de la surface d'un contour en km², motif de hachures des périodes sensibles, source des altitudes pour la 3D.

#### `resources/views/`

- `app.blade.php` : page HTML racine dans laquelle Inertia monte l'application (polices, routes Ziggy, fichiers Vite).
- `pdf/autorisation.blade.php` : modèle du PDF d'autorisation officielle.
- `pdf/cahier_charges.blade.php` : modèle du PDF du cahier des charges.

### `routes/`

| Fichier | Contenu |
|---|---|
| `web.php` | Accueil public, tableau de bord, espace connecté (profil, demandes, notifications, documents, PDF), groupe `admin/*` (`can:is-admin`), groupe `validateur/*` (`can:is-validateur`). |
| `auth.php` | Routes d'authentification Breeze. |
| `console.php` | Commandes Artisan personnalisées (aucune pour l'instant). |

### `storage/`

| Dossier | Contenu |
|---|---|
| `app/private/demandes/{id}/` | Pièces jointes des demandes. Jamais accessibles par URL directe. |
| `app/public/` | Fichiers publics (non utilisé). |
| `framework/` | Cache, sessions et vues Blade compilées. |
| `logs/` | Journal `laravel.log`. |

---

## 6. Tests automatisés

69 tests PHPUnit, exécutés avec `php artisan test` sur la base MySQL `projet_stage_test` (à créer une fois, voir section 7). Chaque test démarre sur des tables vides : la base de l'application `projet_stage` n'est jamais touchée.

| Fichier | Ce qu'il vérifie |
|---|---|
| `Feature/DemandeParcoursTest` | Brouillon minimal, brouillon invisible au validateur, reprise d'un brouillon avec ses pièces, suppression réservée aux brouillons, cycle complet de complément, détection des chevauchements (avertissement accepté, blocage refusé), périodes envoyées au formulaire, tableau de bord demandeur, filtres et pagination validateur, export CSV, recherche des sites. |
| `Feature/DashboardTest` | File du validateur triée par urgence et limitée à ses sites, activité du mois, indicateurs de l'admin (délai médian, taux de validation sur les dossiers décidés, compléments), sites sans validateur, présence sur le terrain, fréquentation, pièces manquantes et délai habituel côté demandeur. |
| `Feature/DemandeSecurityTest` | Cloisonnement des pièces jointes et des PDF par site et par propriétaire, PDF réservés aux demandes validées, date passée et format de fichier refusés, détail réservé au propriétaire, suppression interdite d'un site ou d'un type utilisé. |
| `Feature/ProfileTest` | Modification et suppression du profil. |
| `Feature/Auth/*` | Connexion, inscription, vérification de l'e-mail, confirmation, réinitialisation et changement du mot de passe. |
| `Unit/DemandeStatutWorkflowTest` | Transitions de statut autorisées et interdites. |
| `Unit/DemandeDecisionRulesTest` | Interdiction de l'auto-validation et choix des décideurs à notifier. |
| `Unit/DemandeForStatsViewerTest` | Périmètre des statistiques : admin = tout, validateur = ses sites. |


---

## 7. Installation et lancement

```bash
composer install
npm install
cp .env.example .env          # vérifier la connexion MySQL (projet_stage, root, sans mot de passe par défaut)
php artisan key:generate

# Bases MySQL : application et tests
mysql -u root -e "CREATE DATABASE IF NOT EXISTS projet_stage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS projet_stage_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan migrate --seed
php artisan db:seed --class=NouveauxSitesSeeder   # aires protégées réelles
# ou, pour un jeu de données complet de test (remplace les données) :
# mysql -u root projet_stage < database/donnees_test.sql

# Développement (serveur PHP et Vite en parallèle)
composer dev

# Tests automatisés (base projet_stage_test)
php artisan test

# Production : compiler le front-end
npm run build
```
