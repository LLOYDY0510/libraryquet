<?php
// api/active_alerts_count.php
// Returns JSON count of active alerts — used by sidebar badge
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');
try {
    $count = getDB()->query('SELECT COUNT(*) FROM alerts WHERE status = "active"')->fetchColumn();
    echo json_encode(['count' => (int)$count]);
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
}
