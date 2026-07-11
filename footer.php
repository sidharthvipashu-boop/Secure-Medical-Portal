# Secure Medical Records Portal

A university **Systems Security** project: a hospital web application where patient
medical records are **encrypted at rest**, **protected against tampering**, and
**replicated across multiple hospital sites**.

Built with **HTML5, CSS3, vanilla JavaScript, PHP and MySQL** on **XAMPP** — no
frameworks, so every line is easy to read and explain.

---

## The three security concepts (and exactly where they live)

| # | Concept | Technique | Implemented in |
|---|---------|-----------|----------------|
| **1** | `SEC-PRJ-12A_25` — Secure Storage in Edge Cloud | **AES-256-CBC** encryption of sensitive fields & files before they touch the database | `includes/crypto.php` → `encrypt_data()` / `decrypt_data()`; used in `doctor/add_record.php`, `edit_record.php`, `view_record.php`, `patient/view_record.php` |
| **2** | `SEC-PRJ-7E_25` — Fake Data Prevention with Conventional Cryptotools | **SHA-256** integrity hash stored with each record/file, re-checked on every view/download | `includes/crypto.php` → `make_hash()` / `verify_integrity()` / `make_file_hash()`; checked in the view + `download.php` |
| **3** | `SEC-PRJ-6_23` — Federated File System Encoding at the Edge | Encrypt once, **replicate the ciphertext to 3 branch folders** (Hospital A/B/C), read with fail-over | `includes/functions.php` → `store_federated_report()` / `read_federated_report()`; used in `doctor/upload_report.php`, `download.php`, `admin/manage_storage.php` |

Supporting techniques used throughout: **bcrypt** password hashing, **PDO prepared
statements** (anti SQL-injection), **sessions + role checks** (so decryption is only
for authorised users), and **CSRF tokens** on every form.

---

## Setup (XAMPP) — 4 steps

1. **Copy the project** into your XAMPP web root so the path is:
   `C:\xampp\htdocs\SecureMedicalPortal\`
   > The folder name **must** be `SecureMedicalPortal` (it matches `BASE_URL` in
   > `config/config.php`). If you rename it, update `BASE_URL` to match.

2. **Start Apache and MySQL** in the XAMPP Control Panel.

3. **Create the database:** open <http://localhost/phpmyadmin> → **Import** →
   choose `database/secure_medical_portal.sql` → **Go**.
   This creates all tables and the demo users/doctors/patients.

4. **Add the encrypted demo data:** visit
   <http://localhost/SecureMedicalPortal/database/seed.php> **once**.
   This inserts the AES-encrypted medical records, SHA-256 hashes, and writes the
   federated (encrypted) report copies into the three branch folders.

Then open <http://localhost/SecureMedicalPortal/> and log in.

---

## Demo logins

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `Admin@123` |
| Doctor (Cardiology, Hospital A) | `dr.smith` | `Doctor@123` |
| Doctor (Neurology, Hospital B) | `dr.jones` | `Doctor@123` |
| Doctor (Orthopedics, Hospital C) | `dr.patel` | `Doctor@123` |
| Patient | `john.doe` | `Patient@123` |
| Patient | `jane.roe` | `Patient@123` |
| Patient | `michael.brown` | `Patient@123` |
| Patient | `sarah.wilson` | `Patient@123` |
| Patient (pending approval) | `david.lee` | `Patient@123` |

`david.lee` is intentionally **pending** so you can demonstrate the admin approval
flow (Admin → Manage Patients → Approve).

---

## How to demonstrate each concept in the viva

### Idea 1 — Encryption at rest (AES-256)
1. Log in as `dr.smith`, open any record (**My Patients → Records → Open**).
2. Scroll to **“Behind the Scenes”** — it shows the record’s **ciphertext** exactly
   as stored, plus the SHA-256 hash. The plaintext above it was decrypted live.
3. Optional proof: open phpMyAdmin → `medical_records` table → the `*_enc` columns
   are unreadable base64 ciphertext.

### Idea 2 — Tamper detection (SHA-256)  ← great live demo
1. In phpMyAdmin, open `medical_records`, pick any row, and **edit the
   `diagnosis_enc` value** (change one character) — this simulates an attacker
   altering the database **without** updating the hash.
2. Refresh that record in the portal. You’ll see a red banner:
   **“Integrity Violation Detected”**, and the event appears in
   **Admin → Security Logs** as `INTEGRITY_FAIL`.
3. (Same idea works for report files and is checked inside `download.php`.)

### Idea 3 — Federated multi-site storage
1. Log in as a doctor → open a patient → **Upload Report** (any PDF/JPG/PNG/TXT).
2. You’ll see **“Record stored securely across three hospital branches.”**
3. Log in as `admin` → **Branch Storage**: a live grid shows a green **✓ stored**
   for Hospital A, B and C. Check the folders on disk — `uploads/hospital_A|B|C/`
   each hold an identical encrypted `.enc` copy.
4. Delete the copy from `hospital_A` and download the report anyway — the system
   **fails over** to Hospital B/C (resilience).

---

## Website flow (as required)

```
Patient registers ─► Admin approves account ─► Doctor logs in ─► selects patient
   ─► enters diagnosis ─► AES-256 encrypts ─► SHA-256 hash generated
   ─► encrypted record stored ─► encrypted copies stored in all 3 branch folders
