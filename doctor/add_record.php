<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$dq = $pdo->prepare('SELECT id FROM doctors WHERE user_id = :uid');
$dq->execute([':uid' => $_SESSION['user_id']]);
$doctorId = $dq->fetchColumn();

$errors = [];
$preselectPatient = (int) ($_GET['patient_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $patientId    = (int) ($_POST['patient_id'] ?? 0);
    $diagnosis    = trim($_POST['diagnosis'] ?? '');
    $treatment    = trim($_POST['treatment'] ?? '');
    $prescription = trim($_POST['prescription'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if ($patientId <= 0) $errors[] = 'Please choose a patient.';
    if ($diagnosis === '') $errors[] = 'Diagnosis is required.';

    if (!$errors) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM patients p JOIN users u ON u.id=p.user_id
                              WHERE p.id = :id AND u.status = "active"');
        $chk->execute([':id' => $patientId]);
        if ($chk->fetchColumn() == 0) $errors[] = 'Selected patient is not valid.';
    }

    if (!$errors) {
        $recordHash = make_hash([$diagnosis, $treatment, $prescription, $notes]);

        $stmt = $pdo->prepare(
            'INSERT INTO medical_records
                (patient_id, doctor_id, diagnosis_enc, treatment_enc, prescription_enc, notes_enc, record_hash)
             VALUES (:p, :d, :diag, :treat, :pres, :notes, :hash)'
        );
        $stmt->execute([
            ':p'     => $patientId,
            ':d'     => $doctorId,
            ':diag'  => encrypt_data($diagnosis),
            ':treat' => encrypt_data($treatment),
            ':pres'  => encrypt_data($prescription),
            ':notes' => encrypt_data($notes),
            ':hash'  => $recordHash,
        ]);
        $newId = $pdo->lastInsertId();

        log_event($pdo, $_SESSION['user_id'], 'RECORD_CREATE',
                  "Encrypted record #$newId created for patient #$patientId.");
        set_flash('success', 'Medical record encrypted, hashed and stored securely.');
        redirect('doctor/view_record.php?id=' . $newId);
    }
}

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
