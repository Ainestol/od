/* =====================================
   ORDO DRACONIS — ADMIN: Security (Anti-Cheat)
   Overview + players list + events list + review modal
   ===================================== */
(() => {
  'use strict';

  const state = {
    sub: 'overview',                  // active subtab
    players: {
      q: '', filters: new Set(),
      sort: 'current_score', dir: 'desc',
      debounce: null, data: [],
    },
    events: {
      q: '', eventType: '', severity: '',
      reviewStatuses: new Set(['NEW']),
      debounce: null, data: [],
      currentRowId: null,
      currentNewStatus: null,
    },
  };

  // ----- helpers -----------------------------------------------------
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
  function fmtMs(ms) {
    if (ms == null) return '';
    return ms + ' ms';
  }
  function severityClass(s) {
    switch ((s || '').toUpperCase()) {
      case 'CRITICAL': return 'sev-critical';
      case 'HIGH':     return 'sev-high';
      case 'MEDIUM':   return 'sev-medium';
      case 'LOW':      return 'sev-low';
      default:         return 'sev-unknown';
    }
  }
  function statusClass(s) {
    switch ((s || '').toUpperCase()) {
      case 'CONFIRMED': return 'st-confirmed';
      case 'REVIEWED':  return 'st-reviewed';
      case 'IGNORED':   return 'st-ignored';
      case 'NEW':       return 'st-new';
      default:          return 'st-unknown';
    }
  }
  function flagsHtml(p) {
    const b = [];
    if (p.ban_recommended)   b.push('<span class="flag-badge flag-ban">🔴 BAN REC</span>');
    if (p.high_risk_flag)    b.push('<span class="flag-badge flag-high">⚠ HIGH</span>');
    if (p.manual_watch_flag) b.push('<span class="flag-badge flag-watch">👁 WATCH</span>');
    return b.join(' ');
  }
  function webEmailCell(p) {
    if (!p.web_email) return '<span class="muted">–</span>';
    return `<a class="link" href="/admin/users.html?q=${encodeURIComponent(p.web_email)}" title="Otevřít v Uživatelé">${esc(p.web_email)}</a>`;
  }
  function toast(msg, kind = 'ok') {
    const t = document.getElementById('secToast');
    t.className = 'vip-toast show ' + (kind === 'err' ? 'err' : 'ok');
    t.textContent = msg;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => t.classList.remove('show'), 3000);
  }

  // ----- subtabs -----------------------------------------------------
  function bindSubtabs() {
    document.getElementById('secSubtabs').addEventListener('click', e => {
      const btn = e.target.closest('.sec-subtab');
      if (!btn) return;
      const k = btn.dataset.sub;
      if (k === state.sub) return;
      state.sub = k;
      document.querySelectorAll('.sec-subtab').forEach(b => b.classList.toggle('active', b.dataset.sub === k));
      document.querySelectorAll('.sec-pane').forEach(p => p.classList.add('hidden'));
      document.getElementById('pane' + k[0].toUpperCase() + k.slice(1)).classList.remove('hidden');
      if (k === 'overview') loadOverview();
      else if (k === 'players') loadPlayers();
      else if (k === 'events')  loadEvents();
    });
  }

  // Cached overview data (so toggle re-renders without re-fetch)
  let _overviewCache = null;

  // ----- OVERVIEW ---------------------------------------------------
  async function loadOverview() {
    try {
      const res = await apiFetch('/admin/api/ac_overview.php');
      _overviewCache = res;
      const p = res.players || {};
      const e = res.events  || {};
      document.getElementById('statBanRec').textContent  = p.ban_recommended ?? 0;
      document.getElementById('statHighRisk').textContent= p.high_risk     ?? 0;
      document.getElementById('statWatch').textContent   = p.manual_watch  ?? 0;
      document.getElementById('statScored').textContent  = p.total_with_score ?? 0;
      document.getElementById('statEvNew').textContent   = e.new_total ?? 0;
      document.getElementById('statEv24h').textContent   = e.last_24h ?? 0;
      document.getElementById('statEv1h').textContent    = e.last_1h ?? 0;
      document.getElementById('statAdmin7d').textContent = e.admin_audit_7d ?? 0;

      renderBreakdowns();

      // Top players table
      const tbody = document.querySelector('#topPlayersTable tbody');
      const list = p.top || [];
      if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="muted ta-center">Žádní hráči s aktivním score.</td></tr>';
      } else {
        tbody.innerHTML = list.map(t => `
          <tr>
            <td><b>${esc(t.char_name)}</b> <span class="muted">#${t.char_object_id}</span></td>
            <td>${esc(t.account_name || '')}</td>
            <td>${webEmailCell(t)}</td>
            <td class="ta-right"><b class="score-num">${t.current_score}</b></td>
            <td class="ta-right">${t.score_24h}</td>
            <td class="ta-right">${t.total_events}</td>
            <td>${flagsHtml(t)}</td>
            <td>
              <button class="btn-small" data-act="view-events" data-cid="${t.char_object_id}">Události</button>
            </td>
          </tr>
        `).join('');
      }
    } catch (e) {
      console.error(e);
      toast('Chyba: ' + e.message, 'err');
    }
  }

  function bindOverviewActions() {
    document.querySelector('#topPlayersTable tbody').addEventListener('click', e => {
      const btn = e.target.closest('button[data-act="view-events"]');
      if (!btn) return;
      const charId = parseInt(btn.dataset.cid, 10);
      state.events.charId = charId;
      document.querySelector('.sec-subtab[data-sub="events"]').click();
      loadEvents({ char_id: charId });
    });

    // Toggle: NEW only vs include IGNORED noise
    const tg = document.getElementById('bdShowAll');
    if (tg) {
      tg.addEventListener('change', () => renderBreakdowns());
    }

    // Admin audit card click → events tab filtered to ADMIN_PRIVILEGE_EVENT
    const adminCard = document.getElementById('statAdminCard');
    if (adminCard) {
      adminCard.addEventListener('click', () => {
        state.events.eventType = 'ADMIN_PRIVILEGE_EVENT';
        // Clear review status default (we want to see audit, not 'NEW')
        state.events.reviewStatuses = new Set();
        document.querySelector('.sec-subtab[data-sub="events"]').click();
        // Apply selects visually
        const sel = document.getElementById('eventsType');
        if (sel) sel.value = 'ADMIN_PRIVILEGE_EVENT';
        document.querySelectorAll('#eventsReviewFilters .chip').forEach(c => c.classList.remove('active'));
        loadEvents();
      });
    }
  }

  // Render the breakdown pills from cached overview data, respecting toggle
  function renderBreakdowns() {
    if (!_overviewCache) return;
    const e = _overviewCache.events || {};
    const showAll = !!document.getElementById('bdShowAll')?.checked;

    const byType = showAll ? (e.by_type_all || {}) : (e.by_type_new || {});
    const bySev  = showAll ? (e.by_severity_all || {}) : (e.by_severity_new || {});

    document.getElementById('breakdownType').innerHTML =
      Object.keys(byType).length
        ? Object.entries(byType)
            .map(([k, v]) => `<span class="bd-pill"><b>${v}</b> ${esc(k)}</span>`).join('')
        : `<span class="muted">${showAll ? 'Žádné události za 7 dní.' : 'Žádná aktivní podezření (NEW).'}</span>`;

    document.getElementById('breakdownSeverity').innerHTML =
      Object.keys(bySev).length
        ? ['CRITICAL','HIGH','MEDIUM','LOW']
            .filter(s => bySev[s])
            .map(s => `<span class="bd-pill ${severityClass(s)}"><b>${bySev[s]}</b> ${esc(s)}</span>`)
            .join('')
        : '<span class="muted">–</span>';
  }

  // ----- PLAYERS ----------------------------------------------------
  async function loadPlayers() {
    const p = new URLSearchParams();
    if (state.players.q) p.set('q', state.players.q);
    if (state.players.filters.size) p.set('filter', [...state.players.filters].join(','));
    p.set('sort', state.players.sort);
    p.set('dir',  state.players.dir);

    const tbody = document.querySelector('#playersTable tbody');
    tbody.innerHTML = '<tr><td colspan="12" class="muted ta-center">Načítám…</td></tr>';

    try {
      const res = await apiFetch('/admin/api/ac_players_list.php?' + p.toString());
      const rows = res.data || [];
      state.players.data = rows;
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="muted ta-center">Žádní hráči neodpovídají filtru.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(r => `
        <tr>
          <td><b>${esc(r.char_name)}</b> <span class="muted">#${r.char_object_id}</span></td>
          <td>${esc(r.account_name || '')}</td>
          <td>${webEmailCell(r)}</td>
          <td class="ta-right"><b class="score-num">${r.current_score}</b></td>
          <td class="ta-right">${r.score_24h}</td>
          <td class="ta-right">${r.total_events}</td>
          <td class="ta-right">${r.teleport_events}</td>
          <td class="ta-right">${r.speed_events}</td>
          <td class="ta-right">${r.range_events}</td>
          <td class="ta-right">${r.economy_events}</td>
          <td>${esc(fmtDate(r.last_event_time))}</td>
          <td>${flagsHtml(r)}</td>
        </tr>
      `).join('');
    } catch (e) {
      console.error(e);
      tbody.innerHTML = '<tr><td colspan="12" class="muted ta-center">Chyba: ' + esc(e.message) + '</td></tr>';
    }
  }

  function bindPlayers() {
    document.getElementById('playersSearch').addEventListener('input', e => {
      clearTimeout(state.players.debounce);
      state.players.debounce = setTimeout(() => {
        state.players.q = e.target.value.trim();
        loadPlayers();
      }, 250);
    });
    document.getElementById('playersFilters').addEventListener('click', e => {
      const chip = e.target.closest('.chip');
      if (!chip) return;
      const k = chip.dataset.filter;
      if (state.players.filters.has(k)) {
        state.players.filters.delete(k);
        chip.classList.remove('active');
      } else {
        state.players.filters.add(k);
        chip.classList.add('active');
      }
      loadPlayers();
    });
    const sel = document.getElementById('playersSort');
    sel.addEventListener('change', () => { state.players.sort = sel.value; loadPlayers(); });
    const dirBtn = document.getElementById('playersSortDir');
    dirBtn.addEventListener('click', () => {
      state.players.dir = (state.players.dir === 'desc') ? 'asc' : 'desc';
      dirBtn.textContent = state.players.dir === 'desc' ? '↓' : '↑';
      loadPlayers();
    });
  }

  // ----- EVENTS -----------------------------------------------------
  async function loadEvents(extra) {
    extra = extra || {};
    const p = new URLSearchParams();
    if (state.events.q)          p.set('q', state.events.q);
    if (state.events.eventType)  p.set('event_type', state.events.eventType);
    if (state.events.severity)   p.set('severity', state.events.severity);
    if (state.events.reviewStatuses.size)
      p.set('review_status', [...state.events.reviewStatuses].join(','));
    if (extra.char_id)           p.set('char_id', extra.char_id);

    const tbody = document.querySelector('#eventsTable tbody');
    tbody.innerHTML = '<tr><td colspan="10" class="muted ta-center">Načítám…</td></tr>';

    try {
      const res = await apiFetch('/admin/api/ac_events_list.php?' + p.toString());
      const rows = res.data || [];
      state.events.data = rows;
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="muted ta-center">Žádné události.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(r => `
        <tr data-id="${r.id}">
          <td class="ta-mono">${esc(fmtDate(r.event_time))}</td>
          <td><b>${esc(r.char_name)}</b> <span class="muted">#${r.char_object_id}</span></td>
          <td>${esc(r.account_name || '')}</td>
          <td>${webEmailCell(r)}</td>
          <td><span class="ev-type ev-${(r.event_type||'').toLowerCase()}">${esc(r.event_type)}</span></td>
          <td><span class="sev-badge ${severityClass(r.severity)}">${esc(r.severity)}</span></td>
          <td class="ta-right">+${r.score_added}</td>
          <td class="muted-text">${eventQuickDetail(r)}</td>
          <td><span class="status-badge ${statusClass(r.review_status)}">${esc(r.review_status)}</span></td>
          <td>
            <button class="btn-small" data-act="open-detail" data-id="${r.id}">Detail</button>
          </td>
        </tr>
      `).join('');
    } catch (e) {
      console.error(e);
      tbody.innerHTML = '<tr><td colspan="10" class="muted ta-center">Chyba: ' + esc(e.message) + '</td></tr>';
    }
  }

  function eventQuickDetail(r) {
    const t = (r.event_type || '').toUpperCase();
    if (t === 'TELEPORT' || t === 'SPEED') {
      const parts = [];
      if (r.distance != null) parts.push('dist ' + Math.round(r.distance));
      if (r.effective_speed != null) parts.push('speed ' + Math.round(r.effective_speed));
      if (r.time_delta_ms != null) parts.push(r.time_delta_ms + ' ms');
      return esc(parts.join(' · '));
    }
    if (t === 'RANGE') {
      if (r.distance != null) return 'dist ' + Math.round(r.distance);
    }
    if (t === 'ECONOMY' && r.item_id) {
      return 'item #' + r.item_id;
    }
    return '';
  }

  function bindEvents() {
    document.getElementById('eventsSearch').addEventListener('input', e => {
      clearTimeout(state.events.debounce);
      state.events.debounce = setTimeout(() => {
        state.events.q = e.target.value.trim();
        loadEvents();
      }, 250);
    });
    document.getElementById('eventsType').addEventListener('change', e => {
      state.events.eventType = e.target.value;
      loadEvents();
    });
    document.getElementById('eventsSeverity').addEventListener('change', e => {
      state.events.severity = e.target.value;
      loadEvents();
    });
    document.getElementById('eventsReviewFilters').addEventListener('click', e => {
      const chip = e.target.closest('.chip');
      if (!chip) return;
      const k = chip.dataset.review;
      if (state.events.reviewStatuses.has(k)) {
        state.events.reviewStatuses.delete(k);
        chip.classList.remove('active');
      } else {
        state.events.reviewStatuses.add(k);
        chip.classList.add('active');
      }
      loadEvents();
    });

    document.querySelector('#eventsTable tbody').addEventListener('click', e => {
      const btn = e.target.closest('button[data-act="open-detail"]');
      if (!btn) return;
      const id = parseInt(btn.dataset.id, 10);
      const row = state.events.data.find(r => r.id === id);
      if (row) openEventModal(row);
    });
  }

  // ----- Event modal ------------------------------------------------
  function openEventModal(r) {
    state.events.currentRowId = r.id;
    state.events.currentNewStatus = r.review_status;

    document.getElementById('evModalId').textContent = '#' + r.id;
    document.getElementById('evModalNote').value = r.review_note || '';

    document.querySelectorAll('#evModalStatusSeg button').forEach(b => {
      b.classList.toggle('active', b.dataset.st === (r.review_status || 'NEW'));
    });

    let ctx = '';
    try { ctx = r.context_json ? JSON.stringify(JSON.parse(r.context_json), null, 2) : ''; }
    catch (_) { ctx = r.context_json || ''; }

    document.getElementById('evModalBody').innerHTML = `
      <div class="ev-detail-grid">
        <div><span class="muted-text">Čas</span><b>${esc(fmtDate(r.event_time))}</b></div>
        <div><span class="muted-text">Postava</span><b>${esc(r.char_name)}</b> <span class="muted">#${r.char_object_id}</span></div>
        <div><span class="muted-text">Account</span><b>${esc(r.account_name || '')}</b></div>
        <div><span class="muted-text">Web hráč</span>${webEmailCell(r)}</div>
        <div><span class="muted-text">IP</span><span class="ta-mono">${esc(r.ip_address || '–')}</span></div>
        <div><span class="muted-text">HWID hash</span><span class="ta-mono">${esc(r.hwid_hash ? r.hwid_hash.substring(0,16) + '…' : '–')}</span></div>
        <div><span class="muted-text">Typ</span><span class="ev-type ev-${(r.event_type||'').toLowerCase()}">${esc(r.event_type)}</span></div>
        <div><span class="muted-text">Severity</span><span class="sev-badge ${severityClass(r.severity)}">${esc(r.severity)}</span></div>
        <div><span class="muted-text">Score added</span><b>+${r.score_added}</b></div>
        <div><span class="muted-text">Status</span><span class="status-badge ${statusClass(r.review_status)}">${esc(r.review_status)}</span></div>
        ${r.distance != null ? `<div><span class="muted-text">Vzdálenost</span><b>${Math.round(r.distance)}</b></div>` : ''}
        ${r.time_delta_ms != null ? `<div><span class="muted-text">Δt</span><b>${r.time_delta_ms} ms</b></div>` : ''}
        ${r.expected_speed != null ? `<div><span class="muted-text">Expected speed</span><b>${Math.round(r.expected_speed)}</b></div>` : ''}
        ${r.effective_speed != null ? `<div><span class="muted-text">Effective speed</span><b>${Math.round(r.effective_speed)}</b></div>` : ''}
        ${r.skill_id ? `<div><span class="muted-text">Skill ID</span><b>${r.skill_id}</b></div>` : ''}
        ${r.item_id ? `<div><span class="muted-text">Item ID</span><b>${r.item_id}</b></div>` : ''}
        ${r.target_object_id ? `<div><span class="muted-text">Target</span><b>#${r.target_object_id}</b></div>` : ''}
        ${(r.x_from != null) ? `<div><span class="muted-text">Z</span><span class="ta-mono">${r.x_from}, ${r.y_from}, ${r.z_from}</span></div>` : ''}
        ${(r.x_to != null) ? `<div><span class="muted-text">Na</span><span class="ta-mono">${r.x_to}, ${r.y_to}, ${r.z_to}</span></div>` : ''}
      </div>
      ${ctx ? `<details class="ev-context"><summary>context_json</summary><pre>${esc(ctx)}</pre></details>` : ''}
    `;

    document.getElementById('evModalBackdrop').classList.remove('hidden');
  }

  function bindEventModal() {
    const back = document.getElementById('evModalBackdrop');

    document.getElementById('evModalCancel').addEventListener('click', closeEventModal);
    back.addEventListener('click', e => { if (e.target === back) closeEventModal(); });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !back.classList.contains('hidden')) closeEventModal();
    });

    document.getElementById('evModalStatusSeg').addEventListener('click', e => {
      const btn = e.target.closest('button[data-st]');
      if (!btn) return;
      state.events.currentNewStatus = btn.dataset.st;
      document.querySelectorAll('#evModalStatusSeg button').forEach(b => {
        b.classList.toggle('active', b === btn);
      });
    });

    document.getElementById('evModalSave').addEventListener('click', async () => {
      const id = state.events.currentRowId;
      if (!id) return;
      const status = state.events.currentNewStatus || 'NEW';
      const note   = document.getElementById('evModalNote').value.trim();
      try {
        await apiFetch('/admin/api/ac_event_review.php', {
          method: 'POST',
          body: JSON.stringify({
            event_id:      id,
            review_status: status,
            review_note:   note,
          }),
        });
        toast('Status uložen', 'ok');
        closeEventModal();
        await loadEvents();
      } catch (e) {
        toast('Chyba: ' + e.message, 'err');
      }
    });
  }

  function closeEventModal() {
    document.getElementById('evModalBackdrop').classList.add('hidden');
    state.events.currentRowId = null;
    state.events.currentNewStatus = null;
  }

  // ----- init -------------------------------------------------------
  async function init() {
    if (typeof initCsrf === 'function') await initCsrf();
    bindSubtabs();
    bindOverviewActions();
    bindPlayers();
    bindEvents();
    bindEventModal();
    await loadOverview();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
