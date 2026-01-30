document.addEventListener('DOMContentLoaded', () => {
  console.log('BUG ADMIN DETAIL JS LOADED');

  const sendBtn  = document.getElementById('sendMessage');
  const textarea = document.getElementById('newMessage');
  const history  = document.getElementById('messageHistory');
  const statusEl = document.getElementById('bugStatus');

  if (!sendBtn || !textarea || !history || !statusEl) {
    console.warn('Bug admin detail: missing DOM elements');
    return;
  }

  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  if (!id) {
    alert('Chybí ID ticketu');
    return;
  }

  /* =========================
     ODESLÁNÍ ZPRÁVY (ADMIN)
     ========================= */
  sendBtn.addEventListener('click', async () => {
    const text = textarea.value.trim();
    if (!text) {
      alert('Zpráva je prázdná');
      return;
    }

    sendBtn.disabled = true;

    try {
      const res = await fetch('/admin/api/add_bug_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, message: text })
      });

      const data = await res.json();

      if (!data.ok) {
        if (data.error === 'WAIT_FOR_USER') {
          alert('Čeká se na odpověď uživatele (ping-pong)');
        } else {
          alert('Chyba při odesílání zprávy');
        }
        return;
      }

      textarea.value = '';
      await loadMessages();

    } catch (e) {
      alert('Chyba spojení se serverem');
    } finally {
      sendBtn.disabled = false;
    }
  });

  /* =========================
     NAČTENÍ HISTORIE + UX
     ========================= */
  async function loadMessages() {
    try {
      const res = await fetch(`/admin/api/get_bug_messages.php?id=${id}`);
      const data = await res.json();

      if (!data.ok) return;

      history.innerHTML = '';

      if (!data.messages.length) {
        history.innerHTML = '<div class="muted">Zatím žádné zprávy</div>';
        textarea.disabled = false;
        return;
      }

      data.messages.forEach(m => {
        const div = document.createElement('div');
        div.className = `bug-message ${m.author_role}`;
        div.innerHTML = `
          <strong>${m.author_role === 'admin' ? 'Admin' : 'User'}:</strong><br>
          ${m.message}
          <div class="muted" style="font-size:12px;">${m.created_at}</div>
        `;
        history.appendChild(div);
      });

      /* 🔒 PING-PONG */
      const last = data.messages[data.messages.length - 1];

      if (last.author_role === 'admin') {
        textarea.disabled = true;
        textarea.placeholder = 'Čeká se na odpověď uživatele';
      } else {
        textarea.disabled = false;
        textarea.placeholder = 'Napiš odpověď…';
      }

      /* 🔒 CLOSED */
      if (statusEl.value === 'closed') {
        textarea.disabled = true;
        textarea.placeholder = 'Ticket je uzavřen';
      }

    } catch (e) {
      history.innerHTML = '<div class="form-error">Chyba načítání zpráv</div>';
    }
  }

  /* =========================
     ZMĚNA STATUSU
     ========================= */
  statusEl.addEventListener('change', async () => {
    try {
      await fetch('/admin/api/update_bug_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id,
          status: statusEl.value
        })
      });

      loadMessages();

    } catch (e) {
      alert('Chyba při ukládání statusu');
    }
  });

  /* INIT */
  loadMessages();
});
