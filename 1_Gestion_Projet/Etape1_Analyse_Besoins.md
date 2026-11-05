# ÉTAPE 1 : ANALYSE DES BESOINS

## 1. Règles de gestion

Les règles de gestion traduisent les contraintes métiers de l'association Fanamby sous forme de phrases simples, compréhensibles par tous. Ce sont elles qui dictent la construction du Modèle Conceptuel de Données (MCD).

*   **RG01** : Un utilisateur est caractérisé par un rôle unique (demandeur, validateur, administrateur ou observateur).
*   **RG02** : Un utilisateur (demandeur) peut soumettre de zéro à plusieurs demandes d'autorisation.
*   **RG03** : Une demande concerne toujours un et un seul site (aire protégée).
*   **RG04** : Un utilisateur (validateur) peut être responsable de zéro, un ou plusieurs sites, et un site peut être géré par zéro, un ou plusieurs validateurs. Un site sans validateur est traité par l'administration.
*   **RG05** : Une demande appartient à un et un seul type de demande (ex: Tournage, Recherche scientifique).
*   **RG06** : Un type de demande exige zéro à plusieurs pièces justificatives et impose zéro à plusieurs règles spécifiques, reprises dans le cahier des charges. Une pièce requise ou une règle n'appartient qu'à un seul type, et un même type ne peut pas exiger deux fois la même pièce.
*   **RG07** : Une demande est rattachée à un statut actuel (brouillon, soumise, en analyse, à compléter, validée, rejetée ou retirée).
*   **RG08** : Un statut possède un code unique et un libellé descriptif.
*   **RG09** : Tous les changements de statut d'une demande sont conservés dans un historique, à partir de sa soumission (un brouillon n'a pas encore d'historique). Chaque entrée consigne l'ancien et le nouveau statut, la date et l'utilisateur auteur ; si le compte de l'auteur est supprimé, l'entrée est conservée sans auteur.
*   **RG10** : Une demande peut contenir de zéro à plusieurs documents justificatifs, mais un document appartient à une seule demande. Chaque document fournit au plus une pièce requise par le type de la demande ; s'il est remplacé, l'ancien fichier est supprimé.
*   **RG11** : Une aire protégée peut être concernée par zéro ou plusieurs périodes sensibles (ex: nidification des tortues).
*   **RG12** : Le système génère des notifications. Une notification est adressée à un seul utilisateur et concerne au maximum une demande spécifique.
*   **RG13** : La date de fin d'une demande ou d'une période sensible ne peut pas précéder sa date de début, et une équipe compte au moins une personne.
*   **RG14** : Un site ou un type de demande auquel des demandes sont rattachées ne peut pas être supprimé : les demandes et leur historique sont des traces à conserver.
*   **RG15** : Une aire protégée peut fixer une capacité d'accueil (nombre maximal de personnes présentes en même temps, au moins une) et imposer zéro à plusieurs règles permanentes ; une règle permanente n'appartient qu'à une seule aire protégée. Une période sensible d'avertissement peut proposer une condition à respecter.
*   **RG16** : Le demandeur peut retirer (annuler) une demande envoyée tant qu'aucune décision n'a été prise ; le statut « Retirée » est définitif.
*   **RG17** : L'équipe arrive sur le terrain au plus tard le jour du début des activités. L'analyse automatique porte sur la période des activités ; l'arrivée sert au suivi (comptes à rebours, présence sur le terrain).
*   **RG18** : Pour une demande, la plateforme prépare au plus un document officiel de chaque type (autorisation, cahier des charges, convention de collaboration), généré par au plus un validateur ou administrateur. Ses textes sont relus avant la validation, puis figés.
*   **RG19** : Une aire protégée peut être illustrée par zéro à douze photos ; une photo n'appartient qu'à une seule aire protégée et disparaît avec elle. Une aire protégée peut aussi renvoyer vers une banque d'images complète, hébergée ailleurs.
*   **RG20** : Un membre de l'équipe (observateur, validateur du site ou administrateur) peut signaler zéro à plusieurs problèmes sur une demande. Un signalement porte sur une seule demande et une seule partie du dossier ; il a au plus un auteur et est traité au plus une fois, par un validateur du site ou un administrateur. Le demandeur ne voit pas les signalements.
*   **RG21** : Un observateur consulte toutes les demandes envoyées, sur toutes les aires protégées, sans jamais prendre de décision.
*   **RG22** : L'équipe dépose des documents de référence (chacun a au plus un auteur, conservé sans nom si son compte est supprimé) ; un modèle de document officiel a un titre unique.

