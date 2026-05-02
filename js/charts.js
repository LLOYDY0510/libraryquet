// ============================================================
// charts.js
// Noise history line chart using Canvas API (no Chart.js dep).
// Called by dashboard.js: renderNoiseChart(canvasId, datasets, labels)
//
// Changes from original:
//   - Font updated from 'DM Sans' to 'Plus Jakarta Sans' to match
//     the rest of the app (main.css --font-body)
//   - Threshold lines now accept optional warn/crit values via
//     renderNoiseChart(id, datasets, labels, warnAt, critAt)
//     instead of being hardcoded to 60/75 dB
//   - CRLF → LF line endings
// ============================================================

/**
 * @param {string}   canvasId    - ID of the <canvas> element
 * @param {Array}    datasets    - [{ label, data: [number] }, ...]
 * @param {Array}    labels      - X-axis tick labels (time strings)
 * @param {number}   [warnAt=40] - Warning threshold line (dB)
 * @param {number}   [critAt=60] - Critical threshold line (dB)
 */
function renderNoiseChart(canvasId, datasets, labels, warnAt = 40, critAt = 60) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth;
    const H = canvas.offsetHeight;
    canvas.width  = W * devicePixelRatio;
    canvas.height = H * devicePixelRatio;
    ctx.scale(devicePixelRatio, devicePixelRatio);

    const padL = 44, padR = 16, padT = 16, padB = 36;
    const chartW = W - padL - padR;
    const chartH = H - padT - padB;
    const maxVal = 90; // dB scale ceiling
    const steps  = 5;
    const FONT   = 'Plus Jakarta Sans, sans-serif';

    const colors = ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6'];

    // ── Grid + Y axis labels ──────────────────────────────────
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth   = 1;
    ctx.fillStyle   = '#94a3b8';
    ctx.font        = `10px ${FONT}`;
    ctx.textAlign   = 'right';

    for (let i = 0; i <= steps; i++) {
        const y   = padT + (chartH / steps) * i;
        const val = maxVal - (maxVal / steps) * i;
        ctx.beginPath();
        ctx.moveTo(padL, y);
        ctx.lineTo(padL + chartW, y);
        ctx.stroke();
        ctx.fillText(val.toFixed(0) + ' dB', padL - 5, y + 3);
    }

    // ── Threshold lines ───────────────────────────────────────
    const drawThreshold = (val, color, label) => {
        const y = padT + chartH - (val / maxVal) * chartH;
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth   = 1.5;
        ctx.setLineDash([4, 4]);
        ctx.beginPath();
        ctx.moveTo(padL, y);
        ctx.lineTo(padL + chartW, y);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle  = color;
        ctx.textAlign  = 'left';
        ctx.font       = `9px ${FONT}`;
        ctx.fillText(label, padL + 4, y - 3);
        ctx.restore();
    };

    drawThreshold(warnAt, '#f59e0b', `WARN ${warnAt}dB`);
    drawThreshold(critAt, '#ef4444', `CRIT ${critAt}dB`);

    // ── X axis labels ─────────────────────────────────────────
    if (labels && labels.length) {
        ctx.fillStyle = '#94a3b8';
        ctx.font      = `9px ${FONT}`;
        ctx.textAlign = 'center';
        const step = Math.max(1, Math.floor(labels.length / 6));
        labels.forEach((lbl, i) => {
            if (i % step !== 0) return;
            const x = padL + (i / Math.max(labels.length - 1, 1)) * chartW;
            ctx.fillText(lbl, x, padT + chartH + 18);
        });
    }

    // ── Dataset lines + fills ─────────────────────────────────
    datasets.forEach((ds, di) => {
        if (!ds.data || ds.data.length < 2) return;
        const color = colors[di % colors.length];
        const pts   = ds.data;
        ctx.save();

        // Gradient fill under the line
        const grad = ctx.createLinearGradient(0, padT, 0, padT + chartH);
        grad.addColorStop(0, color + '28');
        grad.addColorStop(1, color + '00');

        // Build path once, use for stroke + fill
        ctx.beginPath();
        pts.forEach((val, i) => {
            const x = padL + (i / Math.max(pts.length - 1, 1)) * chartW;
            const y = padT + chartH - Math.min(val / maxVal, 1) * chartH;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });

        // Stroke
        ctx.strokeStyle = color;
        ctx.lineWidth   = 2;
        ctx.lineJoin    = 'round';
        ctx.lineCap     = 'round';
        ctx.stroke();

        // Fill area below line
        ctx.lineTo(padL + chartW, padT + chartH);
        ctx.lineTo(padL,          padT + chartH);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();

        // Dots on data points
        ctx.fillStyle = color;
        pts.forEach((val, i) => {
            const x = padL + (i / Math.max(pts.length - 1, 1)) * chartW;
            const y = padT + chartH - Math.min(val / maxVal, 1) * chartH;
            ctx.beginPath();
            ctx.arc(x, y, 3, 0, Math.PI * 2);
            ctx.fill();
        });

        ctx.restore();
    });

    // ── Legend (drawn at bottom of canvas) ───────────────────
    datasets.forEach((ds, di) => {
        const color = colors[di % colors.length];
        const lx    = padL + di * 110;
        const ly    = padT + chartH + 30;
        ctx.fillStyle = color;
        ctx.fillRect(lx, ly - 6, 12, 4);
        ctx.fillStyle = '#64748b';
        ctx.font      = `10px ${FONT}`;
        ctx.textAlign = 'left';
        ctx.fillText(ds.label || `Zone ${di + 1}`, lx + 16, ly);
    });
}
