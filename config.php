-- ============================================================================
--  secure_medical_portal.sql
--  Database schema + seed data for the Secure Medical Records Portal
-- ----------------------------------------------------------------------------
--  HOW TO IMPORT (XAMPP):
--    1. Start Apache + MySQL in the XAMPP control panel.
--    2. Open http://localhost/phpmyadmin
--    3. Click "Import", choose this file, and run it. It creates the database,
--       all tables, and inserts the demo users/doctors/patients.
--    4. THEN open http://localhost/SecureMedicalPortal/database/seed.php ONCE
--       to insert the ENCRYPTED medical records + hashed reports + federated
--       files (those must be produced by PHP so the encryption keys line up).
--
--  SECURITY NOTES EMBEDDED IN THE SCHEMA:
--    - Sensitive medical fields are stored as *_enc (AES-256 ciphertext, Idea 1).
--    - Each medical_records row carries a record_hash (SHA-256, Idea 2).
--    - uploaded_reports carries a file_hash (SHA-256, Idea 2).
--    - hospital_storage records WHERE each encrypted copy was replicated (Idea 3).
--    - security_logs stores an audit trail (including integrity violations).
-- ============================================================================

-- Create the database and select it.
CREATE DATABASE IF NOT EXISTS secure_medical_portal
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE secure_medical_portal;

-- Start clean if re-importing (drop children before parents for FK safety).
DROP TABLE IF EXISTS hospital_storage;
DROP TABLE IF EXISTS uploaded_reports;
DROP TABLE IF EXISTS medical_records;
DROP TABLE IF EXISTS security_logs;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

-- ============================================================================
--  TABLE: users
--  Every person who can log in. Password stored ONLY as a bcrypt hash.
-- ============================================================================
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    -- We NEVER store plain passwords. password_hash() (bcrypt) output goes here.
    password_hash VARCHAR(255) NOT NULL,
    -- Role decides what the user is allowed to do (access control).
    role          ENUM('admin','doctor','patient') NOT NULL,
    -- 'pending' patients must be approved by an admin before they can log in.
    status        ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Constraints: usernames and emails must be unique across the system.
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email),
    -- Index to speed up the very common "find by role/status" admin queries.
    KEY idx_role_status (role, status)
) ENGINE=InnoDB;

-- ============================================================================
--  TABLE: patients
--  One row per patient, linked 1-to-1 with a 'patient' user account.
-- ============================================================================
CREATE TABLE patients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    full_name   VARCHAR(120) NOT NULL,
    dob         DATE,
    gender      ENUM('Male','Female','Other'),
    phone       VARCHAR(30),
    address     VARCHAR(255),
    blood_group VARCHAR(5),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_user (user_id),
    -- FK: if the user account is deleted, remove the patient profile too.
    CONSTRAINT fk_patient_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
