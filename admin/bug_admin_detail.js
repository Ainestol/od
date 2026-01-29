document.addEventListener('DOMContentLoaded', () => {
  console.log('BUG ADMIN DETAIL JS LOADED');

  const sendBtn = document.getElementById('sendMessage');
  const textarea = document.getElementById('newMessage');
  const history = document.getElementById('messageHistory');

  if (!sendBtn || !textarea || !history) {
    console.warn('Bug admin detail: missing elements');
    return;
  }

  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  if (!id) {
    alert('Chybí ID ticketu');
    return;
  }

  // === ODESLÁNÍ ZPRÁVY ===
  sendBtn.addEventListener('click', async () => {
    const text = textarea.value.trim();
    if (!text) {
      alert('Zpráva je prázdná');
      return;
    }

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
    loadMessages();
  });

  // === NAČTENÍ HISTORIE ===
  async function loadMessages() {
    const res = await fetch(`/admin/api/get_bug_messages.php?id=${id}`);
    const data = await res.json();

    if (!data.ok) return;

    history.innerHTML = '';

    data.messages.forEach(m => {
      const div = document.createElement('div');
      div.className = `bug-message ${m.author_role}`;
      div.innerHTML = `
        <strong>${m.author_role === 'admin' ? 'Admin' : 'User'}:</strong><br>
        ${m.message}
        <div class="muted" style="font-size:12px;">
          ${m.created_at}
        </div>
      `;
      history.appendChild(div);
    });
  }

  loadMessages();
});
