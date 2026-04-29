<?php
// ============================================================
// zones.php — Zone Management
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Zones';
$user      = currentUser();
$msg       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $zoneId = $_POST['zone_id'] ?? '';

    // ── OVERRIDE ──────────────────────────────────────────────
    if ($action === 'override') {
        if (!canDo('override_zone')) {
            $msg = 'error:You do not have permission to override zones.';
        } else {
            $level = (float)($_POST['override_level'] ?? 0);
            if ($level < 0 || $level > 120) {
                $msg = 'error:Level must be between 0 and 120 dB.';
            } else {
                $db->prepare('UPDATE zones SET level = ?, manual_override = 1 WHERE id = ?')
                   ->execute([$level, $zoneId]);
                $db->prepare('REPLACE INTO sensor_overrides (zone_id, level, set_by, set_at, set_date) VALUES (?,?,?,?,?)')
                   ->execute([$zoneId, $level, $user['name'], date('h:i A'), date('F d, Y')]);
                logActivity('Override Zone', "Set $zoneId to {$level} dB", 'zones');
                $msg = 'ok:Override applied for zone ' . $zoneId;
            }
        }

    // ── CLEAR OVERRIDE ────────────────────────────────────────
    } elseif ($action === 'clear_override') {
        if (!canDo('override_zone')) {
            $msg = 'error:You do not have permission to clear overrides.';
        } else {
            $db->prepare('UPDATE zones SET manual_override = 0 WHERE id = ?')->execute([$zoneId]);
            $db->prepare('DELETE FROM sensor_overrides WHERE zone_id = ?')->execute([$zoneId]);
            logActivity('Clear Override', "Cleared override for $zoneId", 'zones');
            $msg = 'ok:Override cleared.';
        }

    // ── ADD ZONE ──────────────────────────────────────────────
    } elseif ($action === 'add_zone') {
        if (!canDo('add_zone')) {
            $msg = 'error:You do not have permission to add zones.';
        } else {
            $name    = trim($_POST['zone_name']    ?? '');
            $floor   = trim($_POST['zone_floor']   ?? '');
            $sensor  = trim($_POST['zone_sensor']  ?? '');
            $cap     = (int)($_POST['zone_capacity']  ?? 0);
            $warn    = (int)($_POST['zone_warn']      ?? 40);
            $crit    = (int)($_POST['zone_crit']      ?? 60);
            $desc    = trim($_POST['zone_desc']    ?? '');
            $lat     = (float)($_POST['zone_lat']  ?? 0);
            $lng     = (float)($_POST['zone_lng']  ?? 0);

            if (!$name || !$floor || !$sensor) {
                $msg = 'error:Name, floor, and sensor ID are required.';
            } else {
                $newId = generateId('Z');
                $db->prepare(
                    'INSERT INTO zones (id, name, floor, sensor, capacity, warn_threshold, crit_threshold,
                     level, battery, occupied, lat, lng, description, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, 100, 0, ?, ?, ?, "active")'
                )->execute([$newId, $name, $floor, $sensor, $cap, $warn, $crit, $lat ?: null, $lng ?: null, $desc]);
                logActivity('Add Zone', "Added zone: $name ($newId)", 'zones');
                $msg = 'ok:Zone "' . $name . '" added successfully.';
            }
        }

    // ── EDIT ZONE ─────────────────────────────────────────────
    } elseif ($action === 'edit_zone') {
        if (!canDo('edit_zone')) {
            $msg = 'error:You do not have permission to edit zones.';
        } else {
            $name   = trim($_POST['zone_name']     ?? '');
            $floor  = trim($_POST['zone_floor']    ?? '');
            $warn   = (int)($_POST['zone_warn']    ?? 40);
            $crit   = (int)($_POST['zone_crit']    ?? 60);
            $cap    = (int)($_POST['zone_capacity']?? 0);
            $desc   = trim($_POST['zone_desc']     ?? '');
            $lat    = (float)($_POST['zone_lat']   ?? 0);
            $lng    = (float)($_POST['zone_lng']   ?? 0);

            $db->prepare(
                'UPDATE zones SET name=?, floor=?, warn_threshold=?, crit_threshold=?,
                 capacity=?, description=?, lat=?, lng=? WHERE id=?'
            )->execute([$name, $floor, $warn, $crit, $cap, $desc, $lat ?: null, $lng ?: null, $zoneId]);
            logActivity('Edit Zone', "Edited zone $zoneId: $name", 'zones');
            $msg = 'ok:Zone updated.';
        }

    // ── DELETE ZONE ───────────────────────────────────────────
    } elseif ($action === 'delete_zone') {
        if (!canDo('delete_zone')) {
            $msg = 'error:You do not have permission to delete zones.';
        } else {
            $db->prepare('DELETE FROM zones WHERE id = ?')->execute([$zoneId]);
            logActivity('Delete Zone', "Deleted zone $zoneId", 'zones');
            $msg = 'ok:Zone deleted.';
        }
    }
}