--  TABLE: doctors
--  One row per doctor, linked 1-to-1 with a 'doctor' user account.
-- ============================================================================
CREATE TABLE doctors (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    specialization  VARCHAR(100),
    phone           VARCHAR(30),
    -- The branch a doctor primarily belongs to (ties into Idea 3 sites).
    hospital_branch VARCHAR(50),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doctor_user (user_id),
    CONSTRAINT fk_doctor_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
--  TABLE: medical_records   <-- Ideas 1 & 2 live here
--  Sensitive fields are AES-256 ciphertext (*_enc). record_hash is the SHA-256
--  integrity fingerprint of the ORIGINAL plain-text values.
-- ============================================================================
CREATE TABLE medical_records (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    doctor_id        INT NOT NULL,
    -- Idea 1: these hold encrypted text, never readable in the database.
    diagnosis_enc    TEXT NOT NULL,
    treatment_enc    TEXT,
    prescription_enc TEXT,
    notes_enc        TEXT,
    -- Idea 2: SHA-256 fingerprint (64 hex chars) used to detect tampering.
    record_hash      CHAR(64) NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Indexes to quickly list a patient's or doctor's records.
    KEY idx_mr_patient (patient_id),
    KEY idx_mr_doctor (doctor_id),
    CONSTRAINT fk_mr_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_mr_doctor FOREIGN KEY (doctor_id)
        REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
--  TABLE: uploaded_reports   <-- Ideas 2 & 3
--  Metadata for report files. The file itself is stored ENCRYPTED across the
--  three branch folders (see hospital_storage). file_hash = SHA-256 of original.
-- ============================================================================
CREATE TABLE uploaded_reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    record_id        INT NULL,           -- optional link to a medical record
    patient_id       INT NOT NULL,
    doctor_id        INT NOT NULL,
    original_filename VARCHAR(200) NOT NULL,
    -- Unique name used inside every branch folder (avoids collisions/guessing).
    stored_filename  VARCHAR(200) NOT NULL,
    -- Idea 2: fingerprint of the ORIGINAL file, checked on every download.
    file_hash        CHAR(64) NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rep_patient (patient_id),
    KEY idx_rep_doctor (doctor_id),
    CONSTRAINT fk_rep_record FOREIGN KEY (record_id)
        REFERENCES medical_records(id) ON DELETE SET NULL,
    CONSTRAINT fk_rep_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_rep_doctor FOREIGN KEY (doctor_id)
        REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
--  TABLE: hospital_storage   <-- Idea 3 (Federated multi-site storage)
--  One row per (report, branch): proves an encrypted copy exists at each site.
-- ============================================================================
CREATE TABLE hospital_storage (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    report_id   INT NOT NULL,
    branch_name VARCHAR(50) NOT NULL,     -- 'Hospital A' / 'B' / 'C'
    file_path   VARCHAR(255) NOT NULL,    -- path to the encrypted copy on disk
    status      ENUM('stored','missing') NOT NULL DEFAULT 'stored',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hs_report (report_id),
    CONSTRAINT fk_hs_report FOREIGN KEY (report_id)
        REFERENCES uploaded_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
--  TABLE: security_logs   <-- audit trail (supports Idea 2 tamper alerts)
--  Records logins, record creation, and INTEGRITY VIOLATION events.
-- ============================================================================
CREATE TABLE security_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,                 -- who caused the event (null if guest)
    event_type  VARCHAR(40) NOT NULL,     -- e.g. LOGIN, RECORD_CREATE, INTEGRITY_FAIL
    description VARCHAR(255) NOT NULL,
    ip_address  VARCHAR(45),              -- source IP (IPv4/IPv6)
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_type (event_type),
    KEY idx_log_time (created_at),
    -- If a user is deleted we keep the log but null the reference.
    CONSTRAINT fk_log_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
--  SEED DATA (structural, non-encrypted)
--  Passwords below are REAL bcrypt hashes. Log-in credentials:
--     Admin    ->  username: admin          password: Admin@123
--     Doctors  ->  dr.smith / dr.jones / dr.patel     password: Doctor@123
--     Patients ->  john.doe / jane.roe / michael.brown / sarah.wilson  password: Patient@123
--     Pending  ->  david.lee (password Patient@123) -- awaits admin approval
-- ============================================================================

-- ---- Users -----------------------------------------------------------------
INSERT INTO users (id, username, email, password_hash, role, status, created_at) VALUES
(1, 'admin',        'admin@hospital.test',        '$2y$10$tfWQkJjJ18SDq7yNOy4l2.3wBdwticejIEzFyo0MatYGAhlAgLV1C', 'admin',   'active',  '2025-01-10 09:00:00'),
(2, 'dr.smith',     'smith@hospital.test',        '$2y$10$KhF4vF0lNzCEdlgYQgiPbePiL7mgT0Q9henGOJy1k29zyjpxXj8k6', 'doctor',  'active',  '2025-01-11 09:15:00'),
(3, 'dr.jones',     'jones@hospital.test',        '$2y$10$G9f.5xOicBZAdha42lOh5u5eFPD3.EBFkYnMBnfriEvYp0L138CTG', 'doctor',  'active',  '2025-01-11 09:20:00'),
(4, 'dr.patel',     'patel@hospital.test',        '$2y$10$Doc8.WMbuDEaphtIaO/Okew4/hawB4eQBz3gSR61Ca54BGDWJH0O2', 'doctor',  'active',  '2025-01-11 09:25:00'),
(5, 'john.doe',     'john.doe@mail.test',         '$2y$10$qDPXWG2SxMaG8cOqNLx4tuKVrvuRJEnsT8D1uiK2AlxzAFkULSRsS', 'patient', 'active',  '2025-02-01 10:00:00'),
(6, 'jane.roe',     'jane.roe@mail.test',         '$2y$10$6I3Z25ClqWHJ8Toi/RjNR.QX.kxcWPwHI0JmJUulafwWD14iGdm3e', 'patient', 'active',  '2025-02-02 11:30:00'),
(7, 'michael.brown','michael.brown@mail.test',    '$2y$10$5rW/MxuUHAqfHnK08ze5i.HocME6nDFkqHDsNMmUlHQ8sM0sli3oG', 'patient', 'active',  '2025-02-03 14:10:00'),
(8, 'sarah.wilson', 'sarah.wilson@mail.test',     '$2y$10$mjmsktrmZfsM7YiqWNq1JOdPMtUf3qia1HBhi8joadbWT9z5TSu/i', 'patient', 'active',  '2025-02-04 08:45:00'),
(9, 'david.lee',    'david.lee@mail.test',        '$2y$10$7xAcMBO4Le7sWwJL9SwFzO1rKb/WhrW4UdjRP3/wrYrf1X.b/j/RC', 'patient', 'pending', '2025-02-05 16:20:00');

-- ---- Doctors ---------------------------------------------------------------
INSERT INTO doctors (id, user_id, full_name, specialization, phone, hospital_branch, created_at) VALUES
(1, 2, 'Dr. John Smith',  'Cardiology',   '+1-202-555-0111', 'Hospital A', '2025-01-11 09:16:00'),
(2, 3, 'Dr. Emily Jones', 'Neurology',    '+1-202-555-0122', 'Hospital B', '2025-01-11 09:21:00'),
(3, 4, 'Dr. Raj Patel',   'Orthopedics',  '+1-202-555-0133', 'Hospital C', '2025-01-11 09:26:00');

-- ---- Patients --------------------------------------------------------------
INSERT INTO patients (id, user_id, full_name, dob, gender, phone, address, blood_group, created_at) VALUES
(1, 5, 'John Doe',      '1988-06-14', 'Male',   '+1-303-555-0201', '12 Elm Street, Springfield',  'O+',  '2025-02-01 10:05:00'),
(2, 6, 'Jane Roe',      '1995-03-22', 'Female', '+1-303-555-0202', '48 Oak Avenue, Riverdale',    'A-',  '2025-02-02 11:35:00'),
(3, 7, 'Michael Brown', '1979-11-30', 'Male',   '+1-303-555-0203', '7 Pine Road, Fairview',       'B+',  '2025-02-03 14:15:00'),
(4, 8, 'Sarah Wilson',  '2001-08-09', 'Female', '+1-303-555-0204', '90 Maple Lane, Lakeside',     'AB+', '2025-02-04 08:50:00'),
(5, 9, 'David Lee',     '1992-01-27', 'Male',   '+1-303-555-0205', '5 Cedar Court, Hilltop',      'O-',  '2025-02-05 16:25:00');

-- ---- A couple of starter log entries (seed.php will add more) ---------------
INSERT INTO security_logs (user_id, event_type, description, ip_address, created_at) VALUES
(1, 'SYSTEM_INIT', 'Database imported and demo accounts created.', '127.0.0.1', '2025-02-05 16:30:00'),
(2, 'LOGIN',       'Doctor dr.smith logged in successfully.',      '127.0.0.1', '2025-02-06 09:00:00');

-- Keep AUTO_INCREMENT counters tidy after manual IDs.
ALTER TABLE users     AUTO_INCREMENT = 10;
ALTER TABLE doctors   AUTO_INCREMENT = 4;
ALTER TABLE patients  AUTO_INCREMENT = 6;

-- End of schema + structural seed. Now run database/seed.php for encrypted data.
