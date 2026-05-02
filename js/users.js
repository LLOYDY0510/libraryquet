// ============================================================
// users.js
// Modal population helpers for edit role, reset password,
// and delete user confirm.
// Depends on: app.js (openModal, closeModal)
// ============================================================

function openEditRoleModal(uid, name, role) {
    document.getElementById('editRoleUid').value    = uid;
    document.getElementById('editRoleName').value   = name;
    document.getElementById('editRoleSelect').value = role;
    openModal('editRoleModal');
}

function openResetPasswordModal(uid, name) {
    document.getElementById('resetPwUid').value  = uid;
    document.getElementById('resetPwName').value = name;
    openModal('resetPasswordModal');
}

function confirmDeleteUser(uid, name) {
    const label = document.getElementById('deleteUserName');
    if (label) label.textContent = name;
    const form = document.getElementById('deleteUserForm');
    if (form) form.querySelector('[name="uid"]').value = uid;
    openModal('deleteUserModal');
}
