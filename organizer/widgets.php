<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Widget-uri Embed';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'widgets';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-secondary">Widget-uri Embed</h1>
                <p class="text-sm text-muted">Generează coduri de embed pentru a vinde bilete direct de pe site-ul tău.</p>
            </div>

            <!-- Status check: widget_enabled -->
            <div id="widget-disabled-alert" class="hidden p-4 mb-6 text-sm border bg-amber-50 border-amber-200 rounded-xl text-amber-700">
                <strong>Widget-urile nu sunt activate.</strong> Contactează administratorul marketplace-ului pentru a activa funcționalitatea de embed.
            </div>

            <!-- Domain config — single domain input -->
            <div id="domains-section" class="hidden p-6 mb-6 bg-white border rounded-2xl border-border">
                <h2 class="mb-2 text-lg font-bold text-secondary">Domeniu site</h2>
                <p class="mb-4 text-sm text-muted">Introdu domeniul site-ului unde vei folosi widget-urile. Dacă folosești subdomenii, adaugă cu wildcard: <code class="px-1 py-0.5 bg-slate-100 rounded text-xs">*.numedomeniu.ro</code></p>
                <div class="flex gap-2">
                    <input type="text" id="domain-input" class="flex-1 input" placeholder="ex: https://site-meu.ro sau *.site-meu.ro">
                    <button onclick="WidgetsPage.saveDomain()" class="px-6 btn btn-primary bg-primary">Salvează</button>
                </div>
                <p id="domain-current" class="hidden px-3 py-2 mt-3 text-sm text-green-700 rounded-lg bg-green-50"></p>
                <p id="domains-save-hint" class="hidden mt-2 text-xs text-green-600">Domeniul a fost salvat.</p>
            </div>

            <!-- Widget tabs -->
            <!-- Whitelabel package download -->
            <div id="whitelabel-section" class="hidden p-6 mb-6 bg-white border rounded-2xl border-border">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-xl bg-primary/10">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-bold text-secondary">Pachet Whitelabel (recomandat)</h2>
                        <p class="mt-1 text-sm text-muted">Descarcă un pachet complet de fișiere PHP pe care le urci pe serverul tău. Site propriu de bilete cu URL-uri native, SEO, fără iframe, fără restricții cross-origin. Necesită server cu Apache + PHP + cURL.</p>
                        <button onclick="WidgetsPage.downloadPackage()" class="inline-flex items-center gap-2 px-6 py-3 mt-4 text-sm font-semibold text-white rounded-xl bg-primary hover:bg-primary-dark transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarcă pachet whitelabel (.zip)
                        </button>
                    </div>
                </div>
            </div>

            <div id="widget-tabs-section" class="hidden">
                <div class="flex flex-wrap gap-2 pb-4 mb-6 border-b border-border">
                    <button onclick="WidgetsPage.showTab('full')" class="px-4 py-2 text-sm font-medium text-white rounded-lg widget-tab active bg-primary" data-tab="full">Widget Full (iframe)</button>
                    <button onclick="WidgetsPage.showTab('single')" class="px-4 py-2 text-sm font-medium rounded-lg widget-tab text-muted hover:bg-surface" data-tab="single">Widget Eveniment</button>
                    <button onclick="WidgetsPage.showTab('list')" class="px-4 py-2 text-sm font-medium rounded-lg widget-tab text-muted hover:bg-surface" data-tab="list">Widget Listă</button>
                </div>

                <!-- Tab: Widget Full -->
                <div id="tab-full" class="widget-tab-content">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="p-6 bg-white border rounded-2xl border-border">
                            <h3 class="mb-4 text-base font-bold text-secondary">Configurare</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="label">Temă</label>
                                    <select id="full-theme" class="w-full input" onchange="WidgetsPage.updateCode('full')">
                                        <option value="light">Light</option>
                                        <option value="dark">Dark</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Culoare accent</label>
                                    <input type="color" id="full-accent" value="#6366f1" class="w-full h-10 input" onchange="WidgetsPage.updateCode('full')">
                                </div>
                                <div>
                                    <label class="label">Logo organizator</label>
                                    <input type="text" id="full-logo" class="w-full input" placeholder="https://site-meu.ro/logo.png" onchange="WidgetsPage.updateCode('full')">
                                    <p class="mt-1 text-xs text-muted">URL-ul logo-ului tău. Va apărea centrat sus pe toate paginile embed.</p>
                                </div>
                                <div>
                                    <label class="label">Imagine de fundal</label>
                                    <input type="text" id="full-bg-image" class="w-full input" placeholder="https://site-meu.ro/background.jpg" onchange="WidgetsPage.updateCode('full')">
                                    <p class="mt-1 text-xs text-muted">Imagine de fundal opțională pentru paginile embed.</p>
                                </div>
                                <div>
                                    <label class="label">Return URL (după plată)</label>
                                    <input type="text" id="full-return-url" class="w-full input" onchange="WidgetsPage.updateCode('full')">
                                    <p class="mt-1 text-xs text-muted">Pagina unde revine clientul după plată. Lasă gol pentru a rămâne în iframe.</p>
                                </div>
                                <button onclick="WidgetsPage.saveWidgetConfig()" class="w-full px-4 py-2 mt-2 text-sm font-medium text-white rounded-lg bg-green-600 hover:bg-green-700 transition">
                                    Salvează setări widget
                                </button>
                                <p id="widget-config-save-hint" class="hidden mt-1 text-xs text-green-600">Setările au fost salvate.</p>
                            </div>
                            <div class="mt-6">
                                <label class="label">Fișier HTML de urcat pe server</label>
                                <p class="mb-2 text-xs text-muted">Descarcă fișierul HTML gata de urcat pe serverul tău. Conține codul embed complet.</p>
                                <button onclick="WidgetsPage.downloadFile('full')" class="w-full px-4 py-2 text-sm btn btn-primary bg-primary">
                                    Descarcă fișier HTML
                                </button>
                            </div>
                            <div class="mt-4">
                                <label class="label">Sau copiază codul de embed</label>
                                <textarea id="full-code" class="w-full font-mono text-xs input" rows="8" readonly onclick="this.select()"></textarea>
                                <button onclick="WidgetsPage.copyCode('full')" class="px-4 py-2 mt-2 text-sm btn bg-slate-200 text-secondary">
                                    Copiază codul
                                </button>
                            </div>
                        </div>
                        <div class="p-6 bg-white border rounded-2xl border-border">
                            <h3 class="mb-4 text-base font-bold text-secondary">Preview</h3>
                            <div id="full-preview" class="overflow-hidden border rounded-xl border-border" style="min-height:400px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Widget Eveniment (single) -->
                <div id="tab-single" class="hidden widget-tab-content">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="p-6 bg-white border rounded-2xl border-border">
                            <h3 class="mb-4 text-base font-bold text-secondary">Configurare</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="label">Eveniment</label>
                                    <select id="single-event" class="w-full input" onchange="WidgetsPage.updateCode('single')">
                                        <option value="">Selectează un eveniment...</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Temă</label>
                                    <select id="single-theme" class="w-full input" onchange="WidgetsPage.updateCode('single')">
                                        <option value="light">Light</option>
                                        <option value="dark">Dark</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Stil card</label>
                                    <select id="single-style" class="w-full input" onchange="WidgetsPage.updateCode('single')">
                                        <option value="card">Card vertical</option>
                                        <option value="horizontal">Card horizontal</option>
                                        <option value="compact">Compact (doar buton)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-6">
                                <label class="label">Cod de embed</label>
                                <textarea id="single-code" class="w-full font-mono text-xs input" rows="5" readonly onclick="this.select()"></textarea>
                                <button onclick="WidgetsPage.copyCode('single')" class="px-4 py-2 mt-2 text-sm btn bg-slate-200 text-secondary">
                                    Copiază codul
                                </button>
                            </div>
                        </div>
                        <div class="p-6 bg-white border rounded-2xl border-border">
                            <h3 class="mb-4 text-base font-bold text-secondary">Preview</h3>
                            <div id="single-preview" style="min-height:200px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Widget Listă -->
                <div id="tab-list" class="hidden widget-tab-content">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="p-6 bg-white border rounded-2xl border-border">
                            <h3 class="mb-4 text-base font-bold text-secondary">Configurare</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="label">Nr. maxim evenimente</label>
                                    <input type="number" id="list-limit" class="w-full input" value="6" min="1" max="20" onchange="WidgetsPage.updateCode('list')">
                                </div>
                                <div>
                                    <label class="label">Temă</label>
                                    <select id="list-theme" class="w-full input" onchange="WidgetsPage.updateCode('list')">
                                        <option value="light">Light</option>
                                        <option value="dark">Dark</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Layout</label>
                                    <select id="list-layout" class="w-full input" onchange="WidgetsPage.updateCode('list')">
                                        <option value="grid">Grid (carduri)</option>
                                        <option value="list">Listă (orizontal)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-6">
                                <label class="label">Cod de embed</label>
                                <textarea id="list-code" class="w-full font-mono text-xs input" rows="5" readonly onclick="this.select()"></textarea>
                                <button onclick="WidgetsPage.copyCode('list')" class="px-4 py-2 mt-2 text-sm btn bg-slate-200 text-secondary">
                                    Copiază codul
                                </button>
                            </div>
                        </div>
                        <div class="p-6 bg-white border rounded-2xl border-border">
                            <h3 class="mb-4 text-base font-bold text-secondary">Preview</h3>
                            <div id="list-preview" style="min-height:200px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
