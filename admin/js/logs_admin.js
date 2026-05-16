/* =====================================
   ORDO DRACONIS — ADMIN: System Logs + Security
   ===================================== */
(() => {
  'use strict';

  const state = {
    sub: 'logs',
    logs: {
      q: '',
      logTypes: new Set(),
      statuses: new Set(),
      from: '', to: '',
      sort: 'created_at', dir: 'desc',
      page: 1, perPage: 50,
      total: 0, pages: 1,
      data: [],
      facetsLoaded: false,
      debounce: null,
    },
    security: {
      days: 7,
    },
  };

  // ----- helpers -------------------------------------------------------
  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear()
      + ' ' + String(d.getHours()).padStart(2, '0')
      + ':' + String(d.getMinutes()).padStart(2, '0')
      + ':' + String(d.getSeconds()).padStart(2, '0');
  }
  function toast(msg, kind = 'ok') {
    const t = document.getElementById('logsToast');
    t.className = 'vip-toast show ' + (kind === 'err' ? 'err' : 'ok');
    t.textContent = msg;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => t.classList.remove('show'), 3000);
  }
  function statusClass(s) {
    s = (s || '').toUpperCase();
    if (s === 'SUCCESS')           return 'st-confirmed';   // green
    if (s === 'FAIL'  || s === 'ERROR' || s === 'FAILED') return 'st-ignored';
    if (s === 'INFO'  || s === 'PENDING' || s === 'WARNING') return 'st-new';
    return 'st-unknown';
  }
  function logTypeClass(t) {
    t = (t || '').toUpperCase();
    if (t === 'VOTE')      return 'lt-vote';
    if (t === 'ADMIN')     return 'lt-admin';
    if (t === 'AUTH')      return 'lt-auth';
    if (t === 'ECONOMY' || t === 'SHOP' || t === 'WALLET') return 'lt-economy';
    if (t === 'VIP')       return 'lt-vip';
    if (t === 'DONATION')  return 'lt-donation';
    return 'lt-other';
  }

  // ----- subtabs -------------------------------------------------------
  function bindSubtabs() {
    document.getElementById('logsSubtabs').addEventListener('click', e => {
      const btn = e.target.closest('.sec-subtab');
      if (!btn) return;
      const k = btn.dataset.sub;
      if (k === state.sub) return;
      state.sub = k;
      document.querySelectorAll('.sec-subtab').forEach(b => b.classList.toggle('active', b.dataset.sub === k));
      document.querySelectorAll('.sec-pane').forEach(p => p.classList.add('hidden'));
      document.getElementById('pane' + k[0].toUpperCase() + k.slice(1)).classList.remove('hidden');
      if (k === 'logs')     loadLogs();
      if (k === 'security') loadSecurity();
    });
  }

  // ----- LOGS load -----------------------------------------------------
  async function loadLogs() {
    const p = new URLSearchParams();
    if (state.logs.q)         p.set('q', state.logs.q);
    if (state.logs.logTypes.size) p.set('log_type', [...state.logs.logTypes].join(','));
    if (state.logs.statuses.size) p.set('status',   [...state.logs.statuses].join(','));
    if (state.logs.from)      p.set('from', state.logs.from.replace('T',' ') + ':00');
    if (state.logs.to)        p.set('to',   state.logs.to.replace('T',' ')   + ':59');
    p.set('sort', state.logs.sort);
    p.set('dir',  state.logs.dir);
    p.set('page', state.logs.page);
    p.set('per_page', state.logs.perPage);

    const tbody = document.querySelector('#logsTable tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="muted ta-center">Načítám…</td></tr>';

    try {
      const res = await apiFetch('/admin/api/logs_list.php?' + p.toString());
      state.logs.data  = res.data || [];
      state.logs.total = res.total ?? 0;
      state.logs.pages = res.pages ?? 1;
      state.logs.page  = res.page  ?? state.logs.page;

      // First load → populate facet chips
      if (!state.logs.facetsLoaded && res.facets) {
        renderFacetChips(res.facets);
        state.logs.facetsLoaded = true;
      }

      renderMeta();
      renderSortIndicators();
      renderTable();
      renderPagination();
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="9" class="muted ta-center">Chyba: ' + esc(e.message) + '</td></tr>';
    }
  }

  function renderFacetChips(facets) {
    // log_type
    const tCt = document.getElementById('logsTypeFilters');
    (facets.log_types || []).forEach(t => {
      const b = document.createElement('button');
      b.className = 'chip';
      b.dataset.logtype = t;
      b.textContent = t;
      tCt.appendChild(b);
    });
    tCt.addEventListener('click', e => {
      const chip = e.target.closest('.chip[data-logtype]');
      if (!chip) return;
      const v = chip.dataset.logtype;
      if (state.logs.logTypes.has(v)) {
        state.logs.logTypes.delete(v);
        chip.classList.remove('active');
      } else {
        state.logs.logTypes.add(v);
        chip.classList.add('active');
      }
      state.logs.page = 1;
      loadLogs();
    });

    // status
    const sCt = document.getElementById('logsStatusFilters');
    (facets.statuses || []).forEach(s => {
      const b = document.createElement('button');
      b.className = 'chip';
      b.dataset.statusval = s;
      b.textContent = s;
      sCt.appendChild(b);
    });
    sCt.addEventListener('click', e => {
      const chip = e.target.closest('.chip[data-statusval]');
      if (!chip) return;
      const v = chip.dataset.statusval;
      if (state.logs.statuses.has(v)) {
        state.logs.statuses.delete(v);
        chip.classList.remove('active');
      } else {
        state.logs.statuses.add(v);
        chip.classList.add('active');
      }
      state.logs.page = 1;
      loadLogs();
    });
  }

  function renderMeta() {
    const el = document.getElementById('logsCount');
    const start = state.logs.total === 0 ? 0 : (state.logs.page - 1) * state.logs.perPage + 1;
    const end   = Math.min(state.logs.page * state.logs.perPage, state.logs.total);
    el.textContent = `Zobrazeno ${start}–${end} z ${state.logs.total}`;
  }

  function renderSortIndicators() {
    document.querySelectorAll('#logsTable thead th').forEach(th => {
      th.classList.remove('sort-asc', 'sort-desc');
      if (th.dataset.key === state.logs.sort) {
        th.classList.add(state.logs.dir === 'asc' ? 'sort-asc' : 'sort-desc');
      }
    });
  }

  function renderTable() {
    const tbody = document.querySelector('#logsTable tbody');
    const rows = state.logs.data;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="muted ta-center">Žádné záznamy.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => {
      const userTxt = r.user_email
        ? `${esc(r.user_email)} <span class="muted">#${r.user_id}</span>`
        : (r.user_id ? '<span class="muted">#' + r.user_id + '</span>' : '<span class="muted">–</span>');
      const targetTxt = r.target_email
        ? `${esc(r.target_email)} <span class="muted">#${r.target_id}</span>`
        : (r.target_id ? '<span class="muted">#' + r.target_id + '</span>' : '<span class="muted">–</span>');
      const metaPreview = r.meta ? truncate(r.meta, 80) : '–';
      return `
        <tr data-id="${r.id}">
          <td class="ta-mono">${esc(fmtDate(r.created_at))}</td>
          <td><span class="log-type-badge ${logTypeClass(r.log_type)} cell-clickable" data-filter-logtype="${esc(r.log_type)}">${esc(r.log_type)}</span></td>
          <td><b>${esc(r.action)}</b></td>
          <td>${userTxt}</td>
          <td>${targetTxt}</td>
          <td><span class="status-badge ${statusClass(r.status)} cell-clickable" data-filter-status="${esc(r.status)}">${esc(r.status)}</span></td>
          <td class="ta-mono muted-text">${esc(r.ip_address || '–')}</td>
          <td class="log-meta-cell ta-mono">${esc(metaPreview)}</td>
          <td><button class="btn-small" data-act="open-detail" data-id="${r.id}">Detail</button></td>
        </tr>
      `;
    }).join('');
  }

  function truncate(s, n) {
    s = String(s);
    return s.length > n ? s.substring(0, n) + '…' : s;
  }

  function renderPagination() {
    const cur = state.logs.page;
    const tot = state.logs.pages;
    const html = paginationHtml(cur, tot);
    document.getElementById('logsPaginationTop').innerHTML    = html;
    document.getElementById('logsPaginationBottom').innerHTML = html;
  }

  function paginationHtml(cur, tot) {
    if (tot <= 1) return '';
    return [
      `<button class="page-btn" data-page="1" ${cur===1 ? 'disabled':''}>« 1</button>`,
      `<button class="page-btn" data-page="${cur-1}" ${cur===1 ? 'disabled':''}>‹ Předchozí</button>`,
      `<span class="page-info">Strana <b>${cur}</b> / ${tot}</span>`,
      `<button class="page-btn" data-page="${cur+1}" ${cur===tot ? 'disabled':''}>Další ›</button>`,
      `<button class="page-btn" data-page="${tot}" ${cur===tot ? 'disabled':''}>${tot} »</button>`,
    ].join('');
  }

  // ----- LOGS bindings -------------------------------------------------
  function bindLogs() {
    document.getElementById('logsSearch').addEventListener('input', e => {
      clearTimeout(state.logs.debounce);
      state.logs.debounce = setTimeout(() => {
        state.logs.q = e.target.value.trim();
        state.logs.page = 1;
        loadLogs();
      }, 250);
    });

    document.getElementById('logsFrom').addEventListener('change', e => {
      state.logs.from = e.target.value;
      state.logs.page = 1;
      loadLogs();
    });
    document.getElementById('logsTo').addEventListener('change', e => {
      state.logs.to = e.target.value;
      state.logs.page = 1;
      loadLogs();
    });

    document.getElementById('logsReset').addEventListener('click', () => {
      state.logs.q = '';
      state.logs.logTypes.clear();
      state.logs.statuses.clear();
      state.logs.from = '';
      state.logs.to   = '';
      state.logs.page = 1;
      state.logs.sort = 'created_at';
      state.logs.dir  = 'desc';
      document.getElementById('logsSearch').value = '';
      document.getElementById('logsFrom').value   = '';
      document.getElementById('logsTo').value     = '';
      document.querySelectorAll('#logsTypeFilters .chip, #logsStatusFilters .chip')
        .forEach(c => c.classList.remove('active'));
      loadLogs();
    });

    // Sortable headers
    document.querySelectorAll('#logsTable thead th[data-key]').forEach(th => {
      th.style.cursor = 'pointer';
      th.addEventListener('click', () => {
        const k = th.dataset.key;
        if (state.logs.sort === k) state.logs.dir = (state.logs.dir === 'asc' ? 'desc' : 'asc');
        else { state.logs.sort = k; state.logs.dir = 'desc'; }
        state.logs.page = 1;
        loadLogs();
      });
    });

    // Pagination
    ['logsPaginationTop','logsPaginationBottom'].forEach(id => {
      document.getElementById(id).addEventListener('click', e => {
        const btn = e.target.closest('button[data-page]');
        if (!btn || btn.disabled) return;
        const p = parseInt(btn.dataset.page, 10);
        if (!p) return;
        state.logs.page = p;
        loadLogs();
        document.getElementById('logsTable').scrollIntoView({ behavior:'smooth', block:'start' });
      });
    });

    // Table row clicks (badge filter, detail open)
    document.querySelector('#logsTable tbody').addEventListener('click', e => {
      const detailBtn = e.target.closest('button[data-act="open-detail"]');
      if (detailBtn) {
        const id = parseInt(detailBtn.dataset.id, 10);
        const row = state.logs.data.find(r => r.id === id);
        if (row) openLogModal(row);
        return;
      }
      const ltype = e.target.closest('[data-filter-logtype]');
      if (ltype) {
        const v = ltype.dataset.filterLogtype;
        if (!state.logs.logTypes.has(v)) {
          state.logs.logTypes.add(v);
          const chip = document.querySelector(`#logsTypeFilters .chip[data-logtype="${v}"]`);
          if (chip) chip.classList.add('active');
        }
        state.logs.page = 1;
        loadLogs();
        return;
      }
      const stat = e.target.closest('[data-filter-status]');
      if (stat) {
        const v = stat.dataset.filterStatus;
        if (!state.logs.statuses.has(v)) {
          state.logs.statuses.add(v);
          const chip = document.querySelector(`#logsStatusFilters .chip[data-statusval="${v}"]`);
          if (chip) chip.classList.add('active');
        }
        state.logs.page = 1;
        loadLogs();
        return;
      }
    });
  }

  // ----- MODAL ---------------------------------------------------------
  function openLogModal(r) {
    document.getElementById('logsModalId').textContent = '#' + r.id;
    let pretty = '';
    try { pretty = r.meta ? JSON.stringify(JSON.parse(r.meta), null, 2) : ''; }
    catch (_) { pretty = r.meta || ''; }

    document.getElementById('logsModalBody').innerHTML = `
      <div class="ev-detail-grid">
        <div><span class="muted-text">Čas</span><b>${esc(fmtDate(r.created_at))}</b></div>
        <div><span class="muted-text">Typ</span><span class="log-type-badge ${logTypeClass(r.log_type)}">${esc(r.log_type)}</span></div>
        <div><span class="muted-text">Akce</span><b>${esc(r.action)}</b></div>
        <div><span class="muted-text">Status</span><span class="status-badge ${statusClass(r.status)}">${esc(r.status)}</span></div>
        <div><span class="muted-text">User</span><b>${r.user_email ? esc(r.user_email) : '–'}</b> ${r.user_id ? `<span class="muted">#${r.user_id}</span>` : ''}</div>
        <div><span class="muted-text">Target</span><b>${r.target_email ? esc(r.target_email) : '–'}</b> ${r.target_id ? `<span class="muted">#${r.target_id}</span>` : ''}</div>
        <div><span class="muted-text">IP</span><span class="ta-mono">${esc(r.ip_address || '–')}</span></div>
        <div><span class="muted-text">ID záznamu</span><span class="ta-mono">#${r.id}</span></div>
      </div>
      ${pretty ? `<h4 class="ev-section-h">Meta JSON</h4><pre class="log-meta-pre">${esc(pretty)}</pre>` : ''}
    `;
    document.getElementById('logsModalBackdrop').classList.remove('hidden');
  }

  function bindModal() {
    const back = document.getElementById('logsModalBackdrop');
    document.getElementById('logsModalClose').addEventListener('click', () => back.classList.add('hidden'));
    back.addEventListener('click', e => { if (e.target === back) back.classList.add('hidden'); });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !back.classList.contains('hidden')) back.classList.add('hidden');
    });
  }

  // ----- SECURITY (preserved) -----------------------------------------
  async function loadSecurity() {
    try {
      const res = await apiFetch('/admin/api/security_stats.php?days=' + state.security.days);
      const d = res.data;

      const fill = (id, items, mapFn) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '';
        if (!items || !items.length) {
          el.innerHTML = '<tr><td colspan="3" class="muted ta-center">Žádné záznamy.</td></tr>';
          return;
        }
        el.innerHTML = items.map(mapFn).join('');
      };
      fill('securityBrute',    d.brute_ips,    r => `<tr><td class="ta-mono">${esc(r.ip)}</td><td>${r.fails}</td></tr>`);
      fill('securityRecent',   d.recent_fails, r => `<tr><td class="ta-mono">${esc(r.ip)}</td><td>${r.fails}</td></tr>`);
      fill('securityRate',     d.rate_limits,  r => `<tr><td class="ta-mono">${esc(r.ip)}</td><td>${r.blocks}</td></tr>`);
      fill('securityAccounts', d.accounts,     r => `<tr><td>${esc(r.email)}</td><td>${r.fails}</td></tr>`);
      fill('securityEconomy',  d.economy,      r => `<tr><td>${esc(r.user_id)}</td><td>${esc(r.action)}</td><td>${r.count}</td></tr>`);
    } catch (e) {
      console.error(e);
    }
  }

  function bindSecurity() {
    document.querySelectorAll('.sec-days-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        state.security.days = parseInt(btn.dataset.days, 10) || 7;
        document.getElementById('securityDaysLabel').textContent = state.security.days;
        document.querySelectorAll('.sec-days-btn').forEach(b => b.classList.toggle('active', b === btn));
        loadSecurity();
      });
    });
  }

  // ----- init ---------------------------------------------------------
  async function init() {
    if (typeof initCsrf === 'function') await initCsrf();
    bindSubtabs();
    bindLogs();
    bindModal();
    bindSecurity();
    await loadLogs();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
