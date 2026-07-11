<?php
/**
 * =============================================================================
 *  doctor/add_record.php  --  Create a new medical record
 * =============================================================================
 *  THIS PAGE IS WHERE IDEAS 1 & 2 HAPPEN ON CREATION.
 *
 *    Idea 1 (Secure Storage / AES-256):
 *        Diagnosis, treatment, prescription and notes are each ENCRYPTED with
 *        encrypt_data() before they are inserted into MySQL. The database only
 *        ever receives ciphertext.
 *
 *    Idea 2 (Fake Data Prevention / SHA-256):
 *        A single SHA-256 hash is computed over the ORIGINAL plain-text fields
 *        and stored alongside the record. This fingerprint is re-checked every
 *        time the record is viewed, to detect tampering.
 *
 *        Plain text --> encrypt_data() --> ciphertext -----> DB (*_enc columns)
 *        Plain text --> make_hash()    --> SHA-256 hash ---> DB (record_hash)
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

// Resolve this doctor's id.
$dq = $pdo->prepare('SELECT id FROM doctors WHERE user_id = :uid');
$dq->execute([':uid' => $_SESSION['user_id']]);
$doctorId = $dq->fetchColumn();

$errors = [];
$preselectPatient = (int) ($_GET['patient_id'] ?? 0);

// ---- Handle submission ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $patientId    = (int) ($_POST['patient_id'] ?? 0);
    $diagnosis    = trim($_POST['diagnosis'] ?? '');
    $treatment    = trim($_POST['treatment'] ?? '');
    $prescription = trim($_POST['prescription'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    // Validate: patient must exist & be active; diagnosis is mandatory.
    if ($patientId <= 0) $errors[] = 'Please choose a patient.';
    if ($diagnosis === '') $errors[] = 'Diagnosis is required.';

    if (!$errors) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM patients p JOIN users u ON u.id=p.user_id
                              WHERE p.id = :id AND u.status = "active"');
        $chk->execute([':id' => $patientId]);
        if ($chk->fetchColumn() == 0) $errors[] = 'Selected patient is not valid.';
    }

    if (!$errors) {
        /* ---------- IDEA 2: integrity fingerprint of the ORIGINAL text -------- */
        // We hash the plain text (in a fixed field order) BEFORE encrypting.
        $recordHash = make_hash([$diagnosis, $treatment, $prescription, $notes]);

        /* ---------- IDEA 1: encrypt every sensitive field -------------------- */
        $stmt = $pdo->prepare(
            'INSERT INTO medical_records
                (patient_id, doctor_id, diagnosis_enc, treatment_enc, prescription_enc, notes_enc, record_hash)
             VALUES (:p, :d, :diag, :treat, :pres, :notes, :hash)'
        );
        $stmt->execute([
            ':p'     => $patientId,
            ':d'     => $doctorId,
            ':diag'  => encrypt_data($diagnosis),      // <- AES-256 ciphertext
            ':treat' => encrypt_data($treatment),      // <- AES-256 ciphertext
            ':pres'  => encrypt_data($prescription),   // <- AES-256 ciphertext
            ':notes' => encrypt_data($notes),          // <- AES-256 ciphertext
            ':hash'  => $recordHash,                    // <- SHA-256 fingerprint
        ]);
        $newId = $pdo->lastInsertId();

        log_event($pdo, $_SESSION['user_id'], 'RECORD_CREATE',
                  "Encrypted record #$newId created for patient #$patientId.");
        set_flash('success', 'Medical record encrypted, hashed and stored securely.');
        redirect('doctor/view_record.php?id=' . $newId);
    }
}

// Fetch active patients for the dropdown.
$patients = $pdo->query(
    'SELECT p.id, p.full_name FROM patients p JOIN users u ON u.id=p.user_id
     WHERE u.status="active" ORDER BY p.full_name'
)->fetchAll();

$page_title = 'Add Medical Record';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Add Medical Record</h1>
            <p class="text-muted">Fields below are encrypted with AES-256 and fingerprinted with SHA-256 on save.</p>
        </div>

        <?php if ($errors): ?>
            <div class="flash flash-error"><?php foreach ($errors as $e) echo e($e).'<br>'; ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="add_record.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div class="form-row">
                    <label for="patient_id">Patient</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">-- select patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($preselectPatient === (int)$p['id']) ? 'selected' : ''; ?>>
                                <?php echo e($p['full_name']); ?> (#<?php echo $p['id']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="diagnosis">Diagnosis <span style="color:var(--red)">*</span></label>
                    <textarea id="diagnosis" name="diagnosis" required placeholder="e.g. Stage 1 Hypertension"><?php echo e($_POST['diagnosis'] ?? ''); ?></textarea>
                    <div class="hint">Encrypted before storage (Idea 1).</div>
                </div>

                <div class="form-row">
                    <label for="treatment">Treatment</label>
                    <textarea id="treatment" name="treatment"><?php echo e($_POST['treatment'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <label for="prescription">Prescription</label>
                    <textarea id="prescription" name="prescription"><?php echo e($_POST['prescription'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"><?php echo e($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn">Encrypt &amp; Save Record</button>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
