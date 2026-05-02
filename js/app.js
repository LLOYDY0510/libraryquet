// ============================================================
//  app.js
// Core client-side logic: BASE_URL, clock, sidebar, toasts,
// alert badge polling, zone level refresh, modal helpers.
// ============================================================

// ── BASE_URL ──────────────────────────────────────────────────
// Read from the <meta name="base-url"> tag injected by layout.php.
// This avoids both the fragile data-attribute hack and the hardcoded
// fallback string that would break on re-deployment.
const BASE_URL = (() => {
    const meta = document.querySelector('meta[name="base-url"]');
    return meta ? meta.getAttribute('content') : '';
})();

// ── Live Clock ────────────────────────────────────────────────
(function initClock() {
    const el = document.getElementById('liveClock');
    if (!el) return;
    const tick = () => {
        el.textContent = new Date().toLocaleTimeString('en-PH', {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    };
    tick();
    setInterval(tick, 1000);
})();

// ── Sidebar Toggle (mobile) ───────────────────────────────────
(function initSidebar() {
    const toggle  = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
        if (sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
})();

// ── Toast System ──────────────────────────────────────────────
const Toast = (() => {
    let wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'toast-wrap';
        document.body.appendChild(wrap);
    }

    /**
     * @param {string} title
     * @param {string} msg
     * @param {'info'|'warn'|'crit'|'ok'|'safe'} type
     * @param {number} duration  milliseconds
     */
    function show(title, msg, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML =
            `<div class="toast-title">${title}</div>
             <div class="toast-msg">${msg}</div>`;
        wrap.appendChild(toast);
        setTimeout(() => {
            toast.style.transition = 'opacity .3s, transform .3s';
            toast.style.opacity    = '0';
            toast.style.transform  = 'translateX(110%)';
            setTimeout(() => toast.remove(), 320);
        }, duration);
    }

    return { show };
})();

// ── Alert Badge Polling ───────────────────────────────────────
// Lightweight: one tiny JSON request every 30 s.
(function initAlertBadge() {
    const badge = document.getElementById('alertBadge');
    if (!badge) return;

    const update = () => {
        fetch(`${BASE_URL}/api/active_alerts_count.php`, { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (data.count > 0) {
                    badge.textContent     = data.count;
                    badge.style.display   = 'flex';
                } else {
                    badge.style.display   = 'none';
                }
            })
            .catch(() => {});
    };

    update();
    setInterval(update, 30_000);
})();

// ── Zone Level Live Refresh ───────────────────────────────────
// Called by the dashboard countdown and zones page after each
// simulation tick. Updates DOM without a full page reload.
function refreshZoneLevels() {
    fetch(`${BASE_URL}/api/zone_levels.php`, { cache: 'no-store' })
        .then(r => r.json())
        .then(zones => {
            zones.forEach(z => {
                const card = document.querySelector(`[data-zone="${z.id}"]`);
                if (!card) return;

                const pct = Math.min((z.level / 90) * 100, 100);

                // Dashboard list uses .db-bar-fill + .db-val
                const dbFill = card.querySelector('.db-bar-fill');
                const dbVal  = card.querySelector('.db-val');
                if (dbFill) {
                    dbFill.style.width = `${pct}%`;
                    dbFill.className   = `db-bar-fill ${z.status}`;
                }
                if (dbVal) {
                    dbVal.textContent = `${parseFloat(z.level).toFixed(1)} dB`;
                    dbVal.className   = `db-val zone-db-num ${z.status}`;
                }

                // Zones page cards use .zone-prog-fill + .zone-db-num
                const fill = card.querySelector('.zone-prog-fill');
                const num  = card.querySelector('.zone-db-num');
                if (fill) {
                    fill.style.width = `${pct}%`;
                    fill.className   = `zone-prog-fill ${z.status}`;
                }
                if (num) {
                    num.textContent = parseFloat(z.level).toFixed(1);
                    num.className   = `zone-db-num ${z.status}`;
                }

                // Keep zone-card border colour in sync
                if (card.classList.contains('zone-card')) {
                    card.className = `zone-card zone-${z.status}`;
                }

                // Fire toast on newly-critical zone
                if (z.status === 'critical' && card.dataset.lastStatus !== 'critical') {
                    Toast.show(
                        '⚠️ Noise Alert',
                        `${z.name} exceeded critical threshold (${parseFloat(z.level).toFixed(1)} dB)`,
                        'crit',
                        8000
                    );
                }
                card.dataset.lastStatus = z.status;
            });
        })
        .catch(() => {});
}

// ── Progress Bar Initial Render ───────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.zone-prog-fill[data-pct], .db-bar-fill[data-pct]')
        .forEach(el => {
            const pct = parseFloat(el.dataset.pct) || 0;
            // Tiny delay lets the browser paint 0-width first for the transition
            setTimeout(() => { el.style.width = `${pct}%`; }, 80);
        });
});

// ── Modal Helpers ─────────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// Close on backdrop click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// Close on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open')
            .forEach(m => m.classList.remove('open'));
    }
});
