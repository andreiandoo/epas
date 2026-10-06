/**
 * WL App — API client for whitelabel site.
 */
const WLApi = {
    base: (typeof WL_BASE !== 'undefined' ? WL_BASE : ''),

    async get(endpoint, params) {
        let url = this.base + '/api/proxy.php?endpoint=' + encodeURIComponent(endpoint);
        if (params) {
            for (const [k, v] of Object.entries(params)) {
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(v);
            }
        }
        const resp = await fetch(url, { credentials: 'same-origin' });
        return resp.json();
    },

    async post(endpoint, data) {
        const resp = await fetch(this.base + '/api/proxy.php?endpoint=' + encodeURIComponent(endpoint), {
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
