<?php
// ============================================================
// alerts.php — Alert Management
// FIXED: broken PHP syntax in resolve block (logActivity was
//        incorrectly placed inside prepare() call)
//        Added logging for message actions too
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db   = getDB();
$pageTitle = 'Alerts';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action  = $_POST['action'];
    $alertId = trim($_POST['alert_id'] ?? '');

    // ── RESOLVE ALERT ─────────────────────────────────────────
    if ($action === 'resolve' && $alertId) {
        // FIX: logActivity is now correctly placed OUTSIDE prepare()
        // (original code had it dangling inside an open prepare() call)
        $db->prepare(
            'UPDATE alerts SET status = "resolved", type = "resolved",
             resolved_by = ?, resolved_at = ? WHERE id = ?'
        )->execute([$user['name'], date('M d, Y h:i A'), $alertId]);

        // Log the resolve action
        logActivity('Resolve Alert', "Resolved alert ID: $alertId by {$user['name']}", 'alerts');

        // Auto system message
        $msgId = generateId('MSG');
        $db->prepare(
            'INSERT INTO alert_messages (id, alert_id, from_name, from_role, message, msg_time, msg_date, is_system)
             VALUES (?,?,?,?,?,?,?,1)'
        )->execute([
            $msgId, $alertId,
            'System', 'System',
            'Alert resolved by ' . $user['name'] . ' (' . $user['role'] . ')',
            date('h:i A'), date('F d, Y')
        ]);
    }

    // ── SEND MESSAGE ──────────────────────────────────────────
    if ($action === 'message' && $alertId) {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            $msgId = generateId('MSG');
            $db->prepare(
                'INSERT INTO alert_messages (id, alert_id, from_name, from_role, message, msg_time, msg_date)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $msgId, $alertId,
                $user['name'], $user['role'],
                $message, date('h:i A'), date('F d, Y')
            ]);
            logActivity('Alert Message', "Sent message on alert $alertId: " . substr($message, 0, 80), 'alerts');
        }
    }

    header('Location: ' . BASE_URL . '/alerts.php' . ($alertId ? '?id=' . $alertId : ''));
    exit;
}

// Filter
$filterStatus = $_GET['status'] ?? 'all';
$where  = $filterStatus !== 'all' ? 'WHERE status = ?' : '';
$params = $filterStatus !== 'all' ? [$filterStatus] : [];

$stmt = $db->prepare("SELECT * FROM alerts $where ORDER BY created_at DESC LIMIT 80");
$stmt->execute($params);
$alerts = $stmt->fetchAll();

// Selected alert for detail
$selectedId    = $_GET['id'] ?? null;
$selectedAlert = null;
$alertMessages = [];

if ($selectedId) {
    $s = $db->prepare('SELECT * FROM alerts WHERE id = ?');
    $s->execute([$selectedId]);
    $selectedAlert = $s->fetch();

    $m = $db->prepare('SELECT * FROM alert_messages WHERE alert_id = ? ORDER BY created_at ASC');
    $m->execute([$selectedId]);
    $alertMessages = $m->fetchAll();
}

logActivity('Viewed Alerts', 'Opened alert logs page', 'alerts');
include __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <h1>Alerts</h1>
        <p>Noise threshold violations and notifications</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="?status=all"      class="btn <?= $filterStatus==='all'      ?'btn-primary':'btn-outline' ?> btn-sm">All</a>
        <a href="?status=active"   class="btn <?= $filterStatus==='active'   ?'btn-primary':'btn-outline' ?> btn-sm">Active</a>
        <a href="?status=resolved" class="btn <?= $filterStatus==='resolved' ?'btn-primary':'btn-outline' ?> btn-sm">Resolved</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:<?= $selectedAlert ? '1fr 380px' : '1fr' ?>;gap:20px;">

