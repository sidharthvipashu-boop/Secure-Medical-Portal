<?php
/**
 * =============================================================================
 *  logout.php  --  End the session securely
 * =============================================================================
 *  Clears all session data and destroys the session cookie so the user is fully
 *  logged out. Logging out promptly limits the window in which a shared/stolen
 *  session could be misused.
 * =============================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Record who logged out (for the audit trail) before we clear the session.
if (is_logged_in()) {
    log_event($pdo, $_SESSION['user_id'], 'LOGOUT', 'User ' . ($_SESSION['username'] ?? '') . ' logged out.');
}

// 1) Empty the $_SESSION array.
$_SESSION = [];

// 2) Delete the session cookie in the browser.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// 3) Destroy the session data on the server.
session_destroy();

set_flash('info', 'You have been logged out.');
redirect('login.php');
