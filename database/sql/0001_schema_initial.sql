-- Fichier GÉNÉRÉ par « php artisan makary:schema » depuis specs/donnees/migrations/0001_schema_initial.sql.
-- Ne pas modifier à la main : modifier la spécification, puis régénérer.
-- =====================================================================================
-- Plateforme numérique de la Commune de Makary — schéma initial
-- Migration 0001 · ticket MKY-P1-04 · réécrite pour MySQL/MariaDB (MKY-P3-05, décision D13) · PROPOSITION
--
-- Moteurs : MariaDB 10.4 ou plus récent ; MySQL 8.0.16 ou plus récent (contraintes CHECK appliquées).
-- Le fichier s'exécute tel quel avec le client mysql (blocs délimités par DELIMITER).
--
-- Principes :
--   * Le portail public ne lit que des PUBLICATIONS (instantanés validés), jamais les brouillons.
--   * Toute publication renvoie à une validation approuvée par une autre personne que l'auteur.
--   * Les règles du §8 du cahier des charges sont imposées ici (contraintes CHECK, déclencheurs), pas seulement dans l'interface.
--   * Toute donnée personnelle porte une date de fin de conservation (exigence E-04).
--   * L'utilisateur courant est transmis par l'application : SET @app_utilisateur_id = <id>.
--   * Énumérations : colonnes VARCHAR avec une contrainte CHECK nommée chk_<table>_<colonne> (portable, compatible
--     avec les clés étrangères). Identifiants textuels (slug, code, numéro, devise) en collation binaire :
--     comparaisons et motifs REGEXP sensibles à la casse.
--   * Dates et heures en DATETIME, à l'heure de Douala (UTC+1, sans heure d'été) : session en time_zone '+01:00'.
-- =====================================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Les variables et paramètres des procédures et déclencheurs prennent la collation par défaut de la base : elle est fixée
-- ici, sinon une base créée avec une autre collation (utf8mb4_general_ci, utf8mb4_0900_ai_ci…) ferait échouer leurs comparaisons.
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 1. Énumérations (valeurs de référence des contraintes CHECK)
-- -------------------------------------------------------------------------------------
--   etat_editorial           : brouillon, en_revue, approuve, publie, retire
--   nature_information       : verifie, estimation, a_confirmer
--   statut_projet            : idee_identifiee, etude_en_cours, dossier_en_preparation, pret_a_etre_soumis,
--                              financement_recherche, finance, en_execution, acheve, suspendu
--   statut_ligne_financement : recherche, sollicite, acquis
--   droit_usage              : propriete_commune, licence, autorisation_ecrite, a_verifier
--   droit_diffusion          : public, sur_demande, interne
--   decision_validation      : en_attente, approuve, renvoye
--   type_objet               : contenu, projet, document, elu, partenaire, localite, equipement, media
--   type_demande             : contact, investisseur, partenaire, diaspora, autre
--   statut_demande           : recue, qualifiee, affectee, en_traitement, repondue, cloturee, rejetee
--   base_legale              : consentement, mission_interet_public, obligation_legale

