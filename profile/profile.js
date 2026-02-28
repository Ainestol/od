/* profile.js – shared for index.html + index-en.html */
(() => {
'use strict';

let CSRF_READY = false;

async function initCsrf() {
  const res = await fetch('/api/csrf_token.php', { credentials: 'same-origin' });
  const data = await res.json().catch(() => ({}));

  if (data.ok) {
    window.CSRF_TOKEN = data.token;
    CSRF_READY = true;
  } else {
    console.error('Failed to load CSRF token');
  }
}

/* 🔥 ZABLOKUJ start dokud není token */
(async function bootstrap() {
  await initCsrf();
})();
  /* ---- GLOBAL FETCH WRAPPER (CSRF) ---- */
const _origFetch = window.fetch;

window.fetch = function (url, options = {}) {
  if (options.method && options.method.toUpperCase() === 'POST') {

    if (!window.CSRF_TOKEN) {
      console.error('CSRF token not ready');
    }

    options.headers = options.headers || {};
    options.headers['X-CSRF-TOKEN'] = window.CSRF_TOKEN || '';
  }

  return _origFetch(url, options);
};
  /* -----------------------------
   * helpers + i18n
   * ----------------------------- */
  const isEn = ((document.documentElement.lang || '').toLowerCase() === 'en');

  const T = {
    // generic
    loadingAccounts: isEn ? 'Loading game accounts…' : 'Načítám herní účty…',
    serverConnErr: isEn ? 'Server connection error.' : 'Chyba spojení se serverem.',
    unableLoad: isEn ? 'Unable to load accounts.' : 'Nepodařilo se načíst účty.',
    noAccounts: isEn ? 'You have no game accounts yet.' : 'Zatím nemáš žádné herní účty.',
    vipOk: isEn ? 'VIP activated' : 'VIP aktivováno',
    vipErr: isEn ? 'VIP activation failed' : 'Aktivace VIP se nezdařila',

    // account actions
    accCreated: isEn ? 'Game account created' : 'Herní účet byl vytvořen',
    accCreateErr: isEn ? 'Failed to create account' : 'Chyba při vytváření účtu',

    accDeleted: isEn ? 'Game account deleted' : 'Herní účet byl smazán',
    accHasChars: isEn ? 'Account has active characters' : 'Účet má aktivní postavy',
    accDeleteFail: isEn ? 'Failed to delete account' : 'Nepodařilo se smazat účet',

    pwChanged: isEn ? 'Password changed' : 'Heslo bylo změněno',
    pwResetFail: isEn ? 'Failed to reset password' : 'Nepodařilo se resetovat heslo',

    primarySetOk: isEn ? 'Primary account set' : 'Primární účet nastaven',
    primarySetFail: isEn ? 'Failed to set primary account' : 'Nastavení primárního účtu se nezdařilo',

    // characters
    loadingChars: isEn ? 'Loading characters…' : 'Načítám postavy…',
    noChars: isEn ? 'No characters' : 'Žádné postavy',
    charsLoadFail: isEn ? 'Failed to load characters' : 'Nepodařilo se načíst postavy',

    // bug form
    noBugs: isEn ? 'No reports' : 'Žádná hlášení',
    bugSent: isEn ? 'Bug report sent' : 'Bug report odeslán',
    bugSendErr: isEn ? 'Failed to send bug report' : 'Chyba při odesílání',
    textTooLong: isEn ? 'Text is too long (max 1000 chars)' : 'Text je příliš dlouhý (max. 1000 znaků)',

    // VIP modal
    activating: isEn ? 'Activating...' : 'Aktivuji...',
    activate: isEn ? 'Activate' : 'Aktivovat',

    // conversion
    convertConfirm: isEn ? 'Convert 4 Vote Coin into 1 Dragon Coin?' : 'Převést 4 Vote Coin na 1 Dragon Coin?',
    processing: isEn ? 'Processing...' : 'Zpracovávám...',
    convertLabel: isEn ? 'Convert 4 Vote Coin → 1 Dragon Coin' : 'Převést 4 Vote Coin → 1 Dragon Coin',

    // shop
    shopLoading: isEn ? 'Loading products…' : 'Načítám produkty…',
    shopNone: isEn ? 'No products.' : 'Žádné produkty.',
    buyOk: isEn ? 'Purchase complete' : 'Nákup dokončen',
    buyErr: isEn ? 'Purchase failed' : 'Chyba nákupu',
    needAcc: isEn ? 'Select game account' : 'Vyber herní účet',
    insufficient: isEn ? 'Not enough DC' : 'Nedostatek DC',
    alreadyBought: isEn ? 'Already purchased' : 'Už koupeno',

    // vote
    voteNoSites: isEn ? 'No active vote sites.' : 'Žádné aktivní vote weby.',
    voteReady: 'READY',
    voteCooldown: isEn ? 'Vote is on cooldown.' : 'Vote je v cooldownu.',
    voteCooldownLeft: isEn ? 'Cooldown remaining: ' : 'Zbývá cooldown: ',
    voteStartErr: isEn ? 'Failed to start vote.' : 'Nepodařilo se spustit vote.',
    voteOpenHint: isEn ? 'Vote page opened. Waiting for verification…' : 'Vote stránka otevřena. Čekám na ověření…',
    voteNeedConfirm: isEn ? 'Confirm you voted?' : 'Potvrdit, že jsi hlasoval?',
    voteRewarded: isEn ? 'Vote Coin added!' : 'Vote Coin připsán!',
    votePending: isEn ? 'Not detected yet. Try again later.' : 'Zatím nedetekováno. Zkus to později.',
    unknownErr: isEn ? 'Unknown error.' : 'Neznámá chyba.'
    
  };

  function qs(sel, root = document) { return root.querySelector(sel); }
  function qsa(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

  /* -----------------------------
   * notify
   * ----------------------------- */
function notify(type, message, timeout = 3000) {
  let box = document.getElementById('notifications');

  // když v HTML není, vytvoříme ho automaticky
  if (!box) {
    box = document.createElement('div');
    box.id = 'notifications';
    document.body.appendChild(box);
  }

  const el = document.createElement('div');
  el.className = `notify ${type}`;
  el.textContent = message;

  box.appendChild(el);

  setTimeout(() => {
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
  }, timeout);
}

  // expose notify if some legacy inline code expects it
  window.notify = window.notify || notify;

   /* -----------------------------
   * ME + redirect + VIP box + admin btn
   * ----------------------------- */
  let meCache = null;

  async function fetchMe(force = false) {
    if (meCache && !force) return meCache;
    const res = await fetch('/api/me.php', { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    meCache = data;
    return data;
  }

  function redirectToLogin() {
    window.location.href = isEn ? '/auth/login-en.html' : '/auth/login.html';
  }

  async function initMeAndUi(force = false) {
    try {
      const me = await fetchMe(force);

      if (!me || !me.ok) {
        redirectToLogin();
        return;
      }

      const container = qs('.auth-container.profile-shell') || qs('.auth-container');
      if (container) {
        const old = container.querySelector('.profile-vip-box');
        if (old) old.remove();

        if (me.web_vip) {
          const vipBox = document.createElement('div');
          vipBox.className = 'profile-vip-box';
          vipBox.innerHTML = `
            <img class="vip-icon" src="/img/drak.png" alt="VIP">
            <div>
              <div class="vip-title">${isEn ? 'WEB VIP active' : 'WEB VIP aktivní'}</div>
              <div class="vip-meta">
                ${isEn ? 'Valid until' : 'Platí do'}: ${me.web_vip.end_at}<br>
                ${isEn ? 'Days left' : 'Zbývá dní'}: ${me.web_vip.days_left}
              </div>
            </div>
          `;
          container.prepend(vipBox);
        }
      }

      if (me.role === 'admin') {
        const btn = document.getElementById('adminBtn');
        if (btn) btn.style.display = 'inline-flex';
      }
    } catch (e) {
      redirectToLogin();
    }
  }

  window.refreshMeAndUi = window.refreshMeAndUi || (async () => initMeAndUi(true));

   /* -----------------------------
   * tabs (main profile tabs)
   * ----------------------------- */
  function initTabs() {
    const tabs = qsa('.profile-shell > .profile-tabs .tab');
    const panels = qsa('.profile-panels .panel');

    if (!tabs.length || !panels.length) return;

    const show = (key) => {
      tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === key));
      panels.forEach(p => p.classList.toggle('active', p.id === key));
    };

    tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.tab)));

    const first = qs('.profile-shell > .profile-tabs .tab.active') || tabs[0];
    if (first) show(first.dataset.tab);

    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab) {
      const btn = qs(`.profile-shell > .profile-tabs [data-tab="${tab}"]`);
      if (btn) btn.click();
    }
  }

  /* -----------------------------
   * game accounts list
   * ----------------------------- */
  async function loadGameAccounts() {
    const list = document.getElementById('gameAccountsList');
    const countEl = document.getElementById('accountsCount');
    if (!list) return;

    list.innerHTML = `<div class="muted">${T.loadingAccounts}</div>`;

    let res;
    try {
      res = await fetch('/api/list_game_accounts.php', { credentials: 'same-origin' });
    } catch (e) {
      list.innerHTML = `<div class="form-error">${T.serverConnErr}</div>`;
      if (countEl) countEl.textContent = '(0 / 10)';
      return;
    }

    if (!res.ok) {
      list.innerHTML = `<div class="form-error">${T.unableLoad}</div>`;
      if (countEl) countEl.textContent = '(0 / 10)';
      return;
    }

    const data = await res.json().catch(() => ({}));

    if (countEl) {
      const n = Array.isArray(data.accounts) ? data.accounts.length : 0;
      countEl.textContent = `(${n} / 10)`;
    }

    list.innerHTML = '';

    if (!data.ok || !Array.isArray(data.accounts) || !data.accounts.length) {
      list.innerHTML = `<div class="muted">${T.noAccounts}</div>`;
      return;
    }

    data.accounts.forEach(acc => {
      const row = document.createElement('div');
      row.className = 'mini-row';

      const premiumTag = (() => {
  const tInactive = isEn ? 'Premium: inactive' : 'Premium: neaktivní';
  const tExpired  = isEn ? 'Premium: expired'  : 'Premium: expirováno';
  const tDays     = (n) => isEn ? `${n} days` : `${n} ${n === 1 ? 'den' : (n >= 2 && n <= 4 ? 'dny' : 'dní')}`;

  if (acc.premium_days_left === null) {
    return `<span class="tag muted">${tInactive}</span>`;
  }
  const left = Number(acc.premium_days_left);
  if (left < 0) return `<span class="tag danger">${tExpired}</span>`;
  if (left <= 3) return `<span class="tag warning">Premium: ${tDays(left)}</span>`;
  return `<span class="tag success">Premium: ${tDays(left)}</span>`;
})();

      row.innerHTML = `
        <div class="account-row" data-login="${acc.login}">
          <strong>${isEn ? 'Account' : 'Účet'}:</strong> ${acc.login}

          <span class="tag">${acc.chars_count} ${isEn ? 'characters' : 'postav'}</span>
          ${premiumTag}
          ${
            acc.premium_end_at
              ? `<span class="tag">${acc.premium_end_at}</span>`
              : `<span class="tag">${acc.created_at}</span>`
          }

          <div class="actions">
            <button class="btn btn-small btn-danger" data-login="${acc.login}">Delete</button>
            <button class="btn btn-small" data-reset="${acc.login}">Change password</button>
          </div>
        </div>

        <div class="char-list hidden" id="chars-${acc.login}"></div>
      `;

      list.appendChild(row);
    });
  }

  // expose for other internal calls
  window.loadGameAccounts = window.loadGameAccounts || loadGameAccounts;

  /* -----------------------------
   * create account modal
   * ----------------------------- */
  function initCreateAccountModal() {
    const modal = document.getElementById('accModal');
    const openBtn = document.getElementById('createAccBtn');
    const cancelBtn = document.getElementById('accCancel');
    const submitBtn = document.getElementById('accSubmit');
    const msg = document.getElementById('accMsg');

    const login = document.getElementById('accLogin');
    const pass = document.getElementById('accPass');

    if (!modal) return;

    const open = () => {
      modal.classList.remove('hidden');
      if (msg) { msg.style.display = 'none'; msg.textContent = ''; }
    };
    const close = () => modal.classList.add('hidden');

    openBtn?.addEventListener('click', (e) => { e.preventDefault(); open(); });
    cancelBtn?.addEventListener('click', (e) => { e.preventDefault(); close(); });
    modal?.addEventListener('click', (e) => { if (e.target === modal) close(); });

    submitBtn?.addEventListener('click', async (e) => {
      e.preventDefault();
      if (!login || !pass || !submitBtn) return;

      msg && (msg.style.display = 'none');

      const payload = {
        login: login.value.trim(),
        password: pass.value
      };

      submitBtn.disabled = true;
      try {
        const res = await fetch('/api/create_game_account.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });

        const data = await res.json().catch(() => ({}));

        if (data.ok) {
          modal.classList.add('hidden');
          notify('success', T.accCreated);

          pass.value = '';
          login.value = '';

          await loadGameAccounts();
          setTimeout(() => modal.classList.add('hidden'), 800);
        } else {
          notify('error', data.error || T.accCreateErr);
        }
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

  /* -----------------------------
   * delete account modal (keyword confirm)
   * ----------------------------- */
  function initDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const input = document.getElementById('deleteConfirmInput');
    const confirmBtn = document.getElementById('deleteConfirm');
    const cancelBtn = document.getElementById('deleteCancel');

    if (!modal || !input || !confirmBtn || !cancelBtn) return;

    let currentLogin = null;
    const keyword = isEn ? 'delete' : 'smazat';

    document.addEventListener('click', (e) => {
      // only buttons with data-login inside account actions should open delete modal
      const btn = e.target.closest('button[data-login]');
      if (!btn) return;

      // but avoid catching the "delete" open on other places if any:
      // here we assume this is the delete button; ok.

      currentLogin = btn.dataset.login;

      const loginEl = document.getElementById('deleteLogin');
      const keywordEl = document.getElementById('deleteKeyword');
      if (loginEl) loginEl.textContent = currentLogin || '';
      if (keywordEl) keywordEl.textContent = keyword;

      input.value = '';
      confirmBtn.disabled = true;
      modal.classList.remove('hidden');
    });

    input.addEventListener('input', () => {
      confirmBtn.disabled = input.value.trim() !== keyword;
    });

    cancelBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
    });

    confirmBtn.addEventListener('click', async () => {
      if (!currentLogin) return;

      confirmBtn.disabled = true;

      const res = await fetch('/api/delete_game_account.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ login: currentLogin })
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        modal.classList.add('hidden');
        notify('success', T.accDeleted);
        await loadGameAccounts();
        return;
      }

      if (data.error === 'ACCOUNT_HAS_ACTIVE_CHARACTERS') {
        notify('error', T.accHasChars);
      } else {
        notify('error', T.accDeleteFail);
      }

      confirmBtn.disabled = false;
    });
  }

  /* -----------------------------
   * reset password modal
   * ----------------------------- */
  function initResetPasswordModal() {
    const modal = document.getElementById('resetModal');
    const loginEl = document.getElementById('resetLogin');
    const pass1 = document.getElementById('resetPass1');
    const pass2 = document.getElementById('resetPass2');
    const confirmBtn = document.getElementById('resetConfirm');
    const cancelBtn = document.getElementById('resetCancel');

    if (!modal || !loginEl || !pass1 || !pass2 || !confirmBtn || !cancelBtn) return;

    let currentLogin = null;

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-reset]');
      if (!btn) return;

      currentLogin = btn.dataset.reset;
      loginEl.textContent = currentLogin || '';
      pass1.value = '';
      pass2.value = '';
      confirmBtn.disabled = true;

      modal.classList.remove('hidden');
    });

    const validate = () => {
      confirmBtn.disabled = pass1.value.length < 6 || pass1.value !== pass2.value;
    };

    pass1.addEventListener('input', validate);
    pass2.addEventListener('input', validate);

    cancelBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
    });

    confirmBtn.addEventListener('click', async () => {
      if (!currentLogin) return;

      confirmBtn.disabled = true;

      const res = await fetch('/api/reset_game_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          login: currentLogin,
          password: pass1.value
        })
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        modal.classList.add('hidden');
        notify('success', T.pwChanged);
        return;
      }

      notify('error', data.error || T.pwResetFail);
      confirmBtn.disabled = false;
    });
  }

  
  /* -----------------------------
   * bug account select + bug form submit
   * ----------------------------- */
  function initBugForm() {
    const accountSelect = document.getElementById('bugAccount');
    if (accountSelect) {
      fetch('/api/list_game_accounts.php', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
          if (!data.ok || !Array.isArray(data.accounts)) return;
          data.accounts.forEach(acc => {
            const opt = document.createElement('option');
            opt.value = acc.login;
            opt.textContent = acc.login;
            accountSelect.appendChild(opt);
          });
        })
        .catch(() => {});
    }

    const bugForm = document.getElementById('bugForm');
    bugForm?.addEventListener('submit', async (e) => {
      e.preventDefault();

      const payload = {
        game_account: document.getElementById('bugAccount')?.value || '',
        category: document.getElementById('bugCategory')?.value || '',
        title: (document.getElementById('bugTitle')?.value || '').trim(),
        message: (document.getElementById('bugMessage')?.value || '').trim()
      };

      if (payload.message.length > 1000) {
        notify('error', T.textTooLong);
        return;
      }

      const res = await fetch('/api/create_bug_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        notify('success', T.bugSent);
        e.target.reset();
      } else {
        notify('error', data.error || T.bugSendErr);
      }
    });
  }

  /* -----------------------------
   * bug message counter
   * ----------------------------- */
  function initBugCounter() {
    const textarea = document.getElementById('bugMessage');
    const counter = document.getElementById('bugCounter');
    const max = 1000;

    if (!textarea || !counter) return;

    const update = () => {
      const len = textarea.value.length;
      counter.textContent = `${len} / ${max}`;

      counter.classList.remove('warning', 'danger');
      if (len > 950) counter.classList.add('danger');
      else if (len > 800) counter.classList.add('warning');
    };

    textarea.addEventListener('input', update);
    update();
  }

  /* -----------------------------
   * my bugs list
   * ----------------------------- */
  function initMyBugsList() {
    const box = document.getElementById('myBugs');
    if (!box) return;

    fetch('/api/list_my_bug_reports.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data.ok || !Array.isArray(data.bugs) || !data.bugs.length) {
          box.innerHTML = `<div class="muted">${T.noBugs}</div>`;
          return;
        }

        box.innerHTML = '';
        data.bugs.forEach(bug => {
          const row = document.createElement('a');
          row.href = `/profile/bug_detail.html?id=${bug.id}&lang=${isEn ? 'en' : 'cs'}`;
          row.className = 'link-item';
          row.innerHTML = `
            <strong>[${String(bug.status || '').toUpperCase()}]</strong>
            ${bug.title}
          `;
          box.appendChild(row);
        });
      })
      .catch(() => {
        box.innerHTML = `<div class="muted">${T.noBugs}</div>`;
      });
  }

  /* -----------------------------
   * characters VIP map + expand list
   * ----------------------------- */
  async function loadVipMap(login) {
    const res = await fetch(`/api/list_characters_with_vip.php?account=${encodeURIComponent(login)}`, {
      credentials: 'same-origin'
    });
    const data = await res.json().catch(() => ({}));

    const map = {};
    if (data.ok && Array.isArray(data.characters)) {
      data.characters.forEach(ch => {
        map[ch.charId] = {
          hasVip: !!ch.has_vip,
          endAt: ch.vip_end_at || null
        };
      });
    }
    return map;
  }

  function initCharactersToggle() {
    document.addEventListener('click', async (e) => {
      const row = e.target.closest('.account-row');
      if (!row) return;

      // If click was on action buttons (delete/reset/primary), ignore toggle
      if (e.target.closest('button')) return;

      const login = row.dataset.login;
      if (!login) return;

      const box = document.getElementById('chars-' + login);
      if (!box) return;

      // toggle close
      if (!box.classList.contains('hidden')) {
        box.classList.add('hidden');
        box.innerHTML = '';
        return;
      }

      box.classList.remove('hidden');
      box.innerHTML = `<div class="muted">${T.loadingChars}</div>`;

      try {
        const vipMap = await loadVipMap(login);
        const res = await fetch(`/api/list_characters.php?account=${encodeURIComponent(login)}`, {
          credentials: 'same-origin'
        });
        const data = await res.json().catch(() => ({}));

        if (!data.ok || !Array.isArray(data.characters) || !data.characters.length) {
          box.innerHTML = `<div class="muted">${T.noChars}</div>`;
          return;
        }

        box.innerHTML = '';

        data.characters.forEach(ch => {
          const el = document.createElement('div');
          el.className = 'char-row';

          const vipData = vipMap[ch.charId];
          let vipTag = '';
          if (vipData && vipData.hasVip) {
            vipTag = `<span class="tag vip">VIP do ${vipData.endAt}</span>`;
          }

          el.innerHTML = `
            <span><strong>${ch.char_name}</strong></span>
            <span>Lv ${ch.level}</span>
            <span class="${ch.online ? 'online' : 'offline'}">
              ${ch.online ? 'ONLINE' : 'offline'}
            </span>
            ${vipTag}
          `;
          box.appendChild(el);
        });
      } catch (err) {
        box.innerHTML = `<div class="form-error">${T.charsLoadFail}</div>`;
      }
    });
  }

  /* -----------------------------
   * vote (status/start/check)
   * ----------------------------- */
  let voteCache = [];
  let voteBusy = false;

  async function loadVoteSites() {
    try {
      const res = await fetch('/api/vote_status.php', { credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!data.ok) return;

      voteCache = data.sites || [];

      const box = qs('#vote .link-list');
      if (!box) return;

      box.innerHTML = '';

      if (!voteCache.length) {
        box.innerHTML = `<div class="muted">${T.voteNoSites}</div>`;
        return;
      }

      voteCache.forEach(site => {
        const el = document.createElement('div');

        const status = (() => {
          const rem = Number(site.remaining || 0);
          if (rem <= 0) return T.voteReady;

          const h = Math.floor(rem / 3600);
          const m = Math.floor((rem % 3600) / 60);

          if (h <= 0) return `${m}m`;
          return `${h}h ${m}m`;
        })();

        const disabled = Number(site.remaining || 0) > 0 ? 'disabled' : '';

        el.innerHTML = `
          <button class="btn vote-btn" data-id="${site.id}" ${disabled}>
            ${site.name} – ${status}
          </button>
        `;
        box.appendChild(el);
      });
    } catch (err) {
      console.error('Vote load error:', err);
    }
  }

  async function pollVote(attemptId) {
    const start = Date.now();
    const maxMs = 90000;
    const waitMs = 4000;
    let manualConfirm = false;
    let askedConfirm = false;

    while (Date.now() - start < maxMs) {
      const res = await fetch('/api/vote_check.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ attempt_id: attemptId, confirm: manualConfirm })
      });

      const data = await res.json().catch(() => ({}));

      if (!data.ok) {
        if (data.error === 'COOLDOWN') {
          notify('error', T.voteCooldown);
          await loadVoteSites();
          return;
        }
        notify('error', data.error || T.unknownErr);
        return;
      }

      if (data.status === 'REWARDED' || data.status === 'USED') {
        notify('success', T.voteRewarded);
        await loadVoteSites();
        if (typeof window.loadVoteBalance === 'function') window.loadVoteBalance();
        return;
      }

      if (data.status === 'WAITING_CONFIRM' && !askedConfirm) {
        askedConfirm = true;
        manualConfirm = confirm(T.voteNeedConfirm);
      }

      await new Promise(r => setTimeout(r, waitMs));
    }

    notify('error', T.votePending);
    await loadVoteSites();
  }

  async function startVote(siteId, btnEl) {
    if (voteBusy) return;
    voteBusy = true;

    try {
      if (btnEl) btnEl.disabled = true;

      const res = await fetch('/api/vote_start.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ site_id: siteId })
      });

      const data = await res.json().catch(() => ({}));

      if (!data.ok) {
        if (data.error === 'COOLDOWN') {
          const rem = typeof data.remaining === 'number' ? data.remaining : 0;
          notify('error', `${T.voteCooldown} ${T.voteCooldownLeft}${rem}s`);
          await loadVoteSites();
          return;
        }
        notify('error', `${T.voteStartErr} ${data.error || ''}`.trim());
        return;
      }

      window.open(data.vote_url, '_blank');
      notify('success', T.voteOpenHint, 3500);

      await pollVote(data.attempt_id);

    } catch (err) {
      console.error('Vote start error:', err);
      notify('error', T.voteStartErr);
    } finally {
      voteBusy = false;
      if (btnEl) btnEl.disabled = false;
    }
  }

  function initVote() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.vote-btn');
      if (!btn) return;

      const id = parseInt(btn.dataset.id, 10);
      if (!id) return;

      startVote(id, btn);
    });
  }

  /* -----------------------------
   * balances
   * ----------------------------- */
