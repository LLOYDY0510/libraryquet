<?php
// ============================================================
// php/logout.php
// FIXED: config.php (which starts the session) is now loaded
//        BEFORE logActivity, so the session/user is available.
//        Session is properly cleared before destroy.
// ============================================================
require_once __DIR__ . '/../includes/config.php';

// Log while session is still active and user data is available
logActivity('Logout', 'User signed out', 'logout');

// Proper session teardown
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}
session_destroy();

header('Location: ' . BASE_URL . '/index.php');
exit;
