<?php
// ============================================================
// api/zone_map.php — Zone map data (lat/lng + noise levels)
// Called by the dashboard map every 30 seconds
// ============================================================
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $zones = getDB()->query(
        'SELECT id, name, floor, sensor, level, lat, lng,
                warn_threshold, crit_threshold, occupied, capacity, battery
         FROM zones
         WHERE status = "active"
         ORDER BY id'
    )->fetchAll();

    $result = array_map(function($z) {
        return [
            'id'       => $z['id'],
            'name'     => $z['name'],
            'floor'    => $z['floor'],
            'sensor'   => $z['sensor'],
            'level'    => (float)$z['level'],
            'status'   => noiseStatus((float)$z['level']),
            'label'    => noiseLabel((float)$z['level']),
            'lat'      => (float)($z['lat']  ?? 0),
            'lng'      => (float)($z['lng']  ?? 0),
            'warnAt'   => (int)$z['warn_threshold'],
            'critAt'   => (int)$z['crit_threshold'],
            'occupied' => (int)$z['occupied'],
            'capacity' => (int)$z['capacity'],
            'battery'  => (int)$z['battery'],
        ];
    }, $zones);

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([]);
}