async function loadVoteBalance() {
  try {
    const res = await fetch('/api/get_wallet_balance.php?currency=VOTE_COIN');
    const data = await res.json();
    if (!data.ok) return;

    const el = document.querySelector('#voteBalance strong');
    if (el) el.textContent = data.balance;

    const top = document.querySelector('#voteBalanceTop strong');
    if (top) top.textContent = data.balance;

  } catch (err) {
    console.error('Vote balance error:', err);
  }
}

  async function loadDcBalance() {
  try {
    const res = await fetch('/api/get_wallet_balance.php?currency=DC');
    const data = await res.json();
    if (!data.ok) return;

    const el = document.querySelector('#dcBalance strong');
    if (el) el.textContent = data.balance;

    const top = document.querySelector('#dcBalanceTop strong');
    if (top) top.textContent = data.balance;

  } catch (err) {
    console.error('DC balance error:', err);
  }
}
  window.loadVoteBalance = window.loadVoteBalance || loadVoteBalance;
  window.loadDcBalance = window.loadDcBalance || loadDcBalance;

  /* -----------------------------
   * VIP 24h activation modal
   * ----------------------------- */
  async function loadAllCharactersForVip() {
    const select = document.getElementById('vipCharSelect');
    if (!select) return;

    select.innerHTML = '';

    const resAcc = await fetch('/api/list_game_accounts.php', { credentials: 'same-origin' });
    const dataAcc = await resAcc.json().catch(() => ({}));
    if (!dataAcc.ok || !Array.isArray(dataAcc.accounts)) return;

    for (const acc of dataAcc.accounts) {
      const resChar = await fetch(`/api/list_characters.php?account=${encodeURIComponent(acc.login)}`, {
        credentials: 'same-origin'
      });
      const dataChar = await resChar.json().catch(() => ({}));
      if (!dataChar.ok || !Array.isArray(dataChar.characters)) continue;

      dataChar.characters.forEach(ch => {
        const opt = document.createElement('option');
        opt.value = ch.charId;
        opt.textContent = `${ch.char_name} (Lv ${ch.level})`;
        select.appendChild(opt);
      });
    }
  }

  function initVipModal() {
    const openBtn = document.getElementById('openVipModal');
    const cancelBtn = document.getElementById('vipCancel');
    const confirmBtn = document.getElementById('vipConfirm');
    const modal = document.getElementById('vipModal');

    if (!modal) return;

    openBtn?.addEventListener('click', async () => {
      await loadAllCharactersForVip();
      modal.classList.remove('hidden');
    });

    cancelBtn?.addEventListener('click', () => {
      modal.classList.add('hidden');
    });

    confirmBtn?.addEventListener('click', async (e) => {
      const btn = e.target;
      if (btn.disabled) return;

      btn.disabled = true;
      btn.textContent = T.activating;

      const charId = document.getElementById('vipCharSelect')?.value || '';
      const currency = document.getElementById('vipCurrency')?.value || '';

      try {
        const res = await fetch('/api/activate_vip_24h.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ char_id: charId, currency })
        });

        const data = await res.json().catch(() => ({}));

if (data.ok) {
  modal.classList.add('hidden');

  await loadVoteBalance();
  await loadDcBalance();
  await loadGameAccounts();

  if (typeof window.refreshMeAndUi === 'function') {
    await window.refreshMeAndUi();
  }
}

else {
  alert(data.error || 'Activation failed.');
}
      } catch (err) {
        alert('Server error.');
      }

      btn.disabled = false;
      btn.textContent = T.activate;
    });
  }

  /* -----------------------------
   * convert VC->DC
   * ----------------------------- */
  function initConvert() {
    const btn = document.getElementById('convertVcToDc');
    if (!btn) return;

    btn.addEventListener('click', async (e) => {
      const b = e.target;
      if (b.disabled) return;

      if (!confirm(T.convertConfirm)) return;

      b.disabled = true;
      b.textContent = T.processing;

      try {
        const res = await fetch('/api/convert_vc_to_dc.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' }
        });

        const data = await res.json().catch(() => ({}));

        if (data.ok) {
          loadVoteBalance();
          loadDcBalance();
        } else {
          alert(data.error || 'Conversion failed.');
        }
      } catch (err) {
        alert('Server error.');
      }

      b.disabled = false;
      b.textContent = T.convertLabel;
    });
  }


