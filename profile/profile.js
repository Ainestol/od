console.log('[profile] loaded');
window.addEventListener('error', (e) => console.error('[profile] JS error:', e.message, e.filename, e.lineno));
window.addEventListener('unhandledrejection', (e) => console.error('[profile] Promise error:', e.reason));
// /js/profile.js
(() => {
  'use strict';

  function isEn() {
    return (document.documentElement.lang || '').toLowerCase() === 'en';
  }

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

  async function initMeAndVipBox() {
    try {
      const res = await fetch('/api/me.php', { credentials: 'same-origin' });
      const data = await res.json();

      if (!data.ok) {
        window.location.href = isEn() ? '/auth/login-en.html' : '/auth/login.html';
        return;
      }

      // WEB VIP box
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

      // Admin button
      if (data.role === 'admin') {
        const btn = document.getElementById('adminBtn');
        if (btn) btn.style.display = 'inline-flex';
      }

    } catch (e) {
      window.location.href = '/auth/login.html';
    }
  }

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

    if (countEl) countEl.textContent = `(${(data.accounts || []).length} / 10)`;

    list.innerHTML = '';

    if (!data.ok || !data.accounts || !data.accounts.length) {
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
        if (acc.premium_days_left <= 3) {
          return `<span class="tag warning">Premium: ${acc.premium_days_left} dny</span>`;
        }
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
      if (msg) { msg.style.display='none'; msg.textContent=''; }
    };
    const close = () => modal.classList.add('hidden');

    openBtn?.addEventListener('click', (e) => { e.preventDefault(); open(); });
    cancelBtn?.addEventListener('click', (e) => { e.preventDefault(); close(); });
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

    submitBtn.addEventListener('click', async (e) => {
      e.preventDefault();

      const payload = {
        login: (login?.value || '').trim(),
        password: pass?.value || ''
      };

      submitBtn.disabled = true;
      try {
        const res = await fetch('/api/create_game_account.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        });

        const data = await res.json().catch(()=> ({}));

        if (data.ok) {
          modal.classList.add('hidden');
          notify('success', 'Herní účet byl vytvořen');

          if (pass) pass.value = '';
          if (login) login.value = '';

          await loadGameAccounts();
          setTimeout(() => modal.classList.add('hidden'), 800);
        } else {
          notify('error', data.error || 'Chyba při vytváření účtu');
        }
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

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
      const loginEl = document.getElementById('deleteLogin');
      const keyEl = document.getElementById('deleteKeyword');
      if (loginEl) loginEl.textContent = currentLogin;
      if (keyEl) keyEl.textContent = keyword;

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
        headers: {'Content-Type':'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ login: currentLogin })
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        modal.classList.add('hidden');
        notify('success', 'Herní účet byl smazán');
        await loadGameAccounts();
        return;
      }

      if (data.error === 'ACCOUNT_HAS_ACTIVE_CHARACTERS') notify('error', 'Účet má aktivní postavy');
      else notify('error', 'Nepodařilo se smazat účet');

      confirmBtn.disabled = false;
    });
  }

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

    cancelBtn.addEventListener('click', () => modal.classList.add('hidden'));

    confirmBtn.addEventListener('click', async () => {
      confirmBtn.disabled = true;

      const res = await fetch('/api/reset_game_password.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ login: currentLogin, password: pass1.value })
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

  function initSetPrimaryAccount() {
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('button[data-primary]');
      if (!btn) return;

      const login = btn.dataset.primary;

      const res = await fetch('/api/set_primary_account.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ login })
      });

      const data = await res.json().catch(() => ({}));

      if (data.ok) {
        notify('success', 'Primární účet nastaven');
        loadGameAccounts();
      } else {
        notify('error', 'Nastavení primárního účtu se nezdařilo');
      }
    });
  }

  function initBugReport() {
    const accountSelect = document.getElementById('bugAccount');
    if (accountSelect) {
      fetch('/api/list_game_accounts.php', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
          if (!data.ok) return;
          data.accounts.forEach(acc => {
            const opt = document.createElement('option');
            opt.value = acc.login;
            opt.textContent = acc.login;
            accountSelect.appendChild(opt);
          });
        });
    }

    document.getElementById('bugForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();

      const payload = {
        game_account: document.getElementById('bugAccount')?.value,
        category: document.getElementById('bugCategory')?.value,
        title: (document.getElementById('bugTitle')?.value || '').trim(),
        message: (document.getElementById('bugMessage')?.value || '').trim()
      };

      if (payload.message.length > 1000) {
        notify('error', 'Text je příliš dlouhý (max. 1000 znaků)');
        return;
      }

      const res = await fetch('/api/create_bug_report.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials: 'same-origin',
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

  function initMyBugsList() {
    const box = document.getElementById('myBugs');
    if (!box) return;

    fetch('/api/list_my_bug_reports.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data.ok) {
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
      });
  }

  async function loadVipMap(login) {
    const res = await fetch(`/api/list_characters_with_vip.php?account=${encodeURIComponent(login)}`, { credentials: 'same-origin' });
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

  function initAccountRowCharactersToggle() {
    document.addEventListener('click', async (e) => {
      const row = e.target.closest('.account-row');
      if (!row) return;

      const login = row.dataset.login;
      const box = document.getElementById('chars-' + login);
      if (!box) return;

      if (!box.classList.contains('hidden')) {
        box.classList.add('hidden');
        box.innerHTML = '';
        return;
      }

      box.classList.remove('hidden');
      box.innerHTML = '<div class="muted">Loading characters…</div>';

      try {
        const vipMap = await loadVipMap(login);
        const res = await fetch(`/api/list_characters.php?account=${encodeURIComponent(login)}`, { credentials: 'same-origin' });
        const data = await res.json().catch(() => ({}));

        if (!data.ok || !data.characters?.length) {
          box.innerHTML = '<div class="muted">No characters</div>';
          return;
        }

        box.innerHTML = '';
        data.characters.forEach(ch => {
          const el = document.createElement('div');
          el.className = 'char-row';

          const vipData = vipMap[ch.charId];
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

  // --- INIT ---
  document.addEventListener('DOMContentLoaded', async () => {
    // 1) auth + VIP + admin
    initMeAndVipBox();

    // 2) tabs
    initProfileTabs();

    // 3) game accounts + modaly + actions
    await loadGameAccounts();
    initCreateAccountModal();
    initDeleteModal();
    initResetPasswordModal();
    initSetPrimaryAccount();
    initAccountRowCharactersToggle();

    // 4) bug sekce
    initBugReport();
    initMyBugsList();
  });

  // (pokud někde voláš loadGameAccounts() z jiného JS, necháme export)
  window.loadGameAccounts = loadGameAccounts;
  window.notify = notify;

})();
console.log('[profile] end');