<script>
const WidgetsPage = {
    organizer: null,
    domain: '',
    siteUrl: '<?= SITE_URL ?>',
    siteName: '<?= SITE_NAME ?>',

    async init() {
        try {
            const resp = await AmbiletAPI.get('/organizer/me');
            this.organizer = resp.data?.organizer || resp.organizer || resp.data || resp;
        } catch (e) {
            console.error('Failed to load organizer:', e);
            return;
        }

        const widgetEnabled = this.organizer.settings?.widget_enabled || false;
        const embedDomains = this.organizer.settings?.embed_domains || [];

        if (!widgetEnabled) {
            document.getElementById('widget-disabled-alert').classList.remove('hidden');
            return;
        }

        document.getElementById('domains-section').classList.remove('hidden');
        document.getElementById('whitelabel-section').classList.remove('hidden');
        document.getElementById('widget-tabs-section').classList.remove('hidden');

        // Show current domain
        this.domain = embedDomains[0] || '';
        if (this.domain) {
            document.getElementById('domain-input').value = this.domain;
            const $current = document.getElementById('domain-current');
            $current.textContent = 'Domeniu configurat: ' + this.domain;
            $current.classList.remove('hidden');
        }

        // Pre-fill return URL with domain + /multumim
        if (this.domain) {
            const baseHost = this.domain.replace(/^\*\./, 'www.');
            const returnBase = this.domain.startsWith('http') ? this.domain : 'https://' + baseHost;
            document.getElementById('full-return-url').value = returnBase + '/multumim';
        }

        // Pre-fill from saved widget config
        const wc = this.organizer.settings?.widget_config || {};
        if (wc.logo) document.getElementById('full-logo').value = wc.logo;
        else if (this.organizer.logo) document.getElementById('full-logo').value = this.organizer.logo;
        if (wc.bg_image) document.getElementById('full-bg-image').value = wc.bg_image;
        if (wc.theme) document.getElementById('full-theme').value = wc.theme;
        if (wc.accent) document.getElementById('full-accent').value = wc.accent;
        if (wc.return_url) document.getElementById('full-return-url').value = wc.return_url;

        await this.loadEvents();

        this.updateCode('full');
        this.updateCode('single');
        this.updateCode('list');
    },

    async saveDomain() {
        const val = document.getElementById('domain-input').value.trim();
        if (!val) return;
        this.domain = val;

        try {
            await AmbiletAPI.put('/organizer/widget-settings', {
                settings: { embed_domains: [val] }
            });
            const $current = document.getElementById('domain-current');
            $current.textContent = 'Domeniu configurat: ' + val;
            $current.classList.remove('hidden');
            const hint = document.getElementById('domains-save-hint');
            hint.classList.remove('hidden');
            setTimeout(() => hint.classList.add('hidden'), 2000);

            // Update return URL
            const baseHost = val.replace(/^\*\./, 'www.');
            const returnBase = val.startsWith('http') ? val : 'https://' + baseHost;
            document.getElementById('full-return-url').value = returnBase + '/multumim';

            this.updateCode('full');
        } catch (e) {
            console.error('Failed to save domain:', e);
            alert('Eroare la salvare. Încearcă din nou.');
        }
    },

    async saveWidgetConfig() {
        const config = {
            logo: document.getElementById('full-logo').value.trim(),
            bg_image: document.getElementById('full-bg-image').value.trim(),
            theme: document.getElementById('full-theme').value,
            accent: document.getElementById('full-accent').value,
            return_url: document.getElementById('full-return-url').value.trim(),
        };
        try {
            await AmbiletAPI.put('/organizer/widget-settings', {
                settings: { widget_config: config }
            });
            const hint = document.getElementById('widget-config-save-hint');
            hint.classList.remove('hidden');
            setTimeout(() => hint.classList.add('hidden'), 3000);
        } catch (e) {
            console.error('Failed to save widget config:', e);
            alert('Eroare la salvare. Încearcă din nou.');
        }
    },

    async loadEvents() {
        try {
            const resp = await AmbiletAPI.get('/organizer/events', { status: 'published', limit: 50 });
            const events = resp.data || [];
            const $select = document.getElementById('single-event');
            events.forEach(ev => {
                const opt = document.createElement('option');
                opt.value = ev.slug;
                opt.textContent = ev.title || ev.name || 'Eveniment';
                $select.appendChild(opt);
            });
        } catch (e) {
            console.error('Failed to load events:', e);
        }
    },

    showTab(tab) {
        document.querySelectorAll('.widget-tab').forEach(t => {
            t.classList.remove('active', 'bg-primary', 'text-white');
            t.classList.add('text-muted', 'hover:bg-surface');
        });
        document.querySelectorAll('.widget-tab-content').forEach(c => c.classList.add('hidden'));
        const btn = document.querySelector('.widget-tab[data-tab="' + tab + '"]');
        btn.classList.add('active', 'bg-primary', 'text-white');
        btn.classList.remove('text-muted', 'hover:bg-surface');
        document.getElementById('tab-' + tab).classList.remove('hidden');
    },

    updateCode(type) {
        const slug = this.organizer?.slug || '';

        if (type === 'full') {
            const theme = document.getElementById('full-theme').value;
            const accent = document.getElementById('full-accent').value;
            const returnUrl = document.getElementById('full-return-url').value;
            const logo = document.getElementById('full-logo').value;
            const bgImage = document.getElementById('full-bg-image').value;

            let attrs = '\n  data-organizer="' + slug + '"';
            if (returnUrl) attrs += '\n  data-return-url="' + this.esc(returnUrl) + '"';
            attrs += '\n  data-theme="' + theme + '"';
            if (accent && accent !== '#6366f1') attrs += '\n  data-accent-color="' + accent + '"';
            if (logo) attrs += '\n  data-logo="' + this.esc(logo) + '"';
            if (bgImage) attrs += '\n  data-bg-image="' + this.esc(bgImage) + '"';

            const code = '<div id="tixello-widget"></div>\n<script src="' + this.siteUrl + '/embed/tixello-embed.js"' + attrs + '>\n<\/script>';
            document.getElementById('full-code').value = code;

            // Preview — iframe
            const params = new URLSearchParams({ theme, accent, logo, bg_image: bgImage });
            const $preview = document.getElementById('full-preview');
            $preview.innerHTML = '<iframe src="' + this.siteUrl + '/embed/' + slug + '?' + params.toString() + '" style="width:100%;min-height:400px;border:none;" allow="payment"></iframe>';
        }

        if (type === 'single') {
            const eventSlug = document.getElementById('single-event').value;
            const theme = document.getElementById('single-theme').value;
            const style = document.getElementById('single-style').value;
            if (!eventSlug) {
                document.getElementById('single-code').value = '<!-- Selectează un eveniment -->';
                document.getElementById('single-preview').innerHTML = '<p class="text-sm text-muted">Selectează un eveniment din dropdown.</p>';
                return;
            }
            let attrs = '\n  data-type="single"\n  data-event="' + eventSlug + '"\n  data-organizer="' + slug + '"\n  data-theme="' + theme + '"';
            if (style !== 'card') attrs += '\n  data-style="' + style + '"';
            const code = '<div id="tixello-event"></div>\n<script src="' + this.siteUrl + '/embed/tixello-widget.js"' + attrs + '>\n<\/script>';
            document.getElementById('single-code').value = code;

            const $preview = document.getElementById('single-preview');
            $preview.innerHTML = '<div id="txw-preview-single"></div>';
            this.loadWidgetPreview('txw-preview-single', 'single', eventSlug, slug, theme);
        }

        if (type === 'list') {
            const limit = document.getElementById('list-limit').value;
            const theme = document.getElementById('list-theme').value;
            const layout = document.getElementById('list-layout').value;
            let attrs = '\n  data-type="list"\n  data-organizer="' + slug + '"\n  data-limit="' + limit + '"\n  data-theme="' + theme + '"';
            if (layout !== 'grid') attrs += '\n  data-layout="' + layout + '"';
            const code = '<div id="tixello-events"></div>\n<script src="' + this.siteUrl + '/embed/tixello-widget.js"' + attrs + '>\n<\/script>';
            document.getElementById('list-code').value = code;

            const $preview = document.getElementById('list-preview');
            $preview.innerHTML = '<div id="txw-preview-list"></div>';
            this.loadWidgetPreview('txw-preview-list', 'list', '', slug, theme, limit);
        }
    },

    loadWidgetPreview(containerId, type, eventSlug, orgSlug, theme, limit) {
        // Remove old script + styles to force fresh re-execution
        const existing = document.getElementById('txw-preview-script');
        if (existing) existing.remove();
        const oldStyles = document.getElementById('txw-styles');
        if (oldStyles) oldStyles.remove();

        const s = document.createElement('script');
        s.id = 'txw-preview-script';
        s.src = this.siteUrl + '/embed/tixello-widget.js?_t=' + Date.now();
        s.setAttribute('data-type', type);
        s.setAttribute('data-container', containerId);
        s.setAttribute('data-theme', theme);
        if (eventSlug) s.setAttribute('data-event', eventSlug);
        if (orgSlug) s.setAttribute('data-organizer', orgSlug);
        if (limit) s.setAttribute('data-limit', limit);
        document.body.appendChild(s);
    },

    downloadPackage() {
        const slug = this.organizer?.slug || '';
        window.location.href = this.siteUrl + '/embed/generate-package.php?organizer=' + encodeURIComponent(slug);
    },

    downloadFile(type) {
        if (type !== 'full') return;
        const code = document.getElementById('full-code').value;
        const orgName = this.organizer?.name || 'Organizator';
        const logo = document.getElementById('full-logo').value;

        const html = '<!DOCTYPE html>\n<html lang="ro">\n<head>\n  <meta charset="UTF-8">\n  <meta name="viewport" content="width=device-width, initial-scale=1.0">\n  <title>Bilete - ' + this.esc(orgName) + '</title>\n  <style>\n    body { margin: 0; padding: 0; font-family: system-ui, sans-serif; }\n    .widget-container { max-width: 1200px; margin: 0 auto; padding: 20px; }\n' +
            (logo ? '    .org-logo { display: block; max-height: 60px; margin: 20px auto; }\n' : '') +
            '  </style>\n</head>\n<body>\n  <div class="widget-container">\n' +
            (logo ? '    <img src="' + this.esc(logo) + '" alt="' + this.esc(orgName) + '" class="org-logo">\n' : '') +
            '    ' + code + '\n  </div>\n</body>\n</html>';

        const blob = new Blob([html], { type: 'text/html' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'index.html';
        a.click();
        URL.revokeObjectURL(a.href);
    },

    copyCode(type) {
        const textarea = document.getElementById(type + '-code');
        textarea.select();
        navigator.clipboard?.writeText(textarea.value).catch(() => document.execCommand('copy'));
        const btn = textarea.parentElement.querySelector('button');
        const orig = btn.textContent;
        btn.textContent = 'Copiat!';
        setTimeout(() => btn.textContent = orig, 1500);
    },

    esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => WidgetsPage.init());
</script>
