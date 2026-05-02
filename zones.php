<?php
// ============================================================
// zones.php — Zone Management
// PHP logic is unchanged. HTML refactored:
//   - Inline styles removed → CSS classes in components.css
//   - Inline <script> removed → zones.js
//   - native confirm() replaced with #deleteConfirmModal
//   - flash $msg uses .page-flash class
//   - modal forms use .modal-form-grid + .modal-footer
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Zones';
$pageSubtitle = 'Real-time noise level monitoring per zone';
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
            $name   = trim($_POST['zone_name']     ?? '');
            $floor  = trim($_POST['zone_floor']    ?? '');
            $sensor = trim($_POST['zone_sensor']   ?? '');
            $cap    = (int)($_POST['zone_capacity']  ?? 0);
            $warn   = (int)($_POST['zone_warn']      ?? 40);
            $crit   = (int)($_POST['zone_crit']      ?? 60);
            $desc   = trim($_POST['zone_desc']     ?? '');
            $lat    = (float)($_POST['zone_lat']   ?? 0);
            $lng    = (float)($_POST['zone_lng']   ?? 0);

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
            $name  = trim($_POST['zone_name']      ?? '');
            $floor = trim($_POST['zone_floor']     ?? '');
            $warn  = (int)($_POST['zone_warn']     ?? 40);
            $crit  = (int)($_POST['zone_crit']     ?? 60);
            $cap   = (int)($_POST['zone_capacity'] ?? 0);
            $desc  = trim($_POST['zone_desc']      ?? '');
            $lat   = (float)($_POST['zone_lat']    ?? 0);
            $lng   = (float)($_POST['zone_lng']    ?? 0);

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

$extraScripts = '<script src="' . BASE_URL . '/js/zones.js"></script>';
include __DIR__ . '/includes/layout.php';
?>

<?php if ($msg): [$type, $text] = explode(':', $msg, 2); ?>
<div class="page-flash <?= $type ?>">
    <?php if ($type === 'ok'): ?>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <?php else: ?>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php endif; ?>
    <?= htmlspecialchars($text) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Library Zones</h1>
        <p>Real-time noise level monitoring per zone</p>
    </div>
    <?php if (canDo('add_zone')): ?>
    <button class="btn btn-primary" onclick="openModal('addZoneModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Zone
    </button>
    <?php endif; ?>
</div>

<!-- ── Zone Cards ──────────────────────────────────────────── -->
<div class="zones-grid">
<?php foreach ($zones as $z):
    $status   = noiseStatus($z['level']);
    $pct      = min(($z['level'] / 90) * 100, 100);
    $bat      = (int)$z['battery'];
    $batClass = $bat > 60 ? 'high' : ($bat > 30 ? 'mid' : 'low');
    $badgeCls = $status === 'safe' ? 'safe' : ($status === 'warning' ? 'warn' : 'crit');
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
            <span class="threshold-warn">⚠ <?= $z['warn_threshold'] ?>dB</span>
            <span class="threshold-crit">⛔ <?= $z['crit_threshold'] ?>dB</span>
            <span>90 dB</span>
        </div>
    </div>

    <div class="zone-meta-grid">
        <div class="zone-meta-item">
            <span class="zone-meta-label">Status</span>
            <span class="zone-meta-val">
                <span class="badge badge-<?= $badgeCls ?>"><?= noiseLabel($z['level']) ?></span>
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
    <div class="zone-description"><?= htmlspecialchars($z['description']) ?></div>
    <?php endif; ?>

    <div class="zone-actions">
        <?php if (canDo('override_zone')): ?>
        <button class="btn btn-outline btn-sm"
                onclick="openOverrideModal('<?= $z['id'] ?>', '<?= addslashes(htmlspecialchars($z['name'])) ?>', <?= $z['level'] ?>)">
            Set Override
        </button>
        <?php endif; ?>

        <?php if ($z['manual_override'] && canDo('override_zone')): ?>
        <form method="POST" class="form-inline">
            <input type="hidden" name="action"  value="clear_override">
            <input type="hidden" name="zone_id" value="<?= $z['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Clear Override</button>
        </form>
        <?php endif; ?>

        <?php if (canDo('edit_zone')): ?>
        <button class="btn btn-outline btn-sm"
                onclick="openEditModal(
                    '<?= $z['id'] ?>',
                    '<?= addslashes(htmlspecialchars($z['name'])) ?>',
                    '<?= addslashes(htmlspecialchars($z['floor'])) ?>',
                    <?= $z['warn_threshold'] ?>,
                    <?= $z['crit_threshold'] ?>,
                    <?= $z['capacity'] ?>,
                    '<?= addslashes(htmlspecialchars($z['description'] ?? '')) ?>',
                    <?= $z['lat'] ?? 0 ?>,
                    <?= $z['lng'] ?? 0 ?>
                )">
            Edit
        </button>
        <?php endif; ?>

        <?php if (canDo('delete_zone')): ?>
        <!-- onclick calls confirmDelete() in zones.js — no native confirm() popup -->
        <button class="btn btn-danger btn-sm"
                onclick="confirmDelete('<?= $z['id'] ?>', '<?= addslashes(htmlspecialchars($z['name'])) ?>')">
            Delete
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php if (empty($zones)): ?>
<div class="empty-state">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        <polyline points="9 22 9 12 15 12 15 22"/>
    </svg>
    <h3>No zones yet</h3>
    <p>Add your first zone to start monitoring noise levels.</p>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     MODALS
     ════════════════════════════════════════════════════════════ -->

