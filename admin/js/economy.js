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

  document.getElementById('userResult').classList.remove('hidden');
  document.getElementById('userEmail').textContent = data.user.email;
  document.getElementById('adminVC').textContent = data.wallet.VOTE_COIN || 0;
  document.getElementById('adminDC').textContent = data.wallet.DC || 0;

  loadLedger(data.user.id);
});

async function loadLedger(userId) {

  const res = await fetch('/admin/api/economy_get_ledger.php?user_id='+userId);
  const data = await res.json();

  const box = document.getElementById('ledgerList');
  box.innerHTML = '';

  if (!data.ok) return;

  data.ledger.forEach(row => {
    const div = document.createElement('div');
    div.textContent = `${row.created_at} | ${row.currency} | ${row.amount} | ${row.reason}`;
    box.appendChild(div);
  });
}
