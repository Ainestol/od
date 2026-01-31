document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  if (!id) {
    document.getElementById('bugInfo').innerHTML =
      '<div class="form-error">Chybí ID hlášení</div>';
    return;
  }

  const bugInfo   = document.getElementById('bugInfo');
  const messagesBox = document.getElementById('messages');
  const replyBox  = document.getElementById('replyBox');
  const replyBtn  = document.getElementById('replySend');
  const replyMsg  = document.getElementById('replyMessage');
  const replyHint = document.getElementById('replyHint');

  const confirmBox = document.getElementById('confirmResolvedBox');
  const confirmBtn = document.getElementById('confirmResolvedBtn');

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
      <strong>Status:</strong> ${b.status}<br>
      <strong>Vytvořeno:</strong> ${b.created_at}
    `;

    /* 👉 TLAČÍTKO PRO USERA: jen při RESOLVED */
    if (b.status === 'RESOLVED') {
      confirmBox.style.display = 'block';
    } else {
      confirmBox.style.display = 'none';
    }
  }

  /* =========================
     NAČTENÍ ZPRÁV
     ========================= */
  async function loadMessages() {
    const res = await fetch(`/api/get_my_bug_messages.php?id=${id}`);
    const data = await res.json();

    if (!data.ok || !Array.isArray(data.messages)) {
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

      const authorLabel =
        m.author_role === 'admin'
          ? 'Admin'
          : m.author_role === 'system'
          ? 'Systém'
          : 'Ty';

      div.innerHTML = `
        <strong>${authorLabel}:</strong><br>
        ${m.message}<br>
        <small>${m.created_at}</small>
      `;

      messagesBox.appendChild(div);
    });

    /* ping-pong logika */
    replyBox.style.display = 'block';

    if (lastAuthor === 'admin') {
      replyHint.textContent = '';
      replyBtn.disabled = false;
    } else {
      replyHint.textContent = 'Čeká se na odpověď administrátora.';
      replyBtn.disabled = true;
    }
  }

  /* =========================
     ODESLÁNÍ ZPRÁVY
     ========================= */
  replyBtn.addEventListener('click', async () => {
    const text = replyMsg.value.trim();
    if (!text) return;

    if (text.length > 1000) {
      alert('Zpráva může mít maximálně 1000 znaků.');
      return;
    }

    replyBtn.disabled = true;

    const res = await fetch('/api/add_bug_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, message: text })
    });

    const data = await res.json();

    if (!data.ok) {
  if (data.error === 'TICKET_LOCKED') {
    alert('Ticket je uzamčen – problém byl potvrzen jako vyřešen.');
  } else {
    alert('Chyba při odesílání');
  }
  replyBtn.disabled = false;
  return;
}

    replyMsg.value = '';
    await loadMessages();
  });

  /* =========================
     POTVRZENÍ VYŘEŠENÍ USEREM
     ========================= */
  confirmBtn.addEventListener('click', async () => {
    confirmBtn.disabled = true;

    const res = await fetch('/api/confirm_bug_resolved.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });

    const data = await res.json();

    if (!data.ok) {
      alert('Nelze potvrdit vyřešení');
      confirmBtn.disabled = false;
      return;
    }

    confirmBox.style.display = 'none';
    await loadMessages();
  });

  /* INIT */
  await loadBug();
  await loadMessages();
});
