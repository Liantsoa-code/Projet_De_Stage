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
-- Convention de nommage : toutes les tables portent le préfixe t_ (t_users, t_demandes…),
-- et les noms d'index et de contraintes reprennent le nom de la table (t_demandes_user_id_foreign,
-- chk_t_demandes_dates). Seules les tables métier figurent ici ; les tables techniques de
-- Laravel suivent la même convention (t_sessions, t_cache, t_cache_locks, t_jobs, t_job_batches,
-- t_failed_jobs, t_password_reset_tokens, t_migrations) et sont créées par les migrations du framework.
-- =====================================================================================

SET NAMES utf8mb4;

-- ── Utilisateurs (RG01) ──
CREATE TABLE t_users (
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
    UNIQUE KEY t_users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Aires protégées ──
CREATE TABLE t_sites (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom                  VARCHAR(150) NOT NULL,
    description          TEXT NULL,
    polygone_coordonnees JSON NULL,            -- un contour géographique = une seule valeur
    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Association GÉRER (n,m) entre validateur et site (RG04) ──
CREATE TABLE t_site_user (
    user_id BIGINT UNSIGNED NOT NULL,
    site_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, site_id),
    KEY t_site_user_site_id_foreign (site_id),
    CONSTRAINT t_site_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES t_users (id) ON DELETE CASCADE,
    CONSTRAINT t_site_user_site_id_foreign FOREIGN KEY (site_id) REFERENCES t_sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Statuts du workflow (RG07, RG08) ──
CREATE TABLE t_statuts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(50) NOT NULL,          -- brouillon, soumise, en_analyse, a_completer, validee, rejetee
    libelle     VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY t_statuts_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Types d'activité (RG05) ──
CREATE TABLE t_type_demandes (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle    VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pièces exigées par type (RG06) — 1NF : une ligne par pièce ──
CREATE TABLE t_documents_requis (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_demande_id BIGINT UNSIGNED NOT NULL,
    libelle         VARCHAR(150) NOT NULL,
    reutilisable    TINYINT(1) NOT NULL DEFAULT 0,  -- pièce reprise d'un dossier précédent du même demandeur
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY t_documents_requis_type_demande_id_libelle_unique (type_demande_id, libelle),
    CONSTRAINT t_documents_requis_type_demande_id_foreign FOREIGN KEY (type_demande_id) REFERENCES t_type_demandes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Règles du cahier des charges par type (RG06) — 1NF : une ligne par règle ──
CREATE TABLE t_regles_specifiques (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type_demande_id BIGINT UNSIGNED NOT NULL,
    libelle         VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY t_regles_specifiques_type_demande_id_foreign (type_demande_id),
    CONSTRAINT t_regles_specifiques_type_demande_id_foreign FOREIGN KEY (type_demande_id) REFERENCES t_type_demandes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Demandes d'autorisation (RG02, RG03, RG05, RG07) ──
CREATE TABLE t_demandes (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           BIGINT UNSIGNED NOT NULL,
    site_id           BIGINT UNSIGNED NOT NULL,
    type_demande_id   BIGINT UNSIGNED NOT NULL,
    description       TEXT NULL,
    date_debut        DATE NULL,                -- facultative tant que la demande est un brouillon
    date_fin          DATE NULL,
    equipe_nombre     INT NOT NULL DEFAULT 1,
    -- Dénormalisation maîtrisée : copie du dernier statut de t_historique_statuts
    -- (brouillon tant qu'il n'y a pas d'historique), mise à jour dans la même transaction.
    statut_id         BIGINT UNSIGNED NOT NULL,
    date_soumission   TIMESTAMP NULL,
    -- Dénormalisation maîtrisée : alertes du moteur d'analyse figées à la soumission.
    resultats_analyse JSON NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY t_demandes_user_id_foreign (user_id),
    KEY t_demandes_site_id_foreign (site_id),
    KEY t_demandes_type_demande_id_foreign (type_demande_id),
    KEY t_demandes_statut_id_foreign (statut_id),
    CONSTRAINT t_demandes_user_id_foreign FOREIGN KEY (user_id) REFERENCES t_users (id) ON DELETE CASCADE,
    CONSTRAINT t_demandes_site_id_foreign FOREIGN KEY (site_id) REFERENCES t_sites (id) ON DELETE RESTRICT,
    CONSTRAINT t_demandes_type_demande_id_foreign FOREIGN KEY (type_demande_id) REFERENCES t_type_demandes (id) ON DELETE RESTRICT,
    CONSTRAINT t_demandes_statut_id_foreign FOREIGN KEY (statut_id) REFERENCES t_statuts (id) ON DELETE RESTRICT,
    CONSTRAINT chk_t_demandes_dates CHECK (date_fin >= date_debut),
    CONSTRAINT chk_t_demandes_equipe CHECK (equipe_nombre >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pièces jointes (RG10) ──
CREATE TABLE t_documents (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    demande_id         BIGINT UNSIGNED NOT NULL,
    document_requis_id BIGINT UNSIGNED NULL,    -- vide si la pièce a été retirée du type depuis le dépôt
    nom_fichier        VARCHAR(255) NOT NULL,
    chemin             VARCHAR(255) NOT NULL,
    type_document      VARCHAR(50) NOT NULL,    -- dénormalisation : libellé de la pièce figé au dépôt
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY t_documents_demande_id_foreign (demande_id),
    KEY t_documents_document_requis_id_foreign (document_requis_id),
    CONSTRAINT t_documents_demande_id_foreign FOREIGN KEY (demande_id) REFERENCES t_demandes (id) ON DELETE CASCADE,
    CONSTRAINT t_documents_document_requis_id_foreign FOREIGN KEY (document_requis_id) REFERENCES t_documents_requis (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Historique des changements de statut (RG09) ──
CREATE TABLE t_historique_statuts (
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
    KEY t_historique_statuts_demande_id_foreign (demande_id),
    KEY t_historique_statuts_user_id_foreign (user_id),
    KEY t_historique_statuts_ancien_statut_id_foreign (ancien_statut_id),
    KEY t_historique_statuts_nouveau_statut_id_foreign (nouveau_statut_id),
    CONSTRAINT t_historique_statuts_demande_id_foreign FOREIGN KEY (demande_id) REFERENCES t_demandes (id) ON DELETE CASCADE,
    CONSTRAINT t_historique_statuts_user_id_foreign FOREIGN KEY (user_id) REFERENCES t_users (id) ON DELETE SET NULL,
    CONSTRAINT t_historique_statuts_ancien_statut_id_foreign FOREIGN KEY (ancien_statut_id) REFERENCES t_statuts (id),
    CONSTRAINT t_historique_statuts_nouveau_statut_id_foreign FOREIGN KEY (nouveau_statut_id) REFERENCES t_statuts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Périodes sensibles (RG11) ──
CREATE TABLE t_periode_sensibles (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id            BIGINT UNSIGNED NOT NULL,
    date_debut         DATE NOT NULL,
    date_fin           DATE NOT NULL,
    description        VARCHAR(255) NOT NULL,
    niveau_restriction ENUM('bloquant', 'avertissement') NOT NULL,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY t_periode_sensibles_site_id_foreign (site_id),
    CONSTRAINT t_periode_sensibles_site_id_foreign FOREIGN KEY (site_id) REFERENCES t_sites (id) ON DELETE CASCADE,
    CONSTRAINT chk_t_periode_sensibles_dates CHECK (date_fin >= date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications (RG12) ──
CREATE TABLE t_notifications (
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
    KEY t_notifications_user_lu_envoi_index (user_id, lu, date_envoi),
    KEY t_notifications_demande_id_foreign (demande_id),
    CONSTRAINT t_notifications_user_id_foreign FOREIGN KEY (user_id) REFERENCES t_users (id) ON DELETE CASCADE,
    CONSTRAINT t_notifications_demande_id_foreign FOREIGN KEY (demande_id) REFERENCES t_demandes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Documents officiels préparés avant la validation (autorisation, cahier des charges) ──
-- Seuls les textes modifiables sont stockés ; les données du dossier sont lues sur la demande.
CREATE TABLE t_documents_officiels (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    demande_id BIGINT UNSIGNED NOT NULL,
    type       VARCHAR(30) NOT NULL,            -- autorisation, cahier_charges
    contenu    JSON NOT NULL,                   -- textes relus par le validateur
    genere_par BIGINT UNSIGNED NULL,            -- vide si le compte de l'auteur a été supprimé
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    -- Un document de chaque type par demande ; l'index sert aussi à la clé étrangère demande_id.
    UNIQUE KEY t_documents_officiels_demande_id_type_unique (demande_id, type),
    KEY t_documents_officiels_genere_par_foreign (genere_par),
    CONSTRAINT t_documents_officiels_demande_id_foreign FOREIGN KEY (demande_id) REFERENCES t_demandes (id) ON DELETE CASCADE,
    CONSTRAINT t_documents_officiels_genere_par_foreign FOREIGN KEY (genere_par) REFERENCES t_users (id) ON DELETE SET NULL,
    CONSTRAINT chk_t_documents_officiels_type CHECK (type IN ('autorisation', 'cahier_charges'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Documents déposés par l'équipe sur la page Références ──
CREATE TABLE t_documents_reference (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titre       VARCHAR(255) NOT NULL,
    description TEXT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin      VARCHAR(255) NOT NULL,          -- disque privé : storage/app/private/references
    type_mime   VARCHAR(100) NOT NULL,
    taille      INT UNSIGNED NOT NULL,          -- en octets
    user_id     BIGINT UNSIGNED NULL,           -- vide si le compte de l'auteur a été supprimé
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY t_documents_reference_user_id_foreign (user_id),
    CONSTRAINT t_documents_reference_user_id_foreign FOREIGN KEY (user_id) REFERENCES t_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Modèles des documents officiels de la page Références (gérés par les admins) ──
-- Les champs à compléter sont les textes entre crochets du contenu : ils ne sont pas stockés à part.
CREATE TABLE t_modeles_reference (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titre       VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    `usage`     TEXT NULL,
    icone       VARCHAR(40) NOT NULL,
    automatique TINYINT(1) NOT NULL DEFAULT 0,  -- document produit par la plateforme à la validation
    contenu     TEXT NOT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY t_modeles_reference_titre_unique (titre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Référentiel des statuts (inséré par la migration de t_statuts) ──
INSERT INTO t_statuts (code, libelle, description, created_at, updated_at) VALUES
('brouillon', 'Brouillon', 'Demande en cours de rédaction', NOW(), NOW()),
('soumise', 'Soumise', 'Demande envoyée pour validation', NOW(), NOW()),
('en_analyse', 'En cours d''analyse', 'Le validateur étudie la demande', NOW(), NOW()),
('validee', 'Validée', 'Demande acceptée', NOW(), NOW()),
('rejetee', 'Rejetée', 'Demande refusée', NOW(), NOW()),
('a_completer', 'À compléter', 'Des compléments sont demandés au demandeur', NOW(), NOW());
