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


function showTwofaModal({ text, requireCode = false }) {
  return new Promise((resolve) => {
    const modal = document.getElementById('twofaConfirmModal');
    const textEl = document.getElementById('twofaConfirmText');
    const input = document.getElementById('twofaConfirmCode');
    const ok = document.getElementById('twofaConfirmOk');
    const cancel = document.getElementById('twofaConfirmCancel');

    textEl.textContent = text;

    if (requireCode) {
      input.style.display = 'block';
      input.value = '';
      input.focus();
    } else {
      input.style.display = 'none';
    }

    const cleanup = (val) => {
      modal.classList.add('hidden');
      ok.onclick = null;
      cancel.onclick = null;
      resolve(val);
    };

    ok.onclick = () => {
      if (requireCode) {
        const code = input.value.trim();
        if (!code) return;
        cleanup(code);
      } else {
        cleanup(true);
      }
    };

    cancel.onclick = () => cleanup(false);
    modal.onclick = (e) => { if (e.target === modal) cleanup(false); };

    modal.classList.remove('hidden');
  });
}



/* 🔥 ZABLOKUJ start dokud není token */
(async function bootstrap() {
  await initCsrf();
})();
  /* ---- GLOBAL FETCH WRAPPER (CSRF) ---- */
const _origFetch = window.fetch;

