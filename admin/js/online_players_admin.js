/* =====================================
   ORDO DRACONIS — ADMIN: Online players
   Live list + offline-trade + stats + sortable + class names
   ===================================== */
(() => {
  'use strict';

  const REFRESH_MS = 5000;

  // L2 Interlude / Classic class id → name map
  const CLASS_NAMES = {
    0:'Fighter',1:'Warrior',2:'Gladiator',3:'Warlord',4:'Knight',5:'Paladin',
    6:'Dark Avenger',7:'Rogue',8:'Treasure Hunter',9:'Hawkeye',
    10:'Mage',11:'Wizard',12:'Sorcerer',13:'Necromancer',14:'Warlock',
    15:'Cleric',16:'Bishop',17:'Prophet',
    18:'Elven Fighter',19:'Elven Knight',20:'Temple Knight',21:'Sword Singer',
    22:'Elven Scout',23:'Plains Walker',24:'Silver Ranger',
    25:'Elven Mage',26:'Elven Wizard',27:'Spellsinger',28:'Elemental Summoner',
    29:'Elven Oracle',30:'Elven Elder',
    31:'Dark Fighter',32:'Palus Knight',33:'Shillien Knight',34:'Bladedancer',
    35:'Assassin',36:'Abyss Walker',37:'Phantom Ranger',
    38:'Dark Mage',39:'Dark Wizard',40:'Spellhowler',41:'Phantom Summoner',
    42:'Shillien Oracle',43:'Shillien Elder',
    44:'Orc Fighter',45:'Orc Raider',46:'Destroyer',47:'Orc Shaman',
    48:'Overlord',49:'Warcryer',
    50:'Dwarven Fighter',51:'Scavenger',52:'Bounty Hunter',53:'Artisan',54:'Warsmith',
    88:'Duelist',89:'Dreadnought',90:'Phoenix Knight',91:'Hell Knight',
    92:'Sagittarius',93:'Adventurer',94:'Archmage',95:'Soultaker',
    96:'Arcana Lord',97:'Cardinal',98:'Hierophant',
    99:"Eva's Templar",100:'Sword Muse',101:'Wind Rider',102:'Moonlight Sentinel',
    103:'Mystic Muse',104:'Elemental Master',105:"Eva's Saint",
    106:'Shillien Templar',107:'Spectral Dancer',108:'Ghost Hunter',109:'Ghost Sentinel',
    110:'Storm Screamer',111:'Spectral Master',112:'Shillien Saint',
    113:'Titan',114:'Grand Khavatari',115:'Dominator',116:'Doomcryer',
    117:'Fortune Seeker',118:'Maestro',
  };

  const state = {
    online: [],
    offlineTrade: [],
    sort: { key: 'level', dir: -1 },
    paused: false,
    countdown: 0,
    pollTimer: null,
    countdownTimer: null,
  };

  // ----- helpers -------------------------------------------------------
  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function className(id) { return CLASS_NAMES[id] || ('Class #' + id); }
  function fmtSecs(sec) {
    if (!sec) return '0h 0m';
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    return h + 'h ' + m + 'm';
  }
  function fmtSinceMs(ms) {
    if (!ms || ms <= 0) return '-';
    const d = new Date(ms);
    return d.getDate() + '. ' + (d.getMonth()+1) + '. '
         + String(d.getHours()).padStart(2,'0') + ':'
         + String(d.getMinutes()).padStart(2,'0');
  }
  function tradeTypeLabel(t) {
    // L2 offline trade type: 1=sell, 3=buy, 8=manufacture/package; show roughly
    switch (Number(t)) {
      case 1: return 'Prodej';
      case 3: return 'Nákup';
      case 8: return 'Výroba';
      default: return 'Typ ' + t;
    }
  }

  // ----- loading --------------------------------------------------------
  async function loadData() {
    document.getElementById('refreshStatus').textContent = 'Načítám…';
    try {
      const res = await apiFetch('/admin/api/list_online_players.php?t=' + Date.now());
      state.online       = res.online        || [];
      state.offlineTrade = res.offline_trade || [];
      renderStats(res.stats || {});
      renderOnline();
      renderOfflineTrade();
    } catch (e) {
      console.error(e);
      document.getElementById('refreshStatus').textContent = 'Chyba: ' + e.message;
    }
  }

  function renderStats(s) {
    document.getElementById('onlTotal').textContent       = s.online_total ?? 0;
    document.getElementById('onlUniqUsers').textContent   = s.online_unique_web_users ?? 0;
    document.getElementById('onlGm').textContent          = s.online_gm ?? 0;
    document.getElementById('onlTrade').textContent       = s.offline_trade_total ?? 0;
    const b = s.online_by_level || {};
    document.getElementById('onlLv1').textContent = b['1-39'] ?? 0;
    document.getElementById('onlLv2').textContent = b['40-59'] ?? 0;
    document.getElementById('onlLv3').textContent = b['60-79'] ?? 0;
    document.getElementById('onlLv4').textContent = b['80+']   ?? 0;
  }

  // ----- online table ---------------------------------------------------
  function renderOnline() {
    const tbody = document.querySelector('#onlineTable tbody');
    const rows = sortRows(state.online, state.sort.key, state.sort.dir);
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="muted ta-center">Nikdo není online.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(p => {
      const gm = p.is_gm
        ? '<span class="gm-badge">GM</span>'
        : '';
      const webHtml = p.web_email
        ? `<a class="link" href="/admin/users.html?q=${encodeURIComponent(p.web_email)}" title="Otevřít v Uživatelé">${esc(p.web_email)}</a>`
        : '<span class="muted">–</span>';
      const onlineCls = p.onlineTime >= 8*3600 ? 'time-warn' : (p.onlineTime >= 2*3600 ? 'time-mid' : 'time-ok');
      return `
        <tr>
          <td>${esc(p.char_name)} ${gm}</td>
          <td class="ta-right">${p.level}</td>
          <td>${esc(className(p.classid))} <span class="muted">#${p.classid}</span></td>
          <td>${esc(p.account_name || '')}</td>
          <td>${webHtml}</td>
          <td class="ta-right ${onlineCls}">${fmtSecs(p.onlineTime)}</td>
        </tr>
      `;
    }).join('');
  }

  function renderOfflineTrade() {
    const tbody = document.querySelector('#offlineTable tbody');
    if (!state.offlineTrade.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="muted ta-center">Žádné offline obchody.</td></tr>';
      return;
    }
    tbody.innerHTML = state.offlineTrade.map(p => {
      const webHtml = p.web_email
        ? `<a class="link" href="/admin/users.html?q=${encodeURIComponent(p.web_email)}" title="Otevřít v Uživatelé">${esc(p.web_email)}</a>`
        : '<span class="muted">–</span>';
      const typeBadge = `<span class="trade-type-badge type-${p.type}">${esc(tradeTypeLabel(p.type))}</span>`;
      return `
        <tr>
          <td>${esc(p.char_name || '–')}</td>
          <td class="ta-right">${p.level || '–'}</td>
          <td>${esc(className(p.classid))} <span class="muted">#${p.classid}</span></td>
          <td>${typeBadge}</td>
          <td class="trade-title">${esc(p.title || '')}</td>
          <td>${esc(p.account_name || '')}</td>
          <td>${webHtml}</td>
          <td>${fmtSinceMs(p.since_ms)}</td>
        </tr>
      `;
    }).join('');
  }

  // ----- sort -----------------------------------------------------------
  function sortRows(rows, key, dir) {
    if (!key) return rows;
    const copy = [...rows];
    copy.sort((a, b) => {
      let v1 = a[key], v2 = b[key];
      if (v1 == null) v1 = '';
      if (v2 == null) v2 = '';
      if (typeof v1 === 'string' || typeof v2 === 'string') {
        return String(v1).localeCompare(String(v2)) * dir;
      }
      return (v1 - v2) * dir;
    });
    return copy;
  }

  function bindSortHeaders() {
    document.querySelectorAll('#onlineTable th[data-key]').forEach(th => {
      th.style.cursor = 'pointer';
      th.addEventListener('click', () => {
        const k = th.dataset.key;
        if (state.sort.key === k) state.sort.dir = -state.sort.dir;
        else state.sort = { key: k, dir: -1 };
        // update sort indicator
        document.querySelectorAll('#onlineTable th').forEach(x => x.classList.remove('sort-asc','sort-desc'));
        th.classList.add(state.sort.dir > 0 ? 'sort-asc' : 'sort-desc');
        renderOnline();
      });
    });
  }

  // ----- polling --------------------------------------------------------
  function startPolling() {
    stopPolling();
    state.countdown = REFRESH_MS / 1000;
    updateRefreshStatus();
    state.countdownTimer = setInterval(() => {
      state.countdown--;
      if (state.countdown <= 0) {
        loadData();
        state.countdown = REFRESH_MS / 1000;
      }
      updateRefreshStatus();
    }, 1000);
  }
  function stopPolling() {
    if (state.countdownTimer) clearInterval(state.countdownTimer);
    state.countdownTimer = null;
  }

  function updateRefreshStatus() {
    const el = document.getElementById('refreshStatus');
    if (state.paused) {
      el.textContent = '⏸ Pozastaveno';
    } else {
      el.textContent = 'Obnovení za ' + state.countdown + ' s';
    }
  }

  function bindControls() {
    document.getElementById('refreshPause').addEventListener('click', () => {
      state.paused = !state.paused;
      const btn = document.getElementById('refreshPause');
      btn.textContent = state.paused ? 'Spustit' : 'Pauza';
      if (state.paused) stopPolling();
      else startPolling();
      updateRefreshStatus();
    });
    document.getElementById('refreshNow').addEventListener('click', () => {
      loadData();
      if (!state.paused) {
        state.countdown = REFRESH_MS / 1000;
      }
    });
  }

  // ----- init -----------------------------------------------------------
  async function init() {
    if (typeof initCsrf === 'function') await initCsrf();
    bindSortHeaders();
    bindControls();
    await loadData();
    startPolling();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
