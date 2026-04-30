<?php
// ============================================================
// index.php — Login + Create Account
// FIXED: passwords now use password_hash() / password_verify()
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
    $role      = 'Library Staff'; // always default; admin can change later

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
                // SECURE: hash password with bcrypt before storing
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare(
                    'INSERT INTO users (id, name, email, password, role, status)
                     VALUES (?, ?, ?, ?, ?, "active")'
                )->execute([$id, $name, $email, $hash, $role]);

                // Log registration (no session yet — direct insert)
                try {
                    $db->prepare(
                        'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute([$id, $name, $role, 'Register',
                        "New account created: $name ($email)", 'index',
                        $_SERVER['REMOTE_ADDR'] ?? '']);
                } catch (Exception $e) { /* silent */ }

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

            // SECURE: use password_verify() for bcrypt hashes.
            // Also supports legacy plain-text passwords (seed data) and
            // automatically upgrades them to bcrypt on first login.
            $valid = false;
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $valid = true;
                } elseif ($user['password'] === $password) {
                    // Legacy plain-text — upgrade to bcrypt now
                    $valid = true;
                    $db->prepare('UPDATE users SET password = ? WHERE id = ?')
                       ->execute([password_hash($password, PASSWORD_BCRYPT), $user['id']]);
                }
            }

            if ($valid) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user']    = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role'],
                ];
                $db->prepare('UPDATE users SET last_login = ? WHERE id = ?')
                   ->execute([date('M d, Y h:i A'), $user['id']]);

                logActivity('Login', 'User signed in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'index');
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            } else {
                // Log failed login attempt (no session — direct insert)
                try {
                    $db->prepare(
                        'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    )->execute(['—', $email, 'Unknown', 'Login Failed',
                        "Failed login attempt for email: $email", 'index',
                        $_SERVER['REMOTE_ADDR'] ?? '']);
                } catch (Exception $e) { /* silent */ }

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
<title>LibraryQuiet — Sign In / Create Account</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --bg-page:    #0f1117;
    --bg-card:    #161b26;
    --bg-card2:   #1c2230;
    --border:     rgba(255,255,255,0.07);
    --border2:    rgba(255,255,255,0.14);
    --text-1:     #f0f2f8;
    --text-2:     #8892a4;
    --text-3:     #525e72;
    --accent:     #4f8ef7;
    --accent-dim: rgba(79,142,247,0.15);
    --safe:       #10d98e;
    --safe-bg:    rgba(16,217,142,0.1);
    --crit:       #f44336;
    --font-head:  'Syne', sans-serif;
    --font-body:  'DM Sans', sans-serif;
    --radius-sm:  8px;
}
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
html { font-size:14px; }
body {
    background:var(--bg-page); color:var(--text-1);
    font-family:var(--font-body); min-height:100vh;
    display:flex; align-items:center; justify-content:center;
    position:relative; overflow-x:hidden;
}
body::before {
    content:''; position:fixed; inset:0;
    background-image:
        linear-gradient(rgba(79,142,247,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79,142,247,.04) 1px, transparent 1px);
    background-size:40px 40px; pointer-events:none; z-index:0;
}
.bg-orb { position:fixed; border-radius:50%; filter:blur(80px); pointer-events:none; z-index:0; animation:orbFloat 8s ease-in-out infinite; }
.bg-orb-1 { width:400px;height:400px;background:rgba(79,142,247,.12);top:-100px;left:-100px; }
.bg-orb-2 { width:300px;height:300px;background:rgba(16,217,142,.08);bottom:-80px;right:-80px;animation-delay:-4s; }
.bg-orb-3 { width:200px;height:200px;background:rgba(94,79,219,.1);top:50%;left:60%;animation-delay:-2s; }
@keyframes orbFloat { 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-20px) scale(1.05)} }

