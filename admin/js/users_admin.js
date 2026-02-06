document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadWebUsers();
});

async function loadWebUsers() {
    const res = await apiFetch('/api/admin/users_list.php');
    const tbody = document.getElementById('webUsersBody');
    tbody.innerHTML = '';

    res.data.forEach(u => {
        const tr = document.createElement('tr');
        tr.className = 'web-row';
        tr.innerHTML = `
            <td>
              <button onclick="toggleGameAccounts(${u.id}, this)">▶</button>
              ${u.email}
              <small style="color:#999">#${u.id}</small>
            </td>
            <td>${u.role}</td>
            <td>
              <button onclick="openVip('WEB', ${u.id})">VIP</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function setupTabs() {
    document.querySelectorAll('.tabs button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.add('hidden'));

            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
        });
    });
}
async function toggleGameAccounts(webUserId, btn) {
    const tr = btn.closest('tr');
    document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadWebUsers();
});


    // zavřít pokud už otevřené
    if (tr.nextElementSibling?.classList.contains('child-row')) {
        tr.nextElementSibling.remove();
        btn.textContent = '▶';
        return;
    }

    btn.textContent = '▼';

    const res = await apiFetch(
        `/api/admin/game_accounts_list.php?webUserId=${webUserId}`
    );

    const child = document.createElement('tr');
    child.className = 'child-row';
    child.innerHTML = `
        <td colspan="3">
            <table class="nested">
                ${res.data.map(g => `
                    <tr>
                        <td>
                          <button onclick="toggleCharacters(${g.id}, this)">▶</button>
                          ${g.login}
                          <small>#${g.id}</small>
                        </td>
                        <td colspan="2">
                          <button onclick="openVip('GAME', ${g.id})">VIP</button>
                        </td>
                    </tr>
                `).join('')}
            </table>
        </td>
    `;
document.querySelectorAll('.child-row').forEach(r => r.remove());

    tr.after(child);
}

async function toggleCharacters(gameAccountId, btn) {
    const tr = btn.closest('tr');
    document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadWebUsers();
});


    if (tr.nextElementSibling?.classList.contains('child-row')) {
        tr.nextElementSibling.remove();
        btn.textContent = '▶';
        return;
    }

    btn.textContent = '▼';

    const res = await apiFetch(
        `/api/admin/characters_list.php?gameAccountId=${gameAccountId}`
    );

    const child = document.createElement('tr');
    child.className = 'child-row';
    child.innerHTML = `
        <td colspan="3">
            <ul>
              ${res.data.map(c => `
                <li>
                  ${c.name}
                  <small>#${c.id}</small>
                  <button onclick="openVip('CHAR', ${c.id})">VIP</button>
                </li>
              `).join('')}
            </ul>
        </td>
    `;

    tr.after(child);
}
function openVip(scope, id) {
    window.location.href =
      `/admin/vip.html?scope=${scope}&targetId=${id}`;
}
