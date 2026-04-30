<?php
// ============================================================
// includes/auth.php — Auth guards for login page
// ============================================================
// NOTE: The main auth helpers (isLoggedIn, requireLogin,
// hasRole, requireRole, currentUser, logActivity, canDo) all
// live in config.php because they are needed by every page
// that includes config.php — even those that don't include
// auth.php (e.g. API endpoints).
//
// auth.php contains only the helpers that are exclusive to
// the login/register flow, to avoid circular includes.
// ============================================================
require_once __DIR__ . '/config.php';

/**
 * Called at the top of index.php (login page).
 * If the user is already logged in, redirect them to the dashboard.
 */
function redirectIfLoggedIn(): void {
    if (isLoggedIn()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}
