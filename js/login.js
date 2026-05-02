// ============================================================
//  login.js
// Auth page behaviour: clock, tab switching, password eye
// toggle, password strength meter, form loading states.
// ============================================================

// ── Live clock ────────────────────────────────────────────────
(function initClock() {
    const tick = () => {
        const t = new Date().toLocaleTimeString('en-PH', {
            hour: '2-digit', minute: '2-digit', second: '2-digit',
        });
        document.querySelectorAll('.auth-clock').forEach(el => el.textContent = t);
    };
    tick();
    setInterval(tick, 1000);
})();

// ── Tab switching ─────────────────────────────────────────────
function switchTab(tab) {
    ['login', 'register'].forEach(t => {
        const panel = document.getElementById(`authPanel_${t}`);
        const btn   = document.getElementById(`authTab_${t}`);
        if (panel) panel.classList.toggle('active', t === tab);
        if (btn)   btn.classList.toggle('active',   t === tab);
    });
}

// ── Password eye toggle ───────────────────────────────────────
const EYE_OPEN = `
    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
    <circle cx="12" cy="12" r="3"/>`;

const EYE_CLOSED = `
    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/>
    <path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/>
    <line x1="1" y1="1" x2="23" y2="23"/>`;

function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    const showing = input.type === 'password';
    input.type    = showing ? 'text' : 'password';
    icon.innerHTML = showing ? EYE_CLOSED : EYE_OPEN;
}

// ── Password strength meter ───────────────────────────────────
const STRENGTH_LEVELS = [
    { pct: '20%', color: 'var(--crit)',      text: 'Very weak'   },
    { pct: '40%', color: 'var(--warn)',      text: 'Weak'        },
    { pct: '60%', color: 'var(--warn-mid)',  text: 'Fair'        },
    { pct: '80%', color: 'var(--safe)',      text: 'Strong'      },
    { pct: '100%',color: 'var(--safe)',      text: 'Very strong' },
];

function checkStrength(val) {
    const bar   = document.getElementById('strengthBarFill');
    const label = document.getElementById('strengthLabel');
    if (!bar || !label) return;

    if (!val) {
        bar.style.width     = '0';
        label.textContent   = '';
        return;
    }

    let score = 0;
    if (val.length >= 6)             score++;
    if (val.length >= 10)            score++;
    if (/[A-Z]/.test(val))           score++;
    if (/[0-9]/.test(val))           score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    const level = STRENGTH_LEVELS[Math.min(score, 4)];
    bar.style.width      = level.pct;
    bar.style.background = level.color;
    label.style.color    = level.color;
    label.textContent    = level.text;
}

// ── Form submit loading states ────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            document.getElementById('loginBtn')?.classList.add('loading');
        });
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            const pw  = document.getElementById('reg_password')?.value;
            const cfm = document.getElementById('reg_confirm')?.value;
            if (pw !== cfm) {
                e.preventDefault();
                // Show inline error instead of alert()
                const errEl = document.getElementById('registerMatchError');
                if (errEl) errEl.style.display = 'flex';
                return;
            }
            document.getElementById('registerBtn')?.classList.add('loading');
        });

        // Live confirm-match feedback
        const confirmInput = document.getElementById('reg_confirm');
        const pwInput      = document.getElementById('reg_password');
        if (confirmInput && pwInput) {
            confirmInput.addEventListener('input', () => {
                const errEl = document.getElementById('registerMatchError');
                if (!errEl) return;
                const mismatch = confirmInput.value.length > 0 && confirmInput.value !== pwInput.value;
                errEl.style.display = mismatch ? 'flex' : 'none';
            });
        }
    }
});