$zones = $db->query('SELECT * FROM zones ORDER BY floor, name')->fetchAll();
logActivity('Viewed Zones', 'Opened zone management page', 'zones');
include __DIR__ . '/includes/layout.php';
?>

<?php if ($msg): [$type, $text] = explode(':', $msg, 2); ?>
<div style="background:<?= $type==='ok'?'var(--safe-bg)':'var(--crit-bg)' ?>;color:<?= $type==='ok'?'var(--safe)':'var(--crit)' ?>;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:500;border-left:3px solid <?= $type==='ok'?'var(--safe)':'var(--crit)' ?>;">
    <?= htmlspecialchars($text) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Library Zones</h1>
        <p>Real-time noise level monitoring per zone</p>
    </div>
    <?php if (canDo('add_zone')): ?>
    <button class="btn btn-primary" onclick="openModal('addZoneModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Zone
    </button>
    <?php endif; ?>
</div>

<!-- Zone Cards -->
<div class="zones-grid">
<?php foreach ($zones as $z):
    $status   = noiseStatus($z['level']);
    $pct      = min(($z['level'] / 90) * 100, 100);
    $bat      = (int)$z['battery'];
    $batClass = $bat > 60 ? 'high' : ($bat > 30 ? 'mid' : 'low');
?>
<div class="zone-card zone-<?= $status ?>" data-zone="<?= $z['id'] ?>" data-last-status="<?= $status ?>">
    <div class="zone-card-header">
        <div>
            <div class="zone-name"><?= htmlspecialchars($z['name']) ?></div>
            <div class="zone-floor"><?= htmlspecialchars($z['floor']) ?> · Sensor <?= htmlspecialchars($z['sensor']) ?></div>
        </div>
        <div class="zone-db-display">
            <div class="zone-db-num <?= $status ?>"><?= number_format($z['level'], 1) ?></div>
            <div class="zone-db-unit">dB (avg)</div>
        </div>
    </div>

    <div class="zone-progress">
        <div class="zone-prog-bar">
            <div class="zone-prog-fill <?= $status ?>" style="width:0" data-pct="<?= round($pct, 1) ?>"></div>
        </div>
        <div class="zone-thresholds">
            <span>0 dB</span>
            <span style="color:var(--warn)">⚠ <?= $z['warn_threshold'] ?>dB</span>
            <span style="color:var(--crit)">⛔ <?= $z['crit_threshold'] ?>dB</span>
            <span>90 dB</span>
        </div>
    </div>

    <div class="zone-meta">
        <div class="zone-meta-item">
            <span class="zone-meta-label">Status</span>
            <span class="zone-meta-val">
                <span class="badge badge-<?= $status==='safe'?'safe':($status==='warning'?'warn':'crit') ?>">
                    <?= noiseLabel($z['level']) ?>
                </span>
            </span>
        </div>
        <div class="zone-meta-item">
            <span class="zone-meta-label">Battery</span>
            <span class="zone-meta-val">
                <span class="battery <?= $batClass ?>">
                    <span class="battery-bar"><span class="battery-fill" style="width:<?= $bat ?>%"></span></span>
                    <?= $bat ?>%
                </span>
            </span>
        </div>
        <div class="zone-meta-item">
            <span class="zone-meta-label">Occupancy</span>
            <span class="zone-meta-val"><?= $z['occupied'] ?> / <?= $z['capacity'] ?></span>
        </div>
        <div class="zone-meta-item">
            <span class="zone-meta-label">Override</span>
            <span class="zone-meta-val">
                <?php if ($z['manual_override']): ?>
                <span class="badge badge-warn">Manual</span>
                <?php else: ?>
                <span class="badge badge-gray">Auto</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <?php if ($z['description']): ?>
    <div style="font-size:12px;color:var(--gray-400);margin-top:10px;font-style:italic;">
        <?= htmlspecialchars($z['description']) ?>
    </div>
    <?php endif; ?>

    <div class="zone-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
        <?php if (canDo('override_zone')): ?>
        <button class="btn btn-outline btn-sm"
                onclick="openOverrideModal('<?= $z['id'] ?>', '<?= addslashes($z['name']) ?>', <?= $z['level'] ?>)">
            Set Override
        </button>
        <?php endif; ?>
        <?php if ($z['manual_override'] && canDo('override_zone')): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action"  value="clear_override">
            <input type="hidden" name="zone_id" value="<?= $z['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Clear Override</button>
        </form>
        <?php endif; ?>
        <?php if (canDo('edit_zone')): ?>
        <button class="btn btn-outline btn-sm"
                onclick="openEditModal('<?= $z['id'] ?>','<?= addslashes($z['name']) ?>','<?= addslashes($z['floor']) ?>',<?= $z['warn_threshold'] ?>,<?= $z['crit_threshold'] ?>,<?= $z['capacity'] ?>,'<?= addslashes($z['description']??'') ?>',<?= $z['lat']??0 ?>,<?= $z['lng']??0 ?>)">
            Edit
        </button>
        <?php endif; ?>
        <?php if (canDo('delete_zone')): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this zone permanently?')">
            <input type="hidden" name="action"  value="delete_zone">
            <input type="hidden" name="zone_id" value="<?= $z['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── ADD ZONE MODAL ── -->