Patient logs in ─► requests record ─► hash verified ─► record decrypted ─► displayed
   (if the hash fails ─► "Integrity Violation Detected")
```

---

## Folder structure

```
SecureMedicalPortal/
├── index.php            Home (explains the 3 concepts)
├── about.php            Project background
├── login.php            Authentication (bcrypt verify + session)
├── register.php         Patient self-registration (pending)
├── logout.php           Secure session destroy
├── profile.php          View own profile (all roles)
├── download.php         Secure download (federated read + hash verify + decrypt)
├── error.php            Friendly error / access-denied page
├── css/style.css        Hand-written responsive hospital theme
├── js/script.js         Vanilla JS (confirm dialogs, flash auto-hide)
├── config/
│   ├── config.php       App constants + AES key + branch list (Idea 1 & 3)
│   ├── database.php     PDO connection (prepared statements)
│   └── .htaccess        Blocks direct web access to config
├── includes/
│   ├── crypto.php       ★ AES-256 (Idea 1) + SHA-256 (Idea 2)
│   ├── functions.php    ★ Federated storage (Idea 3) + logging + CSRF helpers
│   ├── auth.php         Login/role guards (restricts decryption to authorised users)
│   ├── header.php / footer.php / sidebar.php   Shared UI
├── admin/
│   ├── dashboard.php    Stats + recent security events
│   ├── manage_doctors.php   Add / suspend doctors
│   ├── manage_patients.php  Approve / suspend patients
│   ├── manage_storage.php   Federated branch monitor (Idea 3)
│   └── view_logs.php    Security audit log (Idea 2 tamper events)
├── doctor/
│   ├── dashboard.php
│   ├── patients.php     Patient list + search
│   ├── add_record.php   ★ encrypt + hash on create
│   ├── edit_record.php  ★ re-encrypt + re-hash on update
│   ├── view_record.php  ★ decrypt + verify integrity
│   ├── view_reports.php Per-patient records & reports
│   └── upload_report.php ★ hash + encrypt + replicate to 3 branches
├── patient/
│   ├── dashboard.php
│   ├── records.php      Own records (read-only)
│   └── view_record.php  ★ decrypt + verify (with ownership check)
├── uploads/
│   ├── hospital_A/ hospital_B/ hospital_C/   Simulated branches (encrypted copies)
│   └── .htaccess        Blocks direct access to encrypted files
└── database/
    ├── secure_medical_portal.sql   Schema + structural seed
    └── seed.php                    Inserts encrypted records + federated files
```

---

## Database tables

`users`, `patients`, `doctors`, `medical_records`, `uploaded_reports`,
`hospital_storage`, `security_logs` — all with primary keys, foreign keys,
indexes and constraints (see the SQL file’s comments).

---

## Security notes / limitations (be honest in the viva)

- This is an **educational simulation**. A real edge-cloud/federated deployment
  would use networked nodes, a proper key-management service (not a key in a file),
  HTTPS, and erasure-coding/MPU chunking instead of three local folders.
- The AES key lives in `config/config.php` for XAMPP convenience; in production it
  would come from an environment variable or secrets manager and sit outside the
  web root.
- HTTPS is assumed to be provided by the hosting environment; enable it in XAMPP
  for a fully secure transport layer.
```
