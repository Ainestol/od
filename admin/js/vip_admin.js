document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('vipAddBtn')
        .addEventListener('click', adminAddVip);

    document.getElementById('vipReloadBtn')
        .addEventListener('click', loadVipList);

    loadVipList();
});

async function adminAddVip() {
  try {
    const payload = {
      scope: document.getElementById('vipScope').value,
      targetId: Number(document.getElementById('vipTargetId').value),
      levelId: Number(document.getElementById('vipLevelId').value),
      days: Number(document.getElementById('vipDays').value)
    };

    const res = await apiFetch('/api/admin/vip_add.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    document.getElementById('vipAddResult').innerText =
      'OK – VIP přidáno (ID ' + res.vip_grant_id + ')';

  } catch (e) {
    document.getElementById('vipAddResult').innerText =
      'Chyba: ' + e.message;
  }
}


async function loadVipList() {
    const tbody = document.getElementById('vipTable');
    tbody.innerHTML = '';

    try {
        const res = await apiFetch('/api/admin/vip_list.php');

        res.data.forEach(v => {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>${v.id}</td>
                <td>${v.scope}</td>
                <td>${v.target_id}</td>
                <td>${v.level_id}</td>
                <td>${v.end_at}</td>
                <td>
                    <button onclick="removeVip(${v.id})">Odebrat</button>
                </td>
            `;

            tbody.appendChild(tr);
        });

    } catch (e) {
        alert('Chyba při načítání VIP: ' + e.message);
    }
}

async function removeVip(vipGrantId) {
    if (!confirm('Opravdu odebrat VIP?')) return;

    try {
        await apiFetch('/api/admin/vip_remove.php', {
            method: 'POST',
            body: JSON.stringify({ vipGrantId })
        });

        loadVipList();

    } catch (e) {
        alert('Chyba: ' + e.message);
    }
}
