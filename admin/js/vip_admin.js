document.addEventListener('DOMContentLoaded', () => {
    
 const params = new URLSearchParams(window.location.search);
    const scope = params.get('scope');
    const targetId = params.get('targetId');

    if (scope && targetId) {
        const scopeSelect = document.getElementById('vipScope');
        const targetInput = document.getElementById('vipTargetId');

        if (scopeSelect && targetInput) {
            scopeSelect.value = scope;
            targetInput.value = targetId;
        }
    }

document.getElementById('vipAddBtn')
        .addEventListener('click', adminAddVip);

    document.getElementById('vipReloadBtn')
        .addEventListener('click', loadVipList);

        document.getElementById('showExpiredVip')
        ?.addEventListener('change', loadVipList);

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

    const showExpired = document.getElementById('showExpiredVip')?.checked;
    const url = showExpired
        ? '/api/admin/vip_list.php?showExpired=1'
        : '/api/admin/vip_list.php';

    try {
        const res = await apiFetch(url);

        res.data.forEach(v => {
            const expired = new Date(v.end_at) < new Date();

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${v.id}</td>
                <td>${v.scope}</td>
                <td>${v.target_id}</td>
                <td>${v.level_id}</td>
                <td>${v.end_at}</td>
                <td>
                    ${expired ? '<em>expirované</em>' :
                    `<button onclick="removeVip(${v.id})">Odebrat</button>`}
                </td>
            `;

            if (expired) tr.style.opacity = '0.5';

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
