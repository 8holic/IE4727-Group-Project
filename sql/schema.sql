-- schema.sql
-- J&B Family Clinic booking system. Full schema.
-- Import via phpMyAdmin (Import tab), or: mysql -u root < schema.sql
-- Safe to re-run: every object uses IF NOT EXISTS.

CREATE DATABASE IF NOT EXISTS jnb_clinic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jnb_clinic;

-- Staff accounts. Only doctors log in, and they can see every appointment.
CREATE TABLE IF NOT EXISTS doctors (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Appointments. Patients never log in: they manage a booking through the
-- manage_token emailed to them (an "edit my appointment" link).
--
-- "doctor" holds the doctor's name. Opening hours are 9:00 AM - 6:00 PM:
--   Dr Bryan  : Mon-Fri
--   Dr Javier : Tue-Sat
--   Clinic closed on Sundays.
CREATE TABLE IF NOT EXISTS appointments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  patient_name  VARCHAR(100) NOT NULL,
  patient_email VARCHAR(150) NOT NULL,
  phone         VARCHAR(30)  NULL,
  doctor        VARCHAR(100) NOT NULL,
  slot_start    DATETIME     NOT NULL,
  status        ENUM('booked','cancelled','completed') NOT NULL DEFAULT 'booked',
  manage_token  CHAR(64)     NULL UNIQUE,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- active_slot equals the booked time while status = 'booked', and is NULL
  -- otherwise. A UNIQUE index ignores NULLs, so a cancelled row keeps its
  -- history but no longer blocks that slot from being rebooked.
  active_slot   DATETIME GENERATED ALWAYS AS (IF(status = 'booked', slot_start, NULL)) STORED,
  UNIQUE KEY uniq_slot (doctor, active_slot)
);

-- How the future features map onto this schema:
--
--   Doctor sees ALL rows:   SELECT * FROM appointments ORDER BY slot_start;
--   Doctor edits any row:   UPDATE appointments SET ... WHERE id = ?;
--   Patient edits their own:UPDATE appointments SET ... WHERE id = ? AND manage_token = ?;
--                           (0 rows affected = not their booking)
--
--   On insert, generate the token with:
--     $token = bin2hex(random_bytes(32));   // 64 hex chars
--   and email a link like:  manage.php?token=$token
--
--   Taken-slot lookup ignores cancelled rows:
--     ... WHERE doctor = ? AND DATE(slot_start) = ? AND status = 'booked'


-- ---------------------------------------------------------------------------
-- Migration for anyone who already created an earlier version of the table.
-- Run once; it keeps existing bookings. (Earlier versions used "location";
-- this renames it to "doctor".)
-- ---------------------------------------------------------------------------
--
-- CREATE TABLE IF NOT EXISTS doctors ( ... );   -- same definition as above
--
-- ALTER TABLE appointments
--   CHANGE COLUMN location doctor VARCHAR(100) NOT NULL,
--   ADD COLUMN status       ENUM('booked','cancelled','completed') NOT NULL DEFAULT 'booked',
--   ADD COLUMN manage_token CHAR(64) NULL UNIQUE,
--   ADD COLUMN updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   ADD COLUMN active_slot  DATETIME GENERATED ALWAYS AS (IF(status = 'booked', slot_start, NULL)) STORED,
--   DROP INDEX uniq_slot,
--   ADD UNIQUE KEY uniq_slot (doctor, active_slot);
--
-- UPDATE appointments
--   SET manage_token = SHA2(CONCAT(UUID(), RAND()), 256)
--   WHERE manage_token IS NULL;
