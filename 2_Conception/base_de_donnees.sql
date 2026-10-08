-- =====================================================================================
-- DÉMARCHE DE CONCEPTION (Basée sur le cours)
-- =====================================================================================
-- 
-- ÉTAPE 1 & 2 : MCD (Modèle Conceptuel)
-- Entités : UTILISATEUR, SITE, STATUT, TYPE_DEMANDE, DEMANDE, DOCUMENT, HISTORIQUE_STATUT, 
--           PERIODE_SENSIBLE, NOTIFICATION
-- Associations : S'INSCRIRE (n,m) ici représenté par la gestion des sites par les validateurs.
-- 
-- ÉTAPE 3 : MLD (Modèle Logique)
-- Les associations (1,n) deviennent des clés étrangères (ex: id_utilisateur dans t_demande).
-- L'association (n,m) entre UTILISATEUR et SITE devient la table d'association t_site_validateur.
--
-- ÉTAPE 4 : Normalisation (jusqu'à 3NF)
-- Tous les attributs dépendent de leur clé primaire. Par exemple, l'email dépend 
-- d'un utilisateur, pas d'une demande.
--
-- ÉTAPE 5 : MPD et Dénormalisation maîtrisée (Le contre-pied)
-- 1. Le 'statut' est conservé dans 't_demande' : évite une jointure coûteuse 
--    sur t_historique_statut à chaque affichage (comme 'nb_commandes' dans le cours).
-- 2. 'resultats_analyse' (JSON) dans 't_demande' : fige les contraintes au moment 
--    de la soumission. Si les règles changent l'année suivante, l'ancienne demande 
--    garde ses résultats intacts (exactement comme le 'prix_unitaire' figé du cours !).
-- =====================================================================================

CREATE TABLE t_utilisateur (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('demandeur', 'validateur', 'admin')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE t_site (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    description TEXT,
    polygone_coordonnees JSON, -- Pour la carte interactive
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table d'association (Issue du MLD pour une relation plusieurs-à-plusieurs)
CREATE TABLE t_site_validateur (
    id_utilisateur INT NOT NULL REFERENCES t_utilisateur(id) ON DELETE CASCADE,
    id_site INT NOT NULL REFERENCES t_site(id) ON DELETE CASCADE,
    PRIMARY KEY (id_utilisateur, id_site)
);

CREATE TABLE t_statut (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE, -- ex: 'brouillon', 'soumise', 'validee'
    libelle VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE t_type_demande (
    id SERIAL PRIMARY KEY,
    libelle VARCHAR(100) NOT NULL,
    documents_requis JSON, 
    regles_specifiques JSON, -- Règles métier pour l'analyse
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE t_demande (
    id SERIAL PRIMARY KEY,
    id_utilisateur INT NOT NULL REFERENCES t_utilisateur(id),
    id_site INT NOT NULL REFERENCES t_site(id),
    id_type INT NOT NULL REFERENCES t_type_demande(id),
    description TEXT,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    equipe_nombre INT DEFAULT 1,
    -- Dénormalisation assumée : on stocke l'ID du statut ici pour accès rapide (évite jointure historique)
    id_statut INT NOT NULL REFERENCES t_statut(id), 
    date_soumission TIMESTAMP,
    -- Dénormalisation assumée : fige les règles évaluées au moment de la demande
    resultats_analyse JSON, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE t_document (
    id SERIAL PRIMARY KEY,
    id_demande INT NOT NULL REFERENCES t_demande(id) ON DELETE CASCADE,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin VARCHAR(255) NOT NULL,
    type_document VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE t_historique_statut (
    id SERIAL PRIMARY KEY,
    id_demande INT NOT NULL REFERENCES t_demande(id) ON DELETE CASCADE,
    id_utilisateur INT NOT NULL REFERENCES t_utilisateur(id),
    id_ancien_statut INT REFERENCES t_statut(id),
    id_nouveau_statut INT NOT NULL REFERENCES t_statut(id),
    commentaire TEXT,
    date_changement TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE t_periode_sensible (
    id SERIAL PRIMARY KEY,
    id_site INT NOT NULL REFERENCES t_site(id) ON DELETE CASCADE,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    niveau_restriction VARCHAR(50) NOT NULL CHECK (niveau_restriction IN ('bloquant', 'avertissement')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE t_notification (
    id SERIAL PRIMARY KEY,
    id_utilisateur INT NOT NULL REFERENCES t_utilisateur(id) ON DELETE CASCADE,
    id_demande INT REFERENCES t_demande(id) ON DELETE CASCADE,
    contenu TEXT NOT NULL,
    lu BOOLEAN DEFAULT FALSE,
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour accélérer les requêtes fréquentes (Étape 5 du cours)
CREATE INDEX idx_t_demande_utilisateur ON t_demande(id_utilisateur);
CREATE INDEX idx_t_demande_site ON t_demande(id_site);
CREATE INDEX idx_t_historique_demande ON t_historique_statut(id_demande);
