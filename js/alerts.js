// ============================================================
//  alerts.js
// Resolve confirm modal logic.
// Depends on: app.js (openModal, closeModal)
// ============================================================

let _resolveAlertId = null;

function confirmResolve(alertId, zoneName) {
    _resolveAlertId = alertId;
    const label = document.getElementById('resolveZoneName');
    if (label) label.textContent = zoneName;

    const btn = document.getElementById('resolveConfirmBtn');
    if (btn) {
        btn.onclick = () => {
            const form = document.getElementById(`resolveForm_${alertId}`);
            if (form) form.submit();
        };
    }
    openModal('resolveConfirmModal');
}
