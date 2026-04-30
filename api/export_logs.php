<?php
// ============================================================
// api/export_logs.php — Export activity logs as CSV
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator');

$db = getDB();

$where  = ['1=1'];
$params = [];

if (!empty($_GET['user']))   { $where[] = 'user_id = ?';          $params[] = $_GET['user']; }
if (!empty($_GET['action'])) { $where[] = 'action = ?';           $params[] = $_GET['action']; }
if (!empty($_GET['date']))   { $where[] = 'DATE(created_at) = ?'; $params[] = $_GET['date']; }

$stmt = $db->prepare('SELECT * FROM activity_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC');
$stmt->execute($params);
$logs = $stmt->fetchAll();

logActivity('Exported Activity Logs', 'Admin exported activity log CSV', 'activity_log');

$filename = 'lqms_activity_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fputcsv($out, ['#', 'User', 'Role', 'Action', 'Detail', 'Page', 'IP Address', 'User Agent', 'Date & Time']);

foreach ($logs as $i => $log) {
    fputcsv($out, [
        $i + 1,
        $log['user_name'],
        $log['user_role'],
        $log['action'],
        $log['detail'],
        $log['page'],
        $log['ip'],
        $log['user_agent'],
        $log['created_at'],
    ]);
}
fclose($out);
exit;
