document.addEventListener('DOMContentLoaded', () => {
  console.log('BUG ADMIN DETAIL JS LOADED');

  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  const saveBtn   = document.getElementById('saveBtn');
  const textarea  = document.getElementById('adminMessage');
  const history   = document.getElementById('messageHistory');
  const statusSel = document.getElementById('bugStatus');

  if (!id || !saveBtn || !textarea || !history || !statusSel) {
    console.warn('Missing elements');
    return;
  }

  // === NAČTENÍ HISTORIE ===
  async function loadMessages() {
    const r = await fetch(`/admin/api/get_bug_messages.php?id=${id}`);
    const d = await r.json();
    if (!d.ok) return;

    history.innerHTML = '';
    let lastRole = null;

    d.messages.forEach(m => {
      lastRole = m.author_role;
      const div = document.createElement('div');
      div.className = 'profile-card muted';
      div.innerHTML = `
        <strong>${m.author_role.toUpperCase()}</strong><br>
        ${m.message}<br>
        <small>${m.created_at}</small>
      `;
      history.appendChild(div);
    });

    // 🔒 ping-pong
    if (lastRole === 'admin') {
      textarea.disabled = true;
      textarea.placeholder = 'Čeká se na odpověď uživatele…';
    } else {
      textarea.disabled = false;
    }
  }

  // === ULOŽENÍ (status + zpráva) ===
  saveBtn.addEventListener('click', async () => {
    const message = textarea.value.trim();
    const status  = statusSel.value;

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
      alert(data.error || 'Chyba při ukládání');
      return;
    }

    textarea.value = '';
    await loadMessages();
    alert('Uloženo');
  });

  loadMessages();
});
