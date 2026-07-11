<?php
/**
 * =============================================================================
 *  doctor/upload_report.php  --  Upload a report (Idea 2 + Idea 3)
 * =============================================================================
 *  THIS PAGE DEMONSTRATES IDEAS 2 & 3 ON UPLOAD.
 *
 *    Idea 2 (SHA-256): we hash the ORIGINAL file bytes and store that fingerprint.
 *    On download we re-hash and compare, to prove the file was not altered.
 *
 *    Idea 3 (Federated storage): store_federated_report() encrypts the file with
 *    AES-256 and writes the SAME ciphertext into all three branch folders
 *    (uploads/hospital_A, _B, _C). We then record one hospital_storage row per
 *    branch. The user sees: "Record stored securely across three hospital branches."
 *
 *  Only the encrypted copies are written to disk -- the raw upload is never saved.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

// Resolve this doctor's id.
$dq = $pdo->prepare('SELECT id FROM doctors WHERE user_id = :uid');
$dq->execute([':uid' => $_SESSION['user_id']]);
$doctorId = $dq->fetchColumn();

$patientId = (int) ($_GET['patient_id'] ?? ($_POST['patient_id'] ?? 0));
$recordId  = (int) ($_GET['record_id'] ?? ($_POST['record_id'] ?? 0)); // optional link
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Basic checks on the uploaded file.
    if (!isset($_FILES['report']) || $_FILES['report']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a file to upload.';
    }
    if ($patientId <= 0) $errors[] = 'Missing patient.';

    // Restrict to sensible medical file types and a max size (2 MB).
    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'txt'];
    if (!$errors) {
        $originalName = $_FILES['report']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Allowed file types: ' . implode(', ', $allowedExt) . '.';
        }
        if ($_FILES['report']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'File too large (max 2 MB).';
        }
    }

    if (!$errors) {
        // Read the raw bytes of the uploaded file.
        $contents = file_get_contents($_FILES['report']['tmp_name']);

        // Idea 2: fingerprint of the ORIGINAL bytes.
        $fileHash = make_file_hash($contents);

        // A unique, non-guessable stored name (with .enc extension).
        $stored = 'report_' . time() . '_' . bin2hex(random_bytes(4)) . '.enc';

        // Idea 3: encrypt + replicate across all three branch folders.
        $branchesWritten = store_federated_report($stored, $contents);

        if (count($branchesWritten) === 0) {
            $errors[] = 'Could not write to branch folders. Check folder permissions.';
        } else {
            // Save report metadata.
            $ins = $pdo->prepare(
                'INSERT INTO uploaded_reports
                    (record_id, patient_id, doctor_id, original_filename, stored_filename, file_hash)
                 VALUES (:rid, :pid, :did, :orig, :stored, :fhash)'
            );
            $ins->execute([
                ':rid'    => $recordId ?: null,
                ':pid'    => $patientId,
                ':did'    => $doctorId,
                ':orig'   => $originalName,
                ':stored' => $stored,
                ':fhash'  => $fileHash,
            ]);
            $reportId = $pdo->lastInsertId();

            // One hospital_storage row per branch that received a copy.
            $hs = $pdo->prepare('INSERT INTO hospital_storage (report_id, branch_name, file_path, status)
                                 VALUES (:rid, :branch, :path, "stored")');
            foreach ($branchesWritten as $branchName) {
                $folder = HOSPITAL_BRANCHES[$branchName];
                $hs->execute([
                    ':rid'    => $reportId,
                    ':branch' => $branchName,
                    ':path'   => 'uploads/' . $folder . '/' . $stored,
                ]);
            }

            log_event($pdo, $_SESSION['user_id'], 'REPORT_UPLOAD',
                      "Report '$originalName' encrypted & federated across " . count($branchesWritten) . " branches.");

            $success = 'Record stored securely across ' . count($branchesWritten)
                     . ' hospital branches (' . implode(', ', $branchesWritten) . ').';
        }
    }
}

$page_title = 'Upload Report';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Upload Medical Report</h1>
            <p class="text-muted">The file is hashed, encrypted, and copied to all three hospital branches.</p>
        </div>

        <?php if ($errors): ?>
            <div class="flash flash-error"><?php foreach ($errors as $e) echo e($e).'<br>'; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="integrity-ok">&#10003; <?php echo e($success); ?></div>
            <div class="actions mb">
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>doctor/view_reports.php?patient_id=<?php echo $patientId; ?>">Back to Patient</a>
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>admin/manage_storage.php">View Storage</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="card">
            <!-- enctype="multipart/form-data" is REQUIRED for file uploads. -->
            <form method="post" action="upload_report.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">

                <div class="form-row">
                    <label for="report">Report file</label>
                    <input type="file" id="report" name="report" required accept=".pdf,.jpg,.jpeg,.png,.txt">
                    <div class="hint">Allowed: PDF, JPG, PNG, TXT &middot; Max 2 MB. Stored encrypted (never in plain form).</div>
                </div>

                <button type="submit" class="btn btn-teal">Encrypt &amp; Replicate</button>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
