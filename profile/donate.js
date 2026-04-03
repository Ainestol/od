document.addEventListener('DOMContentLoaded', () => {

  const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';

  const modal = document.getElementById('donateConfirmModal');
  const confirmBtn = document.getElementById('donateConfirmOk');
  const cancelBtn = document.getElementById('donateConfirmCancel');
  const textEl = document.getElementById('donateConfirmText');

  let selectedAmount = null;

  // 👉 CLICK NA BUY
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.buy-dc');
    if (!btn) return;

    selectedAmount = Number(btn.dataset.pack);

    if (!selectedAmount || isNaN(selectedAmount)) {
      alert('Invalid pack');
      return;
    }

    const text = isEn
      ? `Do you want to purchase ${selectedAmount} DC?\n\nYou will be redirected to the payment gateway.`
      : `Chceš koupit ${selectedAmount} DC?\n\nPo kliknutí budeš přesměrován na platební bránu.`;

    textEl.textContent = text;
    modal.classList.remove('hidden');
  });

  // 👉 CONFIRM
  confirmBtn?.addEventListener('click', async () => {

  console.log('🔥 CONFIRM CLICK'); // ← SEM

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
        }),
        skipCsrf: true
      });

      const data = await res.json();

      console.log('Stripe response:', data);

      if (data.url) {
        window.location.href = data.url;
        return; // 🔥 STOP
      }

      alert(data.error || 'Stripe error');

    } catch (err) {
  console.error('❌ ERROR DETAIL:', err); // ← SEM
  alert('Payment error');
}finally {
      confirmBtn.disabled = false;
    }
  });

  // 👉 CANCEL
  cancelBtn?.addEventListener('click', () => {
    modal.classList.add('hidden');
    selectedAmount = null;
  });

});