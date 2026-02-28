async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        credentials: 'include', // důležité kvůli session (admin auth)
        headers: {
            'Content-Type': 'application/json'
        },
        ...options
    });

    const data = await res.json();

    if (!res.ok || data.ok === false) {
        throw new Error(data.error || 'API_ERROR');
    }

    return data;
}
