document.addEventListener('DOMContentLoaded', () => {

  const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';

  const modal = document.getElementById('donateConfirmModal');
  const confirmBtn = document.getElementById('donateConfirmOk');
  const cancelBtn = document.getElementById('donateConfirmCancel');
  const textEl = document.getElementById('donateConfirmText');

  let selectedAmount = null;

  // 👉 CLICK NA BUY (delegace)
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
    if (!selectedAmount || isNaN(selectedAmount)) {
      alert('Invalid pack');
      return;
    }

    try {
      confirmBtn.disabled = true;

      const currency = 'eur'; // zatím fix

      const res = await fetch('/api/create-checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '' },
        body: JSON.stringify({
          pack: selectedAmount,
          currency: currency
        })
      });

      // 🔥 správně JSON (žádné text parse)
      const data = await res.json();

      console.log('STRIPE RESPONSE:', data);

      // 🔥 HLAVNÍ FIX – okamžitý exit
      if (data && data.url) {
        window.location.href = data.url;
        return;
      }

      console.error('Stripe error:', data);
      alert(data?.error || 'Stripe error');

    } catch (err) {
      console.error('Fetch error:', err);
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