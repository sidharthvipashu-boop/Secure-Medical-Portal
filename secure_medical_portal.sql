<?php
/**
 * =============================================================================
 *  admin/view_logs.php  --  Security audit log viewer
 * =============================================================================
 *  Displays the security_logs table. This is the audit trail that supports
 *  Idea 2: every INTEGRITY_FAIL event is recorded here so administrators can see
 *  exactly when tampering was detected, by whom, and from which IP address.
 *
 *  A simple event-type filter is included and is applied with a prepared
 *  statement so the filter value can never inject SQL.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// Optional filter by event type (from the dropdown).
$filter = $_GET['type'] ?? '';

// Distinct event types for the filter dropdown.
$types = $pdo->query('SELECT DISTINCT event_type FROM security_logs ORDER BY event_type')->fetchAll(PDO::FETCH_COLUMN);

// Fetch logs, optionally filtered. We join users to show who triggered each event.
if ($filter !== '') {
    $stmt = $pdo->prepare(
        'SELECT l.event_type, l.description, l.ip_address, l.created_at, u.username
         FROM security_logs l LEFT JOIN users u ON u.id = l.user_id
         WHERE l.event_type = :t ORDER BY l.id DESC LIMIT 200'
    );
    $stmt->execute([':t' => $filter]);
} else {
    $stmt = $pdo->query(
        'SELECT l.event_type, l.description, l.ip_address, l.created_at, u.username
         FROM security_logs l LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.id DESC LIMIT 200'
    );
}
$logs = $stmt->fetchAll();

$page_title = 'Security Logs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Security Logs</h1>
            <p class="text-muted">Audit trail of logins, record changes and integrity checks.</p>
        </div>

        <div class="card">
            <!-- Filter form (GET so the filter shows in the URL and is bookmarkable) -->
            <form method="get" action="view_logs.php" class="actions" style="align-items:flex-end;">
                <div class="form-row" style="margin-bottom:0;min-width:220px;">
                    <label for="type">Filter by event type</label>
                    <select id="type" name="type" onchange="this.form.submit()">
                        <option value="">All events</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo e($t); ?>" <?php echo $filter===$t?'selected':''; ?>><?php echo e($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a class="btn btn-outline btn-sm" href="view_logs.php">Reset</a>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="data">
                    <tr><th>Event</th><th>User</th><th>Description</th><th>IP</th><th>Time</th></tr>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <span class="badge <?php echo $log['event_type']==='INTEGRITY_FAIL'?'badge-red':'badge-blue'; ?>">
                                    <?php echo e($log['event_type']); ?>
                                </span>
                            </td>
                            <td><?php echo e($log['username'] ?? '-'); ?></td>
                            <td><?php echo e($log['description']); ?></td>
                            <td class="mono"><?php echo e($log['ip_address']); ?></td>
                            <td class="text-muted"><?php echo e($log['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td colspan="5" class="text-muted">No log entries found.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
