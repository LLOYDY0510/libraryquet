<?php
// ============================================================
// users.php — User Management (Admin only)
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireRole('Administrator');

$db        = getDB();
$pageTitle = 'Users';
$pageSubtitle = 'Manage library staff accounts and roles';
$user      = currentUser();
$msg       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id    = generateId('U');
        $name  = trim($_POST['name']     ?? '');
        $email = trim($_POST['email']    ?? '');
        $pass  = trim($_POST['password'] ?? '');
        $role  = $_POST['role']          ?? 'Library Staff';
        if (!in_array($role, ['Administrator','Library Manager','Library Staff'])) $role = 'Library Staff';
        if ($name && $email && $pass) {
            try {
                $db->prepare('INSERT INTO users (id, name, email, password, role) VALUES (?,?,?,?,?)')
                   ->execute([$id, $name, $email, password_hash($pass, PASSWORD_BCRYPT), $role]);
                logActivity('Add User', "Added user: $name | Role: $role | Email: $email", 'users');
                $msg = 'ok:User added successfully.';
            } catch (Exception $e) {
                $msg = 'error:Email already exists or database error.';
            }
        } else {
            $msg = 'error:Please fill in all required fields.';
        }
    }

    if ($action === 'toggle') {
        $uid = $_POST['uid'] ?? ''; $newStatus = $_POST['new_status'] ?? 'active';
        if ($uid && $uid !== $user['id']) {
            $tgt = $db->prepare('SELECT name, role FROM users WHERE id = ?'); $tgt->execute([$uid]); $tgtUser = $tgt->fetch();
            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $uid]);
            logActivity('Toggle User', "Set {$tgtUser['name']} ({$tgtUser['role']}) to $newStatus", 'users');
            $msg = 'ok:User status updated.';
        } else { $msg = 'error:Cannot deactivate your own account.'; }
    }

    if ($action === 'edit_role') {
        $uid = $_POST['uid'] ?? ''; $newRole = $_POST['role'] ?? '';
        if ($uid && $uid !== $user['id'] && in_array($newRole, ['Administrator','Library Manager','Library Staff'])) {
            $tgt = $db->prepare('SELECT name, role FROM users WHERE id = ?'); $tgt->execute([$uid]); $tgtUser = $tgt->fetch();
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $uid]);
            logActivity('Edit User Role', "Changed {$tgtUser['name']} from {$tgtUser['role']} to $newRole", 'users');
            $msg = 'ok:User role updated.';
        } else { $msg = 'error:Invalid operation.'; }
    }

    if ($action === 'reset_password') {
        $uid = $_POST['uid'] ?? ''; $newPass = trim($_POST['new_password'] ?? '');
        if ($uid && $uid !== $user['id'] && strlen($newPass) >= 6) {
            $tgt = $db->prepare('SELECT name FROM users WHERE id = ?'); $tgt->execute([$uid]); $tgtUser = $tgt->fetch();
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($newPass, PASSWORD_BCRYPT), $uid]);
            logActivity('Reset Password', "Reset password for user: {$tgtUser['name']}", 'users');
            $msg = 'ok:Password reset successfully.';
        } else { $msg = 'error:Password must be at least 6 characters.'; }
    }

    if ($action === 'delete') {
        $uid = $_POST['uid'] ?? '';
        if ($uid && $uid !== $user['id']) {
            $tgt = $db->prepare('SELECT name, role FROM users WHERE id = ?'); $tgt->execute([$uid]); $tgtUser = $tgt->fetch();
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            logActivity('Delete User', "Deleted user: {$tgtUser['name']} ({$tgtUser['role']})", 'users');
            $msg = 'ok:User deleted.';
        } else { $msg = 'error:Cannot delete your own account.'; }
    }
}

$users = $db->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
logActivity('Viewed Users', 'Opened user management page', 'users');
$extraScripts = '<script src="' . BASE_URL . '/js/users.js"></script>';
include __DIR__ . '/includes/layout.php';
?>

