CREATE DATABASE IF NOT EXISTS secure_medical_portal
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE secure_medical_portal;

DROP TABLE IF EXISTS hospital_storage;
DROP TABLE IF EXISTS uploaded_reports;
DROP TABLE IF EXISTS medical_records;
DROP TABLE IF EXISTS security_logs;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','doctor','patient') NOT NULL,
    status        ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email),
    KEY idx_role_status (role, status)
) ENGINE=InnoDB;

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
    CONSTRAINT fk_patient_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE doctors (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    specialization  VARCHAR(100),
    phone           VARCHAR(30),
    hospital_branch VARCHAR(50),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doctor_user (user_id),
    CONSTRAINT fk_doctor_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE medical_records (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    doctor_id        INT NOT NULL,
    diagnosis_enc    TEXT NOT NULL,
    treatment_enc    TEXT,
    prescription_enc TEXT,
    notes_enc        TEXT,
    record_hash      CHAR(64) NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mr_patient (patient_id),
    KEY idx_mr_doctor (doctor_id),
    CONSTRAINT fk_mr_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_mr_doctor FOREIGN KEY (doctor_id)
        REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE uploaded_reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    record_id        INT NULL,
    patient_id       INT NOT NULL,
    doctor_id        INT NOT NULL,
    original_filename VARCHAR(200) NOT NULL,
    stored_filename  VARCHAR(200) NOT NULL,
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

CREATE TABLE hospital_storage (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    report_id   INT NOT NULL,
    branch_name VARCHAR(50) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    status      ENUM('stored','missing') NOT NULL DEFAULT 'stored',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hs_report (report_id),
    CONSTRAINT fk_hs_report FOREIGN KEY (report_id)
        REFERENCES uploaded_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE security_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    event_type  VARCHAR(40) NOT NULL,
    description VARCHAR(255) NOT NULL,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_type (event_type),
    KEY idx_log_time (created_at),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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

INSERT INTO doctors (id, user_id, full_name, specialization, phone, hospital_branch, created_at) VALUES
(1, 2, 'Dr. John Smith',  'Cardiology',   '+1-202-555-0111', 'Hospital A', '2025-01-11 09:16:00'),
(2, 3, 'Dr. Emily Jones', 'Neurology',    '+1-202-555-0122', 'Hospital B', '2025-01-11 09:21:00'),
(3, 4, 'Dr. Raj Patel',   'Orthopedics',  '+1-202-555-0133', 'Hospital C', '2025-01-11 09:26:00');

INSERT INTO patients (id, user_id, full_name, dob, gender, phone, address, blood_group, created_at) VALUES
(1, 5, 'John Doe',      '1988-06-14', 'Male',   '+1-303-555-0201', '12 Elm Street, Springfield',  'O+',  '2025-02-01 10:05:00'),
(2, 6, 'Jane Roe',      '1995-03-22', 'Female', '+1-303-555-0202', '48 Oak Avenue, Riverdale',    'A-',  '2025-02-02 11:35:00'),
(3, 7, 'Michael Brown', '1979-11-30', 'Male',   '+1-303-555-0203', '7 Pine Road, Fairview',       'B+',  '2025-02-03 14:15:00'),
(4, 8, 'Sarah Wilson',  '2001-08-09', 'Female', '+1-303-555-0204', '90 Maple Lane, Lakeside',     'AB+', '2025-02-04 08:50:00'),
(5, 9, 'David Lee',     '1992-01-27', 'Male',   '+1-303-555-0205', '5 Cedar Court, Hilltop',      'O-',  '2025-02-05 16:25:00');

INSERT INTO security_logs (user_id, event_type, description, ip_address, created_at) VALUES
(1, 'SYSTEM_INIT', 'Database imported and demo accounts created.', '127.0.0.1', '2025-02-05 16:30:00'),
(2, 'LOGIN',       'Doctor dr.smith logged in successfully.',      '127.0.0.1', '2025-02-06 09:00:00');

ALTER TABLE users     AUTO_INCREMENT = 10;
ALTER TABLE doctors   AUTO_INCREMENT = 4;
ALTER TABLE patients  AUTO_INCREMENT = 6;