<div class="modal-overlay" id="addZoneModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title">Add New Zone</div>
            <button class="modal-close" onclick="closeModal('addZoneModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_zone">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Zone Name *</label>
                    <input class="form-control" type="text" name="zone_name" placeholder="e.g. Reading Area" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Floor *</label>
                    <input class="form-control" type="text" name="zone_floor" placeholder="e.g. 1F">
                </div>
                <div class="form-group">
                    <label class="form-label">Sensor ID *</label>
                    <input class="form-control" type="text" name="zone_sensor" placeholder="e.g. SNS-004">
                </div>
                <div class="form-group">
                    <label class="form-label">Capacity</label>
                    <input class="form-control" type="number" name="zone_capacity" min="1" value="30">
                </div>
                <div class="form-group">
                    <label class="form-label">Warn Threshold (dB)</label>
                    <input class="form-control" type="number" name="zone_warn" min="0" max="120" value="40">
                </div>
                <div class="form-group">
                    <label class="form-label">Critical Threshold (dB)</label>
                    <input class="form-control" type="number" name="zone_crit" min="0" max="120" value="60">
                </div>
                <div class="form-group">
                    <label class="form-label">Latitude (for map)</label>
                    <input class="form-control" type="number" name="zone_lat" step="0.0000001" placeholder="e.g. 8.359248">
                </div>
                <div class="form-group">
                    <label class="form-label">Longitude (for map)</label>
                    <input class="form-control" type="number" name="zone_lng" step="0.0000001" placeholder="e.g. 124.867853">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="zone_desc" rows="2" placeholder="Optional description..."></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('addZoneModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Zone</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDIT ZONE MODAL ── -->
<div class="modal-overlay" id="editZoneModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title">Edit Zone</div>
            <button class="modal-close" onclick="closeModal('editZoneModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="edit_zone">
            <input type="hidden" name="zone_id" id="editZoneId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="form-group">
                    <label class="form-label">Zone Name</label>
                    <input class="form-control" type="text" name="zone_name" id="editZoneName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Floor</label>
                    <input class="form-control" type="text" name="zone_floor" id="editZoneFloor">
                </div>
                <div class="form-group">
                    <label class="form-label">Warn Threshold (dB)</label>
                    <input class="form-control" type="number" name="zone_warn" id="editZoneWarn" min="0" max="120">
                </div>
                <div class="form-group">
                    <label class="form-label">Critical Threshold (dB)</label>
                    <input class="form-control" type="number" name="zone_crit" id="editZoneCrit" min="0" max="120">
                </div>
                <div class="form-group">
                    <label class="form-label">Capacity</label>
                    <input class="form-control" type="number" name="zone_capacity" id="editZoneCap" min="1">
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="zone_desc" id="editZoneDesc" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Latitude</label>
                    <input class="form-control" type="number" name="zone_lat" id="editZoneLat" step="0.0000001">
                </div>
                <div class="form-group">
                    <label class="form-label">Longitude</label>
                    <input class="form-control" type="number" name="zone_lng" id="editZoneLng" step="0.0000001">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editZoneModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ── OVERRIDE MODAL ── -->
<div class="modal-overlay" id="overrideModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Manual Noise Override</div>
            <button class="modal-close" onclick="closeModal('overrideModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="override">
            <input type="hidden" name="zone_id" id="overrideZoneId">
            <div class="form-group">
                <label class="form-label">Zone</label>
                <input class="form-control" id="overrideZoneName" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Override Level (dB)</label>
                <input class="form-control" type="number" name="override_level" id="overrideLevel"
                       min="0" max="120" step="0.1" required>
                <div style="font-size:11px;color:var(--gray-400);margin-top:5px;">
                    Warn: <?= NOISE_WARNING ?>dB · Critical: <?= NOISE_CRITICAL ?>dB
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('overrideModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply Override</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = '<script>
function openOverrideModal(id, name, level) {
    document.getElementById("overrideZoneId").value   = id;
    document.getElementById("overrideZoneName").value = name;
    document.getElementById("overrideLevel").value    = level;
    openModal("overrideModal");
}
function openEditModal(id, name, floor, warn, crit, cap, desc, lat, lng) {
    document.getElementById("editZoneId").value    = id;
    document.getElementById("editZoneName").value  = name;
    document.getElementById("editZoneFloor").value = floor;
    document.getElementById("editZoneWarn").value  = warn;
    document.getElementById("editZoneCrit").value  = crit;
    document.getElementById("editZoneCap").value   = cap;
    document.getElementById("editZoneDesc").value  = desc;
    document.getElementById("editZoneLat").value   = lat;
    document.getElementById("editZoneLng").value   = lng;
    openModal("editZoneModal");
}
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".zone-prog-fill[data-pct]").forEach(el => {
        const pct = parseFloat(el.dataset.pct) || 0;
        setTimeout(() => { el.style.width = pct + "%"; }, 100);
    });
});
</script>';
include __DIR__ . '/includes/layout_footer.php';
?>
