/**
 * SARI-POS — Global Application JS
 * Pusher init, toast notifications, live clock, modal helpers.
 */
'use strict';

window.posApp = {};

// ── Pusher Real-Time ──────────────────────────────────────
(function initPusher() {
  const cfg = window.PUSHER_CONFIG;
  if (!cfg || !cfg.key || cfg.key === 'your_pusher_app_key') {
    setRtStatus('offline');
    return;
  }
  try {
    Pusher.logToConsole = false;
    const pusher = new Pusher(cfg.key, { cluster: cfg.cluster, encrypted: true });
    window.posApp.pusher = pusher;
    pusher.connection.bind('connected',    () => setRtStatus('connected'));
    pusher.connection.bind('disconnected', () => setRtStatus('offline'));
    pusher.connection.bind('error',        () => setRtStatus('error'));
  } catch (e) {
    console.warn('Pusher init failed:', e);
    setRtStatus('error');
  }
})();

function setRtStatus(status) {
  const dot   = document.getElementById('rtDot');
  const label = document.getElementById('rtLabel');
  if (!dot || !label) return;
  dot.className = 'rt-dot ' + (status === 'connected' ? 'connected' : status === 'error' ? 'error' : '');
  label.textContent = { connected: 'Live', offline: 'Offline', error: 'Error' }[status] || status;
}

// ── Live Clock ────────────────────────────────────────────
function updateClock() {
  const el = document.getElementById('topClock');
  if (el) el.textContent = new Date().toLocaleTimeString('en-PH');
}
setInterval(updateClock, 1000);
updateClock();

// ── Toast Notifications ───────────────────────────────────
const TOAST_ICONS = {
  success: 'fas fa-check-circle',
  danger:  'fas fa-times-circle',
  warning: 'fas fa-exclamation-circle',
  info:    'fas fa-info-circle',
};

function showToast(message, type = 'success', duration = 3200) {
  const stack = document.getElementById('toasts');
  if (!stack) return;
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="${TOAST_ICONS[type] || TOAST_ICONS.info}" aria-hidden="true"></i><span>${escHtml(String(message))}</span>`;
  stack.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = '.3s'; setTimeout(() => t.remove(), 320); }, duration);
}
window.showToast = showToast;

// ── Modal Backdrop Close ──────────────────────────────────
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-bg')) {
    e.target.classList.remove('show');
  }
});

// ── ESC Close Modal ───────────────────────────────────────
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-bg.show').forEach(m => m.classList.remove('show'));
  }
});

// ── F2 Focus Search ───────────────────────────────────────
document.addEventListener('keydown', function (e) {
  if (e.key === 'F2') {
    e.preventDefault();
    const s = document.getElementById('posSearch') || document.getElementById('prodSearch');
    if (s) { s.focus(); s.select(); }
  }
});

// ── XSS-safe HTML escape (for JS use) ────────────────────
function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
window.escHtml = escHtml;
