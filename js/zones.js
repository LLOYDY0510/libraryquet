// ============================================================
//  zones.js
// Zones page: modal population helpers, delete confirmation,
// progress bar animation on load.
// Depends on: app.js (openModal, closeModal)
// ============================================================

// ── Override modal ────────────────────────────────────────────
function openOverrideModal(id, name, level) {
    document.getElementById('overrideZoneId').value   = id;
    document.getElementById('overrideZoneName').value = name;
    document.getElementById('overrideLevel').value    = level;
    openModal('overrideModal');
}

// ── Edit modal ────────────────────────────────────────────────
function openEditModal(id, name, floor, warn, crit, cap, desc, lat, lng) {
    document.getElementById('editZoneId').value    = id;
    document.getElementById('editZoneName').value  = name;
    document.getElementById('editZoneFloor').value = floor;
    document.getElementById('editZoneWarn').value  = warn;
    document.getElementById('editZoneCrit').value  = crit;
    document.getElementById('editZoneCap').value   = cap;
    document.getElementById('editZoneDesc').value  = desc;
    document.getElementById('editZoneLat').value   = lat;
    document.getElementById('editZoneLng').value   = lng;
    openModal('editZoneModal');
}

// ── Delete confirmation modal ─────────────────────────────────
// Replaces the native browser confirm() popup.
// Usage: <button onclick="confirmDelete('Z001', 'Reading Area')">
let _pendingDeleteForm = null;

function confirmDelete(zoneId, zoneName) {
    const label = document.getElementById('deleteZoneName');
    if (label) label.textContent = zoneName;

    // Wire the hidden form with the correct zone_id
    const form = document.getElementById('deleteZoneForm');
    if (form) {
        form.querySelector('[name="zone_id"]').value = zoneId;
    }
    _pendingDeleteForm = form;

    openModal('deleteConfirmModal');
}

function submitDelete() {
    if (_pendingDeleteForm) {
        _pendingDeleteForm.submit();
    }
}

// ── Progress bars ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // app.js already handles .db-bar-fill[data-pct].
    // Zone cards use .zone-prog-fill[data-pct] — trigger them here
    // with a slightly longer delay so the card entrance animation
    // completes first.
    document.querySelectorAll('.zone-prog-fill[data-pct]').forEach(el => {
        const pct = parseFloat(el.dataset.pct) || 0;
        setTimeout(() => { el.style.width = `${pct}%`; }, 160);
    });
});
