<?php
/**
 * =============================================================================
 *  admin/manage_storage.php  --  Federated branch storage monitor  (Idea 3)
 * =============================================================================
 *  Visualises Project Idea 3 (Federated File System Encoding at the Edge).
 *  For every uploaded report it shows which of the three simulated branches
 *  (Hospital A/B/C) hold an encrypted copy, and LIVE-CHECKS the disk to confirm
 *  each encrypted file is actually present. This demonstrates the resilience
 *  benefit: the data survives as long as any one branch still has its copy.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// Count encrypted files physically present in each branch folder (live).
$branchCounts = [];
foreach (HOSPITAL_BRANCHES as $name => $folder) {
    $dir = UPLOAD_PATH . '/' . $folder;
    // glob() lists the .enc files that actually exist on disk right now.
    $files = is_dir($dir) ? glob($dir . '/*.enc') : [];
    $branchCounts[$name] = count($files);
}

// Fetch all reports plus their per-branch storage rows.
$reports = $pdo->query(
    'SELECT r.id, r.original_filename, r.stored_filename, r.created_at,
            p.full_name AS patient_name
     FROM uploaded_reports r
     JOIN patients p ON p.id = r.patient_id
     ORDER BY r.id DESC'
)->fetchAll();

$page_title = 'Branch Storage';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Federated Branch Storage</h1>
            <p class="text-muted">
                Every report is AES-256 encrypted and replicated to all three hospital
                branches. This simulates a distributed edge file system.
            </p>
        </div>

        <!-- Per-branch file counts (read live from the folders on disk) -->
        <div class="stat-grid">
            <?php foreach ($branchCounts as $name => $count): ?>
                <div class="stat teal">
                    <div class="num"><?php echo $count; ?></div>
                    <div class="label"><?php echo e($name); ?> &mdash; encrypted files</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>Reports &amp; Replication Status</h2>
            <p class="text-muted mb">A green tick means the encrypted copy is physically present in that branch folder.</p>
            <div class="table-wrap">
                <table class="data">
                    <tr>
                        <th>#</th><th>Report</th><th>Patient</th>
                        <?php foreach (array_keys(HOSPITAL_BRANCHES) as $b): ?>
                            <th><?php echo e($b); ?></th>
                        <?php endforeach; ?>
                        <th>Uploaded</th>
                    </tr>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo e($r['original_filename']); ?></td>
                            <td><?php echo e($r['patient_name']); ?></td>
                            <?php
                            // For each branch, check if the encrypted file exists on disk.
                            foreach (HOSPITAL_BRANCHES as $bname => $folder):
                                $path = UPLOAD_PATH . '/' . $folder . '/' . $r['stored_filename'];
                                $exists = is_file($path);
                            ?>
                                <td>
                                    <?php if ($exists): ?>
                                        <span class="badge badge-green">&#10003; stored</span>
                                    <?php else: ?>
                                        <span class="badge badge-red">&#10007; missing</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-muted"><?php echo e($r['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$reports): ?>
                        <tr><td colspan="7" class="text-muted">No reports uploaded yet.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>How the Simulation Works</h2>
            <p class="text-muted">
                When a doctor uploads a report, the file contents are encrypted once with
                AES-256 and the same ciphertext is written into
                <span class="mono">uploads/hospital_A/</span>,
                <span class="mono">uploads/hospital_B/</span> and
                <span class="mono">uploads/hospital_C/</span>.
                On download, the system reads from the first available branch and fails over
                to the next if a copy is missing or corrupted.
            </p>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
