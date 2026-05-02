// ============================================================
// dashboard.js
// Dashboard-specific logic: chart, countdown, zone map, activity log.
// Depends on: app.js (BASE_URL, Toast, refreshZoneLevels)
//             charts.js (renderNoiseChart)
//             Leaflet (window.L)
// PHP injects: CHART_DATA, CHART_LBLS, ZONE_NAMES, HAS_MAP, HAS_ACT_LOG
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Noise History Chart ───────────────────────────────────
    if (CHART_DATA.length && CHART_DATA[0].length) {
        const datasets = CHART_DATA.map((d, i) => ({
            label: ZONE_NAMES[i] || `Zone ${i + 1}`,
            data: d,
        }));
        setTimeout(() => renderNoiseChart('noiseChart', datasets, CHART_LBLS), 150);
    }

    // ── Simulation Countdown ──────────────────────────────────
    const nextReadEl = document.getElementById('nextRead');
    if (nextReadEl) {
        const INTERVAL = 7 * 60; // 420 s
        let remaining  = INTERVAL;

        const tick = () => {
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            nextReadEl.textContent = `${m}m ${String(s).padStart(2, '0')}s`;
            if (remaining-- <= 0) {
                remaining = INTERVAL;
                refreshZoneLevels();
            }
        };
        tick();
        setInterval(tick, 1000);
    }

    // ── Zone Map ──────────────────────────────────────────────
    if (HAS_MAP) initZoneMap();

    // ── Activity Log Widget ───────────────────────────────────
    if (HAS_ACT_LOG) initActivityFeed();
});

// ═════════════════════════════════════════════════════════════
//  ZONE MAP
// ═════════════════════════════════════════════════════════════
function initZoneMap() {
    const MAP_CENTER = [8.359282, 124.867826]; // NBSC Campus
    const MAP_ZOOM   = 20;

    const STATUS_COLOR = {
        safe:     '#059669',
        warning:  '#d97706',
        critical: '#dc2626',
    };
    const STATUS_PULSE = {
        safe:     '5,150,105',
        warning:  '217,119,6',
        critical: '220,38,38',
    };

    const map = L.map('zoneMap', {
        center: MAP_CENTER,
        zoom:   MAP_ZOOM,
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 22,
    }).addTo(map);

    const markers = {};

    function buildIcon(zone) {
        const color    = STATUS_COLOR[zone.status] || '#64748b';
        const pulseRgb = STATUS_PULSE[zone.status] || '100,116,139';
        const isCrit   = zone.status === 'critical';

        return L.divIcon({
            className: '',
            iconSize:   [52, 52],
            iconAnchor: [26, 26],
            html: `<div style="
                        width:52px;height:52px;border-radius:50%;
                        background:${color}20;
                        border:2.5px solid ${color};
                        display:flex;align-items:center;justify-content:center;
                        cursor:pointer;
                        box-shadow:0 0 0 4px ${color}18,0 4px 14px rgba(0,0,0,.2);
                        --pulse-rgb:${pulseRgb};
                   " class="${isCrit ? 'zone-marker-critical' : ''}">
                       <div style="text-align:center;line-height:1.1;">
                           <div style="font-weight:800;font-size:12px;color:${color};font-family:'Plus Jakarta Sans',sans-serif;">
                               ${zone.level.toFixed(0)}
                           </div>
                           <div style="font-size:8px;color:${color};opacity:.7;font-weight:600;">dB</div>
                       </div>
                   </div>`,
        });
    }

    function buildPopup(zone) {
        const color    = STATUS_COLOR[zone.status] || '#64748b';
        const batColor = zone.battery > 60 ? '#059669' : zone.battery > 30 ? '#d97706' : '#dc2626';
        return `
        <div style="padding:14px 16px 16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div>
                    <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;color:#0f172a;">${zone.name}</div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:1px;">${zone.floor} · ${zone.sensor}</div>
                </div>
                <span style="
                    background:${color}18;color:${color};
                    border:1px solid ${color}40;
                    font-size:10px;font-weight:700;padding:2px 8px;
                    border-radius:6px;text-transform:uppercase;letter-spacing:.4px;
                ">${zone.label}</span>
            </div>
            <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:28px;font-weight:800;color:${color};margin-bottom:12px;line-height:1;">
                ${zone.level.toFixed(1)}<span style="font-size:13px;font-weight:400;color:#94a3b8;"> dB</span>
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
                    <div style="font-weight:600;color:#d97706;">${zone.warnAt} dB</div>
                </div>
                <div style="background:#f8fafc;border-radius:6px;padding:6px 10px;">
                    <div style="color:#94a3b8;font-size:10px;margin-bottom:2px;">CRIT AT</div>
                    <div style="font-weight:600;color:#dc2626;">${zone.critAt} dB</div>
                </div>
            </div>
        </div>`;
    }

    function loadMapZones() {
        fetch(`${BASE_URL}/api/zone_map.php`, { cache: 'no-store' })
            .then(r => r.json())
            .then(zones => {
                zones.forEach(zone => {
                    if (!zone.lat || !zone.lng) return;

                    if (markers[zone.id]) {
                        markers[zone.id].setIcon(buildIcon(zone));
                        markers[zone.id].setPopupContent(buildPopup(zone));
                    } else {
                        markers[zone.id] = L.marker([zone.lat, zone.lng], { icon: buildIcon(zone) })
                            .addTo(map)
                            .bindPopup(buildPopup(zone), { maxWidth: 240, minWidth: 220 });
                    }
                });

                const el = document.getElementById('mapLastUpdate');
                if (el) {
                    el.textContent = new Date().toLocaleTimeString('en-PH', {
                        hour: '2-digit', minute: '2-digit', second: '2-digit',
                    });
                }
            })
            .catch(() => {});
    }

    loadMapZones();
    setInterval(loadMapZones, 30_000);

    // Exposed for the Reset button
    window.resetZoneMap = () => map.setView(MAP_CENTER, MAP_ZOOM);
}

