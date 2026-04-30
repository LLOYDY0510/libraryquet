<?php
// ============================================================
// reports.php — Reports (Manager + Admin)
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireRole('Administrator', 'Library Manager');

$db   = getDB();
$pageTitle = 'Reports';
$user = currentUser();

// Generate report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_report'])) {
    $type  = trim($_POST['report_type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($type) {
        $id = generateId('RPT');
        $db->prepare(
            'INSERT INTO reports (id, type, generated_by, role, report_date, report_time, notes)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $id, $type,
            $user['name'], $user['role'],
            date('F d, Y'), date('h:i A'),
            $notes
        ]);
        logActivity('Generated Report', "Generated: $type", 'reports');
        header('Location: ' . BASE_URL . '/reports.php?generated=1');
        exit;
    }
}

$reports = $db->query('SELECT * FROM reports ORDER BY created_at DESC LIMIT 50')->fetchAll();

// Summary stats for current report view
$zones  = $db->query('SELECT * FROM zones')->fetchAll();
$alerts = $db->query('SELECT * FROM alerts')->fetchAll();
$activeAlerts   = array_filter($alerts, fn($a) => $a['status'] === 'active');
$resolvedAlerts = array_filter($alerts, fn($a) => $a['status'] === 'resolved');
$critAlerts     = array_filter($alerts, fn($a) => $a['type'] === 'critical');

logActivity('Viewed Reports', 'Opened reports page', 'reports');
include __DIR__ . '/includes/layout.php';
?>

<?php if (isset($_GET['generated'])): ?>
<div style="background:var(--safe-bg);color:var(--safe);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:500;">
    ✓ Report generated successfully.
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1>Reports</h1>
        <p>Generate and review noise monitoring reports</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('genModal')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Generate Report
    </button>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            </svg>
        </div>
        <div class="stat-value"><?= count($reports) ?></div>
        <div class="stat-label">Total Reports</div>
    </div>
    <div class="stat-card safe-card">
        <div class="stat-icon safe">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="stat-value" style="color:var(--safe)"><?= count($resolvedAlerts) ?></div>
        <div class="stat-label">Resolved Alerts</div>
    </div>
    <div class="stat-card crit-card">
        <div class="stat-icon crit">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
            </svg>
        </div>
        <div class="stat-value" style="color:var(--crit)"><?= count($critAlerts) ?></div>
        <div class="stat-label">Critical Incidents</div>
    </div>
    <div class="stat-card warn-card">
        <div class="stat-icon warn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
        </div>
        <div class="stat-value" style="color:var(--warn)"><?= count($zones) ?></div>
        <div class="stat-label">Zones Monitored</div>
    </div>
</div>

<!-- Zone Summary Table -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-title">Zone Noise Summary</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zone</th>
                    <th>Floor</th>
                    <th>Current Level</th>
                    <th>Status</th>
                    <th>Warn Threshold</th>
                    <th>Crit Threshold</th>
                    <th>Occupancy</th>
                    <th>Sensor</th>
                    <th>Battery</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($zones as $z):
                $status = noiseStatus($z['level']);
            ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($z['name']) ?></td>
                <td><?= htmlspecialchars($z['floor']) ?></td>
                <td>
                    <span style="font-family:var(--font-head);font-weight:700;color:<?= $status==='critical'?'var(--crit)':($status==='warning'?'var(--warn)':'var(--safe)') ?>;">
                        <?= number_format($z['level'], 1) ?> dB
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= $status==='safe'?'safe':($status==='warning'?'warn':'crit') ?>">
                        <?= noiseLabel($z['level']) ?>
                    </span>
                </td>
                <td><?= $z['warn_threshold'] ?> dB</td>
                <td><?= $z['crit_threshold'] ?> dB</td>
                <td><?= $z['occupied'] ?>/<?= $z['capacity'] ?></td>
                <td><span class="sensor-dot"><?= htmlspecialchars($z['sensor']) ?></span></td>
                <td><?= $z['battery'] ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reports Log -->
<div class="card">
    <div class="card-title">Generated Reports</div>
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
            <?php foreach ($reports as $r): ?>
            <tr>
                <td style="font-family:var(--font-head);font-size:12px;color:var(--gray-400);"><?= $r['id'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($r['type']) ?></td>
                <td><?= htmlspecialchars($r['generated_by']) ?></td>
                <td><span class="badge badge-blue"><?= htmlspecialchars($r['role']) ?></span></td>
                <td style="font-size:12px;color:var(--gray-500);"><?= htmlspecialchars($r['report_date']) ?></td>
                <td style="font-size:12px;color:var(--gray-500);"><?= htmlspecialchars($r['report_time']) ?></td>
                <td style="font-size:12px;color:var(--gray-500);"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
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
                <textarea class="form-control" name="notes" rows="3"
                          placeholder="Any notes or observations…" style="resize:none;"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('genModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
