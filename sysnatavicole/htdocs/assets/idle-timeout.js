(() => {
  'use strict';
  const idleLimit = 5 * 60 * 1000;
  let lastActivity = Date.now();
  let expiryTimer;
  let pingTimer;
  const expire = () => window.location.replace('logout.php?idle=1');

  function scheduleExpiry() {
    window.clearTimeout(expiryTimer);
    expiryTimer = window.setTimeout(expire, Math.max(0, idleLimit - (Date.now() - lastActivity)));
  }

  function noteActivity() {
    lastActivity = Date.now();
    scheduleExpiry();
  }

  function pingSession() {
    if (document.hidden || Date.now() - lastActivity > 40000) return;
    fetch('session_ping.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(response => { if (response.status === 401) expire(); })
      .catch(() => {});
  }

  ['pointerdown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(eventName => {
    window.addEventListener(eventName, noteActivity, { passive: true });
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) return;
    if (Date.now() - lastActivity >= idleLimit) expire();
    else pingSession();
  });

  noteActivity();
  pingTimer = window.setInterval(pingSession, 30 * 1000);
  window.addEventListener('pagehide', () => {
    window.clearTimeout(expiryTimer);
    window.clearInterval(pingTimer);
  }, { once: true });
})();
