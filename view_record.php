<?php
/**
 * =============================================================================
 *  register.php  --  New patient self-registration
 * =============================================================================
 *  Creates a 'patient' user account with status = 'pending'. The account cannot
 *  log in until an admin approves it (see admin/manage_patients.php). This models
 *  the required flow: "Patient registers -> Admin approves account."
 *
 *  SECURITY POINTS:
 *    - The password is hashed with password_hash() (bcrypt) before storage.
 *    - All inserts use prepared statements.
 *    - A DB transaction keeps the users row and patients row consistent
 *      (either both are created or neither is).
 *    - CSRF token protects the form.
 * =============================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_for_role(current_role()));
}

$errors = [];
$old = []; // remember entered values if validation fails

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Collect and trim input.
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['username']  = trim($_POST['username'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['dob']       = $_POST['dob'] ?? '';
    $old['gender']    = $_POST['gender'] ?? '';
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['address']   = trim($_POST['address'] ?? '');
    $old['blood']     = trim($_POST['blood_group'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm          = $_POST['confirm'] ?? '';

    // --- Basic server-side validation (never trust the browser alone) --------
    if ($old['full_name'] === '')                 $errors[] = 'Full name is required.';
    if (strlen($old['username']) < 4)             $errors[] = 'Username must be at least 4 characters.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 6)                    $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                   $errors[] = 'Passwords do not match.';

    // Check username/email are not already taken (prepared statement).
    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u OR email = :e');
        $check->execute([':u' => $old['username'], ':e' => $old['email']]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'That username or email is already registered.';
        }
    }

    // --- Create the account inside a transaction -----------------------------
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1) Insert the login account (password hashed, status pending).
            $u = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, role, status)
                 VALUES (:u, :e, :ph, "patient", "pending")'
            );
            $u->execute([
                ':u'  => $old['username'],
                ':e'  => $old['email'],
                // bcrypt hash -- one-way, salted, slow-by-design to resist cracking.
                ':ph' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $newUserId = $pdo->lastInsertId();

            // 2) Insert the patient profile linked to that account.
            $p = $pdo->prepare(
                'INSERT INTO patients (user_id, full_name, dob, gender, phone, address, blood_group)
                 VALUES (:uid, :fn, :dob, :g, :ph, :ad, :bg)'
            );
            $p->execute([
                ':uid' => $newUserId,
                ':fn'  => $old['full_name'],
                ':dob' => $old['dob'] ?: null,
                ':g'   => $old['gender'] ?: null,
                ':ph'  => $old['phone'],
                ':ad'  => $old['address'],
                ':bg'  => $old['blood'],
            ]);

            $pdo->commit();

            log_event($pdo, $newUserId, 'REGISTER', 'New patient registered (pending approval).');
            set_flash('success', 'Registration successful! An admin must approve your account before you can log in.');
            redirect('login.php');

        } catch (PDOException $ex) {
            $pdo->rollBack(); // undo partial inserts on error
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

$page_title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="auth-card" style="max-width:560px;margin:1.5rem auto;">
    <div class="card">
      <div class="auth-head">
        <div class="lock">&#128100;</div>
        <h1>Patient Registration</h1>
        <p class="text-muted">Your account will be reviewed by an administrator.</p>
      </div>

      <?php if ($errors): ?>
        <div class="flash flash-error">
          <?php foreach ($errors as $er) echo e($er) . '<br>'; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="register.php">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

        <div class="form-row">
          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" required value="<?php echo e($old['full_name'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required value="<?php echo e($old['username'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?php echo e($old['email'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <label for="dob">Date of Birth</label>
          <input type="date" id="dob" name="dob" value="<?php echo e($old['dob'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <label for="gender">Gender</label>
          <select id="gender" name="gender">
            <option value="">-- select --</option>
            <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?php echo $g; ?>" <?php echo (($old['gender'] ?? '') === $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label for="phone">Phone</label>
          <input type="text" id="phone" name="phone" value="<?php echo e($old['phone'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <label for="address">Address</label>
          <input type="text" id="address" name="address" value="<?php echo e($old['address'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <label for="blood_group">Blood Group</label>
          <input type="text" id="blood_group" name="blood_group" maxlength="5" placeholder="e.g. O+" value="<?php echo e($old['blood'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
          <div class="hint">At least 6 characters.</div>
        </div>

        <div class="form-row">
          <label for="confirm">Confirm Password</label>
          <input type="password" id="confirm" name="confirm" required>
        </div>

        <button type="submit" class="btn btn-block">Create Account</button>
      </form>

      <p class="mt text-muted" style="text-align:center;">
        Already registered? <a href="<?php echo BASE_URL; ?>login.php">Log in</a>
      </p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
