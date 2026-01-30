document.addEventListener('DOMContentLoaded', () => {
  console.log('BUG ADMIN DETAIL JS LOADED');

  /* =========================
     ZÁKLADNÍ PROMĚNNÉ
     ========================= */
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  const saveBtn   = document.getElementById('saveBtn');
  const textarea  = document.getElementById('adminMessage');
  const history   = document.getElementById('messageHistory');
  const statusSel = document.getElementById('bugStatus');
  const badge     = document.getElementById('statusBadge');

  if (!id || !saveBtn || !textarea || !history || !statusSel) {
    console.error('Chybí povinné elementy v DOM');
    return;
  }

  /* =========================
     STATUS BADGE
     ========================= */
  function updateBadge(status) {
    if (!badge) return;
    badge.className = 'status-badge status-' + status;
    badge.textContent = status.replace('_', ' ').toUpperCase();
  }

  statusSel.addEventListener('change', () => {
    updateBadge(statusSel.value);
  });

  /* =========================
     NAČTENÍ DETAILU BUGU
     ========================= */
  async function loadBugDetail() {
    try {
      const res = await fetch(`/admin/api/get_bug_report.php?id=${id}`);
      const data = await res.json();
      if (!data.ok) return;

      const b = data.bug;

      document.getElementById('bugTitle').textContent    = b.title;
      document.getElementById('bugUser').textContent     = b.email;
      document.getElementById('bugAccount').textContent  = b.game_account || '-';
      document.getElementById('bugCategory').textContent = b.category;

      const msg = document.getElementById('bugUserMessage');
      msg.textContent = b.message && b.message.trim()
        ? b.message
        : '⚠ Uživatel nepřiložil žádný popis problému.';

      statusSel.value = b.status;
      updateBadge(b.status);

    } catch (e) {
      console.error('Chyba při načítání detailu bugu', e);
    }
  }

  /* =========================
     NAČTENÍ HISTORIE ZPRÁV
     ========================= */
  async function loadMessages() {
    try {
      const res = await fetch(`/admin/api/get_bug_messages.php?id=${id}`);
      const data = await res.json();
      if (!data.ok) return;

      history.innerHTML = '';
      let lastRole = null;

      data.messages.forEach(m => {
        lastRole = m.author_role;

        const div = document.createElement('div');
        div.className = 'profile-card muted';
        div.style.marginBottom = '10px';

        div.innerHTML = `
          <strong>${m.author_role.toUpperCase()}</strong><br>
          ${m.message}<br>
          <small>${m.created_at}</small>
        `;

        history.appendChild(div);
      });

      /* ping-pong logika */
      if (lastRole === 'admin') {
        textarea.disabled = true;
        textarea.placeholder = 'Čeká se na odpověď uživatele…';
      } else {
        textarea.disabled = false;
        textarea.placeholder = 'Napiš odpověď…';
      }

    } catch (e) {
      console.error('Chyba při načítání zpráv', e);
      history.innerHTML =
        '<div class="form-error">Chyba při načítání historie</div>';
    }
  }

  /* =========================
     ULOŽENÍ ZPRÁVY + STATUSU
     ========================= */
  saveBtn.addEventListener('click', async () => {
    const message = textarea.value.trim();
    const status  = statusSel.value;

    saveBtn.disabled = true;

    try {
      const res = await fetch('/admin/api/save_bug_detail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id,
          status,
          message
        })
      });

      const data = await res.json();

      if (!data.ok) {
        alert(data.detail || data.error || 'Chyba při ukládání');
        saveBtn.disabled = false;
        return;
      }

      textarea.value = '';
      await loadMessages();
      alert('Uloženo');

    } catch (e) {
      console.error(e);
      alert('Chyba komunikace se serverem');
    }

    saveBtn.disabled = false;
  });

  /* =========================
     INIT
     ========================= */
  loadBugDetail();
  loadMessages();
});
