-- ============================================================
-- SignKu Database Schema
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS signku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE signku;

-- ── USERS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  email       VARCHAR(200) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('mahasiswa','dosen','staff','admin') DEFAULT 'mahasiswa',
  otp_secret  VARCHAR(64)  DEFAULT NULL,
  otp_enabled TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── OTP RESET TOKENS ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_reset_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token      VARCHAR(128) NOT NULL UNIQUE,
  expires_at DATETIME     NOT NULL,
  used       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── DIGITAL ID REQUESTS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS digital_id_requests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  role        VARCHAR(100) NOT NULL,
  passphrase  VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  status      ENUM('pending','approved','rejected') DEFAULT 'pending',
  is_ready    TINYINT(1)   NOT NULL DEFAULT 0,
  is_sent     TINYINT(1)   NOT NULL DEFAULT 0,
  valid_until DATE         DEFAULT NULL,
  notes       TEXT         DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── SIGNATURE REQUESTS (DOCUMENTS) ───────────────────────
CREATE TABLE IF NOT EXISTS signing_requests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  filename_orig VARCHAR(255) NOT NULL,
  filename_stored VARCHAR(255) NOT NULL,
  filename_signed VARCHAR(255) DEFAULT NULL,
  sign_x       FLOAT        DEFAULT NULL,
  sign_y       FLOAT        DEFAULT NULL,
  sign_page    INT          DEFAULT 1,
  sign_width   FLOAT        DEFAULT 200,
  sign_height  FLOAT        DEFAULT 80,
  status       ENUM('uploaded','signed','failed') DEFAULT 'uploaded',
  otp_verified TINYINT(1)   NOT NULL DEFAULT 0,
  signed_at    DATETIME     DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── SIGNATURE APPEARANCES ─────────────────────────────────
CREATE TABLE IF NOT EXISTS signature_appearances (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  name         VARCHAR(100) NOT NULL,
  image_path   VARCHAR(255) DEFAULT NULL,
  show_name    TINYINT(1)   NOT NULL DEFAULT 1,
  show_date    TINYINT(1)   NOT NULL DEFAULT 1,
  show_logo    TINYINT(1)   NOT NULL DEFAULT 0,
  is_default   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── ACTIVITY LOG ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED DEFAULT NULL,
  action     VARCHAR(100) NOT NULL,
  detail     TEXT         DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── SEED: default admin ────────────────────────────────────
INSERT IGNORE INTO users (name, email, password, role, otp_enabled)
VALUES (
  'Admin SignKu',
  'admin@signku.id',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uEutDBv8u', -- password: password
  'admin',
  0
);

-- ── UPDATE: Add doc_hash for verification ─────────────────
ALTER TABLE signing_requests ADD COLUMN IF NOT EXISTS doc_hash VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 hash of signed PDF';

-- ── DIGITAL ID direct creation table update ───────────────
-- (existing table supports status=approved directly)
