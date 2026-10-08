-- MédiRDV — schéma de la base de données (MySQL 5.7+ / MariaDB 10.3+)
CREATE DATABASE IF NOT EXISTS medirdv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medirdv;

CREATE TABLE IF NOT EXISTS specialties (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  icon VARCHAR(40) NOT NULL DEFAULT 'bi-heart-pulse'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role          ENUM('patient','practitioner') NOT NULL,
  first_name    VARCHAR(60)  NOT NULL,
  last_name     VARCHAR(60)  NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  phone         VARCHAR(30)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS practitioners (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL UNIQUE,
  specialty_id  INT UNSIGNED NOT NULL,
  city          VARCHAR(80)  NOT NULL,
  address       VARCHAR(190) NOT NULL,
  bio           TEXT NULL,
  slot_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  price_fcfa    INT UNSIGNED NULL,
  auto_confirm  TINYINT(1) NOT NULL DEFAULT 1,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prac_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prac_spec FOREIGN KEY (specialty_id) REFERENCES specialties(id),
  INDEX idx_prac_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Horaires hebdomadaires récurrents (weekday : 1 = lundi ... 7 = dimanche)
CREATE TABLE IF NOT EXISTS availabilities (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practitioner_id INT UNSIGNED NOT NULL,
  weekday         TINYINT UNSIGNED NOT NULL,
  start_time      TIME NOT NULL,
  end_time        TIME NOT NULL,
  CONSTRAINT fk_av_prac FOREIGN KEY (practitioner_id) REFERENCES practitioners(id) ON DELETE CASCADE,
  INDEX idx_av (practitioner_id, weekday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Congés / fermetures exceptionnelles
CREATE TABLE IF NOT EXISTS time_off (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practitioner_id INT UNSIGNED NOT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE NOT NULL,
  reason          VARCHAR(150) NULL,
  CONSTRAINT fk_to_prac FOREIGN KEY (practitioner_id) REFERENCES practitioners(id) ON DELETE CASCADE,
  INDEX idx_to (practitioner_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS appointments (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id       INT UNSIGNED NOT NULL,
  practitioner_id  INT UNSIGNED NOT NULL,
  start_at         DATETIME NOT NULL,
  end_at           DATETIME NOT NULL,
  status           ENUM('pending','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  reason           VARCHAR(255) NULL,
  cancelled_by     ENUM('patient','practitioner') NULL,
  cancelled_at     DATETIME NULL,
  reminder_sent    TINYINT(1) NOT NULL DEFAULT 0,
  reminder_sent_at DATETIME NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ap_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_prac    FOREIGN KEY (practitioner_id) REFERENCES practitioners(id) ON DELETE CASCADE,
  INDEX idx_ap_prac (practitioner_id, start_at),
  INDEX idx_ap_patient (patient_id, start_at),
  INDEX idx_ap_reminder (status, reminder_sent, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email   VARCHAR(190) NOT NULL,
  subject    VARCHAR(255) NOT NULL,
  status     ENUM('sent','failed','logged') NOT NULL,
  error      VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(190) NOT NULL,
  ip           VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  INDEX idx_la (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pr (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO specialties (name, icon) VALUES
 ('Médecine générale','bi-heart-pulse'),
 ('Pédiatrie','bi-emoji-smile'),
 ('Gynécologie','bi-gender-female'),
 ('Cardiologie','bi-activity'),
 ('Dermatologie','bi-droplet'),
 ('Ophtalmologie','bi-eye'),
 ('Dentisterie','bi-emoji-grin'),
 ('ORL','bi-ear'),
 ('Psychologie','bi-chat-heart'),
 ('Kinésithérapie','bi-person-arms-up');
