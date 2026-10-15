-- =====================================================================================
-- MODÈLE PHYSIQUE DE DONNÉES (MPD) — Plateforme d'accès aux aires protégées FANAMBY
-- =====================================================================================
-- SGBD cible : MySQL 8.0.16+ / MariaDB 10.2+ (InnoDB, utf8mb4).
-- Ce script reproduit exactement le schéma produit par les migrations Laravel
-- (3_Application/database/migrations). En cas d'écart, ce sont les migrations qui font foi.
--
-- Il n'est qu'une étape de la démarche : règles de gestion et dictionnaire des données
-- (1_Gestion_Projet/Etape1_Analyse_Besoins.md), puis MCD, MLD, normalisation et
-- dénormalisation (2_Conception/Conception_BDD.md).
--
-- Seules les tables métier figurent ici. Les tables techniques de Laravel (sessions,
-- cache, jobs, migrations, password_reset_tokens…) sont créées par le framework.
-- =====================================================================================

SET NAMES utf8mb4;

-- ── Utilisateurs (RG01) ──
CREATE TABLE users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(255) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password          VARCHAR(255) NOT NULL,
    role              ENUM('demandeur', 'validateur', 'admin') NOT NULL DEFAULT 'demandeur',
    remember_token    VARCHAR(100) NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Aires protégées ──
