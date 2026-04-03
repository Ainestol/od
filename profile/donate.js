confirmBtn?.addEventListener('click', async () => {
  if (!selectedAmount || isNaN(selectedAmount)) {
    alert('Invalid pack');
    return;
  }

  try {
    confirmBtn.disabled = true;

    const currency = 'eur';

    const res = await fetch('/api/create-checkout.php', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        pack: selectedAmount,
        currency: currency
      })
    });

    console.log('STATUS:', res.status);

    const raw = await res.text();
    console.log('RAW:', raw);

    if (!res.ok) {
      alert('SERVER ERROR:\n' + raw);
      return;
    }

    let data;
    try {
      data = JSON.parse(raw);
    } catch (e) {
      alert('JSON ERROR:\n' + raw);
      return;
    }

    console.log('PARSED:', data);

    if (data.url) {
      window.location.href = data.url;
      return;
    }

    alert('Stripe error:\n' + raw);

  } catch (err) {
    console.error('FETCH FAIL:', err);
    alert('FETCH ERROR:\n' + err.message);
  } finally {
    confirmBtn.disabled = false;
  }
});