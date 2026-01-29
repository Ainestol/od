const params = new URLSearchParams(window.location.search);
const bugId = params.get('id');

if (!bugId) {
  alert('Chybí ID hlášení');
  location.href = '/profile/index.html';
}

const bugInfo = document.getElementById('bugInfo');
const messagesBox = document.getElementById('messages');
const replyBox = document.getElementById('replyBox');
const replyMsg = document.getElementById('replyMessage');
const replyBtn = document.getElementById('replySend');
const replyHint = document.getElementById('replyHint');

let canReply = false;

/* ===== NAČTENÍ DETAILU ===== */
fetch(`/api/get_my_bug_report.php?id=${bugId}`)
  .then(r => r.json())
  .then(data => {
    if (!data.ok) {
      bugInfo.innerHTML = '<div class="form-error">Hlášení nenalezeno</div>';
      return;
    }

    const b = data.bug;

    bugInfo.innerHTML = `
      <strong>${b.title}</strong><br>
      Kategorie: ${b.category}<br>
      Stav: <strong>${b.status.toUpperCase()}</strong><br>
      Vytvořeno: ${b.created_at}
    `;

    if (b.status === 'closed') {
      replyHint.textContent = 'Hlášení je uzavřeno.';
    }
  });

/* ===== NAČTENÍ ZPRÁV ===== */
fetch(`/api/get_my_bug_messages.php?id=${bugId}`)
  .then(r => r.json())
  .then(data => {
    messagesBox.innerHTML = '';

    if (!data.ok || !data.messages.length) {
      messagesBox.innerHTML = '<div class="muted">Zatím žádné zprávy</div>';
      canReply = true;
      replyBox.style.display = 'block';
      return;
    }

    let lastAuthor = null;

    data.messages.forEach(m => {
      const row = document.createElement('div');
      row.className = 'chat-row ' + m.author;

      row.innerHTML = `
        <strong>${m.author === 'admin' ? 'Admin' : 'Ty'}:</strong><br>
        ${m.message.replace(/\n/g,'<br>')}
        <div class="muted">${m.created_at}</div>
      `;

      messagesBox.appendChild(row);
      lastAuthor = m.author;
    });

    /* ===== PING-PONG LOGIKA ===== */
    if (lastAuthor === 'admin') {
      canReply = true;
      replyBox.style.display = 'block';
      replyHint.textContent = 'Můžeš odpovědět.';
    } else {
      canReply = false;
      replyBox.style.display = 'block';
      replyHint.textContent = 'Čeká se na odpověď admina.';
      replyBtn.disabled = true;
    }
  });

/* ===== ODESLÁNÍ ODPOVĚDI ===== */
replyBtn?.addEventListener('click', async () => {
  if (!canReply) return;

  const text = replyMsg.value.trim();
  if (!text) return;

  replyBtn.disabled = true;

  const res = await fetch('/api/add_bug_message.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      bug_id: bugId,
      message: text
    })
  });

  const data = await res.json().catch(() => ({}));

  if (data.ok) {
    location.reload();
  } else {
    alert(data.error || 'Chyba při odesílání');
    replyBtn.disabled = false;
  }
});
