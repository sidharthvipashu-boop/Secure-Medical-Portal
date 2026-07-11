<?php
/**
 * =============================================================================
 *  doctor/patients.php  --  The doctor's patient list (with search)
 * =============================================================================
 *  Lists all active patients so the doctor can pick one and view/add records.
 *  A search box filters by name or username. The search term is bound with a
 *  prepared statement (using LIKE) so it can never inject SQL.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

// Optional search term.
$search = trim($_GET['q'] ?? '');

// List active patients. If a search term is present, filter by name/username.
if ($search !== '') {
    // The %...% wildcards are added to the VALUE, not the SQL, so binding is safe.
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        'SELECT p.id, p.full_name, p.gender, p.blood_group, u.username,
                (SELECT COUNT(*) FROM medical_records mr WHERE mr.patient_id = p.id) AS record_count
         FROM patients p JOIN users u ON u.id = p.user_id
         WHERE u.status = "active" AND (p.full_name LIKE :s OR u.username LIKE :s)
         ORDER BY p.full_name'
    );
    $stmt->execute([':s' => $like]);
} else {
    $stmt = $pdo->query(
        'SELECT p.id, p.full_name, p.gender, p.blood_group, u.username,
                (SELECT COUNT(*) FROM medical_records mr WHERE mr.patient_id = p.id) AS record_count
         FROM patients p JOIN users u ON u.id = p.user_id
         WHERE u.status = "active" ORDER BY p.full_name'
    );
}
$patients = $stmt->fetchAll();

$page_title = 'My Patients';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Patients</h1>
            <p class="text-muted">Select a patient to view their encrypted records or add a new one.</p>
        </div>

        <div class="card">
            <form method="get" action="patients.php" class="actions" style="align-items:flex-end;">
                <div class="form-row" style="margin-bottom:0;flex:1;min-width:220px;">
                    <label for="q">Search patients</label>
                    <input type="text" id="q" name="q" placeholder="Name or username" value="<?php echo e($search); ?>">
                </div>
                <button class="btn" type="submit">Search</button>
                <?php if ($search !== ''): ?><a class="btn btn-outline" href="patients.php">Clear</a><?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="data">
                    <tr><th>#</th><th>Name</th><th>Username</th><th>Gender</th><th>Blood</th><th>Records</th><th>Actions</th></tr>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><?php echo e($p['full_name']); ?></td>
                            <td><?php echo e($p['username']); ?></td>
                            <td><?php echo e($p['gender']); ?></td>
                            <td><?php echo e($p['blood_group']); ?></td>
                            <td><span class="badge badge-blue"><?php echo (int)$p['record_count']; ?></span></td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-sm btn-outline" href="<?php echo BASE_URL; ?>doctor/view_reports.php?patient_id=<?php echo $p['id']; ?>">Records</a>
                                    <a class="btn btn-sm" href="<?php echo BASE_URL; ?>doctor/add_record.php?patient_id=<?php echo $p['id']; ?>">+ Record</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$patients): ?>
                        <tr><td colspan="7" class="text-muted">No patients found.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
