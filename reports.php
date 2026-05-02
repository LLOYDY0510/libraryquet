<?php
// ============================================================
// reports.php — Reports (Manager + Admin)
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireRole('Administrator', 'Library Manager');

$db        = getDB();
$pageTitle = 'Reports';
$pageSubtitle = 'Generate and review noise monitoring reports';
$user      = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_report'])) {
    $type  = trim($_POST['report_type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($type) {
        $id = generateId('RPT');
        $db->prepare(
            'INSERT INTO reports (id, type, generated_by, role, report_date, report_time, notes)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$id, $type, $user['name'], $user['role'], date('F d, Y'), date('h:i A'), $notes]);
        logActivity('Generated Report', "Generated: $type", 'reports');
        header('Location: ' . BASE_URL . '/reports.php?generated=1');
        exit;
    }
}

$reports        = $db->query('SELECT * FROM reports ORDER BY created_at DESC LIMIT 50')->fetchAll();
$zones          = $db->query('SELECT * FROM zones')->fetchAll();
$alerts         = $db->query('SELECT * FROM alerts')->fetchAll();
$activeAlerts   = array_filter($alerts, fn($a) => $a['status'] === 'active');
$resolvedAlerts = array_filter($alerts, fn($a) => $a['status'] === 'resolved');
$critAlerts     = array_filter($alerts, fn($a) => $a['type'] === 'critical');

logActivity('Viewed Reports', 'Opened reports page', 'reports');
include __DIR__ . '/includes/layout.php';
?>

<?php if (isset($_GET['generated'])): ?>
<div class="page-flash ok">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    Report generated successfully.
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Reports</h1>
        <p>Generate and review noise monitoring reports</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('genModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Generate Report
    </button>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="stat-value"><?= count($reports) ?></div>
        <div class="stat-label">Total Reports</div>
    </div>
    <div class="stat-card safe-card">
        <div class="stat-icon safe">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-value safe"><?= count($resolvedAlerts) ?></div>
        <div class="stat-label">Resolved Alerts</div>
    </div>
    <div class="stat-card crit-card">
        <div class="stat-icon crit">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="stat-value crit"><?= count($critAlerts) ?></div>
        <div class="stat-label">Critical Incidents</div>
    </div>
    <div class="stat-card warn-card">
        <div class="stat-icon warn">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div class="stat-value warn"><?= count($zones) ?></div>
        <div class="stat-label">Zones Monitored</div>
    </div>
</div>

<!-- Zone Summary -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Zone Noise Summary</span>
        <span class="badge badge-gray"><?= count($zones) ?> zones</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zone</th>
                    <th>Floor</th>
                    <th>Current Level</th>
                    <th>Status</th>
                    <th>Warn</th>
                    <th>Critical</th>
                    <th>Occupancy</th>
                    <th>Sensor</th>
                    <th>Battery</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($zones as $z):
                $status  = noiseStatus($z['level']);
                $badgeCls = $status === 'safe' ? 'safe' : ($status === 'warning' ? 'warn' : 'crit');
                $dbCls    = $status === 'safe' ? 'safe' : ($status === 'warning' ? 'warn' : 'crit');
                $bat      = (int)$z['battery'];
                $batClass = $bat > 60 ? 'high' : ($bat > 30 ? 'mid' : 'low');
            ?>
            <tr>
                <td class="td-bold"><?= htmlspecialchars($z['name']) ?></td>
                <td><?= htmlspecialchars($z['floor']) ?></td>
                <td>
                    <span class="db-reading db-reading-<?= $dbCls ?>">
                        <?= number_format($z['level'], 1) ?> dB
                    </span>
                </td>
                <td><span class="badge badge-<?= $badgeCls ?>"><?= noiseLabel($z['level']) ?></span></td>
                <td class="td-threshold"><?= $z['warn_threshold'] ?> dB</td>
                <td class="td-threshold"><?= $z['crit_threshold'] ?> dB</td>
                <td><?= $z['occupied'] ?>/<?= $z['capacity'] ?></td>
                <td>
                    <span class="sensor-status"><?= htmlspecialchars($z['sensor']) ?></span>
                </td>
                <td>
                    <span class="battery <?= $batClass ?>">
                        <span class="battery-bar"><span class="battery-fill" style="width:<?= $bat ?>%"></span></span>
                        <?= $bat ?>%
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reports Log -->
<div class="card card-stack">
    <div class="card-header">
        <span class="card-title">Generated Reports</span>
        <span class="badge badge-blue"><?= count($reports) ?> total</span>
    </div>
    <?php if (empty($reports)): ?>
    <div class="empty-state">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
        </svg>
        <h3>No reports yet</h3>
        <p>Generate a report using the button above.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Type</th>
                    <th>Generated By</th>
                    <th>Role</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $r):
                $rRoleCls = match($r['role']) {
                    'Administrator'   => 'role-admin',
                    'Library Manager' => 'role-manager',
                    default           => 'role-staff'
                };
            ?>
            <tr>
                <td class="td-id"><?= htmlspecialchars($r['id']) ?></td>
                <td class="td-bold"><?= htmlspecialchars($r['type']) ?></td>
                <td><?= htmlspecialchars($r['generated_by']) ?></td>
                <td><span class="role-badge <?= $rRoleCls ?>"><?= htmlspecialchars($r['role']) ?></span></td>
                <td class="td-meta"><?= htmlspecialchars($r['report_date']) ?></td>
                <td class="td-meta"><?= htmlspecialchars($r['report_time']) ?></td>
                <td class="td-notes"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Generate Modal -->
<div class="modal-overlay" id="genModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Generate Report</div>
            <button class="modal-close" onclick="closeModal('genModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="gen_report" value="1">
            <div class="form-group">
                <label class="form-label">Report Type</label>
                <select class="form-control form-select" name="report_type" required>
                    <option value="">Select type…</option>
                    <option>Daily Noise Summary</option>
                    <option>Weekly Zone Report</option>
                    <option>Monthly Activity Report</option>
                    <option>Alert Incident Report</option>
                    <option>Sensor Health Report</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Notes (optional)</label>
                <textarea class="form-control textarea-noresize" name="notes" rows="3"
                          placeholder="Any notes or observations…"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('genModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
