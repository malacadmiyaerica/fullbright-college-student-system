-- ============================================================
-- Fullbright College - Student Management System
-- Upgrade script: Digital ID + Student Profile features
-- ------------------------------------------------------------
-- Run this ONCE on an existing database (that already has data)
-- to add the new columns WITHOUT losing enrolled students.
--
--   mysql -u root -p fullbright_college < upgrade_profile.sql
--
-- Fresh installs should import schema.sql instead (already up to date).
-- ============================================================

USE fullbright_college;

ALTER TABLE students
  ADD COLUMN emergency_contact_name VARCHAR(120) NULL DEFAULT NULL AFTER emergency_contact_no,
  ADD COLUMN id_photo               VARCHAR(255) NULL DEFAULT NULL AFTER profile_photo,
  ADD COLUMN profile_photo_opt      ENUM('upload','skip') NOT NULL DEFAULT 'skip' AFTER id_photo;

-- Existing rows stored the mandatory 2x2 photo in profile_photo.
-- Move it into the new id_photo column and leave profile_photo empty
-- so the "2x2 ID photo" fallback is used until the student uploads one.
UPDATE students SET id_photo = profile_photo WHERE id_photo IS NULL;
UPDATE students SET profile_photo = NULL;
