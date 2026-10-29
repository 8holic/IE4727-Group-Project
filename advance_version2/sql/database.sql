-- database.sql
-- J&B Family Clinic booking system: complete fresh database.
-- WARNING: this DROPS the existing jnb_clinic_advance_v2 database and recreates it.
-- Import via phpMyAdmin (Import tab), or:
--   mysql -u root < advance_version2/sql/database.sql

DROP DATABASE IF EXISTS jnb_clinic_advance_v2;

CREATE DATABASE jnb_clinic_advance_v2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jnb_clinic_advance_v2;

CREATE TABLE doctors (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE patients (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  phone         VARCHAR(30)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE appointments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  patient_id    INT          NULL,
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
  UNIQUE KEY uniq_slot (doctor, active_slot),
  KEY idx_patient (patient_id),
  CONSTRAINT fk_appointment_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Demo doctor accounts.
--   Dr Bryan  / bryan@localhost.net  / bryan123
--   Dr Javier / javier@localhost.net / javier123
INSERT INTO doctors (name, email, password_hash) VALUES
  ('Dr Bryan',  'bryan@localhost.net',  '$2y$10$bZyjLPb/bnoHIAvo2vCy/OEaS2N0iTudfw4gfh/G1uzHfTxZZbTou'),
  ('Dr Javier', 'javier@localhost.net', '$2y$10$GhxMKU7RWxdRXwbOjyBMd.aJuOYcc5Chy5vKpLK/S.zueMFsczMBe')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password_hash = VALUES(password_hash);
