<?php
// ============================================================
// users.php — User Management (Admin only)
// FIXED: syntax error in add action, added password hashing,
//        added logging for toggle/delete/password-reset actions
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireRole('Administrator');

$db   = getDB();
$pageTitle = 'Users';
$user = currentUser();
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD USER ─────────────────────────────────────────────
    if ($action === 'add') {
        $id    = generateId('U');
        $name  = trim($_POST['name']     ?? '');
        $email = trim($_POST['email']    ?? '');
        $pass  = trim($_POST['password'] ?? '');
        $role  = $_POST['role']          ?? 'Library Staff';

        if (!in_array($role, ['Administrator','Library Manager','Library Staff'])) {
            $role = 'Library Staff';
        }

        if ($name && $email && $pass) {
            try {
                // SECURE: hash the password before storing
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $db->prepare(
                    'INSERT INTO users (id, name, email, password, role) VALUES (?,?,?,?,?)'
                )->execute([$id, $name, $email, $hash, $role]);

                // FIX: logActivity MUST be called OUTSIDE the prepare() chain (was broken before)
                logActivity('Add User', "Added user: $name | Role: $role | Email: $email", 'users');
                $msg = 'ok:User added successfully.';
            } catch (Exception $e) {
                $msg = 'error:Email already exists or database error.';
            }
        } else {
            $msg = 'error:Please fill in all required fields.';
        }
    }

    // ── TOGGLE STATUS ─────────────────────────────────────────
    if ($action === 'toggle') {
        $uid       = $_POST['uid']        ?? '';
        $newStatus = $_POST['new_status'] ?? 'active';
        if ($uid && $uid !== $user['id']) {
            // Get user name for log
            $tgt = $db->prepare('SELECT name, role FROM users WHERE id = ?');
            $tgt->execute([$uid]);
            $tgtUser = $tgt->fetch();

            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $uid]);
            logActivity('Toggle User', "Set {$tgtUser['name']} ({$tgtUser['role']}) to $newStatus", 'users');
            $msg = 'ok:User status updated.';
        } else {
            $msg = 'error:Cannot deactivate your own account.';
        }
    }

    // ── EDIT ROLE ─────────────────────────────────────────────
    if ($action === 'edit_role') {
        $uid     = $_POST['uid']  ?? '';
        $newRole = $_POST['role'] ?? '';
        if ($uid && $uid !== $user['id'] && in_array($newRole, ['Administrator','Library Manager','Library Staff'])) {
            $tgt = $db->prepare('SELECT name, role FROM users WHERE id = ?');
            $tgt->execute([$uid]);
            $tgtUser = $tgt->fetch();

            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $uid]);
            logActivity('Edit User Role', "Changed {$tgtUser['name']} from {$tgtUser['role']} to $newRole", 'users');
            $msg = 'ok:User role updated.';
        } else {
            $msg = 'error:Invalid operation.';
        }
    }

    // ── RESET PASSWORD ────────────────────────────────────────
    if ($action === 'reset_password') {
        $uid     = $_POST['uid']          ?? '';
        $newPass = trim($_POST['new_password'] ?? '');
        if ($uid && $uid !== $user['id'] && strlen($newPass) >= 6) {
            $tgt = $db->prepare('SELECT name FROM users WHERE id = ?');
            $tgt->execute([$uid]);
            $tgtUser = $tgt->fetch();

            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $uid]);
            logActivity('Reset Password', "Reset password for user: {$tgtUser['name']}", 'users');
            $msg = 'ok:Password reset successfully.';
        } else {
            $msg = 'error:Password must be at least 6 characters.';
        }
    }

    // ── DELETE USER ───────────────────────────────────────────
    if ($action === 'delete') {
        $uid = $_POST['uid'] ?? '';
        if ($uid && $uid !== $user['id']) {
            $tgt = $db->prepare('SELECT name, role FROM users WHERE id = ?');
            $tgt->execute([$uid]);
            $tgtUser = $tgt->fetch();

            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            logActivity('Delete User', "Deleted user: {$tgtUser['name']} ({$tgtUser['role']})", 'users');
            $msg = 'ok:User deleted.';
        } else {
            $msg = 'error:Cannot delete your own account.';
        }
    }
}

$users = $db->query('SELECT * FROM users ORDER BY role, name')->fetchAll();

logActivity('Viewed Users', 'Opened user management page', 'users');
include __DIR__ . '/includes/layout.php';
?>

