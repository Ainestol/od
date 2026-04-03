document.addEventListener('DOMContentLoaded', () => {

  const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';

  const modal = document.getElementById('shopConfirmModal');
  const confirmBtn = document.getElementById('shopConfirmOk');
  const cancelBtn = document.getElementById('shopConfirmCancel');

  let selectedAmount = null;

  // 👉 DONATE tlačítka
  document.querySelectorAll('.buy-dc').forEach(btn => {
    btn.addEventListener('click', () => {

      selectedAmount = btn.dataset.pack;

      const text = isEn
        ? `Do you want to purchase ${selectedAmount} DC?\n\nYou will be redirected to the payment gateway.`
        : `Chceš koupit ${selectedAmount} DC?\n\nPo kliknutí budeš přesměrován na platební bránu.`;

      document.getElementById('shopConfirmText').textContent = text;

      modal.classList.remove('hidden');
    });
  });

  // 👉 CONFIRM (POUZE PRO DONATE)
  confirmBtn?.addEventListener('click', async () => {
    if (!selectedAmount) return;

    try {
      confirmBtn.disabled = true;

      const currency = isEn ? 'eur' : 'czk';

      const res = await fetch('/api/create-checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pack: parseInt(selectedAmount),
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
      } else {
        alert('Stripe error');
      }

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