CREATE TABLE sites (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom                  VARCHAR(150) NOT NULL,
    description          TEXT NULL,
    polygone_coordonnees JSON NULL,            -- un contour géographique = une seule valeur
    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Association GÉRER (n,m) entre validateur et site (RG04) ──
CREATE TABLE site_user (
    user_id BIGINT UNSIGNED NOT NULL,
    site_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, site_id),
    KEY site_user_site_id_foreign (site_id),
    CONSTRAINT site_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT site_user_site_id_foreign FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Statuts du workflow (RG07, RG08) ──
CREATE TABLE statuts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(50) NOT NULL,          -- brouillon, soumise, en_analyse, a_completer, validee, rejetee
    libelle     VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY statuts_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Types d'activité (RG05) ──
CREATE TABLE type_demandes (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle    VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pièces exigées par type (RG06) — 1NF : une ligne par pièce ──
CREATE TABLE documents_requis (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_demande_id BIGINT UNSIGNED NOT NULL,
    libelle         VARCHAR(150) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY documents_requis_type_demande_id_libelle_unique (type_demande_id, libelle),
    CONSTRAINT documents_requis_type_demande_id_foreign FOREIGN KEY (type_demande_id) REFERENCES type_demandes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Règles du cahier des charges par type (RG06) — 1NF : une ligne par règle ──
CREATE TABLE regles_specifiques (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_demande_id BIGINT UNSIGNED NOT NULL,
    libelle         VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY regles_specifiques_type_demande_id_foreign (type_demande_id),
    CONSTRAINT regles_specifiques_type_demande_id_foreign FOREIGN KEY (type_demande_id) REFERENCES type_demandes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Demandes d'autorisation (RG02, RG03, RG05, RG07) ──
CREATE TABLE demandes (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           BIGINT UNSIGNED NOT NULL,
    site_id           BIGINT UNSIGNED NOT NULL,
    type_demande_id   BIGINT UNSIGNED NOT NULL,
    description       TEXT NULL,
    date_debut        DATE NULL,                -- facultative tant que la demande est un brouillon
    date_fin          DATE NULL,
    equipe_nombre     INT NOT NULL DEFAULT 1,
    -- Dénormalisation maîtrisée : copie du dernier statut de historique_statuts
    -- (brouillon tant qu'il n'y a pas d'historique), mise à jour dans la même transaction.
    statut_id         BIGINT UNSIGNED NOT NULL,
    date_soumission   TIMESTAMP NULL,
    -- Dénormalisation maîtrisée : alertes du moteur d'analyse figées à la soumission.
    resultats_analyse JSON NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY demandes_user_id_foreign (user_id),
    KEY demandes_site_id_foreign (site_id),
    KEY demandes_type_demande_id_foreign (type_demande_id),
    KEY demandes_statut_id_foreign (statut_id),
    CONSTRAINT demandes_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT demandes_site_id_foreign FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT demandes_type_demande_id_foreign FOREIGN KEY (type_demande_id) REFERENCES type_demandes (id) ON DELETE RESTRICT,
    CONSTRAINT demandes_statut_id_foreign FOREIGN KEY (statut_id) REFERENCES statuts (id) ON DELETE RESTRICT,
    CONSTRAINT chk_demandes_dates CHECK (date_fin >= date_debut),
    CONSTRAINT chk_demandes_equipe CHECK (equipe_nombre >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pièces jointes (RG10) ──
CREATE TABLE documents (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    demande_id         BIGINT UNSIGNED NOT NULL,
    document_requis_id BIGINT UNSIGNED NULL,    -- vide si la pièce a été retirée du type depuis le dépôt
    nom_fichier        VARCHAR(255) NOT NULL,
    chemin             VARCHAR(255) NOT NULL,
    type_document      VARCHAR(50) NOT NULL,    -- dénormalisation : libellé de la pièce figé au dépôt
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY documents_demande_id_foreign (demande_id),
    KEY documents_document_requis_id_foreign (document_requis_id),
    CONSTRAINT documents_demande_id_foreign FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE CASCADE,
    CONSTRAINT documents_document_requis_id_foreign FOREIGN KEY (document_requis_id) REFERENCES documents_requis (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Historique des changements de statut (RG09) ──
CREATE TABLE historique_statuts (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    demande_id        BIGINT UNSIGNED NOT NULL,
    user_id           BIGINT UNSIGNED NULL,     -- vide si le compte de l'auteur a été supprimé
    ancien_statut_id  BIGINT UNSIGNED NULL,     -- vide pour une demande soumise directement
    nouveau_statut_id BIGINT UNSIGNED NOT NULL,
    commentaire       TEXT NULL,
    date_changement   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY historique_statuts_demande_id_foreign (demande_id),
    KEY historique_statuts_user_id_foreign (user_id),
    KEY historique_statuts_ancien_statut_id_foreign (ancien_statut_id),
    KEY historique_statuts_nouveau_statut_id_foreign (nouveau_statut_id),
    CONSTRAINT historique_statuts_demande_id_foreign FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE CASCADE,
    CONSTRAINT historique_statuts_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT historique_statuts_ancien_statut_id_foreign FOREIGN KEY (ancien_statut_id) REFERENCES statuts (id),
    CONSTRAINT historique_statuts_nouveau_statut_id_foreign FOREIGN KEY (nouveau_statut_id) REFERENCES statuts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Périodes sensibles (RG11) ──
CREATE TABLE periode_sensibles (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id            BIGINT UNSIGNED NOT NULL,
    date_debut         DATE NOT NULL,
    date_fin           DATE NOT NULL,
    description        VARCHAR(255) NOT NULL,
    niveau_restriction ENUM('bloquant', 'avertissement') NOT NULL,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY periode_sensibles_site_id_foreign (site_id),
    CONSTRAINT periode_sensibles_site_id_foreign FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
    CONSTRAINT chk_periode_sensibles_dates CHECK (date_fin >= date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications (RG12) ──
CREATE TABLE notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    demande_id BIGINT UNSIGNED NULL,
    contenu    TEXT NOT NULL,
    lu         TINYINT(1) NOT NULL DEFAULT 0,
    date_envoi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    -- Index de la cloche : notifications non lues d'un utilisateur, de la plus récente à la plus ancienne.
    -- Il sert aussi d'index à la clé étrangère user_id.
    KEY notifications_user_lu_envoi_index (user_id, lu, date_envoi),
    KEY notifications_demande_id_foreign (demande_id),
    CONSTRAINT notifications_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT notifications_demande_id_foreign FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
