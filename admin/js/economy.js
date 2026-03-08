let currentUserId = null;

/* ============================= */
/* FETCH WRAPPER (CSRF + SESSION) */
/* ============================= */

const _origFetch = window.fetch;

window.fetch = function (url, options = {}) {

  options.credentials = options.credentials || 'same-origin';
  options.cache = 'no-store';

  options.headers = options.headers || {};

  if (options.method && options.method.toUpperCase() === 'POST') {
    options.headers['X-CSRF-TOKEN'] = window.CSRF_TOKEN || '';
  }

  return _origFetch(url, options);
};


/* ============================= */
/* SAFE JSON PARSE */
/* ============================= */

async function safeJson(res) {
  try {
    return await res.json();
  } catch {
    console.error("JSON parse error", res);
    return { ok:false };
  }
}


/* ============================= */
/* HLEDÁNÍ UŽIVATELE */
/* ============================= */

document.getElementById('searchUser').addEventListener('click', async () => {

  const email = document.getElementById('searchEmail').value.trim();
  if (!email) return;

  try {

    const res = await fetch('/admin/api/economy_search_user.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({email})
    });

    const data = await safeJson(res);

    if (!data.ok) {
      alert('Uživatel nenalezen');
      return;
    }

    currentUserId = data.user.id;

    document.getElementById('userResult').classList.remove('hidden');
    document.getElementById('userEmail').textContent = data.user.email;

    updateWalletUI(data.wallet);
    loadLedger(currentUserId);

  } catch (e) {
    console.error(e);
    alert('Chyba komunikace se serverem');
  }

});


/* ============================= */
/* ÚPRAVA BALANCE */
/* ============================= */

document.getElementById('adjustBalance').addEventListener('click', async () => {

  if (!currentUserId) {
    alert('Nejprve vyhledej uživatele');
    return;
  }

  const currency = document.getElementById('currencySelect').value;
  const amount   = parseInt(document.getElementById('amountInput').value);

  if (!amount || isNaN(amount)) {
    alert('Zadej částku (může být i záporná)');
    return;
  }

  try {

    const res = await fetch('/admin/api/economy_adjust_balance.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        user_id: currentUserId,
        currency,
        amount
      })
    });

    const data = await safeJson(res);

    if (!data.ok) {
      alert('Chyba při úpravě');
      return;
    }

    document.getElementById('amountInput').value = '';

    await refreshUser();

  } catch (e) {
    console.error(e);
    alert('Server error');
  }

});


/* ============================= */
/* REFRESH USER */
/* ============================= */

async function refreshUser() {

  const email = document.getElementById('userEmail').textContent;

  try {

    const res = await fetch('/admin/api/economy_search_user.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({email})
    });

    const data = await safeJson(res);

    if (!data.ok) return;

    updateWalletUI(data.wallet);
    loadLedger(currentUserId);

  } catch (e) {
    console.error(e);
  }
}


/* ============================= */
/* WALLET UI */
/* ============================= */

function updateWalletUI(wallet) {

  document.getElementById('adminVC').textContent =
    wallet.VOTE_COIN ?? 0;

  document.getElementById('adminDC').textContent =
    wallet.DC ?? 0;

}


/* ============================= */
/* LEDGER */
/* ============================= */

async function loadLedger(userId) {

  try {

    const res = await fetch('/admin/api/economy_get_ledger.php?user_id='+userId);

    const data = await safeJson(res);

    const box = document.getElementById('ledgerList');
    box.innerHTML = '';

    if (!data.ok) return;

    data.ledger.forEach(row => {

      const div = document.createElement('div');

      div.textContent =
        `${row.created_at} | ${row.currency} | ${row.amount} | ${row.reason}`;

      box.appendChild(div);

    });

  } catch (e) {
    console.error(e);
  }

}