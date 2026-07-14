<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $uid = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $map = ['approve' => 'active', 'suspend' => 'suspended', 'activate' => 'active'];
    if (isset($map[$action]) && $uid > 0) {
        $st = $pdo->prepare('UPDATE users SET status=:s WHERE id=:id AND role="patient"');
        $st->execute([':s' => $map[$action], ':id' => $uid]);
        log_event($pdo, $_SESSION['user_id'], 'PATIENT_STATUS', "Admin performed '$action' on patient user #$uid.");
        set_flash('success', 'Patient account updated.');
    }
    redirect('admin/manage_patients.php');
}

$patients = $pdo->query(
    'SELECT p.id, p.full_name, p.dob, p.gender, p.blood_group,
            u.id AS user_id, u.username, u.email, u.status, u.created_at
     FROM patients p JOIN users u ON u.id = p.user_id
     ORDER BY (u.status = "pending") DESC, p.id'
)->fetchAll();

$page_title = 'Manage Patients';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Manage Patients</h1>
            <p class="text-muted">Approve new registrations and manage existing patients.</p>
        </div>

        <div class="card">
            <h2>All Patients</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th>#</th><th>Name</th><th>Username</th><th>Email</th><th>DOB</th><th>Blood</th><th>Status</th><th>Actions</th></tr>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td><?php echo e($p['full_name']); ?></td>
                            <td><?php echo e($p['username']); ?></td>
                            <td><?php echo e($p['email']); ?></td>
                            <td><?php echo e($p['dob']); ?></td>
                            <td><?php echo e($p['blood_group']); ?></td>
                            <td>
                                <?php
                                $cls = $p['status']==='active' ? 'badge-green'
                                     : ($p['status']==='pending' ? 'badge-amber' : 'badge-red');
                                ?>
                                <span class="badge <?php echo $cls; ?>"><?php echo e($p['status']); ?></span>
                            </td>
                            <td>
                                <div class="actions">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                        <button class="btn btn-sm btn-teal" data-confirm="Approve this patient?">Approve</button>
                                    </form>
                                <?php elseif ($p['status'] === 'active'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                        <button class="btn btn-sm btn-danger" data-confirm="Suspend this patient?">Suspend</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                        <button class="btn btn-sm btn-teal" data-confirm="Reactivate this patient?">Activate</button>
                                    </form>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
