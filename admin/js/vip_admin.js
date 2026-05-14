/* =====================================
   ORDO DRACONIS — ADMIN: VIP / Premium správa
   Target picker + level dropdown + presets + preview
   ===================================== */
(() => {
  'use strict';

  // ----- state ---------------------------------------------------------
  const state = {
    scope: 'WEB',
    target: null,           // { id, label, context, extra, existing_grant }
    levelId: null,
    days: 30,
    customDays: false,

    // Cascade picker: when admin picks a WEB user, we remember them
    // so that switching to GAME shows only their accounts.
    webContext: null,       // null | { id, email }

    levels: [],             // [{level_id, name, is_active}]
    prices: { WEB: {}, GAME: {}, CHAR: {} },

    list: [],
    listScope: 'all',       // all | WEB | GAME | CHAR | soon
    showExpired: false,

    searchResults: [],
    debounceTimer: null,
  };

  // ----- helpers -------------------------------------------------------
  function esc(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear()
         + ' ' + String(d.getHours()).padStart(2, '0')
         + ':' + String(d.getMinutes()).padStart(2, '0');
  }

  function toast(msg, kind = 'ok') {
    const t = document.getElementById('vipToast');
    t.className = 'vip-toast show ' + (kind === 'err' ? 'err' : 'ok');
    t.textContent = msg;
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => t.classList.remove('show'), 3500);
  }

  // ----- init ---------------------------------------------------------
  async function init() {
    if (typeof initCsrf === 'function') await initCsrf();

    await loadLevels();
    bindScope();
    bindLevel();
    bindDays();
    bindTargetSearch();
    bindAddButton();
    bindListFilters();
    bindModal();
    bindGrantListActions();

    // Pre-fill from URL ?scope=&targetId=
    const params = new URLSearchParams(window.location.search);
    const urlScope = params.get('scope');
    const urlId    = params.get('targetId');
    if (urlScope && urlId) {
      const upper = urlScope.toUpperCase();
      if (upper === 'CHAR') {
        // Legacy URL (users.html → "VIP postavy" button). CHAR scope is deprecated;
        // explain and leave form on default WEB scope.
        toast('CHAR scope je legacy. Pro Premium 24h použij GAME + VIP I + 1 den.', 'err');
      } else if (upper === 'WEB' || upper === 'GAME') {
        state.scope = upper;
        applyScopeUI();
        await prefillTarget(state.scope, urlId);
      }
    }

    updatePreview();
    await loadList();
  }

  // ----- levels -------------------------------------------------------
  async function loadLevels() {
    try {
      const res = await apiFetch('/api/admin/vip_levels_list.php');
      state.levels = res.levels || [];
      state.prices = res.prices || { WEB:{}, GAME:{}, CHAR:{} };
      renderLevels();
    } catch (e) {
      console.error(e);
      toast('Nelze načíst seznam levelů: ' + e.message, 'err');
    }
  }

  function renderLevels() {
    const c = document.getElementById('levelSeg');
    c.innerHTML = state.levels.map(l =>
      `<button type="button" data-level-id="${l.level_id}">${esc(l.name)}</button>`
    ).join('');
    state.levelId = pickDefaultLevelForScope(state.scope);
    applyLevelUI();
    updateLevelHint();
  }

  function pickDefaultLevelForScope(scope) {
    const keys = Object.keys(state.prices[scope] || {}).map(Number);
    if (keys.length) return keys[0];
    return state.levels.length ? state.levels[0].level_id : null;
  }

  function applyLevelUI() {
    document.querySelectorAll('#levelSeg button').forEach(b => {
      b.classList.toggle('active', parseInt(b.dataset.levelId, 10) === state.levelId);
    });
  }

  function updateLevelHint() {
    const hint = document.getElementById('levelHint');
    const p = (state.prices[state.scope] || {})[state.levelId];
    if (p) {
      hint.textContent = `Doporučená cena v shopu: ${p.price} ${p.currency} za ${p.duration_days} dní`;
    } else {
      hint.textContent = '';
    }
  }

  function bindLevel() {
    document.getElementById('levelSeg').addEventListener('click', e => {
      const btn = e.target.closest('button[data-level-id]');
      if (!btn) return;
      state.levelId = parseInt(btn.dataset.levelId, 10);
      applyLevelUI();
      updateLevelHint();
      updatePreview();
    });
  }

  // ----- scope -------------------------------------------------------
  function bindScope() {
    document.getElementById('scopeSeg').addEventListener('click', e => {
      const btn = e.target.closest('button[data-scope]');
      if (!btn || btn.dataset.scope === state.scope) return;
      const newScope = btn.dataset.scope;
      // If switching back to WEB, the cascade context no longer makes sense
      if (newScope === 'WEB') state.webContext = null;
      state.scope = newScope;
      state.target = null;
      applyScopeUI();
      applyContextBar();
      hideTargetChip();
      document.getElementById('vipTargetSearch').value = '';
      hideAutocomplete();
      state.levelId = pickDefaultLevelForScope(state.scope);
      applyLevelUI();
      updateLevelHint();
      updatePreview();
    });
  }

  function applyScopeUI() {
    document.querySelectorAll('#scopeSeg button').forEach(b => {
      b.classList.toggle('active', b.dataset.scope === state.scope);
    });
    const map = {
      WEB:  ['Pro koho (e-mail)',       'Začni psát e-mail nebo web ID…'],
      GAME: ['Pro koho (game účet)',    'Začni psát login game účtu nebo ID…'],
      CHAR: ['Pro koho (postava)',      'Začni psát jméno postavy nebo charId…'],
    };
    const [lbl, ph] = map[state.scope];
    document.getElementById('targetLabel').textContent = lbl;
    const input = document.getElementById('vipTargetSearch');
    input.placeholder = ph;
  }

  // ----- target search -----------------------------------------------
  function bindTargetSearch() {
    const input = document.getElementById('vipTargetSearch');
    const ac    = document.getElementById('vipAutocomplete');

    input.addEventListener('input', () => {
      clearTimeout(state.debounceTimer);
      const q = input.value.trim();
      const ctxId = cascadeWebUserId();
      if (q.length < 1) {
        // No query: if cascade context active for GAME, show browse-mode dropdown
        if (ctxId > 0 && state.scope === 'GAME') {
          state.debounceTimer = setTimeout(() => searchTarget('', ctxId), 100);
        } else {
          hideAutocomplete();
        }
        return;
      }
      state.debounceTimer = setTimeout(() => searchTarget(q, ctxId), 250);
    });

    input.addEventListener('focus', () => {
      const q = input.value.trim();
      const ctxId = cascadeWebUserId();
      if (q) {
        searchTarget(q, ctxId);
      } else if (ctxId > 0 && state.scope === 'GAME') {
        // Cascade: focus on empty field opens browse list of this user's accounts
        searchTarget('', ctxId);
      }
    });

    document.addEventListener('click', e => {
      if (!ac.contains(e.target) && e.target !== input) hideAutocomplete();
    });

    ac.addEventListener('click', e => {
      const item = e.target.closest('.vip-autocomplete-item');
      if (!item) return;
      const idx = parseInt(item.dataset.idx, 10);
      const r = state.searchResults[idx];
      if (r) selectTarget(r);
    });

    document.querySelector('#vipTargetChip .chip-clear').addEventListener('click', () => {
      state.target = null;
      hideTargetChip();
      document.getElementById('vipTargetSearch').focus();
      updatePreview();
    });

    // Clear cascade context filter
    document.querySelector('#vipContextBar .vip-context-clear').addEventListener('click', () => {
      state.webContext = null;
      applyContextBar();
      // If the autocomplete is currently open in browse mode, hide it
      hideAutocomplete();
      document.getElementById('vipTargetSearch').focus();
    });
  }

  function cascadeWebUserId() {
    // Cascade only applies to GAME scope picker
    if (state.scope === 'GAME' && state.webContext) return state.webContext.id;
    return 0;
  }

  function applyContextBar() {
    const bar = document.getElementById('vipContextBar');
    const show = state.scope === 'GAME' && state.webContext;
    if (show) {
      document.getElementById('vipContextEmail').textContent =
        state.webContext.email || ('web #' + state.webContext.id);
      bar.classList.remove('hidden');
    } else {
      bar.classList.add('hidden');
    }
  }

  async function searchTarget(q, webUserId = 0) {
    const ac = document.getElementById('vipAutocomplete');
    ac.innerHTML = '<div class="vip-autocomplete-loading">Hledám…</div>';
    ac.classList.add('open');
    try {
      let url = '/api/admin/vip_target_search.php?scope=' + encodeURIComponent(state.scope)
              + '&q=' + encodeURIComponent(q);
      if (webUserId > 0) url += '&webUserId=' + webUserId;
      const res = await apiFetch(url);
      state.searchResults = res.results || [];
      if (!state.searchResults.length) {
        const msg = (q === '' && webUserId > 0)
          ? 'Tento hráč nemá žádný game účet'
          : 'Nic nenalezeno';
        ac.innerHTML = '<div class="vip-autocomplete-empty">' + msg + '</div>';
        return;
      }
      ac.innerHTML = state.searchResults.map((r, i) => acItemHtml(r, i)).join('');
    } catch (e) {
      ac.innerHTML = '<div class="vip-autocomplete-empty">Chyba: ' + esc(e.message) + '</div>';
    }
  }

  function acItemHtml(r, idx) {
    let extraBadge = '';
    if (state.scope === 'WEB' && r.extra) {
      extraBadge = `<span class="ac-extra">${r.extra.game_account_count || 0} účtů</span>`;
    } else if (state.scope === 'GAME' && r.extra) {
      extraBadge = `<span class="ac-extra">${r.extra.character_count || 0} postav</span>`;
    } else if (state.scope === 'CHAR' && r.extra) {
      const lv = r.extra.level ? `Lv.${r.extra.level}` : '';
      const on = r.extra.online ? '<span class="dot on"></span>' : '';
      extraBadge = `<span class="ac-extra">${on}${lv}</span>`;
    }

    let grantBadge = '';
    if (r.existing_grant) {
      const g = r.existing_grant;
      grantBadge = `<span class="ac-grant" title="Již má aktivní grant — nové dny se přičtou">${esc(g.level_name)} · ${g.days_remaining} d</span>`;
    }

    return `
      <div class="vip-autocomplete-item" data-idx="${idx}">
        <span class="ac-label">${esc(r.label)} <span class="ac-id">#${r.id}</span></span>
        <span class="ac-context">${esc(r.context || '')}</span>
        ${extraBadge}
        ${grantBadge}
      </div>
    `;
  }

  function selectTarget(r) {
    state.target = r;
    // Remember web context so we can cascade-filter game accounts when scope switches
    if (state.scope === 'WEB') {
      state.webContext = { id: r.id, email: r.label };
    } else if (state.scope === 'GAME' && r.extra && r.extra.web_user_id) {
      state.webContext = { id: r.extra.web_user_id, email: r.context || null };
    }
    applyContextBar();
    hideAutocomplete();
    showTargetChip(r);
    document.getElementById('vipTargetSearch').value = '';
    updatePreview();
  }

  async function prefillTarget(scope, id) {
    try {
      const res = await apiFetch(
        '/api/admin/vip_target_search.php?scope=' + encodeURIComponent(scope)
        + '&q=' + encodeURIComponent(id)
      );
      const exactId = parseInt(id, 10);
      const match = (res.results || []).find(r => r.id === exactId);
      if (match) selectTarget(match);
    } catch (e) {
      console.error(e);
    }
  }

  function showTargetChip(r) {
    const chip = document.getElementById('vipTargetChip');
    chip.querySelector('.chip-label').innerHTML =
      `<b>${esc(r.label)}</b> <span class="chip-id">#${r.id}</span>`
      + (r.context ? ` <span class="chip-context">· ${esc(r.context)}</span>` : '');
    chip.classList.remove('hidden');
    document.querySelector('.vip-picker').classList.add('hidden');
  }

  function hideTargetChip() {
    document.getElementById('vipTargetChip').classList.add('hidden');
    document.querySelector('.vip-picker').classList.remove('hidden');
  }

  function hideAutocomplete() {
    document.getElementById('vipAutocomplete').classList.remove('open');
  }

  // ----- days -------------------------------------------------------
  function bindDays() {
    document.getElementById('daysPresets').addEventListener('click', e => {
      const btn = e.target.closest('button[data-days]');
      if (!btn) return;
      document.querySelectorAll('#daysPresets button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const v = btn.dataset.days;
      const customInput = document.getElementById('vipDaysCustom');
      if (v === 'custom') {
        state.customDays = true;
        customInput.classList.remove('hidden');
        customInput.focus();
        state.days = parseInt(customInput.value, 10) || 0;
      } else {
        state.customDays = false;
        customInput.classList.add('hidden');
        state.days = parseInt(v, 10);
      }
      updatePreview();
    });

    document.getElementById('vipDaysCustom').addEventListener('input', e => {
      state.days = Math.max(0, parseInt(e.target.value, 10) || 0);
      updatePreview();
    });
  }

  // ----- preview + add ----------------------------------------------
  function updatePreview() {
    const box = document.getElementById('vipPreview');
    const btn = document.getElementById('vipAddBtn');

    if (!state.target || !state.levelId || state.days <= 0) {
      box.classList.add('hidden');
      btn.disabled = true;
      return;
    }

    const lvl = state.levels.find(l => l.level_id === state.levelId);
    const lvlName = lvl ? lvl.name : ('Lvl ' + state.levelId);

    const scopeWord = {
      WEB:  `pro web account <b>${esc(state.target.label)}</b>`,
      GAME: `pro game účet <b>${esc(state.target.label)}</b>`,
      CHAR: `pro postavu <b>${esc(state.target.label)}</b>`,
    }[state.scope];

    // Special-case: Premium 24h preset (GAME + VIP I + 1 den)
    let badge = '';
    if (state.scope === 'GAME' && state.levelId === 1 && state.days === 1) {
      badge = '<span class="vip-preview-badge">⏱ Premium 24h</span> ';
    }

    let warn = '';
    if (state.scope === 'WEB' && state.target.extra) {
      const n = state.target.extra.game_account_count || 0;
      warn = `<br><span class="preview-warning">⚠ Ovlivní VŠECHNY ${n} game účtů hráče.</span>`;
    }

    let exi = '';
    if (state.target.existing_grant) {
      const g = state.target.existing_grant;
      exi = `<br><span class="preview-info">ℹ Aktuálně má <b>${esc(g.level_name)}</b> do ${esc(fmtDate(g.end_at))} (zbývá ${g.days_remaining} d) — nové dny se přičtou.</span>`;
    }

    box.innerHTML = `${badge}Aktivuje <b>${esc(lvlName)}</b> na <b>${state.days} dní</b> ${scopeWord}.${warn}${exi}`;
    box.classList.remove('hidden');
    btn.disabled = false;
  }

  function bindAddButton() {
    document.getElementById('vipAddBtn').addEventListener('click', async () => {
      if (!state.target || !state.levelId || state.days <= 0) return;
      const btn = document.getElementById('vipAddBtn');
      btn.disabled = true;
      const original = btn.textContent;
      btn.textContent = 'Pracuje se…';
      try {
        const res = await apiFetch('/api/admin/vip_add.php', {
          method: 'POST',
          body: JSON.stringify({
            scope:    state.scope,
            targetId: state.target.id,
            levelId:  state.levelId,
            days:     state.days,
          }),
        });
        toast(`✓ Premium přidáno (grant #${res.vip_grant_id})`, 'ok');
        state.target = null;
        hideTargetChip();
        document.getElementById('vipTargetSearch').value = '';
        await loadList();
        updatePreview();
      } catch (e) {
        toast('Chyba: ' + e.message, 'err');
      } finally {
        btn.textContent = original;
      }
    });
  }

  // ----- list -------------------------------------------------------
  async function loadList() {
    const box = document.getElementById('vipGrantList');
    box.innerHTML = '<div class="muted">Načítám…</div>';
    const p = new URLSearchParams();
    if (state.showExpired) p.set('showExpired', '1');
    if (state.listScope === 'soon') p.set('expiringSoon', '1');
    else if (state.listScope !== 'all') p.set('scope', state.listScope);
    try {
      const res = await apiFetch('/api/admin/vip_list.php?' + p.toString());
      state.list = res.data || [];
      renderStats(res.stats || {});
      renderList();
    } catch (e) {
      box.innerHTML = '<div class="muted">Chyba: ' + esc(e.message) + '</div>';
    }
  }

  function renderStats(s) {
    document.getElementById('statEffective').textContent = s.game_accounts_with_premium ?? '–';
    document.getElementById('statActive').textContent = s.active ?? '–';
    document.getElementById('statWeb').textContent    = (s.by_scope && s.by_scope.WEB)  ?? '–';
    document.getElementById('statGame').textContent   = (s.by_scope && s.by_scope.GAME) ?? '–';
    document.getElementById('statChar').textContent   = (s.by_scope && s.by_scope.CHAR) ?? '–';
    document.getElementById('statSoon').textContent   = s.expiring_soon ?? '–';
  }

  function renderList() {
    const box = document.getElementById('vipGrantList');
    if (!state.list.length) {
      box.innerHTML = '<div class="muted">Žádné granty pro zvolené filtry.</div>';
      return;
    }
    box.innerHTML = state.list.map(grantCardHtml).join('');
  }

  function grantCardHtml(v) {
    const cls = ['vip-grant-card', 'scope-' + v.scope.toLowerCase()];
    if (v.is_expired) cls.push('expired');
    else if (v.days_remaining > 0 && v.days_remaining < 5) cls.push('soon');

    const label = v.target_label || ('#' + v.target_id);
    const ctx   = v.target_context
      ? `<div class="grant-context">${esc(v.target_context)}</div>` : '';
    const remaining = v.is_expired
      ? '<span class="grant-remaining grant-expired">expiroval</span>'
      : `<span class="grant-remaining">${v.days_remaining} d zbývá</span>`;
    const actions = v.is_expired
      ? ''
      : `<button class="btn-danger" data-act="remove" data-id="${v.id}" type="button">Odebrat</button>`;

    return `
      <div class="${cls.join(' ')}">
        <div class="grant-head">
          <span class="grant-scope-badge">${esc(v.scope)}</span>
          <span class="grant-label">${esc(label)}</span>
          <span class="grant-id">#${v.target_id}</span>
        </div>
        ${ctx}
        <div class="grant-body">
          <span class="grant-level">${esc(v.level_name || 'Lvl ' + v.level_id)}</span>
          <span class="grant-dates">do ${esc(fmtDate(v.end_at))}</span>
          ${remaining}
        </div>
        <div class="grant-actions">${actions}</div>
      </div>
    `;
  }

  function bindListFilters() {
    document.getElementById('vipListFilters').addEventListener('click', e => {
      const chip = e.target.closest('button[data-scope-filter]');
      if (!chip) return;
      document.querySelectorAll('#vipListFilters .chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      state.listScope = chip.dataset.scopeFilter;
      loadList();
    });
    document.getElementById('showExpiredVip').addEventListener('change', e => {
      state.showExpired = e.target.checked;
      loadList();
    });
  }

  function bindGrantListActions() {
    document.getElementById('vipGrantList').addEventListener('click', e => {
      const btn = e.target.closest('button[data-act="remove"]');
      if (!btn) return;
      const id = parseInt(btn.dataset.id, 10);
      const grant = state.list.find(g => g.id === id);
      if (!grant) return;
      askRemoveConfirm(grant);
    });
  }

  function askRemoveConfirm(grant) {
    const label = grant.target_label || ('#' + grant.target_id);
    let body = `Odebrat <b>${esc(grant.level_name || 'Lvl ' + grant.level_id)}</b> pro <b>${esc(label)}</b>?`;
    if (grant.scope === 'WEB') {
      body += `<br><span class="preview-warning">⚠ Premium se okamžitě zruší pro VŠECHNY game účty tohoto hráče.</span>`;
    } else if (grant.scope === 'CHAR') {
      body += `<br><span class="muted">Postava ztratí VIP flag ve hře.</span>`;
    } else {
      body += `<br><span class="muted">Postavám zůstanou jejich přímé granty (CHAR), pokud nějaké jsou.</span>`;
    }

    showConfirm('Odebrat VIP grant', body, async () => {
      try {
        await apiFetch('/api/admin/vip_remove.php', {
          method: 'POST',
          body: JSON.stringify({ vipGrantId: grant.id }),
        });
        toast('VIP odebráno', 'ok');
        await loadList();
      } catch (e) {
        toast('Chyba: ' + e.message, 'err');
      }
    });
  }

  // ----- modal ------------------------------------------------------
  let modalOnConfirm = null;

  function bindModal() {
    const back = document.getElementById('vipModalBackdrop');
    document.getElementById('vipModalCancel').addEventListener('click', closeModal);
    document.getElementById('vipModalConfirm').addEventListener('click', () => {
      const cb = modalOnConfirm;
      closeModal();
      if (typeof cb === 'function') cb();
    });
    back.addEventListener('click', e => { if (e.target === back) closeModal(); });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !back.classList.contains('hidden')) closeModal();
    });
  }

  function showConfirm(title, bodyHtml, onConfirm) {
    document.getElementById('vipModalTitle').textContent = title;
    document.getElementById('vipModalBody').innerHTML = bodyHtml;
    modalOnConfirm = onConfirm;
    document.getElementById('vipModalBackdrop').classList.remove('hidden');
  }

  function closeModal() {
    document.getElementById('vipModalBackdrop').classList.add('hidden');
    modalOnConfirm = null;
  }

  // ----- go ---------------------------------------------------------
  document.addEventListener('DOMContentLoaded', init);
})();
