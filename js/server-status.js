/**
 * Ordo Draconis — Server Status Widget
 *
 * Sjednocený poller pro panel `.server-status` napříč všemi stránkami.
 * Backend: GET /api/status.php → { login, game, players, online }
 *
 * Očekávaná HTML struktura (i18n přes data-* atributy na .players):
 *
 *   <div class="server-status">
 *     <div class="status-line"><span class="dot dot-ls"></span><span class="label">Login Server</span></div>
 *     <div class="status-line"><span class="dot dot-gs"></span><span class="label">Game Server</span></div>
 *     <div class="status-players">
 *       <span class="players" data-label="hráčů" data-offline-label="Offline">—</span>
 *     </div>
 *   </div>
 */
(function () {
  'use strict';

  const ENDPOINT = '/api/status.php';
  const POLL_MS  = 10000;

  async function fetchStatus() {
    try {
      const res = await fetch(ENDPOINT, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return await res.json();
    } catch (err) {
      console.error('[server-status]', err);
      return { login: false, game: false, players: 0 };
    }
  }

  function applyStatus(data) {
    const wrap = document.querySelector('.server-status');
    if (!wrap) return;

    const lsDot     = wrap.querySelector('.dot-ls');
    const gsDot     = wrap.querySelector('.dot-gs');
    const playersEl = wrap.querySelector('.players');

    if (lsDot) lsDot.classList.toggle('on', !!data.login);
    if (gsDot) gsDot.classList.toggle('on', !!data.game);

    if (playersEl) {
      const label        = playersEl.dataset.label || 'players';
      const offlineLabel = playersEl.dataset.offlineLabel || 'Offline';
      if (data.game) {
        const n = (typeof data.players === 'number' && data.players >= 0) ? data.players : 0;
        playersEl.textContent = n + ' ' + label;
      } else {
        playersEl.textContent = offlineLabel;
      }
    }

    wrap.classList.toggle('all-online',  !!(data.login && data.game));
    wrap.classList.toggle('all-offline', !data.login && !data.game);
    wrap.classList.toggle('partial',     (!!data.login !== !!data.game));
  }

  async function tick() {
    const data = await fetchStatus();
    applyStatus(data);
  }

  function init() {
    if (!document.querySelector('.server-status')) return; // stránka panel nemá
    tick();
    setInterval(tick, POLL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
