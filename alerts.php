<?php
// ============================================================
// alerts.php — Alert Management
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Alerts';
$pageSubtitle = 'Noise threshold violations and notifications';
$user      = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action  = $_POST['action'];
    $alertId = trim($_POST['alert_id'] ?? '');

    if ($action === 'resolve' && $alertId) {
        $db->prepare(
            'UPDATE alerts SET status = "resolved", type = "resolved",
             resolved_by = ?, resolved_at = ? WHERE id = ?'
        )->execute([$user['name'], date('M d, Y h:i A'), $alertId]);
        logActivity('Resolve Alert', "Resolved alert ID: $alertId by {$user['name']}", 'alerts');
        $msgId = generateId('MSG');
        $db->prepare(
            'INSERT INTO alert_messages (id, alert_id, from_name, from_role, message, msg_time, msg_date, is_system)
             VALUES (?,?,?,?,?,?,?,1)'
        )->execute([
            $msgId, $alertId, 'System', 'System',
            'Alert resolved by ' . $user['name'] . ' (' . $user['role'] . ')',
            date('h:i A'), date('F d, Y')
        ]);
    }

    if ($action === 'message' && $alertId) {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            $msgId = generateId('MSG');
            $db->prepare(
                'INSERT INTO alert_messages (id, alert_id, from_name, from_role, message, msg_time, msg_date)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $msgId, $alertId, $user['name'], $user['role'],
                $message, date('h:i A'), date('F d, Y')
            ]);
            logActivity('Alert Message', "Sent message on alert $alertId: " . substr($message, 0, 80), 'alerts');
        }
    }

    header('Location: ' . BASE_URL . '/alerts.php' . ($alertId ? '?id=' . $alertId : ''));
    exit;
}

$filterStatus = $_GET['status'] ?? 'all';
$where  = $filterStatus !== 'all' ? 'WHERE status = ?' : '';
$params = $filterStatus !== 'all' ? [$filterStatus] : [];

$stmt = $db->prepare("SELECT * FROM alerts $where ORDER BY created_at DESC LIMIT 80");
$stmt->execute($params);
$alerts = $stmt->fetchAll();

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
$extraScripts = '<script src="' . BASE_URL . '/js/alerts.js"></script>';
include __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Alerts</h1>
        <p>Noise threshold violations and notifications</p>
    </div>
    <div class="filter-tabs">
        <a href="?status=all"      class="btn <?= $filterStatus==='all'      ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
        <a href="?status=active"   class="btn <?= $filterStatus==='active'   ? 'btn-primary' : 'btn-outline' ?> btn-sm">Active</a>
        <a href="?status=resolved" class="btn <?= $filterStatus==='resolved' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Resolved</a>
    </div>
</div>