<?php if ($msg): list($t, $m) = explode(':', $msg, 2); ?>
<div style="background:<?= $t==='ok'?'var(--safe-bg)':'var(--crit-bg)' ?>;color:<?= $t==='ok'?'var(--safe)':'var(--crit)' ?>;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:500;border-left:3px solid <?= $t==='ok'?'var(--safe)':'var(--crit)' ?>;">
    <?= htmlspecialchars($m) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>User Management</h1>
        <p>Manage library staff accounts and roles</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addUserModal')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add User
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="font-family:var(--font-head);font-size:12px;color:var(--gray-400);"><?= htmlspecialchars($u['id']) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <span style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></span>
                    </div>
                </td>
                <td style="color:var(--gray-500);"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?php
                    $rc = match($u['role']) {
                        'Administrator'  => 'role-admin',
                        'Library Manager'=> 'role-manager',
                        default          => 'role-staff'
                    };
                    ?>
                    <span class="role-badge <?= $rc ?>"><?= htmlspecialchars($u['role']) ?></span>
                </td>
                <td>
                    <span class="badge <?= $u['status']==='active'?'badge-safe':'badge-gray' ?>">
                        <?= ucfirst($u['status']) ?>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--gray-400);">
                    <?= $u['last_login'] ?: '—' ?>
                </td>
                <td>
                    <?php if ($u['id'] !== $user['id']): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <!-- Toggle status -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action"     value="toggle">
                            <input type="hidden" name="uid"        value="<?= $u['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $u['status']==='active'?'inactive':'active' ?>">
                            <button type="submit" class="btn btn-outline btn-sm">
                                <?= $u['status']==='active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>

                        <!-- Edit Role -->
                        <button class="btn btn-outline btn-sm"
                                onclick="openEditRoleModal('<?= $u['id'] ?>','<?= addslashes($u['name']) ?>','<?= $u['role'] ?>')">
                            Edit Role
                        </button>

                        <!-- Reset Password -->
                        <button class="btn btn-outline btn-sm"
                                onclick="openResetPasswordModal('<?= $u['id'] ?>','<?= addslashes($u['name']) ?>')">
                            Reset PW
                        </button>

                        <!-- Delete -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete user <?= addslashes($u['name']) ?>? This cannot be undone.')">
                                Delete
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--gray-400);">Current user</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Add New User</div>
            <button class="modal-close" onclick="closeModal('addUserModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input class="form-control" type="text" name="name" required placeholder="e.g. Juan dela Cruz">
            </div>
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input class="form-control" type="email" name="email" required placeholder="user@library.edu">
            </div>
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input class="form-control" type="password" name="password" required placeholder="Minimum 6 characters" minlength="6">
                <div style="font-size:11px;color:var(--gray-400);margin-top:4px;">🔒 Will be stored as a secure bcrypt hash.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-control form-select" name="role">
                    <option value="Library Staff">Library Staff</option>
                    <option value="Library Manager">Library Manager</option>
                    <option value="Administrator">Administrator</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal-overlay" id="editRoleModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Role</div>
            <button class="modal-close" onclick="closeModal('editRoleModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_role">
            <input type="hidden" name="uid"    id="editRoleUid">
            <div class="form-group">
                <label class="form-label">User</label>
                <input class="form-control" id="editRoleName" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Role</label>
                <select class="form-control form-select" name="role" id="editRoleSelect">
                    <option value="Library Staff">Library Staff</option>
                    <option value="Library Manager">Library Manager</option>
                    <option value="Administrator">Administrator</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editRoleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPasswordModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Reset Password</div>
            <button class="modal-close" onclick="closeModal('resetPasswordModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="uid"    id="resetPwUid">
            <div class="form-group">
                <label class="form-label">User</label>
                <input class="form-control" id="resetPwName" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Password *</label>
                <input class="form-control" type="password" name="new_password" required minlength="6"
                       placeholder="Minimum 6 characters">
                <div style="font-size:11px;color:var(--gray-400);margin-top:4px;">🔒 Will be stored as a secure bcrypt hash.</div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = '<script>
function openEditRoleModal(uid, name, role) {
    document.getElementById("editRoleUid").value    = uid;
    document.getElementById("editRoleName").value   = name;
    document.getElementById("editRoleSelect").value = role;
    openModal("editRoleModal");
}
function openResetPasswordModal(uid, name) {
    document.getElementById("resetPwUid").value  = uid;
    document.getElementById("resetPwName").value = name;
    openModal("resetPasswordModal");
}
</script>';
include __DIR__ . '/includes/layout_footer.php';
?>
