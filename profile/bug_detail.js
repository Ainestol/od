document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  const bugInfo     = document.getElementById('bugInfo');
  const messagesBox = document.getElementById('messages');
  const replyBox    = document.getElementById('replyBox');
  const replyBtn    = document.getElementById('replySend');
  const replyMsg    = document.getElementById('replyMessage');
  const replyHint   = document.getElementById('replyHint');

  const confirmBox  = document.getElementById('confirmResolvedBox');
  const confirmBtn  = document.getElementById('confirmResolvedBtn');
  const backLink    = document.getElementById('backToProfile');

  // 1) lang – priorita: ?lang=en -> <html lang> -> cs
  const urlLang = (params.get('lang') || '').toLowerCase();
  const htmlLang = (document.documentElement.lang || '').toLowerCase();
  const lang = (urlLang === 'en' || urlLang === 'cs') ? urlLang : (htmlLang === 'en' ? 'en' : 'cs');

  // nastav lang do dokumentu (kvůli konzistenci)
  document.documentElement.lang = lang;

  const i18n = {
    cs: {
      pageTitle: 'Detail hlášení | Ordo Draconis',
      h1: 'Detail hlášení',
      history: 'Historie komunikace',
      reply: 'Odpověď',
      replyPh: 'Napiš odpověď…',
      send: 'Odeslat odpověď',
      none: 'Žádné zprávy',
      loadingDetail: 'Načítám detail…',
      loadingMsgs: 'Načítám zprávy…',

      back: '← Zpět na profil',
      waitingAdmin: 'Čeká se na odpověď administrátora.',
      sendError: 'Chyba při odesílání',
      tooLong: 'Zpráva může mít maximálně 1000 znaků.',
      locked: 'Ticket je uzamčen – problém byl potvrzen jako vyřešen.',
      confirmResolved: '✅ Problém je vyřešen',
      confirmText: 'Klikni pouze v případě, že problém skutečně funguje.',
      cannotConfirm: 'Nelze potvrdit vyřešení',
      notFound: 'Hlášení nebylo nalezeno',
      missingId: 'Chybí ID hlášení',

      // labels
      name: 'Název',
      category: 'Kategorie',
      status: 'Status',
      created: 'Vytvořeno',
      author_admin: 'Admin',
      author_system: 'Systém',
      author_user: 'Ty',
    },
    en: {
      pageTitle: 'Report detail | Ordo Draconis',
      h1: 'Report detail',
      history: 'Conversation history',
      reply: 'Reply',
      replyPh: 'Write a reply…',
      send: 'Send reply',
      none: 'No messages',
      loadingDetail: 'Loading detail…',
      loadingMsgs: 'Loading messages…',

      back: '← Back to profile',
      waitingAdmin: 'Waiting for admin response.',
      sendError: 'Error while sending message',
      tooLong: 'Message can have a maximum of 1000 characters.',
      locked: 'Ticket is locked – issue has been confirmed as resolved.',
      confirmResolved: '✅ Issue is resolved',
      confirmText: 'Click only if the issue is really fixed.',
      cannotConfirm: 'Cannot confirm resolution',
      notFound: 'Report not found',
      missingId: 'Missing report ID',

      // labels
      name: 'Title',
      category: 'Category',
      status: 'Status',
      created: 'Created',
      author_admin: 'Admin',
      author_system: 'System',
      author_user: 'You',
    }
  };

  const t = i18n[lang];

  // 2) Překlad statického HTML (jednorázově)
  document.title = t.pageTitle;

  const h1 = document.querySelector('main h1');
  if (h1) h1.textContent = t.h1;

  const historyH3 = document.querySelector('.profile-card h3');
  // první h3 je "Historie komunikace" v tvém HTML
  if (historyH3) historyH3.textContent = t.history;

  const replyH3 = replyBox?.querySelector('h3');
  if (replyH3) replyH3.textContent = t.reply;

  if (replyMsg) replyMsg.placeholder = t.replyPh;
  if (replyBtn) replyBtn.textContent = t.send;
  if (confirmBtn) confirmBtn.textContent = t.confirmResolved;

  const confirmTextP = confirmBox?.querySelector('p.muted');
  if (confirmTextP) confirmTextP.textContent = t.confirmText;

  // 3) Back link
  if (backLink) {
    backLink.textContent = t.back;
    backLink.href = (lang === 'en')
      ? '/profile/index-en.html?tab=support'
      : '/profile/index.html?tab=support';
  }

  if (!id) {
    if (bugInfo) bugInfo.innerHTML = `<div class="form-error">${t.missingId}</div>`;
    return;
  }

  /* =========================
     NAČTENÍ DETAILU TICKETU
     ========================= */
  async function loadBug() {
    const res = await fetch(`/api/get_my_bug_report.php?id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));

    if (!data.ok) {
      bugInfo.innerHTML = `<div class="form-error">${t.notFound}</div>`;
      return;
    }

    const b = data.bug;

    bugInfo.innerHTML = `
      <strong>${t.name}:</strong> ${b.title}<br>
      <strong>${t.category}:</strong> ${b.category}<br>
      <strong>${t.status}:</strong> ${String(b.status || '').toUpperCase()}<br>
      <strong>${t.created}:</strong> ${b.created_at}
    `;

    // confirm box jen při RESOLVED
    if (confirmBox && confirmBtn) {
      if (b.status === 'RESOLVED') {
        confirmBox.style.display = 'block';
        confirmBtn.disabled = false;
      } else {
        confirmBox.style.display = 'none';
      }
    }
  }

  /* =========================
     NAČTENÍ ZPRÁV
     ========================= */
  async function loadMessages() {
    const res = await fetch(`/api/get_my_bug_messages.php?id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));

    if (!data.ok || !Array.isArray(data.messages)) {
      messagesBox.innerHTML = `<div class="muted">${t.none}</div>`;
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
          ? t.author_admin
          : m.author_role === 'system'
          ? t.author_system
          : t.author_user;

      div.innerHTML = `
        <strong>${authorLabel}:</strong><br>
        ${m.message}<br>
        <small>${m.created_at}</small>
      `;

      messagesBox.appendChild(div);
    });

    // ping-pong logika
    if (replyBox) replyBox.style.display = 'block';

    if (lastAuthor === 'admin') {
      if (replyHint) replyHint.textContent = '';
      if (replyBtn) replyBtn.disabled = false;
    } else {
      if (replyHint) replyHint.textContent = t.waitingAdmin;
      if (replyBtn) replyBtn.disabled = true;
    }
  }

  /* =========================
     ODESLÁNÍ ZPRÁVY
     ========================= */
  replyBtn?.addEventListener('click', async () => {
    const text = (replyMsg?.value || '').trim();
    if (!text) return;

    if (text.length > 1000) {
      alert(t.tooLong);
      return;
    }

    replyBtn.disabled = true;

    const res = await fetch('/api/add_bug_message.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, message: text })
    });

    const data = await res.json().catch(() => ({}));

    if (!data.ok) {
      if (data.error === 'TICKET_LOCKED') alert(t.locked);
      else alert(t.sendError);

      replyBtn.disabled = false;
      return;
    }

    if (replyMsg) replyMsg.value = '';
    await loadMessages();
  });

  /* =========================
     POTVRZENÍ VYŘEŠENÍ USEREM
     ========================= */
  confirmBtn?.addEventListener('click', async () => {
    confirmBtn.disabled = true;

    const res = await fetch('/api/confirm_bug_resolved.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });

    const data = await res.json().catch(() => ({}));

    if (!data.ok) {
      alert(t.cannotConfirm);
      confirmBtn.disabled = false;
      return;
    }

    // po potvrzení schovat box
    if (confirmBox) confirmBox.style.display = 'none';

    await loadMessages();
  });

  // INIT placeholder texty (když je to v HTML natvrdo)
  if (bugInfo) bugInfo.innerHTML = `<div class="muted">${t.loadingDetail}</div>`;
  if (messagesBox) messagesBox.innerHTML = `<div class="muted">${t.loadingMsgs}</div>`;

  await loadBug();
  await loadMessages();
});