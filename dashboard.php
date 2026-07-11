<?php
/**
 * =============================================================================
 *  doctor/edit_record.php  --  Update an existing medical record
 * =============================================================================
 *  To edit, we first DECRYPT the record (Idea 1) to show its current values.
 *  On save we RE-ENCRYPT the new values and RE-COMPUTE the SHA-256 hash (Idea 2)
 *  so the integrity fingerprint always matches the latest authentic content.
 *
 *  IMPORTANT: a legitimate edit updates BOTH the ciphertext AND the hash together.
 *  That is different from tampering, where data is changed WITHOUT updating the
 *  hash -- which is exactly what the integrity check on view_record.php catches.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$recordId = (int) ($_GET['id'] ?? ($_POST['record_id'] ?? 0));

// Load the record.
$stmt = $pdo->prepare('SELECT * FROM medical_records WHERE id = :id');
$stmt->execute([':id' => $recordId]);
$record = $stmt->fetch();

if (!$record) {
    set_flash('error', 'Record not found.');
    redirect('doctor/dashboard.php');
}

$errors = [];

// ---- Handle save ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $diagnosis    = trim($_POST['diagnosis'] ?? '');
    $treatment    = trim($_POST['treatment'] ?? '');
    $prescription = trim($_POST['prescription'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if ($diagnosis === '') $errors[] = 'Diagnosis is required.';

    if (!$errors) {
        // Idea 2: fresh hash of the new plain text.
        $newHash = make_hash([$diagnosis, $treatment, $prescription, $notes]);

        // Idea 1: re-encrypt each field, then update the row (hash updated too).
        $upd = $pdo->prepare(
            'UPDATE medical_records
             SET diagnosis_enc=:diag, treatment_enc=:treat, prescription_enc=:pres,
                 notes_enc=:notes, record_hash=:hash
             WHERE id=:id'
        );
        $upd->execute([
            ':diag'  => encrypt_data($diagnosis),
            ':treat' => encrypt_data($treatment),
            ':pres'  => encrypt_data($prescription),
            ':notes' => encrypt_data($notes),
            ':hash'  => $newHash,
            ':id'    => $recordId,
        ]);

        log_event($pdo, $_SESSION['user_id'], 'RECORD_UPDATE', "Record #$recordId updated & re-hashed.");
        set_flash('success', 'Record re-encrypted and integrity hash updated.');
        redirect('doctor/view_record.php?id=' . $recordId);
    }

    // If there were errors, keep the submitted values to redisplay.
    $current = compact('diagnosis', 'treatment', 'prescription', 'notes');
} else {
    // First load: decrypt the stored values so the doctor can edit them.
    $current = [
        'diagnosis'    => decrypt_data($record['diagnosis_enc']),
        'treatment'    => decrypt_data($record['treatment_enc']),
        'prescription' => decrypt_data($record['prescription_enc']),
        'notes'        => decrypt_data($record['notes_enc']),
    ];
}

$page_title = 'Edit Record #' . $recordId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Edit Medical Record #<?php echo $recordId; ?></h1>
            <p class="text-muted">Saving will re-encrypt the data and refresh its integrity hash.</p>
        </div>

        <?php if ($errors): ?>
            <div class="flash flash-error"><?php foreach ($errors as $e) echo e($e).'<br>'; ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="edit_record.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">

                <div class="form-row">
                    <label for="diagnosis">Diagnosis <span style="color:var(--red)">*</span></label>
                    <textarea id="diagnosis" name="diagnosis" required><?php echo e($current['diagnosis']); ?></textarea>
                </div>
                <div class="form-row">
                    <label for="treatment">Treatment</label>
                    <textarea id="treatment" name="treatment"><?php echo e($current['treatment']); ?></textarea>
                </div>
                <div class="form-row">
                    <label for="prescription">Prescription</label>
                    <textarea id="prescription" name="prescription"><?php echo e($current['prescription']); ?></textarea>
                </div>
                <div class="form-row">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"><?php echo e($current['notes']); ?></textarea>
                </div>

                <div class="actions">
                    <button type="submit" class="btn">Save &amp; Re-encrypt</button>
                    <a class="btn btn-outline" href="<?php echo BASE_URL; ?>doctor/view_record.php?id=<?php echo $recordId; ?>">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
