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
            <div id="widget-disabled-alert" class="hidden p-4 mb-6 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-sm">
                <strong>Widget-urile nu sunt activate.</strong> Contactează administratorul marketplace-ului pentru a activa funcționalitatea de embed.
            </div>

            <!-- Domains config -->
            <div id="domains-section" class="hidden mb-6 p-6 bg-white border rounded-2xl border-border">
                <h2 class="mb-2 text-lg font-bold text-secondary">Domenii permise</h2>
                <p class="mb-4 text-sm text-muted">Adaugă domeniile site-urilor unde vei folosi widget-urile. Widget-ul Full (iframe) va funcționa doar pe aceste domenii.</p>
                <div id="domains-list" class="space-y-2 mb-4"></div>
                <div class="flex gap-2">
                    <input type="text" id="new-domain-input" class="flex-1 input" placeholder="https://site-meu.ro">
                    <button onclick="WidgetsPage.addDomain()" class="btn btn-primary bg-primary px-4">Adaugă</button>
                </div>
                <p id="domains-save-hint" class="hidden mt-2 text-xs text-green-600">Domeniile se salvează automat.</p>
            </div>

            <!-- Widget tabs -->
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
                                    <label class="label">Return URL (după plată)</label>
                                    <input type="text" id="full-return-url" class="w-full input" placeholder="https://site-meu.ro/multumesc" onchange="WidgetsPage.updateCode('full')">
                                    <p class="mt-1 text-xs text-muted">Lasă gol pentru a rămâne în iframe după plată.</p>
                                </div>
                            </div>
                            <div class="mt-6">
                                <label class="label">Cod de embed</label>
                                <textarea id="full-code" class="w-full input font-mono text-xs" rows="6" readonly onclick="this.select()"></textarea>
                                <button onclick="WidgetsPage.copyCode('full')" class="mt-2 btn btn-primary bg-primary px-4 py-2 text-sm">
                                    Copiază codul
                                </button>
                            </div>
                        </div>
                        <div class="p-6 bg-white border rounded-2xl border-border">
                            <h3 class="mb-4 text-base font-bold text-secondary">Preview</h3>
                            <div id="full-preview" class="border rounded-xl border-border overflow-hidden" style="min-height:400px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Widget Eveniment (single) -->
                <div id="tab-single" class="widget-tab-content hidden">
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
                            </div>
                            <div class="mt-6">
                                <label class="label">Cod de embed</label>
                                <textarea id="single-code" class="w-full input font-mono text-xs" rows="5" readonly onclick="this.select()"></textarea>
                                <button onclick="WidgetsPage.copyCode('single')" class="mt-2 btn btn-primary bg-primary px-4 py-2 text-sm">
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
                <div id="tab-list" class="widget-tab-content hidden">
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
                            </div>
                            <div class="mt-6">
                                <label class="label">Cod de embed</label>
                                <textarea id="list-code" class="w-full input font-mono text-xs" rows="5" readonly onclick="this.select()"></textarea>
                                <button onclick="WidgetsPage.copyCode('list')" class="mt-2 btn btn-primary bg-primary px-4 py-2 text-sm">
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
require_once dirname(__DIR__) . '/includes/organizer-footer.php';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
<script>
const WidgetsPage = {
    organizer: null,
    siteUrl: '<?= SITE_URL ?>',

    async init() {
        // Load organizer data
        try {
            const resp = await AmbiletAPI.get('/organizer/me');
            this.organizer = resp.data || resp;
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
        document.getElementById('widget-tabs-section').classList.remove('hidden');

        // Render domains
        this.domains = [...embedDomains];
        this.renderDomains();

        // Populate event selector
        await this.loadEvents();

        // Generate initial codes
        this.updateCode('full');
        this.updateCode('single');
        this.updateCode('list');
    },

    renderDomains() {
        const $list = document.getElementById('domains-list');
        if (this.domains.length === 0) {
            $list.innerHTML = '<p class="text-sm text-muted italic">Niciun domeniu adăugat. Widget-ul Full nu va funcționa fără domenii configurate.</p>';
            return;
        }
        $list.innerHTML = this.domains.map((d, i) =>
            '<div class="flex items-center justify-between px-3 py-2 bg-slate-50 rounded-lg">' +
            '<span class="text-sm font-mono">' + this.esc(d) + '</span>' +
            '<button onclick="WidgetsPage.removeDomain(' + i + ')" class="text-red-500 text-sm hover:underline">Elimină</button>' +
            '</div>'
        ).join('');
    },

    async addDomain() {
        const input = document.getElementById('new-domain-input');
        const val = input.value.trim();
        if (!val) return;
        // Basic URL validation
        if (!val.startsWith('http://') && !val.startsWith('https://')) {
            alert('Domeniul trebuie să înceapă cu http:// sau https://');
            return;
        }
        this.domains.push(val);
        input.value = '';
        this.renderDomains();
        await this.saveDomains();
    },

    async removeDomain(index) {
        this.domains.splice(index, 1);
        this.renderDomains();
        await this.saveDomains();
    },

    async saveDomains() {
        try {
            await AmbiletAPI.put('/organizer/settings', {
                settings: { embed_domains: this.domains }
            });
            const hint = document.getElementById('domains-save-hint');
            hint.classList.remove('hidden');
            setTimeout(() => hint.classList.add('hidden'), 2000);
        } catch (e) {
            console.error('Failed to save domains:', e);
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
            const code = '<div id="tixello-widget"></div>\n<script src="' + this.siteUrl + '/embed/tixello-embed.js"\n  data-organizer="' + slug + '"' +
                (returnUrl ? '\n  data-return-url="' + this.esc(returnUrl) + '"' : '') +
                '\n  data-theme="' + theme + '"' +
                (accent !== '#6366f1' ? '\n  data-accent-color="' + accent + '"' : '') +
                '>\n<\/script>';
            document.getElementById('full-code').value = code;

            // Preview
            const $preview = document.getElementById('full-preview');
            $preview.innerHTML = '<iframe src="' + this.siteUrl + '/embed/' + slug + '?theme=' + theme + '&accent=' + encodeURIComponent(accent) + '" style="width:100%;min-height:400px;border:none;"></iframe>';
        }

        if (type === 'single') {
            const eventSlug = document.getElementById('single-event').value;
            const theme = document.getElementById('single-theme').value;
            if (!eventSlug) {
                document.getElementById('single-code').value = '<!-- Selectează un eveniment -->';
                document.getElementById('single-preview').innerHTML = '<p class="text-muted text-sm">Selectează un eveniment din dropdown.</p>';
                return;
            }
            const code = '<div id="tixello-event"></div>\n<script src="' + this.siteUrl + '/embed/tixello-widget.js"\n  data-type="single"\n  data-event="' + eventSlug + '"\n  data-organizer="' + slug + '"\n  data-theme="' + theme + '">\n<\/script>';
            document.getElementById('single-code').value = code;

            // Preview: load widget script dynamically
            const $preview = document.getElementById('single-preview');
            $preview.innerHTML = '<div id="txw-preview-single"></div>';
            this.loadWidgetPreview('txw-preview-single', 'single', eventSlug, slug, theme);
        }

        if (type === 'list') {
            const limit = document.getElementById('list-limit').value;
            const theme = document.getElementById('list-theme').value;
            const code = '<div id="tixello-events"></div>\n<script src="' + this.siteUrl + '/embed/tixello-widget.js"\n  data-type="list"\n  data-organizer="' + slug + '"\n  data-limit="' + limit + '"\n  data-theme="' + theme + '">\n<\/script>';
            document.getElementById('list-code').value = code;

            const $preview = document.getElementById('list-preview');
            $preview.innerHTML = '<div id="txw-preview-list"></div>';
            this.loadWidgetPreview('txw-preview-list', 'list', '', slug, theme, limit);
        }
    },

    loadWidgetPreview(containerId, type, eventSlug, orgSlug, theme, limit) {
        // Create a temporary script tag to simulate the widget
        const existing = document.getElementById('txw-preview-script');
        if (existing) existing.remove();

        const s = document.createElement('script');
        s.id = 'txw-preview-script';
        s.src = this.siteUrl + '/embed/tixello-widget.js';
        s.setAttribute('data-type', type);
        s.setAttribute('data-container', containerId);
        s.setAttribute('data-theme', theme);
        if (eventSlug) s.setAttribute('data-event', eventSlug);
        if (orgSlug) s.setAttribute('data-organizer', orgSlug);
        if (limit) s.setAttribute('data-limit', limit);
        document.body.appendChild(s);
    },

    copyCode(type) {
        const textarea = document.getElementById(type + '-code');
        textarea.select();
        document.execCommand('copy');
        // Show feedback
        const btn = textarea.nextElementSibling;
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