// ═════════════════════════════════════════════════════════════
//  ACTIVITY LOG WIDGET
// ═════════════════════════════════════════════════════════════
function initActivityFeed() {
    const feed = document.getElementById('activityFeed');
    if (!feed) return;

    const todayVal = document.getElementById('actTodayVal');
    const usersVal = document.getElementById('actUsersVal');

    // Colour by first word of action label
    const ACTION_COLORS = {
        Login:     '#059669',
        Logout:    '#94a3b8',
        Add:       '#2563eb',
        Edit:      '#d97706',
        Delete:    '#dc2626',
        Clear:     '#dc2626',
        Override:  '#d97706',
        View:      '#94a3b8',
        Resolve:   '#059669',
        Generated: '#2563eb',
        Exported:  '#2563eb',
        Updated:   '#d97706',
        Register:  '#7c3aed',
    };

    function actionColor(action) {
        for (const [key, val] of Object.entries(ACTION_COLORS)) {
            if (action.includes(key)) return val;
        }
        return '#94a3b8';
    }

    function actionEmoji(action) {
        if (action.includes('Login'))    return '🔑';
        if (action.includes('Delete') || action.includes('Clear')) return '🗑';
        if (action.includes('Add') || action.includes('Register')) return '➕';
        if (action.includes('Override')) return '🎛';
        if (action.includes('Resolve'))  return '✅';
        if (action.includes('Export'))   return '📥';
        if (action.includes('Report'))   return '📊';
        return '📋';
    }

    function roleStyle(role) {
        if (role === 'Administrator')   return 'background:rgba(37,99,235,.10);color:#1d4ed8;';
        if (role === 'Library Manager') return 'background:rgba(217,119,6,.10);color:#92400e;';
        return 'background:rgba(0,0,0,.05);color:#64748b;';
    }

    function timeAgo(ts) {
        const diff = Math.floor((Date.now() - new Date(ts)) / 1000);
        if (diff < 60)    return `${diff}s ago`;
        if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return new Date(ts).toLocaleDateString('en-PH');
    }

    function buildRow(log) {
        const color = actionColor(log.action);
        const div   = document.createElement('div');
        div.className = 'act-row';
        div.innerHTML = `
            <div class="act-icon" style="background:${color}15;border:1px solid ${color}30;">
                ${actionEmoji(log.action)}
            </div>
            <div class="act-body">
                <div class="act-user">
                    ${log.user_name}
                    <span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:5px;margin-left:5px;${roleStyle(log.user_role)}">${log.user_role}</span>
                    <span style="font-size:11px;font-weight:600;padding:1px 7px;border-radius:5px;margin-left:3px;background:${color}12;color:${color};">${log.action}</span>
                </div>
                <div class="act-detail">${log.detail || ''}</div>
                <div class="act-footer">
                    ${log.page ? `<span>${log.page}</span>` : ''}
                    ${log.ip   ? `<span>${log.ip}</span>`   : ''}
                    <span class="act-time">${timeAgo(log.created_at)}</span>
                </div>
            </div>`;
        return div;
    }

    let lastTs = null;

    function loadActivity() {
        const url = `${BASE_URL}/api/activity_log.php?limit=12`
            + (lastTs ? `&since=${encodeURIComponent(lastTs)}` : '');

        fetch(url, { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                // Mini stats
                if (data.stats) {
                    if (todayVal) todayVal.textContent = data.stats.today || 0;
                    if (usersVal) usersVal.textContent = data.stats.unique_users || 0;
                }

                if (!data.logs?.length) {
                    if (!lastTs) {
                        feed.innerHTML = '<div class="act-loading">No activity yet.</div>';
                    }
                    return;
                }

                if (!lastTs) {
                    // First load — render all
                    feed.innerHTML = '';
                    data.logs.forEach(log => feed.appendChild(buildRow(log)));
                } else {
                    // Subsequent polls — prepend new rows with flash
                    data.logs.forEach(log => {
                        const row = buildRow(log);
                        row.style.animation = 'actFlash .8s ease';
                        feed.insertBefore(row, feed.firstChild);
                    });
                    // Cap at 12 rows
                    while (feed.children.length > 12) feed.removeChild(feed.lastChild);
                }

                lastTs = data.ts;
            })
            .catch(() => {});
    }

    loadActivity();
    setInterval(loadActivity, 15_000);
}
