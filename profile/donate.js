document.addEventListener('DOMContentLoaded', () => {

  console.log('DONATE JS LOADED');

  const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';

  const modal = document.getElementById('donateConfirmModal');
  const confirmBtn = document.getElementById('donateConfirmOk');
  const cancelBtn = document.getElementById('donateConfirmCancel');
  const textEl = document.getElementById('donateConfirmText');

  let selectedAmount = null;

  // 👉 originální fetch (obejde profile.js override)
  const nativeFetch = window.fetch.bind(window);

  // 👉 CLICK NA BUY
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.buy-dc');
    if (!btn) return;

    e.preventDefault();
    e.stopImmediatePropagation();

    console.log('CLICK HANDLER RUNNING');

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

    console.log('🔥 CONFIRM CLICK');

    if (!selectedAmount) {
      alert('No pack selected');
      return;
    }

    try {
      confirmBtn.disabled = true;

      const res = await nativeFetch('/api/create-checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pack: selectedAmount,
          currency: 'eur'
        })
      });

      const data = await res.json();

      console.log('Stripe response:', data);

      if (data && data.url) {
        window.location.href = data.url;
        return;
      }

      console.error('Stripe error:', data);
      alert(data?.error || 'Stripe error');

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