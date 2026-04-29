<?php
// ============================================================
// dashboard.php — Main Dashboard
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle = 'Dashboard';

// Fetch zone summary
$zones = $db->query('SELECT * FROM zones WHERE status = "active" ORDER BY id')->fetchAll();

// Active alerts count
$alertCount = $db->query('SELECT COUNT(*) FROM alerts WHERE status = "active"')->fetchColumn();

// Recent alerts
$recentAlerts = $db->query(
    'SELECT * FROM alerts ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

// Stats
$totalZones   = count($zones);
$critZones    = 0;
$warnZones    = 0;
$safeZones    = 0;
foreach ($zones as $z) {
    $s = noiseStatus($z['level']);
    if ($s === 'critical') $critZones++;
    elseif ($s === 'warning') $warnZones++;
    else $safeZones++;
}

// Chart data: last 10 readings per zone (from alerts or simulated history)
// We pull the last 8 hours of simulated averages from alert records
$chartLabels = [];
$chartData   = [];
foreach ($zones as $z) {
    $rows = $db->prepare(
        'SELECT level, alert_time FROM alerts WHERE zone_name = ? ORDER BY created_at DESC LIMIT 10'
    );
    $rows->execute([$z['name']]);
    $history = $rows->fetchAll();
    $history = array_reverse($history);
    $chartData[$z['id']] = array_map(fn($r) => (float)$r['level'], $history);
    if (empty($chartLabels) && !empty($history)) {
        $chartLabels = array_map(fn($r) => $r['alert_time'], $history);
    }
}

// ── Leaflet CSS must be declared BEFORE layout.php so it renders inside <head> ──
$extraStyles = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
.map-legend-chip {
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 12px; border-radius:20px; font-size:11.5px; font-weight:600;
    border:1px solid;
}
.map-chip-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.safe-chip { background:var(--safe-bg);  border-color:rgba(16,185,129,.3); color:#065f46; }
.warn-chip { background:var(--warn-bg);  border-color:rgba(245,158,11,.3); color:#92400e; }
.crit-chip { background:var(--crit-bg);  border-color:rgba(239,68,68,.3);  color:#991b1b; }
.leaflet-popup-content-wrapper {
    border-radius:10px !important;
    box-shadow:0 8px 24px rgba(0,0,0,.14) !important;
    border:1px solid #e2e8f0 !important;
    padding:0 !important; overflow:hidden;
}
.leaflet-popup-content {
    margin:0 !important;
    font-family:"DM Sans",sans-serif !important;
    font-size:13px !important; line-height:1.5 !important; min-width:190px;
}
.leaflet-popup-tip-container { margin-top:-1px; }
@keyframes mapPulse {
    0%,100% { box-shadow:0 0 0 0 rgba(var(--pulse-rgb),.5); }
    50%      { box-shadow:0 0 0 8px rgba(var(--pulse-rgb),0); }
}
.zone-marker-critical { animation: mapPulse 1.6s ease-in-out infinite; }
</style>';

include __DIR__ . '/includes/layout.php';
?>

<div data-base="<?= BASE_URL ?>">

<!-- Stats Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div class="stat-value"><?= $totalZones ?></div>
        <div class="stat-label">Total Zones Monitored</div>
    </div>

    <div class="stat-card safe-card">
        <div class="stat-icon safe">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="stat-value" style="color:var(--safe)"><?= $safeZones ?></div>
        <div class="stat-label">Zones in Safe Range</div>
    </div>

    <div class="stat-card warn-card">
        <div class="stat-icon warn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div class="stat-value" style="color:var(--warn)"><?= $warnZones ?></div>
        <div class="stat-label">Zones with Warnings</div>
    </div>

    <div class="stat-card crit-card">
        <div class="stat-icon crit">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <div class="stat-value" style="color:var(--crit)"><?= $critZones ?></div>
        <div class="stat-label">Critical Alerts Active</div>
    </div>
</div>

<!-- Main Grid -->
<div class="dashboard-grid">

    <!-- Left: Zones Overview -->
    <div>
        <div class="card" style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div class="card-title" style="margin-bottom:0">Zone Overview</div>
                <span class="sensor-dot">All Sensors Online</span>
            </div>

            <?php foreach ($zones as $z):
                $status = noiseStatus($z['level']);
                $pct    = min(($z['level'] / 90) * 100, 100);
                $label  = noiseLabel($z['level']);
                $bat    = (int)$z['battery'];
                $batClass = $bat > 60 ? 'high' : ($bat > 30 ? 'mid' : 'low');
            ?>
            <div style="padding:14px 0;border-bottom:1px solid var(--gray-100);" data-zone="<?= $z['id'] ?>" class="zone-row">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <div>
                        <span style="font-weight:600;font-size:13.5px;color:var(--gray-900)"><?= htmlspecialchars($z['name']) ?></span>
                        <span style="font-size:11px;color:var(--gray-400);margin-left:8px"><?= htmlspecialchars($z['floor']) ?> · <?= htmlspecialchars($z['sensor']) ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;">
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
                        <div class="db-bar-fill <?= $status ?>" style="width:0"
                             data-pct="<?= round($pct, 1) ?>"></div>
                    </div>
                    <div class="db-val zone-db-num <?= $status ?>"><?= number_format($z['level'], 1) ?> dB</div>
                </div>
                <div style="font-size:10.5px;color:var(--gray-400);margin-top:5px;">
                    Warn: <?= $z['warn_threshold'] ?>dB &nbsp;|&nbsp;
                    Critical: <?= $z['crit_threshold'] ?>dB &nbsp;|&nbsp;
                    Occupied: <?= $z['occupied'] ?>/<?= $z['capacity'] ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:14px;text-align:right;">
                <a href="<?= BASE_URL ?>/zones.php" class="btn btn-outline btn-sm">View All Zones →</a>
            </div>
        </div>

        <!-- Noise History Chart -->
        <div class="card">
            <div class="card-title">Noise Level History</div>
            <canvas id="noiseChart" class="chart-container" style="height:220px;"></canvas>
        </div>
    </div>

    <!-- Right: Recent Alerts -->
    <div>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div class="card-title" style="margin-bottom:0">Recent Alerts</div>
                <?php if ($alertCount > 0): ?>
                <span class="badge badge-crit"><?= $alertCount ?> Active</span>
                <?php endif; ?>
            </div>

            <?php if (empty($recentAlerts)): ?>
            <div class="empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                </svg>
                <h3>No alerts yet</h3>
                <p>System is monitoring all zones.</p>
            </div>
            <?php else: ?>
            <?php foreach ($recentAlerts as $a): ?>
            <div class="alert-item">
                <div class="alert-icon <?= $a['status'] === 'resolved' ? 'resolved' : $a['type'] ?>">
                    <?php if ($a['type'] === 'critical'): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?php else: ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
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

            <div style="margin-top:12px;text-align:right;">
                <a href="<?= BASE_URL ?>/alerts.php" class="btn btn-outline btn-sm">All Alerts →</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Simulation Status -->
        <div class="card" style="margin-top:20px;">
            <div class="card-title">Simulation Status</div>
            <div style="font-size:13px;color:var(--gray-500);line-height:1.8;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span>Mode</span>
                    <span class="badge badge-blue">Simulated IoT</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span>Data Interval</span>
                    <span style="font-weight:600;color:var(--gray-700)">Every 7 min</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span>Averaging</span>
                    <span style="font-weight:600;color:var(--gray-700)">Per interval</span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span>Next Read</span>
                    <span style="font-weight:600;color:var(--blue-600)" id="nextRead">Calculating…</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (hasRole('Administrator', 'Library Manager')): ?>
<!-- ═══════════════════════════════════════════════════════════
     ZONE MAP — Admin & Manager only
     Leaflet map with live dB markers for each zone
     ═══════════════════════════════════════════════════════════ -->
<div class="card map-card" style="margin-top:20px;">

    <!-- Map header row -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <div>
            <div class="card-title" style="margin-bottom:2px;">
                🗺️ Zone Map — NBSC Campus
            </div>
            <div style="font-size:12px;color:var(--gray-400);">
                Live noise markers &nbsp;·&nbsp; Updates every 30s
            </div>
        </div>

        <!-- Legend chips -->
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="map-legend-chip safe-chip">
                <span class="map-chip-dot" style="background:var(--safe)"></span>
                Quiet &lt;40 dB
            </div>
            <div class="map-legend-chip warn-chip">
                <span class="map-chip-dot" style="background:var(--warn)"></span>
                Moderate 40–60
            </div>
            <div class="map-legend-chip crit-chip">
                <span class="map-chip-dot" style="background:var(--crit)"></span>
                Loud &gt;60 dB
            </div>
            <button class="btn btn-outline btn-sm" id="mapResetBtn" onclick="resetZoneMap()">
                ↺ Reset View
            </button>
        </div>
    </div>

    <!-- Map container -->
    <div id="zoneMap" style="height:380px;border-radius:10px;border:1px solid var(--gray-200);overflow:hidden;"></div>

    <!-- Last updated note -->
    <div style="margin-top:10px;font-size:11.5px;color:var(--gray-400);text-align:right;">
        Last map refresh: <span id="mapLastUpdate">—</span>
    </div>
</div>
<?php endif; ?>

<?php if (hasRole('Administrator')): ?>
<!-- ═══════════════════════════════════════════════════════════
     LIVE ACTIVITY LOG WIDGET — Admin only
     ═══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-top:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <div>
            <div class="card-title" style="margin-bottom:2px;">📋 Live Activity Log</div>
            <div style="font-size:12px;color:var(--gray-400);">Recent user actions &nbsp;·&nbsp; Auto-refreshes every 15s</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <!-- Mini stats -->
            <div id="actToday"  style="font-size:11.5px;color:var(--gray-400);">Today: <strong style="color:var(--blue-600);">—</strong></div>
            <div id="actUsers"  style="font-size:11.5px;color:var(--gray-400);">Users: <strong style="color:var(--blue-600);">—</strong></div>
            <a href="<?= BASE_URL ?>/activity_log.php" class="btn btn-outline btn-sm">View All</a>
        </div>
    </div>

    <!-- Activity feed -->
    <div id="activityFeed" style="min-height:120px;">
        <div style="text-align:center;padding:30px;color:var(--gray-300);font-size:13px;">Loading activity...</div>
    </div>
</div>
<?php endif; ?>

</div><!-- /data-base -->

<?php
$extraScripts = '
<script src="' . BASE_URL . '/js/charts.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
// ─────────────────────────────────────────────────────────────
//  PHP → JS data bridge
// ─────────────────────────────────────────────────────────────
// BASE_URL already declared by app.js — reuse it
const HAS_MAP     = ' . (hasRole('Administrator', 'Library Manager') ? 'true' : 'false') . ';

// ─────────────────────────────────────────────────────────────
//  EXISTING: zone progress bars + chart + countdown
// ─────────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", function() {

    // Init zone progress bars
    document.querySelectorAll(".db-bar-fill[data-pct]").forEach(el => {
        const pct = parseFloat(el.dataset.pct) || 0;
        setTimeout(() => { el.style.width = pct + "%"; }, 100);
    });

    // Noise history chart
    const chartLabels = ' . json_encode($chartLabels) . ';
    const chartData   = ' . json_encode(array_values($chartData)) . ';
    const zoneNames   = ' . json_encode(array_column($zones, 'name')) . ';

    if (chartData.length && chartData[0].length) {
        const datasets = chartData.map((d, i) => ({ label: zoneNames[i] || "Zone " + (i+1), data: d }));
        setTimeout(() => renderNoiseChart("noiseChart", datasets, chartLabels), 200);
    }

    // Next-read countdown
    const nextRead = document.getElementById("nextRead");
    if (nextRead) {
        const interval = 7 * 60;
        let remaining  = interval;
        const tick = () => {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            nextRead.textContent = m + "m " + String(s).padStart(2,"0") + "s";
            if (remaining-- <= 0) { remaining = interval; refreshZoneLevels(); }
        };
        tick();
        setInterval(tick, 1000);
    }

    // ─────────────────────────────────────────────────────────
    //  ZONE MAP (Admin & Library Manager only)
    // ─────────────────────────────────────────────────────────
    if (!HAS_MAP) return;

    const MAP_CENTER = [8.359282, 124.867826]; // NBSC Campus centre
    const MAP_ZOOM   = 20;

    // Colour helpers
    const STATUS_COLOR = { safe:"#10b981", warning:"#f59e0b", critical:"#ef4444" };
    const STATUS_PULSE = { safe:"16,185,129", warning:"245,158,11", critical:"239,68,68" };

    // ── Init Leaflet ──────────────────────────────────────────
    const map = L.map("zoneMap", {
        center: MAP_CENTER,
        zoom:   MAP_ZOOM,
        zoomControl: true,
    });

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a>",
        maxZoom: 22
    }).addTo(map);

    // marker registry
    const markers = {};

    // ── Build a DivIcon marker ────────────────────────────────
    function buildIcon(zone) {
        const color  = STATUS_COLOR[zone.status]  || "#64748b";
        const pulseRgb = STATUS_PULSE[zone.status] || "100,116,139";
        const isCrit = zone.status === "critical";

        return L.divIcon({
            className: "",
            iconSize:   [52, 52],
            iconAnchor: [26, 26],
            html: `<div style="
                width:52px;height:52px;border-radius:50%;
                background:${color}20;
                border:2.5px solid ${color};
                display:flex;align-items:center;justify-content:center;
                cursor:pointer;
                box-shadow:0 0 0 4px ${color}18, 0 4px 14px rgba(0,0,0,.25);
                --pulse-rgb:${pulseRgb};
            " class="${isCrit ? "zone-marker-critical" : ""}">
                <div style="text-align:center;line-height:1.1;">
                    <div style="font-weight:800;font-size:12px;color:${color};font-family:\'Space Grotesk\',sans-serif;">${zone.level.toFixed(0)}</div>
                    <div style="font-size:8px;color:${color};opacity:.75;font-weight:600;">dB</div>
                </div>
            </div>`
        });
    }

    // ── Build popup HTML ──────────────────────────────────────
    function buildPopup(zone) {
        const color = STATUS_COLOR[zone.status] || "#64748b";
        const batColor = zone.battery > 60 ? "#10b981" : zone.battery > 30 ? "#f59e0b" : "#ef4444";
        return `
        <div style="padding:14px 16px 16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div>
                    <div style="font-family:\'Space Grotesk\',sans-serif;font-weight:700;font-size:14px;color:#0f172a;">${zone.name}</div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:1px;">${zone.floor} · ${zone.sensor}</div>
                </div>
                <span style="
                    background:${color}18;color:${color};
                    border:1px solid ${color}40;
                    font-size:10px;font-weight:700;padding:2px 8px;border-radius:5px;
                    text-transform:uppercase;letter-spacing:.4px;
                ">${zone.label}</span>
            </div>

            <div style="font-family:\'Space Grotesk\',sans-serif;font-size:30px;font-weight:800;color:${color};margin-bottom:12px;line-height:1;">
                ${zone.level.toFixed(1)}
                <span style="font-size:13px;font-weight:400;color:#94a3b8;"> dB</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:11.5px;">
                <div style="background:#f8fafc;border-radius:6px;padding:6px 10px;">
                    <div style="color:#94a3b8;font-size:10px;margin-bottom:2px;">OCCUPIED</div>
                    <div style="font-weight:600;color:#334155;">${zone.occupied}/${zone.capacity}</div>
                </div>
                <div style="background:#f8fafc;border-radius:6px;padding:6px 10px;">
                    <div style="color:#94a3b8;font-size:10px;margin-bottom:2px;">BATTERY</div>
                    <div style="font-weight:600;color:${batColor};">${zone.battery}%</div>
                </div>
                <div style="background:#f8fafc;border-radius:6px;padding:6px 10px;">
                    <div style="color:#94a3b8;font-size:10px;margin-bottom:2px;">WARN AT</div>
                    <div style="font-weight:600;color:#f59e0b;">${zone.warnAt} dB</div>
                </div>
                <div style="background:#f8fafc;border-radius:6px;padding:6px 10px;">
                    <div style="color:#94a3b8;font-size:10px;margin-bottom:2px;">CRIT AT</div>
                    <div style="font-weight:600;color:#ef4444;">${zone.critAt} dB</div>
                </div>
            </div>
        </div>`;
    }

    // ── Fetch zones and render/refresh markers ─────────────────
    function loadMapZones() {
        fetch(BASE_URL + "/api/zone_map.php", { cache: "no-store" })
            .then(r => r.json())
            .then(zones => {
                zones.forEach(zone => {
                    if (!zone.lat || !zone.lng) return;

                    if (markers[zone.id]) {
                        // Update existing marker
                        markers[zone.id].setIcon(buildIcon(zone));
                        markers[zone.id].setPopupContent(buildPopup(zone));
                    } else {
                        // Create new marker
                        const m = L.marker([zone.lat, zone.lng], { icon: buildIcon(zone) })
                            .addTo(map)
                            .bindPopup(buildPopup(zone), { maxWidth: 240, minWidth: 220 });
                        markers[zone.id] = m;
                    }
                });

                // Timestamp
                const el = document.getElementById("mapLastUpdate");
                if (el) {
                    el.textContent = new Date().toLocaleTimeString("en-PH", {
                        hour: "2-digit", minute: "2-digit", second: "2-digit"
                    });
                }
            })
            .catch(() => {});
    }

    // Initial load
    loadMapZones();

    // Refresh every 30 seconds (lightweight — matches alert badge polling)
    setInterval(loadMapZones, 30000);

    // Expose reset for the button
    window.resetZoneMap = function() {
        map.setView(MAP_CENTER, MAP_ZOOM);
    };
});

