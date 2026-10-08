# ÉTAPE 1 : ANALYSE DES BESOINS

## 1. Règles de gestion

Les règles de gestion traduisent les contraintes métiers de l'association Fanamby sous forme de phrases simples, compréhensibles par tous. Ce sont elles qui dictent la construction du Modèle Conceptuel de Données (MCD).

*   **RG01** : Un utilisateur est caractérisé par un rôle unique (demandeur, validateur ou administrateur).
*   **RG02** : Un utilisateur (demandeur) peut soumettre de zéro à plusieurs demandes d'autorisation.
*   **RG03** : Une demande concerne toujours un et un seul site (aire protégée).
*   **RG04** : Un utilisateur (validateur) peut être responsable d'un ou plusieurs sites, et un site peut être géré par un ou plusieurs validateurs.
*   **RG05** : Une demande appartient à un et un seul type de demande (ex: Tournage, Recherche scientifique).
*   **RG06** : Un type de demande définit une liste de documents requis et un ensemble de règles métiers d'analyse spécifiques.
*   **RG07** : Une demande est rattachée à un statut actuel (brouillon, soumise, validée, etc.).
*   **RG08** : Un statut possède un code unique et un libellé descriptif.
*   **RG09** : Les changements de statut d'une demande sont conservés dans un historique, consignant l'utilisateur auteur de la modification et la date.
*   **RG10** : Une demande peut contenir de zéro à plusieurs documents justificatifs, mais un document appartient à une seule demande.
*   **RG11** : Un site protégé peut être concerné par zéro ou plusieurs périodes sensibles (ex: nidification des tortues).
*   **RG12** : Le système génère des notifications. Une notification est adressée à un seul utilisateur et concerne au maximum une demande spécifique.

---

## 2. Dictionnaire des données

Le dictionnaire des données recense de manière exhaustive toutes les informations élémentaires (attributs) que le système devra mémoriser, avant même de les regrouper dans des tables.

| Donnée | Type | Description |
| :--- | :--- | :--- |
| **id_utilisateur** | Entier | Identifiant unique de l'utilisateur |
| **nom_utilisateur** | Texte | Nom complet de la personne |
| **email_utilisateur**| Texte | Adresse email (sert d'identifiant de connexion) |
| **mot_de_passe** | Texte | Mot de passe chiffré |
| **role_utilisateur** | Texte court | Niveau de privilège (demandeur, validateur, admin) |
| **id_site** | Entier | Identifiant unique du site protégé |
| **nom_site** | Texte | Nom de l'aire protégée (ex: Lokobe) |
| **description_site** | Texte long | Informations générales sur le site |
| **coordonnees_site** | JSON | Tracé du polygone géographique pour la carte interactive |
| **id_type_demande** | Entier | Identifiant du type de demande |
| **libelle_type** | Texte | Catégorie (Tournage par drone, Recherche...) |
| **documents_requis** | JSON | Modèle définissant les pièces jointes obligatoires |
| **regles_analyse** | JSON | Configuration des contraintes pour le moteur de règles |
| **id_demande** | Entier | Identifiant unique de la demande |
| **description_demande**| Texte long | Détail des activités prévues sur le site |
| **date_debut_activite**| Date | Date prévue de début de l'accès au site |
| **date_fin_activite**| Date | Date prévue de fin de l'accès au site |
| **nombre_personnes** | Entier | Taille de l'équipe prévue sur le terrain |
| **date_soumission** | DateHeure | Moment de l'envoi définitif de la demande |
| **resultats_analyse**| JSON | Rapport du moteur de règles figé lors de la soumission |
| **id_statut** | Entier | Identifiant du statut |
| **code_statut** | Texte court | Code technique unique (ex: en_analyse) |
| **libelle_statut** | Texte | Nom lisible affiché (ex: En cours d'analyse) |
| **id_document** | Entier | Identifiant du document joint |
| **nom_fichier** | Texte | Nom original du fichier uploadé |
| **chemin_fichier** | Texte | Chemin de stockage local sécurisé |
| **type_document** | Texte court | Nature de la pièce jointe (CNI, Plan de vol, PDF généré) |
| **commentaire_statut**| Texte long | Motif, recommandation ou observation de validation |
| **date_changement** | DateHeure | Moment où le statut de la demande a été modifié |
| **id_periode** | Entier | Identifiant d'une période de restriction |
| **date_debut_periode**| Date | Date de début de la sensibilité écologique |
| **date_fin_periode** | Date | Date de fin de la sensibilité écologique |
| **description_periode**| Texte | Raison de la contrainte (ex: Nidification espèce endémique)|
| **niveau_restriction**| Texte court | Gravité du conflit (bloquant ou avertissement) |
| **id_notification** | Entier | Identifiant unique de la notification |
| **contenu_notification**| Texte long | Message d'alerte ou d'information à afficher |
| **lu_notification** | Booléen | Indique si l'utilisateur a consulté la notification |
| **date_envoi** | DateHeure | Date de génération de la notification |
