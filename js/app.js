// ============================================================
// LQMS — app.js
// Core client-side logic: clock, sidebar, toast, alert polling
// ============================================================

const BASE_URL = (document.querySelector('[data-base]')?.dataset.base) || '/library-saba';

// ── Live Clock ────────────────────────────────────────────────
(function initClock() {
    const el = document.getElementById('liveClock');
    if (!el) return;
    const tick = () => {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-PH', {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    };
    tick();
    setInterval(tick, 1000);
})();

// ── Sidebar toggle (mobile) ───────────────────────────────────
(function initSidebar() {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (!toggle || !sidebar) return;
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (e) => {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
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

    function show(title, msg, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<div class="toast-title">${title}</div>
                           <div class="toast-msg">${msg}</div>`;
        wrap.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120%)';
            toast.style.transition = 'all .3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    return { show };
})();

// ── Active Alert Badge Polling ────────────────────────────────
// Polls every 30 seconds just to update the sidebar badge count.
// This is very lightweight — one tiny JSON call.
(function initAlertBadge() {
    const badge = document.getElementById('alertBadge');
    if (!badge) return;

    const update = () => {
        fetch(`${BASE_URL}/api/active_alerts_count.php`, { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    };

    update();
    setInterval(update, 30000); // every 30s — safe, very small payload
})();

// ── Zone Level Live Update ────────────────────────────────────
// Called from dashboard/zones page after simulation ticks.
// Refreshes zone cards without full page reload.
function refreshZoneLevels() {
    fetch(`${BASE_URL}/api/zone_levels.php`, { cache: 'no-store' })
        .then(r => r.json())
        .then(zones => {
            zones.forEach(z => {
                const card = document.querySelector(`[data-zone="${z.id}"]`);
                if (!card) return;

                const pct = Math.min((z.level / 90) * 100, 100);

                // Dashboard uses .db-bar-fill + .db-val
                const dbFill = card.querySelector('.db-bar-fill');
                const dbVal  = card.querySelector('.db-val');
                if (dbFill) {
                    dbFill.style.width = pct + '%';
                    dbFill.className = `db-bar-fill ${z.status}`;
                }
                if (dbVal) {
                    dbVal.textContent = parseFloat(z.level).toFixed(1) + ' dB';
                    dbVal.className = `db-val zone-db-num ${z.status}`;
                }

                // Zones page uses .zone-prog-fill + .zone-db-num
                const fill = card.querySelector('.zone-prog-fill');
                const num  = card.querySelector('.zone-db-num');
                if (fill) {
                    fill.style.width = pct + '%';
                    fill.className = `zone-prog-fill ${z.status}`;
                }
                if (num) {
                    num.textContent = parseFloat(z.level).toFixed(1);
                    num.className = `zone-db-num ${z.status}`;
                }

                // Update card status class
                if (card.classList.contains('zone-card')) {
                    card.className = `zone-card zone-${z.status}`;
                }

                // Fire toast on new critical
                if (z.status === 'critical' && card.dataset.lastStatus !== 'critical') {
                    Toast.show('⚠️ Noise Alert', `${z.name} exceeded critical threshold (${parseFloat(z.level).toFixed(1)} dB)`, 'crit', 8000);
                }
                card.dataset.lastStatus = z.status;
            });
        })
        .catch(() => {});
}

// ── dB Progress Bars (initial render) ────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Cover both dashboard (.db-bar-fill) and zones page (.zone-prog-fill)
    document.querySelectorAll('.zone-prog-fill[data-pct], .db-bar-fill[data-pct]').forEach(el => {
        const pct = parseFloat(el.dataset.pct) || 0;
        setTimeout(() => { el.style.width = pct + '%'; }, 80);
    });
});

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// Close modal on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open')
            .forEach(m => m.classList.remove('open'));
    }
});
