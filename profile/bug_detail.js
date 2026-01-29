document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  if (!id) {
    document.getElementById('bugInfo').innerHTML =
      '<div class="form-error">Chybí ID hlášení</div>';
    return;
  }

  const bugInfo = document.getElementById('bugInfo');
  const messagesBox = document.getElementById('messages');
  const replyBox = document.getElementById('replyBox');
  const replyBtn = document.getElementById('replySend');
  const replyMsg = document.getElementById('replyMessage');
  const replyHint = document.getElementById('replyHint');

  /* =========================
     NAČTENÍ DETAILU TICKETU
     ========================= */
  async function loadBug() {
    const res = await fetch(`/api/get_my_bug_report.php?id=${id}`);
    const data = await res.json();

    if (!data.ok) {
      bugInfo.innerHTML =
        '<div class="form-error">Hlášení nebylo nalezeno</div>';
      return;
    }

    const b = data.bug;

    bugInfo.innerHTML = `
      <strong>Název:</strong> ${b.title}<br>
      <strong>Kategorie:</strong> ${b.category}<br>
      <strong>Status:</strong> ${b.status.toUpperCase()}<br>
      <strong>Vytvořeno:</strong> ${b.created_at}
    `;
  }

  /* =========================
     NAČTENÍ ZPRÁV
     ========================= */
  async function loadMessages() {
    const res = await fetch(`/api/get_my_bug_messages.php?id=${id}`);
    const data = await res.json();

    if (!data.ok) {
      messagesBox.innerHTML =
        '<div class="muted">Žádné zprávy</div>';
      return;
    }

    messagesBox.innerHTML = '';
    let lastAuthor = null;

    data.messages.forEach(m => {
      lastAuthor = m.author_role;

      const div = document.createElement('div');
      div.className = 'profile-card muted';
      div.style.marginBottom = '10px';

      div.innerHTML = `
        <strong>${m.author_role === 'admin' ? 'Admin' : 'Ty'}:</strong><br>
        ${m.message}<br>
        <small>${m.created_at}</small>
      `;

      messagesBox.appendChild(div);
    });

    /* ping-pong logika */
    if (lastAuthor === 'admin') {
      replyBox.style.display = 'block';
      replyHint.textContent = '';
      replyBtn.disabled = false;
    } else {
      replyBox.style.display = 'block';
      replyHint.textContent =
        'Čeká se na odpověď administrátora.';
      replyBtn.disabled = true;
    }
  }

  /* =========================
     ODESLÁNÍ ZPRÁVY
     ========================= */
  replyBtn.addEventListener('click', async () => {
    const text = replyMsg.value.trim();
    if (!text) return;

    replyBtn.disabled = true;

    const res = await fetch('/api/add_bug_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id,
        message: text
      })
    });

    const data = await res.json();

    if (!data.ok) {
      alert(
        data.error === 'WAIT_FOR_ADMIN'
          ? 'Počkej na odpověď administrátora'
          : 'Chyba při odesílání'
      );
      replyBtn.disabled = false;
      return;
    }

    replyMsg.value = '';
    await loadMessages();
  });

  await loadBug();
  await loadMessages();
});