.auth-wrap { position:relative; z-index:1; width:100%; max-width:440px; padding:20px; }
.auth-card {
    background:var(--bg-card); border:1px solid var(--border2); border-radius:20px;
    padding:36px 36px 30px;
    box-shadow:0 0 0 1px rgba(255,255,255,.04),0 24px 60px rgba(0,0,0,.5),0 0 40px rgba(79,142,247,.06);
    animation:cardIn .45s cubic-bezier(.22,.68,0,1.2) both;
}
@keyframes cardIn { from{opacity:0;transform:translateY(22px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }
.brand { display:flex; align-items:center; gap:11px; margin-bottom:22px; }
.brand-icon { width:42px;height:42px;border-radius:11px;background:linear-gradient(135deg,#4f8ef7,#7b5ea7);display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0;box-shadow:0 4px 14px rgba(79,142,247,.35); }
.brand-name { font-family:var(--font-head);font-size:19px;font-weight:800;color:var(--text-1);line-height:1.1; }
.brand-sub  { font-size:10.5px;color:var(--text-3);letter-spacing:.3px;margin-top:2px; }
.live-strip { display:flex;align-items:center;gap:8px;background:rgba(16,217,142,.07);border:1px solid rgba(16,217,142,.15);border-radius:8px;padding:7px 13px;margin-bottom:20px;font-size:11.5px;color:var(--safe);font-weight:500; }
.pulse-dot { width:7px;height:7px;border-radius:50%;background:var(--safe);flex-shrink:0;animation:pulse 1.8s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.8)} }
.live-right { margin-left:auto;font-size:10.5px;color:var(--text-3); }
.tab-row { display:grid;grid-template-columns:1fr 1fr;background:var(--bg-card2);border:1px solid var(--border);border-radius:10px;padding:4px;margin-bottom:24px; }
.tab-btn { background:none;border:none;padding:9px;border-radius:7px;font-family:var(--font-head);font-size:13px;font-weight:600;color:var(--text-3);cursor:pointer;transition:background .18s,color .18s; }
.tab-btn.active { background:var(--accent);color:#fff;box-shadow:0 2px 10px rgba(79,142,247,.35); }
.tab-btn:not(.active):hover { color:var(--text-1); }
.tab-panel { display:none; }
.tab-panel.active { display:block; }
.panel-heading { font-family:var(--font-head);font-size:20px;font-weight:700;color:var(--text-1);margin-bottom:4px; }
.panel-sub { font-size:12px;color:var(--text-3);margin-bottom:20px;line-height:1.5; }
.alert-box { border-radius:8px;padding:10px 14px;font-size:12.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px; }
.alert-box.error   { background:rgba(244,67,54,.1);border:1px solid rgba(244,67,54,.25);border-left:3px solid var(--crit);color:#f88; }
.alert-box.success { background:var(--safe-bg);border:1px solid rgba(16,217,142,.25);border-left:3px solid var(--safe);color:var(--safe); }
.secure-badge { display:inline-flex;align-items:center;gap:5px;background:rgba(16,217,142,.07);border:1px solid rgba(16,217,142,.18);border-radius:6px;padding:4px 10px;font-size:10.5px;color:var(--safe);margin-bottom:12px; }
.form-group { margin-bottom:15px; }
.form-row   { display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:0; }
.form-label { display:block;font-size:11px;font-weight:600;color:var(--text-2);letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px; }
.form-control { width:100%;background:var(--bg-card2);border:1px solid var(--border2);border-radius:var(--radius-sm);padding:10px 13px;font-size:13px;font-family:var(--font-body);color:var(--text-1);outline:none;transition:border-color .2s,box-shadow .2s; }
.form-control::placeholder { color:var(--text-3); }
.form-control:focus { border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-dim); }
.pw-wrap { position:relative; }
.pw-wrap .form-control { padding-right:42px; }
.pw-toggle { position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-3);cursor:pointer;padding:4px;display:flex;align-items:center;transition:color .15s; }
.pw-toggle:hover { color:var(--text-1); }
.btn-submit { width:100%;margin-top:6px;background:linear-gradient(135deg,#4f8ef7,#5e4fdb);border:none;border-radius:var(--radius-sm);padding:12px;font-size:13.5px;font-weight:700;font-family:var(--font-head);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .2s,transform .15s,box-shadow .2s;box-shadow:0 4px 18px rgba(79,142,247,.35);letter-spacing:.3px; }
.btn-submit:hover { opacity:.9;transform:translateY(-1px);box-shadow:0 6px 26px rgba(79,142,247,.5); }
.btn-submit.loading { pointer-events:none;opacity:.65; }
.spinner { width:15px;height:15px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:none; }
.btn-submit.loading .spinner   { display:block; }
.btn-submit.loading .btn-label { display:none; }
@keyframes spin { to { transform:rotate(360deg); } }
.divider { display:flex;align-items:center;gap:10px;margin:16px 0;font-size:11px;color:var(--text-3); }
.divider::before,.divider::after { content:'';flex:1;height:1px;background:var(--border); }
.switch-link { background:none;border:none;color:var(--accent);font-size:12px;font-weight:600;cursor:pointer;margin-left:4px; }
.auth-footer { margin-top:22px;padding-top:16px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--text-3); }
.footer-dot { width:4px;height:4px;border-radius:50%;background:var(--safe); }
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1);border-radius:3px; }
</style>
</head>
<body>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>

<div class="auth-wrap">
<div class="auth-card">
    <div class="brand">
        <div class="brand-icon">📡</div>
        <div>
            <div class="brand-name">LibraryQuiet</div>
            <div class="brand-sub">Noise Monitoring System &nbsp;·&nbsp; NBSC Campus</div>
        </div>
    </div>
    <div class="live-strip">
        <span class="pulse-dot"></span>
        System online &nbsp;·&nbsp; All sensors active
        <span class="live-right">v<?= APP_VERSION ?> &nbsp;·&nbsp; <span id="lClock"></span></span>
    </div>
    <div class="tab-row">
        <button class="tab-btn <?= $activeTab==='login'    ? 'active' : '' ?>" onclick="switchTab('login')"    id="tabLogin">Sign In</button>
        <button class="tab-btn <?= $activeTab==='register' ? 'active' : '' ?>" onclick="switchTab('register')" id="tabRegister">Create Account</button>
    </div>

    <!-- LOGIN PANEL -->
    <div class="tab-panel <?= $activeTab==='login' ? 'active' : '' ?>" id="panelLogin">
        <div class="panel-heading">Welcome back</div>
        <div class="panel-sub">Sign in to monitor and manage library noise levels.</div>

        <div class="secure-badge">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            Passwords are encrypted with bcrypt
        </div>

        <?php if ($loginError): ?>
        <div class="alert-box error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($loginError) ?>
        </div>
        <?php endif; ?>
        <?php if ($registerSuccess): ?>
        <div class="alert-box success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($registerSuccess) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input type="hidden" name="login" value="1">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@library.edu" required autocomplete="email">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
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
            <button type="submit" class="btn-submit" id="loginBtn">
                <div class="spinner"></div>
                <span class="btn-label">Sign In to Dashboard</span>
            </button>
        </form>
        <div class="divider">or</div>
        <div style="text-align:center;font-size:12px;color:var(--text-3);">
            Don't have an account? <button class="switch-link" onclick="switchTab('register')">Create one</button>
        </div>
    </div>

    <!-- REGISTER PANEL -->
    <div class="tab-panel <?= $activeTab==='register' ? 'active' : '' ?>" id="panelRegister">
        <div class="panel-heading">Create Account</div>
        <div class="panel-sub">Register to access the Library Noise Monitoring System.</div>

        <?php if ($registerError): ?>
        <div class="alert-box error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($registerError) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <input type="hidden" name="register" value="1">
            <input type="hidden" name="reg_role" value="Library Staff">
            <div class="form-group">
                <label class="form-label" for="reg_name">Full Name</label>
                <input class="form-control" type="text" id="reg_name" name="reg_name"
                       value="<?= htmlspecialchars($_POST['reg_name'] ?? '') ?>"
                       placeholder="e.g. Juan dela Cruz" required autocomplete="name">
            </div>
            <div class="form-group">
                <label class="form-label" for="reg_email">Email Address</label>
                <input class="form-control" type="email" id="reg_email" name="reg_email"
                       value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>"
                       placeholder="you@library.edu" required autocomplete="email">
            </div>
            <div class="form-row" style="margin-bottom:0">
                <div class="form-group">
                    <label class="form-label" for="reg_password">Password</label>
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
                <div class="form-group">
                    <label class="form-label" for="reg_confirm">Confirm</label>
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
            <div style="margin-top:10px;margin-bottom:14px;">
                <div style="height:4px;background:var(--border2);border-radius:4px;overflow:hidden;">
                    <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:width .3s,background .3s;"></div>
                </div>
                <div id="strengthLabel" style="font-size:10.5px;color:var(--text-3);margin-top:4px;min-height:16px;"></div>
            </div>
            <div style="margin-bottom:14px;font-size:11px;color:var(--text-3);">
                ℹ️ New accounts are assigned <strong style="color:var(--text-2);">Library Staff</strong> role. Admin can upgrade anytime.
            </div>
            <button type="submit" class="btn-submit" id="registerBtn">
                <div class="spinner"></div>
                <span class="btn-label">Create Account</span>
            </button>
        </form>
        <div class="divider">or</div>
        <div style="text-align:center;font-size:12px;color:var(--text-3);">
            Already have an account? <button class="switch-link" onclick="switchTab('login')">Sign in</button>
        </div>
    </div>

    <div class="auth-footer">
        <div>LQMS &nbsp;·&nbsp; <?= date('Y') ?> Library Noise Management</div>
        <div style="display:flex;align-items:center;gap:6px;">
            <span class="footer-dot"></span>
            <span id="lClock2" style="font-family:var(--font-head);font-size:10.5px;color:var(--text-3);letter-spacing:.4px;"></span>
        </div>
    </div>
</div>
</div>

<script>
function tick() {
    const t = new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    ['lClock','lClock2'].forEach(id => { const el=document.getElementById(id); if(el) el.textContent=t; });
}
tick(); setInterval(tick, 1000);

function switchTab(tab) {
    ['login','register'].forEach(t => {
        document.getElementById('panel'+t[0].toUpperCase()+t.slice(1))?.classList.toggle('active', t===tab);
        document.getElementById('tab'  +t[0].toUpperCase()+t.slice(1))?.classList.toggle('active', t===tab);
    });
}

const eyeOpen   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
const eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    icon.innerHTML = show ? eyeClosed : eyeOpen;
}

