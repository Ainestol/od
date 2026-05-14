/* =====================================
   ORDO DRACONIS — ADMIN: Economy panel
   Autocomplete picker + quick chips + reason + ledger
   ===================================== */
(() => {
  'use strict';

  const state = {
    user: null,                    // { id, email, vc, dc }
    currency: 'VOTE_COIN',
    quickAmt: 'custom',
    amount: 0,
    note: '',
    searchResults: [],
    debounceTimer: null,
    ledgerFilter: 'all',
  };

  // ----- helpers -----------------------------------------------------
  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(String(s).replace(' ','T'));
    if (isNaN(d.getTime())) return s;
    return d.getDate() + '. ' + (d.getMonth()+1) + '. ' + d.getFullYear()
      + ' ' + String(d.getHours()).padStart(2,'0')
      + ':' + String(d.getMinutes()).padStart(2,'0');
  }
  function toast(msg, kind='ok') {
    const t = document.getElementById('econToast');
    t.className = 'vip-toast show ' + (kind === 'err' ? 'err' : 'ok');
    t.textContent = msg;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => t.classList.remove('show'), 3500);
  }

  // ----- init --------------------------------------------------------
  async function init() {
    if (typeof initCsrf === 'function') await initCsrf();
    await loadStats();
    bindPicker();
    bindCurrency();
    bindQuickAdjust();
    bindAmountInput();
    bindNoteInput();
    bindAdjustButton();
    bindLedgerFilters();
    bindChipClear();
  }

  // ----- stats -------------------------------------------------------
  async function loadStats() {
    try {
      const res = await apiFetch('/admin/api/economy_stats.php');
      const t = res.totals || {};
      const h = res.holders || {};
      document.getElementById('econTotalVc').textContent   = t.VOTE_COIN ?? 0;
      document.getElementById('econHoldersVc').textContent = h.VOTE_COIN ?? 0;
      document.getElementById('econTotalDc').textContent   = t.DC ?? 0;
      document.getElementById('econHoldersDc').textContent = h.DC ?? 0;
    } catch (e) {
      console.error(e);
    }
  }

  // ----- picker (autocomplete) ---------------------------------------
  function bindPicker() {
    const input = document.getElementById('econSearch');
    const ac    = document.getElementById('econAutocomplete');

    input.addEventListener('input', () => {
      clearTimeout(state.debounceTimer);
      const q = input.value.trim();
      if (q.length < 1) { hideAc(); return; }
      state.debounceTimer = setTimeout(() => searchUser(q), 250);
    });

    input.addEventListener('focus', () => {
      const q = input.value.trim();
      if (q) searchUser(q);
    });

    document.addEventListener('click', e => {
      if (!ac.contains(e.target) && e.target !== input) hideAc();
    });

    ac.addEventListener('click', e => {
      const item = e.target.closest('.vip-autocomplete-item');
      if (!item) return;
      const idx = parseInt(item.dataset.idx, 10);
      const u = state.searchResults[idx];
      if (u) selectUser(u);
    });
  }

  async function searchUser(q) {
    const ac = document.getElementById('econAutocomplete');
    ac.innerHTML = '<div class="vip-autocomplete-loading">Hledám…</div>';
    ac.classList.add('open');
    try {
      // Reuse users_list — same data we need
      const res = await apiFetch('/api/admin/users_list.php?q=' + encodeURIComponent(q));
      state.searchResults = res.data || [];
      if (!state.searchResults.length) {
        ac.innerHTML = '<div class="vip-autocomplete-empty">Nic nenalezeno</div>';
        return;
      }
      ac.innerHTML = state.searchResults.map((u, i) => `
        <div class="vip-autocomplete-item" data-idx="${i}">
          <span class="ac-label">${esc(u.email)} <span class="ac-id">#${u.id}</span></span>
          <span class="ac-context">${u.game_account_count} účtů · ${u.character_count} postav</span>
          <span class="ac-extra">🪙 ${u.vc_balance} VC · 💎 ${u.dc_balance} DC</span>
        </div>
      `).join('');
    } catch (e) {
      ac.innerHTML = '<div class="vip-autocomplete-empty">Chyba: ' + esc(e.message) + '</div>';
    }
  }

  function selectUser(u) {
    state.user = {
      id: u.id,
      email: u.email,
      vc: u.vc_balance || 0,
      dc: u.dc_balance || 0,
    };
    document.getElementById('econSearch').value = '';
    hideAc();
    showSelectedChip();
    showDetail();
    loadLedger();
  }

  function hideAc() {
    document.getElementById('econAutocomplete').classList.remove('open');
  }

  function bindChipClear() {
    document.querySelector('#econSelectedChip .chip-clear').addEventListener('click', () => {
      state.user = null;
      document.getElementById('econSelectedChip').classList.add('hidden');
      document.querySelector('.econ-picker').classList.remove('hidden');
      document.getElementById('econDetail').classList.add('hidden');
      document.getElementById('econSearch').focus();
    });
  }

  function showSelectedChip() {
    const chip = document.getElementById('econSelectedChip');
    chip.querySelector('.chip-label').innerHTML =
      `<b>${esc(state.user.email)}</b> <span class="chip-id">#${state.user.id}</span>`;
    chip.classList.remove('hidden');
    document.querySelector('.econ-picker').classList.add('hidden');
  }

  // ----- detail panel -----------------------------------------------
  function showDetail() {
    document.getElementById('econDetail').classList.remove('hidden');
    document.getElementById('econUserEmail').textContent = state.user.email;
    refreshBalanceUI();
    updateAdjustButtonState();
  }

  function refreshBalanceUI() {
    document.getElementById('econUserVc').textContent = state.user.vc;
    document.getElementById('econUserDc').textContent = state.user.dc;
  }

  // ----- currency seg -----------------------------------------------
  function bindCurrency() {
    document.getElementById('econCurrencySeg').addEventListener('click', e => {
      const btn = e.target.closest('button[data-cur]');
      if (!btn) return;
      document.querySelectorAll('#econCurrencySeg button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.currency = btn.dataset.cur;
    });
  }

  // ----- quick adjust -----------------------------------------------
  function bindQuickAdjust() {
    document.getElementById('econQuickAdjust').addEventListener('click', e => {
      const btn = e.target.closest('button[data-amt]');
      if (!btn) return;
      document.querySelectorAll('#econQuickAdjust button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const v = btn.dataset.amt;
      const input = document.getElementById('econAmount');
      if (v === 'custom') {
        state.quickAmt = 'custom';
        input.focus();
      } else {
        state.quickAmt = v;
        input.value = v;
        state.amount = parseInt(v, 10);
      }
      updateAdjustButtonState();
    });
  }

  function bindAmountInput() {
    document.getElementById('econAmount').addEventListener('input', e => {
      state.amount = parseInt(e.target.value, 10) || 0;
      // mark "Vlastní…" as active if user types manually
      document.querySelectorAll('#econQuickAdjust button').forEach(b => b.classList.remove('active'));
      document.querySelector('#econQuickAdjust button[data-amt="custom"]').classList.add('active');
      state.quickAmt = 'custom';
      updateAdjustButtonState();
    });
  }

  function bindNoteInput() {
    document.getElementById('econNote').addEventListener('input', e => {
      state.note = e.target.value.trim();
    });
  }

  function updateAdjustButtonState() {
    document.getElementById('econAdjustBtn').disabled = !state.user || !state.amount || isNaN(state.amount);
  }

  // ----- adjust submit ----------------------------------------------
  function bindAdjustButton() {
    document.getElementById('econAdjustBtn').addEventListener('click', async () => {
      if (!state.user || !state.amount) return;

      // Big amount confirm
      if (Math.abs(state.amount) >= 50) {
        const sign = state.amount > 0 ? '+' : '';
        const cur  = state.currency === 'DC' ? 'DC' : 'VC';
        if (!confirm(`Opravdu změnit zůstatek o ${sign}${state.amount} ${cur} pro ${state.user.email}?`)) {
          return;
        }
      }

      const btn = document.getElementById('econAdjustBtn');
      btn.disabled = true;
      const original = btn.textContent;
      btn.textContent = 'Pracuje se…';

      try {
        await apiFetch('/admin/api/economy_adjust_balance.php', {
          method: 'POST',
          body: JSON.stringify({
            user_id:  state.user.id,
            currency: state.currency,
            amount:   state.amount,
            note:     state.note || null,
          }),
        });

        // Update local state
        if (state.currency === 'VOTE_COIN') state.user.vc += state.amount;
        else                                state.user.dc += state.amount;
        refreshBalanceUI();

        const sign = state.amount > 0 ? '+' : '';
        const cur  = state.currency === 'DC' ? 'DC' : 'VC';
        toast(`✓ ${sign}${state.amount} ${cur} pro ${state.user.email}`, 'ok');

        // Reset inputs
        document.getElementById('econAmount').value = '';
        document.getElementById('econNote').value = '';
        state.amount = 0;
        state.note = '';
        updateAdjustButtonState();

        // Refresh ledger + global stats
        await loadLedger();
        await loadStats();
      } catch (e) {
        toast('Chyba: ' + e.message, 'err');
      } finally {
        btn.textContent = original;
      }
    });
  }

  // ----- ledger -----------------------------------------------------
  async function loadLedger() {
    if (!state.user) return;
    const box = document.getElementById('econLedger');
    box.innerHTML = '<div class="muted">Načítám historii…</div>';
    try {
      const res = await apiFetch('/admin/api/economy_get_ledger.php?user_id=' + state.user.id);
      state.ledger = res.ledger || [];
      renderLedger();
    } catch (e) {
      box.innerHTML = '<div class="muted">Chyba: ' + esc(e.message) + '</div>';
    }
  }

  function renderLedger() {
    const box = document.getElementById('econLedger');
    let rows = state.ledger || [];
    if (state.ledgerFilter !== 'all') {
      rows = rows.filter(r => r.currency === state.ledgerFilter);
    }
    if (!rows.length) {
      box.innerHTML = '<div class="muted">Žádné záznamy.</div>';
      return;
    }
    box.innerHTML = `
      <table class="econ-ledger-table">
        <thead>
          <tr>
            <th>Datum</th>
            <th>Měna</th>
            <th class="ta-right">Změna</th>
            <th>Důvod</th>
            <th>Reference</th>
            <th>Poznámka</th>
          </tr>
        </thead>
        <tbody>
          ${rows.map(r => {
            const cls = r.amount > 0 ? 'amt-pos' : (r.amount < 0 ? 'amt-neg' : 'amt-zero');
            const sign = r.amount > 0 ? '+' : '';
            const ref = r.ref_type
              ? (r.ref_type + (r.ref_id ? ' #' + r.ref_id : ''))
              : '';
            const curBadge = r.currency === 'DC' ? 'dc' : 'vc';
            return `
              <tr>
                <td class="ta-mono">${esc(fmtDate(r.created_at))}</td>
                <td><span class="cur-badge ${curBadge}">${esc(r.currency)}</span></td>
                <td class="ta-right ${cls}">${sign}${r.amount}</td>
                <td>${esc(r.reason || '')}</td>
                <td class="muted-text">${esc(ref)}</td>
                <td>${esc(r.note || '')}</td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
    `;
  }

  function bindLedgerFilters() {
    document.getElementById('econLedgerFilters').addEventListener('click', e => {
      const chip = e.target.closest('button[data-cur-filter]');
      if (!chip) return;
      document.querySelectorAll('#econLedgerFilters .chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      state.ledgerFilter = chip.dataset.curFilter;
      renderLedger();
    });
  }

  // ----- go ----------------------------------------------------------
  document.addEventListener('DOMContentLoaded', init);
})();
