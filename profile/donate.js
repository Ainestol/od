
document.addEventListener('DOMContentLoaded', () => {

  console.log('DONATE JS LOADED');

  const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';

  const modal = document.getElementById('donateConfirmModal');
  const confirmBtn = document.getElementById('donateConfirmOk');
  const cancelBtn = document.getElementById('donateConfirmCancel');
  const textEl = document.getElementById('donateConfirmText');
console.log('confirmBtn:', confirmBtn);
  let selectedAmount = null;

  // 👉 originální fetch (obejde profile.js override)
  const nativeFetch = window.fetch.bind(window);

  // 👉 CLICK NA BUY
  document.addEventListener('click', async (e) => {
  const btn = e.target.closest('#donateConfirmOk');
  if (!btn) return;

  console.log('🔥 CONFIRM CLICK');

  if (!selectedAmount) {
    alert('No pack selected');
    return;
  }

  try {
    btn.disabled = true;

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

    if (data && data.url) {
      window.location.href = data.url;
      return;
    }

    alert(data?.error || 'Stripe error');

  } catch (err) {
    console.error('❌ ERROR DETAIL:', err);
    alert('Payment error');
  } finally {
    btn.disabled = false;
  }
});

  // 👉 CONFIRM
  document.addEventListener('click', async (e) => {
  const btn = e.target.closest('#donateConfirmOk');
  if (!btn) return;

  console.log('🔥 CONFIRM CLICK');

  if (!selectedAmount) {
    alert('No pack selected');
    return;
  }

  try {
    btn.disabled = true;

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

    if (data && data.url) {
      window.location.href = data.url;
      return;
    }

    alert(data?.error || 'Stripe error');

  } catch (err) {
    console.error('❌ ERROR DETAIL:', err);
    alert('Payment error');
  } finally {
    btn.disabled = false;
  }
});
  // 👉 CANCEL
  cancelBtn?.addEventListener('click', () => {
    modal.classList.add('hidden');
    selectedAmount = null;
  });

});