document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('sendMessage');
  const textarea = document.getElementById('newMessage');
  const history = document.getElementById('messageHistory');

  if (!btn || !textarea) return;

  btn.addEventListener('click', async () => {
    const text = textarea.value.trim();
    if (!text) {
      alert('Zpráva je prázdná');
      return;
    }
console.log('BUG ADMIN DETAIL JS LOADED');

    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    const res = await fetch('/admin/api/add_bug_message.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id,
        message: text
      })
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

    // úspěch
    textarea.value = '';
    loadMessages(); // znovu načteme historii
  });

  // načtení historie
  async function loadMessages() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    const res = await fetch(`/admin/api/get_bug_messages.php?id=${id}`);
    const data = await res.json();

    if (!data.ok) return;

    history.innerHTML = '';

    data.messages.forEach(m => {
      const div = document.createElement('div');
      div.className = 'bug-message ' + m.author_role;
      div.innerHTML = `
        <strong>${m.author_role === 'admin' ? 'Admin' : 'User'}:</strong><br>
        ${m.message}
        <div class="muted" style="font-size:12px;">${m.created_at}</div>
      `;
      history.appendChild(div);
    });
  }

  loadMessages();
});
