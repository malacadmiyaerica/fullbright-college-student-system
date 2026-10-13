-- ============================================================
-- STUDENT MANAGEMENT SYSTEM OF FULLBRIGHT COLLEGE
-- Database Schema (Student module only)
-- ------------------------------------------------------------
-- Import via MySQL (e.g. XAMPP phpMyAdmin):
--   CREATE DATABASE THEN Run this script, or run:
--   mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS fullbright_college
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE fullbright_college;

-- Clean slate (development). Order respects foreign keys.
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS requirements;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS students;

-- ------------------------------------------------------------
-- Students  (Registration = Enrollment, single process)
-- login:  username = student_id,  password = a random password
-- generated at enrollment and shown once on the receipt.
-- password_hash stores the hash (bcrypt/Argon2 via PASSWORD_DEFAULT).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
  id                    INT UNSIGNED        NOT NULL AUTO_INCREMENT,
  student_id            VARCHAR(12)         NOT NULL,          -- YYSSXXXXXX e.g. 2401010067
  first_name            VARCHAR(60)         NOT NULL,
  middle_name           VARCHAR(60)         NULL DEFAULT NULL,
  last_name             VARCHAR(60)         NOT NULL,
  suffix                VARCHAR(10)         NULL DEFAULT NULL, -- Jr., Sr., III ...
  email                 VARCHAR(120)        NOT NULL,          -- verified enrollment email (also used for notifications)
  email_verified        TINYINT(1)          NOT NULL DEFAULT 0, -- 1 once the enrollment email link was clicked
  email_verified_at     DATETIME            NULL DEFAULT NULL, -- when the email was verified
  birthday              DATE                NOT NULL,
  contact_no            VARCHAR(30)         NOT NULL,
  emergency_contact_no  VARCHAR(30)         NOT NULL,          -- emergency contact number (read-only after enrollment)
  emergency_contact_name VARCHAR(120)       NULL DEFAULT NULL, -- emergency contact person (read-only after enrollment)
  marital_status        ENUM('Single','Married') NOT NULL DEFAULT 'Single',
  permanent_address     VARCHAR(255)        NULL DEFAULT NULL, -- student's permanent/home address (read-only after enrollment)
  current_address       VARCHAR(255)        NULL DEFAULT NULL, -- student's current address (editable)
  same_address          TINYINT(1)          NOT NULL DEFAULT 0, -- 1 when current = permanent ("Same as Permanent Address")
  department            VARCHAR(120)        NOT NULL,          -- Engineering & Communication Technology (ECT)
  program               VARCHAR(150)        NOT NULL,          -- BSIT, BSCpE, BSCS, BSECE
  id_photo              VARCHAR(255)        NULL DEFAULT NULL, -- required 2x2 ID picture from enrollment
  profile_photo         VARCHAR(255)        NULL DEFAULT NULL, -- optional uploaded profile photo
  profile_photo_opt     ENUM('upload','skip') NOT NULL DEFAULT 'skip', -- student's profile photo choice
  exam_code             VARCHAR(20)         NULL DEFAULT NULL, -- linked entrance exam
  exam_score            TINYINT UNSIGNED    NULL DEFAULT NULL, -- 0 - 15
  section               VARCHAR(10)         NULL DEFAULT NULL, -- auto-assigned, e.g. IT1A (max 50 students)
  password_hash         VARCHAR(255)        NULL DEFAULT NULL, -- set when student changes password
  reset_token           VARCHAR(64)         NULL DEFAULT NULL, -- hashed password-reset token (forgot password)
  reset_expires         DATETIME            NULL DEFAULT NULL, -- token expiry (e.g. +30 minutes)
  created_at            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_students_email (email),
  UNIQUE KEY uq_students_student_id (student_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Email verifications (enrollment email verification tokens)
-- token_hash : SHA-256 of the secret token emailed to the student
-- session_id : enrollment browser session the token is tied to
-- expires_at : token lifetime (24 hours)
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- Requirements (uploaded documents that come with the student)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS requirements (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  student_id    INT UNSIGNED  NOT NULL,
  document_name VARCHAR(150)  NOT NULL,
  file_path     VARCHAR(255)  NOT NULL,
  uploaded_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_requirements_student (student_id),
  CONSTRAINT fk_requirements_student
    FOREIGN KEY (student_id) REFERENCES students (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Exams (generated entrance exams, accessible by code / QR)
-- questions: JSON [ {subject, q, options[4], answer} ]
-- answers:   JSON [ selected index ... ], score 0-15
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exams (
  code       VARCHAR(20)   NOT NULL,
  questions  JSON          NOT NULL,
  answers    JSON          NULL DEFAULT NULL,
  score      TINYINT UNSIGNED NULL DEFAULT NULL,
  taken_at   TIMESTAMP     NULL DEFAULT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (code)
) ENGINE=InnoDB;