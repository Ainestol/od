document.addEventListener('DOMContentLoaded', () => {

  const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';

  // ✅ POUŽÍVEJ DONATE MODAL (NE shop!)
  const modal = document.getElementById('donateConfirmModal');
  const confirmBtn = document.getElementById('donateConfirmOk');
  const cancelBtn = document.getElementById('donateConfirmCancel');
  const textEl = document.getElementById('donateConfirmText');

  let selectedAmount = null;

  // 👉 CLICK NA BUY
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.buy-dc');
    if (!btn) return;

    selectedAmount = Number(btn.getAttribute('data-pack'));

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
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pack: selectedAmount,
          currency: currency
        })
      });

      const text = await res.text();
      console.log('RAW RESPONSE:', text);

      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        alert('Invalid JSON:\n' + text);
        return;
      }

 if (data.url) {
  window.location.href = data.url;
  return; // 🔥 důležité – zastaví další kód
}

console.error('Stripe response error:', data);
alert(data.error || 'Stripe error');

    } catch (err) {
      console.error(err);
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