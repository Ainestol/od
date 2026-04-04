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

const bonuses = {
  20: 20, 55: 60, 120: 140, 260: 320, 600: 800
};
const totalDc = bonuses[selectedAmount] ?? selectedAmount;
const isCz = (document.documentElement.lang || '').toLowerCase() !== 'en';

// Načti cenu z karty
const card = btn.closest('.donate-card');
const priceText = card?.querySelector('.price')?.textContent?.trim() ?? '';

if (isCz) {
  textEl.textContent =
    `Vážíme si tvé podpory! Rozhodl ses podpořit server Ordo Draconis dobrovolným příspěvkem ${priceText}. Na oplátku od nás dostaneš ${totalDc} Dragon Coinů jako náš dík.\n\nDragon Coiny nejsou zboží ani služba – jsou to naše poděkování za tvou podporu. Příspěvek je dobrovolný a nenárokový.\n\nPřeješ si pokračovat?`;
} else {
  textEl.textContent =
    `We appreciate your support! You're about to support the Ordo Draconis server with a voluntary contribution of ${priceText}. As a token of our gratitude, you'll receive ${totalDc} Dragon Coins.\n\nDragon Coins are not a product or service – they are simply our way of saying thank you. This contribution is voluntary and non-refundable.\n\nDo you wish to continue?`;
}

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

      const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';

const res = await fetch('/api/create-checkout.php', {
  method: 'POST',
  credentials: 'same-origin', // 🔥 TOTO JE KLÍČ
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    pack: selectedAmount,
    currency: isEn ? 'eur' : 'czk'
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