function checkStrength(val) {
    const bar=document.getElementById('strengthBar'), label=document.getElementById('strengthLabel');
    if (!val) { bar.style.width='0'; label.textContent=''; return; }
    let s=0;
    if(val.length>=6) s++; if(val.length>=10) s++;
    if(/[A-Z]/.test(val)) s++; if(/[0-9]/.test(val)) s++; if(/[^A-Za-z0-9]/.test(val)) s++;
    const lv=[{pct:'20%',color:'#f44336',text:'Very weak'},{pct:'40%',color:'#f5a623',text:'Weak'},{pct:'60%',color:'#f5a623',text:'Fair'},{pct:'80%',color:'#10d98e',text:'Strong'},{pct:'100%',color:'#10d98e',text:'Very strong'}][Math.min(s,4)];
    bar.style.width=lv.pct; bar.style.background=lv.color; label.style.color=lv.color; label.textContent=lv.text;
}

document.getElementById('loginForm').addEventListener('submit',()=>document.getElementById('loginBtn').classList.add('loading'));
document.getElementById('registerForm').addEventListener('submit',function(e){
    if(document.getElementById('reg_password').value !== document.getElementById('reg_confirm').value){
        e.preventDefault(); alert('Passwords do not match.'); return;
    }
    document.getElementById('registerBtn').classList.add('loading');
});
</script>
</body>
</html>
