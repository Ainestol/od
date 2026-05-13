/* =====================================
   ORDO DRACONIS — ADMIN: Web Users
   Search + filters + inline metadata + tree drill-down
   ===================================== */
(() => {
  'use strict';

  // ----- state ---------------------------------------------------------
  const state = {
    q: '',
    filters: new Set(),       // active filter keys
    debounceTimer: null,
    inflight: null,           // current fetch promise (for cancel-newest semantics)
  };

  // ----- helpers -------------------------------------------------------
  function esc(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function relTime(ms) {
    if (!ms || ms <= 0) return 'nikdy';
    const diffSec = Math.max(0, Math.floor((Date.now() - ms) / 1000));
    if (diffSec < 60)        return 'právě teď';
    if (diffSec < 3600)      return Math.floor(diffSec / 60) + ' min zpět';
    if (diffSec < 86400)     return Math.floor(diffSec / 3600) + ' h zpět';
    if (diffSec < 86400 * 7) return Math.floor(diffSec / 86400) + ' dní zpět';
    const d = new Date(ms);
    return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear();
  }

  function premiumTag(endMs) {
    if (!endMs || endMs <= Date.now()) return null;
    const daysLeft = Math.ceil((endMs - Date.now()) / 86400000);
    let cls = 'danger';
    if (daysLeft >= 15) cls = 'success';
    else if (daysLeft >= 5) cls = 'warning';
    return { cls, label: 'Premium · ' + daysLeft + ' d' };
  }

  function dotOnline(isOnline) {
    return '<span class="dot ' + (isOnline ? 'on' : 'off') + '" title="' +
      (isOnline ? 'Online' : 'Offline') + '"></span>';
  }

  function formatDate(s) {
    if (!s) return '';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear();
  }

  function buildQuery() {
    const p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (state.filters.size) p.set('filter', [...state.filters].join(','));
    const s = p.toString();
    return s ? '?' + s : '';
  }

  // ----- rendering -----------------------------------------------------
  function renderUserHeader(u) {
    const badges = [];
    badges.push('<span class="badge role-' + u.role + '">' + esc(u.role) + '</span>');
    if (u.is_verified)   badges.push('<span class="badge ok">ověřen</span>');
    else                 badges.push('<span class="badge warn">neověřen</span>');
    if (u.twofa_enabled) badges.push('<span class="badge info">2FA</span>');
    if (u.premium_active) badges.push('<span class="tag success">Premium</span>');

    const meta = [];
    meta.push('🎮 ' + (u.game_account_count || 0) + ' účtů');
    meta.push('👥 ' + (u.character_count || 0) + ' postav');
    meta.push('🪙 ' + (u.vc_balance || 0) + ' VC');
    meta.push('💎 ' + (u.dc_balance || 0) + ' DC');
    meta.push('📅 reg. ' + formatDate(u.created_at));
    if (u.last_access_ms > 0) meta.push('⏱ ' + relTime(u.last_access_ms));

    return `
      <div class="tree-header">
        <button class="toggle-btn">▶</button>
        ${dotOnline(u.any_online)}
        <span class="tree-title">${esc(u.email)}</span>
        <span class="tree-id">#${u.id}</span>
        ${badges.join(' ')}
        <button class="btn btn-small" data-act="vip-web" data-id="${u.id}">VIP</button>
      </div>
      <div class="tree-meta">${meta.join(' · ')}</div>
      <div class="tree-children hidden"></div>
    `;
  }

  function renderGameAccountHeader(g) {
    const meta = [];
    if (g.is_primary) meta.push('<span class="badge info">primární</span>');
    meta.push('👥 ' + (g.character_count || 0) + ' postav');
    const pt = premiumTag(g.premium_end_ms);
    if (pt) meta.push('<span class="tag ' + pt.cls + '">' + pt.label + '</span>');
    if (g.last_access_ms > 0) meta.push('⏱ ' + relTime(g.last_access_ms));

    return `
      <div class="tree-header">
        <button class="toggle-btn">▶</button>
        ${dotOnline(g.any_online)}
        <span class="tree-title">${esc(g.login)}</span>
        <span class="tree-id">#${g.id}</span>
        <span class="tree-meta-inline">${meta.join(' · ')}</span>
        <button class="btn btn-small" data-act="vip-game" data-id="${g.id}">VIP</button>
      </div>
      <div class="tree-children hidden"></div>
    `;
  }

  function renderCharacter(c) {
    const meta = [];
    meta.push('Lv. ' + (c.level || '?'));
    if (c.classid) meta.push('Class #' + c.classid);
    if (c.lastAccess > 0) meta.push('⏱ ' + relTime(c.lastAccess));

    return `
      <span class="char-dot">${dotOnline(c.online)}</span>
      <span class="tree-title">${esc(c.char_name)}</span>
      <span class="tree-id">#${c.charId}</span>
      <span class="tree-meta-inline">${meta.join(' · ')}</span>
      <button class="btn btn-small" data-act="vip-char" data-id="${c.charId}">VIP</button>
    `;
  }

  // ----- data ----------------------------------------------------------
  async function loadUsers() {
    const container = document.getElementById('webUsersTree');
    const stats     = document.getElementById('usersStats');
    container.innerHTML = '<div class="muted">Načítám…</div>';
    stats.textContent = '';

    const myCall = Symbol();
    state.inflight = myCall;
    try {
      const res = await apiFetch('/api/admin/users_list.php' + buildQuery());
      // ignore stale response if newer request started
      if (state.inflight !== myCall) return;

      const users = res.data || [];
      stats.textContent = users.length + ' / ' + (res.total ?? users.length);
      container.innerHTML = '';

      if (!users.length) {
        container.innerHTML = '<div class="muted">Žádný uživatel neodpovídá filtru.</div>';
        return;
      }

      users.forEach(u => {
        const box = document.createElement('div');
        box.className = 'tree-web';
        box.dataset.userId = u.id;
        box.innerHTML = renderUserHeader(u);
        container.appendChild(box);
      });
    } catch (e) {
      console.error(e);
      container.innerHTML = '<div class="muted">Chyba načítání: ' + esc(e.message) + '</div>';
    }
  }

  async function toggleGameAccounts(webUserId, headerToggleBtn, childrenBox) {
    if (!childrenBox.classList.contains('hidden')) {
      childrenBox.classList.add('hidden');
      childrenBox.innerHTML = '';
      headerToggleBtn.textContent = '▶';
      return;
    }
    headerToggleBtn.textContent = '▼';
    childrenBox.classList.remove('hidden');
    childrenBox.innerHTML = '<div class="muted">Načítám…</div>';

    try {
      const res = await apiFetch('/api/admin/game_accounts_list.php?webUserId=' + webUserId);
      childrenBox.innerHTML = '';
      const accounts = res.data || [];
      if (!accounts.length) {
        childrenBox.innerHTML = '<div class="muted">Žádný game účet.</div>';
        return;
      }
      accounts.forEach(g => {
        const gameBox = document.createElement('div');
        gameBox.className = 'tree-game';
        gameBox.dataset.accountId = g.id;
        gameBox.innerHTML = renderGameAccountHeader(g);
        childrenBox.appendChild(gameBox);
      });
    } catch (e) {
      console.error(e);
      childrenBox.innerHTML = '<div class="muted">Chyba: ' + esc(e.message) + '</div>';
    }
  }

  async function toggleCharacters(gameAccountId, headerToggleBtn, childrenBox) {
    if (!childrenBox.classList.contains('hidden')) {
      childrenBox.classList.add('hidden');
      childrenBox.innerHTML = '';
      headerToggleBtn.textContent = '▶';
      return;
    }
    headerToggleBtn.textContent = '▼';
    childrenBox.classList.remove('hidden');
    childrenBox.innerHTML = '<div class="muted">Načítám…</div>';

    try {
      const res = await apiFetch('/api/admin/characters_list.php?gameAccountId=' + gameAccountId);
      childrenBox.innerHTML = '';
      const chars = res.data || [];
      if (!chars.length) {
        childrenBox.innerHTML = '<div class="muted">Žádná postava.</div>';
        return;
      }
      chars.forEach(c => {
        const charBox = document.createElement('div');
        charBox.className = 'tree-char';
        charBox.innerHTML = renderCharacter(c);
        childrenBox.appendChild(charBox);
      });
    } catch (e) {
      console.error(e);
      childrenBox.innerHTML = '<div class="muted">Chyba: ' + esc(e.message) + '</div>';
    }
  }

  // ----- VIP action (kept for backward compat with vip.html) -----------
  function openVip(scope, id) {
    window.location.href = '/admin/vip.html?scope=' + scope + '&targetId=' + id;
  }

  // ----- events --------------------------------------------------------
  function bindToolbar() {
    const input   = document.getElementById('usersSearch');
    const clear   = document.getElementById('usersSearchClear');
    const filters = document.getElementById('usersFilters');

    input.addEventListener('input', () => {
      clearTimeout(state.debounceTimer);
      state.debounceTimer = setTimeout(() => {
        state.q = input.value.trim();
        loadUsers();
      }, 250);
    });

    clear.addEventListener('click', () => {
      input.value = '';
      state.q = '';
      loadUsers();
      input.focus();
    });

    filters.addEventListener('click', e => {
      const chip = e.target.closest('.chip');
      if (!chip) return;
      const key = chip.dataset.filter;
      if (state.filters.has(key)) {
        state.filters.delete(key);
        chip.classList.remove('active');
      } else {
        state.filters.add(key);
        chip.classList.add('active');
      }
      loadUsers();
    });
  }

  function bindTreeDelegation() {
    const tree = document.getElementById('webUsersTree');

    tree.addEventListener('click', e => {
      // toggle web → game
      const toggleBtn = e.target.closest('.toggle-btn');
      if (toggleBtn) {
        const webBox = toggleBtn.closest('.tree-web');
        if (webBox) {
          const childrenBox = webBox.querySelector(':scope > .tree-children');
          if (childrenBox) {
            toggleGameAccounts(webBox.dataset.userId, toggleBtn, childrenBox);
            return;
          }
        }
        // toggle game → characters
        const gameBox = toggleBtn.closest('.tree-game');
        if (gameBox) {
          const childrenBox = gameBox.querySelector(':scope > .tree-children');
          if (childrenBox) {
            toggleCharacters(gameBox.dataset.accountId, toggleBtn, childrenBox);
            return;
          }
        }
      }

      // VIP buttons
      const act = e.target.closest('button[data-act]');
      if (act) {
        const id = parseInt(act.dataset.id, 10);
        if (!id) return;
        if (act.dataset.act === 'vip-web')  openVip('WEB',  id);
        if (act.dataset.act === 'vip-game') openVip('GAME', id);
        if (act.dataset.act === 'vip-char') openVip('CHAR', id);
      }
    });
  }

  // ----- init ----------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    bindToolbar();
    bindTreeDelegation();
    loadUsers();
  });

  // expose for legacy in-HTML handlers (none currently, just safe)
  window.openVip = openVip;
})();
