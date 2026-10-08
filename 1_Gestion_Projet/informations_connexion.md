# Guide de Démarrage Rapide

Ce document contient les commandes nécessaires pour lancer votre application en local ainsi que les identifiants de connexion des utilisateurs de test créés dans les seeders.

## 🚀 Lancer l'application

Puisque votre projet utilise Laravel en backend et Vue.js/Inertia en frontend, vous devez lancer **deux terminaux** simultanément.

Ouvrez un terminal à la racine de votre projet (`3_Application`) et lancez le serveur Laravel :
```bash
php artisan serve
```
*(L'application sera accessible sur `http://localhost:8000`)*

Ouvrez un **deuxième terminal** au même endroit et lancez le serveur de développement frontend (Vite) :
```bash
npm run dev
```

---

## 🔑 Utilisateurs de Test

Voici les comptes par défaut que vous pouvez utiliser pour vous connecter et tester les différents rôles de l'application. 
**Le mot de passe pour tous les comptes est :** `password`

### 1. Administrateur
Ce compte a accès à l'ensemble du système et peut gérer les types de demandes, les utilisateurs, etc.
* **Email** : `admin@fanamby.org`
* **Mot de passe** : `password`

### 2. Validateur (Assigné à la Réserve de Lokobe)
Ce compte est utilisé pour analyser, valider ou refuser les demandes qui concernent les sites dont il est responsable.
* **Email** : `validateur@fanamby.org`
* **Mot de passe** : `password`

### 3. Demandeur
Ce compte permet de soumettre de nouvelles demandes d'accès ou d'activité sur les différents sites.
* **Email** : `alice@exemple.com`
* **Mot de passe** : `password`

> **Note :** Si ces utilisateurs n'existent pas encore dans votre base de données, vous pouvez les générer en exécutant la commande suivante dans votre terminal :
> ```bash
> php artisan migrate:fresh --seed
> ```