function showShopConfirm({ title, text, okLabel, cancelLabel }) {
  return new Promise((resolve) => {
    const modal = document.getElementById('shopConfirmModal');
    const tEl = document.getElementById('shopConfirmTitle');
    const msgEl = document.getElementById('shopConfirmText');
    const btnOk = document.getElementById('shopConfirmOk');
    const btnCancel = document.getElementById('shopConfirmCancel');

    if (!modal || !tEl || !msgEl || !btnOk || !btnCancel) {
  console.error('Shop confirm modal missing in HTML');
  resolve(false);
  return;
}

    tEl.textContent = title || '';
    msgEl.textContent = text || '';
    btnOk.textContent = okLabel || (isEn ? 'Buy' : 'Koupit');
    btnCancel.textContent = cancelLabel || (isEn ? 'Cancel' : 'Zrušit');

    const cleanup = (val) => {
      modal.classList.add('hidden');
      btnOk.onclick = null;
      btnCancel.onclick = null;
      modal.onclick = null;
      resolve(val);
    };

    btnOk.onclick = () => cleanup(true);
    btnCancel.onclick = () => cleanup(false);
    modal.onclick = (e) => { if (e.target === modal) cleanup(false); };

    modal.classList.remove('hidden');
  });
}



function notify(type, message, timeout = 3000) {
  const box = document.getElementById('notifications');
  if (!box) return;

  const el = document.createElement('div');
  el.className = `notify ${type}`;
  el.textContent = message;

  box.appendChild(el);

  setTimeout(() => {
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
  }, timeout);
}

