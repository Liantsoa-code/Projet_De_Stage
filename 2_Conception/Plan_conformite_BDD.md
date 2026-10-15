# Mise en conformité de la base de données

Ce document suit toute la démarche de mise en conformité, depuis le début : ce qu'on a vérifié, ce qu'on a trouvé, ce qui a été corrigé et ce qui reste à faire.

La référence est le document de cours [Demarche_conception_bdd.docx](Demarche_conception_bdd.docx), qui définit cinq étapes : besoins, MCD, MLD, normalisation et MPD, plus la dénormalisation.

---

## 1. Vérification de départ : Base.pdf, schema.dbml et migrations

Le diagramme [Base.pdf](../Base.pdf), le fichier [schema.dbml](../3_Application/database/schema.dbml) et les migrations Laravel correspondaient :

- les 10 tables, toutes leurs colonnes et leurs types ;
- les 2 enums et les 14 relations.

Un seul écart a été relevé : `demandes.statut_id` était déclaré `RESTRICT` dans la migration, mais sans action dans le DBML. Il est corrigé.

## 2. Confrontation avec la démarche du cours

| Étape | Constat | Gravité |
|---|---|---|
| 1. Besoins | RG04 imposait au moins un validateur par site, alors que l'application en accepte zéro. RG09 disait l'historique complet, mais la soumission n'y était pas. Le dictionnaire était incomplet (`description_statut`) et contenait un nom faux (`regles_analyse`). | Moyenne |
| 2. MCD | Absent : il n'y avait aucun diagramme Merise avec ses cardinalités. | Forte |
| 3. MLD | Absent : il n'y avait aucun schéma relationnel rédigé. | Forte |
| 4. Normalisation | 2NF et 3NF respectées. **La 1NF était violée** : `documents_requis` et `regles_specifiques` étaient des listes rangées en JSON. Renommer une pièce faisait perdre les fichiers déjà déposés. | Forte |
| 5. MPD | Aucun CHECK. Supprimer un site ou un type effaçait ses demandes en cascade, et seul le code PHP l'empêchait. L'index de la cloche manquait. Le script `base_de_donnees.sql` était obsolète (syntaxe PostgreSQL, mauvais noms). | Moyenne |
| Dénormalisation | `statut_id` était présenté comme une copie de l'historique, mais cette copie n'était pas cohérente : les soumissions n'étaient pas historisées. | Moyenne |

---

## 3. Ce qui a été fait

### Base de données (3 migrations)

- [x] **Correction de la 1NF.** Les listes JSON deviennent les tables `documents_requis` et `regles_specifiques`. Chaque pièce jointe est reliée à sa pièce requise par `documents.document_requis_id`. Les données existantes sont reprises.
  - Migration : [2026_10_13_090000_normaliser_listes_type_demandes.php](../3_Application/database/migrations/2026_10_13_090000_normaliser_listes_type_demandes.php)
- [x] **Contraintes du MPD.**
  - CHECK `date_fin >= date_debut` sur `demandes` et `periode_sensibles`, et CHECK `equipe_nombre >= 1`.
  - `RESTRICT` sur la suppression d'un site ou d'un type encore utilisé.
  - Index `notifications (user_id, lu, date_envoi)`.
  - Migration : [2026_10_13_090100_contraintes_integrite_bdd.php](../3_Application/database/migrations/2026_10_13_090100_contraintes_integrite_bdd.php)
- [x] **Historisation des soumissions déjà faites.** Désormais, `statut_id` est toujours le dernier statut de l'historique.
  - Migration : [2026_10_13_090200_historiser_soumissions.php](../3_Application/database/migrations/2026_10_13_090200_historiser_soumissions.php)

### Application

- [x] La soumission crée une entrée dans l'historique (`DemandeController::historiser`).
- [x] Les pièces jointes sont reconnues par l'identifiant de la pièce requise, et non plus par son libellé. Renommer une pièce ne fait plus perdre les fichiers.
- [x] Dans l'écran admin des types, les pièces requises et les règles se saisissent ligne par ligne (composant `ListeEditable.vue`). Avant, c'était un texte séparé par des virgules, et les règles n'étaient même pas modifiables.
- [x] Le cahier des charges PDF lit les règles depuis la nouvelle table.
- [x] Les commentaires des suppressions de site et de type sont mis à jour (la base applique maintenant RESTRICT).
- [x] Seeder et jeu de test [donnees_test.sql](../3_Application/database/donnees_test.sql) adaptés : 9 pièces requises, 11 règles et l'historique des soumissions.

