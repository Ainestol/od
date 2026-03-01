async function apiFetch(url, options = {}) {

    const headers = {
        'Content-Type': 'application/json',
        ...(options.headers || {})
    };

    // Přidáme CSRF token pokud existuje
    if (window.CSRF_TOKEN) {
        headers['X-CSRF-TOKEN'] = window.CSRF_TOKEN;
    }

    const res = await fetch(url, {
        credentials: 'include',
        ...options,
        headers
    });

    const data = await res.json();

    if (!res.ok || data.ok === false) {
        throw new Error(data.error || 'API_ERROR');
    }

    return data;
}
async function initCsrf() {
    const res = await fetch('/api/csrf_token.php', {
        credentials: 'include'
    });

    const data = await res.json();
    window.CSRF_TOKEN = data.token;
}