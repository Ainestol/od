// profile.js (část 1/3)
(() => {
  'use strict';

  // ---------- helpers ----------
  const isEn = () => (document.documentElement.lang || '').toLowerCase() === 'en';

  // notify() nechávám jako externí (bude v části 2/3 nebo 3/3),
  // ale kdyby ještě nebyl, ať to nespadne:
  const safeNotify = (type, msg, t) => (window.notify ? window.notify(type, msg, t) : console.log(type, msg));

  // ---------- auth + WEB VIP box ----------
  async function initMeAndVipBox() {
    try {
      const res = await fetch('/api/me.php', { credentials: 'same-origin' });
      const data = await res.json();

      if (!data.ok) {
        window.location.href = isEn() ? '/auth/login-en.html' : '/auth/login.html';
        return;
      }

      if (data.web_vip) {
        const vipBox = document.createElement('div');
        vipBox.className = 'profile-vip-box';
        vipBox.innerHTML = `
          <strong>WEB VIP aktivní</strong><br>
          Platí do: ${data.web_vip.end_at}<br>
          Zbývá dní: ${data.web_vip.days_left}
        `;
        document.querySelector('.auth-container')?.prepend(vipBox);
      }
    } catch (e) {
      window.location.href = '/auth/login.html';
    }
  }

  // ---------- main tabs ----------
  function initProfileTabs() {
    const tabs = document.querySelectorAll('.profile-shell > .profile-tabs .tab');
    const panels = document.querySelectorAll('.profile-panels .panel');
    if (!tabs.length || !panels.length) return;

    const show = (key) => {
      tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === key));
      panels.forEach(p => p.classList.toggle('active', p.id === key));
    };

    tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.tab)));

    const first = document.querySelector('.profile-shell > .profile-tabs .tab.active') || tabs[0];
    if (first) show(first.dataset.tab);

    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab) {
      const btn = document.querySelector(`.profile-shell > .profile-tabs [data-tab="${tab}"]`);
      if (btn) btn.click();
    }
  }

  // ---------- game accounts list ----------
  async function loadGameAccounts() {
    const list = document.getElementById('gameAccountsList');
    const countEl = document.getElementById('accountsCount');
    if (!list) return;

    list.innerHTML = '<div class="muted">Loading game accounts…</div>';

    let res;
    try {
      res = await fetch('/api/list_game_accounts.php', { credentials: 'same-origin' });
    } catch (e) {
      list.innerHTML = '<div class="form-error">Server connection error.</div>';
      if (countEl) countEl.textContent = '(0 / 10)';
      return;
    }

    if (!res.ok) {
      list.innerHTML = '<div class="form-error">Unable to load accounts.</div>';
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
      list.innerHTML = '<div class="muted">You have no game accounts yet.</div>';
      return;
    }

    data.accounts.forEach(acc => {
      const row = document.createElement('div');
      row.className = 'mini-row';

      const isPrimary = Number(acc.is_primary) === 1;
      if (isPrimary) row.classList.add('primary-account');

      const premiumTag = (() => {
        if (acc.premium_days_left === null) return `<span class="tag muted">Premium: neaktivní</span>`;
        if (acc.premium_days_left < 0) return `<span class="tag danger">Premium: expirováno</span>`;
        if (acc.premium_days_left <= 3) return `<span class="tag warning">Premium: ${acc.premium_days_left} dny</span>`;
        return `<span class="tag success">Premium: ${acc.premium_days_left} dní</span>`;
      })();

      row.innerHTML = `
        <div class="account-row" data-login="${acc.login}">
          <strong>${isPrimary ? '⭐ ' : ''}Account:</strong> ${acc.login}
          <span class="tag">${acc.chars_count} characters</span>
          ${premiumTag}
          ${acc.premium_end_at
            ? `<span class="tag">${acc.premium_end_at}</span>`
            : `<span class="tag">${acc.created_at}</span>`
          }

          <div class="actions">
            ${isPrimary
              ? '<span class="tag primary">PRIMARY</span>'
              : `<button class="btn btn-small" data-primary="${acc.login}">Set primary</button>`
            }
            <button class="btn btn-small btn-danger" data-login="${acc.login}">Delete</button>
            <button class="btn btn-small" data-reset="${acc.login}">Change password</button>
          </div>
        </div>

        <div class="char-list hidden" id="chars-${acc.login}"></div>
      `;

      list.appendChild(row);
    });
  }

  // ---------- create account modal ----------
  function initCreateAccountModal() {
    const modal = document.getElementById('accModal');
    const openBtn = document.getElementById('createAccBtn');
    const cancelBtn = document.getElementById('accCancel');
    const submitBtn = document.getElementById('accSubmit');
    const msg = document.getElementById('accMsg');

    const login = document.getElementById('accLogin');
    const pass  = document.getElementById('accPass');

    if (!modal || !submitBtn) return;

    const open = () => {
      modal.classList.remove('hidden');
      if (msg) { msg.style.display = 'none'; msg.textContent = ''; }
    };
    const close = () => modal.classList.add('hidden');

    openBtn?.addEventListener('click', (e) => { e.preventDefault(); open(); });
    cancelBtn?.addEventListener('click', (e) => { e.preventDefault(); close(); });
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

    submitBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (msg) msg.style.display = 'none';

      const payload = {
        login: (login?.value || '').trim(),
        password: pass?.value || ''
      };

      submitBtn.disabled = true;
      try {
        const res = await fetch('/api/create_game_account.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const data = await res.json().catch(() => ({}));

        if (data.ok) {
          modal.classList.add('hidden');
          safeNotify('success', 'Herní účet byl vytvořen');

          if (pass) pass.value = '';
          if (login) login.value = '';

          await loadGameAccounts();
          setTimeout(() => modal.classList.add('hidden'), 800);
        } else {
          safeNotify('error', data.error || 'Chyba při vytváření účtu');
        }
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

  // ---------- delete modal ----------
  function initDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const input = document.getElementById('deleteConfirmInput');
    const confirmBtn = document.getElementById('deleteConfirm');
    const cancelBtn = document.getElementById('deleteCancel');
    if (!modal || !input || !confirmBtn || !cancelBtn) return;

    let currentLogin = null;
    const keyword = 'smazat';

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-login]');
      if (!btn) return;

      currentLogin = btn.dataset.login;

      document.getElementById('deleteLogin')?.textContent = currentLogin;
      document.getElementById('deleteKeyword')?.textContent = keyword;

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
      confirmBtn.disabled = true;

      const res = await fetch('/api/delete_game_account.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ login: currentLogin })
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        modal.classList.add('hidden');
        safeNotify('success', 'Herní účet byl smazán');
        await loadGameAccounts();
        return;
      }

      if (data.error === 'ACCOUNT_HAS_ACTIVE_CHARACTERS') safeNotify('error', 'Účet má aktivní postavy');
      else safeNotify('error', 'Nepodařilo se smazat účet');

      confirmBtn.disabled = false;
    });
  }

 
  window.loadGameAccounts = loadGameAccounts;
