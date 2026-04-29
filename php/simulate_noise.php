<?php
// ============================================================
// php/simulate_noise.php
// IoT Noise Simulator — called by cron every 7 minutes
// Simulates 7 raw readings per zone, averages them,
// then writes ONE averaged record to the DB.
// SAFE: 1 UPDATE per zone + 1 INSERT on threshold breach
// ============================================================
require_once __DIR__ . '/../includes/config.php';

// Only allow CLI or local calls (basic protection)
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)) {
    // Allow via secret token for cron-over-HTTP
    $token = $_GET['token'] ?? '';
    if ($token !== 'lqms_sim_7min') {
        http_response_code(403);
        die('Forbidden');
    }
}

$db = getDB();

// Fetch all active zones
$zones = $db->query('SELECT * FROM zones WHERE status = "active"')->fetchAll();

$log = [];

foreach ($zones as $zone) {
    $zoneId   = $zone['id'];
    $zoneName = $zone['name'];
    $warnT    = (int)$zone['warn_threshold'];
    $critT    = (int)$zone['crit_threshold'];

    // Skip zones with manual override — don't touch their levels
    if ($zone['manual_override']) {
        $log[] = "[SKIP] {$zoneName} — manual override active";
        continue;
    }

    // ── Simulate 7 raw readings over the interval ──────────
    // Base noise per zone type, with realistic random variance
    $baseNoise = match(true) {
        str_contains(strtolower($zoneName), 'reading')  => 30.0,
        str_contains(strtolower($zoneName), 'study')    => 42.0,
        str_contains(strtolower($zoneName), 'computer') => 25.0,
        default => 35.0,
    };

    // Occasionally spike (10% chance) to simulate a noisy event
    $spike = (rand(1, 10) === 1);
    $readings = [];
    for ($i = 0; $i < 7; $i++) {
        $variance = rand(-80, 120) / 10.0;  // ±8–12 dB realistic variance
        $raw      = $baseNoise + $variance;
        if ($spike) $raw += rand(20, 35); // sudden noise event
        $raw = max(15.0, min(90.0, $raw)); // clamp 15–90 dB
        $readings[] = $raw;
    }

    // Average of the 7 readings (what goes into DB)
    $avgLevel = round(array_sum($readings) / count($readings), 2);

    // Update zone level
    $db->prepare('UPDATE zones SET level = ?, updated_at = NOW() WHERE id = ?')
       ->execute([$avgLevel, $zoneId]);

    $nowStatus = noiseStatus($avgLevel);

    // ── Create alert only if threshold is breached ─────────
    // Check if there's already an active alert for this zone
    $existingAlert = $db->prepare(
        'SELECT id FROM alerts WHERE zone_name = ? AND status = "active" AND type != "resolved" LIMIT 1'
    );
    $existingAlert->execute([$zoneName]);
    $existing = $existingAlert->fetch();

    if ($nowStatus !== 'safe') {
        if (!$existing) {
            // New alert
            $alertId = generateId('ALT');
            $alertType = $nowStatus === 'critical' ? 'critical' : 'warning';
            $msg = "Noise level reached {$avgLevel} dB in {$zoneName}. "
                 . ($alertType === 'critical' ? 'CRITICAL threshold exceeded.' : 'WARNING threshold exceeded.');

            $db->prepare(
                'INSERT INTO alerts (id, zone_name, level, type, msg, status, alert_date, alert_time)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $alertId, $zoneName, $avgLevel,
                $alertType, $msg, 'active',
                date('F d, Y'), date('h:i A')
            ]);

            // System auto-message
            $msgId = generateId('MSG');
            $db->prepare(
                'INSERT INTO alert_messages (id, alert_id, from_name, from_role, message, msg_time, msg_date, is_system)
                 VALUES (?,?,?,?,?,?,?,1)'
            )->execute([
                $msgId, $alertId,
                'System', 'Automated Sensor',
                "Auto-alert: {$avgLevel} dB average recorded. Readings: " . implode(', ', array_map(fn($r)=>round($r,1), $readings)) . ' dB.',
                date('h:i A'), date('F d, Y')
            ]);

            // Log system alert to activity log (no user session — use direct insert)
            try {
                $db->prepare(
                    'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute(['SYSTEM', 'Auto-Sensor', 'System',
                    'System Alert',
                    "{$alertType} alert triggered in {$zoneName}: {$avgLevel} dB",
                    'simulate_noise', '127.0.0.1']);
            } catch (Exception $e) { /* silent */ }

            $log[] = "[ALERT] {$zoneName} — {$alertType} at {$avgLevel} dB (avg of 7)";
        } else {
            // Update existing alert level
            $db->prepare('UPDATE alerts SET level = ? WHERE id = ?')
               ->execute([$avgLevel, $existing['id']]);
            $log[] = "[UPDATE] {$zoneName} — still {$nowStatus} at {$avgLevel} dB";
        }
    } else {
        // Auto-resolve if was active and now safe
        if ($existing) {
            $db->prepare(
                'UPDATE alerts SET status = "resolved", type = "resolved",
                 resolved_by = "Auto-Sensor", resolved_at = ? WHERE id = ?'
            )->execute([date('M d, Y h:i A'), $existing['id']]);

            $msgId = generateId('MSG');
            $db->prepare(
                'INSERT INTO alert_messages (id, alert_id, from_name, from_role, message, msg_time, msg_date, is_system)
                 VALUES (?,?,?,?,?,?,?,1)'
            )->execute([
                $msgId, $existing['id'],
                'System', 'Automated Sensor',
                "Auto-resolved: Noise level returned to safe range ({$avgLevel} dB).",
                date('h:i A'), date('F d, Y')
            ]);

            // Log auto-resolve to activity log
            try {
                $db->prepare(
                    'INSERT INTO activity_logs (user_id, user_name, user_role, action, detail, page, ip)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute(['SYSTEM', 'Auto-Sensor', 'System',
                    'System Alert Resolved',
                    "Auto-resolved: {$zoneName} returned to safe range ({$avgLevel} dB)",
                    'simulate_noise', '127.0.0.1']);
            } catch (Exception $e) { /* silent */ }

            $log[] = "[RESOLVE] {$zoneName} — back to safe at {$avgLevel} dB";
        } else {
            $log[] = "[OK] {$zoneName} — safe at {$avgLevel} dB";
        }
    }
}

// Output log (useful for CLI cron)
if (PHP_SAPI === 'cli') {
    echo date('[Y-m-d H:i:s]') . " Simulation complete:\n";
    foreach ($log as $line) echo "  $line\n";
} else {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'log' => $log, 'time' => date('Y-m-d H:i:s')]);
}