window.fetch = function (url, options = {}) {

  // 1) Stripe checkout requests – skip CSRF
  if (options.skipCsrf) {
    return _origFetch(url, options);
  }

  // 🔐 2) normální chování
  options.credentials = options.credentials || 'same-origin';
  options.cache = 'no-store';

  if (options.method && options.method.toUpperCase() === 'POST') {
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
    vipOk: isEn ? 'Premium activated' : 'Premium aktivováno',
    vipErr: isEn ? 'Premium activation failed' : 'Aktivace Premium se nezdařila',

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
    unknownErr: isEn ? 'Unknown error.' : 'Neznámá chyba.',
    vipActivated: isEn ? 'Premium activated for 24 hours!' : 'Premium aktivováno na 24 hodin!',
    convertOk: isEn ? 'Vote Coins exchanged for Dragon Coin!' : 'Vote Coiny vyměněny za Dragon Coin!',
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

      // Welcome message pod nadpisem "Můj profil"
      const welcomeEl = document.getElementById('profileWelcome');
      if (welcomeEl && me.email) {
        welcomeEl.textContent = isEn
          ? `Welcome back, ${me.email}`
          : `Vítej zpět, ${me.email}`;
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
              <div class="vip-title">${isEn ? 'WEB Premium active' : 'WEB Premium aktivní'}</div>
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

       // 🔐 2FA state (toggle button + box uvnitř Bezpečnost tab + indikátor v hlavičce)
      const btn2fa    = document.getElementById('twofaToggle');
      const status    = document.getElementById('twofaStatus');
      const box       = document.getElementById('twofaBox');
      const indicator = document.getElementById('twofaIndicator');

      const isEnabled = Number(me.twofa_enabled) === 1;

      if (btn2fa) {
        btn2fa.textContent = isEnabled
          ? (isEn ? 'Disable' : 'Vypnout')
          : (isEn ? 'Enable' : 'Zapnout');
        btn2fa.classList.toggle('on', isEnabled);
        btn2fa.classList.toggle('off', !isEnabled);
        btn2fa.dataset.mode = isEnabled ? 'disable' : 'enable';
      }

      if (status) {
        status.textContent = isEnabled
          ? (isEn ? '2FA: on' : '2FA: aktivní')
          : (isEn ? '2FA: off' : '2FA: vypnuto');
      }

      if (box) {
        box.classList.remove('active', 'inactive');
        box.classList.add(isEnabled ? 'active' : 'inactive');
      }

      // Mini indikátor v hlavičce — barva (zelená on / šedá off) + tooltip
      if (indicator) {
        indicator.classList.toggle('twofa-indicator--on',  isEnabled);
        indicator.classList.toggle('twofa-indicator--off', !isEnabled);
        indicator.title = isEnabled
          ? (isEn ? '2FA active — click for security settings'
                  : '2FA aktivní — klikni pro nastavení bezpečnosti')
          : (isEn ? '2FA disabled — click for security settings'
                  : '2FA vypnuto — klikni pro nastavení bezpečnosti');
      }

    } catch (e) {
      redirectToLogin();
    }
  }

  // ✅ MUSÍ být až mimo funkci
  window.refreshMeAndUi = window.refreshMeAndUi || (async () => initMeAndUi(true));

/* -----------------------------
  2FA
 * ----------------------------- */
function init2FA() {
  const btn = document.getElementById('twofaToggle');
  const modal = document.getElementById('twofaModal');
  const cancel = document.getElementById('twofaCancel');
  const confirmBtn = document.getElementById('twofaConfirm');
  const codeInput = document.getElementById('twofaCode');
  const qrBox = document.getElementById('twofaQr'); // ✅ JEN JEDNOU

  if (!btn || !modal) return;

  // 👉 OPEN MODAL + QR
 btn.addEventListener('click', async () => {
  // 👉 DISABLE režim
if (btn.dataset.mode === 'disable') {

  const confirmDisable = await showTwofaModal({
    text: isEn ? 'Are you sure you want to disable 2FA?' : 'Opravdu chceš vypnout 2FA?'
  });

  if (!confirmDisable) return;

  const code = await showTwofaModal({
    text: document.documentElement.lang === 'en' ? 'Enter code from authenticator' : 'Zadej kód z aplikace',
    requireCode: true
  });

  if (!code) return;

  try {
    const res = await fetch('/api/2fa_disable.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code })
    });

    const data = await res.json();

    if (data.ok) {
      notify('success', isEn ? '2FA disabled' : '2FA vypnuto');

      if (typeof window.refreshMeAndUi === 'function') {
        await window.refreshMeAndUi();
      }

    } else {
      notify('error', data.error || 'Chyba');
    }

  } catch (err) {
    console.error(err);
    notify('error', 'Server error');
  }

  return;
}


  // 👉 ENABLE režim (to co už máš)
  modal.classList.remove('hidden');

  const qrBox = document.getElementById('twofaQr');
  if (qrBox) qrBox.innerHTML = 'Načítám QR...';

  try {
    const res = await fetch('/api/2fa_setup.php', {
      credentials: 'same-origin'
    });

    const data = await res.json();

    if (data.ok && qrBox) {
      qrBox.innerHTML = '';

      if (data.ok && qrBox) {
  qrBox.innerHTML = `
    <img 
      src="https://quickchart.io/qr?text=${encodeURIComponent(data.qr_url)}&size=260&margin=2"
      style="background:#fff;padding:10px;border-radius:8px;"
    >
  `;
}
    } else {
      qrBox.innerHTML = 'Chyba QR';
    }

  } catch (err) {
    console.error(err);
    if (qrBox) qrBox.innerHTML = 'Chyba spojení';
  }
});

  // 👉 CLOSE MODAL
 cancel?.addEventListener('click', async () => {
  modal.classList.add('hidden');

  try {
    await fetch('/api/2fa_cancel.php', {
      method: 'POST',
      credentials: 'same-origin'
    });
  } catch (err) {
    console.error('2FA cancel error:', err);
  }
});

  // 👉 CONFIRM 2FA ENABLE
  confirmBtn?.addEventListener('click', async () => {
    const code = codeInput.value.trim();

    if (!code) {
      alert(document.documentElement.lang === 'en' ? 'Enter 6-digit code' : 'Zadej kód');
      return;
    }

    const res = await fetch('/api/2fa_enable.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code })
    });

    const data = await res.json();

    if (data.ok) {
      notify('success', isEn ? '2FA enabled' : '2FA zapnuto');
      modal.classList.add('hidden');

      if (typeof window.refreshMeAndUi === 'function') {
        await window.refreshMeAndUi();
      }

    } else {
      alert(data.error || 'Chyba');
    }
  });
}
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
      // Backward-compat: staré deeplinky ?tab=vote / shop / donate teď
      // patří pod Odměny → otevřeme Odměny + odpovídající sub-tab.
      const rewardsSubMap = { vote: 1, shop: 1, donate: 1 };
      if (rewardsSubMap[tab]) {
        const rewardsBtn = qs(`.profile-shell > .profile-tabs [data-tab="rewards"]`);
        if (rewardsBtn) rewardsBtn.click();
        const subBtn = qs(`.rewards-tabs [data-rewards-tab="${tab}"]`);
        if (subBtn) subBtn.click();
      } else {
        const btn = qs(`.profile-shell > .profile-tabs [data-tab="${tab}"]`);
        if (btn) btn.click();
      }
    }
  }

  /* -----------------------------
   * Sub-tabs uvnitř Odměn (Hlasování / Tržiště / Donate)
   * ----------------------------- */
  function initRewardsSubTabs() {
    const subTabs   = document.querySelectorAll('.rewards-tabs .tab');
    const subPanels = document.querySelectorAll('.rewards-panels .rewards-panel');
    if (!subTabs.length || !subPanels.length) return;

    const showSub = (key) => {
      subTabs.forEach(t => t.classList.toggle('active', t.dataset.rewardsTab === key));
      subPanels.forEach(p => p.classList.toggle('active', p.id === key));
    };

    subTabs.forEach(t => t.addEventListener('click', () => showSub(t.dataset.rewardsTab)));
  }

  /* -----------------------------
   * Wallet shortcuts — klik na VC/DC pill přepne na Odměny + odpovídající sub-tab
   * VC → Odměny → Hlasování, DC → Odměny → Tržiště
   * ----------------------------- */
  function initWalletShortcuts() {
    document.querySelectorAll('.wallet-pill[data-target-tab]').forEach(pill => {
      pill.addEventListener('click', () => {
        const target = pill.dataset.targetTab; // 'vote' nebo 'shop'

        // Krok 1: otevřít Odměny top-level záložku
        const rewardsBtn = document.querySelector(
          '.profile-shell > .profile-tabs [data-tab="rewards"]'
        );
        if (rewardsBtn) rewardsBtn.click();

        // Krok 2: otevřít cílovou sub-záložku uvnitř Odměn
        const subBtn = document.querySelector(
          `.rewards-tabs [data-rewards-tab="${target}"]`
        );
        if (subBtn) subBtn.click();

        // Smooth scroll k Odměny sekci
        const rewardsSection = document.getElementById('rewards');
        if (rewardsSection) {
          rewardsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    });
  }

  /* -----------------------------
   * Mini 2FA indikátor v hlavičce — klik vede na záložku Bezpečnost
   * ----------------------------- */
  function initSecurityIndicator() {
    const indicator = document.getElementById('twofaIndicator');
    if (!indicator) return;

    indicator.addEventListener('click', (e) => {
      e.preventDefault();
      const securityBtn = document.querySelector(
        '.profile-shell > .profile-tabs [data-tab="security"]'
      );
      if (securityBtn) {
        securityBtn.click();
        const sec = document.getElementById('security');
        if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
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

    // Lokální helpers pro formátování (jeden set pro všechny účty)
    const formatDate = (ms) => {
      if (!ms) return '';
      const d = new Date(Number(ms));
      if (isEn) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
      }
      return `${d.getDate()}. ${d.getMonth() + 1}. ${d.getFullYear()}`;
    };

    const charsLabel = (n) => {
      if (isEn) return `${n} character${n === 1 ? '' : 's'}`;
      if (n === 1) return `${n} postava`;
      if (n >= 2 && n <= 4) return `${n} postavy`;
      return `${n} postav`;
    };

    const daysLabel = (n) => {
      if (isEn) return `${n} day${n === 1 ? '' : 's'}`;
      if (n === 1) return `${n} den`;
      if (n >= 2 && n <= 4) return `${n} dny`;
      return `${n} dní`;
    };

    data.accounts.forEach(acc => {
      const row = document.createElement('div');
      row.className = 'mini-row';

      // Sloučený premium tag: status + datum + počet dní v jednom
      const premiumTag = (() => {
        const tInactive = isEn ? 'Premium: inactive' : 'Premium: neaktivní';
        const tExpired  = isEn ? 'Premium: expired'  : 'Premium: expirováno';

        if (acc.premium_days_left === null) {
          return `<span class="tag muted">${tInactive}</span>`;
        }
        const left = Number(acc.premium_days_left);
        if (left < 0) return `<span class="tag danger">${tExpired}</span>`;

        const dateStr = formatDate(acc.premium_end_ms);
        const labelText = isEn
          ? `Premium until ${dateStr} (${daysLabel(left)})`
          : `Premium do ${dateStr} (${daysLabel(left)})`;

        const statusClass = left <= 3 ? 'warning' : 'success';
        return `<span class="tag ${statusClass}">${labelText}</span>`;
      })();

      const tDelete    = isEn ? 'Delete' : 'Smazat';
      const tChangePwd = isEn ? 'Change password' : 'Změnit heslo';

      row.innerHTML = `
        <div class="account-row" data-login="${acc.login}" title="${isEn ? 'Click to show characters' : 'Klikni pro zobrazení postav'}">
          <span class="expand-chevron" aria-hidden="true">▸</span>
          <strong>${isEn ? 'Account' : 'Účet'}:</strong> ${acc.login}

          <span class="tag">${charsLabel(acc.chars_count)}</span>
          ${premiumTag}

          <div class="actions">
            <button class="btn btn-small btn-danger" data-login="${acc.login}">${tDelete}</button>
            <button class="btn btn-small" data-reset="${acc.login}">${tChangePwd}</button>
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
        row.classList.remove('expanded');
        box.innerHTML = '';
        return;
      }

      // toggle open
      row.classList.add('expanded');
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
          let vipTag = '<span class="char-vip-empty"></span>';
          if (vipData && vipData.hasVip) {
            const label = isEn ? 'VIP until' : 'VIP do';
            vipTag = `<span class="tag vip">${label} ${formatDateTime(vipData.endAt)}</span>`;
          }

          el.innerHTML = `
            <span class="char-name"><strong>${escapeHtml(ch.char_name)}</strong></span>
            <span class="char-lvl">Lv ${ch.level}</span>
            <span class="char-state ${ch.online ? 'online' : 'offline'}">${ch.online ? 'ONLINE' : 'offline'}</span>
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

       const isReady = Number(site.remaining || 0) <= 0;

el.innerHTML = `
  <button 
    class="vote-btn ${isReady ? 'ready' : 'cooldown'}"
    data-id="${site.id}"
    ${!isReady ? 'disabled' : ''}
  >
    <span class="vote-name">${site.name}</span>
    <span class="vote-time">${isReady ? (T.voteReady || 'READY') : status}</span>
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
    const maxMs = 300000;
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

    const retry = confirm(isEn ? 'Vote not detected yet. Did you vote? Confirm manually?' : 'Hlasování nebylo detekováno. Hlasoval jsi? Potvrdit ručně?');
if (retry) {
    const retryRes = await fetch('/api/vote_check.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ attempt_id: attemptId, confirm: false })
    });
    const retryData = await retryRes.json().catch(() => ({}));
    if (retryData.status === 'REWARDED' || retryData.status === 'USED') {
      notify('success', T.voteRewarded);
      if (typeof window.loadVoteBalance === 'function') window.loadVoteBalance();
    } else {
      notify('error', T.votePending);
    }
    await loadVoteSites();
  } else {
    notify('error', T.votePending);
    await loadVoteSites();
  }
  }

 async function getClientIPv4() {
  try {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), 4000);
    
    // Vlastní IPv4-only endpoint (DNS-only subdoména, obchází Cloudflare)
    // Prohlížeč se připojí přes IPv4 → REMOTE_ADDR na serveru = reálná IPv4 klienta
    const res = await fetch('https://ip4.l2ordo.net/api/my_ip.php', {
      signal: ctrl.signal,
      credentials: 'omit',
      cache: 'no-store'
    });
    clearTimeout(t);
    
    const data = await res.json();
    if (data && data.ok && /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(data.ip)) {
      return data.ip;
    }
  } catch (e) {
    console.warn('IPv4 detection failed:', e);
  }
  return null;
}

async function startVote(siteId, btnEl) {
  if (voteBusy) return;
  voteBusy = true;

  try {
    if (btnEl) btnEl.disabled = true;

    // 🆕 Získej skutečnou IPv4 klienta (důležité pro IPv6 klienty s dual-stack)
    const clientIpv4 = await getClientIPv4();

    const res = await fetch('/api/vote_start.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        site_id: siteId,
        client_ipv4: clientIpv4
      })
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
    const res = await fetch('/api/get_wallet_balance.php?currency=VOTE_COIN', {
    credentials: 'same-origin'
    });
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
    const res = await fetch('/api/get_wallet_balance.php?currency=DC', {
  credentials: 'same-origin'
});
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
   * Premium 24h activation modal
   * (Per-game-account; nahrazuje původní VIP 24h per-character)
   * ----------------------------- */
  async function loadGameAccountsForPremium24() {
    const select = document.getElementById('premium24AccSelect');
    if (!select) return;

    select.innerHTML = '';

    // list_game_accounts_min.php vrací {id, login} — to je co potřebujeme
    // (full list_game_accounts.php nevrací id v current schema)
    const resAcc = await fetch('/api/list_game_accounts_min.php', { credentials: 'same-origin' });
    const dataAcc = await resAcc.json().catch(() => ({}));
    if (!dataAcc.ok || !Array.isArray(dataAcc.accounts)) return;

    if (dataAcc.accounts.length === 0) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = isEn ? 'No game accounts' : 'Žádné herní účty';
      select.appendChild(opt);
      return;
    }

    dataAcc.accounts.forEach(acc => {
      const opt = document.createElement('option');
      opt.value = acc.id;
      opt.textContent = acc.login;
      select.appendChild(opt);
    });
  }

  function initPremium24Modal() {
    const openBtn = document.getElementById('openPremium24Modal');
    const cancelBtn = document.getElementById('premium24Cancel');
    const confirmBtn = document.getElementById('premium24Confirm');
    const modal = document.getElementById('premium24Modal');

    if (!modal) return;

    openBtn?.addEventListener('click', async () => {
      await loadGameAccountsForPremium24();
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

      const gameAccountId = document.getElementById('premium24AccSelect')?.value || '';
      const currency      = document.getElementById('premium24Currency')?.value || '';

      try {
        // POZN: nepředáváme X-CSRF-Token explicitně — monkey-patched fetch
        // (řádek 84) ho automaticky přidá jako 'X-CSRF-TOKEN'. Explicit header
        // by case-kolidoval a CSRF check by selhal.
        const res = await fetch('/api/activate_premium_24h.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            game_account_id: gameAccountId,
            currency
          })
        });

        const data = await res.json().catch(() => ({}));

        if (data.ok) {
          modal.classList.add('hidden');
          notify('success', T.vipActivated);

          await loadVoteBalance();
          await loadDcBalance();
          await loadGameAccounts();

          if (typeof window.refreshMeAndUi === 'function') {
            await window.refreshMeAndUi();
          }
        } else {
          notify('error', data.error || T.vipErr);
        }
      } catch (err) {
        notify('error', T.serverConnErr);
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

      // Místo nativního confirm() použijeme stylovaný showShopConfirm
      const ok = await showShopConfirm({
        title: isEn ? 'Convert Vote Coins' : 'Převod Vote Coinů',
        text:  T.convertConfirm,
        okLabel: isEn ? 'Convert' : 'Převést',
        cancelLabel: isEn ? 'Cancel' : 'Zrušit'
      });
      if (!ok) return;

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
          notify('success', T.convertOk);
          loadVoteBalance();
          loadDcBalance();
        } else {
          notify('error', isEn ? 'Conversion failed.' : 'Výměna se nezdařila.');
        }
      } catch (err) {
        notify('error', T.serverConnErr);
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
  ? `<img class="shop-img" src="/img/shop/${prod.code}.png?v=8" alt="${prod.name}" loading="lazy">`
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

  /* -----------------------------
   * DONATE — submit + history list + copy buttons
   * ----------------------------- */
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // "2026-07-09 19:17:35" → "19:17 9. 7. 2026"  (CS)
  // Pro EN: "19:17 Jul 9, 2026"
  function formatDateTime(s) {
    if (!s) return '';
    // Parse SQL DATETIME nebo ISO (toleruje obojí)
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s; // pokud nelze parsovat, vrátíme original
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const day   = d.getDate();
    const month = d.getMonth() + 1;
    const year  = d.getFullYear();
    if (isEn) {
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return `${hh}:${mm} ${months[d.getMonth()]} ${day}, ${year}`;
    }
    return `${hh}:${mm} ${day}. ${month}. ${year}`;
  }

  function calcDonateVs(webId, year) {
    return String(webId).padStart(4, '0') + String(year);
  }

  function initDonateCopyButtons() {
    document.querySelectorAll('.donate-copy').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        let text = btn.dataset.copyText || '';
        if (!text && btn.dataset.copyTarget) {
          const target = document.querySelector(btn.dataset.copyTarget);
          text = target ? target.textContent.trim() : '';
        }
        if (!text || text === '—') return;

        try {
          await navigator.clipboard.writeText(text);
          const orig = btn.textContent;
          btn.textContent = isEn ? 'Copied!' : 'Zkopírováno!';
          setTimeout(() => { btn.textContent = orig; }, 1500);
        } catch (err) {
          console.error('Copy failed', err);
          notify('error', isEn ? 'Copy failed' : 'Kopírování selhalo');
        }
      });
    });
  }

  async function refreshMyDonations() {
    const list   = document.getElementById('myDonations');
    const idEl   = document.getElementById('donateUserId');
    const vsEl   = document.getElementById('donateVs');
    if (!list) return;

    list.innerHTML = `<div class="muted">${isEn ? 'Loading…' : 'Načítám…'}</div>`;

    try {
      const res = await fetch('/api/list_my_donations.php', { credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));

      if (!data.ok) {
        list.innerHTML = `<div class="muted">${isEn ? 'Failed to load.' : 'Nepodařilo se načíst.'}</div>`;
        return;
      }

      // Update Web ID + VS pro aktuální rok
      if (data.web_id) {
        const year = new Date().getFullYear();
        if (idEl) idEl.textContent = data.web_id;
        if (vsEl) vsEl.textContent = calcDonateVs(data.web_id, year);
      }

      if (!data.donations.length) {
        list.innerHTML = `<div class="muted">${isEn ? 'No donations yet.' : 'Zatím žádné žádosti.'}</div>`;
        return;
      }

      const statusLabels = {
        pending:  isEn ? 'Pending'  : 'Čeká na schválení',
        approved: isEn ? 'Approved' : 'Schváleno',
        rejected: isEn ? 'Rejected' : 'Zamítnuto'
      };
      const statusClasses = {
        pending:  'warning',
        approved: 'success',
        rejected: 'danger'
      };

      list.innerHTML = '';
      data.donations.forEach(d => {
        const row = document.createElement('div');
        row.className = 'mini-row donation-row';

        const dcInfo = (d.status === 'approved' && d.dc_credited > 0)
          ? `<span class="tag success">+${d.dc_credited} DC</span>`
          : '';

        const adminNote = d.admin_note
          ? `<div class="muted" style="margin-top:4px;font-size:12px;"><em>${isEn ? 'Admin' : 'Admin'}:</em> ${escapeHtml(d.admin_note)}</div>`
          : '';

        const userNote = d.note
          ? `<div class="muted" style="margin-top:4px;font-size:12px;"><em>${isEn ? 'Your note' : 'Tvoje poznámka'}:</em> ${escapeHtml(d.note)}</div>`
          : '';

        row.innerHTML = `
          <div>
            <strong>${d.amount} ${d.currency}</strong>
            <span class="muted">·</span>
            <span class="muted">${isEn ? 'Paid' : 'Platba'}: ${d.paid_at}</span>
            <span class="tag ${statusClasses[d.status] || ''}">${statusLabels[d.status] || d.status}</span>
            ${dcInfo}
          </div>
          <div class="muted" style="font-size:12px;margin-top:4px;">
            VS: ${escapeHtml(d.variable_symbol)} · ${isEn ? 'Submitted' : 'Odesláno'}: ${d.created_at}
          </div>
          ${userNote}
          ${adminNote}
        `;
        list.appendChild(row);
      });

    } catch (err) {
      console.error(err);
      list.innerHTML = `<div class="muted">${isEn ? 'Connection error.' : 'Chyba spojení.'}</div>`;
    }
  }

  function initDonate() {
    const form = document.getElementById('donateForm');
    const list = document.getElementById('myDonations');
    if (!form && !list) return;

    initDonateCopyButtons();

    // Default datum platby = dnes; min = -30 dní; max = dnes
    const dateInput = document.getElementById('donatePaidAt');
    if (dateInput) {
      const today = new Date();
      const todayStr = today.toISOString().slice(0, 10);
      dateInput.value = todayStr;
      dateInput.max   = todayStr;
      const minDate = new Date(today);
      minDate.setDate(minDate.getDate() - 30);
      dateInput.min = minDate.toISOString().slice(0, 10);
    }

    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const msg = document.getElementById('donateMsg');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; }

        const amount   = parseInt(document.getElementById('donateAmount').value, 10);
        const currency = document.getElementById('donateCurrency').value;
        const paidAt   = document.getElementById('donatePaidAt').value;
        const note     = document.getElementById('donateNote').value.trim();

        // Client-side quick validation
        if (!amount || amount <= 0) {
          msg.textContent = isEn ? 'Enter a valid amount.' : 'Zadej platnou částku.';
          msg.style.display = 'block';
          return;
        }
        const minAmount = currency === 'CZK' ? 25 : 5;
        if (amount < minAmount) {
          msg.textContent = isEn
            ? `Minimum amount is ${minAmount} ${currency}.`
            : `Minimální částka je ${minAmount} ${currency}.`;
          msg.style.display = 'block';
          return;
        }
        if (!paidAt) {
          msg.textContent = isEn ? 'Select a payment date.' : 'Zadej datum platby.';
          msg.style.display = 'block';
          return;
        }

        try {
          // CSRF token přidává monkey-patched fetch (řádek 84) automaticky
          const res = await fetch('/api/submit_donation.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              amount, currency,
              paid_at: paidAt,
              note,
              lang: isEn ? 'en' : 'cs'
            })
          });
          const data = await res.json().catch(() => ({}));

          if (!data.ok) {
            const errMap = {
              'NOT_LOGGED_IN':    isEn ? 'You are not logged in.' : 'Nejsi přihlášen.',
              'INVALID_AMOUNT':   isEn ? 'Invalid amount.' : 'Neplatná částka.',
              'INVALID_CURRENCY': isEn ? 'Invalid currency.' : 'Neplatná měna.',
              'INVALID_DATE':     isEn ? 'Invalid date.' : 'Neplatné datum.',
              'DATE_IN_FUTURE':   isEn ? 'Date is in the future.' : 'Datum je v budoucnosti.',
              'DATE_TOO_OLD':     isEn ? 'Date is more than 30 days ago.' : 'Datum je starší než 30 dní.',
              'AMOUNT_TOO_LOW':   isEn ? `Minimum is ${data.min} ${data.currency}.` : `Minimum je ${data.min} ${data.currency}.`,
              'NOTE_TOO_LONG':    isEn ? 'Note is too long.' : 'Poznámka je příliš dlouhá.',
              'TOO_MANY_PENDING': isEn ? 'You have too many pending requests (max 3).' : 'Máš příliš mnoho čekajících žádostí (max 3).',
              'COOLDOWN':         isEn ? 'Please wait 24 hours between submissions.' : 'Mezi žádostmi je nutné počkat 24 hodin.',
              'RATE_LIMITED':     isEn ? 'Too many requests, try later.' : 'Příliš mnoho žádostí, zkus později.',
              'CSRF_INVALID':     isEn ? 'Session expired, refresh the page.' : 'Session vypršela, obnov stránku.'
            };
            const text = errMap[data.error] || (isEn ? 'Request failed.' : 'Žádost selhala.');
            if (msg) { msg.textContent = text; msg.style.display = 'block'; }
            return;
          }

          notify('success', isEn ? 'Request submitted, thank you!' : 'Žádost odeslána, děkujeme!');
          form.reset();
          // Reset date back to today (form.reset() ho vyčistí)
          if (dateInput) {
            dateInput.value = new Date().toISOString().slice(0, 10);
          }
          await refreshMyDonations();

        } catch (err) {
          console.error(err);
          if (msg) {
            msg.textContent = isEn ? 'Connection error.' : 'Chyba spojení.';
            msg.style.display = 'block';
          }
        }
      });
    }

    // Initial load
    refreshMyDonations();

    // Re-load when user clicks Donate sub-tab
    const donateSubBtn = document.querySelector('[data-rewards-tab="donate"]');
    if (donateSubBtn) {
      donateSubBtn.addEventListener('click', refreshMyDonations);
    }
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
  // Po refaktoru je Tržiště sub-záložka uvnitř Odměny → posloucháme
  // klik na sub-tab `[data-rewards-tab="shop"]` (ne původní `[data-tab="shop"]`).
  const shopSubBtn = qs('[data-rewards-tab="shop"]');

  shopSubBtn?.addEventListener('click', ensureShopInit);

  // pokud je shop už aktivní (např. přes deeplink ?tab=shop), nastartuj hned
  const shopPanelActive = document.getElementById('shop')?.classList.contains('active');
  const shopSubActive   = shopSubBtn?.classList.contains('active');

  if (shopPanelActive || shopSubActive) {
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
    initRewardsSubTabs();
    initWalletShortcuts();
    initSecurityIndicator();

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
    initPremium24Modal();
    initConvert();

    // 9) bug system
    initBugForm();
    initBugCounter();
    initMyBugsList();

    // 10) shop
    initShop();
    // 11) 2FA
    init2FA();
    // 12) donate
    initDonate();
  });
})();