<!-- ADD ZONE -->
<div class="modal-overlay" id="addZoneModal">
    <div class="modal modal-md">
        <div class="modal-header">
            <div class="modal-title">Add New Zone</div>
            <button class="modal-close" onclick="closeModal('addZoneModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_zone">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label class="form-label">Zone Name *</label>
                    <input class="form-control" type="text" name="zone_name" placeholder="e.g. Reading Area" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Floor *</label>
                    <input class="form-control" type="text" name="zone_floor" placeholder="e.g. 1F" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Sensor ID *</label>
                    <input class="form-control" type="text" name="zone_sensor" placeholder="e.g. SNS-004" required>
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
                <div class="form-group full">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="zone_desc" rows="2" placeholder="Optional description…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addZoneModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Zone</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT ZONE -->
<div class="modal-overlay" id="editZoneModal">
    <div class="modal modal-md">
        <div class="modal-header">
            <div class="modal-title">Edit Zone</div>
            <button class="modal-close" onclick="closeModal('editZoneModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="edit_zone">
            <input type="hidden" name="zone_id" id="editZoneId">
            <div class="modal-form-grid">
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
                <div class="form-group">
                    <!-- intentional spacer to keep grid balanced -->
                </div>
                <div class="form-group">
                    <label class="form-label">Latitude</label>
                    <input class="form-control" type="number" name="zone_lat" id="editZoneLat" step="0.0000001">
                </div>
                <div class="form-group">
                    <label class="form-label">Longitude</label>
                    <input class="form-control" type="number" name="zone_lng" id="editZoneLng" step="0.0000001">
                </div>
                <div class="form-group full">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="zone_desc" id="editZoneDesc" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editZoneModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- OVERRIDE -->
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
                <div class="form-hint">Warn: <?= NOISE_WARNING ?>dB · Critical: <?= NOISE_CRITICAL ?>dB</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('overrideModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply Override</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRMATION -->
<!-- Single shared form — zone_id is populated by confirmDelete() in zones.js -->
<div class="modal-overlay" id="deleteConfirmModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title">Delete Zone</div>
            <button class="modal-close" onclick="closeModal('deleteConfirmModal')">✕</button>
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
            <div class="delete-confirm-title">Delete this zone?</div>
            <div class="delete-confirm-sub">
                <span class="delete-confirm-zone" id="deleteZoneName"></span> will be permanently removed.
                This action cannot be undone.
            </div>
        </div>
        <form method="POST" id="deleteZoneForm">
            <input type="hidden" name="action"  value="delete_zone">
            <input type="hidden" name="zone_id" value="">
        </form>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('deleteConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="submitDelete()">Yes, Delete</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