<?php if ($msg): [$t, $m] = explode(':', $msg, 2); ?>
<div class="page-flash <?= $t ?>">
    <?php if ($t === 'ok'): ?>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <?php else: ?>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php endif; ?>
    <?= htmlspecialchars($m) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-left">
        <h1>User Management</h1>
        <p>Manage library staff accounts and roles</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addUserModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
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
            <?php foreach ($users as $u):
                $roleCls = match($u['role']) {
                    'Administrator'   => 'role-admin',
                    'Library Manager' => 'role-manager',
                    default           => 'role-staff'
                };
                $isMe = $u['id'] === $user['id'];
            ?>
            <tr>
                <td class="td-id"><?= htmlspecialchars($u['id']) ?></td>
                <td>
                    <div class="user-cell">
                        <div class="user-avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                        <span class="td-bold"><?= htmlspecialchars($u['name']) ?></span>
                        <?php if ($isMe): ?><span class="you-badge">you</span><?php endif; ?>
                    </div>
                </td>
                <td class="td-meta"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="role-badge <?= $roleCls ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                <td>
                    <span class="badge <?= $u['status']==='active' ? 'badge-safe' : 'badge-gray' ?>">
                        <?= ucfirst($u['status']) ?>
                    </span>
                </td>
                <td class="td-sub"><?= htmlspecialchars($u['last_login'] ?: '—') ?></td>
                <td>
                    <?php if (!$isMe): ?>
                    <div class="td-actions">
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action"     value="toggle">
                            <input type="hidden" name="uid"        value="<?= $u['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $u['status']==='active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="btn btn-outline btn-sm">
                                <?= $u['status']==='active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <button class="btn btn-outline btn-sm"
                                onclick="openEditRoleModal('<?= $u['id'] ?>','<?= addslashes(htmlspecialchars($u['name'])) ?>','<?= $u['role'] ?>')">
                            Edit Role
                        </button>
                        <button class="btn btn-outline btn-sm"
                                onclick="openResetPasswordModal('<?= $u['id'] ?>','<?= addslashes(htmlspecialchars($u['name'])) ?>')">
                            Reset PW
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="confirmDeleteUser('<?= $u['id'] ?>','<?= addslashes(htmlspecialchars($u['name'])) ?>')">
                            Delete
                        </button>
                    </div>
                    <?php else: ?>
                    <span class="td-sub">Current user</span>
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
                <input class="form-control" type="password" name="password" required minlength="6" placeholder="Minimum 6 characters">
                <div class="form-hint">🔒 Stored as a secure bcrypt hash.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-control form-select" name="role">
                    <option value="Library Staff">Library Staff</option>
                    <option value="Library Manager">Library Manager</option>
                    <option value="Administrator">Administrator</option>
                </select>
            </div>
            <div class="modal-footer">
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
            <input type="hidden" name="uid" id="editRoleUid">
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
            <div class="modal-footer">
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
            <input type="hidden" name="uid" id="resetPwUid">
            <div class="form-group">
                <label class="form-label">User</label>
                <input class="form-control" id="resetPwName" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">New Password *</label>
                <input class="form-control" type="password" name="new_password" required minlength="6" placeholder="Minimum 6 characters">
                <div class="form-hint">🔒 Stored as a secure bcrypt hash.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Confirm Modal -->
<div class="modal-overlay" id="deleteUserModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title">Delete User</div>
            <button class="modal-close" onclick="closeModal('deleteUserModal')">✕</button>
        </div>
        <div class="delete-confirm-body">
            <div class="delete-confirm-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                    <path d="M9 6V4h6v2"/>
                </svg>
            </div>
            <div class="delete-confirm-title">Delete this user?</div>
            <div class="delete-confirm-sub">
                <span class="delete-confirm-zone" id="deleteUserName"></span> will be permanently removed.
                This cannot be undone.
            </div>
        </div>
        <form method="POST" id="deleteUserForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="uid" value="">
        </form>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('deleteUserModal')">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteUserForm').submit()">Yes, Delete</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
