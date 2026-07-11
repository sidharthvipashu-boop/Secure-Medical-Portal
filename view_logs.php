<?php
/**
 * =============================================================================
 *  admin/dashboard.php  --  Administrator overview
 * =============================================================================
 *  Shows quick counts (doctors, patients, records, pending approvals) and recent
 *  security events. Only users with the 'admin' role may reach this page.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin'); // ACCESS CONTROL: admins only

// Gather simple statistics. These are plain COUNT queries (no user input, but we
// still keep them parameter-free and read-only).
$doctors      = (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
$patients     = (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
$records      = (int) $pdo->query('SELECT COUNT(*) FROM medical_records')->fetchColumn();
$pending      = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient' AND status='pending'")->fetchColumn();
$reports      = (int) $pdo->query('SELECT COUNT(*) FROM uploaded_reports')->fetchColumn();
$violations   = (int) $pdo->query("SELECT COUNT(*) FROM security_logs WHERE event_type='INTEGRITY_FAIL'")->fetchColumn();

// Latest 8 security log rows for a quick glance.
$logs = $pdo->query('SELECT event_type, description, created_at FROM security_logs ORDER BY id DESC LIMIT 8')->fetchAll();

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Admin Dashboard</h1>
            <p class="text-muted">Welcome, <?php echo e($_SESSION['full_name']); ?>.</p>
        </div>

        <!-- Statistic cards -->
        <div class="stat-grid">
            <div class="stat"><div class="num"><?php echo $doctors; ?></div><div class="label">Doctors</div></div>
            <div class="stat teal"><div class="num"><?php echo $patients; ?></div><div class="label">Patients</div></div>
            <div class="stat green"><div class="num"><?php echo $records; ?></div><div class="label">Encrypted Records</div></div>
            <div class="stat amber"><div class="num"><?php echo $pending; ?></div><div class="label">Pending Approvals</div></div>
            <div class="stat"><div class="num"><?php echo $reports; ?></div><div class="label">Federated Reports</div></div>
            <div class="stat <?php echo $violations ? '' : 'green'; ?>" style="<?php echo $violations ? 'border-left-color:var(--red);' : ''; ?>">
                <div class="num"><?php echo $violations; ?></div><div class="label">Integrity Violations</div>
            </div>
        </div>

        <!-- Quick links -->
        <div class="card">
            <h2>Quick Actions</h2>
            <div class="actions">
                <a class="btn" href="<?php echo BASE_URL; ?>admin/manage_patients.php">Approve Patients</a>
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>admin/manage_doctors.php">Manage Doctors</a>
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>admin/manage_storage.php">Branch Storage</a>
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>admin/view_logs.php">Security Logs</a>
            </div>
        </div>

        <!-- Recent security events -->
        <div class="card">
            <h2>Recent Security Events</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th>Event</th><th>Description</th><th>When</th></tr>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <?php
                                // Colour integrity failures red so they stand out.
                                $cls = $log['event_type'] === 'INTEGRITY_FAIL' ? 'badge-red' : 'badge-blue';
                                ?>
                                <span class="badge <?php echo $cls; ?>"><?php echo e($log['event_type']); ?></span>
                            </td>
                            <td><?php echo e($log['description']); ?></td>
                            <td class="text-muted"><?php echo e($log['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td colspan="3" class="text-muted">No events yet.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
