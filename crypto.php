<?php
/**
 * =============================================================================
 *  index.php  --  Public home page
 * =============================================================================
 *  The landing page. It introduces the portal and, importantly for the viva,
 *  explains the THREE Systems Security concepts the project demonstrates.
 * =============================================================================
 */
require_once __DIR__ . '/config/config.php';
$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================ HERO ============================ -->
<section class="hero">
    <h1>Secure Medical Records Portal</h1>
    <p>
        A hospital system where patient records are encrypted at rest,
        protected against tampering, and safely replicated across multiple
        hospital sites.
    </p>
    <div class="actions" style="justify-content:center;">
        <a class="btn" href="<?php echo BASE_URL; ?>login.php">Log In</a>
        <a class="btn btn-outline" href="<?php echo BASE_URL; ?>register.php">Register as Patient</a>
    </div>
</section>

<div class="container">

    <div class="page-head">
        <h2>Three Security Concepts, One System</h2>
        <p class="text-muted">Every feature in this portal supports one of the concepts below.</p>
    </div>

    <!-- Feature cards: one per project idea -->
    <div class="feature-grid">

        <div class="feature">
            <div class="icon">&#128274;</div>
            <span class="tag">SEC-PRJ-12A_25</span>
            <h3>Secure Storage in Edge Cloud</h3>
            <p class="text-muted">
                Diagnoses, treatments, prescriptions, notes and reports are
                encrypted with <strong>AES-256</strong> before they are ever
                written to the database. The stored data is unreadable ciphertext
                and is only decrypted for authorised users.
            </p>
        </div>

        <div class="feature">
            <div class="icon">&#129302;</div>
            <span class="tag">SEC-PRJ-7E_25</span>
            <h3>Fake Data Prevention</h3>
            <p class="text-muted">
                Each record and report gets a <strong>SHA-256</strong> integrity
                fingerprint. On every view we recompute and compare it. Any change
                triggers an <strong>"Integrity Violation Detected"</strong> alert
                and is written to the security log.
            </p>
        </div>

        <div class="feature">
            <div class="icon">&#128451;</div>
            <span class="tag">SEC-PRJ-6_23</span>
            <h3>Federated Multi-Site Storage</h3>
            <p class="text-muted">
                Uploaded reports are encrypted and replicated across three
                simulated branches &mdash; <strong>Hospital A, B and C</strong>.
                This models a federated edge file system: resilient, distributed,
                and encrypted at every site.
            </p>
        </div>

    </div>

    <!-- How it works: the required end-to-end flow -->
    <div class="card mt">
        <h2>How a Record Flows Through the System</h2>
        <p class="text-muted mb">From the doctor's keyboard to the patient's screen, security is applied at every step.</p>
        <ol style="line-height:2;padding-left:1.2rem;">
            <li>Patient registers &rarr; Admin approves the account.</li>
            <li>Doctor logs in and selects a patient.</li>
            <li>Doctor enters the diagnosis and treatment.</li>
            <li><strong>AES-256 encrypts</strong> the sensitive fields (Idea 1).</li>
            <li>A <strong>SHA-256 hash</strong> is generated for integrity (Idea 2).</li>
            <li>The encrypted record is stored in MySQL.</li>
            <li>Uploaded reports are <strong>replicated to all three branches</strong> (Idea 3).</li>
            <li>Patient logs in and requests the record.</li>
            <li>The hash is <strong>re-verified</strong>; if it fails &rarr; "Integrity Violation Detected".</li>
            <li>If intact, the record is decrypted and displayed.</li>
        </ol>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
