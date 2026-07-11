<?php
/**
 * =============================================================================
 *  about.php  --  Project background & objectives (public page)
 * =============================================================================
 *  A plain informational page. Useful during the viva to summarise WHAT the
 *  system is, WHO uses it, and WHICH security techniques implement each concept.
 * =============================================================================
 */
require_once __DIR__ . '/config/config.php';
$page_title = 'About';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <h1>About This Project</h1>
        <p class="text-muted">University Systems Security demonstration project.</p>
    </div>

    <div class="card">
        <h2>Objective</h2>
        <p>
            The Secure Medical Records Portal lets authorised hospital staff manage
            patient medical records while guaranteeing three security properties:
            <strong>confidentiality</strong> (encryption at rest),
            <strong>integrity</strong> (tamper detection), and
            <strong>secure, resilient storage</strong> (multi-site replication).
        </p>
    </div>

    <div class="card">
        <h2>Who Uses It</h2>
        <div class="table-wrap">
            <table class="data">
                <tr><th>Role</th><th>What they can do</th></tr>
                <tr><td><span class="badge badge-blue">Admin</span></td>
                    <td>Manage doctors and patients, approve registrations, view security logs, view branch storage.</td></tr>
                <tr><td><span class="badge badge-blue">Doctor</span></td>
                    <td>View their patients, add/edit encrypted medical records, upload and download reports.</td></tr>
                <tr><td><span class="badge badge-blue">Patient</span></td>
                    <td>View their own records and download their reports (read-only).</td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Security Techniques &rarr; Concepts</h2>
        <div class="table-wrap">
            <table class="data">
                <tr><th>Technique</th><th>Purpose</th><th>Supports</th></tr>
                <tr><td>AES-256 (OpenSSL)</td><td>Encrypt sensitive fields &amp; report files at rest</td><td>Idea 1 &amp; 3</td></tr>
                <tr><td>SHA-256 hashing</td><td>Detect tampered / fake records &amp; files</td><td>Idea 2</td></tr>
                <tr><td>Folder replication</td><td>Store encrypted copies at 3 branches</td><td>Idea 3</td></tr>
                <tr><td>bcrypt password hashing</td><td>Protect account credentials</td><td>Supports all</td></tr>
                <tr><td>PDO prepared statements</td><td>Prevent SQL injection</td><td>Supports all</td></tr>
                <tr><td>Sessions + role checks</td><td>Restrict decryption to authorised users</td><td>Supports all</td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Technology Stack</h2>
        <p>HTML5, CSS3, vanilla JavaScript, PHP, MySQL &mdash; running on XAMPP. No frameworks are used, keeping the code simple and easy to explain.</p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
