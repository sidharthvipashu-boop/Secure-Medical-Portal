<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$patientId = (int) ($_GET['patient_id'] ?? 0);

$pstmt = $pdo->prepare(
    'SELECT p.*, u.username, u.status FROM patients p JOIN users u ON u.id=p.user_id WHERE p.id = :id'
);
$pstmt->execute([':id' => $patientId]);
$patient = $pstmt->fetch();

if (!$patient) {
    set_flash('error', 'Patient not found.');
    redirect('doctor/patients.php');
}

$rstmt = $pdo->prepare(
    'SELECT mr.id, mr.created_at, d.full_name AS doctor_name
     FROM medical_records mr JOIN doctors d ON d.id = mr.doctor_id
     WHERE mr.patient_id = :pid ORDER BY mr.id DESC'
);
$rstmt->execute([':pid' => $patientId]);
$records = $rstmt->fetchAll();

$repStmt = $pdo->prepare(
    'SELECT id, original_filename, created_at FROM uploaded_reports
     WHERE patient_id = :pid ORDER BY id DESC'
);
$repStmt->execute([':pid' => $patientId]);
$reports = $repStmt->fetchAll();

$page_title = 'Patient Records';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1><?php echo e($patient['full_name']); ?></h1>
            <p class="text-muted">
                <?php echo e($patient['gender']); ?> &middot;
                DOB <?php echo e($patient['dob']); ?> &middot;
                Blood <?php echo e($patient['blood_group']); ?> &middot;
                Username <?php echo e($patient['username']); ?>
            </p>
        </div>

        <div class="actions mb">
            <a class="btn" href="<?php echo BASE_URL; ?>doctor/add_record.php?patient_id=<?php echo $patientId; ?>">+ Add Record</a>
            <a class="btn btn-teal" href="<?php echo BASE_URL; ?>doctor/upload_report.php?patient_id=<?php echo $patientId; ?>">Upload Report</a>
        </div>

        <div class="card">
            <h2>Medical Records</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th>Record #</th><th>Author</th><th>Created</th><th>Action</th></tr>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo e($r['doctor_name']); ?></td>
                            <td class="text-muted"><?php echo e($r['created_at']); ?></td>
                            <td><a class="btn btn-sm btn-outline" href="<?php echo BASE_URL; ?>doctor/view_record.php?id=<?php echo $r['id']; ?>">Open (decrypt)</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$records): ?>
                        <tr><td colspan="4" class="text-muted">No records yet.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Uploaded Reports</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th>File</th><th>Uploaded</th><th>Action</th></tr>
                    <?php foreach ($reports as $rep): ?>
                        <tr>
                            <td><?php echo e($rep['original_filename']); ?></td>
                            <td class="text-muted"><?php echo e($rep['created_at']); ?></td>
                            <td><a class="btn btn-sm btn-outline" href="<?php echo BASE_URL; ?>download.php?report_id=<?php echo $rep['id']; ?>">Download (verify)</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$reports): ?>
                        <tr><td colspan="3" class="text-muted">No reports uploaded.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
