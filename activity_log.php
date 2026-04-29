<?php
// ============================================================
// activity_log.php — Activity Log (Admin only)
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireRole('Administrator');

$db        = getDB();
$pageTitle = 'Activity Log';
$user      = currentUser();

// Filters
$filterUser   = $_GET['user']   ?? '';
$filterAction = $_GET['action'] ?? '';
$filterPage   = $_GET['page_filter'] ?? '';
$filterDate   = $_GET['date']   ?? '';

$where  = ['1=1'];
$params = [];

if ($filterUser) {
    $where[]  = 'user_id = ?';
    $params[] = $filterUser;
}
if ($filterAction) {
    $where[]  = 'action = ?';
    $params[] = $filterAction;
}
if ($filterPage) {
    $where[]  = 'page = ?';
    $params[] = $filterPage;
}
if ($filterDate) {
    $where[]  = 'DATE(created_at) = ?';
    $params[] = $filterDate;
}

$whereSQL = implode(' AND ', $where);

// Pagination
$perPage = 25;
$page    = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE $whereSQL");
$total->execute($params);
$totalRows  = (int)$total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$logs = $db->prepare(
    "SELECT * FROM activity_logs WHERE $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset"
);
$logs->execute($params);
$logs = $logs->fetchAll();

// For filter dropdowns
$allUsers   = $db->query('SELECT DISTINCT user_id, user_name FROM activity_logs ORDER BY user_name')->fetchAll();
$allActions = $db->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll();
$allPages   = $db->query('SELECT DISTINCT page FROM activity_logs WHERE page IS NOT NULL ORDER BY page')->fetchAll();

// Clear logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    // FIX: log BEFORE truncating so the clear action itself is preserved as the first new entry
    logActivity('Cleared Activity Logs', 'Admin cleared all activity log history', 'activity_log');
    $db->exec('TRUNCATE TABLE activity_logs');
    // Re-insert the "cleared" record now that table is fresh
    logActivity('Cleared Activity Logs', 'Activity log was cleared — this is the first new entry', 'activity_log');
    header('Location: ' . BASE_URL . '/activity_log.php');
    exit;
}

logActivity('Viewed Activity Log', 'Opened activity log page', 'activity_log');

include __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <h1>Activity Log</h1>
        <p>All user actions across the system — <?= number_format($totalRows) ?> total entries</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="<?= BASE_URL ?>/api/export_logs.php<?= $filterUser||$filterAction||$filterDate ? '?user='.$filterUser.'&action='.$filterAction.'&date='.$filterDate : '' ?>"
           class="btn btn-outline btn-sm">
            ↓ Export CSV
        </a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Clear ALL activity logs? This cannot be undone.')">
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="btn btn-danger btn-sm">🗑 Clear Logs</button>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:18px;">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:150px;">
            <label class="form-label">User</label>
            <select class="form-control form-select" name="user">
                <option value="">All Users</option>
                <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['user_id'] ?>" <?= $filterUser===$u['user_id']?'selected':'' ?>>
                    <?= htmlspecialchars($u['user_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:150px;">
            <label class="form-label">Action</label>
            <select class="form-control form-select" name="action">
                <option value="">All Actions</option>
                <?php foreach ($allActions as $a): ?>
                <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filterAction===$a['action']?'selected':'' ?>>
                    <?= htmlspecialchars($a['action']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:150px;">
            <label class="form-label">Page</label>
            <select class="form-control form-select" name="page_filter">
                <option value="">All Pages</option>
                <?php foreach ($allPages as $pg): ?>
                <option value="<?= htmlspecialchars($pg['page']) ?>" <?= $filterPage===$pg['page']?'selected':'' ?>>
                    <?= htmlspecialchars($pg['page']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:140px;">
            <label class="form-label">Date</label>
            <input class="form-control" type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="<?= BASE_URL ?>/activity_log.php" class="btn btn-outline btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Log Table -->
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Detail</th>
                    <th>Page</th>
                    <th>IP Address</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="8" style="text-align:center;padding:40px;color:var(--gray-400);">
                    No activity logs found.
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $i => $log):
                $actionClass = match(true) {
                    str_contains($log['action'], 'Delete') || str_contains($log['action'], 'Clear') => 'badge-crit',
                    str_contains($log['action'], 'Add') || str_contains($log['action'], 'Create')   => 'badge-safe',
                    str_contains($log['action'], 'Override') || str_contains($log['action'], 'Edit') => 'badge-warn',
                    str_contains($log['action'], 'Login')   => 'badge-safe',
                    str_contains($log['action'], 'Logout')  => 'badge-gray',
                    default => 'badge-gray'
                };
            ?>
            <tr>
                <td style="font-size:11px;color:var(--gray-400);"><?= $offset + $i + 1 ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:28px;height:28px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0;">
                            <?= strtoupper(substr($log['user_name'], 0, 1)) ?>
                        </div>
                        <span style="font-weight:600;font-size:13px;"><?= htmlspecialchars($log['user_name']) ?></span>
                    </div>
                </td>
                <td>
                    <?php
                    $rc = match($log['user_role']) {
                        'Administrator'   => 'role-admin',
                        'Library Manager' => 'role-manager',
                        default           => 'role-staff'
                    };
                    ?>
                    <span class="role-badge <?= $rc ?>"><?= htmlspecialchars($log['user_role']) ?></span>
                </td>
                <td><span class="badge <?= $actionClass ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                <td style="font-size:12px;color:var(--gray-500);max-width:260px;">
                    <?= htmlspecialchars($log['detail'] ?: '—') ?>
                </td>
                <td>
                    <span style="font-size:11px;background:var(--gray-50);border:1px solid var(--gray-200);padding:2px 8px;border-radius:5px;color:var(--gray-500);font-family:monospace;">
                        <?= htmlspecialchars($log['page'] ?: '—') ?>
                    </span>
                </td>
                <td style="font-size:11.5px;color:var(--gray-400);font-family:monospace;">
                    <?= htmlspecialchars($log['ip'] ?: '—') ?>
                </td>
                <td style="font-size:12px;color:var(--gray-500);white-space:nowrap;">
                    <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                    <span style="font-size:11px;color:var(--gray-400);"><?= date('h:i:s A', strtotime($log['created_at'])) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-100);">
        <div style="font-size:12px;color:var(--gray-400);">
            Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= number_format($totalRows) ?>
        </div>
        <div style="display:flex;gap:6px;">
            <?php for ($pg = 1; $pg <= $totalPages; $pg++):
                $q = http_build_query(array_merge($_GET, ['p' => $pg]));
            ?>
            <a href="?<?= $q ?>"
               style="padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;
                      background:<?= $pg===$page?'var(--blue-600)':'var(--gray-50)' ?>;
                      color:<?= $pg===$page?'#fff':'var(--gray-500)' ?>;
                      border:1px solid <?= $pg===$page?'var(--blue-600)':'var(--gray-200)' ?>;">
                <?= $pg ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$extraScripts = '<script>
// Auto-refresh every 30s when on activity log page
setTimeout(() => location.reload(), 30000);
</script>';
include __DIR__ . '/includes/layout_footer.php';
?>
