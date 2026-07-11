<?php
/**
 * =============================================================================
 *  admin/manage_doctors.php  --  Create and manage doctor accounts
 * =============================================================================
 *  Admin can add a new doctor (creates a user + doctor profile) and toggle a
 *  doctor account between active and suspended.
 *
 *  SECURITY POINTS:
 *    - require_role('admin') gates the whole page.
 *    - CSRF token on every state-changing form.
 *    - Prepared statements for all inserts/updates.
 *    - Doctor password is bcrypt-hashed before storage.
 * =============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$errors = [];

// ---- Handle "add doctor" and "toggle status" POST actions -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $spec      = trim($_POST['specialization'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $branch    = $_POST['hospital_branch'] ?? '';
        $password  = $_POST['password'] ?? '';

        if ($full_name === '' || strlen($username) < 4 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            $errors[] = 'Please complete all fields (username 4+ chars, valid email, password 6+ chars).';
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=:u OR email=:e');
            $chk->execute([':u' => $username, ':e' => $email]);
            if ($chk->fetchColumn() > 0) {
                $errors[] = 'Username or email already in use.';
            }
        }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                // Create the login account (doctors are active immediately).
                $u = $pdo->prepare('INSERT INTO users (username,email,password_hash,role,status)
                                    VALUES (:u,:e,:ph,"doctor","active")');
                $u->execute([':u'=>$username, ':e'=>$email, ':ph'=>password_hash($password, PASSWORD_DEFAULT)]);
                $uid = $pdo->lastInsertId();

                // Create the doctor profile.
                $d = $pdo->prepare('INSERT INTO doctors (user_id,full_name,specialization,phone,hospital_branch)
                                    VALUES (:uid,:fn,:sp,:ph,:br)');
                $d->execute([':uid'=>$uid, ':fn'=>$full_name, ':sp'=>$spec, ':ph'=>$phone, ':br'=>$branch]);

                $pdo->commit();
                log_event($pdo, $_SESSION['user_id'], 'DOCTOR_ADD', "Admin created doctor '$username'.");
                set_flash('success', 'Doctor account created.');
                redirect('admin/manage_doctors.php');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $errors[] = 'Could not create doctor.';
            }
        }
    }

    if ($action === 'toggle') {
        // Suspend or reactivate a doctor account.
        $uid = (int) ($_POST['user_id'] ?? 0);
        $new = ($_POST['new_status'] ?? '') === 'suspended' ? 'suspended' : 'active';
        $st = $pdo->prepare('UPDATE users SET status=:s WHERE id=:id AND role="doctor"');
        $st->execute([':s'=>$new, ':id'=>$uid]);
        log_event($pdo, $_SESSION['user_id'], 'DOCTOR_STATUS', "Admin set doctor user #$uid to $new.");
        set_flash('success', 'Doctor status updated.');
        redirect('admin/manage_doctors.php');
    }
}

// ---- Fetch all doctors with their account status ----------------------------
$doctors = $pdo->query(
    'SELECT d.id, d.full_name, d.specialization, d.phone, d.hospital_branch,
            u.id AS user_id, u.username, u.email, u.status
     FROM doctors d JOIN users u ON u.id = d.user_id
     ORDER BY d.id'
)->fetchAll();

$page_title = 'Manage Doctors';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content">
        <div class="page-head">
            <h1>Manage Doctors</h1>
            <p class="text-muted">Create doctor accounts and control access.</p>
        </div>

        <?php if ($errors): ?>
            <div class="flash flash-error"><?php foreach ($errors as $e) echo e($e).'<br>'; ?></div>
        <?php endif; ?>

        <!-- Add doctor form -->
        <div class="card">
            <h2>Add a Doctor</h2>
            <form method="post" action="manage_doctors.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-row"><label>Full Name</label><input type="text" name="full_name" required></div>
                <div class="form-row"><label>Username</label><input type="text" name="username" required></div>
                <div class="form-row"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-row"><label>Specialization</label><input type="text" name="specialization" placeholder="e.g. Cardiology"></div>
                <div class="form-row"><label>Phone</label><input type="text" name="phone"></div>
                <div class="form-row">
                    <label>Hospital Branch</label>
                    <select name="hospital_branch">
                        <?php foreach (array_keys(HOSPITAL_BRANCHES) as $b): ?>
                            <option value="<?php echo e($b); ?>"><?php echo e($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Temporary Password</label><input type="password" name="password" required>
                    <div class="hint">At least 6 characters. The doctor can change it later.</div></div>
                <button class="btn" type="submit">Create Doctor</button>
            </form>
        </div>

        <!-- Doctor list -->
        <div class="card">
            <h2>All Doctors</h2>
            <div class="table-wrap">
                <table class="data">
                    <tr><th>#</th><th>Name</th><th>Specialization</th><th>Branch</th><th>Username</th><th>Status</th><th>Action</th></tr>
                    <?php foreach ($doctors as $d): ?>
                        <tr>
                            <td><?php echo $d['id']; ?></td>
                            <td><?php echo e($d['full_name']); ?></td>
                            <td><?php echo e($d['specialization']); ?></td>
                            <td><?php echo e($d['hospital_branch']); ?></td>
                            <td><?php echo e($d['username']); ?></td>
                            <td>
                                <span class="badge <?php echo $d['status']==='active'?'badge-green':'badge-red'; ?>">
                                    <?php echo e($d['status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" action="manage_doctors.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?php echo $d['user_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $d['status']==='active'?'suspended':'active'; ?>">
                                    <button class="btn btn-sm <?php echo $d['status']==='active'?'btn-danger':'btn-teal'; ?>"
                                            data-confirm="Change this doctor's access?">
                                        <?php echo $d['status']==='active'?'Suspend':'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
