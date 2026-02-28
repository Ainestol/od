document.addEventListener('DOMContentLoaded', () => {
    loadWebUsers();
});

async function loadWebUsers() {
    const res = await apiFetch('/api/admin/users_list.php');
    const container = document.getElementById('webUsersTree');
    container.innerHTML = '';

    res.data.forEach(user => {
        const webBox = document.createElement('div');
        webBox.className = 'tree-web';

        webBox.innerHTML = `
            <div class="tree-header">
                <button class="toggle-btn">▶</button>
                <span class="tree-title">${user.email}</span>
                <span class="tree-id">#${user.id}</span>
                <span class="tree-role">${user.role}</span>
                <button class="btn btn-small"
                    onclick="openVip('WEB', ${user.id})">VIP</button>
            </div>
            <div class="tree-children hidden"></div>
        `;

        const toggleBtn = webBox.querySelector('.toggle-btn');
        const childrenContainer = webBox.querySelector('.tree-children');

        toggleBtn.addEventListener('click', () =>
            toggleGameAccounts(user.id, toggleBtn, childrenContainer)
        );

        container.appendChild(webBox);
    });
}

async function toggleGameAccounts(webUserId, btn, container) {
    if (!container.classList.contains('hidden')) {
        container.classList.add('hidden');
        container.innerHTML = '';
        btn.textContent = '▶';
        return;
    }

    btn.textContent = '▼';
    container.classList.remove('hidden');

    const res = await apiFetch(
        `/api/admin/game_accounts_list.php?webUserId=${webUserId}`
    );

    container.innerHTML = '';

    res.data.forEach(game => {
        const gameBox = document.createElement('div');
        gameBox.className = 'tree-game';

        gameBox.innerHTML = `
            <div class="tree-header">
                <button class="toggle-btn">▶</button>
                <span class="tree-title">${game.login}</span>
                <span class="tree-id">#${game.id}</span>
                <button class="btn btn-small"
                    onclick="openVip('GAME', ${game.id})">VIP</button>
            </div>
            <div class="tree-children hidden"></div>
        `;

        const toggleBtn = gameBox.querySelector('.toggle-btn');
        const childrenContainer = gameBox.querySelector('.tree-children');

        toggleBtn.addEventListener('click', () =>
            toggleCharacters(game.id, toggleBtn, childrenContainer)
        );

        container.appendChild(gameBox);
    });
}

async function toggleCharacters(gameAccountId, btn, container) {
    if (!container.classList.contains('hidden')) {
        container.classList.add('hidden');
        container.innerHTML = '';
        btn.textContent = '▶';
        return;
    }

    btn.textContent = '▼';
    container.classList.remove('hidden');

    const res = await apiFetch(
        `/api/admin/characters_list.php?gameAccountId=${gameAccountId}`
    );

    container.innerHTML = '';

    res.data.forEach(char => {
        const charBox = document.createElement('div');
        charBox.className = 'tree-char';

        charBox.innerHTML = `
            <span class="tree-title">${char.char_name}</span>
            <span class="tree-id">#${char.charId}</span>
            <button class="btn btn-small"
                onclick="openVip('CHAR', ${char.charId})">VIP</button>
        `;

        container.appendChild(charBox);
    });
}

function openVip(scope, id) {
    window.location.href =
        `/admin/vip.html?scope=${scope}&targetId=${id}`;
}