### Tests et vérifications

- [x] **86 tests au vert**, dont 10 nouveaux dans [ConceptionBddTest.php](../3_Application/tests/Feature/ConceptionBddTest.php). Ils couvrent l'historisation, le renommage et le retrait d'une pièce, la pièce en double, le cahier des charges, les CHECK et les RESTRICT.
- [x] **Migrations vérifiées sur une copie des données** :
  - les 45 pièces jointes sur 45 sont rattachées ;
  - les 20 soumissions sont historisées ;
  - le retour arrière (`migrate:rollback`) restaure l'état d'origine.
- [x] **Contrôle de l'invariant** : pour les 22 demandes, `statut_id` est égal au dernier statut historisé (0 écart).
- [x] Base de développement `projet_stage` migrée.
- [x] Front compilé (`npm run build`).

### Documentation de conception

- [x] [Etape1_Analyse_Besoins.md](../1_Gestion_Projet/Etape1_Analyse_Besoins.md) :
  - RG04, RG06, RG09 et RG10 corrigées ;
  - RG13 (dates, effectif) et RG14 (traces à conserver) ajoutées ;
  - dictionnaire complété.
- [x] [Conception_BDD.md](Conception_BDD.md) : la démarche complète en 7 sections (besoins, MCD, MLD, normalisation, MPD, dénormalisations, conformité).
- [x] [base_de_donnees.sql](base_de_donnees.sql) réécrit en MySQL. Il est vérifié **identique** à la base migrée : colonnes, types, clés, CHECK et index.
- [x] [schema.dbml](../3_Application/database/schema.dbml) mis à jour : nouvelles tables, RESTRICT, CHECK et index.

---

## 4. Ce qui reste à faire

### À faire vous-même

- [ ] **Régénérer `Base.pdf`** : collez [schema.dbml](../3_Application/database/schema.dbml) sur [dbdiagram.io](https://dbdiagram.io), puis réexportez le PDF. L'actuel ne contient pas `documents_requis`, `regles_specifiques` ni les RESTRICT.
- [ ] **Tester à l'écran** l'admin des types (`/admin/type-demandes`) : ajouter, renommer et retirer une pièce ou une règle. Puis le formulaire de demande avec ses pièces jointes.
- [ ] **Sur toute autre machine**, lancer `php artisan migrate`.
- [ ] **Commiter** les changements une fois vérifiés.
- [ ] *(Facultatif)* Lancer `php artisan boost:install`, demandé par le `CLAUDE.md` du projet. La commande est interactive.

### Restant en cours (à terminer)

- [x] **Diagramme MCD** : [MCD.svg](MCD.svg), généré par Mocodo à partir de [mcd.mcd](mcd.mcd). Il compte 11 entités et 16 associations, et sa disposition ne laisse que 2 croisements de liens. Pour l'ajuster, collez **mcd.mcd** (pas schema.dbml) sur [mocodo.net](https://www.mocodo.net).
- [ ] **Chevauchements du MCD** : 3 paires de cardinalités se superposent (autour de DEMANDE et de HISTORIQUE_STATUT). Il faut les déplacer à la main sur mocodo.net avant l'export final.
- [x] **[STRUCTURE.md](../3_Application/STRUCTURE.md)** : soumission historisée, écran des types ligne par ligne, modèles `DocumentRequis` et `RegleSpecifique`, migrations 15 à 17, jeu de test, 86 tests avec `ConceptionBddTest`, composant `ListeEditable.vue`, lien vers `2_Conception/`.
- [x] **[regle.txt](../1_Gestion_Projet/regle.txt)** : la stack réelle (Vue 3, Inertia, Tailwind, Gates Laravel) et le modèle de données actuel, avec un renvoi vers `Conception_BDD.md`.

---

## 5. Commandes utiles

```bash
# Appliquer les migrations
php artisan migrate

# Annuler les trois migrations de mise en conformité (testé)
php artisan migrate:rollback --step=3

# Recharger le jeu de données de test
mysql -u root projet_stage < database/donnees_test.sql

# Lancer les tests (base projet_stage_test)
php artisan test

# Compiler le front
npm run build
```
