<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$reportId = (int) ($_GET['report_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM uploaded_reports WHERE id = :id');
$stmt->execute([':id' => $reportId]);
$report = $stmt->fetch();

if (!$report) {
    http_response_code(404);
    exit('Report not found.');
}

$role = current_role();
if ($role === 'patient') {
    $pq = $pdo->prepare('SELECT id FROM patients WHERE user_id = :uid');
    $pq->execute([':uid' => $_SESSION['user_id']]);
    $myPatientId = (int) $pq->fetchColumn();

    if ((int) $report['patient_id'] !== $myPatientId) {
        http_response_code(403);
        log_event($pdo, $_SESSION['user_id'], 'ACCESS_DENIED', "Patient tried to download report #$reportId not belonging to them.");
        exit('Access denied: this report is not yours.');
    }
}

$plainContents = read_federated_report($report['stored_filename']);

if ($plainContents === false) {
    http_response_code(500);
    log_event($pdo, $_SESSION['user_id'], 'REPORT_MISSING', "No branch could provide report #$reportId.");
    exit('The report file could not be retrieved from any hospital branch.');
}

$currentHash = make_file_hash($plainContents);
if (!hash_equals($report['file_hash'], $currentHash)) {
    http_response_code(409);
    log_event($pdo, $_SESSION['user_id'], 'INTEGRITY_FAIL',
              "Integrity violation on report file #$reportId (hash mismatch on download).");

    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="integrity-bad" style="margin-top:1.5rem;">'.
       '&#9888; Integrity Violation Detected &mdash; the report failed its SHA-256 check '.  
       'and will NOT be downloaded. The event has been logged for the administrator.'.     
       '</div><a class="btn" href="'.BASE_URL.'index.php">Home</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

log_event($pdo, $_SESSION['user_id'], 'REPORT_DOWNLOAD', "Report #$reportId downloaded (integrity OK).");

$ext = strtolower(pathinfo($report['original_filename'], PATHINFO_EXTENSION));
$types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'txt'  => 'text/plain',
];
$contentType = $types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . basename($report['original_filename']) . '"');
header('Content-Length: ' . strlen($plainContents));
echo $plainContents;
exit;
