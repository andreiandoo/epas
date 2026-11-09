/**
 * WL App — API client for whitelabel site.
 */
const WLApi = {
    base: (typeof WL_BASE !== 'undefined' ? WL_BASE : ''),

    // Visitor id (and, without a _fbc cookie, an fbc rebuilt from the stored fbclid)
    // for the proxy, so the order and the server-side Purchase carry the same ids as
    // the browser events.
    visitorHeaders() {
        const h = {};
        try {
            const vid = localStorage.getItem('wl_visitor_id');
            if (vid && /^[A-Za-z0-9._:-]{8,64}$/.test(vid)) h['X-Visitor-ID'] = vid;
        } catch (e) {}
        return h;
    },

    async get(endpoint, params) {
        let url = this.base + '/api/proxy.php?endpoint=' + encodeURIComponent(endpoint);
        if (params) {
            for (const [k, v] of Object.entries(params)) {
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(v);
            }
        }
        const resp = await fetch(url, { credentials: 'same-origin', headers: this.visitorHeaders() });
        return resp.json();
    },

    async post(endpoint, data) {
        const resp = await fetch(this.base + '/api/proxy.php?endpoint=' + encodeURIComponent(endpoint), {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, this.visitorHeaders()),
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
