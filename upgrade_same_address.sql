-- ============================================================
-- Fullbright College - Student Management System
-- Migration: "Same as Permanent Address" flag
-- ------------------------------------------------------------
-- Adds a flag recording whether the student ticked
-- "Same as Permanent Address" during enrollment / profile edit.
--
--   mysql -u root fullbright_college < upgrade_same_address.sql
-- ============================================================

USE fullbright_college;

ALTER TABLE students
  ADD COLUMN same_address TINYINT(1) NOT NULL DEFAULT 0 AFTER current_address;