-- -------------------------------------------------------------------------------------
-- 2. Comptes, rôles, journal : créés par les migrations Laravel (MKY-P2-03)
-- -------------------------------------------------------------------------------------
-- 3. Fichiers, sources et médias
-- -------------------------------------------------------------------------------------
CREATE TABLE fichier (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chemin_stockage  VARCHAR(500) COLLATE utf8mb4_bin NOT NULL UNIQUE,   -- clé dans le stockage ; privé par défaut
    nom_original     VARCHAR(255) NOT NULL,
    type_mime        VARCHAR(120) NOT NULL,
    taille_octets    BIGINT UNSIGNED NOT NULL,
    empreinte_sha256 CHAR(64) COLLATE utf8mb4_bin NOT NULL,
    prive            BOOLEAN NOT NULL DEFAULT true,
    analyse_ok       BOOLEAN,                          -- résultat de l'analyse antivirus ; NULL = non analysé
    televerse_par    BIGINT UNSIGNED,
    televerse_le     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_fichier_taille CHECK (taille_octets > 0),
    CONSTRAINT chk_fichier_empreinte CHECK (empreinte_sha256 REGEXP '^[0-9a-f]{64}$'),
    FOREIGN KEY (televerse_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Toute information chiffrée ou factuelle renvoie à une source (§8 : « source des chiffres »).
CREATE TABLE source (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_source  VARCHAR(30) NOT NULL,
    intitule     VARCHAR(300) NOT NULL,
    reference    VARCHAR(160),
    date_source  DATE NOT NULL,
    fichier_id   BIGINT UNSIGNED,
    url          VARCHAR(500),
    cree_par     BIGINT UNSIGNED,
    cree_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_source_type_source CHECK (type_source IN
        ('piece_officielle', 'convention', 'devis', 'proces_verbal', 'recensement', 'estimation_interne', 'etude', 'presse', 'autre')),
    FOREIGN KEY (fichier_id) REFERENCES fichier (id),
    FOREIGN KEY (cree_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fichier_id        BIGINT UNSIGNED NOT NULL,
    titre             VARCHAR(255) NOT NULL,
    texte_alternatif  VARCHAR(500) NOT NULL,
    auteur            VARCHAR(160) NOT NULL,
    date_prise        DATE,
    lieu              VARCHAR(160),
    droit             VARCHAR(30) NOT NULL DEFAULT 'a_verifier',
    justificatif_id   BIGINT UNSIGNED,
    CONSTRAINT chk_media_texte_alternatif CHECK (CHAR_LENGTH(TRIM(texte_alternatif)) > 0),
    CONSTRAINT chk_media_droit CHECK (droit IN ('propriete_commune', 'licence', 'autorisation_ecrite', 'a_verifier')),
    CONSTRAINT chk_media_justificatif CHECK (droit IN ('a_verifier', 'propriete_commune') OR justificatif_id IS NOT NULL),
    FOREIGN KEY (fichier_id) REFERENCES fichier (id),
    FOREIGN KEY (justificatif_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 4. Validation et publication (circuit éditorial commun, §7)
-- -------------------------------------------------------------------------------------
CREATE TABLE validation_demande (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    objet_type  VARCHAR(20) NOT NULL,
    objet_id    BIGINT UNSIGNED NOT NULL,
    soumis_par  BIGINT UNSIGNED NOT NULL,
    soumis_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decision    VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    decide_par  BIGINT UNSIGNED,
    decide_le   DATETIME,
    motif       TEXT,
    CONSTRAINT chk_validation_demande_objet_type CHECK (objet_type IN ('contenu', 'projet', 'document', 'elu', 'partenaire', 'localite', 'equipement', 'media')),
    CONSTRAINT chk_validation_demande_decision CHECK (decision IN ('en_attente', 'approuve', 'renvoye')),
    CONSTRAINT chk_validation_separation CHECK (decide_par IS NULL OR decide_par <> soumis_par),   -- séparation des tâches
    CONSTRAINT chk_validation_coherence CHECK ((decision = 'en_attente') = (decide_par IS NULL AND decide_le IS NULL)),
    CONSTRAINT chk_validation_motif CHECK (decision <> 'renvoye' OR motif IS NOT NULL),
    FOREIGN KEY (soumis_par) REFERENCES utilisateur (id),
    FOREIGN KEY (decide_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE publication (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    objet_type     VARCHAR(20) NOT NULL,
    objet_id       BIGINT UNSIGNED NOT NULL,
    version        INT UNSIGNED NOT NULL,
    instantane     JSON NOT NULL,                      -- champs publics uniquement, figés à l'approbation
    validation_id  BIGINT UNSIGNED NOT NULL,
    publie_par     BIGINT UNSIGNED NOT NULL,
    publie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retire_le      DATETIME,
    motif_retrait  TEXT,
    UNIQUE KEY publication_version (objet_type, objet_id, version),
    KEY publication_courante (objet_type, objet_id, retire_le),
    CONSTRAINT chk_publication_objet_type CHECK (objet_type IN ('contenu', 'projet', 'document', 'elu', 'partenaire', 'localite', 'equipement', 'media')),
    CONSTRAINT chk_publication_version CHECK (version > 0),
    CONSTRAINT chk_publication_retrait CHECK (retire_le IS NULL OR motif_retrait IS NOT NULL),
    FOREIGN KEY (validation_id) REFERENCES validation_demande (id),
    FOREIGN KEY (publie_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique des modifications (§8) : alimenté par déclencheur sur les tables éditoriales (section 12).
CREATE TABLE historique_modification (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_table       VARCHAR(60) NOT NULL,
    ligne_id        BIGINT UNSIGNED NOT NULL,
    operation       VARCHAR(10) NOT NULL,
    ancien          JSON,
    nouveau         JSON,
    utilisateur_id  BIGINT UNSIGNED,
    horodatage      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY historique_ligne (nom_table, ligne_id),
    CONSTRAINT chk_historique_modification_operation CHECK (operation IN ('INSERT', 'UPDATE', 'DELETE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 5. Référentiels territoriaux (M01, M09)
-- -------------------------------------------------------------------------------------
CREATE TABLE secteur (
    code     VARCHAR(40) COLLATE utf8mb4_bin PRIMARY KEY,
    libelle  VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO secteur (code, libelle) VALUES
    ('eau', 'Eau et assainissement'), ('education', 'Éducation'), ('sante', 'Santé'),
    ('voirie', 'Voirie et transport'), ('hygiene', 'Hygiène et salubrité'), ('agriculture', 'Agriculture'),
    ('elevage_peche', 'Élevage et pêche'), ('commerce', 'Commerce et marchés'),
    ('culture_tourisme', 'Culture et tourisme'), ('environnement', 'Environnement'),
    ('administration', 'Administration communale');

-- Liste officielle des localités (C-013) : chaque ligne est validée par le responsable de validation.
CREATE TABLE localite (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                VARCHAR(160) NOT NULL,
    slug               VARCHAR(160) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    categorie          VARCHAR(20) NOT NULL,
    latitude           DECIMAL(9, 6),
    longitude          DECIMAL(9, 6),
    coordonnees_source BIGINT UNSIGNED,
    source_id          BIGINT UNSIGNED NOT NULL,                       -- source de l'existence et du nom
    etat               VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    CONSTRAINT chk_localite_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT chk_localite_categorie CHECK (categorie IN ('espace_urbain', 'village', 'quartier', 'autre')),
    CONSTRAINT chk_localite_latitude CHECK (latitude BETWEEN -90 AND 90),
    CONSTRAINT chk_localite_longitude CHECK (longitude BETWEEN -180 AND 180),
    CONSTRAINT chk_localite_etat CHECK (etat IN ('brouillon', 'en_revue', 'approuve', 'publie', 'retire')),
    CONSTRAINT chk_localite_coordonnees CHECK ((latitude IS NULL) = (longitude IS NULL)),
    CONSTRAINT chk_localite_coordonnees_source CHECK (latitude IS NULL OR coordonnees_source IS NOT NULL),
    FOREIGN KEY (coordonnees_source) REFERENCES source (id),
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE equipement (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_equipement    VARCHAR(30) NOT NULL,
    nom                VARCHAR(160) NOT NULL,
    localite_id        BIGINT UNSIGNED NOT NULL,
    latitude           DECIMAL(9, 6),
    longitude          DECIMAL(9, 6),
    source_id          BIGINT UNSIGNED NOT NULL,
    etat               VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    CONSTRAINT chk_equipement_type_equipement CHECK (type_equipement IN
        ('ecole', 'centre_sante', 'marche', 'forage', 'puits', 'route', 'batiment_public', 'autre')),
    CONSTRAINT chk_equipement_etat CHECK (etat IN ('brouillon', 'en_revue', 'approuve', 'publie', 'retire')),
    CONSTRAINT chk_equipement_coordonnees CHECK ((latitude IS NULL) = (longitude IS NULL)),
    FOREIGN KEY (localite_id) REFERENCES localite (id),
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 6. Contenus éditoriaux (M01, M02, M06, M08, M10, M11 pages)
-- -------------------------------------------------------------------------------------
CREATE TABLE contenu (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_contenu    VARCHAR(20) NOT NULL,
    module          VARCHAR(3) COLLATE utf8mb4_bin NOT NULL,
    slug            VARCHAR(160) COLLATE utf8mb4_bin NOT NULL,
    titre           VARCHAR(160) NOT NULL,
    resume          TEXT,
    corps           MEDIUMTEXT,                        -- Markdown ; rendu HTML assaini par l'application
    attributs       JSON NOT NULL DEFAULT ('{}'),      -- propres au type (dates d'événement, lieu, pièces d'une démarche…)
    localite_id     BIGINT UNSIGNED,
    image_id        BIGINT UNSIGNED,
    auteur_id       BIGINT UNSIGNED NOT NULL,
    etat            VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    publication_programmee DATETIME,
    cree_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY contenu_adresse (type_contenu, slug),
    CONSTRAINT chk_contenu_type_contenu CHECK (type_contenu IN ('page', 'actualite', 'evenement', 'site_patrimoine', 'service', 'demarche', 'opportunite')),
    CONSTRAINT chk_contenu_module CHECK (module REGEXP '^M(0[1-9]|1[0-2])$'),
    CONSTRAINT chk_contenu_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT chk_contenu_resume CHECK (CHAR_LENGTH(resume) <= 500),
    CONSTRAINT chk_contenu_etat CHECK (etat IN ('brouillon', 'en_revue', 'approuve', 'publie', 'retire')),
    FOREIGN KEY (localite_id) REFERENCES localite (id),
    FOREIGN KEY (image_id) REFERENCES media (id),
    FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Élus et anciens élus (M02) : données personnelles de personnes publiques, publiées dans leur fonction.
CREATE TABLE elu (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_affiche        VARCHAR(160) NOT NULL,
    fonction           VARCHAR(160) NOT NULL,
    organe             VARCHAR(30) NOT NULL,
    mandat_debut       DATE NOT NULL,
    mandat_fin         DATE,
    biographie         TEXT,
    photo_id           BIGINT UNSIGNED,
    source_id          BIGINT UNSIGNED NOT NULL,                       -- acte de désignation ou d'élection
    etat               VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    motif_conservation VARCHAR(40) NOT NULL DEFAULT 'archive_publique',
    conserver_jusqu_au DATE,                           -- NULL autorisé seulement au titre des archives publiques (E-14)
    CONSTRAINT chk_elu_organe CHECK (organe IN ('maire', 'executif', 'conseil_municipal', 'ancien_maire', 'ancien_executif')),
    CONSTRAINT chk_elu_etat CHECK (etat IN ('brouillon', 'en_revue', 'approuve', 'publie', 'retire')),
    CONSTRAINT chk_elu_mandat CHECK (mandat_fin IS NULL OR mandat_fin >= mandat_debut),
    CONSTRAINT chk_elu_conservation CHECK (motif_conservation = 'archive_publique' OR conserver_jusqu_au IS NOT NULL),
    FOREIGN KEY (photo_id) REFERENCES media (id),
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 7. Centre documentaire (M07)
-- -------------------------------------------------------------------------------------
CREATE TABLE categorie_document (
    code     VARCHAR(40) COLLATE utf8mb4_bin PRIMARY KEY,
    libelle  VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO categorie_document (code, libelle) VALUES
    ('pcd', 'Plan communal de développement'), ('budget', 'Budgets et comptes'),
    ('deliberation', 'Délibérations et arrêtés'), ('rapport', 'Rapports'), ('etude', 'Études'),
    ('etat_civil', 'État civil'), ('urbanisme', 'Urbanisme'), ('appel_offres', 'Appels d''offres'),
    ('texte_reference', 'Textes de référence');

CREATE TABLE document (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug                  VARCHAR(160) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    titre                 VARCHAR(255) NOT NULL,
    categorie             VARCHAR(40) COLLATE utf8mb4_bin NOT NULL,
    resume                TEXT,
    date_document         DATE NOT NULL,
    reference_officielle  VARCHAR(160),
    diffusion             VARCHAR(20) NOT NULL DEFAULT 'interne',
    justificatif_diffusion BIGINT UNSIGNED,                            -- accord du référent juridique
    etat                  VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    archive               BOOLEAN NOT NULL DEFAULT false,              -- un document retiré est archivé, pas détruit (E-14)
    CONSTRAINT chk_document_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT chk_document_diffusion CHECK (diffusion IN ('public', 'sur_demande', 'interne')),
    CONSTRAINT chk_document_etat CHECK (etat IN ('brouillon', 'en_revue', 'approuve', 'publie', 'retire')),
    CONSTRAINT chk_document_accord CHECK (diffusion <> 'public' OR justificatif_diffusion IS NOT NULL),
    FOREIGN KEY (categorie) REFERENCES categorie_document (code),
    FOREIGN KEY (justificatif_diffusion) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_version (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id  BIGINT UNSIGNED NOT NULL,
    numero       INT UNSIGNED NOT NULL,
    fichier_id   BIGINT UNSIGNED NOT NULL,
    note         TEXT,
    cree_par     BIGINT UNSIGNED,
    cree_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY document_version_numero (document_id, numero),
    CONSTRAINT chk_document_version_numero CHECK (numero > 0),
    FOREIGN KEY (document_id) REFERENCES document (id),
    FOREIGN KEY (fichier_id) REFERENCES fichier (id),
    FOREIGN KEY (cree_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 8. Partenaires (M05)
-- -------------------------------------------------------------------------------------
CREATE TABLE partenaire (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(255) NOT NULL UNIQUE,
    type_partenaire     VARCHAR(30) NOT NULL,
    pays                VARCHAR(80),
    site_web            VARCHAR(500),
    accepte_citation    BOOLEAN NOT NULL DEFAULT false,
    accord_citation_id  BIGINT UNSIGNED,
    logo_id             BIGINT UNSIGNED,
    etat                VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    CONSTRAINT chk_partenaire_type_partenaire CHECK (type_partenaire IN
        ('bailleur_bilateral', 'bailleur_multilateral', 'programme_national', 'ong', 'fondation',
         'entreprise', 'collectivite', 'association_diaspora', 'autre')),
    CONSTRAINT chk_partenaire_etat CHECK (etat IN ('brouillon', 'en_revue', 'approuve', 'publie', 'retire')),
    CONSTRAINT chk_partenaire_citation CHECK (NOT accepte_citation OR accord_citation_id IS NOT NULL),
    FOREIGN KEY (accord_citation_id) REFERENCES source (id),
    FOREIGN KEY (logo_id) REFERENCES media (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 9. Projets et réalisations (M03, M05) — §8
-- -------------------------------------------------------------------------------------
-- Compteur des identifiants PRJ-AAAA-NNN (attribués par déclencheur, sous verrou de ligne).
CREATE TABLE projet_numero (
    id      TINYINT UNSIGNED PRIMARY KEY,
    valeur  INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
INSERT INTO projet_numero (id, valeur) VALUES (1, 0);

CREATE TABLE projet (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                   VARCHAR(20) COLLATE utf8mb4_bin NOT NULL DEFAULT '' UNIQUE,   -- attribué par le déclencheur projet_creation
    slug                   VARCHAR(160) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    titre                  VARCHAR(120) NOT NULL,
    secteur                VARCHAR(40) COLLATE utf8mb4_bin NOT NULL,
    resume                 TEXT,
    description_technique  MEDIUMTEXT,
    description_publique   BOOLEAN NOT NULL DEFAULT false,
    besoin                 TEXT,
    besoin_source_id       BIGINT UNSIGNED,
    statut                 VARCHAR(30) NOT NULL DEFAULT 'idee_identifiee',
    statut_mis_a_jour_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motif_suspension       TEXT,
    avancement_pct         DECIMAL(5, 2),
    avancement_source_id   BIGINT UNSIGNED,
    avancement_date        DATE,
    service_responsable    VARCHAR(160),
    responsable_id         BIGINT UNSIGNED,
    visible_catalogue      BOOLEAN NOT NULL DEFAULT true,
    motif_retrait          TEXT,
    etat                   VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    cree_le                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_projet_code CHECK (code REGEXP '^PRJ-[0-9]{4}-[0-9]{3,}$'),
    CONSTRAINT chk_projet_slug CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT chk_projet_resume CHECK (CHAR_LENGTH(resume) <= 500),
    CONSTRAINT chk_projet_statut CHECK (statut IN ('idee_identifiee', 'etude_en_cours', 'dossier_en_preparation', 'pret_a_etre_soumis',
        'financement_recherche', 'finance', 'en_execution', 'acheve', 'suspendu')),
    CONSTRAINT chk_projet_etat CHECK (etat IN ('brouillon', 'en_revue', 'approuve', 'publie', 'retire')),
    CONSTRAINT chk_projet_avancement_borne CHECK (avancement_pct BETWEEN 0 AND 100),
    CONSTRAINT chk_projet_besoin_source CHECK (besoin IS NULL OR besoin_source_id IS NOT NULL),
    CONSTRAINT chk_projet_motif_suspension CHECK (statut <> 'suspendu' OR motif_suspension IS NOT NULL),
    CONSTRAINT chk_projet_avancement_source CHECK (avancement_pct IS NULL OR (avancement_source_id IS NOT NULL AND avancement_date IS NOT NULL)),
    CONSTRAINT chk_projet_avancement_statut CHECK (avancement_pct IS NULL OR statut IN ('en_execution', 'acheve', 'suspendu')),
    CONSTRAINT chk_projet_retrait CHECK (visible_catalogue OR motif_retrait IS NOT NULL),          -- retrait du catalogue sans suppression
    FOREIGN KEY (secteur) REFERENCES secteur (code),
    FOREIGN KEY (besoin_source_id) REFERENCES source (id),
    FOREIGN KEY (avancement_source_id) REFERENCES source (id),
    FOREIGN KEY (responsable_id) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projet_localite (
    projet_id    BIGINT UNSIGNED NOT NULL,
    localite_id  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (projet_id, localite_id),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (localite_id) REFERENCES localite (id)
) ENGINE=InnoDB;

-- Coordonnées géographiques, seulement si vérifiées (§8). GeoJSON.
CREATE TABLE projet_geometrie (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id     BIGINT UNSIGNED NOT NULL,
    geojson       JSON NOT NULL,
    verifie_par   BIGINT UNSIGNED NOT NULL,
    verifie_le    DATE NOT NULL,
    source_id     BIGINT UNSIGNED NOT NULL,
    CONSTRAINT chk_projet_geometrie_type CHECK (JSON_UNQUOTE(JSON_EXTRACT(geojson, '$.type')) IN ('Point', 'LineString', 'Polygon', 'MultiPolygon')),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (verifie_par) REFERENCES utilisateur (id),
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projet_beneficiaires (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id          BIGINT UNSIGNED NOT NULL,
    nombre             INT UNSIGNED NOT NULL,
    categorie          VARCHAR(80) NOT NULL,
    methode_estimation VARCHAR(500) NOT NULL,
    date_estimation    DATE NOT NULL,
    nature             VARCHAR(20) NOT NULL,
    source_id          BIGINT UNSIGNED NOT NULL,
    CONSTRAINT chk_projet_beneficiaires_nombre CHECK (nombre > 0),
    CONSTRAINT chk_projet_beneficiaires_methode CHECK (CHAR_LENGTH(TRIM(methode_estimation)) > 0),
    CONSTRAINT chk_projet_beneficiaires_nature CHECK (nature IN ('verifie', 'estimation', 'a_confirmer')),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coût : montant, devise, date et source obligatoires (§8).
CREATE TABLE projet_estimation_cout (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id        BIGINT UNSIGNED NOT NULL,
    montant          DECIMAL(18, 2) NOT NULL,
    devise           CHAR(3) COLLATE utf8mb4_bin NOT NULL,
    date_estimation  DATE NOT NULL,
    nature_cout      VARCHAR(20) NOT NULL,
    nature           VARCHAR(20) NOT NULL,
    source_id        BIGINT UNSIGNED NOT NULL,
    CONSTRAINT chk_projet_estimation_cout_montant CHECK (montant > 0),
    CONSTRAINT chk_projet_estimation_cout_devise CHECK (devise REGEXP '^[A-Z]{3}$'),
    CONSTRAINT chk_projet_estimation_cout_nature_cout CHECK (nature_cout IN ('estimation', 'devis', 'marche', 'cout_final')),
    CONSTRAINT chk_projet_estimation_cout_nature CHECK (nature IN ('verifie', 'estimation', 'a_confirmer')),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financement par lignes : seul l'« acquis » justifié et validé compte comme acquis (§8).
CREATE TABLE projet_financement (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id         BIGINT UNSIGNED NOT NULL,
    statut_ligne      VARCHAR(20) NOT NULL,
    partenaire_id     BIGINT UNSIGNED,
    montant           DECIMAL(18, 2) NOT NULL,
    devise            CHAR(3) COLLATE utf8mb4_bin NOT NULL,
    date_information  DATE NOT NULL,
    source_id         BIGINT UNSIGNED NOT NULL,
    piece_id          BIGINT UNSIGNED,                 -- convention ou engagement signé
    valide_par        BIGINT UNSIGNED,
    valide_le         DATETIME,
    CONSTRAINT chk_projet_financement_statut_ligne CHECK (statut_ligne IN ('recherche', 'sollicite', 'acquis')),
    CONSTRAINT chk_projet_financement_montant CHECK (montant > 0),
    CONSTRAINT chk_projet_financement_devise CHECK (devise REGEXP '^[A-Z]{3}$'),
    CONSTRAINT chk_projet_financement_financeur CHECK (statut_ligne = 'recherche' OR partenaire_id IS NOT NULL),
    CONSTRAINT chk_projet_financement_acquis CHECK (statut_ligne <> 'acquis' OR (piece_id IS NOT NULL AND valide_par IS NOT NULL AND valide_le IS NOT NULL)),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (partenaire_id) REFERENCES partenaire (id),
    FOREIGN KEY (source_id) REFERENCES source (id),
    FOREIGN KEY (piece_id) REFERENCES source (id),
    FOREIGN KEY (valide_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projet_piece (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id   BIGINT UNSIGNED NOT NULL,
    type_piece  VARCHAR(30) NOT NULL,
    source_id   BIGINT UNSIGNED NOT NULL,
    diffusion   VARCHAR(20) NOT NULL DEFAULT 'interne',
    CONSTRAINT chk_projet_piece_type_piece CHECK (type_piece IN
        ('etude_faisabilite', 'etude_impact', 'autorisation', 'permis', 'convention',
         'ordre_service', 'pv_reception', 'attestation_achevement', 'rapport', 'autre')),
    CONSTRAINT chk_projet_piece_diffusion CHECK (diffusion IN ('public', 'sur_demande', 'interne')),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projet_indicateur (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id         BIGINT UNSIGNED NOT NULL,
    libelle           VARCHAR(160) NOT NULL,
    unite             VARCHAR(40) NOT NULL,
    valeur_reference  DECIMAL(18, 4),
    cible             DECIMAL(18, 4) NOT NULL,
    valeur_atteinte   DECIMAL(18, 4),
    date_mesure       DATE,
    methode_mesure    VARCHAR(500) NOT NULL,
    source_id         BIGINT UNSIGNED,
    CONSTRAINT chk_projet_indicateur_methode CHECK (CHAR_LENGTH(TRIM(methode_mesure)) > 0),
    CONSTRAINT chk_projet_indicateur_mesure CHECK (valeur_atteinte IS NULL OR (source_id IS NOT NULL AND date_mesure IS NOT NULL)),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES source (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projet_media (
    projet_id  BIGINT UNSIGNED NOT NULL,
    media_id   BIGINT UNSIGNED NOT NULL,
    ordre      INT NOT NULL DEFAULT 0,
    PRIMARY KEY (projet_id, media_id),
    FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media (id)
) ENGINE=InnoDB;

-- Transitions de statut autorisées (fiche projet, §3) et périmètre du validateur :
--   'projets' = référent des projets et financements (RPF) ; 'realisations' = référent des réalisations (RR).
--   Le validateur doit détenir le rôle « validateur » avec ce périmètre (utilisateur_role.perimetre).
CREATE TABLE transition_autorisee (
    de_statut        VARCHAR(30) NOT NULL,
    vers_statut      VARCHAR(30) NOT NULL,
    perimetre        VARCHAR(20) NOT NULL,             -- RPF ou RR (§5.2)
    piece_requise    VARCHAR(30),                      -- type de pièce exigé (projet_piece.type_piece), ou NULL
    PRIMARY KEY (de_statut, vers_statut),
    CONSTRAINT chk_transition_autorisee_de_statut CHECK (de_statut IN
        ('idee_identifiee', 'etude_en_cours', 'dossier_en_preparation', 'pret_a_etre_soumis',
        'financement_recherche', 'finance', 'en_execution', 'acheve', 'suspendu')),
    CONSTRAINT chk_transition_autorisee_vers_statut CHECK (vers_statut IN
        ('idee_identifiee', 'etude_en_cours', 'dossier_en_preparation', 'pret_a_etre_soumis',
        'financement_recherche', 'finance', 'en_execution', 'acheve', 'suspendu')),
    CONSTRAINT chk_transition_autorisee_perimetre CHECK (perimetre IN ('projets', 'realisations'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO transition_autorisee (de_statut, vers_statut, perimetre, piece_requise) VALUES
    ('idee_identifiee',        'etude_en_cours',         'projets',    NULL),
    ('etude_en_cours',         'dossier_en_preparation', 'projets',    NULL),
    ('dossier_en_preparation', 'pret_a_etre_soumis',     'projets',    NULL),
    ('pret_a_etre_soumis',     'financement_recherche',  'projets',    NULL),
    ('pret_a_etre_soumis',     'finance',                'projets',    'convention'),
    ('financement_recherche',  'finance',                'projets',    'convention'),
    ('finance',                'en_execution',           'realisations', 'ordre_service'),
    ('en_execution',           'acheve',                 'realisations', 'pv_reception'),
    ('etude_en_cours',         'idee_identifiee',        'projets',    NULL),
    ('dossier_en_preparation', 'etude_en_cours',         'projets',    NULL),
    ('pret_a_etre_soumis',     'dossier_en_preparation', 'projets',    NULL),
    ('financement_recherche',  'dossier_en_preparation', 'projets',    NULL),
    ('idee_identifiee',        'suspendu',               'projets',    NULL),
    ('etude_en_cours',         'suspendu',               'projets',    NULL),
    ('dossier_en_preparation', 'suspendu',               'projets',    NULL),
    ('pret_a_etre_soumis',     'suspendu',               'projets',    NULL),
    ('financement_recherche',  'suspendu',               'projets',    NULL),
    ('finance',                'suspendu',               'realisations', NULL),
    ('en_execution',           'suspendu',               'realisations', NULL),
    ('suspendu',               'idee_identifiee',        'projets',    NULL),
    ('suspendu',               'dossier_en_preparation', 'projets',    NULL),
    ('suspendu',               'financement_recherche',  'projets',    NULL),
    ('suspendu',               'en_execution',           'realisations', NULL);

CREATE TABLE projet_transition (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    projet_id    BIGINT UNSIGNED NOT NULL,
    de_statut    VARCHAR(30) NOT NULL,
    vers_statut  VARCHAR(30) NOT NULL,
    demande_par  BIGINT UNSIGNED NOT NULL,
    demande_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motif        TEXT,
    piece_id     BIGINT UNSIGNED,
    decision     VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valide_par   BIGINT UNSIGNED,
    valide_le    DATETIME,
    applique_le  DATETIME,
    CONSTRAINT chk_projet_transition_decision CHECK (decision IN ('en_attente', 'approuve', 'renvoye')),
    CONSTRAINT chk_projet_transition_separation CHECK (valide_par IS NULL OR valide_par <> demande_par),   -- nul ne valide sa propre demande
    CONSTRAINT chk_projet_transition_coherence CHECK ((decision = 'en_attente') = (valide_par IS NULL)),
    CONSTRAINT chk_projet_transition_motif CHECK (vers_statut <> 'suspendu' OR motif IS NOT NULL),
    CONSTRAINT chk_projet_transition_application CHECK (applique_le IS NULL OR decision = 'approuve'),
    FOREIGN KEY (projet_id) REFERENCES projet (id),
    FOREIGN KEY (de_statut, vers_statut) REFERENCES transition_autorisee (de_statut, vers_statut),
    FOREIGN KEY (demande_par) REFERENCES utilisateur (id),
    FOREIGN KEY (piece_id) REFERENCES projet_piece (id),
    FOREIGN KEY (valide_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
-- Les procédures survivent à la suppression des tables (migrate:fresh) : on les recrée.
DROP PROCEDURE IF EXISTS controler_transition$$
-- Pièce exigée par la transition, du bon type et du même projet ; validateur du bon périmètre ; financement acquis.
CREATE PROCEDURE controler_transition(IN p_projet BIGINT UNSIGNED, IN p_de VARCHAR(30), IN p_vers VARCHAR(30),
                                      IN p_piece BIGINT UNSIGNED, IN p_decision VARCHAR(20), IN p_valide_par BIGINT UNSIGNED)
BEGIN
    DECLARE requise VARCHAR(30);
    DECLARE perim VARCHAR(20);
    DECLARE message VARCHAR(255);
    SELECT piece_requise, perimetre INTO requise, perim FROM transition_autorisee WHERE de_statut = p_de AND vers_statut = p_vers;
    IF p_decision <> 'en_attente' AND NOT EXISTS (
        SELECT 1 FROM utilisateur_role ur JOIN utilisateur u ON u.id = ur.utilisateur_id
         WHERE ur.utilisateur_id = p_valide_par
           AND (ur.role_code IN ('superadmin', 'secretaire_general') OR (ur.role_code = 'validateur' AND JSON_CONTAINS(ur.perimetre, JSON_QUOTE(perim))))
           AND u.actif) THEN
        SET message = LEFT(CONCAT('transition ', p_de, ' -> ', p_vers, ' : validateur du périmètre « ', perim, ' » requis'), 128);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
    END IF;
    IF requise IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM projet_piece WHERE id = p_piece AND projet_id = p_projet AND type_piece = requise) THEN
        SET message = LEFT(CONCAT('transition ', p_de, ' -> ', p_vers, ' : pièce « ', requise, ' » du projet exigée'), 128);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
    END IF;
    IF p_decision = 'approuve' AND p_vers = 'finance' AND NOT EXISTS (
        SELECT 1 FROM projet_financement WHERE projet_id = p_projet AND statut_ligne = 'acquis') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transition vers « financé » : aucune ligne de financement acquise et validée';
    END IF;
END$$
CREATE TRIGGER projet_transition_controle_insertion BEFORE INSERT ON projet_transition FOR EACH ROW
BEGIN
    CALL controler_transition(NEW.projet_id, NEW.de_statut, NEW.vers_statut, NEW.piece_id, NEW.decision, NEW.valide_par);
END$$
CREATE TRIGGER projet_transition_controle_modification BEFORE UPDATE ON projet_transition FOR EACH ROW
BEGIN
    CALL controler_transition(NEW.projet_id, NEW.de_statut, NEW.vers_statut, NEW.piece_id, NEW.decision, NEW.valide_par);
END$$

-- Un projet est créé au statut « idée identifiée », avec un code attribué par le système.
CREATE TRIGGER projet_creation BEFORE INSERT ON projet FOR EACH ROW
BEGIN
    DECLARE numero INT UNSIGNED;
    IF NEW.statut <> 'idee_identifiee' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'un projet est créé au statut « idée identifiée »';
    END IF;
    UPDATE projet_numero SET valeur = valeur + 1 WHERE id = 1;
    SELECT valeur INTO numero FROM projet_numero WHERE id = 1;
    SET NEW.code = CONCAT('PRJ-', DATE_FORMAT(NOW(), '%Y'), '-', LPAD(numero, 3, '0'));
END$$

-- Le statut d'un projet ne change QUE par une transition approuvée et non encore appliquée.
CREATE TRIGGER projet_statut_controle BEFORE UPDATE ON projet FOR EACH ROW
BEGIN
    DECLARE t_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE message VARCHAR(255);
    IF NOT (NEW.code <=> OLD.code) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'le code d''un projet ne change pas';
    END IF;
    IF NOT (NEW.statut <=> OLD.statut) THEN
        SELECT id INTO t_id FROM projet_transition
         WHERE projet_id = NEW.id AND de_statut = OLD.statut AND vers_statut = NEW.statut
           AND decision = 'approuve' AND applique_le IS NULL
         ORDER BY valide_le LIMIT 1;
        IF t_id IS NULL THEN
            SET message = LEFT(CONCAT('changement de statut ', OLD.statut, ' -> ', NEW.statut, ' refusé : aucune transition approuvée'), 128);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = message;
        END IF;
        UPDATE projet_transition SET applique_le = NOW() WHERE id = t_id;
        SET NEW.statut_mis_a_jour_le = NOW();
    END IF;
END$$

-- Une publication renvoie à une validation approuvée du même objet ; média et document doivent être diffusables.
CREATE TRIGGER publication_controle_insertion BEFORE INSERT ON publication FOR EACH ROW
BEGIN
    DECLARE v_decision VARCHAR(20);
    DECLARE v_type VARCHAR(20);
    DECLARE v_objet BIGINT UNSIGNED;
    SELECT decision, objet_type, objet_id INTO v_decision, v_type, v_objet FROM validation_demande WHERE id = NEW.validation_id;
    IF NOT (v_decision <=> 'approuve') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : la validation n''est pas approuvée';
    END IF;
    IF v_type <> NEW.objet_type OR v_objet <> NEW.objet_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : la validation porte sur un autre objet';
    END IF;
    IF NEW.objet_type = 'media' AND EXISTS (SELECT 1 FROM media WHERE id = NEW.objet_id AND droit = 'a_verifier') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : droit d''usage du média à vérifier';
    END IF;
    IF NEW.objet_type = 'document' AND EXISTS (SELECT 1 FROM document WHERE id = NEW.objet_id AND diffusion <> 'public') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication refusée : le document n''est pas diffusable publiquement';
    END IF;
END$$
CREATE TRIGGER publication_controle_modification BEFORE UPDATE ON publication FOR EACH ROW
BEGIN
    IF NOT (NEW.validation_id <=> OLD.validation_id) OR NOT (NEW.objet_type <=> OLD.objet_type) OR NOT (NEW.objet_id <=> OLD.objet_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'publication : la validation et l''objet publiés ne changent pas';
    END IF;
END$$
DELIMITER ;

-- -------------------------------------------------------------------------------------
-- 10. Demandes et contacts (M04, M05, M06, M11)
-- -------------------------------------------------------------------------------------
CREATE TABLE demande (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_suivi          VARCHAR(20) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    type_demande          VARCHAR(20) NOT NULL,
    objet                 VARCHAR(300) NOT NULL,
    message               TEXT NOT NULL,
    organisme             VARCHAR(160),
    pays                  VARCHAR(80),
    projet_id             BIGINT UNSIGNED,
    nom_contact           VARCHAR(160),
    courriel              VARCHAR(254),
    telephone             VARCHAR(30),
    base                  VARCHAR(30) NOT NULL,
    consentement_donne    BOOLEAN NOT NULL DEFAULT false,
    texte_information_version VARCHAR(60) NOT NULL,   -- version de la mention E-01 affichée au moment de l'envoi
    statut                VARCHAR(20) NOT NULL DEFAULT 'recue',
    priorite              SMALLINT,
    affectee_a            BIGINT UNSIGNED,
    recue_le              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    premiere_reponse_le   DATETIME,
    cloturee_le           DATETIME,
    conserver_jusqu_au    DATE NOT NULL,
    anonymisee_le         DATETIME,
    CONSTRAINT chk_demande_numero_suivi CHECK (numero_suivi REGEXP '^DEM-[0-9]{4}-[A-Z0-9]{6}$'),
    CONSTRAINT chk_demande_type_demande CHECK (type_demande IN ('contact', 'investisseur', 'partenaire', 'diaspora', 'autre')),
    CONSTRAINT chk_demande_message CHECK (CHAR_LENGTH(message) <= 4000),
    CONSTRAINT chk_demande_base CHECK (base IN ('consentement', 'mission_interet_public', 'obligation_legale')),
    CONSTRAINT chk_demande_statut CHECK (statut IN ('recue', 'qualifiee', 'affectee', 'en_traitement', 'repondue', 'cloturee', 'rejetee')),
    CONSTRAINT chk_demande_priorite_borne CHECK (priorite BETWEEN 1 AND 3),
    CONSTRAINT chk_demande_consentement CHECK (base <> 'consentement' OR consentement_donne),
    CONSTRAINT chk_demande_conservation CHECK (conserver_jusqu_au > CAST(recue_le AS DATE)),
    CONSTRAINT chk_demande_priorite CHECK (statut NOT IN ('qualifiee', 'affectee', 'en_traitement', 'repondue', 'cloturee') OR priorite IS NOT NULL),
    CONSTRAINT chk_demande_affectation CHECK (statut <> 'affectee' OR affectee_a IS NOT NULL),
    CONSTRAINT chk_demande_reponse CHECK (premiere_reponse_le IS NULL OR premiere_reponse_le >= recue_le),
    FOREIGN KEY (projet_id) REFERENCES projet (id),
    FOREIGN KEY (affectee_a) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE demande_evenement (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    demande_id  BIGINT UNSIGNED NOT NULL,
    type        VARCHAR(20) NOT NULL,
    contenu     TEXT,
    par         BIGINT UNSIGNED,
    le          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_demande_evenement_type CHECK (type IN ('qualification', 'affectation', 'note', 'reponse', 'cloture', 'relance')),
    FOREIGN KEY (demande_id) REFERENCES demande (id) ON DELETE CASCADE,
    FOREIGN KEY (par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE demande_piece_jointe (
    demande_id  BIGINT UNSIGNED NOT NULL,
    fichier_id  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (demande_id, fichier_id),
    FOREIGN KEY (demande_id) REFERENCES demande (id) ON DELETE CASCADE,
    FOREIGN KEY (fichier_id) REFERENCES fichier (id)
) ENGINE=InnoDB;

-- Purge : anonymise les demandes et supprime les lignes de journal dont la conservation est échue (E-04).
DELIMITER $$
DROP PROCEDURE IF EXISTS purger_donnees_expirees$$
CREATE PROCEDURE purger_donnees_expirees()
BEGIN
    DECLARE d INT DEFAULT 0;
    DECLARE j INT DEFAULT 0;
    UPDATE demande SET nom_contact = NULL, courriel = NULL, telephone = NULL, organisme = NULL,
                       message = '[anonymisé]', anonymisee_le = NOW()
     WHERE conserver_jusqu_au < CURRENT_DATE AND anonymisee_le IS NULL;
    SET d = ROW_COUNT();
    DELETE FROM demande_evenement WHERE type IN ('note', 'reponse')
       AND demande_id IN (SELECT id FROM demande WHERE anonymisee_le IS NOT NULL);
    DELETE FROM journal_audit WHERE conserver_jusqu_au < CURRENT_DATE;
    SET j = ROW_COUNT();
    SELECT d AS demandes_anonymisees, j AS journaux_supprimes;
END$$
DELIMITER ;

-- -------------------------------------------------------------------------------------
-- 11. Statistiques (§12) et configuration
-- -------------------------------------------------------------------------------------
-- Comptages agrégés, sans donnée personnelle. Une ligne par jour, mesure, objet et chemin (clé calculée).
CREATE TABLE statistique_jour (
    jour        DATE NOT NULL,
    mesure      VARCHAR(40) NOT NULL,
    objet_type  VARCHAR(20),
    objet_id    BIGINT UNSIGNED,
    chemin      VARCHAR(500),
    nombre      INT UNSIGNED NOT NULL,
    cle         CHAR(64) AS (SHA2(CONCAT_WS('|', jour, mesure, IFNULL(objet_type, ''), IFNULL(objet_id, ''), IFNULL(chemin, '')), 256)) STORED,
    UNIQUE KEY statistique_jour_cle (cle),
    CONSTRAINT chk_statistique_jour_mesure CHECK (mesure IN ('vue_page', 'telechargement_fiche', 'telechargement_document')),
    CONSTRAINT chk_statistique_jour_objet_type CHECK (objet_type IN ('contenu', 'projet', 'document', 'elu', 'partenaire', 'localite', 'equipement', 'media'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parametre (
    cle          VARCHAR(80) COLLATE utf8mb4_bin PRIMARY KEY,
    valeur       JSON NOT NULL,
    modifie_par  BIGINT UNSIGNED,
    modifie_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (modifie_par) REFERENCES utilisateur (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registre des traitements de données personnelles (E-02).
CREATE TABLE traitement (
    code                    VARCHAR(60) COLLATE utf8mb4_bin PRIMARY KEY,
    finalite                TEXT NOT NULL,
    base                    VARCHAR(30) NOT NULL,
    categories_donnees      JSON NOT NULL,
    destinataires           JSON NOT NULL,
    duree_conservation_jours INT UNSIGNED NOT NULL,
    transfert_hors_cameroun BOOLEAN NOT NULL DEFAULT false,
    mesures_securite        TEXT NOT NULL,
    valide_par_rj           BOOLEAN NOT NULL DEFAULT false,
    CONSTRAINT chk_traitement_base CHECK (base IN ('consentement', 'mission_interet_public', 'obligation_legale')),
    CONSTRAINT chk_traitement_duree CHECK (duree_conservation_jours > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 12. Historisation des tables éditoriales (§8)
-- -------------------------------------------------------------------------------------
-- DÉBUT DE LA SECTION GÉNÉRÉE par outils/donnees/generer_historisation.py : ne pas modifier à la main.
-- Une ligne par insertion, modification et suppression, avec l'utilisateur courant (@app_utilisateur_id).
DELIMITER $$
CREATE TRIGGER historique_contenu_insertion AFTER INSERT ON contenu FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('contenu', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'type_contenu', NEW.type_contenu, 'module', NEW.module, 'slug', NEW.slug, 'titre', NEW.titre, 'resume', NEW.resume, 'corps', NEW.corps, 'attributs', NEW.attributs, 'localite_id', NEW.localite_id, 'image_id', NEW.image_id, 'auteur_id', NEW.auteur_id, 'etat', NEW.etat, 'publication_programmee', NEW.publication_programmee, 'cree_le', NEW.cree_le, 'modifie_le', NEW.modifie_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_contenu_modification AFTER UPDATE ON contenu FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('contenu', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'type_contenu', OLD.type_contenu, 'module', OLD.module, 'slug', OLD.slug, 'titre', OLD.titre, 'resume', OLD.resume, 'corps', OLD.corps, 'attributs', OLD.attributs, 'localite_id', OLD.localite_id, 'image_id', OLD.image_id, 'auteur_id', OLD.auteur_id, 'etat', OLD.etat, 'publication_programmee', OLD.publication_programmee, 'cree_le', OLD.cree_le, 'modifie_le', OLD.modifie_le), JSON_OBJECT('id', NEW.id, 'type_contenu', NEW.type_contenu, 'module', NEW.module, 'slug', NEW.slug, 'titre', NEW.titre, 'resume', NEW.resume, 'corps', NEW.corps, 'attributs', NEW.attributs, 'localite_id', NEW.localite_id, 'image_id', NEW.image_id, 'auteur_id', NEW.auteur_id, 'etat', NEW.etat, 'publication_programmee', NEW.publication_programmee, 'cree_le', NEW.cree_le, 'modifie_le', NEW.modifie_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_contenu_suppression AFTER DELETE ON contenu FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('contenu', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'type_contenu', OLD.type_contenu, 'module', OLD.module, 'slug', OLD.slug, 'titre', OLD.titre, 'resume', OLD.resume, 'corps', OLD.corps, 'attributs', OLD.attributs, 'localite_id', OLD.localite_id, 'image_id', OLD.image_id, 'auteur_id', OLD.auteur_id, 'etat', OLD.etat, 'publication_programmee', OLD.publication_programmee, 'cree_le', OLD.cree_le, 'modifie_le', OLD.modifie_le), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_elu_insertion AFTER INSERT ON elu FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('elu', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'nom_affiche', NEW.nom_affiche, 'fonction', NEW.fonction, 'organe', NEW.organe, 'mandat_debut', NEW.mandat_debut, 'mandat_fin', NEW.mandat_fin, 'biographie', NEW.biographie, 'photo_id', NEW.photo_id, 'source_id', NEW.source_id, 'etat', NEW.etat, 'motif_conservation', NEW.motif_conservation, 'conserver_jusqu_au', NEW.conserver_jusqu_au), @app_utilisateur_id)$$
CREATE TRIGGER historique_elu_modification AFTER UPDATE ON elu FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('elu', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'nom_affiche', OLD.nom_affiche, 'fonction', OLD.fonction, 'organe', OLD.organe, 'mandat_debut', OLD.mandat_debut, 'mandat_fin', OLD.mandat_fin, 'biographie', OLD.biographie, 'photo_id', OLD.photo_id, 'source_id', OLD.source_id, 'etat', OLD.etat, 'motif_conservation', OLD.motif_conservation, 'conserver_jusqu_au', OLD.conserver_jusqu_au), JSON_OBJECT('id', NEW.id, 'nom_affiche', NEW.nom_affiche, 'fonction', NEW.fonction, 'organe', NEW.organe, 'mandat_debut', NEW.mandat_debut, 'mandat_fin', NEW.mandat_fin, 'biographie', NEW.biographie, 'photo_id', NEW.photo_id, 'source_id', NEW.source_id, 'etat', NEW.etat, 'motif_conservation', NEW.motif_conservation, 'conserver_jusqu_au', NEW.conserver_jusqu_au), @app_utilisateur_id)$$
CREATE TRIGGER historique_elu_suppression AFTER DELETE ON elu FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('elu', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'nom_affiche', OLD.nom_affiche, 'fonction', OLD.fonction, 'organe', OLD.organe, 'mandat_debut', OLD.mandat_debut, 'mandat_fin', OLD.mandat_fin, 'biographie', OLD.biographie, 'photo_id', OLD.photo_id, 'source_id', OLD.source_id, 'etat', OLD.etat, 'motif_conservation', OLD.motif_conservation, 'conserver_jusqu_au', OLD.conserver_jusqu_au), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_document_insertion AFTER INSERT ON document FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('document', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'slug', NEW.slug, 'titre', NEW.titre, 'categorie', NEW.categorie, 'resume', NEW.resume, 'date_document', NEW.date_document, 'reference_officielle', NEW.reference_officielle, 'diffusion', NEW.diffusion, 'justificatif_diffusion', NEW.justificatif_diffusion, 'etat', NEW.etat, 'archive', NEW.archive), @app_utilisateur_id)$$
CREATE TRIGGER historique_document_modification AFTER UPDATE ON document FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('document', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'slug', OLD.slug, 'titre', OLD.titre, 'categorie', OLD.categorie, 'resume', OLD.resume, 'date_document', OLD.date_document, 'reference_officielle', OLD.reference_officielle, 'diffusion', OLD.diffusion, 'justificatif_diffusion', OLD.justificatif_diffusion, 'etat', OLD.etat, 'archive', OLD.archive), JSON_OBJECT('id', NEW.id, 'slug', NEW.slug, 'titre', NEW.titre, 'categorie', NEW.categorie, 'resume', NEW.resume, 'date_document', NEW.date_document, 'reference_officielle', NEW.reference_officielle, 'diffusion', NEW.diffusion, 'justificatif_diffusion', NEW.justificatif_diffusion, 'etat', NEW.etat, 'archive', NEW.archive), @app_utilisateur_id)$$
CREATE TRIGGER historique_document_suppression AFTER DELETE ON document FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('document', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'slug', OLD.slug, 'titre', OLD.titre, 'categorie', OLD.categorie, 'resume', OLD.resume, 'date_document', OLD.date_document, 'reference_officielle', OLD.reference_officielle, 'diffusion', OLD.diffusion, 'justificatif_diffusion', OLD.justificatif_diffusion, 'etat', OLD.etat, 'archive', OLD.archive), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_partenaire_insertion AFTER INSERT ON partenaire FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('partenaire', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'nom', NEW.nom, 'type_partenaire', NEW.type_partenaire, 'pays', NEW.pays, 'site_web', NEW.site_web, 'accepte_citation', NEW.accepte_citation, 'accord_citation_id', NEW.accord_citation_id, 'logo_id', NEW.logo_id, 'etat', NEW.etat), @app_utilisateur_id)$$
CREATE TRIGGER historique_partenaire_modification AFTER UPDATE ON partenaire FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('partenaire', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'nom', OLD.nom, 'type_partenaire', OLD.type_partenaire, 'pays', OLD.pays, 'site_web', OLD.site_web, 'accepte_citation', OLD.accepte_citation, 'accord_citation_id', OLD.accord_citation_id, 'logo_id', OLD.logo_id, 'etat', OLD.etat), JSON_OBJECT('id', NEW.id, 'nom', NEW.nom, 'type_partenaire', NEW.type_partenaire, 'pays', NEW.pays, 'site_web', NEW.site_web, 'accepte_citation', NEW.accepte_citation, 'accord_citation_id', NEW.accord_citation_id, 'logo_id', NEW.logo_id, 'etat', NEW.etat), @app_utilisateur_id)$$
CREATE TRIGGER historique_partenaire_suppression AFTER DELETE ON partenaire FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('partenaire', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'nom', OLD.nom, 'type_partenaire', OLD.type_partenaire, 'pays', OLD.pays, 'site_web', OLD.site_web, 'accepte_citation', OLD.accepte_citation, 'accord_citation_id', OLD.accord_citation_id, 'logo_id', OLD.logo_id, 'etat', OLD.etat), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_localite_insertion AFTER INSERT ON localite FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('localite', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'nom', NEW.nom, 'slug', NEW.slug, 'categorie', NEW.categorie, 'latitude', NEW.latitude, 'longitude', NEW.longitude, 'coordonnees_source', NEW.coordonnees_source, 'source_id', NEW.source_id, 'etat', NEW.etat), @app_utilisateur_id)$$
CREATE TRIGGER historique_localite_modification AFTER UPDATE ON localite FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('localite', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'nom', OLD.nom, 'slug', OLD.slug, 'categorie', OLD.categorie, 'latitude', OLD.latitude, 'longitude', OLD.longitude, 'coordonnees_source', OLD.coordonnees_source, 'source_id', OLD.source_id, 'etat', OLD.etat), JSON_OBJECT('id', NEW.id, 'nom', NEW.nom, 'slug', NEW.slug, 'categorie', NEW.categorie, 'latitude', NEW.latitude, 'longitude', NEW.longitude, 'coordonnees_source', NEW.coordonnees_source, 'source_id', NEW.source_id, 'etat', NEW.etat), @app_utilisateur_id)$$
CREATE TRIGGER historique_localite_suppression AFTER DELETE ON localite FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('localite', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'nom', OLD.nom, 'slug', OLD.slug, 'categorie', OLD.categorie, 'latitude', OLD.latitude, 'longitude', OLD.longitude, 'coordonnees_source', OLD.coordonnees_source, 'source_id', OLD.source_id, 'etat', OLD.etat), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_equipement_insertion AFTER INSERT ON equipement FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('equipement', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'type_equipement', NEW.type_equipement, 'nom', NEW.nom, 'localite_id', NEW.localite_id, 'latitude', NEW.latitude, 'longitude', NEW.longitude, 'source_id', NEW.source_id, 'etat', NEW.etat), @app_utilisateur_id)$$
CREATE TRIGGER historique_equipement_modification AFTER UPDATE ON equipement FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('equipement', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'type_equipement', OLD.type_equipement, 'nom', OLD.nom, 'localite_id', OLD.localite_id, 'latitude', OLD.latitude, 'longitude', OLD.longitude, 'source_id', OLD.source_id, 'etat', OLD.etat), JSON_OBJECT('id', NEW.id, 'type_equipement', NEW.type_equipement, 'nom', NEW.nom, 'localite_id', NEW.localite_id, 'latitude', NEW.latitude, 'longitude', NEW.longitude, 'source_id', NEW.source_id, 'etat', NEW.etat), @app_utilisateur_id)$$
CREATE TRIGGER historique_equipement_suppression AFTER DELETE ON equipement FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('equipement', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'type_equipement', OLD.type_equipement, 'nom', OLD.nom, 'localite_id', OLD.localite_id, 'latitude', OLD.latitude, 'longitude', OLD.longitude, 'source_id', OLD.source_id, 'etat', OLD.etat), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_media_insertion AFTER INSERT ON media FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('media', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'fichier_id', NEW.fichier_id, 'titre', NEW.titre, 'texte_alternatif', NEW.texte_alternatif, 'auteur', NEW.auteur, 'date_prise', NEW.date_prise, 'lieu', NEW.lieu, 'droit', NEW.droit, 'justificatif_id', NEW.justificatif_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_media_modification AFTER UPDATE ON media FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('media', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'fichier_id', OLD.fichier_id, 'titre', OLD.titre, 'texte_alternatif', OLD.texte_alternatif, 'auteur', OLD.auteur, 'date_prise', OLD.date_prise, 'lieu', OLD.lieu, 'droit', OLD.droit, 'justificatif_id', OLD.justificatif_id), JSON_OBJECT('id', NEW.id, 'fichier_id', NEW.fichier_id, 'titre', NEW.titre, 'texte_alternatif', NEW.texte_alternatif, 'auteur', NEW.auteur, 'date_prise', NEW.date_prise, 'lieu', NEW.lieu, 'droit', NEW.droit, 'justificatif_id', NEW.justificatif_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_media_suppression AFTER DELETE ON media FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('media', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'fichier_id', OLD.fichier_id, 'titre', OLD.titre, 'texte_alternatif', OLD.texte_alternatif, 'auteur', OLD.auteur, 'date_prise', OLD.date_prise, 'lieu', OLD.lieu, 'droit', OLD.droit, 'justificatif_id', OLD.justificatif_id), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_insertion AFTER INSERT ON projet FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'code', NEW.code, 'slug', NEW.slug, 'titre', NEW.titre, 'secteur', NEW.secteur, 'resume', NEW.resume, 'description_technique', NEW.description_technique, 'description_publique', NEW.description_publique, 'besoin', NEW.besoin, 'besoin_source_id', NEW.besoin_source_id, 'statut', NEW.statut, 'statut_mis_a_jour_le', NEW.statut_mis_a_jour_le, 'motif_suspension', NEW.motif_suspension, 'avancement_pct', NEW.avancement_pct, 'avancement_source_id', NEW.avancement_source_id, 'avancement_date', NEW.avancement_date, 'service_responsable', NEW.service_responsable, 'responsable_id', NEW.responsable_id, 'visible_catalogue', NEW.visible_catalogue, 'motif_retrait', NEW.motif_retrait, 'etat', NEW.etat, 'cree_le', NEW.cree_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_modification AFTER UPDATE ON projet FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'code', OLD.code, 'slug', OLD.slug, 'titre', OLD.titre, 'secteur', OLD.secteur, 'resume', OLD.resume, 'description_technique', OLD.description_technique, 'description_publique', OLD.description_publique, 'besoin', OLD.besoin, 'besoin_source_id', OLD.besoin_source_id, 'statut', OLD.statut, 'statut_mis_a_jour_le', OLD.statut_mis_a_jour_le, 'motif_suspension', OLD.motif_suspension, 'avancement_pct', OLD.avancement_pct, 'avancement_source_id', OLD.avancement_source_id, 'avancement_date', OLD.avancement_date, 'service_responsable', OLD.service_responsable, 'responsable_id', OLD.responsable_id, 'visible_catalogue', OLD.visible_catalogue, 'motif_retrait', OLD.motif_retrait, 'etat', OLD.etat, 'cree_le', OLD.cree_le), JSON_OBJECT('id', NEW.id, 'code', NEW.code, 'slug', NEW.slug, 'titre', NEW.titre, 'secteur', NEW.secteur, 'resume', NEW.resume, 'description_technique', NEW.description_technique, 'description_publique', NEW.description_publique, 'besoin', NEW.besoin, 'besoin_source_id', NEW.besoin_source_id, 'statut', NEW.statut, 'statut_mis_a_jour_le', NEW.statut_mis_a_jour_le, 'motif_suspension', NEW.motif_suspension, 'avancement_pct', NEW.avancement_pct, 'avancement_source_id', NEW.avancement_source_id, 'avancement_date', NEW.avancement_date, 'service_responsable', NEW.service_responsable, 'responsable_id', NEW.responsable_id, 'visible_catalogue', NEW.visible_catalogue, 'motif_retrait', NEW.motif_retrait, 'etat', NEW.etat, 'cree_le', NEW.cree_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_suppression AFTER DELETE ON projet FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'code', OLD.code, 'slug', OLD.slug, 'titre', OLD.titre, 'secteur', OLD.secteur, 'resume', OLD.resume, 'description_technique', OLD.description_technique, 'description_publique', OLD.description_publique, 'besoin', OLD.besoin, 'besoin_source_id', OLD.besoin_source_id, 'statut', OLD.statut, 'statut_mis_a_jour_le', OLD.statut_mis_a_jour_le, 'motif_suspension', OLD.motif_suspension, 'avancement_pct', OLD.avancement_pct, 'avancement_source_id', OLD.avancement_source_id, 'avancement_date', OLD.avancement_date, 'service_responsable', OLD.service_responsable, 'responsable_id', OLD.responsable_id, 'visible_catalogue', OLD.visible_catalogue, 'motif_retrait', OLD.motif_retrait, 'etat', OLD.etat, 'cree_le', OLD.cree_le), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_estimation_cout_insertion AFTER INSERT ON projet_estimation_cout FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_estimation_cout', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'montant', NEW.montant, 'devise', NEW.devise, 'date_estimation', NEW.date_estimation, 'nature_cout', NEW.nature_cout, 'nature', NEW.nature, 'source_id', NEW.source_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_estimation_cout_modification AFTER UPDATE ON projet_estimation_cout FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_estimation_cout', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'montant', OLD.montant, 'devise', OLD.devise, 'date_estimation', OLD.date_estimation, 'nature_cout', OLD.nature_cout, 'nature', OLD.nature, 'source_id', OLD.source_id), JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'montant', NEW.montant, 'devise', NEW.devise, 'date_estimation', NEW.date_estimation, 'nature_cout', NEW.nature_cout, 'nature', NEW.nature, 'source_id', NEW.source_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_estimation_cout_suppression AFTER DELETE ON projet_estimation_cout FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_estimation_cout', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'montant', OLD.montant, 'devise', OLD.devise, 'date_estimation', OLD.date_estimation, 'nature_cout', OLD.nature_cout, 'nature', OLD.nature, 'source_id', OLD.source_id), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_financement_insertion AFTER INSERT ON projet_financement FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_financement', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'statut_ligne', NEW.statut_ligne, 'partenaire_id', NEW.partenaire_id, 'montant', NEW.montant, 'devise', NEW.devise, 'date_information', NEW.date_information, 'source_id', NEW.source_id, 'piece_id', NEW.piece_id, 'valide_par', NEW.valide_par, 'valide_le', NEW.valide_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_financement_modification AFTER UPDATE ON projet_financement FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_financement', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'statut_ligne', OLD.statut_ligne, 'partenaire_id', OLD.partenaire_id, 'montant', OLD.montant, 'devise', OLD.devise, 'date_information', OLD.date_information, 'source_id', OLD.source_id, 'piece_id', OLD.piece_id, 'valide_par', OLD.valide_par, 'valide_le', OLD.valide_le), JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'statut_ligne', NEW.statut_ligne, 'partenaire_id', NEW.partenaire_id, 'montant', NEW.montant, 'devise', NEW.devise, 'date_information', NEW.date_information, 'source_id', NEW.source_id, 'piece_id', NEW.piece_id, 'valide_par', NEW.valide_par, 'valide_le', NEW.valide_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_financement_suppression AFTER DELETE ON projet_financement FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_financement', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'statut_ligne', OLD.statut_ligne, 'partenaire_id', OLD.partenaire_id, 'montant', OLD.montant, 'devise', OLD.devise, 'date_information', OLD.date_information, 'source_id', OLD.source_id, 'piece_id', OLD.piece_id, 'valide_par', OLD.valide_par, 'valide_le', OLD.valide_le), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_beneficiaires_insertion AFTER INSERT ON projet_beneficiaires FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_beneficiaires', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'nombre', NEW.nombre, 'categorie', NEW.categorie, 'methode_estimation', NEW.methode_estimation, 'date_estimation', NEW.date_estimation, 'nature', NEW.nature, 'source_id', NEW.source_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_beneficiaires_modification AFTER UPDATE ON projet_beneficiaires FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_beneficiaires', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'nombre', OLD.nombre, 'categorie', OLD.categorie, 'methode_estimation', OLD.methode_estimation, 'date_estimation', OLD.date_estimation, 'nature', OLD.nature, 'source_id', OLD.source_id), JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'nombre', NEW.nombre, 'categorie', NEW.categorie, 'methode_estimation', NEW.methode_estimation, 'date_estimation', NEW.date_estimation, 'nature', NEW.nature, 'source_id', NEW.source_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_beneficiaires_suppression AFTER DELETE ON projet_beneficiaires FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_beneficiaires', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'nombre', OLD.nombre, 'categorie', OLD.categorie, 'methode_estimation', OLD.methode_estimation, 'date_estimation', OLD.date_estimation, 'nature', OLD.nature, 'source_id', OLD.source_id), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_indicateur_insertion AFTER INSERT ON projet_indicateur FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_indicateur', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'libelle', NEW.libelle, 'unite', NEW.unite, 'valeur_reference', NEW.valeur_reference, 'cible', NEW.cible, 'valeur_atteinte', NEW.valeur_atteinte, 'date_mesure', NEW.date_mesure, 'methode_mesure', NEW.methode_mesure, 'source_id', NEW.source_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_indicateur_modification AFTER UPDATE ON projet_indicateur FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_indicateur', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'libelle', OLD.libelle, 'unite', OLD.unite, 'valeur_reference', OLD.valeur_reference, 'cible', OLD.cible, 'valeur_atteinte', OLD.valeur_atteinte, 'date_mesure', OLD.date_mesure, 'methode_mesure', OLD.methode_mesure, 'source_id', OLD.source_id), JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'libelle', NEW.libelle, 'unite', NEW.unite, 'valeur_reference', NEW.valeur_reference, 'cible', NEW.cible, 'valeur_atteinte', NEW.valeur_atteinte, 'date_mesure', NEW.date_mesure, 'methode_mesure', NEW.methode_mesure, 'source_id', NEW.source_id), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_indicateur_suppression AFTER DELETE ON projet_indicateur FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_indicateur', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'libelle', OLD.libelle, 'unite', OLD.unite, 'valeur_reference', OLD.valeur_reference, 'cible', OLD.cible, 'valeur_atteinte', OLD.valeur_atteinte, 'date_mesure', OLD.date_mesure, 'methode_mesure', OLD.methode_mesure, 'source_id', OLD.source_id), NULL, @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_transition_insertion AFTER INSERT ON projet_transition FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_transition', NEW.id, 'INSERT', NULL, JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'de_statut', NEW.de_statut, 'vers_statut', NEW.vers_statut, 'demande_par', NEW.demande_par, 'demande_le', NEW.demande_le, 'motif', NEW.motif, 'piece_id', NEW.piece_id, 'decision', NEW.decision, 'valide_par', NEW.valide_par, 'valide_le', NEW.valide_le, 'applique_le', NEW.applique_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_transition_modification AFTER UPDATE ON projet_transition FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_transition', NEW.id, 'UPDATE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'de_statut', OLD.de_statut, 'vers_statut', OLD.vers_statut, 'demande_par', OLD.demande_par, 'demande_le', OLD.demande_le, 'motif', OLD.motif, 'piece_id', OLD.piece_id, 'decision', OLD.decision, 'valide_par', OLD.valide_par, 'valide_le', OLD.valide_le, 'applique_le', OLD.applique_le), JSON_OBJECT('id', NEW.id, 'projet_id', NEW.projet_id, 'de_statut', NEW.de_statut, 'vers_statut', NEW.vers_statut, 'demande_par', NEW.demande_par, 'demande_le', NEW.demande_le, 'motif', NEW.motif, 'piece_id', NEW.piece_id, 'decision', NEW.decision, 'valide_par', NEW.valide_par, 'valide_le', NEW.valide_le, 'applique_le', NEW.applique_le), @app_utilisateur_id)$$
CREATE TRIGGER historique_projet_transition_suppression AFTER DELETE ON projet_transition FOR EACH ROW
    INSERT INTO historique_modification (nom_table, ligne_id, operation, ancien, nouveau, utilisateur_id)
    VALUES ('projet_transition', OLD.id, 'DELETE', JSON_OBJECT('id', OLD.id, 'projet_id', OLD.projet_id, 'de_statut', OLD.de_statut, 'vers_statut', OLD.vers_statut, 'demande_par', OLD.demande_par, 'demande_le', OLD.demande_le, 'motif', OLD.motif, 'piece_id', OLD.piece_id, 'decision', OLD.decision, 'valide_par', OLD.valide_par, 'valide_le', OLD.valide_le, 'applique_le', OLD.applique_le), NULL, @app_utilisateur_id)$$
DELIMITER ;
-- FIN DE LA SECTION GÉNÉRÉE

-- Vue publique des projets : uniquement la dernière publication non retirée, si la fiche est visible au catalogue.
CREATE OR REPLACE VIEW projet_public AS
SELECT pub.objet_id AS projet_id, pub.version, pub.instantane, pub.publie_le
  FROM publication pub
  JOIN projet p ON p.id = pub.objet_id
 WHERE pub.objet_type = 'projet' AND pub.retire_le IS NULL AND p.visible_catalogue
   AND pub.version = (SELECT MAX(x.version) FROM publication x
                       WHERE x.objet_type = 'projet' AND x.objet_id = pub.objet_id AND x.retire_le IS NULL);
