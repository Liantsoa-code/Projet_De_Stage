# Plan de Réalisation du Projet

Ce document détaille les étapes chronologiques pour le développement complet de la plateforme de gestion des demandes d'accès aux aires protégées. La stack technique retenue est **Laravel (PHP), Vue.js (Inertia.js) et Tailwind CSS**.

## Phase 1 : Initialisation et Configuration ⚙️
1. **Initialisation du projet** : Cloner ou générer le projet de base (`3_Application`).
2. **Configuration de la base de données** : Configurer le fichier `.env` (SQLite pour le développement, ou MySQL/PostgreSQL).
3. **Mise en place de l'environnement frontend** : S'assurer que Vite, Vue 3, Inertia et Tailwind CSS compilent correctement (`npm run dev`).

## Phase 2 : Structure de Données (Backend) 🗄️
*Traduire le script `base_de_donnees.sql` en structure Laravel.*
1. **Migrations Laravel** : Créer les fichiers de migration pour chaque table (utilisateurs, sites, demandes, statuts, documents, historique, périodes sensibles, notifications).
2. **Modèles Eloquent** : Créer les modèles correspondants et y définir les relations (HasMany, BelongsTo, BelongsToMany pour la table pivot `t_site_validateur`).
3. **Seeders et Factories** : Créer de fausses données (dummy data) pour tester l'application facilement (créer un Admin par défaut, des statuts de base, quelques sites).

## Phase 3 : Authentification et Sécurité 🔐
1. **Système de connexion** : Implémenter l'authentification (si non présente, via Laravel Breeze avec l'option Vue/Inertia).
2. **Gestion des Rôles** : Mettre en place un système de rôles (Demandeur, Validateur, Admin).
3. **Middlewares & Policies** : Protéger les routes. Par exemple, s'assurer qu'un Demandeur ne voit que *ses* demandes, et qu'un Validateur ne gère que *ses* sites assignés.

## Phase 4 : Développement du Panel d'Administration (Back-Office) 🛠️
1. **CRUD Sites** : Interfaces pour créer, lister, modifier et supprimer les aires protégées (incluant la saisie des `polygone_coordonnees`).
2. **CRUD Périodes Sensibles** : Assigner des périodes de restriction sur les sites géographiques.
3. **CRUD Types de Demandes & Statuts** : Interfaces pour définir les règles JSON et les documents requis pour chaque type d'activité.
4. **Gestion des Utilisateurs** : Permettre à l'Admin d'assigner des validateurs à des sites spécifiques.

## Phase 5 : Le Cœur de l'Application - Les Demandes 📝
1. **Espace Demandeur (Front-Office)** : 
    * Formulaire multi-étapes pour soumettre une demande.
    * Upload de fichiers pour répondre aux `documents_requis`.
2. **Moteur d'Analyse (Backend)** : 
    * À la soumission, vérifier si les dates de la demande chevauchent une `periode_sensible`.
    * Figer les `resultats_analyse` au format JSON dans la base de données.
3. **Tableaux de bord (Dashboards)** :
    * Dashboard Demandeur : Suivi de l'état de ses propres dossiers.
    * Dashboard Validateur : Liste des demandes en attente de validation sur ses sites.

## Phase 6 : Workflow de Validation et Notifications 🔄
1. **Système de Transition d'États** : Interface pour le validateur permettant de changer le statut (Accepter, Rejeter, Demander des modifications) avec un champ de commentaire.
2. **Historisation** : Insertion automatique d'un enregistrement dans `t_historique_statut` à chaque modification.
3. **Notifications** : Implémenter le système d'alerte (`t_notification`) pour avertir le demandeur de l'avancée de son dossier.

## Phase 7 : Interface Utilisateur (UI/UX) et Cartographie 🗺️
1. **Intégration Carte Interactive** : Utiliser une librairie comme Leaflet.js ou Mapbox (Vue) pour afficher les sites et les polygones.
2. **Design System** : Harmoniser l'interface avec Tailwind CSS pour un rendu professionnel et "Premium" (couleurs, ombres, design responsive).

## Phase 8 : Finalisation et Déploiement 🚀
1. **Tests** : Rédiger des tests automatisés (PHPUnit/Pest) pour s'assurer que les validations métier (bloquer une demande en période sensible) fonctionnent à 100%.
2. **Optimisation** : Vérification des requêtes SQL (éviter le N+1 problem sur les historiques de statuts).
3. **Mise en Production** : Déploiement sur le serveur final (configuration de l'environnement de production, cache, etc.).