// ─────────────────────────────────────────────────────────────
//  LIVE ACTIVITY LOG WIDGET (Admin only)
// ─────────────────────────────────────────────────────────────
const actFeed = document.getElementById("activityFeed");
if (actFeed) {
    const ACTION_COLOR = {
        "Login": "#10d98e", "Logout": "#8892a4",
        "Add": "#4f8ef7",   "Edit": "#f5a623",
        "Delete": "#f44336","Clear": "#f44336",
        "Override": "#f5a623","View": "#8892a4",
        "Resolve": "#10d98e","Generated": "#4f8ef7",
        "Exported": "#4f8ef7","Updated": "#f5a623",
    };
    function actionColor(action) {
        for (const [k,v] of Object.entries(ACTION_COLOR)) {
            if (action.includes(k)) return v;
        }
        return "#8892a4";
    }
    function roleClass(role) {
        if (role === "Administrator")   return "background:rgba(79,142,247,.12);color:#1e40af;";
        if (role === "Library Manager") return "background:rgba(245,158,11,.1);color:#92400e;";
        return "background:rgba(0,0,0,.05);color:#525e72;";
    }
    function timeAgo(ts) {
        const diff = Math.floor((Date.now() - new Date(ts)) / 1000);
        if (diff < 60)   return diff + "s ago";
        if (diff < 3600) return Math.floor(diff/60) + "m ago";
        if (diff < 86400)return Math.floor(diff/3600) + "h ago";
        return new Date(ts).toLocaleDateString("en-PH");
    }

    let lastTs = null;

    function loadActivity() {
        const url = BASE_URL + "/api/activity_log.php?limit=12" + (lastTs ? "&since=" + encodeURIComponent(lastTs) : "");
        fetch(url, { cache: "no-store" })
            .then(r => r.json())
            .then(data => {
                // Update mini-stats
                const todayEl = document.getElementById("actToday");
                const usersEl = document.getElementById("actUsers");
                if (todayEl && data.stats) todayEl.innerHTML = "Today: <strong style=\"color:var(--blue-600)\">" + (data.stats.today||0) + "</strong>";
                if (usersEl && data.stats) usersEl.innerHTML = "Users: <strong style=\"color:var(--blue-600)\">" + (data.stats.unique_users||0) + "</strong>";

                if (!data.logs || !data.logs.length) {
                    if (!lastTs) actFeed.innerHTML = "<div style=\"text-align:center;padding:30px;color:var(--gray-300);font-size:13px;\">No activity yet.</div>";
                    return;
                }

                // First load — render all
                if (!lastTs) {
                    actFeed.innerHTML = "";
                    data.logs.forEach(renderRow);
                } else {
                    // Prepend new rows with flash
                    data.logs.forEach(log => {
                        const row = renderRow(log, true);
                        actFeed.insertBefore(row, actFeed.firstChild);
                        row.style.animation = "actFlash .8s ease";
                    });
                    // Keep max 12 rows
                    while (actFeed.children.length > 12) actFeed.removeChild(actFeed.lastChild);
                }
                lastTs = data.ts;
            })
            .catch(() => {});
    }

    function renderRow(log, returnEl) {
        const color = actionColor(log.action);
        const div = document.createElement("div");
        div.style.cssText = "display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-100);";
        div.innerHTML = \`
            <div style="width:32px;height:32px;border-radius:8px;background:\${color}18;border:1px solid \${color}40;
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;">
                \${log.action.includes("Login")?"🔑":log.action.includes("Delete")||log.action.includes("Clear")?"🗑":log.action.includes("Add")||log.action.includes("Create")?"➕":log.action.includes("Override")?"🎛":log.action.includes("Resolve")?"✅":log.action.includes("Export")?"📥":"📋"}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:2px;">
                    <span style="font-weight:600;font-size:13px;color:var(--gray-800);">\${log.user_name}</span>
                    <span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:5px;\${roleClass(log.user_role)}">\${log.user_role}</span>
                    <span style="font-size:11px;font-weight:600;color:\${color};background:\${color}12;padding:1px 7px;border-radius:5px;">\${log.action}</span>
                </div>
                <div style="font-size:11.5px;color:var(--gray-500);">\${log.detail||""}</div>
                <div style="display:flex;align-items:center;gap:10px;margin-top:3px;font-size:10.5px;color:var(--gray-300);">
                    <span>\${log.page||""}</span>
                    <span>\${log.ip||""}</span>
                    <span style="margin-left:auto;">\${timeAgo(log.created_at)}</span>
                </div>
            </div>\`;
        if (!returnEl) actFeed.appendChild(div);
        return div;
    }

    // CSS for new-row flash
    const style = document.createElement("style");
    style.textContent = "@keyframes actFlash { 0%{background:rgba(79,142,247,.12)} 100%{background:transparent} }";
    document.head.appendChild(style);

    loadActivity();
    setInterval(loadActivity, 15000);
}
</script>';

include __DIR__ . '/includes/layout_footer.php';
?>
