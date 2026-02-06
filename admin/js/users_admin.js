document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadUsers();
    loadGameAccounts();
    loadCharacters();
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
async function loadUsers() {
    const res = await apiFetch('/api/admin/users_list.php');
    const tbody = document.getElementById('usersTable');
    tbody.innerHTML = '';

    res.data.forEach(u => {
        tbody.innerHTML += `
          <tr>
            <td>${u.email}</td>
            <td>${u.role}</td>
            <td class="id">${u.id}</td>
            <td>
              <button onclick="vipFromUser(${u.id})">VIP</button>
            </td>
          </tr>
        `;
    });
}
async function loadGameAccounts() {
    const res = await apiFetch('/api/admin/game_accounts_list.php');
    const tbody = document.getElementById('gamesTable');
    tbody.innerHTML = '';

    res.data.forEach(g => {
        tbody.innerHTML += `
          <tr>
            <td>${g.login}</td>
            <td>${g.email ?? '-'}</td>
            <td class="id">${g.id}</td>
            <td>
              <button onclick="vipFromGame(${g.id})">VIP</button>
            </td>
          </tr>
        `;
    });
}
async function loadCharacters() {
    const res = await apiFetch('/api/admin/characters_list.php');
    const tbody = document.getElementById('charsTable');
    tbody.innerHTML = '';

    res.data.forEach(c => {
        tbody.innerHTML += `
          <tr>
            <td>${c.char_name}</td>
            <td>${c.account_name}</td>
            <td class="id">${c.charId}</td>
            <td>
              <button onclick="vipFromChar(${c.charId})">VIP</button>
            </td>
          </tr>
        `;
    });

async function toggleGameAccounts(webUserId, btn) {
    const tr = btn.closest('tr');

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

    tr.after(child);
}

async function toggleCharacters(gameAccountId, btn) {
    const tr = btn.closest('tr');

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


}
function vipFromUser(id) {
    window.location.href = `/admin/vip.html?scope=WEB&targetId=${id}`;
}

function vipFromGame(id) {
    window.location.href = `/admin/vip.html?scope=GAME&targetId=${id}`;
}

function vipFromChar(id) {
    window.location.href = `/admin/vip.html?scope=CHAR&targetId=${id}`;
}
