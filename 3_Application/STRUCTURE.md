# Plateforme d'accès aux aires protégées — Association FANAMBY

Application web de gestion des demandes d'accès aux aires protégées gérées par l'Association FANAMBY (Madagascar).
Photographes, équipes de tournage et autres porteurs de projets photographiques ou vidéographiques y déposent leurs demandes, avec les rubriques du formulaire de demande d'autorisation de Fanamby. Les validateurs les instruisent. Une fois la demande validée, l'autorisation officielle et le cahier des charges sont générés en PDF.

**Stack :** Laravel 13 · Vue 3 · Inertia.js 2 · TailwindCSS 3 · MapLibre GL · Chart.js · DomPDF
**Base de données :** MySQL / MariaDB uniquement : `projet_stage` pour l'application, `projet_stage_test` pour les tests automatisés. **Toutes les tables portent le préfixe `t_`** (`t_users`, `t_demandes`, `t_sessions`…), voir 5. `database/`.

> **Lire les références au code.** Les liens de la forme [`Fichier.php:42`](#) ouvrent le fichier à la ligne où la fonctionnalité est codée. Les chemins sont relatifs à `3_Application/`. Les numéros de ligne correspondent à l'état du code au 15/10/2026 : après une modification d'un fichier, mettre à jour les liens qui le concernent.

> **Vocabulaire.** Partout dans l'interface (menus, titres, colonnes, messages, PDF, export CSV), on parle d'**aire protégée**. Le code garde le nom technique `Site` : modèle `Site`, table `t_sites`, champ `site_id`, routes `admin.sites.*`. Dans ce document, « site » désigne donc une aire protégée.

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
Elles protègent les groupes de routes `admin/*` ([web.php:43](routes/web.php#L43)) et `validateur/*` ([web.php:50](routes/web.php#L50)). Le menu latéral s'adapte au rôle dans [AuthenticatedLayout.vue:26](resources/js/Layouts/AuthenticatedLayout.vue#L26).

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
| `validee` | Acceptée, après génération des documents et signature du validateur (voir 3.3). **Statut final.** Les PDF officiels deviennent disponibles. |
| `rejetee` | Refusée. **Statut final.** |

Règles du workflow (encodées dans [app/Support/DemandeStatutWorkflow.php](app/Support/DemandeStatutWorkflow.php)) :
- un statut ne peut qu'avancer : pas de retour en arrière, pas de « changement » vers le même statut — ordre des statuts [DemandeStatutWorkflow.php:11](app/Support/DemandeStatutWorkflow.php#L11), contrôle [DemandeStatutWorkflow.php:59](app/Support/DemandeStatutWorkflow.php#L59) et [DemandeStatutWorkflow.php:74](app/Support/DemandeStatutWorkflow.php#L74) ;
- aucune décision n'est possible sur un brouillon ni sur un dossier clôturé (validé ou rejeté) — [DemandeStatutWorkflow.php:55](app/Support/DemandeStatutWorkflow.php#L55), statuts finaux [DemandeStatutWorkflow.php:23](app/Support/DemandeStatutWorkflow.php#L23) ;
- un complément ne peut être demandé que depuis `soumise` ou `en_analyse` — [DemandeStatutWorkflow.php:21](app/Support/DemandeStatutWorkflow.php#L21) et [DemandeStatutWorkflow.php:63](app/Support/DemandeStatutWorkflow.php#L63) ;
- un dossier « à compléter » renvoyé par le demandeur repasse directement en `en_analyse` — [DemandeController.php:169](app/Http/Controllers/DemandeController.php#L169).

Les statuts proposés dans le formulaire de décision sont calculés par `allowedTargetStatuts` ([DemandeStatutWorkflow.php:83](app/Support/DemandeStatutWorkflow.php#L83)).

Chaque changement de statut est historisé : auteur, date, ancien et nouveau statut, commentaire ([Validateur/DemandeController.php:206](app/Http/Controllers/Validateur/DemandeController.php#L206)). La **soumission** l'est aussi, au nom du demandeur ([DemandeController.php:305](app/Http/Controllers/DemandeController.php#L305)) ; un brouillon n'a pas encore d'historique. Ainsi `t_demandes.statut_id` est toujours le dernier statut historisé, ou `brouillon` s'il n'y a pas d'historique (voir [Conception_BDD.md](../2_Conception/Conception_BDD.md), section 6.3). Si l'auteur supprime ensuite son compte, la décision reste dans l'historique, sans auteur (affichée « Compte supprimé ») : clé étrangère `nullOnDelete` dans [2026_10_19_200006_create_t_historique_statuts_table.php](database/migrations/2026_10_19_200006_create_t_historique_statuts_table.php).

Un changement de statut est enregistré **en une seule fois** (statut, historique et notification ensemble) et **sous verrou** sur la demande ([Validateur/DemandeController.php:179-219](app/Http/Controllers/Validateur/DemandeController.php#L179-L219)). De deux décisions prises au même moment, seule la première s'applique ; la seconde est refusée si elle n'est plus permise par le nouveau statut ([Validateur/DemandeController.php:183](app/Http/Controllers/Validateur/DemandeController.php#L183)). De même, le renvoi d'un complément par le demandeur n'est pas enregistré si un validateur a changé le statut entre-temps ([DemandeController.php:160-165](app/Http/Controllers/DemandeController.php#L160-L165)).

---

## 3. Fonctionnalités

### 3.1 Espace public

- **Page d'accueil** (`/`) : présentation du service en 3 étapes, carte interactive de toutes les aires protégées (polygones, marqueurs animés, fiche du site au clic avec sa surface). La liste des sites permet de centrer la carte sur l'un d'eux.
  - Code : route [web.php:21](routes/web.php#L21), contrôleur [AccueilController.php](app/Http/Controllers/AccueilController.php) (liste des sites gardée en cache une journée et vidée dès qu'un site est créé, modifié ou supprimé : [Site.php:21-37](app/Models/Site.php#L21-L37)), page [Welcome.vue](resources/js/Pages/Welcome.vue), carte [DashboardMap.vue](resources/js/Components/DashboardMap.vue) (centrage `focusSite` [DashboardMap.vue:106](resources/js/Components/DashboardMap.vue#L106)).
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
- Liste paginée (10 par page) avec onglets de filtre par statut et compteur par statut ; la colonne *Projet* montre le titre et le type de projet.
- Actions selon le statut : *Continuer* (brouillon), *Compléter* (à compléter), PDF (validée).
- Code : [DemandeController.php:27](app/Http/Controllers/DemandeController.php#L27) (`index`, pagination [ligne 36](app/Http/Controllers/DemandeController.php#L36), compteurs [ligne 40](app/Http/Controllers/DemandeController.php#L40)) ; page [Demandes/Index.vue](resources/js/Pages/Demandes/Index.vue).

**Nouvelle demande / modification** (`/demandes/create`, `/demandes/{id}/edit`)
- **Formulaire en étapes, calqué sur le formulaire papier de Fanamby** (*Formulaire de demande d'autorisation pour projet photographique / vidéographique*, sections I, II et V) : 1. Demandeur, 2. Projet, 3. Aire protégée et période, 4. Pièces à joindre, 5. Récapitulatif et engagement. Une seule étape est affichée à la fois, avec la carte et la vérification du calendrier toujours visibles à côté.
  - Liste des étapes [Create.vue:164](resources/js/Pages/Demandes/Create.vue#L164) (libellé court pour la barre, titre de section au-dessus de l'étape), barre d'étapes [Create.vue:303](resources/js/Pages/Demandes/Create.vue#L303). La page garde les données, la vérification et l'envoi ; chaque étape est un composant de [Demandes/Partials/](resources/js/Pages/Demandes/Partials/), affiché sous le titre de l'étape ([Create.vue:330](resources/js/Pages/Demandes/Create.vue#L330)).
  - **1. Demandeur (« I. Informations sur le demandeur »)** : nom et prénoms et e-mail repris du compte ; institution / société de production / organisation, fonction, adresse et téléphone (chiffres, espaces et `+ ( ) - .`). Ces coordonnées sont **enregistrées sur le compte** du demandeur à chaque enregistrement et pré-remplies dans ses demandes suivantes — composant [EtapeDemandeur.vue](resources/js/Pages/Demandes/Partials/EtapeDemandeur.vue), envoi au formulaire [DemandeController.php:261](app/Http/Controllers/DemandeController.php#L261), enregistrement sur le compte [DemandeController.php:243](app/Http/Controllers/DemandeController.php#L243).
  - **2. Projet (« II. Informations sur le projet »)** : titre du projet, type de projet présenté en cases comme sur le papier (la liste vient des types gérés par l'admin, voir 3.4), résumé du synopsis (champ `description`) et objectif(s) principal(aux). Un type marqué « précision demandée » (« Autre (précisez) ») fait apparaître un champ de précision obligatoire ; la précision n'est gardée que pour ce type ([DemandeController.php:224](app/Http/Controllers/DemandeController.php#L224)) — composant [EtapeProjet.vue](resources/js/Pages/Demandes/Partials/EtapeProjet.vue).
  - **3. Aire protégée et période** : aire protégée concernée (une seule par demande, en cases ou sur la carte), période prévue (début, fin) et nombre de personnes sur le terrain (utilisé par les statistiques de fréquentation) — composant [EtapeAirePeriode.vue](resources/js/Pages/Demandes/Partials/EtapeAirePeriode.vue).
  - **4. Pièces à joindre, selon le type** : l'étape n'apparaît que si le type de projet exige des pièces ([Create.vue:172](resources/js/Pages/Demandes/Create.vue#L172)) ; composant [EtapePieces.vue](resources/js/Pages/Demandes/Partials/EtapePieces.vue). Choisir un fichier efface aussitôt le message « pièce obligatoire » de cette pièce ([Create.vue:108](resources/js/Pages/Demandes/Create.vue#L108)).
  - **5. Récapitulatif et engagement (« V. Engagement du demandeur »)** : tout le dossier (demandeur, projet, aire protégée, dates et nombre de jours, équipe, résultat du calendrier, pièces fournies ou manquantes) avec un lien *Modifier* par partie, puis l'engagement : « Je soussigné(e) [nom] », N° de CIN ou de passeport (enregistré sur le compte), les quatre engagements du formulaire et une case *J'accepte ces engagements* qui vaut signature, datée de l'envoi. La case n'est jamais pré-cochée : l'engagement est renouvelé à chaque envoi, y compris pour un complément. Engagements [Demande.php:23](app/Models/Demande.php#L23), récapitulatif [EtapeRecap.vue](resources/js/Pages/Demandes/Partials/EtapeRecap.vue).
  - **Vérification à chaque étape** : *Suivant* contrôle les champs de l'étape avec les mêmes exigences que le serveur (coordonnées, titre, type et sa précision, synopsis, objectifs, aire protégée, date passée, fin avant début, équipe d'au moins une personne, période bloquante, pièces manquantes, pièce d'identité, engagement) et place le curseur sur le premier champ en erreur — [Create.vue:178](resources/js/Pages/Demandes/Create.vue#L178) (`erreursEtape`), [Create.vue:250](resources/js/Pages/Demandes/Create.vue#L250) (`suivant`). Une étape n'est accessible dans la barre que si les précédentes sont complètes ([Create.vue:211](resources/js/Pages/Demandes/Create.vue#L211)) ; une étape en erreur y apparaît en rouge.
  - **Envoi** depuis la dernière étape seulement ; si une étape est incomplète, le formulaire y retourne ([Create.vue:258](resources/js/Pages/Demandes/Create.vue#L258), `valider`).
  - **Erreurs du serveur** : le formulaire revient à la première étape concernée — [Create.vue:269](resources/js/Pages/Demandes/Create.vue#L269) (`allerAuxErreurs`).
  - **Reprise d'un brouillon ou d'un complément** : le formulaire s'ouvre sur la première étape incomplète, au plus tard sur l'engagement ([Create.vue:216](resources/js/Pages/Demandes/Create.vue#L216)). *Enregistrer le brouillon* reste disponible à chaque étape.
- **Pièces justificatives dynamiques** : la liste des documents exigés dépend du type de projet choisi. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, ODT, 20 Mo maximum par fichier.
  - Règles de validation [DemandeRequest.php:63-72](app/Http/Requests/DemandeRequest.php#L63-L72), formats [DemandeDossier.php:24](app/Support/DemandeDossier.php#L24).
- **Pièces réutilisables reprises d'office** : une pièce que l'administrateur a marquée « réutilisable » (copie de la CIN ou du passeport, assurance…) et que le demandeur a déjà déposée dans un autre de ses dossiers est reprise automatiquement : l'étape « Pièces à joindre » affiche « Reprise de votre dossier FAN-… (déposée le …) » avec un lien vers le fichier. Le demandeur peut la remplacer par un nouveau fichier ou cliquer *Ne pas reprendre* (puis *Reprendre* pour revenir en arrière). La correspondance se fait sur le libellé de la pièce (casse et espaces ignorés), donc aussi d'un type de projet à l'autre ; c'est le dernier fichier déposé qui est retenu. Le serveur ne reprend qu'un fichier appartenant au demandeur et le **copie** dans le nouveau dossier : supprimer un ancien brouillon ne touche pas la pièce reprise.
  - Recherche des pièces [DemandeDossier.php:58](app/Support/DemandeDossier.php#L58) (`piecesReutilisables`), envoi au formulaire [DemandeController.php:264](app/Http/Controllers/DemandeController.php#L264), pièces demandées et vérifiées [DemandeRequest.php:138](app/Http/Requests/DemandeRequest.php#L138) (`piecesReprises`), copie du fichier [DemandeDossier.php:154](app/Support/DemandeDossier.php#L154) (`reprendreDocument`) ; côté Vue : état de chaque pièce [Create.vue:154](resources/js/Pages/Demandes/Create.vue#L154), *Ne pas reprendre* [Create.vue:102](resources/js/Pages/Demandes/Create.vue#L102), affichage [EtapePieces.vue](resources/js/Pages/Demandes/Partials/EtapePieces.vue), mention « reprise » dans [EtapeRecap.vue](resources/js/Pages/Demandes/Partials/EtapeRecap.vue).
- **Carte de choix du site** : toutes les aires protégées sont affichées. Un clic sur une zone ou un marqueur choisit le site (synchronisé avec les cases de l'étape 3), et le survol affiche son nom, sa surface et son état sur les dates choisies.
  - Événements de la carte [DashboardMap.vue:177](resources/js/Components/DashboardMap.vue#L177), mise en avant du site choisi [DashboardMap.vue:390](resources/js/Components/DashboardMap.vue#L390), infobulle [DashboardMap.vue:91](resources/js/Components/DashboardMap.vue#L91).
- **Carte colorée selon les dates** : dès que les dates sont saisies, chaque site prend la couleur de son état (vert = ouvert, hachures ocre = période sensible, hachures rouges = période bloquante), avec une légende. Le demandeur voit d'un coup d'œil quels sites sont ouverts sur son séjour.
  - Calcul des états [Create.vue:79](resources/js/Pages/Demandes/Create.vue#L79), coloration [DashboardMap.vue:375](resources/js/Components/DashboardMap.vue#L375), hachures [carte.js:112](resources/js/utils/carte.js#L112).
- **Fiche du site choisi** : nom, surface calculée à partir du contour, description.
  - Colonne [VerificationCalendrier.vue](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue) : fiche [L51](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue#L51), surface [L25](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue#L25), calcul de surface [carte.js:93](resources/js/utils/carte.js#L93).
- **Vérification du calendrier pendant la saisie** : les périodes sensibles à venir du site sont affichées et les chevauchements avec les dates choisies sont signalés immédiatement (avertissement ou blocage).
  - Périodes envoyées au formulaire [DemandeController.php:272](app/Http/Controllers/DemandeController.php#L272) ; détection côté Vue [Create.vue:74-93](resources/js/Pages/Demandes/Create.vue#L74-L93) (état du calendrier partagé par le récapitulatif et la colonne de vérification : `calendrier`, [L89](resources/js/Pages/Demandes/Create.vue#L89)).
- **Brouillon** : seuls l'aire protégée et le type de projet sont obligatoires ; ni les coordonnées ni l'engagement ne sont exigés. Le brouillon peut être repris plus tard, et les pièces déjà envoyées sont conservées.
  - [DemandeRequest.php:35-74](app/Http/Requests/DemandeRequest.php#L35-L74) (brouillon ou envoi : `estSoumission`, [L111](app/Http/Requests/DemandeRequest.php#L111)), choix brouillon / envoi [Create.vue:118](resources/js/Pages/Demandes/Create.vue#L118).
- **Soumission** : tous les champs des sections I, II et V, l'acceptation des engagements et toutes les pièces sont exigés (sauf les pièces déjà fournies ou reprises d'un autre dossier). La date de début ne peut pas être passée. Les messages d'erreur sont en français.
  - Champs du formulaire [DemandeRequest.php:42-57](app/Http/Requests/DemandeRequest.php#L42-L57), précision du type (`precisionRequise`, [L127](app/Http/Requests/DemandeRequest.php#L127)), pièces déjà fournies ou reprises [DemandeRequest.php:63-67](app/Http/Requests/DemandeRequest.php#L63-L67), date [DemandeRequest.php:52](app/Http/Requests/DemandeRequest.php#L52), création [DemandeController.php:86](app/Http/Controllers/DemandeController.php#L86), modification [DemandeController.php:146](app/Http/Controllers/DemandeController.php#L146).
- **Complément** : le commentaire du validateur s'affiche en haut du formulaire. Le demandeur peut joindre un message (dernière étape), puis le dossier est renvoyé en analyse. Si le validateur a changé le statut pendant la saisie (par exemple un rejet), rien n'est enregistré et un message l'explique.
  - Commentaire affiché [DemandeController.php:137-142](app/Http/Controllers/DemandeController.php#L137-L142), renvoi [DemandeController.php:169-174](app/Http/Controllers/DemandeController.php#L169-L174), statut changé entre-temps [DemandeController.php:189-192](app/Http/Controllers/DemandeController.php#L189-L192).
- Un nouveau fichier remplace l'ancien du même type. Si le type de projet change, les pièces qui ne correspondent plus sont supprimées. Une pièce sans nouveau fichier ni fichier déjà joint est reprise d'un autre dossier si le demandeur l'a gardée.
  - [DemandeDossier.php:92](app/Support/DemandeDossier.php#L92) (`syncDocuments`).

**Détail d'une demande** (`/demandes/{id}`)
- Titre et type de projet (avec sa précision), aire protégée, séjour, équipe, résumé du synopsis, objectifs, pièces jointes téléchargeables.
- **Formulaire de demande (PDF)** dès l'envoi du dossier (bouton du bloc *Actions*) : le formulaire papier de Fanamby rempli, voir 3.5.
- **Timeline interactive du dossier** (« Suivi du dossier »), partagée avec la fiche du validateur — composant [TimelineDemande.vue](resources/js/Components/TimelineDemande.vue) :
  - étapes passées tirées de l'historique des statuts, avec des libellés parlants (« Demande envoyée », « Prise en charge », « Complément demandé », « Dossier complété et renvoyé », « Demande validée »…) et le nombre de jours écoulés depuis l'étape précédente (« +3 j ») — [TimelineDemande.vue:39](resources/js/Components/TimelineDemande.vue#L39) ; l'étape « Brouillon créé » ouvre la timeline si le dossier a été préparé en brouillon ;
  - **étape actuelle** mise en avant et ouverte par défaut ;
  - **étapes à venir** en grisé : envoi, prise en charge, renvoi du complément, décision avec une **date estimée** (« vers le 28/10 », d'après le délai habituel de décision du site), puis arrivée et fin du séjour — [TimelineDemande.vue:55](resources/js/Components/TimelineDemande.vue#L55) (estimation), [TimelineDemande.vue:68](resources/js/Components/TimelineDemande.vue#L68) (séjour). Pour une demande validée, l'arrivée et la fin du séjour deviennent des étapes passées une fois leur date atteinte ;
  - **chaque étape s'ouvre au clic** (boutons accessibles au clavier, `aria-expanded`) : date et heure, auteur, commentaire complet, **pièces déposées à cette étape** (téléchargeables) et, sur le dernier envoi, **le résultat de l'analyse du calendrier**. Un lien *Tout déplier / Tout replier* ouvre toutes les étapes — rattachement des pièces [TimelineDemande.vue:102](resources/js/Components/TimelineDemande.vue#L102), analyse [TimelineDemande.vue:111](resources/js/Components/TimelineDemande.vue#L111) ;
  - côté demandeur, les agents ne sont pas nommés (« Validateur », « Administration », « Vous ») — [TimelineDemande.vue:29](resources/js/Components/TimelineDemande.vue#L29).
  - Données : historique trié par date ([DemandeController.php:59](app/Http/Controllers/DemandeController.php#L59)), délai habituel transmis tant que le dossier est en instruction ([DemandeController.php:73](app/Http/Controllers/DemandeController.php#L73)).
- Mise en avant de la demande de complément en cours.
- Boutons *Reprendre le brouillon*, *Compléter le dossier* et *Supprimer* (brouillon uniquement, avec confirmation).
- Ouverture des PDF officiels lorsque la demande est validée.
- Code : [DemandeController.php:53](app/Http/Controllers/DemandeController.php#L53) (`show`, complément en cours [ligne 64](app/Http/Controllers/DemandeController.php#L64)), suppression [DemandeController.php:211](app/Http/Controllers/DemandeController.php#L211) ; page [Demandes/Show.vue](resources/js/Pages/Demandes/Show.vue).

### 3.3 Validateur et admin (instruction)

Définitions communes : un dossier est **en retard** après 7 jours sans décision ([StatsInstruction.php:21](app/Support/StatsInstruction.php#L21)) ; une **arrivée est imminente** quand le séjour commence dans 14 jours ou moins (ou a déjà commencé) sans décision ([StatsInstruction.php:24](app/Support/StatsInstruction.php#L24)). Les brouillons sont exclus de toutes les statistiques, et un validateur ne voit que ses sites ([StatsInstruction.php:31](app/Support/StatsInstruction.php#L31), [Demande.php:83](app/Models/Demande.php#L83)).

**Statistiques principales** (en tête des deux tableaux de bord) — calcul [StatsInstruction.php:83](app/Support/StatsInstruction.php#L83), affichage [StatsPrincipales.vue](resources/js/Pages/Dashboard/Partials/StatsPrincipales.vue) :
- **Demandes reçues** : toutes les demandes envoyées (hors brouillons), dont celles du mois en cours ; lien vers la liste des demandes.
- **En attente** : dossiers sans décision finale (soumis, en analyse ou à compléter), dont ceux chez le demandeur.
- **Validées** : avec le taux de validation, calculé sur les seuls dossiers décidés ; lien vers la liste filtrée sur « validée ».
- Une barre de répartition montre la part en attente, validée et rejetée des demandes reçues (reçues = en attente + validées + rejetées).

Les autres indicateurs s'affichent dessous, en plus petit.

**Tableau de bord du validateur** — « qu'est-ce que je dois traiter maintenant ? » — [StatsValidateur.php:15](app/Support/StatsValidateur.php#L15), affichage [EspaceValidateur.vue](resources/js/Pages/Dashboard/Partials/EspaceValidateur.vue)
- Indicateurs secondaires : dossiers à traiter (dont non pris en charge), arrivées imminentes, retards, conflits de calendrier — [StatsInstruction.php:107](app/Support/StatsInstruction.php#L107).
- **Ma file de traitement** : les dossiers en attente triés par urgence (arrivées imminentes d'abord, puis retards, puis les autres), avec pour chacun l'arrivée (« dans 5 j », « dépassée de 2 j »), le nombre de jours d'attente et l'alerte de période sensible — [StatsInstruction.php:43](app/Support/StatsInstruction.php#L43), ligne affichée [LigneDossier.vue](resources/js/Pages/Dashboard/Partials/LigneDossier.vue).
- **En attente du demandeur** : compléments demandés, les plus anciens d'abord, avec le nombre de jours écoulés (pour relancer) — [StatsValidateur.php:32](app/Support/StatsValidateur.php#L32).
- **Mon activité du mois** : prises en charge, compléments demandés, validations, rejets, et délai médian de décision sur ses sites — [StatsValidateur.php:58](app/Support/StatsValidateur.php#L58).
- Carte de ses sites (voir ci-dessous).

**Tableau de bord de l'admin** — « le service fonctionne-t-il, et quel est l'effet sur les aires protégées ? » — [StatsAdmin.php:19](app/Support/StatsAdmin.php#L19), affichage [EspaceAdmin.vue](resources/js/Pages/Dashboard/Partials/EspaceAdmin.vue)
- Indicateurs secondaires : **délai médian de décision** et part des dossiers décidés en 15 jours ou moins ; dossiers à traiter par les validateurs (arrivées imminentes, retards) ; part des dossiers passés par un complément ; **personnes sur le terrain aujourd'hui** — décisions [StatsAdmin.php:22](app/Support/StatsAdmin.php#L22), objectif 15 jours [StatsAdmin.php:17](app/Support/StatsAdmin.php#L17), pourcentages [StatsAdmin.php:152](app/Support/StatsAdmin.php#L152).
- **Points d'attention** : sites sans validateur (les nouvelles demandes n'y sont notifiées à personne), puis dossiers urgents, en retard ou en conflit.
- Histogramme des demandes reçues par mois, **comparé à l'année précédente** — [StatsAdmin.php:137](app/Support/StatsAdmin.php#L137).
- **Fréquentation autorisée** sur les 12 prochains mois, par site : séjours, personnes et **personnes × jours** (la pression humaine réelle sur le site) — [StatsAdmin.php:115](app/Support/StatsAdmin.php#L115).
- **Charge des validateurs** : dossiers en attente sur les sites de chacun — [StatsAdmin.php:93](app/Support/StatsAdmin.php#L93).
- **Sur le terrain aujourd'hui** : séjours en cours (demandeur, site, équipe, date de fin) — [StatsAdmin.php:65](app/Support/StatsAdmin.php#L65).
- Répartition par type de projet.
- Carte de toutes les aires protégées (voir ci-dessous).

**Carte du tableau de bord** (admin et validateur) — données [StatsInstruction.php:122](app/Support/StatsInstruction.php#L122), composant [CarteSites.vue](resources/js/Pages/Dashboard/Partials/CarteSites.vue)
- **Choix de la date** sur l'année à venir : les sites restreints ce jour-là sont colorés (hachures ocre = période sensible, hachures rouges = bloquant), avec un résumé (« 2 bloqués · 1 sensible »). Pour viser un jour précis : champ date et boutons ‹ › jour par jour ([CarteSites.vue:33-42](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L33-L42)), sauts à la période précédente ou suivante ([L57-63](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L57-L63)). Le curseur reste disponible pour parcourir l'année : sa piste montre en couleur les jours sensibles (ocre) et bloquants (rouge), tous sites confondus ([L65-85](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L65-L85)), avec les repères de mois en dessous ([L87-100](resources/js/Pages/Dashboard/Partials/CarteSites.vue#L87-L100)).
- **Charge par site** : un cercle bleu dont la taille suit le nombre de dossiers en attente, avec un contour rouge si l'un d'eux est en retard ou en conflit de calendrier. La fiche du site au clic détaille ces chiffres — [DashboardMap.vue:362](resources/js/Components/DashboardMap.vue#L362), fiche [DashboardMap.vue:56](resources/js/Components/DashboardMap.vue#L56).

**Dossiers à valider / Toutes les demandes** (`/validateur/demandes`)
- Liste paginée (15 par page) avec recherche (nom ou e-mail du demandeur, site, numéro `#id`) et filtres par statut, par site et par période (du / au) ; sous le nom du demandeur, le titre et le type de projet — [Validateur/DemandeController.php:29](app/Http/Controllers/Validateur/DemandeController.php#L29), filtres [Validateur/DemandeController.php:98-136](app/Http/Controllers/Validateur/DemandeController.php#L98-L136) ; page [Validateur/Demandes/Index.vue](resources/js/Pages/Validateur/Demandes/Index.vue).
- Indicateur visuel des dossiers qui chevauchent une période sensible.
- **Export CSV** des demandes filtrées, compatible Excel (séparateur `;`, encodage UTF-8 avec BOM). Il contient demandeur, site, titre et type de projet, dates, statut, date de décision, délai en jours et nombre de chevauchements — [Validateur/DemandeController.php:51-93](app/Http/Controllers/Validateur/DemandeController.php#L51-L93), lien d'export [Validateur/Demandes/Index.vue:31](resources/js/Pages/Validateur/Demandes/Index.vue#L31).

**Dossier complet** (`/validateur/demandes/{id}`) — [Validateur/DemandeController.php:138](app/Http/Controllers/Validateur/DemandeController.php#L138), page [Validateur/Demandes/Show.vue](resources/js/Pages/Validateur/Demandes/Show.vue)
- Identité et coordonnées du demandeur (organisation, fonction, téléphone, adresse, CIN ou passeport), type de projet, aire protégée, séjour, équipe, date de dépôt ([Show.vue:133](resources/js/Pages/Validateur/Demandes/Show.vue#L133)), alertes de chevauchement, bloc *Projet* (titre, résumé du synopsis, objectifs, lien vers le **formulaire de demande en PDF**, [Show.vue:164](resources/js/Pages/Validateur/Demandes/Show.vue#L164)), pièces jointes (avec signalement des pièces manquantes [Show.vue:71](resources/js/Pages/Validateur/Demandes/Show.vue#L71)).
- **Timeline interactive du dossier**, la même que côté demandeur (voir 3.2), avec en plus le nom et le rôle de l'auteur de chaque étape. L'historique est trié par date ([Validateur/DemandeController.php:145](app/Http/Controllers/Validateur/DemandeController.php#L145)) et la décision estimée d'après le délai habituel du site ([Validateur/DemandeController.php:163](app/Http/Controllers/Validateur/DemandeController.php#L163)).
- Alerte lorsque le dossier attend une décision depuis plus de 7 jours — [Show.vue:74](resources/js/Pages/Validateur/Demandes/Show.vue#L74).
- **Formulaire de décision**, qui reprend la section VI « Décision de Fanamby » du formulaire papier : seules les transitions autorisées sont proposées (*Prendre en charge*, *Demander un complément*, *Avis favorable* — validation en deux temps ci-dessous —, *Avis défavorable* — rejet définitif). Le commentaire est obligatoire ; pour un avis, il s'intitule *Observations* et figure, avec le nom et la fonction du décideur (renseignée dans son profil), dans la section VI du formulaire de demande en PDF — libellés [Show.vue:22](resources/js/Pages/Validateur/Demandes/Show.vue#L22), intitulé du commentaire [Show.vue:93](resources/js/Pages/Validateur/Demandes/Show.vue#L93), validation [DecisionRequest.php:26](app/Http/Requests/DecisionRequest.php#L26), enregistrement [Validateur/DemandeController.php:169](app/Http/Controllers/Validateur/DemandeController.php#L169).
- **Validation en deux temps** :
  1. *Avis favorable* puis *Générer les documents* prépare l'autorisation et le cahier des charges, sans changer le statut — [Validateur/DemandeController.php:239](app/Http/Controllers/Validateur/DemandeController.php#L239), textes par défaut [DocumentsOfficiels.php:60](app/Support/DocumentsOfficiels.php#L60) (objet de la mission repris des objectifs du projet, à défaut du synopsis ; règles du type de projet ; engagement repris des engagements du formulaire de demande, [DocumentsOfficiels.php:83](app/Support/DocumentsOfficiels.php#L83)).
  2. Les documents s'affichent en haut du dossier, dans deux onglets ([DocumentsGeneres.vue](resources/js/Pages/Validateur/Demandes/Partials/DocumentsGeneres.vue)). Les **textes** sont modifiables (introduction, objet, conditions particulières, mention de réserve, signataire ; règles et engagement du cahier des charges) ; les **données du dossier** (bénéficiaire, aire protégée, type et titre du projet, dates, équipe) restent fixes et sont toujours lues sur la demande. Enregistrement [Validateur/DemandeController.php:250](app/Http/Controllers/Validateur/DemandeController.php#L250), champs et longueurs [DocumentsOfficiels.php:20](app/Support/DocumentsOfficiels.php#L20). Un **aperçu PDF** marqué « Projet » est disponible ([Validateur/DemandeController.php:276](app/Http/Controllers/Validateur/DemandeController.php#L276)) ; *Annuler la génération* supprime les documents ([L263](app/Http/Controllers/Validateur/DemandeController.php#L263)).
  3. *Signer et valider le dossier* : le validateur **retape son nom** (casse, accents et espaces ignorés) — contrôle [DecisionRequest.php:38](app/Http/Requests/DecisionRequest.php#L38), comparaison [DocumentsOfficiels.php:175](app/Support/DocumentsOfficiels.php#L175), champ [Show.vue:272](resources/js/Pages/Validateur/Demandes/Show.vue#L272). Le bouton reste bloqué tant que des modifications des documents ne sont pas enregistrées. La validation est refusée si les documents n'ont pas été générés ([Validateur/DemandeController.php:190](app/Http/Controllers/Validateur/DemandeController.php#L190)).

  Les documents ne se préparent que pour un dossier qui peut encore être validé ([Validateur/DemandeController.php:289](app/Http/Controllers/Validateur/DemandeController.php#L289)) et ne sont plus modifiables une fois le dossier validé. Une demande de complément ou un avis défavorable les supprime ([Validateur/DemandeController.php:197](app/Http/Controllers/Validateur/DemandeController.php#L197)) : ils seront regénérés à partir du dossier complété.
- Un admin ou un validateur ne peut pas statuer sur **sa propre demande** : un autre décideur doit le faire — `DemandePolicy::decider` [DemandePolicy.php:76](app/Policies/DemandePolicy.php#L76), vérifiée avant la validation par [DecisionRequest.php:21](app/Http/Requests/DecisionRequest.php#L21).
- Si un autre décideur a clôturé le dossier pendant que la page était ouverte, la décision est refusée avec un message (« Cette demande est clôturée… ») — [Validateur/DemandeController.php:183-187](app/Http/Controllers/Validateur/DemandeController.php#L183-L187).

**Références** (`/references`) — modèles des documents officiels et documents de l'équipe, réservés aux validateurs et aux admins (entrée « Références » du groupe *Instruction* du menu, [AuthenticatedLayout.vue:48](resources/js/Layouts/AuthenticatedLayout.vue#L48))
- **Modèles officiels** : une carte par modèle avec son titre, à quoi il sert, quand l'utiliser, les champs à compléter (les 6 premiers, puis « +N ») et l'indication « Généré à la validation » ou « À compléter à la main » — données [ReferenceController.php:17](app/Http/Controllers/ReferenceController.php#L17), cartes [Index.vue:111](resources/js/Pages/References/Index.vue#L111). Les modèles sont en base (table `t_modeles_reference`) ; la migration y a repris les cinq modèles d'origine : **avis favorable** (lettre du gestionnaire à l'autorité compétente), **autorisation officielle d'accès**, **cahier des charges**, **avis défavorable** (refus motivé) et **demande de complément**. Les modèles de l'autorisation et du cahier des charges suivent le formulaire de demande (type et titre du projet, engagements du demandeur).
- *Ouvrir le modèle* affiche le PDF dans un nouvel onglet ([Index.vue:22](resources/js/Pages/References/Index.vue#L22), [ModeleReferenceController.php:18](app/Http/Controllers/ModeleReferenceController.php#L18)). Les champs à remplir sont surlignés et chaque PDF porte un bandeau « Modèle de référence — sans valeur officielle » ([modele_reference.blade.php](resources/views/pdf/modele_reference.blade.php)).
- **Gestion des modèles (admins)** : *Nouveau modèle*, *Modifier* et *Supprimer* n'apparaissent que pour un admin ([Index.vue:140](resources/js/Pages/References/Index.vue#L140)). Le formulaire [ModeleForm.vue](resources/js/Pages/References/ModeleForm.vue) (création et modification) demande le titre (unique), à quoi sert le document, quand l'utiliser (facultatif), une icône parmi une liste, l'indication « produit par la plateforme » et le **contenu** — contrôleur [ModeleReferenceController.php](app/Http/Controllers/ModeleReferenceController.php) (création [L29](app/Http/Controllers/ModeleReferenceController.php#L29), modification [L41](app/Http/Controllers/ModeleReferenceController.php#L41), suppression [L48](app/Http/Controllers/ModeleReferenceController.php#L48)), validation [ModeleReferenceRequest.php](app/Http/Requests/ModeleReferenceRequest.php).
  - Le contenu s'écrit en texte simple, mis en forme à l'impression : `# Titre`, `## Partie`, `- élément` (liste), `> texte` (aligné à droite : date, destinataire, signature), `**gras**`, `[champ]` (champ à compléter, surligné), `...` seul sur une ligne (ligne pointillée à remplir) ; une ligne vide sépare les paragraphes. Un aide-mémoire est affiché sous le champ ([ModeleForm.vue:130](resources/js/Pages/References/ModeleForm.vue#L130)). Le texte saisi est échappé avant la mise en forme — [ModelesReference.php:44](app/Support/ModelesReference.php#L44).
  - Les **champs à compléter** ne sont pas saisis à part : ce sont les textes entre crochets, sans doublon ([ModelesReference.php:34](app/Support/ModelesReference.php#L34)). Le formulaire les affiche au fur et à mesure de la saisie ([ModeleForm.vue:39](resources/js/Pages/References/ModeleForm.vue#L39)).
  - *Aperçu PDF* montre le rendu du texte en cours, avant enregistrement ([ModeleForm.vue:47](resources/js/Pages/References/ModeleForm.vue#L47), [ModeleReferenceController.php:58](app/Http/Controllers/ModeleReferenceController.php#L58), envoi [openPdf.js:29](resources/js/utils/openPdf.js#L29)).
  - L'indication « produit par la plateforme » ne fait qu'afficher le badge : modifier le modèle de l'autorisation ou du cahier des charges ne change pas les documents générés, qui se relisent dans chaque dossier (voir plus haut).
- **Documents de l'équipe** : sous les modèles, chaque validateur ou admin peut déposer ses propres documents (avis signés, courriers types, notes) avec un titre et une description facultative. Formats PDF, JPG, PNG, DOC, DOCX, ODT, 20 Mo maximum ; le titre est proposé à partir du nom du fichier. Les documents sont **partagés avec toute l'équipe** (validateurs et admins), du plus récent au plus ancien, avec leur auteur, leur taille et leur date de dépôt — formulaire [Index.vue:166](resources/js/Pages/References/Index.vue#L166), liste [Index.vue:195](resources/js/Pages/References/Index.vue#L195), dépôt [DocumentReferenceController.php:17](app/Http/Controllers/DocumentReferenceController.php#L17).
  - *Voir* ouvre un aperçu dans la page : PDF et images s'affichent directement, les fichiers Word et OpenDocument proposent le téléchargement. Échap ou un clic à côté ferme l'aperçu — [ApercuDocument.vue](resources/js/Pages/References/Partials/ApercuDocument.vue) (fichier récupéré par une requête authentifiée, comme les PDF officiels).
  - *Télécharger* récupère le fichier d'origine ; *Supprimer* n'apparaît que pour l'auteur du dépôt et les admins — [DocumentReferencePolicy.php:15](app/Policies/DocumentReferencePolicy.php#L15).

### 3.4 Administration (admin uniquement)

| Écran | Fonctionnalités | Code |
|---|---|---|
| **Aires protégées** (`/admin/sites`) | Liste avec recherche et nombre de demandes par site. Création et modification avec **dessin du polygone sur carte** (outil polygone, déplacement des points, effacement). Suppression impossible si des demandes y sont rattachées. | [SiteController.php:13](app/Http/Controllers/Admin/SiteController.php#L13), suppression [SiteController.php:66](app/Http/Controllers/Admin/SiteController.php#L66), dessin [MapComponent.vue](resources/js/Components/MapComponent.vue), pages [Admin/Sites/](resources/js/Pages/Admin/Sites/) |
| **Périodes sensibles** (`/admin/periode-sensibles`) | Périodes de restriction par site, de niveau **bloquant** (la soumission est refusée) ou **avertissement** (la soumission est acceptée mais le conflit est signalé). Filtre par site. Par défaut, seules les périodes en cours ou à venir sont affichées ; une option montre aussi l'historique. | [PeriodeSensibleController.php:13](app/Http/Controllers/Admin/PeriodeSensibleController.php#L13), pages [Admin/PeriodeSensibles/](resources/js/Pages/Admin/PeriodeSensibles/) |
| **Types de demandes** (`/admin/type-demandes`) | **Types de projet** du formulaire de demande (Long métrage, Court métrage, Documentaire, Émission télé, Projet étudiant, Publicité, Corporatif, Clip musical, Reportage, Téléfilm, Photographie institutionnelle, Autre) avec leurs **pièces requises** et leurs **règles spécifiques** (reprises dans le cahier des charges), saisies ligne par ligne. Chaque pièce peut être cochée **Réutilisable** : elle est alors reprise d'office des dossiers précédents du demandeur (mention « · réutilisable » dans la liste des types). Renommer une pièce garde les fichiers déjà déposés ; retirer une pièce ne supprime pas les fichiers des dossiers soumis. Une même pièce ne peut pas figurer deux fois. La case **Le demandeur précise son projet** (`precision_requise`, pour « Autre (précisez) ») rend obligatoire un champ de précision dans le formulaire de demande ; la liste affiche « Précision demandée ». Suppression impossible si des demandes utilisent le type. | [TypeDemandeController.php:25](app/Http/Controllers/Admin/TypeDemandeController.php#L25), validation [TypeDemandeController.php:61](app/Http/Controllers/Admin/TypeDemandeController.php#L61), précision [TypeDemandeController.php:83](app/Http/Controllers/Admin/TypeDemandeController.php#L83), mise à jour des listes [TypeDemande.php:39](app/Models/TypeDemande.php#L39), suppression [TypeDemandeController.php:97](app/Http/Controllers/Admin/TypeDemandeController.php#L97), éditeur [ListeEditable.vue](resources/js/Components/ListeEditable.vue) (case à cocher facultative par ligne : prop `option`), pages [Admin/TypeDemandes/](resources/js/Pages/Admin/TypeDemandes/) |
| **Utilisateurs** (`/admin/users`) | Liste avec recherche (nom, e-mail) et filtre par rôle. Changement de rôle et **affectation des sites** aux validateurs (les sites choisis doivent exister). Les affectations sont retirées si l'utilisateur n'est plus validateur. Un admin ne peut pas se retirer son propre rôle. | [UserController.php:14](app/Http/Controllers/Admin/UserController.php#L14), mise à jour [UserController.php:41](app/Http/Controllers/Admin/UserController.php#L41), propre rôle [UserController.php:52](app/Http/Controllers/Admin/UserController.php#L52), sites [UserController.php:56-66](app/Http/Controllers/Admin/UserController.php#L56-L66), pages [Admin/Users/](resources/js/Pages/Admin/Users/) |

### 3.5 Fonctions transverses

- **Notifications (cloche et email)** : cloche avec compteur de non-lues dans l'en-tête, et chaque notification est aussi envoyée par email au destinataire. Le demandeur est notifié à la soumission et à chaque décision ; les décideurs du site sont notifiés à chaque nouvelle demande et à chaque complément renvoyé ; à chaque changement de statut, les autres validateurs du site et les admins sont informés (sans l'auteur de la décision). Un clic marque la notification comme lue et ouvre le bon dossier selon le rôle. Le bouton *Tout marquer comme lu* du panneau vide la cloche d'un coup, sans changer de page.
  - Envoi regroupé dans [DemandeNotifications.php](app/Support/DemandeNotifications.php) : soumission [L21](app/Support/DemandeNotifications.php#L21), complément renvoyé [L37](app/Support/DemandeNotifications.php#L37), décision [L56](app/Support/DemandeNotifications.php#L56), cloche et email [L76](app/Support/DemandeNotifications.php#L76) ; choix des décideurs [DemandeDecisionRules.php:21](app/Support/DemandeDecisionRules.php#L21) et de l'équipe informée d'une décision [DemandeDecisionRules.php:53](app/Support/DemandeDecisionRules.php#L53), email [DemandeNotificationMail.php](app/Mail/DemandeNotificationMail.php) (mis en file d'attente, envoyé après l'enregistrement de la décision ; le lien ouvre le dossier du demandeur ou la fiche d'instruction), modèle [demande-notification.blade.php](resources/views/mail/demande-notification.blade.php), textes [DemandeNotificationMessage.php](app/Support/DemandeNotificationMessage.php), lecture [NotificationController.php:10](app/Http/Controllers/NotificationController.php#L10), tout marquer comme lu [NotificationController.php:35](app/Http/Controllers/NotificationController.php#L35) (bouton [AuthenticatedLayout.vue:160](resources/js/Layouts/AuthenticatedLayout.vue#L160)), partage à chaque page [HandleInertiaRequests.php:41](app/Http/Middleware/HandleInertiaRequests.php#L41), cloche [AuthenticatedLayout.vue](resources/js/Layouts/AuthenticatedLayout.vue).
- **Formulaire de demande (PDF)**, disponible dès l'envoi du dossier pour le demandeur et les décideurs du site : le formulaire papier de Fanamby rempli — I. demandeur, II. projet (cases du type de projet, avec la précision de « Autre », et de l'aire protégée), V. engagement (pièce d'identité, engagements, date et mention de l'acceptation en ligne), VI. décision de Fanamby (avis favorable ou défavorable coché, observations, date, nom et fonction du décideur ; vierge tant qu'aucun avis n'est rendu) — [PdfController.php:31](app/Http/Controllers/PdfController.php#L31), données [FormulaireDemande.php:27](app/Support/FormulaireDemande.php#L27), décision [FormulaireDemande.php:43](app/Support/FormulaireDemande.php#L43), modèle [formulaire_demande.blade.php](resources/views/pdf/formulaire_demande.blade.php).
- **PDF officiels** (DomPDF), générés à la volée pour une demande validée, avec les textes relus par le validateur (texte par défaut pour les dossiers validés avant l'étape de génération, [DocumentsOfficiels.php:97](app/Support/DocumentsOfficiels.php#L97)) — rendu [DocumentsOfficiels.php:199](app/Support/DocumentsOfficiels.php#L199) :
  - *Autorisation officielle d'accès* : en-tête Association FANAMBY, bénéficiaire, aire protégée, type et titre du projet, dates, équipe, objet de la mission, conditions particulières, signataire et mention « Validé et signé électroniquement par … le … » tirée de l'historique — [PdfController.php:18](app/Http/Controllers/PdfController.php#L18), modèle [autorisation.blade.php](resources/views/pdf/autorisation.blade.php) ;
  - *Cahier des charges* : règles du type de projet, avertissements de l'analyse des périodes, engagement du demandeur (par défaut les engagements du formulaire de demande) et mention de leur acceptation en ligne à l'envoi — [PdfController.php:23](app/Http/Controllers/PdfController.php#L23), modèle [cahier_charges.blade.php](resources/views/pdf/cahier_charges.blade.php).

  Les PDF s'ouvrent dans un nouvel onglet via une requête authentifiée, ce qui évite l'interception par les gestionnaires de téléchargement — [openPdf.js:5](resources/js/utils/openPdf.js#L5).
- **Profil** : modification du nom, de l'e-mail et des coordonnées reprises dans le formulaire de demande (organisation, fonction, adresse, téléphone, CIN ou passeport ; la fonction d'un validateur figure dans la section VI), changement de mot de passe, suppression du compte (avec confirmation par mot de passe). La suppression efface les demandes du compte **et leurs fichiers** ; les décisions qu'il a rendues restent dans l'historique des autres dossiers. Le dernier administrateur ne peut pas supprimer son compte.
  - [ProfileController.php:32](app/Http/Controllers/ProfileController.php#L32) (modification), [ProfileController.php:48](app/Http/Controllers/ProfileController.php#L48) (suppression, dernier admin [ligne 57](app/Http/Controllers/ProfileController.php#L57)), fichiers [DemandeDossier.php:97](app/Support/DemandeDossier.php#L97) ; pages [Profile/](resources/js/Pages/Profile/).
- **Référence officielle** des demandes au format `FAN-AAAA-NNNN`, calculée à un seul endroit, par le serveur : accesseur `reference` du modèle [Demande.php:47](app/Models/Demande.php#L47), envoyé avec chaque demande et repris par les pages, les tableaux de bord et les PDF.
- **Limite d'envois** : 20 créations ou modifications de demande par minute et par utilisateur (envoi de fichiers). Au-delà, le formulaire revient avec le message « Trop d'envois en peu de temps » — limiteur `demandes` [AppServiceProvider.php:43](app/Providers/AppServiceProvider.php#L43), appliqué aux routes `store` et `update` [web.php:32](routes/web.php#L32) et au dépôt de documents de référence [web.php:68](routes/web.php#L68).
- **Fonds de carte** sur toutes les cartes de sites : plan, satellite (Esri World Imagery) ou relief (OpenTopoMap), plus un bouton plein écran — [carte.js:35](resources/js/utils/carte.js#L35) (`FondsControl`).
- **Vue 3D du terrain** : le bouton « Voir en 3D » de la fiche d'un site (ou « Voir le terrain en 3D » dans le formulaire de demande) incline la carte sur le site avec le relief (exagéré ×1,5) et l'ombrage des pentes. Un clic droit + glisser fait pivoter la vue ; « Revenir en 2D » remet la carte à plat. Les altitudes (Terrain Tiles d'AWS, issues de SRTM) ne sont téléchargées qu'au premier passage en 3D.
  - [DashboardMap.vue:127](resources/js/Components/DashboardMap.vue#L127) (`activer3d`), [DashboardMap.vue:145](resources/js/Components/DashboardMap.vue#L145) (`voir3d`), [DashboardMap.vue:153](resources/js/Components/DashboardMap.vue#L153) (`revenir2d`), source des altitudes [carte.js:83](resources/js/utils/carte.js#L83).
- **Squelettes de chargement** pendant la navigation (tableau de bord, liste, formulaire, détail), affichés seulement si la réponse tarde plus de 120 ms — délai [useNavigation.js:12](resources/js/composables/useNavigation.js#L12), choix du squelette [useNavigation.js:39](resources/js/composables/useNavigation.js#L39), composants [PageSkeleton.vue](resources/js/Components/PageSkeleton.vue) et [TableSkeleton.vue](resources/js/Components/TableSkeleton.vue).
- **Compteurs animés** sur les tableaux de bord validateur et admin, ainsi que sur le compte à rebours « jours avant l'arrivée » de l'espace demandeur : les chiffres défilent de 0 jusqu'à leur valeur et les barres horizontales s'allongent progressivement (0,9 s). Rien ne bouge si l'option « réduire les animations » du système est active, et les lecteurs d'écran annoncent directement la valeur finale — [CompteurAnime.vue](resources/js/Components/CompteurAnime.vue), barres [Dashboard.vue:100](resources/js/Pages/Dashboard.vue#L100).
- **Filtres synchronisés avec l'URL** : les filtres survivent au rechargement et peuvent être partagés ; la recherche texte est temporisée (350 ms) — [useListFilters.js:8](resources/js/composables/useListFilters.js#L8).
- **Interface responsive** avec menu latéral (repliable sur mobile), charte graphique FANAMBY (vert forêt, rouge réservé aux blocages, latérite, rivière) et respect de l'option « réduire les animations » du système — [AuthenticatedLayout.vue](resources/js/Layouts/AuthenticatedLayout.vue), [app.css](resources/css/app.css), [tailwind.config.js](tailwind.config.js).

---

## 4. Règles de sécurité

Les droits sur une demande sont tous dans [DemandePolicy](app/Policies/DemandePolicy.php). Les contrôleurs l'appellent par `Gate::authorize(...)` ; les formulaires de demande et de décision la vérifient avant la validation des champs ([DemandeRequest.php:30](app/Http/Requests/DemandeRequest.php#L30), [DecisionRequest.php:21](app/Http/Requests/DecisionRequest.php#L21)).

| Règle | Où elle est appliquée |
|---|---|
| Un demandeur ne voit, ne modifie et ne supprime que **ses** demandes. | `DemandePolicy::view`, `update`, `delete` — [DemandePolicy.php:19](app/Policies/DemandePolicy.php#L19) |
| Seuls les brouillons et les dossiers « à compléter » sont modifiables ; seuls les brouillons sont supprimables. | `DemandePolicy::update` [DemandePolicy.php:29](app/Policies/DemandePolicy.php#L29), `DemandeStatutWorkflow::isEditableByDemandeur` [DemandeStatutWorkflow.php:35](app/Support/DemandeStatutWorkflow.php#L35), `DemandePolicy::delete` [DemandePolicy.php:43](app/Policies/DemandePolicy.php#L43) |
| Un brouillon est invisible côté validation (erreur 404). | `DemandePolicy::instruire` — [DemandePolicy.php:58](app/Policies/DemandePolicy.php#L58), liste [Demande.php:75](app/Models/Demande.php#L75) (`horsBrouillon`) |
| Un validateur n'accède qu'aux demandes de ses sites assignés. | liste [Validateur/DemandeController.php:105](app/Http/Controllers/Validateur/DemandeController.php#L105), dossier `DemandePolicy::instruire` [DemandePolicy.php:58](app/Policies/DemandePolicy.php#L58), `Demande::scopeForStatsViewer` [Demande.php:83](app/Models/Demande.php#L83) |
| Pas d'auto-validation : on ne statue jamais sur sa propre demande. | `DemandePolicy::decider` — [DemandePolicy.php:76](app/Policies/DemandePolicy.php#L76) |
| Pièces jointes stockées hors du dossier public (`storage/app/private`), servies uniquement après contrôle d'accès. | `DocumentController` [DocumentController.php:15](app/Http/Controllers/DocumentController.php#L15), `DemandePolicy::consulterFichiers` [DemandePolicy.php:92](app/Policies/DemandePolicy.php#L92), stockage [DemandeDossier.php:78](app/Support/DemandeDossier.php#L78) |
| PDF officiels uniquement pour une demande **validée** et pour les personnes autorisées. | `DemandePolicy::telechargerPdf` — [DemandePolicy.php:102](app/Policies/DemandePolicy.php#L102), appelée par [PdfController.php:40](app/Http/Controllers/PdfController.php#L40) |
| Formulaire de demande en PDF : l'auteur ou un décideur du site, seulement une fois la demande envoyée (pas pour un brouillon). | `DemandePolicy::telechargerFormulaire` — [DemandePolicy.php:119](app/Policies/DemandePolicy.php#L119), appelée par [PdfController.php:33](app/Http/Controllers/PdfController.php#L33) |
| La page Références (modèles et documents de l'équipe) est réservée aux validateurs et aux admins (un demandeur reçoit une erreur 403). Seuls les admins créent, modifient, prévisualisent et suppriment les modèles ; le texte d'un modèle est échappé avant sa mise en forme dans le PDF. Un document déposé ne peut être supprimé que par son auteur ou un admin. Les fichiers sont stockés hors du dossier public (`storage/app/private/references`) et servis avec leur type réel et l'en-tête `nosniff`. | gates `is-validateur` [web.php:65-71](routes/web.php#L65-L71) et `is-admin` [web.php:74-81](routes/web.php#L74-L81), [ModeleReferenceRequest.php:15](app/Http/Requests/ModeleReferenceRequest.php#L15), échappement [ModelesReference.php:99](app/Support/ModelesReference.php#L99), `DocumentReferencePolicy::delete` [DocumentReferencePolicy.php:15](app/Policies/DocumentReferencePolicy.php#L15), envoi du fichier [DocumentReferenceController.php:59](app/Http/Controllers/DocumentReferenceController.php#L59) |
| Une période sensible **bloquante** empêche la soumission. | `DemandeDossier::analysePeriodes` [DemandeDossier.php:27](app/Support/DemandeDossier.php#L27), refus [DemandeController.php:95](app/Http/Controllers/DemandeController.php#L95) et [ligne 156](app/Http/Controllers/DemandeController.php#L156) |
| Les notifications ne peuvent être marquées comme lues que par leur destinataire (une par une ou toutes à la fois). | `NotificationController` — [NotificationController.php:13](app/Http/Controllers/NotificationController.php#L13), [NotificationController.php:38](app/Http/Controllers/NotificationController.php#L38) |
| Deux décisions simultanées ne peuvent pas s'appliquer toutes les deux ; un renvoi de complément n'écrase pas une décision prise entre-temps (transaction + verrou `lockForUpdate`). | `Validateur\DemandeController::updateStatut` [Validateur/DemandeController.php:180](app/Http/Controllers/Validateur/DemandeController.php#L180), `DemandeController::update` [DemandeController.php:162](app/Http/Controllers/DemandeController.php#L162) |
| Les envois de dossiers (création et modification, avec fichiers) et les dépôts de documents de référence sont limités à 20 par minute et par utilisateur. | limiteur `demandes` [AppServiceProvider.php:43](app/Providers/AppServiceProvider.php#L43), routes [web.php:32](routes/web.php#L32) et [web.php:68](routes/web.php#L68) |
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
| [User](app/Models/User.php) | Utilisateur avec un rôle (`admin`, `validateur`, `demandeur`) et ses coordonnées du formulaire de demande (`organisation`, `fonction`, `adresse`, `telephone`, `piece_identite`), reprises dans chaque demande ; la fonction d'un décideur figure dans la section VI. Lié aux sites qu'il valide (table pivot `t_site_user`, [User.php:35](app/Models/User.php#L35)). |
| [Site](app/Models/Site.php) | Aire protégée : nom, description, polygone de coordonnées (JSON `[{lat, lng}, …]`). Liste de la page d'accueil gardée en cache (`pourAccueil`, [Site.php:32](app/Models/Site.php#L32)) et vidée à chaque création, modification ou suppression d'un site ([Site.php:21](app/Models/Site.php#L21)). |
| [Demande](app/Models/Demande.php) | Demande d'accès (section II du formulaire) : demandeur, site, type de projet et sa précision (`type_precision`), titre, résumé du synopsis (`description`), objectifs, statut, dates, équipe, date de soumission, résultats de l'analyse des périodes (JSON). Engagements du demandeur (`ENGAGEMENTS`, [Demande.php:23](app/Models/Demande.php#L23)) et coordonnées saisies dans le formulaire (`COORDONNEES`, [L31](app/Models/Demande.php#L31)). Référence officielle `reference` (`FAN-AAAA-NNNN`, [Demande.php:47](app/Models/Demande.php#L47)), envoyée avec chaque demande ; type affiché avec sa précision (`typeProjet()`, [L57](app/Models/Demande.php#L57)). Portées `horsBrouillon()` ([Demande.php:75](app/Models/Demande.php#L75)) et `forStatsViewer()` ([Demande.php:83](app/Models/Demande.php#L83)). |
| [TypeDemande](app/Models/TypeDemande.php) | Type de projet (ex. « Documentaire » ; `precision_requise` : le demandeur précise son projet, pour « Autre »). Relations `documentsRequis` et `reglesSpecifiques`, mises à jour ligne par ligne par `syncListe` ([TypeDemande.php:39](app/Models/TypeDemande.php#L39)), qui enregistre aussi les autres champs validés d'une ligne (ex. `reutilisable`). |
| [DocumentRequis](app/Models/DocumentRequis.php) | Pièce exigée pour un type (table `t_documents_requis`, libellé unique par type). `reutilisable` : pièce reprise d'office d'un dossier précédent du même demandeur. |
| [RegleSpecifique](app/Models/RegleSpecifique.php) | Règle d'un type, reprise dans le cahier des charges (table `t_regles_specifiques`). |
| [Statut](app/Models/Statut.php) | Référentiel des 6 statuts du workflow. |
| [Document](app/Models/Document.php) | Pièce jointe d'une demande (nom d'origine, chemin privé). Reliée à la pièce requise qu'elle fournit (`document_requis_id`, vide si la pièce a été retirée du type) ; `type_document` garde le libellé du jour du dépôt. |
| [PeriodeSensible](app/Models/PeriodeSensible.php) | Période de restriction sur un site, de niveau `bloquant` ou `avertissement`. |
| [HistoriqueStatut](app/Models/HistoriqueStatut.php) | Journal d'audit de chaque changement de statut. L'auteur (`user_id`) devient vide si son compte est supprimé. |
| [Notification](app/Models/Notification.php) | Notification interne liée à une demande (indicateur `lu`). |
| [ModeleReference](app/Models/ModeleReference.php) | Modèle de document officiel de la page Références (table `t_modeles_reference`) : titre unique, description, usage, icône, indication « produit par la plateforme », contenu. Les champs à compléter sont lus dans le contenu. |
| [DocumentReference](app/Models/DocumentReference.php) | Document déposé sur la page Références (table `t_documents_reference`) : titre, description, nom d'origine, chemin privé, type MIME, taille, auteur (`user_id`, vide si son compte est supprimé : le document reste). |
| [DocumentOfficiel](app/Models/DocumentOfficiel.php) | Textes modifiables d'un document officiel généré (table `t_documents_officiels`, type `autorisation` ou `cahier_charges`, un seul par type et par demande), avec l'auteur de la génération (`genere_par`, vide si son compte est supprimé). Relation `documentsOfficiels` sur `Demande`. |

#### `app/Http/Controllers/`

| Contrôleur | Rôle |
|---|---|
| [AccueilController](app/Http/Controllers/AccueilController.php) | Page d'accueil publique, avec la liste des sites en cache ([L14](app/Http/Controllers/AccueilController.php#L14)). |
| [DashboardController](app/Http/Controllers/DashboardController.php) | Choisit le tableau de bord selon le rôle ([DashboardController.php:19](app/Http/Controllers/DashboardController.php#L19)). Les calculs sont dans `StatsDemandeur`, `StatsValidateur` et `StatsAdmin` (voir `app/Support/`). |
| [DemandeController](app/Http/Controllers/DemandeController.php) | Espace demandeur : liste ([L27](app/Http/Controllers/DemandeController.php#L27)), création ([L86](app/Http/Controllers/DemandeController.php#L86)), brouillon, modification et complément ([L146](app/Http/Controllers/DemandeController.php#L146)), suppression ([L211](app/Http/Controllers/DemandeController.php#L211)) ; champs du projet ([L224](app/Http/Controllers/DemandeController.php#L224)) et coordonnées enregistrées sur le compte ([L243](app/Http/Controllers/DemandeController.php#L243)). Droits dans `DemandePolicy`, validation dans `DemandeRequest`, notifications dans `DemandeNotifications`. |
| [DocumentController](app/Http/Controllers/DocumentController.php) | Téléchargement sécurisé des pièces jointes ([L11](app/Http/Controllers/DocumentController.php#L11)). |
| [PdfController](app/Http/Controllers/PdfController.php) | PDF officiels d'une demande validée : autorisation ([L18](app/Http/Controllers/PdfController.php#L18)) et cahier des charges ([L23](app/Http/Controllers/PdfController.php#L23)), rendus par `DocumentsOfficiels` ; formulaire de demande rempli dès l'envoi ([L31](app/Http/Controllers/PdfController.php#L31)), rendu par `FormulaireDemande`. |
| [ReferenceController](app/Http/Controllers/ReferenceController.php) | Page Références ([L17](app/Http/Controllers/ReferenceController.php#L17)) : modèles avec leurs champs à compléter, documents déposés avec le droit de suppression de chacun, et `peutGererModeles` (admin). |
| [ModeleReferenceController](app/Http/Controllers/ModeleReferenceController.php) | Modèles de la page Références : PDF d'un modèle pour les validateurs et admins ([L18](app/Http/Controllers/ModeleReferenceController.php#L18)) ; pour les admins, création ([L24](app/Http/Controllers/ModeleReferenceController.php#L24)-[L29](app/Http/Controllers/ModeleReferenceController.php#L29)), modification ([L36](app/Http/Controllers/ModeleReferenceController.php#L36)-[L41](app/Http/Controllers/ModeleReferenceController.php#L41)), suppression ([L48](app/Http/Controllers/ModeleReferenceController.php#L48)) et aperçu PDF d'un texte non enregistré ([L58](app/Http/Controllers/ModeleReferenceController.php#L58)). |
| [DocumentReferenceController](app/Http/Controllers/DocumentReferenceController.php) | Documents de l'équipe sur la page Références : dépôt (formats et 20 Mo comme les pièces jointes, fichier effacé si l'enregistrement échoue, [L17](app/Http/Controllers/DocumentReferenceController.php#L17)), affichage dans le navigateur ou téléchargement avec `?telecharger=1` ([L59](app/Http/Controllers/DocumentReferenceController.php#L59)), suppression ([L71](app/Http/Controllers/DocumentReferenceController.php#L71)). |
| [NotificationController](app/Http/Controllers/NotificationController.php) | Marque une notification comme lue et redirige vers le bon dossier ([L10](app/Http/Controllers/NotificationController.php#L10)) ; marque toutes les notifications de l'utilisateur comme lues ([L35](app/Http/Controllers/NotificationController.php#L35)). |
| [ProfileController](app/Http/Controllers/ProfileController.php) | Profil utilisateur (Breeze) ; suppression du compte avec ses fichiers, refusée au dernier admin ([L48](app/Http/Controllers/ProfileController.php#L48)). |
| [Admin/SiteController](app/Http/Controllers/Admin/SiteController.php) | CRUD des sites, avec recherche. |
| [Admin/PeriodeSensibleController](app/Http/Controllers/Admin/PeriodeSensibleController.php) | CRUD des périodes sensibles, avec filtres. |
| [Admin/TypeDemandeController](app/Http/Controllers/Admin/TypeDemandeController.php) | CRUD des types de projet, avec la case « précision demandée » ([L83](app/Http/Controllers/Admin/TypeDemandeController.php#L83)). |
| [Admin/UserController](app/Http/Controllers/Admin/UserController.php) | Rôles et affectation des sites aux validateurs (en une transaction, [L56](app/Http/Controllers/Admin/UserController.php#L56)). |
| [Validateur/DemandeController](app/Http/Controllers/Validateur/DemandeController.php) | Liste filtrée ([L29](app/Http/Controllers/Validateur/DemandeController.php#L29)), dossier complet avec les coordonnées du demandeur ([L138](app/Http/Controllers/Validateur/DemandeController.php#L138)), décision sous verrou ([L169](app/Http/Controllers/Validateur/DemandeController.php#L169)), export CSV ([L51](app/Http/Controllers/Validateur/DemandeController.php#L51)). |
| [Auth/*](app/Http/Controllers/Auth/) | Authentification Laravel Breeze (9 contrôleurs). |

#### `app/Policies/` — droits d'accès

| Policy | Rôle |
|---|---|
| [DocumentReferencePolicy](app/Policies/DocumentReferencePolicy.php) | Suppression d'un document de la page Références : son auteur ou un admin ([L15](app/Policies/DocumentReferencePolicy.php#L15)). |
| [DemandePolicy](app/Policies/DemandePolicy.php) | Qui peut faire quoi sur une demande, rattachée automatiquement au modèle `Demande` : consulter sa demande (`view`, [L19](app/Policies/DemandePolicy.php#L19)), la modifier (`update`, brouillon ou à compléter, [L29](app/Policies/DemandePolicy.php#L29)), la supprimer (`delete`, brouillon, [L43](app/Policies/DemandePolicy.php#L43)), ouvrir le dossier côté validation (`instruire` : admin ou validateur du site, brouillon introuvable, [L58](app/Policies/DemandePolicy.php#L58)), décider (`decider`, jamais sur sa propre demande, [L76](app/Policies/DemandePolicy.php#L76)), télécharger les pièces jointes (`consulterFichiers`, [L92](app/Policies/DemandePolicy.php#L92)) les PDF officiels (`telechargerPdf`, demande validée, [L102](app/Policies/DemandePolicy.php#L102)) et le formulaire de demande en PDF (`telechargerFormulaire`, demande envoyée, [L119](app/Policies/DemandePolicy.php#L119)). |

#### `app/Http/Requests/` — validation des formulaires

| Requête | Rôle |
|---|---|
| [DemandeRequest](app/Http/Requests/DemandeRequest.php) | Création et modification d'une demande : droits vérifiés avant la validation ([L30](app/Http/Requests/DemandeRequest.php#L30)), règles selon brouillon ou envoi, rangées par section du formulaire (I. demandeur, II. projet, V. engagement, [L35](app/Http/Requests/DemandeRequest.php#L35)), pièces exigées par le type choisi, messages en français ([L77](app/Http/Requests/DemandeRequest.php#L77)), `estSoumission()` ([L111](app/Http/Requests/DemandeRequest.php#L111)), `typeDemande()` ([L119](app/Http/Requests/DemandeRequest.php#L119)), `precisionRequise()` ([L127](app/Http/Requests/DemandeRequest.php#L127)) et `piecesReprises()` ([L138](app/Http/Requests/DemandeRequest.php#L138), pièces réutilisables à reprendre, limitées aux fichiers du demandeur) réutilisés par le contrôleur. |
| [DecisionRequest](app/Http/Requests/DecisionRequest.php) | Décision d'un validateur ou d'un admin : `DemandePolicy::decider` ([L21](app/Http/Requests/DecisionRequest.php#L21)), nouveau statut et commentaire obligatoire ([L26](app/Http/Requests/DecisionRequest.php#L26)), signature par le nom du décideur pour une validation ([L38](app/Http/Requests/DecisionRequest.php#L38)). |
| [ModeleReferenceRequest](app/Http/Requests/ModeleReferenceRequest.php) | Création et modification d'un modèle de la page Références : admins uniquement, titre unique (le sien excepté), icône de la liste, contenu obligatoire (20 000 caractères au plus), messages en français. |
| [DocumentsOfficielsRequest](app/Http/Requests/DocumentsOfficielsRequest.php) | Modification des textes de l'autorisation et du cahier des charges : mêmes droits qu'une décision, champs obligatoires sauf conditions particulières et règles. |
| [ProfileUpdateRequest](app/Http/Requests/ProfileUpdateRequest.php) | Profil : nom, e-mail unique et coordonnées facultatives (organisation, fonction, adresse, téléphone, CIN ou passeport). |
| [Auth/LoginRequest](app/Http/Requests/Auth/LoginRequest.php) | Validation de la connexion avec limitation des tentatives. |


#### `app/Support/` — règles métier réutilisables

| Classe | Rôle |
|---|---|
| [DemandeStatutWorkflow](app/Support/DemandeStatutWorkflow.php) | Transitions de statut autorisées ([L48](app/Support/DemandeStatutWorkflow.php#L48)), statuts finaux ([L23](app/Support/DemandeStatutWorkflow.php#L23)), statuts modifiables par le demandeur ([L35](app/Support/DemandeStatutWorkflow.php#L35)). |
| [DemandeDecisionRules](app/Support/DemandeDecisionRules.php) | Décideurs à prévenir lors d'une nouvelle demande ([L21](app/Support/DemandeDecisionRules.php#L21)) ; validateurs du site et admins informés d'un changement de statut, hors auteur de la décision et demandeur ([L53](app/Support/DemandeDecisionRules.php#L53)). Qui peut consulter ou décider : voir `DemandePolicy`. |
| [DemandeNotifications](app/Support/DemandeNotifications.php) | Envoi des notifications, dans la cloche et par email : soumission ([L21](app/Support/DemandeNotifications.php#L21)), complément renvoyé ([L37](app/Support/DemandeNotifications.php#L37)), décision au demandeur et à l'équipe ([L56](app/Support/DemandeNotifications.php#L56)), envoi à chaque destinataire ([L76](app/Support/DemandeNotifications.php#L76)). |
| [StatsInstruction](app/Support/StatsInstruction.php) | Base des tableaux de bord validateur et admin : seuils de retard et d'arrivée imminente ([L21-24](app/Support/StatsInstruction.php#L21-L24)), dossiers en attente triés par urgence ([L43](app/Support/StatsInstruction.php#L43)), statistiques principales reçues / en attente / validées ([L83](app/Support/StatsInstruction.php#L83)), compteurs ([L107](app/Support/StatsInstruction.php#L107)), carte ([L122](app/Support/StatsInstruction.php#L122)). |
| [StatsValidateur](app/Support/StatsValidateur.php) | Tableau de bord du validateur ([L15](app/Support/StatsValidateur.php#L15)) : file de traitement, attente du demandeur ([L32](app/Support/StatsValidateur.php#L32)), activité du mois ([L58](app/Support/StatsValidateur.php#L58)). |
| [StatsAdmin](app/Support/StatsAdmin.php) | Tableau de bord de l'admin ([L19](app/Support/StatsAdmin.php#L19)) : qualité de service, terrain ([L65](app/Support/StatsAdmin.php#L65)), charge des validateurs ([L93](app/Support/StatsAdmin.php#L93)), fréquentation ([L115](app/Support/StatsAdmin.php#L115)), demandes par mois ([L137](app/Support/StatsAdmin.php#L137)). |
| [StatsDemandeur](app/Support/StatsDemandeur.php) | Tableau de bord du demandeur ([L20](app/Support/StatsDemandeur.php#L20)) : dossiers à compléter, en cours (pièces manquantes, délai habituel) et séjours à venir. |
| [DemandeDossier](app/Support/DemandeDossier.php) | Analyse des chevauchements avec les périodes sensibles ([L31](app/Support/DemandeDossier.php#L31)) ; pièces réutilisables déjà déposées par le demandeur (`piecesReutilisables`, [L58](app/Support/DemandeDossier.php#L58)) ; enregistrement, remplacement, reprise (copie, [L154](app/Support/DemandeDossier.php#L154)) et suppression des pièces jointes ([L92](app/Support/DemandeDossier.php#L92)) ; suppression d'un brouillon (`deleteDemande`, [L131](app/Support/DemandeDossier.php#L131)) ou d'un compte (`deleteUser`, [L141](app/Support/DemandeDossier.php#L141)) avec leurs fichiers. |
| [DelaiDecision](app/Support/DelaiDecision.php) | Délais de décision (soumission → validation ou rejet) : liste des décisions avec leur délai ([L25](app/Support/DelaiDecision.php#L25)), délai habituel d'un site — médiane du site s'il compte au moins 3 décisions, sinon celle de la plateforme ([L47](app/Support/DelaiDecision.php#L47)). Utilisé par les tableaux de bord et par la timeline (date de décision estimée). |
| [DocumentsOfficiels](app/Support/DocumentsOfficiels.php) | Autorisation et cahier des charges : champs modifiables ([L20](app/Support/DocumentsOfficiels.php#L20)), textes fixes (`TEXTES`, [L48](app/Support/DocumentsOfficiels.php#L48)), textes par défaut ([L60](app/Support/DocumentsOfficiels.php#L60)), engagement tiré des engagements du formulaire ([L83](app/Support/DocumentsOfficiels.php#L83)), texte à imprimer ([L97](app/Support/DocumentsOfficiels.php#L97)), génération ([L108](app/Support/DocumentsOfficiels.php#L108)), données de relecture pour la page ([L126](app/Support/DocumentsOfficiels.php#L126)), signature ([L175](app/Support/DocumentsOfficiels.php#L175)), listes « une par ligne » ([L187](app/Support/DocumentsOfficiels.php#L187)), PDF et variables des vues ([L199](app/Support/DocumentsOfficiels.php#L199), [L208](app/Support/DocumentsOfficiels.php#L208)). |
| [FormulaireDemande](app/Support/FormulaireDemande.php) | Formulaire de demande rempli en PDF : variables de la vue (demande, toutes les cases des types de projet et des aires protégées, engagements, [L27](app/Support/FormulaireDemande.php#L27)) et dernière décision définitive pour la section VI (validation = avis favorable, rejet = avis défavorable, [L43](app/Support/FormulaireDemande.php#L43)). |
| [ModelesReference](app/Support/ModelesReference.php) | Modèles de la page Références : icônes proposées ([L21](app/Support/ModelesReference.php#L21)), champs à compléter lus entre crochets ([L34](app/Support/ModelesReference.php#L34)), mise en forme du contenu en HTML échappé ([L44](app/Support/ModelesReference.php#L44)), rendu PDF ([L104](app/Support/ModelesReference.php#L104)). Les conventions d'écriture sont rappelées en tête du fichier. |
| [DemandeNotificationMessage](app/Support/DemandeNotificationMessage.php) | Textes des notifications en français pour chaque événement : décision au demandeur ([L14](app/Support/DemandeNotificationMessage.php#L14)), décision à l'équipe du site ([L55](app/Support/DemandeNotificationMessage.php#L55)), soumission ([L77](app/Support/DemandeNotificationMessage.php#L77)), complément renvoyé ([L88](app/Support/DemandeNotificationMessage.php#L88)), nouvelle demande, avec le type de projet ([L99](app/Support/DemandeNotificationMessage.php#L99)). |

#### `app/Mail/`

- [DemandeNotificationMail.php](app/Mail/DemandeNotificationMail.php) : copie par email d'une notification de la cloche (objet « Dossier #… – site », bouton vers le dossier). Mise en file d'attente (`ShouldQueue`) et envoyée seulement après la validation de la transaction (`afterCommit`), pour ne jamais annoncer une décision annulée.

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

**[migrations/](database/migrations/)** — schéma complet, réécrit le 19/10/2026 : chaque migration crée directement l'état final de sa table (clés, `RESTRICT`, `CHECK`, index, données de départ). **Toutes les tables portent le préfixe `t_`**, y compris les tables techniques de Laravel, déclarées dans `config/` (`auth.php`, `cache.php`, `database.php` pour `t_migrations`, `queue.php`, `session.php`). Les noms d'index et de contraintes suivent : `t_demandes_user_id_foreign`, `chk_t_demandes_dates`. L'historique des corrections successives (1NF, contraintes, historisation des soumissions) est retracé dans [Plan_conformite_BDD.md](../2_Conception/Plan_conformite_BDD.md).

1. `t_users` (avec les coordonnées du formulaire de demande, facultatives), `t_password_reset_tokens`, `t_sessions` — [0001_01_01_000000_create_users_table.php](database/migrations/0001_01_01_000000_create_users_table.php)
2. `t_cache`, `t_cache_locks` — [0001_01_01_000001_create_cache_table.php](database/migrations/0001_01_01_000001_create_cache_table.php)
3. `t_jobs`, `t_job_batches`, `t_failed_jobs` — [0001_01_01_000002_create_jobs_table.php](database/migrations/0001_01_01_000002_create_jobs_table.php)
4. `t_sites` — [2026_10_19_200001_create_t_sites_table.php](database/migrations/2026_10_19_200001_create_t_sites_table.php)
5. `t_statuts`, avec les 6 statuts du workflow — [2026_10_19_200002_create_t_statuts_table.php](database/migrations/2026_10_19_200002_create_t_statuts_table.php)
6. `t_type_demandes` (types de projet, case `precision_requise`), `t_documents_requis` (libellé unique par type, case `reutilisable`), `t_regles_specifiques` (1NF : une ligne par pièce ou par règle) — [2026_10_19_200003_create_t_type_demandes_table.php](database/migrations/2026_10_19_200003_create_t_type_demandes_table.php)
7. `t_demandes` : titre, précision du type, résumé du synopsis (`description`) et objectifs ; champs du projet et dates facultatifs (brouillon), `RESTRICT` vers site, type et statut, CHECK `date_fin >= date_debut` et `equipe_nombre >= 1` — [2026_10_19_200004_create_t_demandes_table.php](database/migrations/2026_10_19_200004_create_t_demandes_table.php)
8. `t_documents` : `document_requis_id` mis à vide si la pièce est retirée du type — [2026_10_19_200005_create_t_documents_table.php](database/migrations/2026_10_19_200005_create_t_documents_table.php)
9. `t_historique_statuts` : auteur mis à vide à la suppression de son compte (`nullOnDelete`) — [2026_10_19_200006_create_t_historique_statuts_table.php](database/migrations/2026_10_19_200006_create_t_historique_statuts_table.php)
10. `t_periode_sensibles`, CHECK sur les dates — [2026_10_19_200007_create_t_periode_sensibles_table.php](database/migrations/2026_10_19_200007_create_t_periode_sensibles_table.php)
11. `t_notifications`, index `(user_id, lu, date_envoi)` de la cloche — [2026_10_19_200008_create_t_notifications_table.php](database/migrations/2026_10_19_200008_create_t_notifications_table.php)
12. `t_site_user` (pivot validateurs ↔ sites) — [2026_10_19_200009_create_t_site_user_table.php](database/migrations/2026_10_19_200009_create_t_site_user_table.php)
13. `t_documents_officiels` : textes relus de l'autorisation et du cahier des charges (JSON), unique par demande et par type, CHECK sur le type — [2026_10_19_200010_create_t_documents_officiels_table.php](database/migrations/2026_10_19_200010_create_t_documents_officiels_table.php)
14. `t_documents_reference` : documents déposés sur la page Références, auteur mis à vide à la suppression de son compte — [2026_10_19_200011_create_t_documents_reference_table.php](database/migrations/2026_10_19_200011_create_t_documents_reference_table.php)
15. `t_modeles_reference` : modèles des documents officiels de la page Références (titre unique), remplie avec les cinq modèles d'origine (autorisation et cahier des charges alignés sur le formulaire de demande) — [2026_10_19_200012_create_t_modeles_reference_table.php](database/migrations/2026_10_19_200012_create_t_modeles_reference_table.php)

> **Recréer la base sur un autre poste.** `php artisan migrate:fresh` ne vide la base que s'il y trouve sa table de suivi (`t_migrations`) : sur une base qui a encore les anciennes tables sans préfixe, lancer d'abord `php artisan db:wipe`, puis `php artisan migrate`.

**[seeders/](database/seeders/)**

- [DatabaseSeeder](database/seeders/DatabaseSeeder.php) : 6 statuts, les 12 types de projet du formulaire de demande (*Autre* demande une précision) avec leurs pièces et règles, 2 sites de démonstration et 3 comptes de test (le validateur a une fonction, reprise dans la section VI) :

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
- 7 comptes (mot de passe `password`), avec leurs coordonnées (organisation, fonction, adresse, téléphone, CIN ou passeport) : `admin@fanamby.org`, `validateur@fanamby.org` (Andrafiamena, Anjozorobe, Loky), `hery@fanamby.org` (Anjozorobe, Menabe), et les demandeurs `alice@`, `rado@`, `sarah@`, `tiana@exemple.com`. L'Allée des Baobabs n'a volontairement aucun validateur.
- Les 5 aires protégées avec leurs contours réels (simplifiés à ~100 m), les 12 types de projet du formulaire (42 pièces requises dont la copie de la CIN ou du passeport, réutilisable, et 22 règles) et 8 périodes sensibles (passées, en cours, à venir, bloquantes ou non).
- 22 demandes de projets photo et vidéo (titre, type, synopsis, objectifs ; un projet « Autre » précisé) couvrant tous les statuts et cas : brouillons incomplets, arrivées imminentes ou dépassées, retards, conflits de calendrier, compléments en attente (dont une pièce manquante), séjours en cours, demandes déposées par un admin et par un validateur (auto-validation interdite), décisions de l'année précédente.
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
- **composants** : boutons compacts 8×14px (icône 16px ; 14px dans les petits boutons `.btn-sm-edit`, `.btn-sm-delete` et `.btn-danger`, dont la taille est fixée pour qu'une icône ne s'étire jamais), champs 8×12px, anneau de focus de 2px, onglets en contrôle segmenté où l'onglet choisi est surélevé ;
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
| [Dashboard/Partials/](resources/js/Pages/Dashboard/Partials/) | [EspaceDemandeur](resources/js/Pages/Dashboard/Partials/EspaceDemandeur.vue), [EspaceValidateur](resources/js/Pages/Dashboard/Partials/EspaceValidateur.vue), [EspaceAdmin](resources/js/Pages/Dashboard/Partials/EspaceAdmin.vue) (un espace par rôle, voir 3.2 et 3.3), [StatsPrincipales](resources/js/Pages/Dashboard/Partials/StatsPrincipales.vue) (demandes reçues, en attente et validées, avec barre de répartition, en tête des espaces admin et validateur), [CarteSites](resources/js/Pages/Dashboard/Partials/CarteSites.vue) (carte, curseur de date et légende) et [LigneDossier](resources/js/Pages/Dashboard/Partials/LigneDossier.vue) (ligne d'un dossier en attente avec ses alertes). |
| [Auth/](resources/js/Pages/Auth/) | Connexion, inscription, mot de passe oublié, réinitialisation, confirmation du mot de passe, vérification de l'e-mail. |
| [Profile/](resources/js/Pages/Profile/) | [Edit.vue](resources/js/Pages/Profile/Edit.vue) et ses 3 sous-formulaires dans `Partials/` : [informations](resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue) (nom, e-mail et coordonnées du formulaire de demande), [mot de passe](resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue), [suppression du compte](resources/js/Pages/Profile/Partials/DeleteUserForm.vue). |
| [Demandes/](resources/js/Pages/Demandes/) | [Index](resources/js/Pages/Demandes/Index.vue) (mes demandes), [Create](resources/js/Pages/Demandes/Create.vue) (formulaire en étapes : création et modification, brouillon, complément ; la page garde les données, la vérification et l'envoi), [Partials/](resources/js/Pages/Demandes/Partials/) (une étape par composant, dans l'ordre du formulaire papier : [EtapeDemandeur](resources/js/Pages/Demandes/Partials/EtapeDemandeur.vue), [EtapeProjet](resources/js/Pages/Demandes/Partials/EtapeProjet.vue), [EtapeAirePeriode](resources/js/Pages/Demandes/Partials/EtapeAirePeriode.vue), [EtapePieces](resources/js/Pages/Demandes/Partials/EtapePieces.vue), [EtapeRecap](resources/js/Pages/Demandes/Partials/EtapeRecap.vue) (récapitulatif et engagement), et la colonne [VerificationCalendrier](resources/js/Pages/Demandes/Partials/VerificationCalendrier.vue) : carte, fiche du site et périodes sensibles), [Show](resources/js/Pages/Demandes/Show.vue) (détail, timeline et formulaire de demande en PDF ; les boutons de suppression du brouillon ont le même gabarit que « Modifier le brouillon » et « Annuler »). |
| [Validateur/Demandes/](resources/js/Pages/Validateur/Demandes/) | [Index](resources/js/Pages/Validateur/Demandes/Index.vue) (liste filtrée et export), [Show](resources/js/Pages/Validateur/Demandes/Show.vue) (dossier avec les coordonnées du demandeur, timeline et décision : avis favorable en deux temps — générer, puis signer — ou avis défavorable), [Partials/DocumentsGeneres](resources/js/Pages/Validateur/Demandes/Partials/DocumentsGeneres.vue) (relecture et modification de l'autorisation et du cahier des charges, aperçu PDF). |
| [References/](resources/js/Pages/References/) | [Index](resources/js/Pages/References/Index.vue) (modèles en cartes avec ouverture du PDF et, pour un admin, création, modification, suppression ; dépôt et liste des documents de l'équipe), [ModeleForm](resources/js/Pages/References/ModeleForm.vue) (création et modification d'un modèle : présentation, contenu, aide-mémoire de mise en forme, champs détectés, aperçu PDF), [Partials/ApercuDocument](resources/js/Pages/References/Partials/ApercuDocument.vue) (aperçu d'un document déposé : PDF ou image dans la page, téléchargement sinon). |
| [Admin/Sites/](resources/js/Pages/Admin/Sites/) | `Index`, `Create`, `Edit` (avec dessin du polygone). |
| [Admin/PeriodeSensibles/](resources/js/Pages/Admin/PeriodeSensibles/) | `Index`, `Create`, `Edit`. |
| [Admin/TypeDemandes/](resources/js/Pages/Admin/TypeDemandes/) | `Index`, `Create`, `Edit` (pièces, règles et case « précision demandée »). |
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
- [openPdf.js](resources/js/utils/openPdf.js#L5) : récupère un PDF avec la session de l'utilisateur et l'ouvre dans un nouvel onglet ; `openPostedPdf` ([L29](resources/js/utils/openPdf.js#L29)) fait de même pour un PDF produit à partir de données envoyées (aperçu d'un modèle).
- [carte.js](resources/js/utils/carte.js) : outils des cartes : contrôle « Plan / Satellite / Relief » ([L35](resources/js/utils/carte.js#L35)), source des altitudes pour la 3D ([L83](resources/js/utils/carte.js#L83)), calcul de la surface d'un contour en km² ([L93](resources/js/utils/carte.js#L93)), motif de hachures des périodes sensibles ([L112](resources/js/utils/carte.js#L112)).

#### `resources/views/`

- [app.blade.php](resources/views/app.blade.php) : page HTML racine dans laquelle Inertia monte l'application (polices, routes Ziggy, fichiers Vite).
- [mail/demande-notification.blade.php](resources/views/mail/demande-notification.blade.php) : email de notification (Markdown Laravel) : salutation, message de la cloche, bouton *Ouvrir le dossier*.
- [pdf/autorisation.blade.php](resources/views/pdf/autorisation.blade.php) : modèle du PDF d'autorisation officielle.
- [pdf/cahier_charges.blade.php](resources/views/pdf/cahier_charges.blade.php) : modèle du PDF du cahier des charges.
- [pdf/formulaire_demande.blade.php](resources/views/pdf/formulaire_demande.blade.php) : formulaire de demande rempli, mis en page comme le formulaire papier de Fanamby (page 1 : sections I et II ; page 2 : sections V et VI), cases dessinées en CSS.
- [pdf/modele_reference.blade.php](resources/views/pdf/modele_reference.blade.php) : PDF d'un modèle de la page Références (en-tête FANAMBY, bandeau « Modèle », style des champs à compléter), avec le contenu mis en forme par `ModelesReference::html`.

### `routes/`

| Fichier | Contenu |
|---|---|
| [web.php](routes/web.php) | Contrôleurs importés en tête du fichier. Accueil public ([L21](routes/web.php#L21)), tableau de bord ([L24](routes/web.php#L24)), espace connecté : profil, demandes (création et modification limitées par `throttle:demandes`, [L32](routes/web.php#L32)), notifications, documents, PDF dont le formulaire de demande ([L38](routes/web.php#L38)) ([L26-39](routes/web.php#L26-L39)), groupe `admin/*` avec `can:is-admin` ([L43](routes/web.php#L43)), groupe `validateur/*` avec `can:is-validateur` ([L50](routes/web.php#L50)), dont la génération, la modification, l'aperçu et l'annulation des documents officiels ([L55-60](routes/web.php#L55-L60)), page Références avec `can:is-validateur` ([L65-71](routes/web.php#L65-L71)) : page, PDF des modèles, dépôt (`throttle:demandes`), consultation et suppression des documents de l'équipe ; gestion des modèles avec `can:is-admin` ([L74-81](routes/web.php#L74-L81)). |
| [auth.php](routes/auth.php) | Routes d'authentification Breeze. |
| [console.php](routes/console.php) | Commandes Artisan personnalisées (aucune pour l'instant). |

### `storage/`

| Dossier | Contenu |
|---|---|
| `app/private/demandes/{id}/` | Pièces jointes des demandes. Jamais accessibles par URL directe. |
| `app/private/references/` | Documents déposés sur la page Références. Jamais accessibles par URL directe. |
| `app/public/` | Fichiers publics (non utilisé). |
| `framework/` | Cache, sessions et vues Blade compilées. |
| `logs/` | Journal `laravel.log`. |

---

## 6. Tests automatisés

144 tests PHPUnit, exécutés avec `php artisan test` sur la base MySQL `projet_stage_test` (à créer une fois, voir section 7). Chaque test démarre sur des tables vides : la base de l'application `projet_stage` n'est jamais touchée.

| Fichier | Ce qu'il vérifie |
|---|---|
| [Feature/DemandeParcoursTest](tests/Feature/DemandeParcoursTest.php) | Brouillon minimal, brouillon invisible au validateur, reprise d'un brouillon avec ses pièces, suppression réservée aux brouillons, cycle complet de complément, emails d'un changement de statut (demandeur, autres validateurs du site et admins, mais pas l'auteur de la décision ni un validateur d'un autre site), détection des chevauchements (avertissement accepté, blocage refusé), périodes envoyées au formulaire, tableau de bord demandeur, filtres et pagination validateur, export CSV, recherche des sites, référence calculée par le serveur (page et tableau de bord), limite de 20 envois par minute. |
| [Feature/FormulaireDemandeTest](tests/Feature/FormulaireDemandeTest.php) | Formulaire de demande : sections I, II et V et engagement exigés à la soumission mais pas pour un brouillon, téléphone contrôlé, champs du projet enregistrés, coordonnées enregistrées sur le compte puis pré-remplies, précision obligatoire pour « Autre » et ignorée pour les autres types, case « précision demandée » de l'admin ; formulaire PDF disponible dès l'envoi pour le demandeur et le validateur du site, refusé pour un brouillon et à un tiers ; section VI (avis favorable ou défavorable, observations, fonction du décideur) ; objet de l'autorisation et engagement du cahier des charges repris du formulaire. |
| [Feature/DashboardTest](tests/Feature/DashboardTest.php) | Statistiques principales (reçues dont ce mois-ci, en attente dont chez le demandeur, validées, rejetées, taux de validation), sans brouillons et limitées aux sites du validateur ; file du validateur triée par urgence et limitée à ses sites, activité du mois, indicateurs de l'admin (délai médian, taux de validation sur les dossiers décidés, compléments), sites sans validateur, présence sur le terrain, fréquentation, pièces manquantes et délai habituel côté demandeur. |
| [Feature/DemandeSecurityTest](tests/Feature/DemandeSecurityTest.php) | Cloisonnement des pièces jointes et des PDF par site et par propriétaire, PDF réservés aux demandes validées, date passée et format de fichier refusés, détail réservé au propriétaire, suppression interdite d'un site ou d'un type utilisé. |
| [Feature/IntegriteComptesEtDecisionsTest](tests/Feature/IntegriteComptesEtDecisionsTest.php) | Suppression du compte d'un validateur avec conservation de ses décisions, effacement des fichiers d'un compte supprimé, dernier admin protégé, seconde décision refusée sur un dossier clôturé entre-temps, admin ne pouvant pas se retirer son rôle, changement de rôle d'un autre utilisateur, site inconnu refusé. |
| [Feature/RepriseDocumentsTest](tests/Feature/RepriseDocumentsTest.php) | Pièces réutilisables : seules les pièces cochées sont proposées, y compris d'un type à l'autre ; la pièce reprise est copiée dans le nouveau dossier (l'original reste en place) ; une pièce écartée redevient obligatoire ; impossible de reprendre le fichier d'un autre demandeur ; un nouveau fichier l'emporte sur la reprise ; l'admin coche « réutilisable ». |
| [Feature/ConceptionBddTest](tests/Feature/ConceptionBddTest.php) | Garanties de la conception : soumission historisée (directe ou depuis un brouillon), renommage d'une pièce requise sans perte des fichiers, retrait d'une pièce sans effacer les fichiers soumis, pièce en double refusée, règles reprises dans le cahier des charges, CHECK (dates, effectif) et RESTRICT appliqués par la base. |
| [Feature/SuiviDemandeTest](tests/Feature/SuiviDemandeTest.php) | Données de la timeline : délai habituel du site transmis pour un dossier en instruction (celui de la plateforme si le site compte moins de 3 décisions), aucune estimation pour un dossier décidé, historique transmis dans l'ordre chronologique aux pages demandeur et validateur. |
| [Feature/ValidationDocumentsTest](tests/Feature/ValidationDocumentsTest.php) | Validation en deux temps : génération sans changement de statut, validation refusée sans documents ou sans le bon nom (casse, accents et espaces ignorés), textes modifiés repris dans les PDF avec la mention de signature, documents figés après validation, champs obligatoires, aperçu réservé aux décideurs, génération refusée à un validateur d'une autre aire protégée, documents supprimés par une demande de complément. |
| [Feature/ReferencesTest](tests/Feature/ReferencesTest.php) | Page Références : refusée au demandeur et à l'invité ; cinq modèles d'origine repris avec leurs champs, chacun rendu en PDF, modèle inconnu introuvable ; création, modification et suppression d'un modèle par un admin, titre unique, icône connue et contenu exigés, gestion et aperçu refusés au validateur, aperçu PDF avant enregistrement, HTML saisi échappé et mise en forme (titres, listes, bloc à droite, lignes à remplir) ; dépôt d'un document visible par toute l'équipe, titre et format exigés, dépôt et consultation refusés au demandeur, affichage dans le navigateur ou téléchargement, suppression réservée à l'auteur et aux admins (fichier effacé). |
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

# Développement (serveur PHP, Vite et file d'attente des emails en parallèle)
composer dev
# Emails : MAIL_MAILER=log les écrit dans storage/logs/laravel.log ;
# pour un vrai envoi, renseigner MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME,
# MAIL_PASSWORD et MAIL_FROM_ADDRESS dans .env. Hors `composer dev` : php artisan queue:work

# Tests automatisés (base projet_stage_test)
php artisan test

# Production : compiler le front-end
npm run build
```