window.notify = notify; // ✅ důležité

  /* -----------------------------
   * shop
   * ----------------------------- */
  let shopProducts = [];
  let myGameAccounts = [];

  function getLang() {
  const l = (document.documentElement.lang || '').toLowerCase();
  return l.startsWith('en') ? 'en' : 'cs';
}

const I18N = {
  cs: {
    shopLoading: 'Načítám produkty…',
    noProducts: 'Žádné produkty.',
    pickAccount: 'Vyber herní účet',
    buy: 'Koupit',
    dc: 'DC'
  },
  en: {
    shopLoading: 'Loading products…',
    noProducts: 'No products.',
    pickAccount: 'Select game account',
    buy: 'BUY',
    dc: 'DC'
  }
};

async function loadShop() {
  const boxPremium = document.getElementById('shopPremium');
  const boxMounts = document.getElementById('shopMounts');
  const boxCloaks = document.getElementById('shopCloaks');
  const boxCosmetic = document.getElementById('shopCosmetic');
  if (!boxPremium || !boxMounts || !boxCloaks || !boxCosmetic) return;

  const L = getLang();
  const TT = I18N[L] || I18N.cs;

  // loading do všech panelů
  boxPremium.innerHTML = `<div class="muted">${TT.shopLoading}</div>`;
  boxMounts.innerHTML = `<div class="muted">${TT.shopLoading}</div>`;
  boxCloaks.innerHTML = `<div class="muted">${TT.shopLoading}</div>`;
  boxCosmetic.innerHTML = `<div class="muted">${TT.shopLoading}</div>`;

  let pRes, aRes, pData, aData;

  try {
    [pRes, aRes] = await Promise.all([
      fetch(`/api/shop_list.php?lang=${L}`, { credentials: 'same-origin' }),
      fetch('/api/list_game_accounts_min.php', { credentials: 'same-origin' })
    ]);

    pData = await pRes.json().catch(() => ({}));
    aData = await aRes.json().catch(() => ({}));
  } catch (e) {
    const err = `<div class="form-error">Shop load failed.</div>`;
    boxPremium.innerHTML = err;
    boxMounts.innerHTML = err;
    boxCloaks.innerHTML = err;
    boxCosmetic.innerHTML = err;
    return;
  }

  const statusInfo = `shop_list=${pRes?.status} accounts=${aRes?.status}`;

  if (!pRes?.ok) {
    const err = `<div class="form-error">Shop API error (${statusInfo}).</div>`;
    boxPremium.innerHTML = err;
    boxMounts.innerHTML = err;
    boxCloaks.innerHTML = err;
    boxCosmetic.innerHTML = err;
    console.error('shop_list bad response:', pRes?.status, pData);
    return;
  }

  if (!aRes?.ok) {
    const err = `<div class="form-error">Accounts API error (${statusInfo}).</div>`;
    boxPremium.innerHTML = err;
    boxMounts.innerHTML = err;
    boxCloaks.innerHTML = err;
    boxCosmetic.innerHTML = err;
    console.error('accounts_min bad response:', aRes?.status, aData);
    return;
  }

  const prods =
    (pData && pData.ok && Array.isArray(pData.products) && pData.products) ||
    (pData && pData.ok && Array.isArray(pData.items) && pData.items) ||
    (pData && pData.ok && Array.isArray(pData.list) && pData.list) ||
    [];

  const accs =
    (aData && aData.ok && Array.isArray(aData.accounts) && aData.accounts) ||
    (aData && aData.ok && Array.isArray(aData.list) && aData.list) ||
    [];

  shopProducts = prods;
  myGameAccounts = accs;

  // vyprázdnit panely
  boxPremium.innerHTML = '';
  boxMounts.innerHTML = '';
  boxCloaks.innerHTML = '';
  boxCosmetic.innerHTML = '';

  if (!shopProducts.length) {
    const empty = `<div class="muted">${TT.noProducts}</div>`;
    boxPremium.innerHTML = empty;
    boxMounts.innerHTML = empty;
    boxCloaks.innerHTML = empty;
    boxCosmetic.innerHTML = empty;
    return;
  }

  // 1) Načíst postavy (jen kvůli Mountům) – ze všech účtů
  let allChars = [];
  if (myGameAccounts.length) {
    const charCalls = myGameAccounts.map(a =>
      fetch(`/api/list_characters.php?account=${encodeURIComponent(a.login)}`, { credentials: 'same-origin' })
        .then(r => r.json().catch(() => ({})))
        .then(d => (d && d.ok && Array.isArray(d.characters)) ? d.characters : [])
        .catch(() => [])
    );
    const charLists = await Promise.all(charCalls);
    allChars = charLists.flat();
  }

  const charOptionsHtml = allChars.length
    ? allChars.map(ch => `<option value="${ch.charId}">${ch.char_name} (Lv ${ch.level})</option>`).join('')
    : `<option value="">${L === 'en' ? 'No characters' : 'Žádné postavy'}</option>`;

 // helper render
function renderRow(prod, extraSelectHtml = '') {
  const row = document.createElement('div');
  row.className = 'mini-row';

  const isImg = ['MOUNT','COSMETIC'].includes(String(prod.category || '').toUpperCase());
  const imgHtml = isImg
  ? `<img class="shop-img" src="/img/shop/${prod.code}.png" alt="${prod.name}" loading="lazy">`
  : '';

  row.innerHTML = `
    <div class="mini-row shop-row">
      ${imgHtml}

      <div class="shop-info">
        <strong>${prod.name}</strong><br>
        <span class="muted">${prod.description || ''}</span>
      </div>

      <div class="shop-actions" style="display:flex; gap:10px; align-items:center; justify-content:flex-end;">
        ${extraSelectHtml}
        <span class="tag">${prod.price_dc} ${TT.dc}</span>
        <button class="btn btn-small btn-primary shop-buy" data-id="${prod.id}">
          ${TT.buy}
        </button>
      </div>
    </div>
  `;
  return row;
}

  // 2) Rozdělit podle category a vykreslit do správných boxů
  let hasPremium = false, hasMounts = false, hasCloaks = false, hasCos = false;

  shopProducts.forEach(prod => {
    const cat = String(prod.category || '').toUpperCase();

    // VIP / Premium
    if (cat === 'VIP') {
      hasPremium = true;

      const needsGameAcc = (prod.code === 'PREM_GAME_30D');
      const selectAccHtml = needsGameAcc ? `
        <select class="shop-acc" data-pid="${prod.id}">
          ${myGameAccounts.map(a => `<option value="${a.id}">${a.login}</option>`).join('')}
        </select>
      ` : '';

      boxPremium.appendChild(renderRow(prod, selectAccHtml));
      return;
    }

    // Mounty
    if (cat === 'MOUNT') {
      hasMounts = true;

      const selectCharHtml = `
        <select class="shop-char" data-pid="${prod.id}">
          ${charOptionsHtml}
        </select>
      `;

      boxMounts.appendChild(renderRow(prod, selectCharHtml));
      return;
    }

    // Cosmetic
   if (cat === 'COSMETIC') {
  const codeUp = String(prod.code || '').toUpperCase();

  const selectCharHtml = `
    <select class="shop-char" data-pid="${prod.id}">
      ${charOptionsHtml}
    </select>
  `;

  if (codeUp.startsWith('CLOAK_')) {
    hasCloaks = true;
    boxCloaks.appendChild(renderRow(prod, selectCharHtml));
  } else {
    hasCos = true;
    boxCosmetic.appendChild(renderRow(prod, selectCharHtml));
  }
  return;
}
  });

  if (!hasPremium) boxPremium.innerHTML = `<div class="muted">${TT.noProducts}</div>`;
  if (!hasMounts)  boxMounts.innerHTML  = `<div class="muted">${TT.noProducts}</div>`;
  if (!hasCloaks) boxCloaks.innerHTML = `<div class="muted">${TT.noProducts}</div>`;
  if (!hasCos)     boxCosmetic.innerHTML = `<div class="muted">${TT.noProducts}</div>`;
}
let shopInited = false;

