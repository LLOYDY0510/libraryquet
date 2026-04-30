<?php
// api/zone_levels.php
// Returns current zone levels JSON for live dashboard refresh
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');
try {
    $zones = getDB()->query(
        'SELECT id, name, level, warn_threshold, crit_threshold FROM zones WHERE status = "active"'
    )->fetchAll();

    $result = array_map(function($z) {
        return [
            'id'     => $z['id'],
            'name'   => $z['name'],
            'level'  => (float)$z['level'],
            'status' => noiseStatus((float)$z['level']),
        ];
    }, $zones);

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([]);
}