// ---------- část 2/3 ----------

// NOTIFY (globální, aby ho mohly volat i další části)
function ensureNotificationsBox() {
  let box = document.getElementById('notifications');
  if (!box) {
    box = document.createElement('div');
    box.id = 'notifications';
    document.body.appendChild(box);
  }
  return box;
}

function notify(type, message, timeout = 3000) {
  const box = ensureNotificationsBox();
  const el = document.createElement('div');
  el.className = `notify ${type}`;
  el.textContent = message;
  box.appendChild(el);

  setTimeout(() => {
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
  }, timeout);
}
window.notify = notify;

// RESET PASSWORD modal
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
    loginEl.textContent = currentLogin;
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
    confirmBtn.disabled = true;

    const res = await fetch('/api/reset_game_password.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        login: currentLogin,
        password: pass1.value
      })
    });

    const data = await res.json().catch(() => ({}));

    if (data.ok) {
      modal.classList.add('hidden');
      notify('success', 'Heslo bylo změněno');
      return;
    }

    notify('error', data.error || 'Nepodařilo se resetovat heslo');
    confirmBtn.disabled = false;
  });
}

// SET PRIMARY account (delegovaný click)
function initSetPrimaryAccount() {
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-primary]');
    if (!btn) return;

    const login = btn.dataset.primary;

    const res = await fetch('/api/set_primary_account.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login })
    });

    const data = await res.json().catch(() => ({}));

    if (data.ok) {
      notify('success', 'Primární účet nastaven');
      window.loadGameAccounts?.();
    } else {
      notify('error', 'Nastavení primárního účtu se nezdařilo');
    }
  });
}