<!-- Alert List -->
<div class="card">
    <?php if (empty($alerts)): ?>
    <div class="empty-state">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <h3>No alerts found</h3>
        <p>No alerts match the current filter.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zone</th>
                    <th>Level</th>
                    <th>Type</th>
                    <th>Date / Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alerts as $a): ?>
            <tr style="<?= $selectedId === $a['id'] ? 'background:var(--blue-50)' : '' ?>">
                <td style="font-weight:600;"><?= htmlspecialchars($a['zone_name']) ?></td>
                <td>
                    <span style="font-family:var(--font-head);font-weight:700;color:<?= $a['type']==='critical'?'var(--crit)':($a['type']==='warning'?'var(--warn)':'var(--safe)') ?>">
                        <?= number_format($a['level'], 1) ?> dB
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= $a['type']==='critical'?'crit':($a['type']==='warning'?'warn':'safe') ?>">
                        <?= ucfirst($a['type']) ?>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--gray-500);">
                    <?= htmlspecialchars($a['alert_date']) ?><br>
                    <?= htmlspecialchars($a['alert_time']) ?>
                </td>
                <td>
                    <span class="badge <?= $a['status']==='active'?'badge-crit':'badge-safe' ?>">
                        <?= ucfirst($a['status']) ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="?id=<?= $a['id'] ?>&status=<?= $filterStatus ?>"
                           class="btn btn-outline btn-sm">Detail</a>

                        <?php if ($a['status'] === 'active'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action"   value="resolve">
                            <input type="hidden" name="alert_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-sm"
                                    style="background:var(--safe);color:#fff;"
                                    onclick="return confirm('Mark this alert as resolved?')">
                                Resolve
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Alert Detail Panel -->
<?php if ($selectedAlert): ?>
<div>
    <div class="card" style="margin-bottom:16px;">
        <div class="modal-header" style="margin-bottom:14px;">
            <div class="modal-title"><?= htmlspecialchars($selectedAlert['zone_name']) ?></div>
            <a href="?status=<?= $filterStatus ?>" class="modal-close">✕</a>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
            <div>
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-400);font-weight:600;">Level</div>
                <div style="font-family:var(--font-head);font-size:22px;font-weight:700;color:<?= $selectedAlert['type']==='critical'?'var(--crit)':'var(--warn)' ?>;">
                    <?= number_format($selectedAlert['level'], 1) ?> dB
                </div>
            </div>
            <div>
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-400);font-weight:600;">Type</div>
                <span class="badge badge-<?= $selectedAlert['type']==='critical'?'crit':'warn' ?>" style="margin-top:4px;">
                    <?= ucfirst($selectedAlert['type']) ?>
                </span>
            </div>
        </div>

        <?php if ($selectedAlert['msg']): ?>
        <div style="background:var(--gray-50);border-radius:8px;padding:10px 12px;font-size:12.5px;color:var(--gray-600);margin-bottom:12px;">
            <?= htmlspecialchars($selectedAlert['msg']) ?>
        </div>
        <?php endif; ?>

        <?php if ($selectedAlert['resolved_by']): ?>
        <div style="font-size:12px;color:var(--safe);">
            ✓ Resolved by <?= htmlspecialchars($selectedAlert['resolved_by']) ?><br>
            <?= htmlspecialchars($selectedAlert['resolved_at']) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Messages Thread -->
    <div class="card">
        <div class="card-title">Staff Messages</div>
        <div style="max-height:280px;overflow-y:auto;margin-bottom:14px;">
            <?php if (empty($alertMessages)): ?>
            <div style="text-align:center;padding:24px;color:var(--gray-400);font-size:13px;">No messages yet.</div>
            <?php else: ?>
            <?php foreach ($alertMessages as $m): ?>
            <div style="margin-bottom:12px;<?= $m['is_system'] ? 'opacity:.6' : '' ?>">
                <div style="font-size:11px;font-weight:600;color:var(--blue-700);">
                    <?= htmlspecialchars($m['from_name']) ?>
                    <span style="font-weight:400;color:var(--gray-400);">(<?= htmlspecialchars($m['from_role']) ?>)</span>
                    <span style="color:var(--gray-300);margin:0 4px;">·</span>
                    <?= htmlspecialchars($m['msg_time']) ?>
                </div>
                <div style="font-size:13px;color:var(--gray-700);margin-top:3px;<?= $m['is_system'] ? 'font-style:italic' : '' ?>">
                    <?= htmlspecialchars($m['message']) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($selectedAlert['status'] === 'active'): ?>
        <form method="POST">
            <input type="hidden" name="action"   value="message">
            <input type="hidden" name="alert_id" value="<?= $selectedAlert['id'] ?>">
            <textarea class="form-control" name="message" rows="2"
                      placeholder="Add a note for staff…" style="resize:none;margin-bottom:8px;"
                      required></textarea>
            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;">
                Send Message
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
