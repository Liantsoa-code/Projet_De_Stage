# Mettre la plateforme en ligne

Ce guide part d'un serveur vide. À la fin, la plateforme est en ligne avec :

- les **5 aires protégées gérées par Fanamby et leur contour réel**, visibles sur la carte de la page d'accueil ;
- les **12 types de projet** du formulaire de demande, avec leurs pièces exigées et leurs règles ;
- **un compte administrateur**, sans aucun compte ni dossier de démonstration ;
- de **vrais emails** : confirmation de l'adresse à l'inscription, mot de passe oublié, notifications des dossiers.

Les commandes sont à lancer dans le dossier `3_Application` du serveur, sauf indication contraire.
Comptez environ une heure la première fois.

---

## Sommaire

1. [Ce qu'il faut avant de commencer](#1-ce-quil-faut-avant-de-commencer)
2. [Préparer l'envoi des emails](#2-préparer-lenvoi-des-emails)
3. [Copier le code sur le serveur](#3-copier-le-code-sur-le-serveur)
4. [Créer la base de données](#4-créer-la-base-de-données)
5. [Écrire le fichier `.env`](#5-écrire-le-fichier-env)
6. [Installer et créer les tables](#6-installer-et-créer-les-tables)
7. [Charger les aires protégées et les types de projet](#7-charger-les-aires-protégées-et-les-types-de-projet)
8. [Créer l'administrateur](#8-créer-ladministrateur)
9. [Lancer l'envoi des notifications](#9-lancer-lenvoi-des-notifications)
10. [Brancher le domaine et le HTTPS](#10-brancher-le-domaine-et-le-https)
11. [Vérifier que tout fonctionne](#11-vérifier-que-tout-fonctionne)
12. [Premiers réglages dans la plateforme](#12-premiers-réglages-dans-la-plateforme)
13. [Mettre à jour la plateforme plus tard](#13-mettre-à-jour-la-plateforme-plus-tard)
14. [Sauvegardes](#14-sauvegardes)
15. [En cas de problème](#15-en-cas-de-problème)

---

## 1. Ce qu'il faut avant de commencer

**Un hébergement avec accès en ligne de commande (SSH).** Un petit serveur dédié (VPS) convient très bien.
Un hébergement mutualisé fonctionne aussi s'il propose un terminal (cPanel, Plesk…) et des tâches cron.

Sur ce serveur :

| Logiciel | Version | Remarque |
|---|---|---|
| PHP | 8.3 ou plus | extensions : `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `dom`, `xml`, `fileinfo`, `curl`, `intl`, `bcmath` |
| MySQL ou MariaDB | MySQL 8 / MariaDB 10.6 ou plus | |
| Composer | 2.x | installe les dépendances PHP |
| Node.js | 20 ou plus | seulement pour compiler l'interface (étape 6) ; peut se faire sur votre ordinateur |

Vérifier PHP et ses extensions :

```bash
php -v
php -m
```

**Réglages PHP pour les pièces jointes** (fichier `php.ini`, ou « Sélecteur PHP » de l'hébergeur) :
une pièce peut peser 20 Mo et une demande en compte plusieurs.

```ini
upload_max_filesize = 20M
post_max_size = 100M
memory_limit = 256M
```

Avec nginx, ajouter aussi `client_max_body_size 100M;` dans la configuration du site.

**Un nom de domaine**, par exemple `autorisations.fanamby.org`, qui pointe vers le serveur.

---

## 2. Préparer l'envoi des emails

La plateforme envoie ses emails par un serveur SMTP. Il faut un compte d'envoi et son mot de passe.
Choisissez **une** des solutions ci-dessous.

### Solution A : un compte Gmail ou Google Workspace (le plus simple)

1. Sur le compte Google qui enverra les emails, activer la **validation en deux étapes**.
2. Créer un **mot de passe d'application** : <https://myaccount.google.com/apppasswords>
   (nom : « Plateforme Fanamby »). Google affiche 16 lettres en 4 groupes : c'est `MAIL_PASSWORD`,
   à recopier **sans les espaces**. Ne mettez **jamais** le mot de passe normal du compte.
3. Réglages pour le `.env` (étape 5) :

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=autorisations.fanamby@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_FROM_ADDRESS=autorisations.fanamby@gmail.com
MAIL_FROM_NAME="Fanamby – Autorisations"
```

Limite : environ 500 destinataires par jour avec un compte Gmail gratuit, 2 000 avec Google Workspace.
Avec Gmail, `MAIL_FROM_ADDRESS` doit être l'adresse du compte, sinon Google la remplace.

### Solution B : un service d'envoi (Brevo, Mailjet…) avec une adresse `@fanamby.org`

1. Créer un compte (Brevo : 300 emails par jour gratuits) et déclarer le domaine `fanamby.org`.
2. Le service donne des enregistrements **SPF** et **DKIM** : la personne qui gère le domaine
   les ajoute dans sa zone DNS. **Sans eux, les emails arrivent en spam.**
3. Le service donne aussi l'hôte, le port, l'identifiant et la clé SMTP. Exemple avec Brevo :

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=identifiant-smtp-donne-par-brevo
MAIL_PASSWORD=cle-smtp-donnee-par-brevo
MAIL_FROM_ADDRESS=ne-pas-repondre@fanamby.org
MAIL_FROM_NAME="Fanamby – Autorisations"
```

> Port 465 au lieu de 587 : mettre `MAIL_SCHEME=smtps`.

---

## 3. Copier le code sur le serveur

**Avec git (recommandé)** :

```bash
cd /var/www
git clone <adresse-du-dépôt> fanamby
cd fanamby/3_Application
```

**Sans git** (envoi par FTP) : envoyer le dossier `3_Application` **sans** les éléments suivants :

- `.env` : celui du serveur s'écrit à l'étape 5 ;
- `vendor/` et `node_modules/` : réinstallés à l'étape 6 ;
- `public/hot` : **à supprimer absolument**. S'il est présent, le site cherche le serveur
  de développement et s'affiche vide ;
- `storage/logs/*`, `storage/app/private/*` : journaux et fichiers de votre poste ;
- `database/database.sqlite`, `ICONS/`.

**Le site doit pointer sur le dossier `3_Application/public`**, jamais sur `3_Application`.
Sinon le fichier `.env` et ses mots de passe seraient téléchargeables.

- Hébergement mutualisé : régler la « racine du document » du domaine sur `.../3_Application/public`.
- Apache : le fichier `public/.htaccess` est fourni.
- nginx : configuration standard de Laravel, avec `root /var/www/fanamby/3_Application/public;`
  (voir <https://laravel.com/docs/deployment#nginx>).

---

## 4. Créer la base de données

Créez une base et un utilisateur réservé à la plateforme : jamais `root`, jamais sans mot de passe.
Dans `mysql` (ou dans « Bases de données MySQL » du panneau de l'hébergeur) :

```sql
CREATE DATABASE fanamby CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fanamby'@'localhost' IDENTIFIED BY 'un-mot-de-passe-long-et-unique';
GRANT ALL PRIVILEGES ON fanamby.* TO 'fanamby'@'localhost';
FLUSH PRIVILEGES;
```

Notez le nom de la base, l'utilisateur et le mot de passe pour le `.env`.

---

## 5. Écrire le fichier `.env`

```bash
cp .env.example .env
nano .env
```

Remplacer le contenu par celui-ci, en adaptant les lignes marquées `← à adapter` :

```env
APP_NAME=Fanamby
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://autorisations.fanamby.org      # ← à adapter : adresse exacte du site, en https, sans / final

# Proxy qui gère le HTTPS devant l'application (voir étape 10) : * ou vide
TRUSTED_PROXIES=

APP_LOCALE=fr
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=fr_FR

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fanamby                             # ← à adapter (étape 4)
DB_USERNAME=fanamby                             # ← à adapter (étape 4)
DB_PASSWORD="un-mot-de-passe-long-et-unique"    # ← à adapter (étape 4)

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

# ← à adapter : les réglages choisis à l'étape 2
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=autorisations.fanamby@gmail.com
MAIL_PASSWORD=abcdefghijklmnop
MAIL_FROM_ADDRESS=autorisations.fanamby@gmail.com
MAIL_FROM_NAME="Fanamby – Autorisations"

VITE_APP_NAME="${APP_NAME}"
```

Pourquoi ces valeurs :

| Réglage | Pourquoi |
|---|---|
| `APP_ENV=production` | active les protections de production : liens des emails construits depuis `APP_URL`, commande `donnees:test` refusée, aucun compte de démonstration créé |
| `APP_DEBUG=false` | **obligatoire**. Sinon, la moindre erreur affiche une page technique avec les mots de passe de la base et du SMTP |
| `APP_URL` | les liens des emails (confirmation, mot de passe, dossiers) partent de cette adresse : elle doit être **exactement** celle du site (`https`, avec ou sans `www` comme dans le navigateur) |
| `SESSION_SECURE_COOKIE=true` | le cookie de connexion ne circule qu'en HTTPS |
| `LOG_LEVEL=warning` | journal limité aux problèmes, un fichier par jour dans `storage/logs/` |

Le `.env` contient tous les mots de passe : il ne doit **jamais** être envoyé sur GitHub
(il est déjà exclu par `.gitignore`). Sur un VPS, le réserver à votre utilisateur et au serveur web :
`chown $USER:www-data .env && chmod 640 .env`.

---

## 6. Installer et créer les tables

```bash
# Dépendances PHP, sans les outils de développement
composer install --no-dev --optimize-autoloader

# Clé de chiffrement de l'application (une seule fois, puis ne plus jamais la changer :
# elle chiffre les sessions, la changer déconnecte tout le monde)
php artisan key:generate

# Interface (Vue) compilée dans public/build
npm ci
npm run build

# Tables de la base, statuts du workflow et modèles de la page Références
php artisan migrate --force

# Le serveur web doit pouvoir écrire dans storage/ (fichiers déposés, journaux) et bootstrap/cache/
chown -R www-data:www-data storage bootstrap/cache     # www-data : utilisateur du serveur web (Ubuntu/Debian)
chmod -R 775 storage bootstrap/cache
```

> Pas de Node.js sur le serveur ? Lancez `npm ci && npm run build` sur votre ordinateur,
> puis envoyez le dossier `public/build` sur le serveur.

---

## 7. Charger les aires protégées et les types de projet

```bash
php artisan db:seed --class=PlateformeSeeder --force
```

Résultat attendu :

```
  ajoutée   Andrafiamena - Andavakoera (204 points de contour)
  ajoutée   Anjozorobe - Angavo (233 points de contour)
  ajoutée   Loky - Manambato (256 points de contour)
  ajoutée   Menabe Antimena (140 points de contour)
  ajoutée   Allée des Baobabs (11 points de contour)
```

Ce que la commande crée :

- **les 5 aires protégées** avec leur description et leur **contour réel** (simplifié à environ 100 m,
  pour une carte rapide). Les contours viennent de `database/seeders/aires_protegees.json` ;
- **les 12 types de projet** (long métrage, documentaire, projet étudiant…) avec les pièces demandées
  et les règles du cahier des charges ;
- les statuts du workflow, s'il en manque.

Ce qu'elle ne crée **pas** : aucun compte, aucune demande, aucune période sensible, aucune capacité d'accueil.
Ces réglages se font dans la plateforme (étape 12).

La commande peut être relancée sans risque : une aire ou un type déjà présent n'est ni dupliqué
ni modifié, et les changements faits par l'admin sont conservés.

> **À ne jamais lancer en production :** `php artisan donnees:test` (elle refuse de toute façon
> quand `APP_ENV=production`) ni le fichier `database/donnees_test.sql`. Ils effacent tout
> et créent des comptes dont le mot de passe est `password`.

---

## 8. Créer l'administrateur

```bash
php artisan plateforme:admin
```

La commande pose les questions une par une. Le mot de passe ne s'affiche pas pendant la saisie.

```
Adresse e-mail de l'administrateur :
> direction@fanamby.org
Nom complet :
> Prénom Nom
Mot de passe (10 caractères minimum, avec au moins une lettre et un chiffre) :
>
Confirmez le mot de passe :
>
Prénom Nom <direction@fanamby.org> est administrateur. Connexion : https://autorisations.fanamby.org/login
```

L'adresse de l'administrateur est marquée comme confirmée : il se connecte tout de suite,
sans attendre d'email.

**Autres usages de la même commande :**

- **Nommer administrateur un compte existant**, par exemple une personne qui s'est inscrite sur le site :
  `php artisan plateforme:admin son-adresse@exemple.mg`, puis répondre « yes ».
- **Redonner l'accès à un administrateur qui a perdu son mot de passe** (et dont les emails ne marchent pas) :
  même commande, puis « yes » à « Lui donner un nouveau mot de passe ? ».

---

## 9. Lancer l'envoi des notifications

Chaque étape d'un dossier envoie un email : soumission, demande de complément, décision, signalement…
Ces emails sont placés dans une **file d'attente** (table `t_jobs`) pour ne pas ralentir les pages.
Un programme doit tourner en permanence pour les envoyer : `php artisan queue:work`.

> Les emails de confirmation d'adresse et de mot de passe oublié partent tout de suite, sans la file.
> Si eux arrivent mais pas les notifications des dossiers, c'est que cette étape manque.

### Sur un serveur dédié (VPS) : Supervisor

```bash
sudo apt install supervisor
sudo nano /etc/supervisor/conf.d/fanamby-queue.conf
```

```ini
[program:fanamby-queue]
command=php /var/www/fanamby/3_Application/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/www/fanamby/3_Application/storage/logs/queue.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status fanamby-queue      # doit afficher RUNNING
```

### Sur un hébergement mutualisé : une tâche cron chaque minute

Dans « Tâches cron » du panneau, avec le chemin de votre compte :

```
* * * * * cd /home/votre-compte/fanamby/3_Application && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Les notifications partent alors au plus une minute après l'action.

---

## 10. Brancher le domaine et le HTTPS

Le HTTPS est obligatoire : mots de passe, pièces d'identité et liens de sécurité circulent sur la plateforme.

- **Hébergement mutualisé** : activer le certificat gratuit « Let's Encrypt » / « AutoSSL » dans le panneau.
- **VPS avec nginx ou Apache** : `sudo certbot --nginx -d autorisations.fanamby.org`
  (ou `--apache`).

**Si un proxy gère le HTTPS devant l'application** (Cloudflare, hébergeur avec répartiteur de charge,
nginx en frontal d'un autre serveur), mettez dans le `.env` :

```env
TRUSTED_PROXIES=*
```

Sans ce réglage, la plateforme se croit en `http` et **refuse les liens de confirmation d'adresse**
avec l'erreur « 403 Invalid signature ». Avec certbot directement sur le serveur, laissez la valeur vide.

Puis, **après chaque modification du `.env`** :

```bash
php artisan optimize:clear
php artisan optimize
```

`optimize` met en cache la configuration, les routes et les vues : la plateforme est plus rapide.
Mais le `.env` n'est plus relu tant qu'on ne relance pas ces deux commandes.

---

## 11. Vérifier que tout fonctionne

Faites ces essais dans l'ordre, avec une **vraie adresse email** que vous consultez.

**1. Envoi d'un email de test.** Lancez `php artisan tinker`, puis tapez :

```php
Mail::raw('Test de la plateforme Fanamby', fn ($m) => $m->to('votre-adresse@exemple.mg')->subject('Test SMTP'));
```

L'email doit arriver en moins d'une minute. Pensez à regarder les spams. Quittez avec `exit`.

**2. Le reste du parcours :**

| Essai | Résultat attendu |
|---|---|
| Ouvrir `https://votre-domaine/` | la carte affiche les 5 aires protégées avec leur contour |
| Se connecter avec l'administrateur | tableau de bord admin ; menu Aires protégées, Utilisateurs… |
| S'inscrire avec une autre adresse (navigation privée) | page « Vérifiez votre adresse e-mail » et email « Confirmez votre adresse e-mail » reçu, en français |
| Cliquer le lien de l'email | arrivée sur le tableau de bord, sans erreur 403 |
| « Mot de passe oublié » sur ce compte | email « Réinitialisez votre mot de passe » reçu ; le lien permet d'en choisir un nouveau |
| Déposer une demande avec ce compte | accusé de réception par email au demandeur, alerte aux validateurs du site et à l'admin |
| Provoquer une erreur (`/une-page-qui-n-existe-pas`) | page « 404 · Page introuvable », **sans** détail technique |

Supprimez ensuite le compte d'essai : Profil → « Supprimer mon compte ».

---

## 12. Premiers réglages dans la plateforme

Connecté en administrateur :

1. **Validateurs.** Chaque responsable crée son compte sur le site (« Créer un compte ») et confirme son adresse.
   L'admin ouvre ensuite **Utilisateurs**, choisit le rôle `validateur` et coche ses aires protégées.
   Rôle `observateur` : suit tous les dossiers sans décider.
2. **Aires protégées.** Pour chaque aire : capacité d'accueil, conditions particulières, photos.
   Le contour se corrige depuis la même page.
3. **Périodes sensibles.** Périodes de reproduction, de nidification… L'analyse des demandes en tient compte.
4. **Types de projet.** Ajuster les pièces exigées et les règles du cahier des charges si besoin.
5. **Références.** Relire les modèles des documents officiels (autorisation, refus…).
6. **Votre profil.** Fonction et organisation : elles figurent sur les documents signés.

---

## 13. Mettre à jour la plateforme plus tard

Quand le code évolue :

```bash
cd /var/www/fanamby
php 3_Application/artisan down                     # page « maintenance » pendant la mise à jour
git pull
cd 3_Application
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan queue:restart                          # le programme d'envoi recharge le nouveau code
php artisan up
```

---

## 14. Sauvegardes

Deux choses à sauvegarder **chaque jour**, copiées hors du serveur :

| Quoi | Comment |
|---|---|
| La base de données | `mysqldump -u fanamby -p fanamby > sauvegarde-$(date +%F).sql` |
| Les fichiers déposés : pièces des dossiers, CIN et passeports, photos, documents officiels | dossier `storage/app/private/` |

Conservez aussi le fichier `.env`, en lieu sûr. Sans la même `APP_KEY`, les sessions sont perdues.

Ces fichiers contiennent des données personnelles (pièces d'identité) : stockez les sauvegardes
dans un espace dont l'accès est restreint.

---

## 15. En cas de problème

Le journal des erreurs est le premier endroit à regarder :

```bash
tail -n 50 storage/logs/laravel-$(date +%F).log
```

| Symptôme | Cause probable | Solution |
|---|---|---|
| Page blanche, l'interface ne se charge pas | `public/hot` présent, ou `public/build` absent | supprimer `public/hot` ; relancer `npm run build` |
| « 500 · Erreur du serveur » | droits d'écriture, `.env` incomplet, `APP_KEY` vide | lire le journal ; `chmod -R 775 storage bootstrap/cache` ; `php artisan key:generate` |
| « 403 Invalid signature » en cliquant le lien de confirmation | HTTPS géré par un proxy, ou `APP_URL` différente de l'adresse du site | `TRUSTED_PROXIES=*` ; corriger `APP_URL` ; puis `optimize:clear` et `optimize` |
| Aucun email ne part | réglages SMTP faux, ou `.env` modifié sans vider le cache | test `tinker` de l'étape 11 ; `php artisan optimize:clear && php artisan optimize` ; lire le journal |
| Erreur « Username and Password not accepted » (Gmail) | mot de passe normal au lieu du mot de passe d'application | recréer un mot de passe d'application (étape 2) |
| Confirmation et mot de passe OK, mais pas les notifications des dossiers | le programme d'envoi ne tourne pas | étape 9 ; `php artisan queue:failed` liste les échecs, `php artisan queue:retry all` les relance |
| Les emails arrivent en spam | SPF/DKIM absents pour le domaine d'envoi | ajouter les enregistrements DNS du service d'envoi (étape 2, solution B) |
| « 419 · Page expirée » à la connexion | cookie sécurisé alors que le site est en `http` | activer le HTTPS (étape 10), ou `SESSION_SECURE_COOKIE=false` le temps de le faire |
| « Trop de tentatives depuis votre connexion » | limite de 5 inscriptions ou demandes de mot de passe par minute (20 par heure) depuis une même adresse IP | patienter ; c'est la protection contre les robots |
| Un utilisateur ne reçoit pas son email de confirmation | adresse mal saisie, ou email en spam | il peut corriger son adresse dans Profil, puis cliquer « Renvoyer l'e-mail » |
| L'administrateur a perdu son mot de passe | | `php artisan plateforme:admin son-adresse`, puis « yes » au nouveau mot de passe |
