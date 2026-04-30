<?php
// ============================================================
// LIBRARY QUIET MONITORING SYSTEM — CONFIG
// UPDATED: canDo() removed (depended on deleted role_rules table).
//          Zone permissions now controlled directly by hasRole().
//          All other helpers unchanged.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Base URL ──────────────────────────────────────────────────
define('BASE_URL', '/library-saba');

// ── Database ──────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'u442411629_librarysaba');
define('DB_USER',    'u442411629_dev_library');
define('DB_PASS',    '6nV6$5BSLjjl');
define('DB_CHARSET', 'utf8mb4');

// ── App Meta ──────────────────────────────────────────────────
define('APP_NAME',    'LibraryQuiet Monitoring System');
define('APP_SHORT',   'LQMS');
define('APP_VERSION', '1.0.0');

// ── Noise Thresholds (dB) ─────────────────────────────────────
define('NOISE_SAFE',    40);
define('NOISE_WARNING', 60);
define('NOISE_CRITICAL',75);

// ── Simulation interval: 7 minutes ───────────────────────────
define('SIM_INTERVAL_SECONDS', 420);

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── PDO Connection ────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;color:#c0392b;padding:20px;">
                 <strong>Database Connection Failed.</strong><br>
                 Please contact your system administrator.<br><br>
                 <small>Error: ' . htmlspecialchars($e->getMessage()) . '</small>
                 </div>');
        }
    }
    return $pdo;
}

// ── Auth Helpers ──────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function hasRole(string ...$roles): bool {
    $userRole = $_SESSION['user']['role'] ?? '';
    return in_array($userRole, $roles, true);
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

// ── ID Generator ──────────────────────────────────────────────
function generateId(string $prefix): string {
    return $prefix . '-' . strtoupper(substr(uniqid(), -6));
}

// ── Noise Level Helpers ───────────────────────────────────────
function noiseStatus(float $db): string {
    if ($db < NOISE_SAFE)    return 'safe';
    if ($db < NOISE_WARNING) return 'warning';
    return 'critical';
}

function noiseLabel(float $db): string {
    if ($db < NOISE_SAFE)    return 'Quiet';
    if ($db < NOISE_WARNING) return 'Moderate';
    return 'Loud';
}

// ── Activity Logger ───────────────────────────────────────────
// Logs every important system action to the activity_logs table.
// Called throughout the system for login, logout, CRUD, alerts, etc.
function logActivity(string $action, string $detail = '', string $page = ''): void {
    if (!isLoggedIn()) return;
    $u = currentUser();
    try {
        getDB()->prepare(
            'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $u['id'],
            $u['name'],
            $u['role'],
            $action,
            $detail,
            $page ?: basename($_SERVER['PHP_SELF'], '.php'),
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Exception $e) { /* silent — never break the page for a log failure */ }
}

// ── Zone Permission Checks ────────────────────────────────────
// Replaces the old canDo() function that queried the role_rules table.
// Permissions are now hardcoded by role:
//
//   Administrator  → full access to everything
//   Library Manager→ can add/edit/override zones, view/generate reports, resolve alerts
//   Library Staff  → can resolve alerts only (read-only everywhere else)
//
function canDo(string $action): bool {
    // Administrator can always do everything
    if (hasRole('Administrator')) return true;

    $role = currentUser()['role'] ?? '';

    $managerPerms = ['add_zone', 'edit_zone', 'override_zone', 'view_reports', 'gen_reports', 'resolve_alert'];
    $staffPerms   = ['resolve_alert'];

    if ($role === 'Library Manager') {
        return in_array($action, $managerPerms, true);
    }
    if ($role === 'Library Staff') {
        return in_array($action, $staffPerms, true);
    }

    return false;
}