function ensureShopInit() {
  if (shopInited) return;
  shopInited = true;
  initShopSubTabs();
  loadShop();
}

  function initShopSubTabs() {
    const btns = qsa('#shop [data-shop-tab]');
    if (!btns.length) return;

    const panes = {
      premium: document.getElementById('shopPremium'),
      mounts: document.getElementById('shopMounts'),
      cloaks: document.getElementById('shopCloaks'),
      cosmetic: document.getElementById('shopCosmetic')
    };

    btns.forEach(b => b.addEventListener('click', () => {
      const key = b.dataset.shopTab;
      btns.forEach(x => x.classList.toggle('active', x === b));
      Object.entries(panes).forEach(([k, el]) => {
        if (!el) return;
        el.style.display = (k === key) ? '' : 'none';
      });
    }));
  }

 function initShop() {
  const shopTabBtn = qs('[data-tab="shop"]');

  // klik na hlavní tab
  shopTabBtn?.addEventListener('click', ensureShopInit);

  // pokud je shop už aktivní (např. přes ?tab=shop), nastartuj hned
  const shopPanelActive = document.getElementById('shop')?.classList.contains('active');
  const shopTabActive = shopTabBtn?.classList.contains('active');

  if (shopPanelActive || shopTabActive) {
    ensureShopInit();
  }

  // delegated buy click (tohle nech jak máš)
  document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.shop-buy');
  if (!btn) return;

  const productId = parseInt(btn.dataset.id, 10);
  if (!productId) return;

  const prod = shopProducts.find(p => Number(p.id) === productId);
  if (!prod) return;

  if (btn.disabled) return; // ochrana proti dvojkliku

  try {
    const payload = { product_id: productId };

    // GAME premium potřebuje vybrat účet
    if (prod.code === 'PREM_GAME_30D') {
      const sel = qs(`.shop-acc[data-pid="${productId}"]`);
      const gaId = parseInt(sel?.value || '0', 10);
      if (!gaId) {
        notify('error', T.needAcc);
        return;
      }
      payload.game_account_id = gaId;
    }

    // MOUNT + COSMETIC potřebuje vybrat postavu
    if (['MOUNT', 'COSMETIC'].includes((prod.category || '').toUpperCase())) {
      const sel = qs(`.shop-char[data-pid="${productId}"]`);
      const chId = parseInt(sel?.value || '0', 10);
      if (!chId) {
        notify('error', isEn ? 'Select character' : 'Vyber postavu');
        return;
      }
      payload.char_id = chId;
    }

    // CONFIRM až teď (payload už existuje a víme postavu/účet)
    const name = prod?.name || '';
    const price = prod?.price_dc ?? '?';

    let extra = '';
    if (payload.char_id) {
      const opt = qs(`.shop-char[data-pid="${productId}"] option:checked`);
      const chName = opt ? opt.textContent : '';
      if (chName) extra += isEn ? `\nCharacter: ${chName}` : `\nPostava: ${chName}`;
    }
    if (payload.game_account_id) {
      const opt = qs(`.shop-acc[data-pid="${productId}"] option:checked`);
      const accName = opt ? opt.textContent : '';
      if (accName) extra += isEn ? `\nAccount: ${accName}` : `\nÚčet: ${accName}`;
    }

    const msg = isEn
      ? `Buy "${name}" for ${price} DC?${extra}`
      : `Koupit "${name}" za ${price} DC?${extra}`;

    const ok = await showShopConfirm({
  title: isEn ? 'Confirm purchase' : 'Potvrdit nákup',
  text: msg,
  okLabel: isEn ? 'Buy' : 'Koupit',
  cancelLabel: isEn ? 'Cancel' : 'Zrušit'
});
if (!ok) return;

    // teprve po potvrzení disable
    btn.disabled = true;

    const res = await fetch('/api/shop_buy.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await res.json().catch(() => ({}));

    if (data.ok) {
      notify('success', T.buyOk);
      await loadDcBalance();
      await loadVoteBalance();
      await loadGameAccounts();
      if (typeof window.refreshMeAndUi === 'function') await window.refreshMeAndUi();
      return;
    }

    const err = data.error || T.buyErr;
    if (err === 'INSUFFICIENT_FUNDS') notify('error', T.insufficient);
    else if (err === 'ALREADY_PURCHASED') notify('error', T.alreadyBought);
    else notify('error', err);

  } catch (ex) {
    console.error(ex);
    notify('error', T.buyErr);
  } finally {
    btn.disabled = false;
  }
});
}
  /* -----------------------------
   * init
   * ----------------------------- */
  document.addEventListener('DOMContentLoaded', async () => {
    await initCsrf();
    // 1) user session + VIP + admin button + redirect if not logged in
    await initMeAndUi();

    // 2) tabs
    initTabs();

    // 3) accounts list
    await loadGameAccounts();

    // 4) modals & actions
    initCreateAccountModal();
    initDeleteModal();
    initResetPasswordModal();
    
    // 5) characters toggle (expand)
    initCharactersToggle();

    // 6) vote
    initVote();
    loadVoteSites();

    // 7) balances
    loadVoteBalance();
    loadDcBalance();

    // 8) vip modal + convert
    initVipModal();
    initConvert();

    // 9) bug system
    initBugForm();
    initBugCounter();
    initMyBugsList();

    // 10) shop
    initShop();
  });
})();