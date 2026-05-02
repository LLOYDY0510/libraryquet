<?php
// ============================================================
// dashboard.php — Main Dashboard
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
logActivity('Viewed Dashboard', 'Viewed the main dashboard', 'dashboard');

$db        = getDB();
$pageTitle = 'Dashboard';
$pageSubtitle = 'Live noise levels across all monitored zones';

// ── Zone summary ──────────────────────────────────────────────
$zones = $db->query(
    'SELECT * FROM zones WHERE status = "active" ORDER BY id'
)->fetchAll();

// ── Alert counts ──────────────────────────────────────────────
$alertCount = $db->query(
    'SELECT COUNT(*) FROM alerts WHERE status = "active"'
)->fetchColumn();

$recentAlerts = $db->query(
    'SELECT * FROM alerts ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

// ── Stat counters ─────────────────────────────────────────────
$totalZones = count($zones);
$critZones  = 0;
$warnZones  = 0;
$safeZones  = 0;
foreach ($zones as $z) {
    $s = noiseStatus($z['level']);
    if ($s === 'critical')     $critZones++;
    elseif ($s === 'warning')  $warnZones++;
    else                       $safeZones++;
}

// ── Chart data: last 10 alert readings per zone ───────────────
$chartLabels = [];
$chartData   = [];
foreach ($zones as $z) {
    $rows = $db->prepare(
        'SELECT level, alert_time FROM alerts
         WHERE zone_name = ? ORDER BY created_at DESC LIMIT 10'
    );
    $rows->execute([$z['name']]);
    $history = array_reverse($rows->fetchAll());
    $chartData[$z['id']] = array_map(fn($r) => (float)$r['level'], $history);
    if (empty($chartLabels) && !empty($history)) {
        $chartLabels = array_map(fn($r) => $r['alert_time'], $history);
    }
}

// ── Extra head content ────────────────────────────────────────
$extraStyles = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
/* ── Leaflet popup styling ── */
.leaflet-popup-content-wrapper {
    border-radius: 12px !important;
    box-shadow: 0 8px 28px rgba(0,0,0,.12) !important;
    border: 1px solid var(--gray-200) !important;
    padding: 0 !important;
    overflow: hidden;
}
.leaflet-popup-content {
    margin: 0 !important;
    font-family: "Plus Jakarta Sans", sans-serif !important;
    font-size: 13px !important;
    min-width: 200px;
}
.leaflet-popup-tip-container { margin-top: -1px; }

/* ── Zone map marker pulse ── */
@keyframes mapPulse {
    0%, 100% { box-shadow: 0 0 0 0   rgba(var(--pulse-rgb), .5); }
    50%      { box-shadow: 0 0 0 10px rgba(var(--pulse-rgb), 0); }
}
.zone-marker-critical { animation: mapPulse 1.6s ease-in-out infinite; }

/* ── Activity feed flash ── */
@keyframes actFlash {
    0%   { background: rgba(37,99,235,.10); }
    100% { background: transparent; }
}
</style>';

include __DIR__ . '/includes/layout.php';
?>

<!-- ════════════════════════════════════════════════════════════
     STAT CARDS
     ════════════════════════════════════════════════════════════ -->
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div class="stat-value"><?= $totalZones ?></div>
        <div class="stat-label">Zones Monitored</div>
    </div>

    <div class="stat-card safe-card">
        <div class="stat-icon safe">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="stat-value safe"><?= $safeZones ?></div>
        <div class="stat-label">Zones in Safe Range</div>
    </div>

    <div class="stat-card warn-card">
        <div class="stat-icon warn">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div class="stat-value warn"><?= $warnZones ?></div>
        <div class="stat-label">Zones with Warnings</div>
    </div>

    <div class="stat-card crit-card">
        <div class="stat-icon crit">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <div class="stat-value crit"><?= $critZones ?></div>
        <div class="stat-label">Critical Alerts Active</div>
    </div>

</div>

<!-- ════════════════════════════════════════════════════════════
     MAIN GRID  (left: zone overview + chart | right: alerts + sim)
     ════════════════════════════════════════════════════════════ -->
<div class="dashboard-grid">

    <!-- ── LEFT COLUMN ─────────────────────────────────────── -->
    <div class="dash-col-left">

        <!-- Zone Overview -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Zone Overview</span>
                <span class="sensor-status">All Sensors Online</span>
            </div>

            <?php foreach ($zones as $z):
                $status   = noiseStatus($z['level']);
                $pct      = min(($z['level'] / 90) * 100, 100);
                $label    = noiseLabel($z['level']);
                $bat      = (int)$z['battery'];
                $batClass = $bat > 60 ? 'high' : ($bat > 30 ? 'mid' : 'low');
            ?>
            <div class="zone-row" data-zone="<?= $z['id'] ?>">
                <div class="zone-row-header">
                    <div>
                        <span class="zone-row-name"><?= htmlspecialchars($z['name']) ?></span>
                        <span class="zone-row-sub"><?= htmlspecialchars($z['floor']) ?> · <?= htmlspecialchars($z['sensor']) ?></span>
                    </div>
                    <div class="zone-row-badges">
                        <span class="battery <?= $batClass ?>">
                            <span class="battery-bar">
                                <span class="battery-fill" style="width:<?= $bat ?>%"></span>
                            </span>
                            <?= $bat ?>%
                        </span>
                        <span class="badge badge-<?= $status === 'safe' ? 'safe' : ($status === 'warning' ? 'warn' : 'crit') ?>"><?= $label ?></span>
                    </div>
                </div>

                <div class="db-bar-wrap">
                    <div class="db-bar">
                        <div class="db-bar-fill <?= $status ?>"
                             style="width:0"
                             data-pct="<?= round($pct, 1) ?>"></div>
                    </div>
                    <div class="db-val zone-db-num <?= $status ?>"><?= number_format($z['level'], 1) ?> dB</div>
                </div>

                <div class="zone-row-meta">
                    Warn: <?= $z['warn_threshold'] ?>dB &nbsp;·&nbsp;
                    Critical: <?= $z['crit_threshold'] ?>dB &nbsp;·&nbsp;
                    Occupied: <?= $z['occupied'] ?>/<?= $z['capacity'] ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="card-action-row">
                <a href="<?= BASE_URL ?>/zones.php" class="btn btn-outline btn-sm">View All Zones →</a>
            </div>
        </div>

        <!-- Noise History Chart -->
        <div class="card card-stack">
            <div class="card-header">
                <span class="card-title">Noise Level History</span>
                <span class="chart-legend" id="chartLegend"></span>
            </div>
            <canvas id="noiseChart" class="chart-container"></canvas>
        </div>

    </div><!-- /dash-col-left -->

    <!-- ── RIGHT COLUMN ────────────────────────────────────── -->
    <div class="dash-col-right">

        <!-- Recent Alerts -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Recent Alerts</span>
                <?php if ($alertCount > 0): ?>
                <span class="badge badge-crit"><?= $alertCount ?> Active</span>
                <?php endif; ?>
            </div>

            <?php if (empty($recentAlerts)): ?>
            <div class="empty-state">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                <h3>No alerts yet</h3>
                <p>All zones are within normal range.</p>
            </div>
            <?php else: ?>

            <?php foreach ($recentAlerts as $a): ?>
            <div class="alert-item">
                <div class="alert-icon <?= $a['status'] === 'resolved' ? 'resolved' : $a['type'] ?>">
                    <?php if ($a['type'] === 'critical'): ?>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php else: ?>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <?php endif; ?>
                </div>
                <div class="alert-body">
                    <div class="alert-zone"><?= htmlspecialchars($a['zone_name']) ?></div>
                    <div class="alert-desc"><?= number_format($a['level'], 1) ?> dB — <?= ucfirst($a['type']) ?></div>
                    <div class="alert-time"><?= htmlspecialchars($a['alert_date']) ?> <?= htmlspecialchars($a['alert_time']) ?></div>
                </div>
                <?php if ($a['status'] === 'active'): ?>
                <div class="alert-actions">
                    <a href="<?= BASE_URL ?>/alerts.php" class="btn btn-outline btn-sm">View</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="card-action-row">
                <a href="<?= BASE_URL ?>/alerts.php" class="btn btn-outline btn-sm">All Alerts →</a>
            </div>

            <?php endif; ?>
        </div>

        <!-- Simulation Status -->
        <div class="card card-stack">
            <div class="card-header">
                <span class="card-title">Simulation Status</span>
                <span class="badge badge-blue">Simulated IoT</span>
            </div>
            <div class="sim-status-row">
                <span>Data Interval</span>
                <span class="sim-status-val">Every 7 min</span>
            </div>
            <div class="sim-status-row">
                <span>Averaging</span>
                <span class="sim-status-val">Per interval</span>
            </div>
            <div class="sim-status-row">
                <span>Next Read</span>
                <span class="sim-status-val" id="nextRead">Calculating…</span>
            </div>
        </div>

    </div><!-- /dash-col-right -->
</div><!-- /dashboard-grid -->

<!-- ════════════════════════════════════════════════════════════
     ZONE MAP  (Admin & Library Manager only)
     ════════════════════════════════════════════════════════════ -->
<?php if (hasRole('Administrator', 'Library Manager')): ?>
<div class="card card-stack">
    <div class="card-header">
        <div>
            <div class="card-title-lg">🗺️ Zone Map — NBSC Campus</div>
            <div class="card-subtitle">Live noise markers · updates every 30 s</div>
        </div>
        <div class="map-legend">
            <span class="map-legend-chip map-chip-safe">
                <span class="map-chip-dot map-chip-dot-safe"></span>
                Quiet &lt;40 dB
            </span>
            <span class="map-legend-chip map-chip-warn">
                <span class="map-chip-dot map-chip-dot-warn"></span>
                Moderate 40–60
            </span>
            <span class="map-legend-chip map-chip-crit">
                <span class="map-chip-dot map-chip-dot-crit"></span>
                Loud &gt;60 dB
            </span>
            <button class="btn btn-outline btn-sm" onclick="resetZoneMap()">↺ Reset</button>
        </div>
    </div>

    <div id="zoneMap" class="zone-map-container"></div>

    <div class="card-footer-note">
        Last updated: <span id="mapLastUpdate">—</span>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     LIVE ACTIVITY LOG  (Admin only)
     ════════════════════════════════════════════════════════════ -->
<?php if (hasRole('Administrator')): ?>
<div class="card card-stack">
    <div class="card-header">
        <div>
            <div class="card-title-lg">📋 Live Activity Log</div>
            <div class="card-subtitle">Recent user actions · auto-refreshes every 15 s</div>
        </div>
        <div class="act-header-right">
            <span id="actToday"  class="act-stat">Today: <strong id="actTodayVal">—</strong></span>
            <span id="actUsers"  class="act-stat">Users: <strong id="actUsersVal">—</strong></span>
            <a href="<?= BASE_URL ?>/activity_log.php" class="btn btn-outline btn-sm">View All</a>
        </div>
    </div>
    <div id="activityFeed" class="activity-feed">
        <div class="act-loading">Loading activity…</div>
    </div>
</div>
<?php endif; ?>

<?php
// ── PHP → JS data bridge ──────────────────────────────────────
// Only the data values go here. All logic lives in the JS blocks below.
$jsHasMap    = hasRole('Administrator', 'Library Manager') ? 'true' : 'false';
$jsHasActLog = hasRole('Administrator') ? 'true' : 'false';
$jsChartData = json_encode(array_values($chartData));
$jsLabels    = json_encode($chartLabels);
$jsZoneNames = json_encode(array_column($zones, 'name'));

$extraScripts = <<<JS
<script src="<?= BASE_URL ?>/js/charts.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
// ── Data from PHP ─────────────────────────────────────────────
const CHART_DATA  = {$jsChartData};
const CHART_LBLS  = {$jsLabels};
const ZONE_NAMES  = {$jsZoneNames};
const HAS_MAP     = {$jsHasMap};
const HAS_ACT_LOG = {$jsHasActLog};
</script>
<script src="<?= BASE_URL ?>/js/dashboard.js"></script>
JS;

include __DIR__ . '/includes/layout_footer.php';
?>
