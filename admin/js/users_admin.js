document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadUsers();
    loadGameAccounts();
    loadCharacters();
});

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