// BUG: naplnění selectu herními účty + submit
function initBugReport() {
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

  document.getElementById('bugForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const payload = {
      game_account: document.getElementById('bugAccount')?.value || '',
      category: document.getElementById('bugCategory')?.value || '',
      title: (document.getElementById('bugTitle')?.value || '').trim(),
      message: (document.getElementById('bugMessage')?.value || '').trim()
    };

    if (payload.message.length > 1000) {
      notify('error', 'Text je příliš dlouhý (max. 1000 znaků)');
      return;
    }

    const res = await fetch('/api/create_bug_report.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await res.json().catch(() => ({}));

    if (data.ok) {
      notify('success', 'Bug report odeslán');
      e.target.reset();
    } else {
      notify('error', data.error || 'Chyba při odesílání');
    }
  });

  // counter
  (() => {
    const textarea = document.getElementById('bugMessage');
    const counter  = document.getElementById('bugCounter');
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
  })();
}

// MY BUGS list
function initMyBugsList() {
  const box = document.getElementById('myBugs');
  if (!box) return;

  fetch('/api/list_my_bug_reports.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.ok || !Array.isArray(data.bugs)) {
        box.innerHTML = '<div class="muted">Žádná hlášení</div>';
        return;
      }

      box.innerHTML = '';
      data.bugs.forEach(bug => {
        const row = document.createElement('a');
        row.href = `/profile/bug_detail.html?id=${bug.id}`;
        row.className = 'link-item';
        row.innerHTML = `<strong>[${String(bug.status).toUpperCase()}]</strong> ${bug.title}`;
        box.appendChild(row);
      });
    })
    .catch(() => {
      box.innerHTML = '<div class="muted">Žádná hlášení</div>';
    });
}

// VIP MAP helper (pro seznam postav u účtů)
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
window.loadVipMap = loadVipMap;

// ---------- část 3/3 ----------

// rozbalování postav pod game účtem (account-row click)
function initAccountRowCharactersToggle() {
  document.addEventListener('click', async (e) => {
    const row = e.target.closest('.account-row');
    if (!row) return;

    const login = row.dataset.login;
    const box = document.getElementById('chars-' + login);
    if (!box) return;

    // toggle
    if (!box.classList.contains('hidden')) {
      box.classList.add('hidden');
      box.innerHTML = '';
      return;
    }

    box.classList.remove('hidden');
    box.innerHTML = '<div class="muted">Loading characters…</div>';

    try {
      // 1) VIP map
      const vipMap = (typeof window.loadVipMap === 'function') ? await window.loadVipMap(login) : {};

      // 2) postavy
      const res = await fetch(`/api/list_characters.php?account=${encodeURIComponent(login)}`, {
        credentials: 'same-origin'
      });
      const data = await res.json().catch(() => ({}));

      if (!data.ok || !Array.isArray(data.characters) || !data.characters.length) {
        box.innerHTML = '<div class="muted">No characters</div>';
        return;
      }

      box.innerHTML = '';

      data.characters.forEach(ch => {
        const el = document.createElement('div');
        el.className = 'char-row';

        const vipData = vipMap?.[ch.charId];
        const vipTag = (vipData && vipData.hasVip)
          ? `<span class="tag vip">VIP do ${vipData.endAt}</span>`
          : '';

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
      box.innerHTML = '<div class="form-error">Failed to load characters</div>';
    }
  });
}

// ---------------- VOTE ----------------
let voteCache = [];
let voteBusy = false;

const i18nVote = (() => {
  const en = (document.documentElement.lang || '').toLowerCase() === 'en';
  return {
    en,
    txt: {
      noSites: en ? 'No active vote sites.' : 'Žádné aktivní vote weby.',
      ready: 'READY',
      cooldown: en ? 'Vote is on cooldown.' : 'Vote je v cooldownu.',
      cooldownLeft: en ? 'Cooldown remaining: ' : 'Zbývá cooldown: ',
      startError: en ? 'Failed to start vote.' : 'Nepodařilo se spustit vote.',
      openHint: en ? 'Vote page opened. Waiting for verification…' : 'Vote stránka otevřena. Čekám na ověření…',
      needConfirm: en ? 'Confirm you voted?' : 'Potvrdit, že jsi hlasoval?',
      rewarded: en ? 'Vote Coin added!' : 'Vote Coin připsán!',
      pendingLater: en ? 'Not detected yet. Try again later.' : 'Zatím nedetekováno. Zkus to později.',
      unknownErr: en ? 'Unknown error.' : 'Neznámá chyba.'
    }
  };
})();

async function loadVoteSites() {
  try {
    const res = await fetch('/api/vote_status.php', { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!data.ok) return;

    voteCache = data.sites || [];

    const box = document.querySelector('#vote .link-list');
    if (!box) return;

    box.innerHTML = '';

    if (!voteCache.length) {
      box.innerHTML = `<div class="muted">${i18nVote.txt.noSites}</div>`;
      return;
    }

    voteCache.forEach(site => {
      const el = document.createElement('div');

      const status = (() => {
        const rem = Number(site.remaining || 0);
        if (rem <= 0) return i18nVote.txt.ready;

        const h = Math.floor(rem / 3600);
        const m = Math.floor((rem % 3600) / 60);
        if (h <= 0) return `${m}m`;
        return `${h}h ${m}m`;
      })();

      const disabled = site.remaining > 0 ? 'disabled' : '';

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
        window.notify?.('error', `${i18nVote.txt.cooldown} ${i18nVote.txt.cooldownLeft}${rem}s`);
        await loadVoteSites();
        return;
      }
      window.notify?.('error', `${i18nVote.txt.startError} ${data.error || ''}`.trim());
      return;
    }

    window.open(data.vote_url, '_blank');
    window.notify?.('success', i18nVote.txt.openHint, 3500);

    await pollVote(data.attempt_id);

  } catch (err) {
    console.error('Vote start error:', err);
    window.notify?.('error', i18nVote.txt.startError);
  } finally {
    voteBusy = false;
    if (btnEl) btnEl.disabled = false;
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
        window.notify?.('error', i18nVote.txt.cooldown);
        await loadVoteSites();
        return;
      }
      window.notify?.('error', data.error || i18nVote.txt.unknownErr);
      return;
    }

    if (data.status === 'REWARDED' || data.status === 'USED') {
      window.notify?.('success', i18nVote.txt.rewarded);
      await loadVoteSites();
      if (typeof loadVoteBalance === 'function') loadVoteBalance();
      return;
    }

    if (data.status === 'WAITING_CONFIRM' && !askedConfirm) {
      askedConfirm = true;
      manualConfirm = confirm(i18nVote.txt.needConfirm);
    }

    await new Promise(r => setTimeout(r, waitMs));
  }

  window.notify?.('error', i18nVote.txt.pendingLater);
  await loadVoteSites();
}

// vote click delegace
function initVoteClicks() {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.vote-btn');
    if (!btn) return;

    const id = parseInt(btn.dataset.id, 10);
    if (!id) return;

    startVote(id, btn);
  });
}

