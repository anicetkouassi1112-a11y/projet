DROP DATABASE IF EXISTS projet_db;

CREATE DATABASE IF NOT EXISTS projet_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE projet_db;

-- ============================================================
-- ADMIN
-- ============================================================
CREATE TABLE IF NOT EXISTS admin (
  id_admin INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('directeur', 'suppleant_1', 'suppleant_2') NOT NULL DEFAULT 'directeur',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_admin),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO admin (username, password, role) VALUES
  ('patro', '$2y$10$hRjLFyzzitNKa6YIOIeag.pcWkLTuG0VKdmpmv39I0qVpq7RsEgTu', 'directeur');

-- ============================================================
-- ANNEE
-- ============================================================
CREATE TABLE IF NOT EXISTS annee (
  idannee INT NOT NULL AUTO_INCREMENT,
  ans INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idannee),
  UNIQUE KEY uq_annee_ans (ans)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- SECTION
-- Une section (ex: "Aiglons", "Louveteaux", ...).
-- ============================================================
CREATE TABLE IF NOT EXISTS section (
  id_section INT NOT NULL AUTO_INCREMENT,
  nom_section VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  genre ENUM('Garçon','Fille') NOT NULL,
  age_min TINYINT UNSIGNED NOT NULL,
  age_max TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_section),
  UNIQUE KEY uq_section_nom (nom_section),
  KEY idx_section_genre_age (genre, age_min, age_max),
  CONSTRAINT chk_section_age_range CHECK (age_min <= age_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- ANIMATEUR
-- Compte/identité permanente d'un animateur.
-- ============================================================
CREATE TABLE IF NOT EXISTS animateur (
  id_animateur INT NOT NULL AUTO_INCREMENT,
  nom_a VARCHAR(255) NOT NULL,
  prenom_a VARCHAR(255) NOT NULL,
  genre_a ENUM('M','F') NOT NULL,
  tel VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  statut ENUM('actif', 'bloque') NOT NULL DEFAULT 'actif',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_animateur),
  UNIQUE KEY uq_animateur_tel (tel),
  KEY idx_animateur_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- UTILISATEUR
-- Données personnelles de l'inscrit.
-- ============================================================
CREATE TABLE IF NOT EXISTS utilisateur (
  id_utilisateur INT NOT NULL AUTO_INCREMENT,
  nom VARCHAR(255) NOT NULL,
  prenom VARCHAR(255) NOT NULL,
  date_naissance DATE NOT NULL,
  genre VARCHAR(50) NOT NULL,
  tel VARCHAR(12) NOT NULL,
  adresse VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_utilisateur),
  UNIQUE KEY uq_utilisateur_identity (nom, prenom, date_naissance),
  KEY idx_utilisateur_tel (tel),
  KEY idx_utilisateur_genre (genre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- SESSION
-- Une "session" = (année, type) : ex. "scolaire 2026", "vacance 2026".
-- ============================================================
CREATE TABLE IF NOT EXISTS session (
  id_session INT NOT NULL AUTO_INCREMENT,
  annee_id INT NOT NULL,
  type_session ENUM('scolaire', 'vacance') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_session),
  UNIQUE KEY uq_session_annee_type (annee_id, type_session),
  CONSTRAINT fk_session_annee
    FOREIGN KEY (annee_id) REFERENCES annee (idannee)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- CODE_INSCRIPTION_ANIMATEUR
-- Code généré pour UNE session précise.
-- ============================================================
CREATE TABLE IF NOT EXISTS code_inscription_animateur (
  id_code INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  id_session INT NOT NULL,
  id_admin INT NOT NULL,
  statut ENUM('disponible', 'utilise') NOT NULL DEFAULT 'disponible',
  id_animateur INT DEFAULT NULL,
  date_expiration DATETIME DEFAULT NULL,
  utilise_le TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_code),
  UNIQUE KEY uq_code_valeur (code),
  KEY idx_code_session (id_session),
  KEY idx_code_statut (statut),
  CONSTRAINT fk_code_session
    FOREIGN KEY (id_session) REFERENCES session (id_session)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_code_admin
    FOREIGN KEY (id_admin) REFERENCES admin (id_admin)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_code_animateur
    FOREIGN KEY (id_animateur) REFERENCES animateur (id_animateur)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- ANIMATEUR_SESSION
-- Enregistrement de l'animateur pour une session.
-- id_section est NULLable (assigné par l'admin après inscription).
-- ============================================================
CREATE TABLE IF NOT EXISTS animateur_session (
  id_animateur_session INT NOT NULL AUTO_INCREMENT,
  id_animateur INT NOT NULL,
  id_session INT NOT NULL,
  id_code INT NOT NULL,
  id_section INT DEFAULT NULL,
  date_inscription TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_animateur_session),
  UNIQUE KEY uq_animateur_session (id_animateur, id_session),
  KEY idx_animateur_session_session (id_session),
  KEY idx_animateur_session_section (id_section),
  CONSTRAINT fk_animateur_session_animateur
    FOREIGN KEY (id_animateur) REFERENCES animateur (id_animateur)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_animateur_session_session
    FOREIGN KEY (id_session) REFERENCES session (id_session)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_animateur_session_code
    FOREIGN KEY (id_code) REFERENCES code_inscription_animateur (id_code)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_animateur_session_section
    FOREIGN KEY (id_section) REFERENCES section (id_section)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- IDENTIFIANT_SEQUENCES
-- ============================================================
CREATE TABLE IF NOT EXISTS identifiant_sequences (
  sequence_name VARCHAR(80) NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (sequence_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- INSCRIPTION
-- ============================================================
CREATE TABLE IF NOT EXISTS inscription (
  id_inscription INT NOT NULL AUTO_INCREMENT,
  identifiant VARCHAR(32) NOT NULL,
  id_utilisateur INT NOT NULL,
  id_session INT NOT NULL,
  id_section INT NULL,
  montant_inscription INT UNSIGNED NOT NULL DEFAULT 0,
  prix_tee_shirt INT UNSIGNED NOT NULL DEFAULT 0,
  taille_tee_shirt VARCHAR(10) DEFAULT NULL,
  etat VARCHAR(50) NOT NULL DEFAULT 'En attente',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_inscription),
  UNIQUE KEY uq_inscription_identifiant (identifiant),
  UNIQUE KEY uq_inscription_utilisateur_session (id_utilisateur, id_session),
  KEY idx_inscription_session (id_session),
  KEY idx_inscription_section (id_section),
  KEY idx_inscription_tee_shirt (prix_tee_shirt, taille_tee_shirt),
  KEY idx_inscription_etat (etat),
  CONSTRAINT fk_inscription_utilisateur
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur (id_utilisateur)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_inscription_session
    FOREIGN KEY (id_session) REFERENCES session (id_session)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_inscription_section
    FOREIGN KEY (id_section) REFERENCES section (id_section)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- JEUX
-- ============================================================
CREATE TABLE IF NOT EXISTS jeux (
  id INT NOT NULL AUTO_INCREMENT,
  nom VARCHAR(150) NOT NULL,
  objectif TEXT,
  age_conseille VARCHAR(50),
  duree VARCHAR(50),
  nombre_joueurs VARCHAR(100),
  lieu VARCHAR(100),
  type_jeu VARCHAR(100),
  materiel TEXT,
  mise_en_place TEXT,
  deroulement TEXT,
  regles TEXT,
  fin_jeu TEXT,
  but_pedagogique TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- ACTIVITE_IMAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS activite_images (
  id INT NOT NULL AUTO_INCREMENT,
  session_id INT NOT NULL,
  titre VARCHAR(255) DEFAULT NULL,
  image_path VARCHAR(500) NOT NULL,
  ordre INT NOT NULL DEFAULT 0,
  description TEXT DEFAULT NULL,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activite_session_visible_ordre (session_id, visible, ordre),
  CONSTRAINT fk_activite_images_session
    FOREIGN KEY (session_id) REFERENCES session (id_session)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- THEMES / SOUS_THEMES
-- ============================================================
CREATE TABLE IF NOT EXISTS themes (
  id INT NOT NULL AUTO_INCREMENT,
  titre VARCHAR(255) NOT NULL,
  session_id INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_themes_session (session_id),
  CONSTRAINT fk_themes_session
    FOREIGN KEY (session_id) REFERENCES session (id_session)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- CONTACT_MESSAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT NOT NULL AUTO_INCREMENT,
  nom VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL,
  sujet VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- CONFIGURATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS configurations (
  id_config INT NOT NULL AUTO_INCREMENT,
  config_key VARCHAR(100) NOT NULL,
  config_value TEXT DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_config),
  UNIQUE KEY uq_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- CINETPAY_TRANSACTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS cinetpay_transactions (
  id INT NOT NULL AUTO_INCREMENT,
  transaction_id VARCHAR(80) NOT NULL,
  id_inscription INT NOT NULL,
  amount INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'INITIATED',
  payment_token VARCHAR(255) DEFAULT NULL,
  payment_url TEXT DEFAULT NULL,
  request_payload LONGTEXT DEFAULT NULL,
  response_payload LONGTEXT DEFAULT NULL,
  notification_payload LONGTEXT DEFAULT NULL,
  verified_payload LONGTEXT DEFAULT NULL,
  failure_reason TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cinetpay_transaction_id (transaction_id),
  KEY idx_cinetpay_inscription (id_inscription),
  KEY idx_cinetpay_status (status),
  CONSTRAINT fk_cinetpay_inscription
    FOREIGN KEY (id_inscription) REFERENCES inscription (id_inscription)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;