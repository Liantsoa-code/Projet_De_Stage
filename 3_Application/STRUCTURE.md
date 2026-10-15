# Plateforme d'accès aux aires protégées — Fondation FANAMBY

Application web de gestion des demandes d'accès aux aires protégées gérées par la Fondation FANAMBY (Madagascar).
Chercheurs, équipes de tournage et partenaires y déposent leurs demandes. Les validateurs les instruisent. Une fois la demande validée, l'autorisation officielle et le cahier des charges sont générés en PDF.

**Stack :** Laravel 13 · Vue 3 · Inertia.js 2 · TailwindCSS 3 · MapLibre GL · Chart.js · DomPDF
**Base de données :** MySQL / MariaDB uniquement : `projet_stage` pour l'application, `projet_stage_test` pour les tests automatisés.

> **Lire les références au code.** Les liens de la forme [`Fichier.php:42`](#) ouvrent le fichier à la ligne où la fonctionnalité est codée. Les chemins sont relatifs à `3_Application/`. Les numéros de ligne correspondent à l'état du code au 15/10/2026 : après une modification d'un fichier, mettre à jour les liens qui le concernent.

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

Les rôles sont contrôlés par trois *gates* déclarées dans `AppServiceProvider` : `is-admin` ([AppServiceProvider.php:30](app/Providers/AppServiceProvider.php#L30)), `is-validateur`, admin inclus ([AppServiceProvider.php:34](app/Providers/AppServiceProvider.php#L34)) et `is-demandeur` ([AppServiceProvider.php:38](app/Providers/AppServiceProvider.php#L38)).
Les droits sur une demande précise (la consulter, la modifier, l'instruire, décider, télécharger ses fichiers) sont réunis dans la policy [DemandePolicy](app/Policies/DemandePolicy.php) (voir section 4).
Elles protègent les groupes de routes `admin/*` ([web.php:39](routes/web.php#L39)) et `validateur/*` ([web.php:46](routes/web.php#L46)). Le menu latéral s'adapte au rôle dans [AuthenticatedLayout.vue:26](resources/js/Layouts/AuthenticatedLayout.vue#L26).

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

Règles du workflow (encodées dans [app/Support/DemandeStatutWorkflow.php](app/Support/DemandeStatutWorkflow.php)) :
- un statut ne peut qu'avancer : pas de retour en arrière, pas de « changement » vers le même statut — ordre des statuts [DemandeStatutWorkflow.php:11](app/Support/DemandeStatutWorkflow.php#L11), contrôle [DemandeStatutWorkflow.php:59](app/Support/DemandeStatutWorkflow.php#L59) et [DemandeStatutWorkflow.php:74](app/Support/DemandeStatutWorkflow.php#L74) ;
- aucune décision n'est possible sur un brouillon ni sur un dossier clôturé (validé ou rejeté) — [DemandeStatutWorkflow.php:55](app/Support/DemandeStatutWorkflow.php#L55), statuts finaux [DemandeStatutWorkflow.php:23](app/Support/DemandeStatutWorkflow.php#L23) ;
- un complément ne peut être demandé que depuis `soumise` ou `en_analyse` — [DemandeStatutWorkflow.php:21](app/Support/DemandeStatutWorkflow.php#L21) et [DemandeStatutWorkflow.php:63](app/Support/DemandeStatutWorkflow.php#L63) ;
- un dossier « à compléter » renvoyé par le demandeur repasse directement en `en_analyse` — [DemandeController.php:181](app/Http/Controllers/DemandeController.php#L181).

Les statuts proposés dans le formulaire de décision sont calculés par `allowedTargetStatuts` ([DemandeStatutWorkflow.php:83](app/Support/DemandeStatutWorkflow.php#L83)).

Chaque changement de statut est historisé : auteur, date, ancien et nouveau statut, commentaire ([Validateur/DemandeController.php:189](app/Http/Controllers/Validateur/DemandeController.php#L189)). La **soumission** l'est aussi, au nom du demandeur ([DemandeController.php:273](app/Http/Controllers/DemandeController.php#L273)) ; un brouillon n'a pas encore d'historique. Ainsi `demandes.statut_id` est toujours le dernier statut historisé, ou `brouillon` s'il n'y a pas d'historique (voir [Conception_BDD.md](../2_Conception/Conception_BDD.md), section 6.3). Si l'auteur supprime ensuite son compte, la décision reste dans l'historique, sans auteur (affichée « Compte supprimé ») : clé étrangère `nullOnDelete` dans [2026_10_12_090000_historique_statuts_user_null_on_delete.php:21](database/migrations/2026_10_12_090000_historique_statuts_user_null_on_delete.php#L21).

Un changement de statut est enregistré **en une seule fois** (statut, historique et notification ensemble) et **sous verrou** sur la demande ([Validateur/DemandeController.php:173-202](app/Http/Controllers/Validateur/DemandeController.php#L173-L202)). De deux décisions prises au même moment, seule la première s'applique ; la seconde est refusée si elle n'est plus permise par le nouveau statut ([Validateur/DemandeController.php:177](app/Http/Controllers/Validateur/DemandeController.php#L177)). De même, le renvoi d'un complément par le demandeur n'est pas enregistré si un validateur a changé le statut entre-temps ([DemandeController.php:164-169](app/Http/Controllers/DemandeController.php#L164-L169)).

---

## 3. Fonctionnalités

### 3.1 Espace public

- **Page d'accueil** (`/`) : présentation du service en 3 étapes, carte interactive de toutes les aires protégées (polygones, marqueurs animés, fiche du site au clic avec sa surface). La liste des sites permet de centrer la carte sur l'un d'eux.
  - Code : route [web.php:18](routes/web.php#L18), contrôleur [AccueilController.php](app/Http/Controllers/AccueilController.php) (liste des sites gardée en cache une journée et vidée dès qu'un site est créé, modifié ou supprimé : [Site.php:19-35](app/Models/Site.php#L19-L35)), page [Welcome.vue](resources/js/Pages/Welcome.vue), carte [DashboardMap.vue](resources/js/Components/DashboardMap.vue) (centrage `focusSite` [DashboardMap.vue:106](resources/js/Components/DashboardMap.vue#L106)).
- **Authentification** (Laravel Breeze) : inscription avec indicateurs de robustesse et de confirmation du mot de passe, connexion, déconnexion, mot de passe oublié et réinitialisation, vérification de l'adresse e-mail, confirmation du mot de passe. Les champs mot de passe ont un bouton afficher/masquer.
  - Code : routes [auth.php](routes/auth.php), contrôleurs [app/Http/Controllers/Auth/](app/Http/Controllers/Auth/), pages [resources/js/Pages/Auth/](resources/js/Pages/Auth/) (inscription [Register.vue](resources/js/Pages/Auth/Register.vue)), champ [PasswordInput.vue](resources/js/Components/PasswordInput.vue), limitation des tentatives [LoginRequest.php](app/Http/Requests/Auth/LoginRequest.php).

### 3.2 Demandeur

**Tableau de bord** — « où en sont mes dossiers, et que dois-je faire ? »
- Encadrés d'alerte pour chaque dossier « à compléter », avec le dernier commentaire du validateur, son auteur et la date.
- Liste des demandes en cours, avec une frise d'avancement du statut et la prochaine étape :
  - brouillon : nombre de **pièces encore à joindre**, dates à préciser, ou « prêt à être envoyé » ;
  - en instruction : nombre de jours d'attente et **délai habituel de décision** (médiane du site s'il compte au moins 3 décisions, sinon celle de la plateforme).
- Séjours validés à venir, avec téléchargement direct des PDF.
- Code : données [StatsDemandeur.php:20](app/Support/StatsDemandeur.php#L20), délai habituel par site [DelaiDecision.php:47](app/Support/DelaiDecision.php#L47) (seuil de 3 décisions [L18](app/Support/DelaiDecision.php#L18), médiane [L58](app/Support/DelaiDecision.php#L58)) ; affichage [EspaceDemandeur.vue](resources/js/Pages/Dashboard/Partials/EspaceDemandeur.vue), frise [StatutSteps.vue](resources/js/Components/StatutSteps.vue).

**Mes demandes** (`/demandes`)
- Liste paginée (10 par page) avec onglets de filtre par statut et compteur par statut.
- Actions selon le statut : *Continuer* (brouillon), *Compléter* (à compléter), PDF (validée).
- Code : [DemandeController.php:26](app/Http/Controllers/DemandeController.php#L26) (`index`, pagination [ligne 35](app/Http/Controllers/DemandeController.php#L35), compteurs [ligne 39](app/Http/Controllers/DemandeController.php#L39)) ; page [Demandes/Index.vue](resources/js/Pages/Demandes/Index.vue).

**Nouvelle demande / modification** (`/demandes/create`, `/demandes/{id}/edit`)
- **Formulaire en étapes** : 1. Activité et site, 2. Dates et équipe, 3. Objet de la mission, 4. Pièces à joindre, 5. Récapitulatif. Une seule étape est affichée à la fois, avec la carte et la vérification du calendrier toujours visibles à côté.
  - Liste des étapes [Create.vue:129](resources/js/Pages/Demandes/Create.vue#L129), barre d'étapes [Create.vue:255](resources/js/Pages/Demandes/Create.vue#L255). La page garde les données, la vérification et l'envoi ; chaque étape est un composant de [Demandes/Partials/](resources/js/Pages/Demandes/Partials/), affiché sous le titre de l'étape ([Create.vue:280](resources/js/Pages/Demandes/Create.vue#L280)).
  - **Selon le type** : l'étape « Pièces à joindre » n'apparaît que si le type d'activité exige des pièces ([Create.vue:137](resources/js/Pages/Demandes/Create.vue#L137)) ; composant [EtapePieces.vue](resources/js/Pages/Demandes/Partials/EtapePieces.vue). Choisir un fichier efface aussitôt le message « pièce obligatoire » de cette pièce ([Create.vue:80](resources/js/Pages/Demandes/Create.vue#L80)).
  - **Vérification à chaque étape** : *Suivant* contrôle les champs de l'étape avec les mêmes exigences que le serveur (champs obligatoires, date passée, fin avant début, équipe d'au moins une personne, période bloquante, pièces manquantes) et place le curseur sur le premier champ en erreur — [Create.vue:142](resources/js/Pages/Demandes/Create.vue#L142) (`erreursEtape`), [Create.vue:202](resources/js/Pages/Demandes/Create.vue#L202) (`suivant`). Une étape n'est accessible dans la barre que si les précédentes sont complètes ([Create.vue:164](resources/js/Pages/Demandes/Create.vue#L164)) ; une étape en erreur y apparaît en rouge.
  - **Récapitulatif** : tout le dossier (activité, site, dates et nombre de jours, équipe, résultat du calendrier, description, pièces fournies ou manquantes) avec un lien *Modifier* par partie. L'envoi ne se fait que depuis cette étape ; si une étape est incomplète, le formulaire y retourne ([Create.vue:210](resources/js/Pages/Demandes/Create.vue#L210), `valider`), récapitulatif [EtapeRecap.vue](resources/js/Pages/Demandes/Partials/EtapeRecap.vue).
  - **Erreurs du serveur** : le formulaire revient à la première étape concernée — [Create.vue:221](resources/js/Pages/Demandes/Create.vue#L221) (`allerAuxErreurs`).
  - **Reprise d'un brouillon ou d'un complément** : le formulaire s'ouvre sur la première étape incomplète, sinon sur le récapitulatif ([Create.vue:168](resources/js/Pages/Demandes/Create.vue#L168)). *Enregistrer le brouillon* reste disponible à chaque étape.
- Choix du site, du type d'activité, des dates, de la taille de l'équipe et description du projet.
- **Pièces justificatives dynamiques** : la liste des documents exigés dépend du type d'activité choisi. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, ODT, 20 Mo maximum par fichier.
  - Règles de validation [DemandeRequest.php:44-50](app/Http/Requests/DemandeRequest.php#L44-L50), formats [DemandeDossier.php:20](app/Support/DemandeDossier.php#L20).
- **Carte de choix du site** : toutes les aires protégées sont affichées. Un clic sur une zone ou un marqueur choisit le site (synchronisé avec la liste déroulante), et le survol affiche son nom, sa surface et son état sur les dates choisies.
  - Événements de la carte [DashboardMap.vue:177](resources/js/Components/DashboardMap.vue#L177), mise en avant du site choisi [DashboardMap.vue:390](resources/js/Components/DashboardMap.vue#L390), infobulle [DashboardMap.vue:91](resources/js/Components/DashboardMap.vue#L91).
- **Carte colorée selon les dates** : dès que les dates sont saisies, chaque site prend la couleur de son état (vert = ouvert, hachures ocre = période sensible, hachures rouges = période bloquante), avec une légende. Le demandeur voit d'un coup d'œil quels sites sont ouverts sur son séjour.
  - Calcul des états [Create.vue:58](resources/js/Pages/Demandes/Create.vue#L58), coloration [DashboardMap.vue:375](resources/js/Components/DashboardMap.vue#L375), hachures [carte.js:112](resources/js/utils/carte.js#L112).
- **Fiche du site choisi** : nom, surface calculée à partir du contour, description.
  - Colonne [VerificationCalendrier.vue](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue) : fiche [L51](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue#L51), surface [L25](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue#L25), calcul de surface [carte.js:93](resources/js/utils/carte.js#L93).
- **Vérification du calendrier pendant la saisie** : les périodes sensibles à venir du site sont affichées et les chevauchements avec les dates choisies sont signalés immédiatement (avertissement ou blocage).
  - Périodes envoyées au formulaire [DemandeController.php:240](app/Http/Controllers/DemandeController.php#L240) ; détection côté Vue [Create.vue:53-72](resources/js/Pages/Demandes/Create.vue#L53-L72) (état du calendrier partagé par le récapitulatif et la colonne de vérification : `calendrier`, [L68](resources/js/Pages/Demandes/Create.vue#L68)).
- **Brouillon** : seuls le site et le type sont obligatoires. Le brouillon peut être repris plus tard, et les pièces déjà envoyées sont conservées.
  - [DemandeRequest.php:32-42](app/Http/Requests/DemandeRequest.php#L32-L42) (brouillon ou envoi : `estSoumission`, [L78](app/Http/Requests/DemandeRequest.php#L78)), choix brouillon / envoi [Create.vue:90](resources/js/Pages/Demandes/Create.vue#L90).
- **Soumission** : tous les champs et toutes les pièces sont exigés (sauf celles déjà fournies). La date de début ne peut pas être passée.
  - Pièces déjà fournies [DemandeRequest.php:45-48](app/Http/Requests/DemandeRequest.php#L45-L48), date [DemandeRequest.php:37](app/Http/Requests/DemandeRequest.php#L37), création [DemandeController.php:85](app/Http/Controllers/DemandeController.php#L85), modification [DemandeController.php:150](app/Http/Controllers/DemandeController.php#L150).
- **Complément** : le commentaire du validateur s'affiche en haut du formulaire. Le demandeur peut joindre un message, puis le dossier est renvoyé en analyse. Si le validateur a changé le statut pendant la saisie (par exemple un rejet), rien n'est enregistré et un message l'explique.
  - Commentaire affiché [DemandeController.php:141-146](app/Http/Controllers/DemandeController.php#L141-L146), renvoi [DemandeController.php:181-186](app/Http/Controllers/DemandeController.php#L181-L186), statut changé entre-temps [DemandeController.php:200-203](app/Http/Controllers/DemandeController.php#L200-L203).
- Un nouveau fichier remplace l'ancien du même type. Si le type d'activité change, les pièces qui ne correspondent plus sont supprimées.
  - [DemandeDossier.php:52](app/Support/DemandeDossier.php#L52) (`syncDocuments`).

**Détail d'une demande** (`/demandes/{id}`)
- Informations du séjour, pièces jointes téléchargeables.
- **Timeline interactive du dossier** (« Suivi du dossier »), partagée avec la fiche du validateur — composant [TimelineDemande.vue](resources/js/Components/TimelineDemande.vue) :
  - étapes passées tirées de l'historique des statuts, avec des libellés parlants (« Demande envoyée », « Prise en charge », « Complément demandé », « Dossier complété et renvoyé », « Demande validée »…) et le nombre de jours écoulés depuis l'étape précédente (« +3 j ») — [TimelineDemande.vue:39](resources/js/Components/TimelineDemande.vue#L39) ; l'étape « Brouillon créé » ouvre la timeline si le dossier a été préparé en brouillon ;
  - **étape actuelle** mise en avant et ouverte par défaut ;
  - **étapes à venir** en grisé : envoi, prise en charge, renvoi du complément, décision avec une **date estimée** (« vers le 28/10 », d'après le délai habituel de décision du site), puis arrivée et fin du séjour — [TimelineDemande.vue:55](resources/js/Components/TimelineDemande.vue#L55) (estimation), [TimelineDemande.vue:68](resources/js/Components/TimelineDemande.vue#L68) (séjour). Pour une demande validée, l'arrivée et la fin du séjour deviennent des étapes passées une fois leur date atteinte ;
  - **chaque étape s'ouvre au clic** (boutons accessibles au clavier, `aria-expanded`) : date et heure, auteur, commentaire complet, **pièces déposées à cette étape** (téléchargeables) et, sur le dernier envoi, **le résultat de l'analyse du calendrier**. Un lien *Tout déplier / Tout replier* ouvre toutes les étapes — rattachement des pièces [TimelineDemande.vue:102](resources/js/Components/TimelineDemande.vue#L102), analyse [TimelineDemande.vue:111](resources/js/Components/TimelineDemande.vue#L111) ;
  - côté demandeur, les agents ne sont pas nommés (« Validateur », « Administration », « Vous ») — [TimelineDemande.vue:29](resources/js/Components/TimelineDemande.vue#L29).
  - Données : historique trié par date ([DemandeController.php:58](app/Http/Controllers/DemandeController.php#L58)), délai habituel transmis tant que le dossier est en instruction ([DemandeController.php:72](app/Http/Controllers/DemandeController.php#L72)).
- Mise en avant de la demande de complément en cours.
- Boutons *Reprendre le brouillon*, *Compléter le dossier* et *Supprimer* (brouillon uniquement, avec confirmation).
- Ouverture des PDF officiels lorsque la demande est validée.
- Code : [DemandeController.php:52](app/Http/Controllers/DemandeController.php#L52) (`show`, complément en cours [ligne 64](app/Http/Controllers/DemandeController.php#L64)), suppression [DemandeController.php:222](app/Http/Controllers/DemandeController.php#L222) ; page [Demandes/Show.vue](resources/js/Pages/Demandes/Show.vue).

### 3.3 Validateur et admin (instruction)

Définitions communes : un dossier est **en retard** après 7 jours sans décision ([StatsInstruction.php:21](app/Support/StatsInstruction.php#L21)) ; une **arrivée est imminente** quand le séjour commence dans 14 jours ou moins (ou a déjà commencé) sans décision ([StatsInstruction.php:24](app/Support/StatsInstruction.php#L24)). Les brouillons sont exclus de toutes les statistiques, et un validateur ne voit que ses sites ([StatsInstruction.php:31](app/Support/StatsInstruction.php#L31), [Demande.php:56](app/Models/Demande.php#L56)).

**Tableau de bord du validateur** — « qu'est-ce que je dois traiter maintenant ? » — [StatsValidateur.php:15](app/Support/StatsValidateur.php#L15), affichage [EspaceValidateur.vue](resources/js/Pages/Dashboard/Partials/EspaceValidateur.vue)
- Indicateurs : dossiers à traiter (dont non pris en charge), arrivées imminentes, retards, conflits de calendrier, dossiers chez le demandeur (compléments demandés) — [StatsInstruction.php:78](app/Support/StatsInstruction.php#L78).
- **Ma file de traitement** : les dossiers en attente triés par urgence (arrivées imminentes d'abord, puis retards, puis les autres), avec pour chacun l'arrivée (« dans 5 j », « dépassée de 2 j »), le nombre de jours d'attente et l'alerte de période sensible — [StatsInstruction.php:43](app/Support/StatsInstruction.php#L43), ligne affichée [LigneDossier.vue](resources/js/Pages/Dashboard/Partials/LigneDossier.vue).
- **En attente du demandeur** : compléments demandés, les plus anciens d'abord, avec le nombre de jours écoulés (pour relancer) — [StatsValidateur.php:31](app/Support/StatsValidateur.php#L31).
- **Mon activité du mois** : prises en charge, compléments demandés, validations, rejets, et délai médian de décision sur ses sites — [StatsValidateur.php:57](app/Support/StatsValidateur.php#L57).
- Carte de ses sites (voir ci-dessous).

**Tableau de bord de l'admin** — « le service fonctionne-t-il, et quel est l'effet sur les aires protégées ? » — [StatsAdmin.php:19](app/Support/StatsAdmin.php#L19), affichage [EspaceAdmin.vue](resources/js/Pages/Dashboard/Partials/EspaceAdmin.vue)
- Indicateurs : **délai médian de décision** et part des dossiers décidés en 15 jours ou moins ; **taux de validation** (calculé sur les seuls dossiers décidés) et part des dossiers passés par un complément ; dossiers en attente (arrivées imminentes, retards) ; **personnes sur le terrain aujourd'hui** — décisions [StatsAdmin.php:22](app/Support/StatsAdmin.php#L22), objectif 15 jours [StatsAdmin.php:17](app/Support/StatsAdmin.php#L17), pourcentages [StatsAdmin.php:151](app/Support/StatsAdmin.php#L151).
- **Points d'attention** : sites sans validateur (les nouvelles demandes n'y sont notifiées à personne), puis dossiers urgents, en retard ou en conflit.
- Histogramme des demandes reçues par mois, **comparé à l'année précédente** — [StatsAdmin.php:136](app/Support/StatsAdmin.php#L136).
- **Fréquentation autorisée** sur les 12 prochains mois, par site : séjours, personnes et **personnes × jours** (la pression humaine réelle sur le site) — [StatsAdmin.php:114](app/Support/StatsAdmin.php#L114).
- **Charge des validateurs** : dossiers en attente sur les sites de chacun — [StatsAdmin.php:92](app/Support/StatsAdmin.php#L92).
- **Sur le terrain aujourd'hui** : séjours en cours (demandeur, site, équipe, date de fin) — [StatsAdmin.php:64](app/Support/StatsAdmin.php#L64).
- Répartition par type d'activité.
- Carte de toutes les aires protégées (voir ci-dessous).

**Carte du tableau de bord** (admin et validateur) — données [StatsInstruction.php:93](app/Support/StatsInstruction.php#L93), composant [CarteSites.vue](resources/js/Pages/Dashboard/Partials/CarteSites.vue)
- **Choix de la date** sur l'année à venir : les sites restreints ce jour-là sont colorés (hachures ocre = période sensible, hachures rouges = bloquant), avec un résumé (« 2 bloqués · 1 sensible »). Pour viser un jour précis : champ date et boutons ‹ › jour par jour ([CarteSites.vue:33-42](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L33-L42)), sauts à la période précédente ou suivante ([L57-63](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L57-L63)). Le curseur reste disponible pour parcourir l'année : sa piste montre en couleur les jours sensibles (ocre) et bloquants (rouge), tous sites confondus ([L65-85](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L65-L85)), avec les repères de mois en dessous ([L87-100](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L87-L100)).
- **Charge par site** : un cercle bleu dont la taille suit le nombre de dossiers en attente, avec un contour rouge si l'un d'eux est en retard ou en conflit de calendrier. La fiche du site au clic détaille ces chiffres — [DashboardMap.vue:362](resources/js/Components/DashboardMap.vue#L362), fiche [DashboardMap.vue:56](resources/js/Components/DashboardMap.vue#L56).

**Dossiers à valider / Toutes les demandes** (`/validateur/demandes`)
- Liste paginée (15 par page) avec recherche (nom ou e-mail du demandeur, site, numéro `#id`) et filtres par statut, par site et par période (du / au) — [Validateur/DemandeController.php:27](app/Http/Controllers/Validateur/DemandeController.php#L27), filtres [Validateur/DemandeController.php:95-133](app/Http/Controllers/Validateur/DemandeController.php#L95-L133) ; page [Validateur/Demandes/Index.vue](resources/js/Pages/Validateur/Demandes/Index.vue).
- Indicateur visuel des dossiers qui chevauchent une période sensible.
- **Export CSV** des demandes filtrées, compatible Excel (séparateur `;`, encodage UTF-8 avec BOM). Il contient demandeur, site, dates, statut, date de décision, délai en jours et nombre de chevauchements — [Validateur/DemandeController.php:49-90](app/Http/Controllers/Validateur/DemandeController.php#L49-L90), lien d'export [Validateur/Demandes/Index.vue:31](resources/js/Pages/Validateur/Demandes/Index.vue#L31).

**Dossier complet** (`/validateur/demandes/{id}`) — [Validateur/DemandeController.php:135](app/Http/Controllers/Validateur/DemandeController.php#L135), page [Validateur/Demandes/Show.vue](resources/js/Pages/Validateur/Demandes/Show.vue)
- Identité du demandeur, séjour, équipe, date de dépôt, alertes de chevauchement, pièces jointes (avec signalement des pièces manquantes [Show.vue:54](resources/js/Pages/Validateur/Demandes/Show.vue#L54)).
- **Timeline interactive du dossier**, la même que côté demandeur (voir 3.2), avec en plus le nom et le rôle de l'auteur de chaque étape. L'historique est trié par date ([Validateur/DemandeController.php:141](app/Http/Controllers/Validateur/DemandeController.php#L141)) et la décision estimée d'après le délai habituel du site ([Validateur/DemandeController.php:157](app/Http/Controllers/Validateur/DemandeController.php#L157)).
- Alerte lorsque le dossier attend une décision depuis plus de 7 jours — [Show.vue:57](resources/js/Pages/Validateur/Demandes/Show.vue#L57).
- **Formulaire de décision** : seules les transitions autorisées sont proposées (*Prendre en charge*, *Demander un complément*, *Valider*, *Rejeter*). Le commentaire est obligatoire — libellés [Show.vue:20](resources/js/Pages/Validateur/Demandes/Show.vue#L20), validation [DecisionRequest.php:22](app/Http/Requests/DecisionRequest.php#L22), enregistrement [Validateur/DemandeController.php:163](app/Http/Controllers/Validateur/DemandeController.php#L163).
- Un admin ou un validateur ne peut pas statuer sur **sa propre demande** : un autre décideur doit le faire — `DemandePolicy::decider` [DemandePolicy.php:76](app/Policies/DemandePolicy.php#L76), vérifiée avant la validation par [DecisionRequest.php:17](app/Http/Requests/DecisionRequest.php#L17).
- Si un autre décideur a clôturé le dossier pendant que la page était ouverte, la décision est refusée avec un message (« Cette demande est clôturée… ») — [Validateur/DemandeController.php:177-181](app/Http/Controllers/Validateur/DemandeController.php#L177-L181).

### 3.4 Administration (admin uniquement)

| Écran | Fonctionnalités | Code |
|---|---|---|
| **Sites protégés** (`/admin/sites`) | Liste avec recherche et nombre de demandes par site. Création et modification avec **dessin du polygone sur carte** (outil polygone, déplacement des points, effacement). Suppression impossible si des demandes y sont rattachées. | [SiteController.php:13](app/Http/Controllers/Admin/SiteController.php#L13), suppression [SiteController.php:66](app/Http/Controllers/Admin/SiteController.php#L66), dessin [MapComponent.vue](resources/js/Components/MapComponent.vue), pages [Admin/Sites/](resources/js/Pages/Admin/Sites/) |
| **Périodes sensibles** (`/admin/periode-sensibles`) | Périodes de restriction par site, de niveau **bloquant** (la soumission est refusée) ou **avertissement** (la soumission est acceptée mais le conflit est signalé). Filtre par site. Par défaut, seules les périodes en cours ou à venir sont affichées ; une option montre aussi l'historique. | [PeriodeSensibleController.php:13](app/Http/Controllers/Admin/PeriodeSensibleController.php#L13), pages [Admin/PeriodeSensibles/](resources/js/Pages/Admin/PeriodeSensibles/) |
| **Types de demandes** (`/admin/type-demandes`) | Types d'activité avec leurs **pièces requises** et leurs **règles spécifiques** (reprises dans le cahier des charges), saisies ligne par ligne. Renommer une pièce garde les fichiers déjà déposés ; retirer une pièce ne supprime pas les fichiers des dossiers soumis. Une même pièce ne peut pas figurer deux fois. Suppression impossible si des demandes utilisent le type. | [TypeDemandeController.php:25](app/Http/Controllers/Admin/TypeDemandeController.php#L25), validation [TypeDemandeController.php:60](app/Http/Controllers/Admin/TypeDemandeController.php#L60), mise à jour des listes [TypeDemande.php:33](app/Models/TypeDemande.php#L33), suppression [TypeDemandeController.php:83](app/Http/Controllers/Admin/TypeDemandeController.php#L83), éditeur [ListeEditable.vue](resources/js/Components/ListeEditable.vue), pages [Admin/TypeDemandes/](resources/js/Pages/Admin/TypeDemandes/) |
| **Utilisateurs** (`/admin/users`) | Liste avec recherche (nom, e-mail) et filtre par rôle. Changement de rôle et **affectation des sites** aux validateurs (les sites choisis doivent exister). Les affectations sont retirées si l'utilisateur n'est plus validateur. Un admin ne peut pas se retirer son propre rôle. | [UserController.php:14](app/Http/Controllers/Admin/UserController.php#L14), mise à jour [UserController.php:41](app/Http/Controllers/Admin/UserController.php#L41), propre rôle [UserController.php:52](app/Http/Controllers/Admin/UserController.php#L52), sites [UserController.php:56-66](app/Http/Controllers/Admin/UserController.php#L56-L66), pages [Admin/Users/](resources/js/Pages/Admin/Users/) |

### 3.5 Fonctions transverses

- **Notifications internes** : cloche avec compteur de non-lues dans l'en-tête. Le demandeur est notifié à la soumission et à chaque décision ; les décideurs du site sont notifiés à chaque nouvelle demande et à chaque complément renvoyé. Un clic marque la notification comme lue et ouvre le bon dossier selon le rôle. Le bouton *Tout marquer comme lu* du panneau vide la cloche d'un coup, sans changer de page.
  - Envoi regroupé dans [DemandeNotifications.php](app/Support/DemandeNotifications.php) : soumission [L19](app/Support/DemandeNotifications.php#L19), complément renvoyé [L35](app/Support/DemandeNotifications.php#L35), décision [L53](app/Support/DemandeNotifications.php#L53) ; choix des décideurs [DemandeDecisionRules.php:21](app/Support/DemandeDecisionRules.php#L21), textes [DemandeNotificationMessage.php](app/Support/DemandeNotificationMessage.php), lecture [NotificationController.php:10](app/Http/Controllers/NotificationController.php#L10), tout marquer comme lu [NotificationController.php:35](app/Http/Controllers/NotificationController.php#L35) (bouton [AuthenticatedLayout.vue:159](resources/js/Layouts/AuthenticatedLayout.vue#L159)), partage à chaque page [HandleInertiaRequests.php:41](app/Http/Middleware/HandleInertiaRequests.php#L41), cloche [AuthenticatedLayout.vue](resources/js/Layouts/AuthenticatedLayout.vue).
- **PDF officiels** (DomPDF), générés à la volée pour une demande validée :
  - *Autorisation officielle d'accès* : en-tête Fondation FANAMBY, bénéficiaire, site, dates, équipe — [PdfController.php:15](app/Http/Controllers/PdfController.php#L15), modèle [autorisation.blade.php](resources/views/pdf/autorisation.blade.php) ;
  - *Cahier des charges* : règles spécifiques au type d'activité, avertissements de l'analyse des périodes, engagement et signature du demandeur — [PdfController.php:23](app/Http/Controllers/PdfController.php#L23), modèle [cahier_charges.blade.php](resources/views/pdf/cahier_charges.blade.php).

  Les PDF s'ouvrent dans un nouvel onglet via une requête authentifiée, ce qui évite l'interception par les gestionnaires de téléchargement — [openPdf.js:5](resources/js/utils/openPdf.js#L5).
- **Profil** : modification du nom et de l'e-mail, changement de mot de passe, suppression du compte (avec confirmation par mot de passe). La suppression efface les demandes du compte **et leurs fichiers** ; les décisions qu'il a rendues restent dans l'historique des autres dossiers. Le dernier administrateur ne peut pas supprimer son compte.
  - [ProfileController.php:32](app/Http/Controllers/ProfileController.php#L32) (modification), [ProfileController.php:48](app/Http/Controllers/ProfileController.php#L48) (suppression, dernier admin [ligne 57](app/Http/Controllers/ProfileController.php#L57)), fichiers [DemandeDossier.php:97](app/Support/DemandeDossier.php#L97) ; pages [Profile/](resources/js/Pages/Profile/).
- **Référence officielle** des demandes au format `FAN-AAAA-NNNN`, calculée à un seul endroit, par le serveur : accesseur `reference` du modèle [Demande.php:31](app/Models/Demande.php#L31), envoyé avec chaque demande et repris par les pages, les tableaux de bord et les PDF.
- **Limite d'envois** : 20 créations ou modifications de demande par minute et par utilisateur (envoi de fichiers). Au-delà, le formulaire revient avec le message « Trop d'envois en peu de temps » — limiteur `demandes` [AppServiceProvider.php:43](app/Providers/AppServiceProvider.php#L43), appliqué aux routes `store` et `update` [web.php:29](routes/web.php#L29).
- **Fonds de carte** sur toutes les cartes de sites : plan, satellite (Esri World Imagery) ou relief (OpenTopoMap), plus un bouton plein écran — [carte.js:35](resources/js/utils/carte.js#L35) (`FondsControl`).
- **Vue 3D du terrain** : le bouton « Voir en 3D » de la fiche d'un site (ou « Voir le terrain en 3D » dans le formulaire de demande) incline la carte sur le site avec le relief (exagéré ×1,5) et l'ombrage des pentes. Un clic droit + glisser fait pivoter la vue ; « Revenir en 2D » remet la carte à plat. Les altitudes (Terrain Tiles d'AWS, issues de SRTM) ne sont téléchargées qu'au premier passage en 3D.
  - [DashboardMap.vue:127](resources/js/Components/DashboardMap.vue#L127) (`activer3d`), [DashboardMap.vue:145](resources/js/Components/DashboardMap.vue#L145) (`voir3d`), [DashboardMap.vue:153](resources/js/Components/DashboardMap.vue#L153) (`revenir2d`), source des altitudes [carte.js:83](resources/js/utils/carte.js#L83).
- **Squelettes de chargement** pendant la navigation (tableau de bord, liste, formulaire, détail), affichés seulement si la réponse tarde plus de 120 ms — délai [useNavigation.js:12](resources/js/composables/useNavigation.js#L12), choix du squelette [useNavigation.js:39](resources/js/composables/useNavigation.js#L39), composants [PageSkeleton.vue](resources/js/Components/PageSkeleton.vue) et [TableSkeleton.vue](resources/js/Components/TableSkeleton.vue).
- **Compteurs animés** sur les tableaux de bord validateur et admin, ainsi que sur le compte à rebours « jours avant l'arrivée » de l'espace demandeur : les chiffres défilent de 0 jusqu'à leur valeur et les barres horizontales s'allongent progressivement (0,9 s). Rien ne bouge si l'option « réduire les animations » du système est active, et les lecteurs d'écran annoncent directement la valeur finale — [CompteurAnime.vue](resources/js/Components/CompteurAnime.vue), barres [Dashboard.vue:100](resources/js/Pages/Dashboard.vue#L100).
- **Filtres synchronisés avec l'URL** : les filtres survivent au rechargement et peuvent être partagés ; la recherche texte est temporisée (350 ms) — [useListFilters.js:8](resources/js/composables/useListFilters.js#L8).
- **Interface responsive** avec menu latéral (repliable sur mobile), charte graphique FANAMBY (vert forêt, rouge réservé aux blocages, latérite, rivière) et respect de l'option « réduire les animations » du système — [AuthenticatedLayout.vue](resources/js/Layouts/AuthenticatedLayout.vue), [app.css](resources/css/app.css), [tailwind.config.js](tailwind.config.js).

---

## 4. Règles de sécurité

Les droits sur une demande sont tous dans [DemandePolicy](app/Policies/DemandePolicy.php). Les contrôleurs l'appellent par `Gate::authorize(...)` ; les formulaires de demande et de décision la vérifient avant la validation des champs ([DemandeRequest.php:25](app/Http/Requests/DemandeRequest.php#L25), [DecisionRequest.php:17](app/Http/Requests/DecisionRequest.php#L17)).

| Règle | Où elle est appliquée |
|---|---|
| Un demandeur ne voit, ne modifie et ne supprime que **ses** demandes. | `DemandePolicy::view`, `update`, `delete` — [DemandePolicy.php:19](app/Policies/DemandePolicy.php#L19) |
| Seuls les brouillons et les dossiers « à compléter » sont modifiables ; seuls les brouillons sont supprimables. | `DemandePolicy::update` [DemandePolicy.php:29](app/Policies/DemandePolicy.php#L29), `DemandeStatutWorkflow::isEditableByDemandeur` [DemandeStatutWorkflow.php:35](app/Support/DemandeStatutWorkflow.php#L35), `DemandePolicy::delete` [DemandePolicy.php:43](app/Policies/DemandePolicy.php#L43) |
| Un brouillon est invisible côté validation (erreur 404). | `DemandePolicy::instruire` — [DemandePolicy.php:58](app/Policies/DemandePolicy.php#L58), liste [Demande.php:48](app/Models/Demande.php#L48) (`horsBrouillon`) |
| Un validateur n'accède qu'aux demandes de ses sites assignés. | liste [Validateur/DemandeController.php:101](app/Http/Controllers/Validateur/DemandeController.php#L101), dossier `DemandePolicy::instruire` [DemandePolicy.php:58](app/Policies/DemandePolicy.php#L58), `Demande::scopeForStatsViewer` [Demande.php:56](app/Models/Demande.php#L56) |
| Pas d'auto-validation : on ne statue jamais sur sa propre demande. | `DemandePolicy::decider` — [DemandePolicy.php:76](app/Policies/DemandePolicy.php#L76) |
| Pièces jointes stockées hors du dossier public (`storage/app/private`), servies uniquement après contrôle d'accès. | `DocumentController` [DocumentController.php:15](app/Http/Controllers/DocumentController.php#L15), `DemandePolicy::consulterFichiers` [DemandePolicy.php:92](app/Policies/DemandePolicy.php#L92), stockage [DemandeDossier.php:78](app/Support/DemandeDossier.php#L78) |
| PDF officiels uniquement pour une demande **validée** et pour les personnes autorisées. | `DemandePolicy::telechargerPdf` — [DemandePolicy.php:102](app/Policies/DemandePolicy.php#L102), appelée par [PdfController.php:17](app/Http/Controllers/PdfController.php#L17) |
| Une période sensible **bloquante** empêche la soumission. | `DemandeDossier::analysePeriodes` [DemandeDossier.php:27](app/Support/DemandeDossier.php#L27), refus [DemandeController.php:94](app/Http/Controllers/DemandeController.php#L94) et [ligne 160](app/Http/Controllers/DemandeController.php#L160) |
| Les notifications ne peuvent être marquées comme lues que par leur destinataire (une par une ou toutes à la fois). | `NotificationController` — [NotificationController.php:13](app/Http/Controllers/NotificationController.php#L13), [NotificationController.php:38](app/Http/Controllers/NotificationController.php#L38) |
| Deux décisions simultanées ne peuvent pas s'appliquer toutes les deux ; un renvoi de complément n'écrase pas une décision prise entre-temps (transaction + verrou `lockForUpdate`). | `Validateur\DemandeController::updateStatut` [Validateur/DemandeController.php:174](app/Http/Controllers/Validateur/DemandeController.php#L174), `DemandeController::update` [DemandeController.php:166](app/Http/Controllers/DemandeController.php#L166) |
| Les envois de dossiers (création et modification, avec fichiers) sont limités à 20 par minute et par utilisateur. | limiteur `demandes` [AppServiceProvider.php:43](app/Providers/AppServiceProvider.php#L43), routes [web.php:29](routes/web.php#L29) |
| La plateforme garde toujours un administrateur : un admin ne peut ni se retirer son rôle, ni supprimer son compte s'il est le dernier. | `Admin\UserController::update` [UserController.php:52](app/Http/Controllers/Admin/UserController.php#L52), `ProfileController::destroy` [ProfileController.php:57](app/Http/Controllers/ProfileController.php#L57) |
| La suppression d'un compte efface aussi les fichiers de ses demandes (après la suppression en base). | `DemandeDossier::deleteUser` — [DemandeDossier.php:97](app/Support/DemandeDossier.php#L97) |
| Les popups de la carte insèrent le nom et la description des sites comme texte, jamais comme HTML (protection XSS). | `DashboardMap.vue` — [DashboardMap.vue:47-50](resources/js/Components/DashboardMap.vue#L47-L50) (`textContent`) |

---

## 5. Organisation des dossiers

### Racine de `3_Application/`

| Élément | Rôle |
|---|---|
| [app/](app/) | Logique métier PHP (modèles, contrôleurs, règles). |
| [bootstrap/](bootstrap/) | Démarrage de Laravel : routage, middleware Inertia, providers. |
| [config/](config/) | Configuration de Laravel. |
| [database/](database/) | Migrations, seeders et données géographiques des sites. |
| [public/](public/) | Racine web : point d'entrée, images, build Vite. |
| [resources/](resources/) | Interface Vue, CSS et templates Blade (page racine et PDF). |
| [routes/](routes/) | Définition des URL. |
| [storage/](storage/) | Fichiers générés à l'exécution (pièces jointes, logs, cache). |
| [tests/](tests/) | Tests automatisés PHPUnit. |
| `vendor/`, `node_modules/` | Dépendances PHP et JavaScript installées (non versionnées). |
| [Site_Fanamby/](Site_Fanamby/) | Fichiers sources **GeoJSON / QGIS** des 5 aires protégées, dans leurs projections d'origine. |
| [process_geojson.py](process_geojson.py) | Script Python (pyproj) qui convertit ces GeoJSON en coordonnées GPS WGS84 et produit `database/seeders/site_coordinates.json`. |
| [insert_sites.sql](insert_sites.sql) | Script SQL d'insertion directe des aires protégées (alternative au seeder). Il vide ensuite la liste des sites mise en cache pour la page d'accueil. |
| `image geo/` | Captures d'écran de référence des périmètres des sites. |
| `ICONS/` | Bibliothèque d'icônes Phosphor complète (SVG, PNG, polices). Fichiers de référence uniquement : les icônes utilisées sont déjà recopiées dans [Icon.vue](resources/js/Components/Icon.vue). |
| [composer.json](composer.json), [package.json](package.json) | Dépendances PHP et JavaScript. |
| [vite.config.js](vite.config.js), [tailwind.config.js](tailwind.config.js), [postcss.config.js](postcss.config.js) | Compilation du front-end et thème Tailwind (couleurs FANAMBY, polices Inter et JetBrains Mono). |
| [phpunit.xml](phpunit.xml) | Configuration des tests : base MySQL dédiée `projet_stage_test`, vidée et recréée à chaque lancement. |
| [AGENTS.md](AGENTS.md), [CLAUDE.md](CLAUDE.md) | Consignes pour les assistants de code. |

### `app/`

#### `app/Models/` — tables de la base

| Modèle | Rôle |
|---|---|
| [User](app/Models/User.php) | Utilisateur avec un rôle (`admin`, `validateur`, `demandeur`). Lié aux sites qu'il valide (table pivot `site_user`, [User.php:33](app/Models/User.php#L33)). |
| [Site](app/Models/Site.php) | Aire protégée : nom, description, polygone de coordonnées (JSON `[{lat, lng}, …]`). Liste de la page d'accueil gardée en cache (`pourAccueil`, [Site.php:30](app/Models/Site.php#L30)) et vidée à chaque création, modification ou suppression d'un site ([Site.php:19](app/Models/Site.php#L19)). |
| [Demande](app/Models/Demande.php) | Demande d'accès : demandeur, site, type, statut, dates, équipe, description, date de soumission, résultats de l'analyse des périodes (JSON). Référence officielle `reference` (`FAN-AAAA-NNNN`, [Demande.php:31](app/Models/Demande.php#L31)), envoyée avec chaque demande. Portées `horsBrouillon()` ([Demande.php:48](app/Models/Demande.php#L48)) et `forStatsViewer()` ([Demande.php:56](app/Models/Demande.php#L56)). |
| [TypeDemande](app/Models/TypeDemande.php) | Type d'activité (ex. « Recherche scientifique »). Relations `documentsRequis` et `reglesSpecifiques`, mises à jour ligne par ligne par `syncListe` ([TypeDemande.php:33](app/Models/TypeDemande.php#L33)). |
| [DocumentRequis](app/Models/DocumentRequis.php) | Pièce exigée pour un type (table `documents_requis`, libellé unique par type). |
| [RegleSpecifique](app/Models/RegleSpecifique.php) | Règle d'un type, reprise dans le cahier des charges (table `regles_specifiques`). |
| [Statut](app/Models/Statut.php) | Référentiel des 6 statuts du workflow. |
| [Document](app/Models/Document.php) | Pièce jointe d'une demande (nom d'origine, chemin privé). Reliée à la pièce requise qu'elle fournit (`document_requis_id`, vide si la pièce a été retirée du type) ; `type_document` garde le libellé du jour du dépôt. |
| [PeriodeSensible](app/Models/PeriodeSensible.php) | Période de restriction sur un site, de niveau `bloquant` ou `avertissement`. |
| [HistoriqueStatut](app/Models/HistoriqueStatut.php) | Journal d'audit de chaque changement de statut. L'auteur (`user_id`) devient vide si son compte est supprimé. |
| [Notification](app/Models/Notification.php) | Notification interne liée à une demande (indicateur `lu`). |

#### `app/Http/Controllers/`

| Contrôleur | Rôle |
|---|---|
| [AccueilController](app/Http/Controllers/AccueilController.php) | Page d'accueil publique, avec la liste des sites en cache ([L14](app/Http/Controllers/AccueilController.php#L14)). |
| [DashboardController](app/Http/Controllers/DashboardController.php) | Choisit le tableau de bord selon le rôle ([DashboardController.php:19](app/Http/Controllers/DashboardController.php#L19)). Les calculs sont dans `StatsDemandeur`, `StatsValidateur` et `StatsAdmin` (voir `app/Support/`). |
| [DemandeController](app/Http/Controllers/DemandeController.php) | Espace demandeur : liste ([L26](app/Http/Controllers/DemandeController.php#L26)), création ([L85](app/Http/Controllers/DemandeController.php#L85)), brouillon, modification et complément ([L150](app/Http/Controllers/DemandeController.php#L150)), suppression ([L222](app/Http/Controllers/DemandeController.php#L222)). Droits dans `DemandePolicy`, validation dans `DemandeRequest`, notifications dans `DemandeNotifications`. |
| [DocumentController](app/Http/Controllers/DocumentController.php) | Téléchargement sécurisé des pièces jointes ([L11](app/Http/Controllers/DocumentController.php#L11)). |
| [PdfController](app/Http/Controllers/PdfController.php) | Génération de l'autorisation ([L15](app/Http/Controllers/PdfController.php#L15)) et du cahier des charges ([L23](app/Http/Controllers/PdfController.php#L23)). |
| [NotificationController](app/Http/Controllers/NotificationController.php) | Marque une notification comme lue et redirige vers le bon dossier ([L10](app/Http/Controllers/NotificationController.php#L10)) ; marque toutes les notifications de l'utilisateur comme lues ([L35](app/Http/Controllers/NotificationController.php#L35)). |
| [ProfileController](app/Http/Controllers/ProfileController.php) | Profil utilisateur (Breeze) ; suppression du compte avec ses fichiers, refusée au dernier admin ([L48](app/Http/Controllers/ProfileController.php#L48)). |
| [Admin/SiteController](app/Http/Controllers/Admin/SiteController.php) | CRUD des sites, avec recherche. |
| [Admin/PeriodeSensibleController](app/Http/Controllers/Admin/PeriodeSensibleController.php) | CRUD des périodes sensibles, avec filtres. |
| [Admin/TypeDemandeController](app/Http/Controllers/Admin/TypeDemandeController.php) | CRUD des types d'activité. |
| [Admin/UserController](app/Http/Controllers/Admin/UserController.php) | Rôles et affectation des sites aux validateurs (en une transaction, [L56](app/Http/Controllers/Admin/UserController.php#L56)). |
| [Validateur/DemandeController](app/Http/Controllers/Validateur/DemandeController.php) | Liste filtrée ([L27](app/Http/Controllers/Validateur/DemandeController.php#L27)), dossier complet ([L135](app/Http/Controllers/Validateur/DemandeController.php#L135)), décision sous verrou ([L163](app/Http/Controllers/Validateur/DemandeController.php#L163)), export CSV ([L49](app/Http/Controllers/Validateur/DemandeController.php#L49)). |
| [Auth/*](app/Http/Controllers/Auth/) | Authentification Laravel Breeze (9 contrôleurs). |

#### `app/Policies/` — droits d'accès

| Policy | Rôle |
|---|---|
| [DemandePolicy](app/Policies/DemandePolicy.php) | Qui peut faire quoi sur une demande, rattachée automatiquement au modèle `Demande` : consulter sa demande (`view`, [L19](app/Policies/DemandePolicy.php#L19)), la modifier (`update`, brouillon ou à compléter, [L29](app/Policies/DemandePolicy.php#L29)), la supprimer (`delete`, brouillon, [L43](app/Policies/DemandePolicy.php#L43)), ouvrir le dossier côté validation (`instruire` : admin ou validateur du site, brouillon introuvable, [L58](app/Policies/DemandePolicy.php#L58)), décider (`decider`, jamais sur sa propre demande, [L76](app/Policies/DemandePolicy.php#L76)), télécharger les pièces jointes (`consulterFichiers`, [L92](app/Policies/DemandePolicy.php#L92)) et les PDF officiels (`telechargerPdf`, demande validée, [L102](app/Policies/DemandePolicy.php#L102)). |

#### `app/Http/Requests/` — validation des formulaires

| Requête | Rôle |
|---|---|
| [DemandeRequest](app/Http/Requests/DemandeRequest.php) | Création et modification d'une demande : droits vérifiés avant la validation ([L25](app/Http/Requests/DemandeRequest.php#L25)), règles selon brouillon ou envoi ([L30](app/Http/Requests/DemandeRequest.php#L30)), pièces exigées par le type choisi, messages en français ([L55](app/Http/Requests/DemandeRequest.php#L55)), `estSoumission()` ([L78](app/Http/Requests/DemandeRequest.php#L78)) et `typeDemande()` ([L86](app/Http/Requests/DemandeRequest.php#L86)) réutilisés par le contrôleur. |
| [DecisionRequest](app/Http/Requests/DecisionRequest.php) | Décision d'un validateur ou d'un admin : `DemandePolicy::decider` ([L17](app/Http/Requests/DecisionRequest.php#L17)), nouveau statut et commentaire obligatoire ([L22](app/Http/Requests/DecisionRequest.php#L22)). |
| [Auth/LoginRequest](app/Http/Requests/Auth/LoginRequest.php) | Validation de la connexion avec limitation des tentatives. |


#### `app/Support/` — règles métier réutilisables

| Classe | Rôle |
|---|---|
| [DemandeStatutWorkflow](app/Support/DemandeStatutWorkflow.php) | Transitions de statut autorisées ([L48](app/Support/DemandeStatutWorkflow.php#L48)), statuts finaux ([L23](app/Support/DemandeStatutWorkflow.php#L23)), statuts modifiables par le demandeur ([L35](app/Support/DemandeStatutWorkflow.php#L35)). |
| [DemandeDecisionRules](app/Support/DemandeDecisionRules.php) | Décideurs à prévenir lors d'une nouvelle demande ([L21](app/Support/DemandeDecisionRules.php#L21)). Qui peut consulter ou décider : voir `DemandePolicy`. |
| [DemandeNotifications](app/Support/DemandeNotifications.php) | Envoi des notifications internes : soumission ([L19](app/Support/DemandeNotifications.php#L19)), complément renvoyé ([L35](app/Support/DemandeNotifications.php#L35)), décision ([L53](app/Support/DemandeNotifications.php#L53)). |
| [StatsInstruction](app/Support/StatsInstruction.php) | Base des tableaux de bord validateur et admin : seuils de retard et d'arrivée imminente ([L21-24](app/Support/StatsInstruction.php#L21-L24)), dossiers en attente triés par urgence ([L43](app/Support/StatsInstruction.php#L43)), compteurs ([L78](app/Support/StatsInstruction.php#L78)), carte ([L93](app/Support/StatsInstruction.php#L93)). |
| [StatsValidateur](app/Support/StatsValidateur.php) | Tableau de bord du validateur ([L15](app/Support/StatsValidateur.php#L15)) : file de traitement, attente du demandeur ([L31](app/Support/StatsValidateur.php#L31)), activité du mois ([L57](app/Support/StatsValidateur.php#L57)). |
| [StatsAdmin](app/Support/StatsAdmin.php) | Tableau de bord de l'admin ([L19](app/Support/StatsAdmin.php#L19)) : qualité de service, terrain ([L64](app/Support/StatsAdmin.php#L64)), charge des validateurs ([L92](app/Support/StatsAdmin.php#L92)), fréquentation ([L114](app/Support/StatsAdmin.php#L114)), demandes par mois ([L136](app/Support/StatsAdmin.php#L136)). |
| [StatsDemandeur](app/Support/StatsDemandeur.php) | Tableau de bord du demandeur ([L20](app/Support/StatsDemandeur.php#L20)) : dossiers à compléter, en cours (pièces manquantes, délai habituel) et séjours à venir. |
| [DemandeDossier](app/Support/DemandeDossier.php) | Analyse des chevauchements avec les périodes sensibles ([L27](app/Support/DemandeDossier.php#L27)) ; enregistrement, remplacement et suppression des pièces jointes ([L52](app/Support/DemandeDossier.php#L52)) ; suppression d'un brouillon (`deleteDemande`, [L86](app/Support/DemandeDossier.php#L86)) ou d'un compte (`deleteUser`, [L96](app/Support/DemandeDossier.php#L96)) avec leurs fichiers. |
| [DelaiDecision](app/Support/DelaiDecision.php) | Délais de décision (soumission → validation ou rejet) : liste des décisions avec leur délai ([L25](app/Support/DelaiDecision.php#L25)), délai habituel d'un site — médiane du site s'il compte au moins 3 décisions, sinon celle de la plateforme ([L47](app/Support/DelaiDecision.php#L47)). Utilisé par les tableaux de bord et par la timeline (date de décision estimée). |
| [DemandeNotificationMessage](app/Support/DemandeNotificationMessage.php) | Textes des notifications en français pour chaque événement : décision ([L14](app/Support/DemandeNotificationMessage.php#L14)), soumission ([L55](app/Support/DemandeNotificationMessage.php#L55)), complément renvoyé ([L66](app/Support/DemandeNotificationMessage.php#L66)), nouvelle demande ([L77](app/Support/DemandeNotificationMessage.php#L77)). |

#### Autres

- [app/Http/Middleware/HandleInertiaRequests.php](app/Http/Middleware/HandleInertiaRequests.php#L30) : partage avec toutes les pages l'utilisateur connecté, les messages flash et les notifications non lues.
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php#L26) : déclaration des gates de rôle, préchargement des fichiers Vite et limiteur d'envois des demandes ([L43](app/Providers/AppServiceProvider.php#L43)).

### `bootstrap/`

- [app.php](bootstrap/app.php) : branche les routes et ajoute le middleware Inertia au groupe web.
- [providers.php](bootstrap/providers.php) : liste des providers chargés.
- `cache/` : caches générés automatiquement.

### `config/`

Configuration standard de Laravel : `app`, `auth`, `cache`, `database`, [filesystems](config/filesystems.php) (disque `local` = `storage/app/private` pour les pièces jointes), `logging`, `mail`, `queue`, `services`, `session`.

### `database/`

**[migrations/](database/migrations/)** — schéma complet, dans l'ordre :

1. `users`, `password_reset_tokens`, `sessions` — [0001_01_01_000000_create_users_table.php](database/migrations/0001_01_01_000000_create_users_table.php)
2. `cache`, `cache_locks` — [0001_01_01_000001_create_cache_table.php](database/migrations/0001_01_01_000001_create_cache_table.php)
3. `jobs`, `job_batches`, `failed_jobs` — [0001_01_01_000002_create_jobs_table.php](database/migrations/0001_01_01_000002_create_jobs_table.php)
4. `sites` — [2026_09_28_080925_create_sites_table.php](database/migrations/2026_09_28_080925_create_sites_table.php)
5. `statuts` — [2026_09_28_080926_create_statuts_table.php](database/migrations/2026_09_28_080926_create_statuts_table.php)
6. `type_demandes` — [2026_09_28_080927_create_type_demandes_table.php](database/migrations/2026_09_28_080927_create_type_demandes_table.php)
7. `demandes` — [2026_09_28_080928_create_demandes_table.php](database/migrations/2026_09_28_080928_create_demandes_table.php)
8. `documents` — [2026_09_28_080929_create_documents_table.php](database/migrations/2026_09_28_080929_create_documents_table.php)
9. `historique_statuts` — [2026_09_28_080930_create_historique_statuts_table.php](database/migrations/2026_09_28_080930_create_historique_statuts_table.php)
10. `periode_sensibles` — [2026_09_28_080931_create_periode_sensibles_table.php](database/migrations/2026_09_28_080931_create_periode_sensibles_table.php)
11. `notifications` — [2026_09_28_080932_create_notifications_table.php](database/migrations/2026_09_28_080932_create_notifications_table.php)
12. `site_user` (pivot validateurs ↔ sites) — [2026_09_28_080933_create_site_user_table.php](database/migrations/2026_09_28_080933_create_site_user_table.php)
13. Brouillons et compléments : dates rendues facultatives, ajout des statuts `brouillon` et `a_completer` — [2026_10_08_090000_add_brouillon_et_complement_to_demandes.php](database/migrations/2026_10_08_090000_add_brouillon_et_complement_to_demandes.php)
14. Historique : `historique_statuts.user_id` facultatif et mis à vide à la suppression du compte (`nullOnDelete`) — [2026_10_12_090000_historique_statuts_user_null_on_delete.php](database/migrations/2026_10_12_090000_historique_statuts_user_null_on_delete.php)
15. 1NF : les listes JSON `documents_requis` et `regles_specifiques` de `type_demandes` deviennent les tables `documents_requis` et `regles_specifiques` ; `documents.document_requis_id` relie chaque pièce jointe à sa pièce requise (données existantes reprises) — [2026_10_13_090000_normaliser_listes_type_demandes.php](database/migrations/2026_10_13_090000_normaliser_listes_type_demandes.php)
16. Contraintes : CHECK `date_fin >= date_debut` (demandes, périodes) et `equipe_nombre >= 1`, `RESTRICT` sur la suppression d'un site ou d'un type utilisé, index `notifications (user_id, lu, date_envoi)` pour la cloche — [2026_10_13_090100_contraintes_integrite_bdd.php](database/migrations/2026_10_13_090100_contraintes_integrite_bdd.php)
17. Historisation des soumissions déjà faites (une entrée « soumise » par demande envoyée) — [2026_10_13_090200_historiser_soumissions.php](database/migrations/2026_10_13_090200_historiser_soumissions.php)

**[seeders/](database/seeders/)**

- [DatabaseSeeder](database/seeders/DatabaseSeeder.php) : 6 statuts, 2 types d'activité (*Recherche scientifique*, *Tournage documentaire*), 2 sites de démonstration et 3 comptes de test :

  | E-mail | Rôle | Mot de passe |
  |---|---|---|
  | `admin@fanamby.org` | admin | `password` |
  | `validateur@fanamby.org` | validateur (Réserve de Lokobe) | `password` |
  | `alice@exemple.com` | demandeur | `password` |

- [NouveauxSitesSeeder](database/seeders/NouveauxSitesSeeder.php) : crée ou met à jour les 5 aires protégées réelles de FANAMBY à partir de `site_coordinates.json`.
- [site_coordinates.json](database/seeders/site_coordinates.json) : polygones GPS produits par `process_geojson.py`.

**[factories/UserFactory.php](database/factories/UserFactory.php)** : utilisateurs fictifs pour les tests.

**[schema.dbml](database/schema.dbml)** : schéma des tables métier et de leurs relations au format DBML, à coller sur [dbdiagram.io](https://dbdiagram.io) pour obtenir le diagramme. Il suit l'état après toutes les migrations et doit être mis à jour à chaque nouvelle migration.

La démarche de conception complète est dans [2_Conception/](../2_Conception/) : [Conception_BDD.md](../2_Conception/Conception_BDD.md) (MCD, MLD, normalisation, MPD, dénormalisations), le MCD [MCD.svg](../2_Conception/MCD.svg) et sa source Mocodo [mcd.mcd](../2_Conception/mcd.mcd), et le script MySQL équivalent aux migrations [base_de_donnees.sql](../2_Conception/base_de_donnees.sql).

**[donnees_test.sql](database/donnees_test.sql)** : jeu de données complet pour tester l'application à la main. Il **vide les tables puis les remplit**, et peut être relancé à tout moment pour revenir à l'état de départ. Il vide aussi le cache de l'application (dont la liste des sites de l'accueil). Les dates sont relatives au jour d'exécution, donc les retards, arrivées imminentes et séjours en cours restent cohérents.
- 7 comptes (mot de passe `password`) : `admin@fanamby.org`, `validateur@fanamby.org` (Andrafiamena, Anjozorobe, Loky), `hery@fanamby.org` (Anjozorobe, Menabe), et les demandeurs `alice@`, `rado@`, `sarah@`, `tiana@exemple.com`. L'Allée des Baobabs n'a volontairement aucun validateur.
- Les 5 aires protégées avec leurs contours réels (simplifiés à ~100 m), 4 types d'activité (9 pièces requises, 11 règles) et 8 périodes sensibles (passées, en cours, à venir, bloquantes ou non).
- 22 demandes couvrant tous les statuts et cas : brouillons incomplets, arrivées imminentes ou dépassées, retards, conflits de calendrier, compléments en attente (dont une pièce manquante), séjours en cours, demandes déposées par un admin et par un validateur (auto-validation interdite), décisions de l'année précédente.
- Historique (soumissions et décisions) et notifications cohérents avec ces dossiers : le statut de chaque demande est le dernier statut de son historique.

```bash
mysql -u root projet_stage < database/donnees_test.sql
```

Les pièces jointes sont des lignes en base. Les fichiers correspondants doivent exister dans `storage/app/private/demandes/{id}/`, sinon leur téléchargement affiche « Fichier introuvable » ([DocumentController.php:18-20](app/Http/Controllers/DocumentController.php#L18-L20)).

### `public/`

| Élément | Rôle |
|---|---|
| [index.php](public/index.php) | Point d'entrée de toutes les requêtes HTTP. |
| [assets/](public/assets/) | Logo FANAMBY (192 px) et image de fond des pages de connexion (1200 px et 2000 px). |
| `build/` | JavaScript et CSS compilés par `npm run build` (non versionné). |
| `hot` | Créé par `npm run dev`. S'il reste présent alors que le serveur Vite est arrêté, les pages s'affichent vides : il faut le supprimer. |
| `favicon.ico`, `robots.txt` | Icône et consignes pour les moteurs de recherche. |

### `resources/`

#### [`resources/css/app.css`](resources/css/app.css)

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
| [app.js](resources/js/app.js) | Démarre l'application Vue et Inertia, charge les pages à la demande, active Ziggy (fonction `route()` côté Vue). |
| [bootstrap.js](resources/js/bootstrap.js) | Configure axios. |

**[Pages/](resources/js/Pages/)** — un fichier par écran

| Dossier / fichier | Écran |
|---|---|
| [Welcome.vue](resources/js/Pages/Welcome.vue) | Accueil public avec carte des sites. |
| [Dashboard.vue](resources/js/Pages/Dashboard.vue) | Tableau de bord : en-tête et choix de l'espace selon le rôle, styles communs. |
| [Dashboard/Partials/](resources/js/Pages/Dashboard/Partials/) | [EspaceDemandeur](resources/js/Pages/Dashboard/Partials/EspaceDemandeur.vue), [EspaceValidateur](resources/js/Pages/Dashboard/Partials/EspaceValidateur.vue), [EspaceAdmin](resources/js/Pages/Dashboard/Partials/EspaceAdmin.vue) (un espace par rôle, voir 3.2 et 3.3), [CarteSites](resources/js/Pages/Dashboard/Partials/CarteSites.vue) (carte, curseur de date et légende) et [LigneDossier](resources/js/Pages/Dashboard/Partials/LigneDossier.vue) (ligne d'un dossier en attente avec ses alertes). |
| [Auth/](resources/js/Pages/Auth/) | Connexion, inscription, mot de passe oublié, réinitialisation, confirmation du mot de passe, vérification de l'e-mail. |
| [Profile/](resources/js/Pages/Profile/) | [Edit.vue](resources/js/Pages/Profile/Edit.vue) et ses 3 sous-formulaires dans `Partials/` : [informations](resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue), [mot de passe](resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue), [suppression du compte](resources/js/Pages/Profile/Partials/DeleteUserForm.vue). |
| [Demandes/](resources/js/Pages/Demandes/) | [Index](resources/js/Pages/Demandes/Index.vue) (mes demandes), [Create](resources/js/Pages/Demandes/Create.vue) (formulaire en étapes : création et modification, brouillon, complément ; la page garde les données, la vérification et l'envoi), [Partials/](resources/js/Pages/Demandes/Partials/) (une étape par composant : [EtapeActivite](resources/js/Pages/Demandes/Partials/EtapeActivite.vue), [EtapeDates](resources/js/Pages/Demandes/Partials/EtapeDates.vue), [EtapeMission](resources/js/Pages/Demandes/Partials/EtapeMission.vue), [EtapePieces](resources/js/Pages/Demandes/Partials/EtapePieces.vue), [EtapeRecap](resources/js/Pages/Demandes/Partials/EtapeRecap.vue), et la colonne [VerificationCalendrier](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue) : carte, fiche du site et périodes sensibles), [Show](resources/js/Pages/Demandes/Show.vue) (détail et timeline). |
| [Validateur/Demandes/](resources/js/Pages/Validateur/Demandes/) | [Index](resources/js/Pages/Validateur/Demandes/Index.vue) (liste filtrée et export), [Show](resources/js/Pages/Validateur/Demandes/Show.vue) (dossier, timeline et décision). |
| [Admin/Sites/](resources/js/Pages/Admin/Sites/) | `Index`, `Create`, `Edit` (avec dessin du polygone). |
| [Admin/PeriodeSensibles/](resources/js/Pages/Admin/PeriodeSensibles/) | `Index`, `Create`, `Edit`. |
| [Admin/TypeDemandes/](resources/js/Pages/Admin/TypeDemandes/) | `Index`, `Create`, `Edit`. |
| [Admin/Users/](resources/js/Pages/Admin/Users/) | `Index`, `Edit` (rôle et sites). |

**[Layouts/](resources/js/Layouts/)**

- [AuthenticatedLayout.vue](resources/js/Layouts/AuthenticatedLayout.vue) : menu latéral organisé selon le rôle (Mon espace, Instruction, Administration — [L26](resources/js/Layouts/AuthenticatedLayout.vue#L26)), cloche de notifications, menu mobile, messages flash, squelette pendant les changements de page ([L14-15](resources/js/Layouts/AuthenticatedLayout.vue#L14-L15)).
- [GuestLayout.vue](resources/js/Layouts/GuestLayout.vue) : mise en page des écrans d'authentification (formulaire et photo de fond).

**[Components/](resources/js/Components/)**

| Composant | Rôle |
|---|---|
| [DashboardMap.vue](resources/js/Components/DashboardMap.vue) | Carte MapLibre de plusieurs sites : polygones ([L242](resources/js/Components/DashboardMap.vue#L242)), marqueurs animés ([L211](resources/js/Components/DashboardMap.vue#L211)), fiches au clic avec surface ([L56](resources/js/Components/DashboardMap.vue#L56)), cadrage automatique ([L113](resources/js/Components/DashboardMap.vue#L113)), centrage sur un site ([L106](resources/js/Components/DashboardMap.vue#L106)), choix du fond de carte, plein écran, vue 3D du terrain (`voir3d`, [L145](resources/js/Components/DashboardMap.vue#L145)). Options facultatives ([props L7](resources/js/Components/DashboardMap.vue#L7)) : `etats` (coloration selon une date ou des dates, [L375](resources/js/Components/DashboardMap.vue#L375)), `charges` (cercles de charge, [L362](resources/js/Components/DashboardMap.vue#L362)), et le mode formulaire (`selectable`, `selectedId`) : choix du site au clic, mise en avant du site choisi ([L390](resources/js/Components/DashboardMap.vue#L390)), couleurs et hachures selon les dates. Chargée seulement quand elle est affichée (tableau de bord, [CarteSites.vue:7](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L7)). |
| [MapComponent.vue](resources/js/Components/MapComponent.vue) | Carte d'un seul site avec dessin du polygone (outil MapboxDraw, chargé uniquement en mode édition), utilisée par les pages admin des sites. Crédits du fond de carte affichés en bas à droite (exigés par les licences OpenStreetMap et CARTO) [L117](resources/js/Components/MapComponent.vue#L117). Polygone existant [L24](resources/js/Components/MapComponent.vue#L24), mise à jour [L145](resources/js/Components/MapComponent.vue#L145), effacement [L158](resources/js/Components/MapComponent.vue#L158). |
| [Icon.vue](resources/js/Components/Icon.vue) | Jeu d'icônes Phosphor (style *fill*) intégré directement en SVG. |
| [ListeEditable.vue](resources/js/Components/ListeEditable.vue) | Liste de lignes à ajouter, renommer ou retirer, en gardant l'id des lignes existantes (pièces requises et règles d'un type). |
| [CompteurAnime.vue](resources/js/Components/CompteurAnime.vue) | Nombre qui défile jusqu'à sa valeur (au chargement, puis depuis l'ancienne valeur quand elle change), au format français ; garde les décimales reçues (délai médian). |
| [StatutSteps.vue](resources/js/Components/StatutSteps.vue) | Frise d'avancement d'une demande (Déposée → Analyse → Décision). |
| [TimelineDemande.vue](resources/js/Components/TimelineDemande.vue) | Timeline interactive d'un dossier (pages de détail demandeur et validateur) : étapes passées, actuelle et à venir, détail de chaque étape au clic (commentaire, auteur, pièces déposées, analyse du calendrier), date de décision estimée. Option `vueDemandeur` : agents non nommés. |
| [PageSkeleton.vue](resources/js/Components/PageSkeleton.vue), [TableSkeleton.vue](resources/js/Components/TableSkeleton.vue) | Squelettes de chargement d'une page entière ou d'un tableau. |
| [Pagination.vue](resources/js/Components/Pagination.vue) | Pagination des listes Laravel. |
| [PasswordInput.vue](resources/js/Components/PasswordInput.vue) | Champ mot de passe avec bouton afficher/masquer. |
| [Modal.vue](resources/js/Components/Modal.vue) | Fenêtre de confirmation (suppression du compte). |
| `TextInput`, `InputLabel`, `InputError`, `Checkbox`, `PrimaryButton`, `SecondaryButton`, `DangerButton` | Éléments de formulaire de base. |
| `ApplicationLogo`, `Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink` | Hérités de Breeze, **plus utilisés** (peuvent être supprimés). |

**[composables/](resources/js/composables/)**

- [useListFilters.js](resources/js/composables/useListFilters.js#L8) : synchronise les filtres d'une liste avec l'URL (recherche temporisée à 350 ms, autres filtres immédiats, réinitialisation).
- [useNavigation.js](resources/js/composables/useNavigation.js#L33) : détecte les navigations Inertia en cours et choisit le squelette à afficher selon la page de destination ([L39](resources/js/composables/useNavigation.js#L39)).

**[utils/](resources/js/utils/)**

- [statut.js](resources/js/utils/statut.js) : classe de badge par statut ([L11](resources/js/utils/statut.js#L11)), nombre de jours écoulés ([L16](resources/js/utils/statut.js#L16)), formatage des dates ([L22](resources/js/utils/statut.js#L22)). La référence `FAN-AAAA-NNNN` vient du serveur (`demande.reference`).
- [openPdf.js](resources/js/utils/openPdf.js#L5) : récupère un PDF avec la session de l'utilisateur et l'ouvre dans un nouvel onglet.
- [carte.js](resources/js/utils/carte.js) : outils des cartes : contrôle « Plan / Satellite / Relief » ([L35](resources/js/utils/carte.js#L35)), source des altitudes pour la 3D ([L83](resources/js/utils/carte.js#L83)), calcul de la surface d'un contour en km² ([L93](resources/js/utils/carte.js#L93)), motif de hachures des périodes sensibles ([L112](resources/js/utils/carte.js#L112)).

#### `resources/views/`

- [app.blade.php](resources/views/app.blade.php) : page HTML racine dans laquelle Inertia monte l'application (polices, routes Ziggy, fichiers Vite).
- [pdf/autorisation.blade.php](resources/views/pdf/autorisation.blade.php) : modèle du PDF d'autorisation officielle.
- [pdf/cahier_charges.blade.php](resources/views/pdf/cahier_charges.blade.php) : modèle du PDF du cahier des charges.

### `routes/`

| Fichier | Contenu |
|---|---|
| [web.php](routes/web.php) | Contrôleurs importés en tête du fichier. Accueil public ([L18](routes/web.php#L18)), tableau de bord ([L21](routes/web.php#L21)), espace connecté : profil, demandes (création et modification limitées par `throttle:demandes`, [L29](routes/web.php#L29)), notifications, documents, PDF ([L23-35](routes/web.php#L23-L35)), groupe `admin/*` avec `can:is-admin` ([L39](routes/web.php#L39)), groupe `validateur/*` avec `can:is-validateur` ([L46](routes/web.php#L46)). |
| [auth.php](routes/auth.php) | Routes d'authentification Breeze. |
| [console.php](routes/console.php) | Commandes Artisan personnalisées (aucune pour l'instant). |

### `storage/`

| Dossier | Contenu |
|---|---|
| `app/private/demandes/{id}/` | Pièces jointes des demandes. Jamais accessibles par URL directe. |
| `app/public/` | Fichiers publics (non utilisé). |
| `framework/` | Cache, sessions et vues Blade compilées. |
| `logs/` | Journal `laravel.log`. |

---

## 6. Tests automatisés

100 tests PHPUnit, exécutés avec `php artisan test` sur la base MySQL `projet_stage_test` (à créer une fois, voir section 7). Chaque test démarre sur des tables vides : la base de l'application `projet_stage` n'est jamais touchée.

| Fichier | Ce qu'il vérifie |
|---|---|
| [Feature/DemandeParcoursTest](tests/Feature/DemandeParcoursTest.php) | Brouillon minimal, brouillon invisible au validateur, reprise d'un brouillon avec ses pièces, suppression réservée aux brouillons, cycle complet de complément, détection des chevauchements (avertissement accepté, blocage refusé), périodes envoyées au formulaire, tableau de bord demandeur, filtres et pagination validateur, export CSV, recherche des sites, référence calculée par le serveur (page et tableau de bord), limite de 20 envois par minute. |
| [Feature/DashboardTest](tests/Feature/DashboardTest.php) | File du validateur triée par urgence et limitée à ses sites, activité du mois, indicateurs de l'admin (délai médian, taux de validation sur les dossiers décidés, compléments), sites sans validateur, présence sur le terrain, fréquentation, pièces manquantes et délai habituel côté demandeur. |
| [Feature/DemandeSecurityTest](tests/Feature/DemandeSecurityTest.php) | Cloisonnement des pièces jointes et des PDF par site et par propriétaire, PDF réservés aux demandes validées, date passée et format de fichier refusés, détail réservé au propriétaire, suppression interdite d'un site ou d'un type utilisé. |
| [Feature/IntegriteComptesEtDecisionsTest](tests/Feature/IntegriteComptesEtDecisionsTest.php) | Suppression du compte d'un validateur avec conservation de ses décisions, effacement des fichiers d'un compte supprimé, dernier admin protégé, seconde décision refusée sur un dossier clôturé entre-temps, admin ne pouvant pas se retirer son rôle, changement de rôle d'un autre utilisateur, site inconnu refusé. |
| [Feature/ConceptionBddTest](tests/Feature/ConceptionBddTest.php) | Garanties de la conception : soumission historisée (directe ou depuis un brouillon), renommage d'une pièce requise sans perte des fichiers, retrait d'une pièce sans effacer les fichiers soumis, pièce en double refusée, règles reprises dans le cahier des charges, CHECK (dates, effectif) et RESTRICT appliqués par la base. |
| [Feature/SuiviDemandeTest](tests/Feature/SuiviDemandeTest.php) | Données de la timeline : délai habituel du site transmis pour un dossier en instruction (celui de la plateforme si le site compte moins de 3 décisions), aucune estimation pour un dossier décidé, historique transmis dans l'ordre chronologique aux pages demandeur et validateur. |
| [Feature/ProfileTest](tests/Feature/ProfileTest.php) | Modification et suppression du profil. |
| [Feature/NotificationTest](tests/Feature/NotificationTest.php) | *Tout marquer comme lu* ne touche que les notifications de l'utilisateur connecté et exige d'être connecté. |
| [Feature/AccueilTest](tests/Feature/AccueilTest.php) | Page d'accueil : un site créé, renommé ou supprimé apparaît tout de suite malgré le cache. |
| [Feature/Auth/*](tests/Feature/Auth/) | Connexion, inscription, vérification de l'e-mail, confirmation, réinitialisation et changement du mot de passe. |
| [Unit/DemandeStatutWorkflowTest](tests/Unit/DemandeStatutWorkflowTest.php) | Transitions de statut autorisées et interdites. |
| [Unit/DemandePolicyTest](tests/Unit/DemandePolicyTest.php) | Droits de `DemandePolicy` : interdiction de l'auto-validation, validateur d'un autre site refusé, brouillon introuvable pour les décideurs mais lisible par son auteur, modification et suppression selon le statut, espace demandeur réservé à l'auteur, PDF seulement pour une demande validée. |
| [Unit/DemandeDecisionRulesTest](tests/Unit/DemandeDecisionRulesTest.php) | Choix des décideurs à notifier. |
| [Unit/DemandeForStatsViewerTest](tests/Unit/DemandeForStatsViewerTest.php) | Périmètre des statistiques : admin = tout, validateur = ses sites. |


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
