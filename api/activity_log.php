<?php
// ============================================================
// api/activity_log.php — Recent activity JSON feed
// Used by the Admin dashboard live widget
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator');

header('Content-Type: application/json');
header('Cache-Control: no-store');

$limit  = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$since  = $_GET['since'] ?? null; // ISO timestamp — only return newer entries

try {
    $db = getDB();

    if ($since) {
        $stmt = $db->prepare(
            'SELECT id, user_name, user_role, action, detail, page, ip, created_at
             FROM activity_logs
             WHERE created_at > ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$since, $limit]);
    } else {
        $stmt = $db->prepare(
            'SELECT id, user_name, user_role, action, detail, page, ip, created_at
             FROM activity_logs
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
    }

    $logs = $stmt->fetchAll();

    // Count by action type for mini stats
    $stats = $db->query(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
            COUNT(DISTINCT user_id) AS unique_users,
            SUM(CASE WHEN action LIKE "%Login%" THEN 1 ELSE 0 END) AS logins
         FROM activity_logs'
    )->fetch();

    echo json_encode([
        'logs'  => $logs,
        'stats' => $stats,
        'ts'    => date('c'),
    ]);
} catch (Exception $e) {
    echo json_encode(['logs' => [], 'stats' => [], 'ts' => date('c')]);
}
