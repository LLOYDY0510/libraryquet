// ============================================================
// LQMS — charts.js
// Noise history line chart using Canvas API (no heavy deps)
// ============================================================

function renderNoiseChart(canvasId, datasets, labels) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth;
    const H = canvas.offsetHeight;
    canvas.width  = W * devicePixelRatio;
    canvas.height = H * devicePixelRatio;
    ctx.scale(devicePixelRatio, devicePixelRatio);

    const padL = 40, padR = 16, padT = 16, padB = 32;
    const chartW = W - padL - padR;
    const chartH = H - padT - padB;
    const maxVal = 90; // max dB scale
    const steps  = 5;

    const colors = ['#3b82f6', '#f59e0b', '#10b981'];

    // Grid + Y labels
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    ctx.fillStyle = '#94a3b8';
    ctx.font = '10px DM Sans, sans-serif';
    ctx.textAlign = 'right';

    for (let i = 0; i <= steps; i++) {
        const y = padT + (chartH / steps) * i;
        const val = maxVal - (maxVal / steps) * i;
        ctx.beginPath();
        ctx.moveTo(padL, y);
        ctx.lineTo(padL + chartW, y);
        ctx.stroke();
        ctx.fillText(val.toFixed(0) + ' dB', padL - 4, y + 3);
    }

    // Threshold lines
    const drawThreshold = (val, color, label) => {
        const y = padT + chartH - (val / maxVal) * chartH;
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.5;
        ctx.setLineDash([4, 4]);
        ctx.beginPath();
        ctx.moveTo(padL, y);
        ctx.lineTo(padL + chartW, y);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = color;
        ctx.textAlign = 'left';
        ctx.font = '9px DM Sans, sans-serif';
        ctx.fillText(label, padL + 4, y - 3);
        ctx.restore();
    };

    drawThreshold(60, '#f59e0b', 'WARN 60dB');
    drawThreshold(75, '#ef4444', 'CRIT 75dB');

    // X labels
    if (labels && labels.length) {
        ctx.fillStyle = '#94a3b8';
        ctx.font = '9px DM Sans, sans-serif';
        ctx.textAlign = 'center';
        const step = Math.max(1, Math.floor(labels.length / 6));
        labels.forEach((lbl, i) => {
            if (i % step !== 0) return;
            const x = padL + (i / (labels.length - 1)) * chartW;
            ctx.fillText(lbl, x, padT + chartH + 18);
        });
    }

    // Lines
    datasets.forEach((ds, di) => {
        if (!ds.data || ds.data.length < 2) return;
        const color = colors[di % colors.length];
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.lineCap  = 'round';

        // Fill gradient
        const grad = ctx.createLinearGradient(0, padT, 0, padT + chartH);
        grad.addColorStop(0, color + '30');
        grad.addColorStop(1, color + '00');

        ctx.beginPath();
        ds.data.forEach((val, i) => {
            const x = padL + (i / (ds.data.length - 1)) * chartW;
            const y = padT + chartH - (val / maxVal) * chartH;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();

        // Fill
        ctx.lineTo(padL + chartW, padT + chartH);
        ctx.lineTo(padL, padT + chartH);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();

        ctx.restore();
    });

    // Legend
    datasets.forEach((ds, di) => {
        const color = colors[di % colors.length];
        const lx = padL + di * 110;
        const ly = padT + chartH + 26;
        ctx.fillStyle = color;
        ctx.fillRect(lx, ly - 6, 12, 4);
        ctx.fillStyle = '#64748b';
        ctx.font = '10px DM Sans, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(ds.label || 'Zone', lx + 16, ly);
    });
}
