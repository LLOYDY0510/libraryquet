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
$pageSubtitle = 'All user actions across the system';
$user      = currentUser();

$filterUser   = $_GET['user']        ?? '';
$filterAction = $_GET['action']      ?? '';
$filterPage   = $_GET['page_filter'] ?? '';
$filterDate   = $_GET['date']        ?? '';

$where  = ['1=1'];
$params = [];
if ($filterUser)   { $where[] = 'user_id = ?';        $params[] = $filterUser; }
if ($filterAction) { $where[] = 'action = ?';         $params[] = $filterAction; }
if ($filterPage)   { $where[] = 'page = ?';           $params[] = $filterPage; }
if ($filterDate)   { $where[] = 'DATE(created_at) = ?'; $params[] = $filterDate; }
$whereSQL = implode(' AND ', $where);

$perPage    = 25;
$page       = max(1, (int)($_GET['p'] ?? 1));
$offset     = ($page - 1) * $perPage;

$total = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE $whereSQL");
$total->execute($params);
$totalRows  = (int)$total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$logsStmt = $db->prepare("SELECT * FROM activity_logs WHERE $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

$allUsers   = $db->query('SELECT DISTINCT user_id, user_name FROM activity_logs ORDER BY user_name')->fetchAll();
$allActions = $db->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll();
$allPages   = $db->query('SELECT DISTINCT page FROM activity_logs WHERE page IS NOT NULL ORDER BY page')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    logActivity('Cleared Activity Logs', 'Admin cleared all activity log history', 'activity_log');
    $db->exec('TRUNCATE TABLE activity_logs');
    logActivity('Cleared Activity Logs', 'Activity log was cleared — this is the first new entry', 'activity_log');
    header('Location: ' . BASE_URL . '/activity_log.php');
    exit;
}

logActivity('Viewed Activity Log', 'Opened activity log page', 'activity_log');
$extraScripts = '<script src="' . BASE_URL . '/js/activity_log.js"></script>';
include __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1>Activity Log</h1>
        <p>All user actions across the system — <?= number_format($totalRows) ?> total entries</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= BASE_URL ?>/api/export_logs.php<?= $filterUser||$filterAction||$filterDate ? '?user='.$filterUser.'&action='.$filterAction.'&date='.$filterDate : '' ?>"
           class="btn btn-outline btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
        <button class="btn btn-danger btn-sm" onclick="openModal('clearLogsModal')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            Clear Logs
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card filter-card">
    <form method="GET" class="filter-form">
        <div class="filter-field">
            <label class="form-label">User</label>
            <select class="form-control form-select" name="user">
                <option value="">All Users</option>
                <?php foreach ($allUsers as $u): ?>
                <option value="<?= htmlspecialchars($u['user_id']) ?>"
                        <?= $filterUser === $u['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['user_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label class="form-label">Action</label>
            <select class="form-control form-select" name="action">
                <option value="">All Actions</option>
                <?php foreach ($allActions as $a): ?>
                <option value="<?= htmlspecialchars($a['action']) ?>"
                        <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['action']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label class="form-label">Page</label>
            <select class="form-control form-select" name="page_filter">
                <option value="">All Pages</option>
                <?php foreach ($allPages as $pg): ?>
                <option value="<?= htmlspecialchars($pg['page']) ?>"
                        <?= $filterPage === $pg['page'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($pg['page']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field filter-field-date">
            <label class="form-label">Date</label>
            <input class="form-control" type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <div class="filter-actions">
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
                    <th>Date &amp; Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="8" class="td-empty">No activity logs found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $i => $log):
                $actionClass = match(true) {
                    str_contains($log['action'], 'Delete') || str_contains($log['action'], 'Clear')  => 'badge-crit',
                    str_contains($log['action'], 'Add')    || str_contains($log['action'], 'Create') => 'badge-safe',
                    str_contains($log['action'], 'Override') || str_contains($log['action'], 'Edit') => 'badge-warn',
                    str_contains($log['action'], 'Login')  => 'badge-safe',
                    str_contains($log['action'], 'Logout') => 'badge-gray',
                    default => 'badge-gray'
                };
                $logRoleCls = match($log['user_role']) {
                    'Administrator'   => 'role-admin',
                    'Library Manager' => 'role-manager',
                    default           => 'role-staff'
                };
            ?>
            <tr>
                <td class="td-rownum"><?= $offset + $i + 1 ?></td>
                <td>
                    <div class="user-cell">
                        <div class="user-avatar user-avatar-sm">
                            <?= strtoupper(substr($log['user_name'], 0, 1)) ?>
                        </div>
                        <span class="td-bold"><?= htmlspecialchars($log['user_name']) ?></span>
                    </div>
                </td>
                <td><span class="role-badge <?= $logRoleCls ?>"><?= htmlspecialchars($log['user_role']) ?></span></td>
                <td><span class="badge <?= $actionClass ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                <td class="td-detail"><?= htmlspecialchars($log['detail'] ?: '—') ?></td>
                <td><span class="page-chip"><?= htmlspecialchars($log['page'] ?: '—') ?></span></td>
                <td class="td-mono"><?= htmlspecialchars($log['ip'] ?: '—') ?></td>
                <td class="td-datetime">
                    <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                    <span class="td-sub"><?= date('h:i:s A', strtotime($log['created_at'])) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-bar">
        <div class="pagination-info">
            Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= number_format($totalRows) ?>
        </div>
        <div class="pagination-pages">
            <?php
            // Show max 7 page links with ellipsis for large sets
            $range = 2;
            for ($pg = 1; $pg <= $totalPages; $pg++):
                $near = abs($pg - $page) <= $range || $pg === 1 || $pg === $totalPages;
                if (!$near) {
                    // Print ellipsis once per gap
                    if ($pg === 2 || $pg === $totalPages - 1) echo '<span class="pagination-ellipsis">…</span>';
                    continue;
                }
                $q = http_build_query(array_merge($_GET, ['p' => $pg]));
            ?>
            <a href="?<?= $q ?>" class="page-btn <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Clear Logs Confirm Modal -->
<div class="modal-overlay" id="clearLogsModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <div class="modal-title">Clear Activity Logs</div>
            <button class="modal-close" onclick="closeModal('clearLogsModal')">✕</button>
        </div>
        <div class="delete-confirm-body">
            <div class="delete-confirm-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                    <path d="M10 11v6"/><path d="M14 11v6"/>
                </svg>
            </div>
            <div class="delete-confirm-title">Clear all activity logs?</div>
            <div class="delete-confirm-sub">
                All <?= number_format($totalRows) ?> log entries will be permanently deleted.
                A new entry recording this action will be created.
                This cannot be undone.
            </div>
        </div>
        <form method="POST" id="clearLogsForm">
            <input type="hidden" name="action" value="clear_logs">
        </form>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('clearLogsModal')">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="document.getElementById('clearLogsForm').submit()">Yes, Clear All</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
