let currentUserId = null;

/* ===== HLEDÁNÍ UŽIVATELE ===== */

document.getElementById('searchUser').addEventListener('click', async () => {

  const email = document.getElementById('searchEmail').value.trim();
  if (!email) return;

  const res = await fetch('/admin/api/economy_search_user.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({email})
  });

  const data = await res.json();

  if (!data.ok) {
    alert('Uživatel nenalezen');
    return;
  }

  currentUserId = data.user.id;

  document.getElementById('userResult').classList.remove('hidden');
  document.getElementById('userEmail').textContent = data.user.email;

  updateWalletUI(data.wallet);
  loadLedger(currentUserId);
});


/* ===== ÚPRAVA BALANCE ===== */

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

  const res = await fetch('/admin/api/economy_adjust_balance.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      user_id: currentUserId,
      currency,
      amount
    })
  });

  const data = await res.json();

  if (!data.ok) {
    alert('Chyba při úpravě');
    return;
  }

  document.getElementById('amountInput').value = '';

  await refreshUser();
});


/* ===== REFRESH USER ===== */

async function refreshUser() {

  const email = document.getElementById('userEmail').textContent;

  const res = await fetch('/admin/api/economy_search_user.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({email})
  });

  const data = await res.json();
  if (!data.ok) return;

  updateWalletUI(data.wallet);
  loadLedger(currentUserId);
}


/* ===== WALLET UI ===== */

function updateWalletUI(wallet) {
  document.getElementById('adminVC').textContent =
    wallet.VOTE_COIN ?? 0;

  document.getElementById('adminDC').textContent =
    wallet.DC ?? 0;
}


/* ===== LEDGER ===== */

async function loadLedger(userId) {

  const res = await fetch('/admin/api/economy_get_ledger.php?user_id='+userId);
  const data = await res.json();

  const box = document.getElementById('ledgerList');
  box.innerHTML = '';

  if (!data.ok) return;

  data.ledger.forEach(row => {

    const div = document.createElement('div');

    div.textContent =
      `${row.created_at} | ${row.currency} | ${row.amount} | ${row.reason}`;

    box.appendChild(div);
  });
}