<div class="alerts-layout <?= $selectedAlert ? 'has-detail' : '' ?>">

    <!-- ── Alert List ──────────────────────────────────────── -->
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
                <?php foreach ($alerts as $a):
                    $typeCls   = $a['type'] === 'critical' ? 'crit' : ($a['type'] === 'warning' ? 'warn' : 'safe');
                    $statusCls = $a['status'] === 'active' ? 'badge-crit' : 'badge-safe';
                    $isSelected = $selectedId === $a['id'];
                ?>
                <tr class="<?= $isSelected ? 'row-selected' : '' ?>">
                    <td class="td-bold"><?= htmlspecialchars($a['zone_name']) ?></td>
                    <td>
                        <span class="db-reading db-reading-<?= $typeCls ?>">
                            <?= number_format($a['level'], 1) ?> dB
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $typeCls ?>"><?= ucfirst($a['type']) ?></span>
                    </td>
                    <td class="td-meta">
                        <?= htmlspecialchars($a['alert_date']) ?><br>
                        <span class="td-sub"><?= htmlspecialchars($a['alert_time']) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $statusCls ?>"><?= ucfirst($a['status']) ?></span>
                    </td>
                    <td>
                        <div class="td-actions">
                            <a href="?id=<?= $a['id'] ?>&status=<?= $filterStatus ?>"
                               class="btn btn-outline btn-sm">Detail</a>

                            <?php if ($a['status'] === 'active'): ?>
                            <button class="btn btn-safe btn-sm"
                                    onclick="confirmResolve('<?= $a['id'] ?>', '<?= addslashes(htmlspecialchars($a['zone_name'])) ?>')">
                                Resolve
                            </button>
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

    <!-- ── Alert Detail Panel ──────────────────────────────── -->
    <?php if ($selectedAlert):
        $detailType = $selectedAlert['type'];
        $detailCls  = $detailType === 'critical' ? 'crit' : 'warn';
    ?>
    <div class="alert-detail-col">

        <!-- Info card -->
        <div class="card alert-detail-card">
            <div class="alert-detail-header">
                <div class="alert-detail-zone"><?= htmlspecialchars($selectedAlert['zone_name']) ?></div>
                <a href="?status=<?= $filterStatus ?>" class="modal-close" title="Close detail">✕</a>
            </div>

            <div class="alert-detail-stats">
                <div class="alert-detail-stat">
                    <div class="alert-detail-stat-label">Level</div>
                    <div class="db-reading db-reading-<?= $detailCls ?> db-reading-lg">
                        <?= number_format($selectedAlert['level'], 1) ?> dB
                    </div>
                </div>
                <div class="alert-detail-stat">
                    <div class="alert-detail-stat-label">Type</div>
                    <span class="badge badge-<?= $detailCls ?> badge-nudge">
                        <?= ucfirst($detailType) ?>
                    </span>
                </div>
                <div class="alert-detail-stat">
                    <div class="alert-detail-stat-label">Status</div>
                    <span class="badge badge-nudge <?= $selectedAlert['status']==='active' ? 'badge-crit' : 'badge-safe' ?>">
                        <?= ucfirst($selectedAlert['status']) ?>
                    </span>
                </div>
                <div class="alert-detail-stat">
                    <div class="alert-detail-stat-label">Triggered</div>
                    <div class="alert-detail-stat-val"><?= htmlspecialchars($selectedAlert['alert_date']) ?></div>
                    <div class="alert-detail-stat-sub"><?= htmlspecialchars($selectedAlert['alert_time']) ?></div>
                </div>
            </div>

            <?php if ($selectedAlert['msg']): ?>
            <div class="alert-detail-msg"><?= htmlspecialchars($selectedAlert['msg']) ?></div>
            <?php endif; ?>

            <?php if ($selectedAlert['resolved_by']): ?>
            <div class="alert-resolved-note">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Resolved by <strong><?= htmlspecialchars($selectedAlert['resolved_by']) ?></strong>
                &nbsp;·&nbsp; <?= htmlspecialchars($selectedAlert['resolved_at']) ?>
            </div>
            <?php endif; ?>

            <?php if ($selectedAlert['status'] === 'active'): ?>
            <form method="POST" class="alert-resolve-form" id="resolveForm_<?= $selectedAlert['id'] ?>">
                <input type="hidden" name="action"   value="resolve">
                <input type="hidden" name="alert_id" value="<?= $selectedAlert['id'] ?>">
            </form>
            <?php endif; ?>
        </div>

        <!-- Messages thread -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Staff Messages</span>
                <span class="badge badge-gray"><?= count($alertMessages) ?></span>
            </div>

            <div class="msg-thread">
                <?php if (empty($alertMessages)): ?>
                <div class="msg-empty">No messages yet.</div>
                <?php else: ?>
                <?php foreach ($alertMessages as $m): ?>
                <div class="msg-item <?= $m['is_system'] ? 'msg-system' : '' ?>">
                    <div class="msg-meta">
                        <span class="msg-from"><?= htmlspecialchars($m['from_name']) ?></span>
                        <span class="msg-role">(<?= htmlspecialchars($m['from_role']) ?>)</span>
                        <span class="msg-dot">·</span>
                        <span class="msg-time"><?= htmlspecialchars($m['msg_time']) ?></span>
                    </div>
                    <div class="msg-body"><?= htmlspecialchars($m['message']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($selectedAlert['status'] === 'active'): ?>
            <form method="POST" class="msg-compose">
                <input type="hidden" name="action"   value="message">
                <input type="hidden" name="alert_id" value="<?= $selectedAlert['id'] ?>">
                <textarea class="form-control msg-textarea" name="message" rows="2"
                          placeholder="Add a note for staff…" required></textarea>
                <button type="submit" class="btn btn-primary btn-sm msg-send-btn">Send Message</button>
            </form>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>

</div>

<!-- Resolve confirm modal -->
<div class="modal-overlay" id="resolveConfirmModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title">Resolve Alert</div>
            <button class="modal-close" onclick="closeModal('resolveConfirmModal')">✕</button>
        </div>
        <div class="delete-confirm-body">
            <div class="delete-confirm-icon safe">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="delete-confirm-title">Mark as Resolved?</div>
            <div class="delete-confirm-sub">
                Alert for <span class="delete-confirm-zone" id="resolveZoneName"></span> will be
                marked resolved and a system message will be logged.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('resolveConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-safe" id="resolveConfirmBtn">Yes, Resolve</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
