<?php
// ============================================================
// index.php — Login + Create Account
// PHP logic is unchanged. HTML/CSS/JS refactored:
//   - Removed 350-line <style> block → login.css
//   - Removed inline <script> block  → login.js
//   - Class names updated to light theme token system
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
redirectIfLoggedIn();

$loginError      = '';
$registerError   = '';
$registerSuccess = '';
$activeTab       = 'login';

// ── REGISTER ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $activeTab = 'register';
    $name      = trim($_POST['reg_name']     ?? '');
    $email     = trim($_POST['reg_email']    ?? '');
    $password  = trim($_POST['reg_password'] ?? '');
    $confirm   = trim($_POST['reg_confirm']  ?? '');
    $role      = 'Library Staff';

    if (!$name || !$email || !$password || !$confirm) {
        $registerError = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $registerError = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $registerError = 'Passwords do not match.';
    } else {
        try {
            $db    = getDB();
            $check = $db->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $registerError = 'An account with that email already exists.';
            } else {
                $id   = generateId('U');
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare(
                    'INSERT INTO users (id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, "active")'
                )->execute([$id, $name, $email, $hash, $role]);
                try {
                    $db->prepare(
                        'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute([$id, $name, $role, 'Register', "New account created: $name ($email)", 'index', $_SERVER['REMOTE_ADDR'] ?? '']);
                } catch (Exception $e) {}
                $registerSuccess = 'Account created successfully! You can now sign in.';
                $activeTab = 'login';
            }
        } catch (Exception $e) {
            $registerError = 'System error. Please try again later.';
        }
    }
}

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $activeTab = 'login';
    $email     = trim($_POST['email']    ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $loginError = 'Please fill in all fields.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $valid = false;
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $valid = true;
                } elseif ($user['password'] === $password) {
                    $valid = true;
                    $db->prepare('UPDATE users SET password = ? WHERE id = ?')
                       ->execute([password_hash($password, PASSWORD_BCRYPT), $user['id']]);
                }
            }
            if ($valid) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user']    = ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
                $db->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([date('M d, Y h:i A'), $user['id']]);
                logActivity('Login', 'User signed in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'index');
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            } else {
                try {
                    $db->prepare(
                        'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute(['—', $email, 'Unknown', 'Login Failed', "Failed login attempt for email: $email", 'index', $_SERVER['REMOTE_ADDR'] ?? '']);
                } catch (Exception $e) {}
                $loginError = 'Invalid email or password. Please try again.';
            }
        } catch (Exception $e) {
            $loginError = 'System error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibraryQuiet — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/main.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/login.css">
</head>
<body class="auth-page">

<div class="auth-blob auth-blob-1"></div>
<div class="auth-blob auth-blob-2"></div>

<div class="auth-wrap">
<div class="auth-card">

    <div class="auth-brand">
        <div class="auth-brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                <line x1="12" y1="19" x2="12" y2="23"/>
                <line x1="8"  y1="23" x2="16" y2="23"/>
            </svg>
        </div>
        <div>
            <div class="auth-brand-name">LibraryQuiet</div>
            <div class="auth-brand-sub">Noise Monitoring System &nbsp;·&nbsp; NBSC Campus</div>
        </div>
    </div>

    <div class="auth-tabs">
        <button class="auth-tab-btn <?= $activeTab === 'login'    ? 'active' : '' ?>" id="authTab_login"    onclick="switchTab('login')">Sign In</button>
        <button class="auth-tab-btn <?= $activeTab === 'register' ? 'active' : '' ?>" id="authTab_register" onclick="switchTab('register')">Create Account</button>
    </div>

    <!-- LOGIN PANEL -->
    <div class="auth-panel <?= $activeTab === 'login' ? 'active' : '' ?>" id="authPanel_login">
        <div class="auth-panel-title">Welcome back</div>
        <div class="auth-panel-sub">Sign in to monitor and manage library noise levels.</div>

        <?php if ($loginError): ?>
        <div class="auth-alert error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($loginError) ?>
        </div>
        <?php endif; ?>
        <?php if ($registerSuccess): ?>
        <div class="auth-alert success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($registerSuccess) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input type="hidden" name="login" value="1">
            <div class="auth-form-group">
                <label class="auth-form-label" for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@library.edu" required autocomplete="email">
            </div>
            <div class="auth-form-group">
                <label class="auth-form-label" for="password">Password</label>
                <div class="pw-wrap">
                    <input class="form-control" type="password" id="password" name="password"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" onclick="togglePw('password','eye1')">
                        <svg id="eye1" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="auth-btn-submit" id="loginBtn">
                <div class="btn-spinner"></div>
                <span class="btn-label-tx">Sign In to Dashboard</span>
            </button>
        </form>

        <div class="auth-divider">or</div>
        <div class="auth-switch-text">
            Don't have an account? <button class="auth-switch-link" onclick="switchTab('register')">Create one</button>
        </div>
    </div>

    <!-- REGISTER PANEL -->
    <div class="auth-panel <?= $activeTab === 'register' ? 'active' : '' ?>" id="authPanel_register">
        <div class="auth-panel-title">Create Account</div>
        <div class="auth-panel-sub">Register to access the Library Noise Monitoring System.</div>

        <?php if ($registerError): ?>
        <div class="auth-alert error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($registerError) ?>
        </div>
        <?php endif; ?>
        <div class="auth-alert error" id="registerMatchError" style="display:none">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Passwords do not match.
        </div>

        <form method="POST" id="registerForm">
            <input type="hidden" name="register" value="1">
            <input type="hidden" name="reg_role"  value="Library Staff">
            <div class="auth-form-group">
                <label class="auth-form-label" for="reg_name">Full Name</label>
                <input class="form-control" type="text" id="reg_name" name="reg_name"
                       value="<?= htmlspecialchars($_POST['reg_name'] ?? '') ?>"
                       placeholder="e.g. Juan dela Cruz" required autocomplete="name">
            </div>
            <div class="auth-form-group">
                <label class="auth-form-label" for="reg_email">Email Address</label>
                <input class="form-control" type="email" id="reg_email" name="reg_email"
                       value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>"
                       placeholder="you@library.edu" required autocomplete="email">
            </div>
            <div class="auth-form-row">
                <div class="auth-form-group">
                    <label class="auth-form-label" for="reg_password">Password</label>
                    <div class="pw-wrap">
                        <input class="form-control" type="password" id="reg_password" name="reg_password"
                               placeholder="Min. 6 chars" required minlength="6" oninput="checkStrength(this.value)">
                        <button type="button" class="pw-toggle" onclick="togglePw('reg_password','eye2')">
                            <svg id="eye2" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="auth-form-group">
                    <label class="auth-form-label" for="reg_confirm">Confirm</label>
                    <div class="pw-wrap">
                        <input class="form-control" type="password" id="reg_confirm" name="reg_confirm"
                               placeholder="Repeat password" required minlength="6">
                        <button type="button" class="pw-toggle" onclick="togglePw('reg_confirm','eye3')">
                            <svg id="eye3" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="strength-wrap">
                <div class="strength-bar-track">
                    <div class="strength-bar-fill" id="strengthBarFill"></div>
                </div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>

            <div class="auth-role-note">
                New accounts are assigned <strong>Library Staff</strong> role.
                An administrator can upgrade your role at any time.
            </div>

            <button type="submit" class="auth-btn-submit" id="registerBtn">
                <div class="btn-spinner"></div>
                <span class="btn-label-tx">Create Account</span>
            </button>
        </form>

        <div class="auth-divider">or</div>
        <div class="auth-switch-text">
            Already have an account? <button class="auth-switch-link" onclick="switchTab('login')">Sign in</button>
        </div>
    </div>

    <div class="auth-footer">
        <div>LQMS &nbsp;·&nbsp; <?= date('Y') ?> Library Noise Management</div>
    </div>

</div>
</div>

<script src="<?= BASE_URL ?>/js/login.js"></script>
</body>
</html>