// ---------------- WALLET BALANCE ----------------
async function loadVoteBalance() {
  try {
    const res = await fetch('/api/get_wallet_balance.php?currency=VOTE_COIN', { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!data.ok) return;

    const el = document.querySelector('#voteBalance strong');
    if (el) el.textContent = data.balance;
  } catch (err) {
    console.error('Vote balance error:', err);
  }
}

async function loadDcBalance() {
  try {
    const res = await fetch('/api/get_wallet_balance.php?currency=DC', { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!data.ok) return;

    const el = document.querySelector('#dcBalance strong');
    if (el) el.textContent = data.balance;
  } catch (err) {
    console.error('DC balance error:', err);
  }
}

// ---------------- VIP MODAL + CONVERT ----------------
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
  document.getElementById('openVipModal')?.addEventListener('click', async () => {
    await loadAllCharactersForVip();
    document.getElementById('vipModal')?.classList.remove('hidden');
  });

  document.getElementById('vipCancel')?.addEventListener('click', () => {
    document.getElementById('vipModal')?.classList.add('hidden');
  });

  document.getElementById('vipConfirm')?.addEventListener('click', async (e) => {
    const btn = e.target;
    if (!btn || btn.disabled) return;

    const select = document.getElementById('vipCharSelect');
    const currencyEl = document.getElementById('vipCurrency');
    if (!select || !currencyEl) return;

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'Activating...';

    try {
      const res = await fetch('/api/activate_vip_24h.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          char_id: select.value,
          currency: currencyEl.value
        })
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        document.getElementById('vipModal')?.classList.add('hidden');
        loadVoteBalance();
        loadDcBalance();
      } else {
        alert(data.error || 'Activation failed.');
      }
    } catch (err) {
      alert('Server error.');
    }

    btn.disabled = false;
    btn.textContent = oldText || 'Activate';
  });
}

function initConvertVcToDc() {
  document.getElementById('convertVcToDc')?.addEventListener('click', async (e) => {
    const btn = e.target;
    if (!btn || btn.disabled) return;

    if (!confirm('Convert 4 Vote Coin into 1 Dragon Coin?')) return;

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'Processing...';

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

    btn.disabled = false;
    btn.textContent = oldText || 'Convert 4 Vote Coin → 1 Dragon Coin';
  });
}

