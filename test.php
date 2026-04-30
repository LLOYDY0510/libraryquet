<?php
// ============================================================
// test.php — System Health Check (Admin only)
// Replaces the bare "Hello World" placeholder.
// Verifies DB connection, table existence, and bcrypt support.
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireRole('Administrator');

$checks = [];

// 1. DB connection
try {
    getDB()->query('SELECT 1');
    $checks[] = ['label' => 'Database connection',   'ok' => true,  'detail' => DB_HOST . ' / ' . DB_NAME];
} catch (Exception $e) {
    $checks[] = ['label' => 'Database connection',   'ok' => false, 'detail' => $e->getMessage()];
}

// 2. Required tables
$requiredTables = ['users','zones','alerts','alert_messages','reports','sensor_overrides','activity_logs'];
foreach ($requiredTables as $tbl) {
    try {
        getDB()->query("SELECT 1 FROM $tbl LIMIT 1");
        $checks[] = ['label' => "Table: $tbl", 'ok' => true, 'detail' => 'exists'];
    } catch (Exception $e) {
        $checks[] = ['label' => "Table: $tbl", 'ok' => false, 'detail' => 'MISSING — run setup.sql'];
    }
}

// 3. bcrypt support
$hash = password_hash('test', PASSWORD_BCRYPT);
$checks[] = ['label' => 'bcrypt password hashing', 'ok' => password_verify('test', $hash), 'detail' => 'PHP ' . PHP_VERSION];

// 4. Activity log write test
try {
    logActivity('System Health Check', 'Admin ran system health check', 'test');
    $checks[] = ['label' => 'Activity log write', 'ok' => true, 'detail' => 'Logged successfully'];
} catch (Exception $e) {
    $checks[] = ['label' => 'Activity log write', 'ok' => false, 'detail' => $e->getMessage()];
}

// 5. role_rules table should NOT exist (we removed it)
try {
    getDB()->query('SELECT 1 FROM role_rules LIMIT 1');
    $checks[] = ['label' => 'role_rules table removed', 'ok' => false, 'detail' => 'Table still exists — run: DROP TABLE role_rules;'];
} catch (Exception $e) {
    $checks[] = ['label' => 'role_rules table removed', 'ok' => true, 'detail' => 'Correctly absent'];
}

$allOk = array_reduce($checks, fn($carry, $c) => $carry && $c['ok'], true);

$pageTitle = 'System Health Check';
include __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
    <div>
        <h1>System Health Check</h1>
        <p>Verifies database, tables, and core functionality</p>
    </div>
    <span class="badge <?= $allOk ? 'badge-safe' : 'badge-crit' ?>" style="font-size:13px;padding:6px 14px;">
        <?= $allOk ? '✓ All Systems OK' : '⚠ Issues Found' ?>
    </span>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Check</th>
                    <th>Status</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $c): ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($c['label']) ?></td>
                <td>
                    <span class="badge <?= $c['ok'] ? 'badge-safe' : 'badge-crit' ?>">
                        <?= $c['ok'] ? '✓ OK' : '✗ FAIL' ?>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--gray-500);font-family:monospace;">
                    <?= htmlspecialchars($c['detail']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-100);font-size:12px;color:var(--gray-400);">
        PHP <?= PHP_VERSION ?> &nbsp;·&nbsp; Server: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') ?>
        &nbsp;·&nbsp; Checked at <?= date('M d, Y h:i:s A') ?>
    </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
