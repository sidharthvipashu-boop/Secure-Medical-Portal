<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    log_event($pdo, $_SESSION['user_id'], 'LOGOUT', 'User ' . ($_SESSION['username'] ?? '') . ' logged out.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

set_flash('info', 'You have been logged out.');
redirect('login.php');