// ---------------- SHOP ----------------
let shopProducts = [];
let myGameAccounts = [];
let shopLoadedOnce = false;

async function loadShop() {
  const box = document.getElementById('shopPremium');
  if (!box) return;

  box.innerHTML = '<div class="muted">Načítám produkty…</div>';

  const [pRes, aRes] = await Promise.all([
    fetch('/api/shop_list.php', { credentials: 'same-origin' }),
    fetch('/api/list_game_accounts_min.php', { credentials: 'same-origin' })
  ]);

  const pData = await pRes.json().catch(() => ({}));
  const aData = await aRes.json().catch(() => ({}));

  shopProducts = (pData.ok && Array.isArray(pData.products)) ? pData.products : [];
  myGameAccounts = (aData.ok && Array.isArray(aData.accounts)) ? aData.accounts : [];

  box.innerHTML = '';

  if (!shopProducts.length) {
    box.innerHTML = '<div class="muted">Žádné produkty.</div>';
    return;
  }

  shopProducts.forEach(prod => {
    const row = document.createElement('div');
    row.className = 'mini-row';

    const needsGameAcc = prod.code === 'PREM_GAME_30D';

    const selectHtml = needsGameAcc ? `
      <select class="shop-acc" data-pid="${prod.id}">
        ${myGameAccounts.map(a => `<option value="${a.id}">${a.login}</option>`).join('')}
      </select>
    ` : '';

    row.innerHTML = `
      <div class="mini-row">
        <div>
          <strong>${prod.name}</strong><br>
          <span class="muted">${prod.description || ''}</span>
        </div>

        <div style="display:flex; gap:10px; align-items:center; justify-content:flex-end;">
          ${selectHtml}
          <span class="tag">${prod.price_dc} DC</span>
          <button class="btn btn-small btn-primary shop-buy" data-id="${prod.id}">
            Koupit
          </button>
        </div>
      </div>
    `;

    box.appendChild(row);
  });
}

function initShopBuyClicks() {
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.shop-buy');
    if (!btn) return;

    const productId = parseInt(btn.dataset.id, 10);
    if (!productId) return;

    const prod = shopProducts.find(p => Number(p.id) === productId);
    if (!prod) return;

    btn.disabled = true;

    try {
      const payload = { product_id: productId };

      if (prod.code === 'PREM_GAME_30D') {
        const sel = document.querySelector(`.shop-acc[data-pid="${productId}"]`);
        const gaId = parseInt(sel?.value || '0', 10);
        if (!gaId) {
          window.notify?.('error', 'Vyber herní účet');
          btn.disabled = false;
          return;
        }
        payload.game_account_id = gaId;
      }

      const res = await fetch('/api/shop_buy.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        window.notify?.('success', 'Nákup dokončen');
        loadDcBalance();
        loadVoteBalance();
        return;
      }

      const err = data.error || 'Chyba nákupu';
      if (err === 'INSUFFICIENT_FUNDS') window.notify?.('error', 'Nedostatek DC');
      else if (err === 'ALREADY_PURCHASED') window.notify?.('error', 'Už koupeno');
      else window.notify?.('error', err);

    } finally {
      btn.disabled = false;
    }
  });
}

function initShopSubTabs() {
  const btns = document.querySelectorAll('#shop [data-shop-tab]');
  const panes = {
    premium: document.getElementById('shopPremium'),
    mounts: document.getElementById('shopMounts'),
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

function initShopLoadOnTabClick() {
  document.querySelector('[data-tab="shop"]')?.addEventListener('click', () => {
    initShopSubTabs();
    if (!shopLoadedOnce) {
      shopLoadedOnce = true;
      loadShop();
    }
  });
}

// ---------- init hooks pro část 3/3 ----------
function initPart3() {
  initAccountRowCharactersToggle();

  // vote
  initVoteClicks();

  // vip + convert
  initVipModal();
  initConvertVcToDc();

  // shop
  initShopBuyClicks();
  initShopLoadOnTabClick();

  // initial loads
  loadVoteSites();
  loadVoteBalance();
  loadDcBalance();
}

document.addEventListener('DOMContentLoaded', async () => {
  await initMeAndVipBox();
  initProfileTabs();

  initCreateAccountModal();
  initDeleteModal();
  initResetPasswordModal();
  initSetPrimaryAccount();
  initBugReport();
  initMyBugsList();

  await loadGameAccounts();

  if (document.getElementById('vote') || document.getElementById('shop')) {
    initPart3();
  }
});
})(); 