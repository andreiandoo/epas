/**
 * WL App — common utilities for whitelabel site.
 */
const WLApi = {
    async get(endpoint, params) {
        let url = 'api/proxy.php?endpoint=' + encodeURIComponent(endpoint);
        if (params) {
            for (const [k, v] of Object.entries(params)) {
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(v);
            }
        }
        const resp = await fetch(url, { credentials: 'same-origin' });
        return resp.json();
    },

    async post(endpoint, data) {
        const resp = await fetch('api/proxy.php?endpoint=' + encodeURIComponent(endpoint), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
            credentials: 'same-origin',
        });
        return resp.json();
    }
};

function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
