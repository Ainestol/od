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
      sort: 'event_time', dir: 'desc',
      page: 1, perPage: 50,
      total: 0, pages: 1,
      charId: 0,                              // optional filter from "Top players → Události"
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
    if (typeof extra.char_id === 'number') state.events.charId = extra.char_id;

    const p = new URLSearchParams();
    if (state.events.q)          p.set('q', state.events.q);
    if (state.events.eventType)  p.set('event_type', state.events.eventType);
    if (state.events.severity)   p.set('severity', state.events.severity);
    if (state.events.reviewStatuses.size)
      p.set('review_status', [...state.events.reviewStatuses].join(','));
    if (state.events.charId)     p.set('char_id', state.events.charId);
    p.set('sort', state.events.sort);
    p.set('dir',  state.events.dir);
    p.set('page', state.events.page);
    p.set('per_page', state.events.perPage);

    const tbody = document.querySelector('#eventsTable tbody');
    tbody.innerHTML = '<tr><td colspan="10" class="muted ta-center">Načítám…</td></tr>';

    try {
      const res = await apiFetch('/admin/api/ac_events_list.php?' + p.toString());
      const rows = res.data || [];
      state.events.data    = rows;
      state.events.total   = res.total ?? rows.length;
      state.events.pages   = res.pages ?? 1;
      state.events.page    = res.page  ?? state.events.page;

      // Pokud server vrátil prázdnou stranu (např. po smazání) a existují předchozí, jdi o stranu zpět
      if (!rows.length && state.events.page > 1) {
        state.events.page--;
        return loadEvents();
      }

      renderEventsMeta();
      renderEventsSortIndicators();

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="muted ta-center">Žádné události neodpovídají filtru.</td></tr>';
        renderPagination();
        return;
      }
      tbody.innerHTML = rows.map(r => `
        <tr data-id="${r.id}">
          <td class="ta-mono">${esc(fmtDate(r.event_time))}</td>
          <td>
            <b class="cell-clickable" data-filter-char="${r.char_object_id}" title="Filtrovat na tuto postavu">${esc(r.char_name)}</b>
            <span class="muted">#${r.char_object_id}</span>
          </td>
          <td>
            <span class="cell-clickable" data-filter-account="${esc(r.account_name || '')}" title="Filtrovat na tento account">${esc(r.account_name || '')}</span>
          </td>
          <td>${webEmailCell(r)}</td>
          <td>
            <span class="ev-type ev-${(r.event_type||'').toLowerCase()} cell-clickable"
                  data-filter-type="${esc(r.event_type)}" title="Filtrovat na tento typ">${esc(r.event_type)}</span>
          </td>
          <td>
            <span class="sev-badge ${severityClass(r.severity)} cell-clickable"
                  data-filter-sev="${esc(r.severity)}" title="Filtrovat na tuto severity">${esc(r.severity)}</span>
          </td>
          <td class="ta-right">+${r.score_added}</td>
          <td class="muted-text">${eventQuickDetail(r)}</td>
          <td>
            <span class="status-badge ${statusClass(r.review_status)} cell-clickable"
                  data-filter-status="${esc(r.review_status)}" title="Filtrovat na tento status">${esc(r.review_status)}</span>
          </td>
          <td>
            <button class="btn-small" data-act="open-detail" data-id="${r.id}">Detail</button>
          </td>
        </tr>
      `).join('');

      renderPagination();
    } catch (e) {
      console.error(e);
      tbody.innerHTML = '<tr><td colspan="10" class="muted ta-center">Chyba: ' + esc(e.message) + '</td></tr>';
    }
  }

  function renderEventsMeta() {
    const el = document.getElementById('eventsCount');
    if (!el) return;
    const start = state.events.total === 0 ? 0 : (state.events.page - 1) * state.events.perPage + 1;
    const end   = Math.min(state.events.page * state.events.perPage, state.events.total);
    const charSuffix = state.events.charId ? ` · filter: char #${state.events.charId}` : '';
    el.textContent = `Zobrazeno ${start}–${end} z ${state.events.total}${charSuffix}`;
  }

  function renderEventsSortIndicators() {
    document.querySelectorAll('#eventsTable thead th').forEach(th => {
      th.classList.remove('sort-asc', 'sort-desc');
      if (th.dataset.key === state.events.sort) {
        th.classList.add(state.events.dir === 'asc' ? 'sort-asc' : 'sort-desc');
      }
    });
  }

  function renderPagination() {
    const cur = state.events.page;
    const tot = state.events.pages;
    const html = paginationHtml(cur, tot);
    document.getElementById('eventsPaginationTop').innerHTML    = html;
    document.getElementById('eventsPaginationBottom').innerHTML = html;
  }

  function paginationHtml(cur, tot) {
    if (tot <= 1) return '';
    const btns = [];
    btns.push(`<button class="page-btn" data-page="1"     ${cur===1 ? 'disabled':''}>« 1</button>`);
    btns.push(`<button class="page-btn" data-page="${cur-1}" ${cur===1 ? 'disabled':''}>‹ Předchozí</button>`);
    btns.push(`<span class="page-info">Strana <b>${cur}</b> / ${tot}</span>`);
    btns.push(`<button class="page-btn" data-page="${cur+1}" ${cur===tot ? 'disabled':''}>Další ›</button>`);
    btns.push(`<button class="page-btn" data-page="${tot}"   ${cur===tot ? 'disabled':''}>${tot} »</button>`);
    return btns.join('');
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
        state.events.page = 1;
        loadEvents();
      }, 250);
    });
    document.getElementById('eventsType').addEventListener('change', e => {
      state.events.eventType = e.target.value;
      state.events.page = 1;
      updateBulkBtnState();
      loadEvents();
    });
    document.getElementById('eventsSeverity').addEventListener('change', e => {
      state.events.severity = e.target.value;
      state.events.page = 1;
      updateBulkBtnState();
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
      state.events.page = 1;
      loadEvents();
    });

    document.querySelector('#eventsTable tbody').addEventListener('click', e => {
      // Detail button
      const detailBtn = e.target.closest('button[data-act="open-detail"]');
      if (detailBtn) {
        const id = parseInt(detailBtn.dataset.id, 10);
        const row = state.events.data.find(r => r.id === id);
        if (row) openEventModal(row);
        return;
      }
      // Clickable badges → quick filter
      const t = e.target.closest('[data-filter-type]');
      if (t) {
        state.events.eventType = t.dataset.filterType;
        document.getElementById('eventsType').value = state.events.eventType;
        updateBulkBtnState();
        state.events.page = 1;
        loadEvents();
        return;
      }
      const sev = e.target.closest('[data-filter-sev]');
      if (sev) {
        state.events.severity = sev.dataset.filterSev;
        document.getElementById('eventsSeverity').value = state.events.severity;
        updateBulkBtnState();
        state.events.page = 1;
        loadEvents();
        return;
      }
      const st = e.target.closest('[data-filter-status]');
      if (st) {
        state.events.reviewStatuses = new Set([st.dataset.filterStatus]);
        document.querySelectorAll('#eventsReviewFilters .chip').forEach(c => {
          c.classList.toggle('active', c.dataset.review === st.dataset.filterStatus);
        });
        state.events.page = 1;
        loadEvents();
        return;
      }
      const ch = e.target.closest('[data-filter-char]');
      if (ch) {
        state.events.charId = parseInt(ch.dataset.filterChar, 10);
        state.events.page = 1;
        loadEvents();
        return;
      }
      const acc = e.target.closest('[data-filter-account]');
      if (acc) {
        state.events.q = acc.dataset.filterAccount;
        document.getElementById('eventsSearch').value = state.events.q;
        state.events.page = 1;
        loadEvents();
        return;
      }
    });

    // Sortable column headers
    document.querySelectorAll('#eventsTable thead th[data-key]').forEach(th => {
      th.style.cursor = 'pointer';
      th.addEventListener('click', () => {
        const key = th.dataset.key;
        if (state.events.sort === key) {
          state.events.dir = state.events.dir === 'asc' ? 'desc' : 'asc';
        } else {
          state.events.sort = key;
          state.events.dir  = 'desc';
        }
        state.events.page = 1;
        loadEvents();
      });
    });

    // Pagination clicks (top + bottom)
    ['eventsPaginationTop','eventsPaginationBottom'].forEach(elId => {
      const el = document.getElementById(elId);
      if (!el) return;
      el.addEventListener('click', e => {
        const btn = e.target.closest('button[data-page]');
        if (!btn || btn.disabled) return;
        const p = parseInt(btn.dataset.page, 10);
        if (!p || p < 1 || p > state.events.pages) return;
        state.events.page = p;
        loadEvents();
        // Scroll to top of events table
        document.getElementById('eventsTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    // Reset filters
    document.getElementById('eventsReset').addEventListener('click', () => {
      state.events.q              = '';
      state.events.eventType      = '';
      state.events.severity       = '';
      state.events.reviewStatuses = new Set(['NEW']);
      state.events.charId         = 0;
      state.events.page           = 1;
      state.events.sort           = 'event_time';
      state.events.dir            = 'desc';
      document.getElementById('eventsSearch').value   = '';
      document.getElementById('eventsType').value     = '';
      document.getElementById('eventsSeverity').value = '';
      document.querySelectorAll('#eventsReviewFilters .chip').forEach(c => {
        c.classList.toggle('active', c.dataset.review === 'NEW');
      });
      updateBulkBtnState();
      loadEvents();
    });

    // Bulk ignore button
    document.getElementById('bulkIgnoreBtn').addEventListener('click', async () => {
      // Vyžaduj aspoň jeden filtr — backend to chrání taky, ale UX si to ohlídá
      const hasFilter = state.events.eventType || state.events.severity || state.events.q;
      if (!hasFilter) {
        toast('Aktivuj nejdřív filtr (typ / severity / search)', 'err');
        return;
      }

      // Spočítáme NEW events v aktuálním listu pro confirm message
      const newCount = state.events.data.filter(r => r.review_status === 'NEW').length;
      const filterLabel =
        (state.events.eventType ? 'typ=' + state.events.eventType : '') +
        (state.events.severity  ? ' severity=' + state.events.severity : '') +
        (state.events.q         ? ' search="' + state.events.q + '"' : '');

      if (!confirm(`Bulk-označit jako IGNORED všechny NEW události odpovídající: ${filterLabel}?\n\nNa této straně cca ${newCount} viditelných (backend označí všechny vyhovující v DB).`)) {
        return;
      }

      const body = {};
      if (state.events.eventType) body.event_type = state.events.eventType;
      if (state.events.severity)  body.severity   = state.events.severity;
      // search → ne; backend nezpracovává LIKE, jen exact filtry. Pokud je search, varuj.
      if (state.events.q && !state.events.eventType && !state.events.severity) {
        toast('Pro bulk ignore nutný filtr na event_type nebo severity (search bulk nepokrývá)', 'err');
        return;
      }

      const btn = document.getElementById('bulkIgnoreBtn');
      btn.disabled = true;
      const orig = btn.textContent;
      btn.textContent = 'Pracuje se…';
      try {
        const res = await apiFetch('/admin/api/ac_bulk_ignore.php', {
          method: 'POST',
          body: JSON.stringify(body),
        });
        toast(`✓ Označeno ${res.updated} událostí jako IGNORED`, 'ok');
        await loadEvents();
        // Refresh i overview, ať se update propíše do statů
        if (typeof loadOverview === 'function') loadOverview();
      } catch (e) {
        toast('Chyba: ' + e.message, 'err');
      } finally {
        btn.textContent = orig;
        updateBulkBtnState();
      }
    });
  }

  function updateBulkBtnState() {
    const btn = document.getElementById('bulkIgnoreBtn');
    if (!btn) return;
    const hasFilter = !!(state.events.eventType || state.events.severity);
    btn.disabled = !hasFilter;
  }

  // ----- Event modal ------------------------------------------------
  async function openEventModal(r) {
    state.events.currentRowId = r.id;
    state.events.currentNewStatus = r.review_status;

    document.getElementById('evModalId').textContent = '#' + r.id;
    document.getElementById('evModalNote').value = r.review_note || '';
    document.querySelectorAll('#evModalStatusSeg button').forEach(b => {
      b.classList.toggle('active', b.dataset.st === (r.review_status || 'NEW'));
    });

    // Show modal immediately with basic info, then enrich
    document.getElementById('evModalBody').innerHTML =
      '<div class="muted ta-center" style="padding:20px;">Načítám detail…</div>';
    document.getElementById('evModalBackdrop').classList.remove('hidden');

    let details;
    try {
      details = await apiFetch('/admin/api/ac_event_details.php?id=' + encodeURIComponent(r.id));
    } catch (e) {
      document.getElementById('evModalBody').innerHTML =
        '<div class="muted ta-center" style="padding:20px;">Chyba načtení: ' + esc(e.message) + '</div>';
      return;
    }

    renderEventModalBody(details);
  }

  function renderEventModalBody(details) {
    const r   = details.event || {};
    const aud = details.economy_audit;
    const ctx = details.parsed_context;

    // Build context-aware "What happened" summary line per event_type
    const summary = buildEventSummary(r, aud, ctx);

    const econHtml = aud ? renderEconomyAuditSection(aud) : '';
    const ctxHtml  = ctx ? renderParsedContextSection(ctx) : '';

    document.getElementById('evModalBody').innerHTML = `
      ${summary ? `<div class="ev-summary">${summary}</div>` : ''}
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
        ${r.skill_id ? `<div><span class="muted-text">Skill</span><b>${r.skill_name ? esc(r.skill_name) + ' ' : ''}<span class="muted">#${r.skill_id}</span></b></div>` : ''}
        ${r.item_id ? `<div><span class="muted-text">Item ID</span><b>${r.item_id}</b></div>` : ''}
        ${r.target_object_id ? `<div><span class="muted-text">Target obj</span><b>#${r.target_object_id}</b></div>` : ''}
        ${(r.x_from != null) ? `<div><span class="muted-text">Z (x,y,z)</span><span class="ta-mono">${r.x_from}, ${r.y_from}, ${r.z_from}</span></div>` : ''}
        ${(r.x_to   != null) ? `<div><span class="muted-text">Na (x,y,z)</span><span class="ta-mono">${r.x_to}, ${r.y_to}, ${r.z_to}</span></div>` : ''}
      </div>
      ${econHtml}
      ${ctxHtml}
    `;
  }

  function buildEventSummary(r, aud, ctx) {
    const t = (r.event_type || '').toUpperCase();
    if (t === 'SUSPICIOUS_ECONOMY_GAIN' && aud) {
      const parts = [];
      if (aud.source_type) parts.push(`<b>${esc(aud.source_type)}</b>`);
      if (aud.reference_name) parts.push(esc(aud.reference_name));
      if (aud.item_id) {
        const cnt = aud.item_count ? ` × ${aud.item_count.toLocaleString()}` : '';
        parts.push(`item #${aud.item_id}${cnt}`);
      }
      if (aud.adena_delta != null && aud.adena_delta !== 0) {
        const sign = aud.adena_delta > 0 ? '+' : '';
        parts.push(`<span class="amt-${aud.adena_delta > 0 ? 'pos' : 'neg'}">${sign}${aud.adena_delta.toLocaleString()} adena</span>`);
      }
      if (aud.map_region) parts.push(`📍 ${esc(aud.map_region)}`);
      if (aud.npc_id)     parts.push(`NPC #${aud.npc_id}`);
      if (aud.target_char) parts.push(`→ ${esc(aud.target_char)}`);
      if (aud.reason)     parts.push(`<i>${esc(aud.reason)}</i>`);
      return parts.length ? '💰 ' + parts.join(' · ') : '';
    }
    if (t === 'IMPOSSIBLE_ACTION_RATE' && ctx) {
      const what = ctx.action_type || ctx.action || ctx.type || '?';
      const rate = ctx.rate ?? ctx.actions_per_sec ?? ctx.per_sec;
      const thr  = ctx.threshold ?? ctx.limit;
      const span = [`<b>${esc(what)}</b>`];
      if (rate != null) span.push(`${rate}/s`);
      if (thr  != null) span.push(`(limit ${thr})`);
      return '⚡ ' + span.join(' · ');
    }
    if ((t === 'SPEED_ANOMALY' || t === 'ILLEGAL_TELEPORT') && r.distance != null) {
      return `🏃 dist ${Math.round(r.distance)}`
        + (r.effective_speed != null ? ` · ${Math.round(r.effective_speed)} units/s` : '')
        + (r.expected_speed  != null ? ` (max ${Math.round(r.expected_speed)})` : '');
    }
    if (t === 'SKILL_OUT_OF_RANGE' && r.distance != null) {
      const sk = r.skill_name ? `${esc(r.skill_name)} (#${r.skill_id})` : `skill #${r.skill_id ?? '?'}`;
      return `🎯 dist ${Math.round(r.distance)} · ${sk}`;
    }
    return '';
  }

  function renderEconomyAuditSection(a) {
    return `
      <h4 class="ev-section-h">Economy audit (z <code>ac_economy_audit</code>)</h4>
      <div class="ev-detail-grid">
        <div><span class="muted-text">Source type</span><b>${esc(a.source_type || '–')}</b></div>
        <div><span class="muted-text">Reference name</span><b>${esc(a.reference_name || '–')}</b></div>
        <div><span class="muted-text">Reference ID</span><span class="ta-mono">${esc(a.reference_id || '–')}</span></div>
        ${a.item_id    ? `<div><span class="muted-text">Item</span><b>#${a.item_id}${a.item_count ? ' × ' + a.item_count.toLocaleString() : ''}</b></div>` : ''}
        ${a.adena_delta != null ? `<div><span class="muted-text">Adena Δ</span><b class="amt-${a.adena_delta > 0 ? 'pos' : 'neg'}">${a.adena_delta > 0 ? '+' : ''}${a.adena_delta.toLocaleString()}</b></div>` : ''}
        ${a.npc_id      ? `<div><span class="muted-text">NPC ID</span><b>#${a.npc_id}</b></div>` : ''}
        ${a.target_char ? `<div><span class="muted-text">Target char</span><b>${esc(a.target_char)}</b></div>` : ''}
        ${a.map_region  ? `<div><span class="muted-text">Mapa</span><b>${esc(a.map_region)}</b></div>` : ''}
        ${a.process_tag ? `<div><span class="muted-text">Process tag</span><span class="ta-mono">${esc(a.process_tag)}</span></div>` : ''}
        ${(a.x != null) ? `<div><span class="muted-text">Souřadnice</span><span class="ta-mono">${a.x}, ${a.y}, ${a.z}</span></div>` : ''}
        <div><span class="muted-text">is_suspicious</span><b>${a.is_suspicious ? '✓ ano' : 'ne'}</b></div>
      </div>
      ${a.reason ? `<div class="ev-audit-reason"><b>Důvod:</b> ${esc(a.reason)}</div>` : ''}
    `;
  }

  function renderParsedContextSection(ctx) {
    const entries = Object.entries(ctx);
    if (!entries.length) return '';
    return `
      <h4 class="ev-section-h">Context</h4>
      <div class="ev-detail-grid">
        ${entries.map(([k, v]) => `
          <div>
            <span class="muted-text">${esc(k)}</span>
            <span class="ta-mono">${esc(typeof v === 'object' ? JSON.stringify(v) : String(v))}</span>
          </div>
        `).join('')}
      </div>
    `;
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
