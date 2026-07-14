<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$recordId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT mr.*, p.full_name AS patient_name, d.full_name AS doctor_name
     FROM medical_records mr
     JOIN patients p ON p.id = mr.patient_id
     JOIN doctors  d ON d.id = mr.doctor_id
     WHERE mr.id = :id'
);
$stmt->execute([':id' => $recordId]);
$record = $stmt->fetch();

if (!$record) {
    set_flash('error', 'Record not found.');
    redirect('doctor/dashboard.php');
}

$diagnosis    = decrypt_data($record['diagnosis_enc']);
$treatment    = decrypt_data($record['treatment_enc']);
$prescription = decrypt_data($record['prescription_enc']);
$notes        = decrypt_data($record['notes_enc']);

$intact = verify_integrity([$diagnosis, $treatment, $prescription, $notes], $record['record_hash']);

if (!$intact) {
    log_event($pdo, $_SESSION['user_id'], 'INTEGRITY_FAIL',
              "Integrity violation on record #$recordId (doctor view).");
}

$repStmt = $pdo->prepare('SELECT id, original_filename, created_at FROM uploaded_reports WHERE record_id = :rid ORDER BY id DESC');
$repStmt->execute([':rid' => $recordId]);
$reports = $repStmt->fetchAll();

$page_title = 'View Record #' . $recordId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Medical Record #<?php echo $recordId; ?></h1>
            <p class="text-muted">
                Patient: <strong><?php echo e($record['patient_name']); ?></strong> &middot;
                Author: <?php echo e($record['doctor_name']); ?> &middot;
                Created: <?php echo e($record['created_at']); ?>
            </p>
        </div>

        <?php if ($intact): ?>
            <div class="integrity-ok">&#10003; Integrity Verified &mdash; SHA-256 hash matches. Data is authentic and unaltered.</div>
        <?php else: ?>
            <div class="integrity-bad">&#9888; Integrity Violation Detected &mdash; the stored hash does NOT match. This record may have been tampered with. Event has been logged.</div>
        <?php endif; ?>

        <div class="card">
            <h2>Decrypted Details</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th style="width:160px;">Diagnosis</th><td><?php echo nl2br(e($diagnosis)); ?></td></tr>
                    <tr><th>Treatment</th><td><?php echo nl2br(e($treatment)); ?></td></tr>
                    <tr><th>Prescription</th><td><?php echo nl2br(e($prescription)); ?></td></tr>
                    <tr><th>Notes</th><td><?php echo nl2br(e($notes)); ?></td></tr>
                </table>
            </div>
            <div class="actions mt">
                <a class="btn" href="<?php echo BASE_URL; ?>doctor/edit_record.php?id=<?php echo $recordId; ?>">Edit Record</a>
                <a class="btn btn-teal" href="<?php echo BASE_URL; ?>doctor/upload_report.php?record_id=<?php echo $recordId; ?>&patient_id=<?php echo $record['patient_id']; ?>">Upload Report</a>
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>doctor/view_reports.php?patient_id=<?php echo $record['patient_id']; ?>">Back to Patient</a>
            </div>
        </div>

        <div class="card">
            <h2>Behind the Scenes (security proof)</h2>
            <p class="text-muted mb">This is exactly what is stored in the database &mdash; unreadable without the key.</p>
            <p><strong>Stored SHA-256 hash:</strong></p>
            <p class="mono"><?php echo e($record['record_hash']); ?></p>
            <p class="mt"><strong>Diagnosis as stored (AES-256 ciphertext, base64):</strong></p>
            <p class="mono"><?php echo e($record['diagnosis_enc']); ?></p>
        </div>

        <?php if ($reports): ?>
        <div class="card">
            <h2>Attached Reports</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th>File</th><th>Uploaded</th><th>Action</th></tr>
                    <?php foreach ($reports as $rep): ?>
                        <tr>
                            <td><?php echo e($rep['original_filename']); ?></td>
                            <td class="text-muted"><?php echo e($rep['created_at']); ?></td>
                            <td><a class="btn btn-sm btn-outline" href="<?php echo BASE_URL; ?>download.php?report_id=<?php echo $rep['id']; ?>">Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