---

## 2. Dictionnaire des données

Le dictionnaire des données recense de manière exhaustive toutes les informations élémentaires (attributs) que le système devra mémoriser, avant même de les regrouper dans des tables.

| Donnée | Type | Description |
| :--- | :--- | :--- |
| **id_utilisateur** | Entier | Identifiant unique de l'utilisateur |
| **nom_utilisateur** | Texte | Nom complet de la personne |
| **email_utilisateur**| Texte | Adresse email (sert d'identifiant de connexion) |
| **mot_de_passe** | Texte | Mot de passe chiffré |
| **role_utilisateur** | Texte court | Niveau de privilège (demandeur, validateur, admin, observateur) |
| **organisation_utilisateur** | Texte | Institution, société de production ou organisation (formulaire de demande, section I) |
| **fonction_utilisateur** | Texte | Fonction du demandeur ; pour un décideur, reprise dans la décision de Fanamby (section VI) |
| **adresse_utilisateur** | Texte | Adresse postale du demandeur |
| **telephone_utilisateur** | Texte court | Numéro de téléphone du demandeur |
| **piece_identite** | Texte court | N° de CIN ou de passeport du demandeur (section V, engagement) |
| **id_site** | Entier | Identifiant unique de l'aire protégée |
| **nom_site** | Texte | Nom de l'aire protégée (ex: Lokobe) |
| **description_site** | Texte long | Informations générales sur le site |
| **capacite_accueil** | Entier | Nombre maximal de personnes présentes en même temps sur l'aire protégée (vide : sans limite) |
| **lien_photos** | Texte | Adresse de la banque d'images complète de l'aire protégée |
| **coordonnees_site** | JSON | Tracé du polygone géographique pour la carte interactive |
| **id_condition_site** | Entier | Identifiant d'une règle permanente de l'aire protégée |
| **libelle_condition_site** | Texte | Règle permanente, proposée dans les conditions particulières de chaque autorisation |
| **id_photo** | Entier | Identifiant d'une photo de l'aire protégée |
| **chemin_photo** | Texte | Chemin de la grande image (1600 px au plus) |
| **chemin_vignette** | Texte | Chemin de la vignette de la galerie (480 px au plus) |
| **largeur_photo** | Entier | Largeur de la grande image, en pixels |
| **hauteur_photo** | Entier | Hauteur de la grande image, en pixels |
| **legende_photo** | Texte | Légende affichée sous la photo |
| **credit_photo** | Texte | Auteur ou ayant droit de la photo |
| **ordre_photo** | Entier | Rang de la photo dans la galerie |
| **id_type_demande** | Entier | Identifiant du type de demande |
| **libelle_type** | Texte | Type de projet du formulaire de demande (Long métrage, Documentaire, Publicité, Autre...) |
| **precision_requise** | Booléen | Le demandeur doit préciser son projet (choix « Autre (précisez) ») |
| **id_document_requis** | Entier | Identifiant d'une pièce exigée pour un type |
| **libelle_document_requis** | Texte | Nom de la pièce exigée (ex : Plan de vol drone) |
| **reutilisable** | Booléen | Pièce reprise d'un dossier précédent du même demandeur (copie de la CIN, assurance…) |
| **id_regle** | Entier | Identifiant d'une règle spécifique |
| **libelle_regle** | Texte | Règle à respecter, reprise dans le cahier des charges (ex : Équipe limitée à 5 personnes) |
| **id_demande** | Entier | Identifiant unique de la demande |
| **titre_projet** | Texte | Titre du projet (section II) |
| **precision_type** | Texte | Précision du type de projet lorsque le type la demande (« Autre ») |
| **description_demande**| Texte long | Résumé du synopsis du projet |
| **objectifs_projet** | Texte long | Objectif(s) principal(aux) du projet |
| **date_arrivee_terrain** | Date | Date d'arrivée de l'équipe sur le terrain, au plus tard le début des activités |
| **date_debut_activite**| Date | Date prévue de début des activités |
| **date_fin_activite**| Date | Date prévue de fin des activités |
| **nombre_personnes** | Entier | Taille de l'équipe prévue sur le terrain |
| **date_soumission** | DateHeure | Moment de l'envoi définitif de la demande |
| **resultats_analyse**| JSON | Rapport du moteur de règles figé lors de la soumission |
| **id_statut** | Entier | Identifiant du statut |
| **code_statut** | Texte court | Code technique unique (ex: en_analyse) |
| **libelle_statut** | Texte | Nom lisible affiché (ex: En cours d'analyse) |
| **description_statut** | Texte long | Explication du statut |
| **id_document** | Entier | Identifiant du document joint |
| **nom_fichier** | Texte | Nom original du fichier uploadé |
| **chemin_fichier** | Texte | Chemin de stockage local sécurisé |
| **type_document** | Texte court | Nom de la pièce requise fournie, tel qu'il était au moment du dépôt |
| **id_historique** | Entier | Identifiant d'une entrée de l'historique des statuts |
| **commentaire_statut**| Texte long | Motif, recommandation ou observation de validation |
| **date_changement** | DateHeure | Moment où le statut de la demande a été modifié |
| **id_periode** | Entier | Identifiant d'une période de restriction |
| **date_debut_periode**| Date | Date de début de la sensibilité écologique |
| **date_fin_periode** | Date | Date de fin de la sensibilité écologique |
| **description_periode**| Texte | Raison de la contrainte (ex: Nidification espèce endémique)|
| **niveau_restriction**| Texte court | Gravité du conflit (bloquant ou avertissement) |
| **condition_proposee** | Texte | Condition proposée par l'analyse quand un séjour chevauche une période d'avertissement |
| **id_notification** | Entier | Identifiant unique de la notification |
| **contenu_notification**| Texte long | Message d'alerte ou d'information à afficher |
| **lu_notification** | Booléen | Indique si l'utilisateur a consulté la notification |
| **date_envoi** | DateHeure | Date de génération de la notification |
| **id_document_officiel** | Entier | Identifiant d'un document officiel préparé pour une demande |
| **type_document_officiel** | Texte court | Autorisation, cahier des charges ou convention de collaboration |
| **contenu_document_officiel** | JSON | Textes du document relus par le validateur (un champ par partie ou article) |
| **id_signalement** | Entier | Identifiant d'un signalement |
| **partie_concernee** | Texte court | Partie du dossier visée (coordonnées, projet, dates et équipe, pièces, analyse, documents, suivi, autre) |
| **message_signalement** | Texte long | Problème constaté |
| **date_signalement** | DateHeure | Moment où le problème a été signalé |
| **reponse_signalement** | Texte long | Réponse de la personne qui a traité le signalement |
| **date_traitement** | DateHeure | Moment où le signalement a été traité (vide : ouvert) |
| **id_document_reference** | Entier | Identifiant d'un document déposé sur la page Références |
| **titre_document_reference** | Texte | Titre du document de référence |
| **description_document_reference** | Texte long | À quoi sert le document |
| **nom_fichier_reference** | Texte | Nom d'origine du fichier déposé |
| **chemin_reference** | Texte | Chemin de stockage privé du fichier |
| **type_mime** | Texte court | Format du fichier (PDF, Word, image…) |
| **taille_fichier** | Entier | Taille du fichier, en octets |
| **id_modele** | Entier | Identifiant d'un modèle de document officiel |
| **titre_modele** | Texte | Titre unique du modèle (ex : Avis favorable) |
| **description_modele** | Texte long | À quoi sert le modèle |
| **usage_modele** | Texte long | Quand l'utiliser |
| **icone_modele** | Texte court | Icône affichée sur la carte du modèle |
| **automatique** | Booléen | Document produit par la plateforme à la validation (autorisation, cahier des charges, convention) |
| **contenu_modele** | Texte long | Texte du modèle, avec ses champs à compléter entre crochets |
