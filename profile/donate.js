document.addEventListener('DOMContentLoaded', () => {

  console.log('DONATE JS LOADED');

  const modal = document.getElementById('donateConfirmModal');
  const confirmBtn = document.getElementById('donateConfirmOk');
  const cancelBtn = document.getElementById('donateConfirmCancel');
  const textEl = document.getElementById('donateConfirmText');

  let selectedAmount = null;

  // 👉 BUY CLICK (TADY SE NASTAVUJE PACK!)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.buy-dc');
    if (!btn) return;

    selectedAmount = Number(btn.dataset.pack);

    console.log('SELECTED PACK:', selectedAmount);

    textEl.textContent = `Chceš koupit ${selectedAmount} DC?`;
    modal.classList.remove('hidden');
  });

  // 👉 CONFIRM
  confirmBtn?.addEventListener('click', async () => {

    console.log('🔥 CONFIRM CLICK', selectedAmount);

    if (!selectedAmount) {
      alert('No pack selected');
      return;
    }

    try {
      confirmBtn.disabled = true;

      const res = await fetch('/api/create-checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pack: selectedAmount,
          currency: 'eur'
        })
      });

     const data = await res.json();

console.log('Stripe response:', data);

// 🔴 nejdřív HTTP chyba
if (!res.ok) {
  console.error('HTTP ERROR:', data);
  alert(data.error || 'Payment error');
  return;
}

// 🔴 pak Stripe logika
if (data.url) {
  window.location.href = data.url;
  return;
}

// 🔴 fallback (když není URL)
console.error('Stripe response error:', data);
alert(data.error || 'Stripe error');


    } catch (err) {
      console.error('❌ ERROR DETAIL:', err);
      alert('Payment error');
    } finally {
      confirmBtn.disabled = false;
    }
  });

  // 👉 CANCEL
  cancelBtn?.addEventListener('click', () => {
    modal.classList.add('hidden');
    selectedAmount = null;
  });

});