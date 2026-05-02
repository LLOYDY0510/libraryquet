<?php
// ============================================================
// LQMS — includes/config.php
//
// CHANGES FROM PREVIOUS VERSION:
//   1. logActivity() now parses + writes the browser column
//      added to activity_logs in the updated setup.sql.
//   2. DB_PASS placeholder — fill in your actual password.
//      Never commit real credentials to version control.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Base URL ──────────────────────────────────────────────────
// Change to match your deployment path.
// Local XAMPP example:  '/library-saba'
// Shared hosting root:  '' (empty string)
define('BASE_URL', '/');

// ── Database ──────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'saba');
define('DB_USER',    'root');              // ← your actual username
define('DB_PASS',    '');            // ← your actual password
define('DB_CHARSET', 'utf8mb4');

// ── App Meta ──────────────────────────────────────────────────
define('APP_NAME',    'LibraryQuiet Monitoring System');
define('APP_SHORT',   'LQMS');
define('APP_VERSION', '1.0.0');

// ── Noise Thresholds (dB) ─────────────────────────────────────
define('NOISE_SAFE',     40);
define('NOISE_WARNING',  60);
define('NOISE_CRITICAL', 75);

// ── Simulation interval: 7 minutes ───────────────────────────
define('SIM_INTERVAL_SECONDS', 420);

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── PDO Connection ────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn     = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;color:#c0392b;padding:20px;">'
              . '<strong>Database Connection Failed.</strong><br>'
              . 'Please contact your system administrator.<br><br>'
              . '<small>Error: ' . htmlspecialchars($e->getMessage()) . '</small>'
              . '</div>');
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

// ── Browser Parser ────────────────────────────────────────────
// Parses a raw HTTP_USER_AGENT string into a short browser name.
// Used by logActivity() to populate the browser column.
function parseBrowser(string $ua): string {
    if (str_contains($ua, 'Edg'))     return 'Edge';
    if (str_contains($ua, 'OPR')
     || str_contains($ua, 'Opera'))   return 'Opera';
    if (str_contains($ua, 'Chrome'))  return 'Chrome';
    if (str_contains($ua, 'Firefox')) return 'Firefox';
    if (str_contains($ua, 'Safari'))  return 'Safari';
    if (str_contains($ua, 'MSIE')
     || str_contains($ua, 'Trident')) return 'IE';
    return 'Other';
}

// ── Activity Logger ───────────────────────────────────────────
// Logs every important system action to the activity_logs table.
// Updated: now also writes to the `browser` column added in
// the latest setup.sql revision.
function logActivity(string $action, string $detail = '', string $page = ''): void {
    if (!isLoggedIn()) return;
    $u  = currentUser();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    try {
        getDB()->prepare(
            'INSERT INTO activity_logs
               (user_id, user_name, user_role, action, detail, page, ip, browser, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $u['id'],
            $u['name'],
            $u['role'],
            $action,
            $detail,
            $page ?: basename($_SERVER['PHP_SELF'], '.php'),
            $_SERVER['REMOTE_ADDR'] ?? '',
            parseBrowser($ua),
            substr($ua, 0, 255),
        ]);
    } catch (Exception $e) {
        // Silent — a log failure must never break the page
    }
}

// ── Zone Permission Checks ────────────────────────────────────
// Role-based permission helper. Replaces the old canDo() that
// queried the deleted role_rules table.
//
//   Administrator   → full access
//   Library Manager → zones, reports, alerts
//   Library Staff   → resolve alerts only
//
function canDo(string $action): bool {
    if (hasRole('Administrator')) return true;

    $role = currentUser()['role'] ?? '';

    $managerPerms = [
        'add_zone', 'edit_zone', 'delete_zone',
        'override_zone', 'view_reports', 'gen_reports',
        'resolve_alert',
    ];
    $staffPerms = ['resolve_alert'];

    if ($role === 'Library Manager') return in_array($action, $managerPerms, true);
    if ($role === 'Library Staff')   return in_array($action, $staffPerms,   true);

    return false;
}
