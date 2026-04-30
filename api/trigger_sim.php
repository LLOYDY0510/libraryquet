<?php
// api/trigger_sim.php
// Called by JS countdown every 7 min to run simulation
// Protected by session + token
require_once __DIR__ . '/../includes/config.php';
requireLogin(); // must be logged in

header('Content-Type: application/json');

// Include the simulator directly (no HTTP call to avoid loopback issues)
ob_start();
$_GET['token'] = 'lqms_sim_7min'; // satisfy the token check
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

try {
    // Execute the simulation logic inline
    $db = getDB();
    $zones = $db->query('SELECT * FROM zones WHERE status = "active"')->fetchAll();
    $log = [];

    foreach ($zones as $zone) {
        if ($zone['manual_override']) continue;

        $zoneName = $zone['name'];
        $baseNoise = match(true) {
            str_contains(strtolower($zoneName), 'reading')  => 30.0,
            str_contains(strtolower($zoneName), 'study')    => 42.0,
            str_contains(strtolower($zoneName), 'computer') => 25.0,
            default => 35.0,
        };

        $spike    = (rand(1, 10) === 1);
        $readings = [];
        for ($i = 0; $i < 7; $i++) {
            $raw = $baseNoise + rand(-80, 120) / 10.0;
            if ($spike) $raw += rand(20, 35);
            $readings[] = max(15.0, min(90.0, $raw));
        }

        $avgLevel  = round(array_sum($readings) / count($readings), 2);
        $nowStatus = noiseStatus($avgLevel);

        $db->prepare('UPDATE zones SET level = ?, updated_at = NOW() WHERE id = ?')
           ->execute([$avgLevel, $zone['id']]);

        $existing = $db->prepare(
            'SELECT id FROM alerts WHERE zone_name = ? AND status = "active" LIMIT 1'
        );
        $existing->execute([$zoneName]);
        $ex = $existing->fetch();

        if ($nowStatus !== 'safe') {
            if (!$ex) {
                $alertId   = generateId('ALT');
                $alertType = $nowStatus === 'critical' ? 'critical' : 'warning';
                $db->prepare(
                    'INSERT INTO alerts (id, zone_name, level, type, msg, status, alert_date, alert_time)
                     VALUES (?,?,?,?,?,?,?,?)'
                )->execute([
                    $alertId, $zoneName, $avgLevel, $alertType,
                    "Noise at {$avgLevel} dB in {$zoneName}.", 'active',
                    date('F d, Y'), date('h:i A')
                ]);
                $msgId = generateId('MSG');
                $db->prepare(
                    'INSERT INTO alert_messages (id, alert_id, from_name, from_role, message, msg_time, msg_date, is_system)
                     VALUES (?,?,?,?,?,?,?,1)'
                )->execute([$msgId, $alertId, 'System', 'Sensor', "Auto-alert: {$avgLevel} dB avg.", date('h:i A'), date('F d, Y')]);

                // Log system alert
                try {
                    $db->prepare(
                        'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip)
                         VALUES (?,?,?,?,?,?,?)'
                    )->execute(['SYSTEM','Auto-Sensor','System','System Alert',
                        "$alertType alert triggered in $zoneName: $avgLevel dB",
                        'trigger_sim','127.0.0.1']);
                } catch (Exception $e) { /* silent */ }
            } else {
                $db->prepare('UPDATE alerts SET level = ? WHERE id = ?')->execute([$avgLevel, $ex['id']]);
            }
        } elseif ($ex) {
            $db->prepare(
                'UPDATE alerts SET status = "resolved", type = "resolved", resolved_by = "Auto-Sensor", resolved_at = ? WHERE id = ?'
            )->execute([date('M d, Y h:i A'), $ex['id']]);
        }

        $log[] = "{$zoneName}: {$avgLevel} dB ({$nowStatus})";
    }

    ob_end_clean();
    echo json_encode(['ok' => true, 'log' => $log, 'time' => date('H:i:s')]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
