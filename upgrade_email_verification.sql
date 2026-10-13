-- ============================================================
-- Fullbright College - Student Management System
-- Migration: Enrollment Email Verification
-- ------------------------------------------------------------
-- Adds a per-email verification token store plus the verified
-- flag on the student record.
--
--   mysql -u root fullbright_college < upgrade_email_verification.sql
-- ============================================================

USE fullbright_college;

-- Verified flag on the student record (set once the enrollment
-- email has been confirmed). Existing rows default to unverified.
ALTER TABLE students
  ADD COLUMN email_verified      TINYINT(1) NOT NULL DEFAULT 0  AFTER email,
  ADD COLUMN email_verified_at   DATETIME   NULL DEFAULT NULL   AFTER email_verified;

-- Pending/confirmed email verification tokens.
--   token_hash : SHA-256 of the secret token emailed to the student
--                (the raw token is never stored).
--   session_id : ties the token to the enrollment browser session.
--   expires_at : token lifetime (24 hours).
CREATE TABLE IF NOT EXISTS email_verifications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email       VARCHAR(120) NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  session_id  VARCHAR(128) NOT NULL,
  expires_at  DATETIME     NOT NULL,
  verified_at DATETIME     NULL DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ev_token (token_hash),
  KEY idx_ev_email (email),
  KEY idx_ev_session (session_id)
) ENGINE=InnoDB;
