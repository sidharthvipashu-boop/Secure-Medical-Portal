<?php
/**
 * =============================================================================
 *  doctor/dashboard.php  --  Doctor overview
 * =============================================================================
 *  Shows the doctor their patient count, how many encrypted records they have
 *  authored, and quick links. Only the 'doctor' role may reach this page.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor'); // ACCESS CONTROL: doctors only

// Find this doctor's row id from their logged-in user id.
$dq = $pdo->prepare('SELECT id, full_name, specialization, hospital_branch FROM doctors WHERE user_id = :uid');
$dq->execute([':uid' => $_SESSION['user_id']]);
$doctor = $dq->fetch();
$doctorId = $doctor['id'];

// "My patients" = distinct patients this doctor has created records for.
$patCountStmt = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) FROM medical_records WHERE doctor_id = :d');
$patCountStmt->execute([':d' => $doctorId]);
$myPatients = (int) $patCountStmt->fetchColumn();

$recCountStmt = $pdo->prepare('SELECT COUNT(*) FROM medical_records WHERE doctor_id = :d');
$recCountStmt->execute([':d' => $doctorId]);
$myRecords = (int) $recCountStmt->fetchColumn();

$repCountStmt = $pdo->prepare('SELECT COUNT(*) FROM uploaded_reports WHERE doctor_id = :d');
$repCountStmt->execute([':d' => $doctorId]);
$myReports = (int) $repCountStmt->fetchColumn();

// Recent records authored by this doctor (join patient names).
$recent = $pdo->prepare(
    'SELECT mr.id, mr.created_at, p.full_name
     FROM medical_records mr JOIN patients p ON p.id = mr.patient_id
     WHERE mr.doctor_id = :d ORDER BY mr.id DESC LIMIT 8'
);
$recent->execute([':d' => $doctorId]);
$recentRecords = $recent->fetchAll();

$page_title = 'Doctor Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Doctor Dashboard</h1>
            <p class="text-muted">
                <?php echo e($doctor['full_name']); ?> &mdash;
                <?php echo e($doctor['specialization']); ?>,
                <?php echo e($doctor['hospital_branch']); ?>
            </p>
        </div>

        <div class="stat-grid">
            <div class="stat"><div class="num"><?php echo $myPatients; ?></div><div class="label">My Patients</div></div>
            <div class="stat teal"><div class="num"><?php echo $myRecords; ?></div><div class="label">Records Authored</div></div>
            <div class="stat green"><div class="num"><?php echo $myReports; ?></div><div class="label">Reports Uploaded</div></div>
        </div>

        <div class="card">
            <h2>Quick Actions</h2>
            <div class="actions">
                <a class="btn" href="<?php echo BASE_URL; ?>doctor/add_record.php">+ Add Medical Record</a>
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>doctor/patients.php">View My Patients</a>
            </div>
        </div>

        <div class="card">
            <h2>Recent Records</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th>Record #</th><th>Patient</th><th>Created</th><th>Action</th></tr>
                    <?php foreach ($recentRecords as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo e($r['full_name']); ?></td>
                            <td class="text-muted"><?php echo e($r['created_at']); ?></td>
                            <td><a class="btn btn-sm btn-outline" href="<?php echo BASE_URL; ?>doctor/view_record.php?id=<?php echo $r['id']; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentRecords): ?>
                        <tr><td colspan="4" class="text-muted">No records yet. Add your first one.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
