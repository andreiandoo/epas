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
            <!-- Widget Full — Configurare branding + Download pachet -->
            <div id="whitelabel-section" class="hidden mb-6">
                <div class="p-6 bg-white border rounded-2xl border-border">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-xl bg-primary/10">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Widget Full — Pachet Whitelabel</h2>
                            <p class="text-sm text-muted">Site propriu de bilete. Configurează branding-ul, salvează, apoi descarcă pachetul ZIP.</p>
                        </div>
                    </div>

                    <!-- Inner tabs: Branding | Termeni | Confidențialitate -->
                    <div class="flex gap-1 p-1 mb-6 rounded-lg bg-slate-100">
                        <button onclick="WidgetsPage.showInnerTab('branding')" class="flex-1 px-3 py-2 text-sm font-medium rounded-md wl-inner-tab active bg-white text-secondary shadow-sm" data-itab="branding">Branding & Configurare</button>
                        <button onclick="WidgetsPage.showInnerTab('terms')" class="flex-1 px-3 py-2 text-sm font-medium rounded-md wl-inner-tab text-muted" data-itab="terms">Termeni și Condiții</button>
                        <button onclick="WidgetsPage.showInnerTab('privacy')" class="flex-1 px-3 py-2 text-sm font-medium rounded-md wl-inner-tab text-muted" data-itab="privacy">Politica Confidențialitate</button>
                    </div>

                    <!-- Inner tab: Branding -->
                    <div id="itab-branding" class="wl-inner-content">
                        <div class="grid gap-6 lg:grid-cols-2">
                            <div class="space-y-5">
                                <!-- Color picker -->
                                <div class="p-4 border rounded-xl border-border bg-slate-50/50">
                                    <label class="block mb-2 text-xs font-semibold tracking-wider uppercase text-secondary">Culoare principală</label>
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <input type="color" id="full-accent" value="#D4A843" class="w-12 h-12 p-0 border-0 rounded-lg cursor-pointer" style="appearance:none;-webkit-appearance:none;" oninput="var h=document.getElementById('full-accent-hex');if(h)h.value=this.value;">
                                            <div class="absolute inset-0 border-2 rounded-lg pointer-events-none border-white/50" style="box-shadow:0 1px 3px rgba(0,0,0,0.1);"></div>
                                        </div>
                                        <input type="text" id="full-accent-hex" value="#D4A843" class="w-28 px-3 py-2 font-mono text-sm tracking-wide uppercase border rounded-lg bg-white border-border text-secondary" oninput="var el=document.getElementById('full-accent');if(el)el.value=this.value;">
                                        <span class="text-xs text-muted">Butoane, link-uri, accente</span>
                                    </div>
                                </div>

                                <!-- Logo upload -->
                                <div>
                                    <label class="block mb-2 text-xs font-semibold tracking-wider uppercase text-secondary">Logo organizator</label>
                                    <div class="wl-upload-zone" onclick="this.querySelector('input[type=file]').click()">
                                        <input type="file" accept="image/*" style="display:none" onchange="WidgetsPage.uploadImage(this, 'logo', 'full-logo')">
                                        <input type="hidden" id="full-logo">
                                        <div class="wl-upload-preview" id="preview-logo"></div>
                                        <div class="wl-upload-text">
                                            <div class="flex items-center justify-center w-10 h-10 mx-auto mb-2 rounded-full bg-primary/10">
                                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                            <span class="text-sm font-medium text-secondary">Încarcă logo</span>
                                            <span class="block mt-0.5 text-xs text-muted">PNG, SVG sau JPG &middot; max 5MB</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hero upload -->
                                <div>
                                    <label class="block mb-2 text-xs font-semibold tracking-wider uppercase text-secondary">Imagine hero homepage</label>
                                    <div class="wl-upload-zone wl-upload-wide" onclick="this.querySelector('input[type=file]').click()">
                                        <input type="file" accept="image/*" style="display:none" onchange="WidgetsPage.uploadImage(this, 'hero', 'full-hero-image')">
                                        <input type="hidden" id="full-hero-image">
                                        <div class="wl-upload-preview" id="preview-hero"></div>
                                        <div class="wl-upload-text">
                                            <div class="flex items-center justify-center w-10 h-10 mx-auto mb-2 rounded-full bg-primary/10">
                                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                                            </div>
                                            <span class="text-sm font-medium text-secondary">Imagine hero</span>
                                            <span class="block mt-0.5 text-xs text-muted">Recomandat: 1920×800px</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Background upload -->
                                <div>
                                    <label class="block mb-2 text-xs font-semibold tracking-wider uppercase text-secondary">Imagine de fundal <span class="font-normal normal-case text-muted">(opțional, toate paginile)</span></label>
                                    <div class="wl-upload-zone" onclick="this.querySelector('input[type=file]').click()">
                                        <input type="file" accept="image/*" style="display:none" onchange="WidgetsPage.uploadImage(this, 'background', 'full-bg-image')">
                                        <input type="hidden" id="full-bg-image">
                                        <div class="wl-upload-preview" id="preview-background"></div>
                                        <div class="wl-upload-text">
                                            <div class="flex items-center justify-center w-10 h-10 mx-auto mb-2 rounded-full bg-slate-100">
                                                <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                            <span class="text-sm font-medium text-secondary">Imagine fundal</span>
                                            <span class="block mt-0.5 text-xs text-muted">Se aplică pe toate paginile</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="label">Titlu hero (acceptă HTML)</label>
                                    <input type="text" id="full-home-title" class="w-full input" placeholder='ex: Seara perfectă<br>începe cu <em>râs.</em>'>
                                    <p class="mt-1 text-xs text-muted">Folosește &lt;em&gt; pentru accent, &lt;br&gt; pentru rând nou.</p>
                                </div>
                                <div>
                                    <label class="label">Subtitlu hero</label>
                                    <input type="text" id="full-home-subtitle" class="w-full input" placeholder="ex: Clubul de comedie nr. 1 din România">
                                </div>
                                <div>
                                    <label class="label">Adresă</label>
                                    <input type="text" id="full-address" class="w-full input" placeholder="ex: Str. Lipscani 45, București">
                                </div>
                                <div>
                                    <label class="label">Telefon</label>
                                    <input type="text" id="full-phone" class="w-full input" placeholder="ex: +40 721 234 567">
                                </div>
                                <div>
                                    <label class="label">Return URL (după plată)</label>
                                    <input type="text" id="full-return-url" class="w-full input bg-slate-50" readonly>
                                    <p class="mt-1 text-xs text-muted">Generat automat din domeniul configurat.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inner tab: Terms -->
                    <div id="itab-terms" class="wl-inner-content" style="display:none;">
                        <p class="mb-3 text-sm text-muted">Conținutul paginii „Termeni și Condiții" de pe site-ul whitelabel. Acceptă HTML.</p>
                        <textarea id="wl-terms-editor" class="w-full input" rows="14" style="font-family:monospace;font-size:13px;line-height:1.6;" placeholder="Introdu termenii și condițiile aici..."></textarea>
                    </div>

                    <!-- Inner tab: Privacy -->
                    <div id="itab-privacy" class="wl-inner-content" style="display:none;">
                        <p class="mb-3 text-sm text-muted">Conținutul paginii „Politica de Confidențialitate" de pe site-ul whitelabel. Acceptă HTML.</p>
                        <textarea id="wl-privacy-editor" class="w-full input" rows="14" style="font-family:monospace;font-size:13px;line-height:1.6;" placeholder="Introdu politica de confidențialitate aici..."></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-wrap items-center gap-3 pt-6 mt-6 border-t border-border">
                        <button onclick="WidgetsPage.saveWidgetConfig()" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white rounded-xl bg-green-600 hover:bg-green-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Salvează setări
                        </button>
                        <button onclick="WidgetsPage.downloadPackage()" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white rounded-xl bg-primary hover:bg-primary-dark transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Descarcă pachet (.zip)
                        </button>
                        <p id="widget-config-save-hint" class="hidden text-xs text-green-600">Setările au fost salvate.</p>
                    </div>
                </div>
            </div>

            <!-- Widget Eveniment + Widget Listă -->
            <div id="widget-tabs-section" class="hidden">
                <div class="flex flex-wrap gap-2 pb-4 mb-6 border-b border-border">
                    <button onclick="WidgetsPage.showTab('single')" class="px-4 py-2 text-sm font-medium text-white rounded-lg widget-tab active bg-primary" data-tab="single">Widget Eveniment</button>
                    <button onclick="WidgetsPage.showTab('list')" class="px-4 py-2 text-sm font-medium rounded-lg widget-tab text-muted hover:bg-surface" data-tab="list">Widget Listă</button>
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
<style>
.wl-upload-zone {
    position: relative; border: 2px dashed #e2e8f0; border-radius: 16px;
    padding: 24px 16px; text-align: center; cursor: pointer;
    transition: all .25s ease;
    min-height: 100px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, rgba(248,250,252,0.5) 0%, rgba(241,245,249,0.8) 100%);
}
.wl-upload-zone:hover { border-color: var(--primary, #6366f1); background: rgba(99,102,241,0.03); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.wl-upload-zone.dragover { border-color: var(--primary, #6366f1); background: rgba(99,102,241,0.06); border-style: solid; }
.wl-upload-zone.has-image { border-style: solid; border-color: #e2e8f0; padding: 6px; }
.wl-upload-zone.has-image:hover { border-color: var(--primary, #6366f1); }
.wl-upload-zone.has-image .wl-upload-text { display: none; }
.wl-upload-preview { position: absolute; inset: 6px; border-radius: 10px; overflow: hidden; display: none; }
.wl-upload-zone.has-image .wl-upload-preview { display: block; }
.wl-upload-preview img { width: 100%; height: 100%; object-fit: contain; }
.wl-upload-wide { min-height: 120px; }
.wl-upload-wide .wl-upload-preview img { object-fit: cover; }
</style>
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

        // Pre-fill from saved widget config (safe — elements may not exist on older deploys)
        const wc = this.organizer.settings?.widget_config || {};
        const _s = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
        _s('full-logo', wc.logo || this.organizer.logo);
        _s('full-bg-image', wc.bg_image);
        _s('full-hero-image', wc.hero_image);
        _s('full-home-title', wc.home_title);
        _s('full-home-subtitle', wc.home_subtitle);
        _s('full-address', wc.address);
        _s('full-phone', wc.phone);
        _s('full-theme', wc.theme);
        _s('full-accent', wc.accent);
        _s('full-accent-hex', wc.accent);
        // Auto-generate return URL from domain
        if (this.domain) {
            const baseHost = this.domain.replace(/^\*\./, 'www.');
            const returnBase = this.domain.startsWith('http') ? this.domain : 'https://' + baseHost;
            _s('full-return-url', returnBase + '/multumim');
        }

        // Show image previews for existing uploads
        ['logo', 'hero_image', 'bg_image'].forEach(key => {
            const url = wc[key];
            if (url) {
                const previewId = 'preview-' + (key === 'hero_image' ? 'hero' : (key === 'bg_image' ? 'background' : key));
                const $preview = document.getElementById(previewId);
                const $zone = $preview?.closest('.wl-upload-zone');
                if ($preview && $zone) {
                    $preview.innerHTML = '<img src="' + url + '" alt="">';
                    $zone.classList.add('has-image');
                }
                // Also set hidden input value
                const fieldMap = { logo: 'full-logo', hero_image: 'full-hero-image', bg_image: 'full-bg-image' };
                _s(fieldMap[key], url);
            }
        });

        // Pre-fill terms & privacy
        const terms = this.organizer.settings?.widget_terms || '';
        const privacy = this.organizer.settings?.widget_privacy || '';
        _s('wl-terms-editor', terms);
        _s('wl-privacy-editor', privacy);

        await this.loadEvents();

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

        } catch (e) {
            console.error('Failed to save domain:', e);
            alert('Eroare la salvare. Încearcă din nou.');
        }
    },

    _v(id) { return (document.getElementById(id)?.value || '').trim(); },

    showInnerTab(tab) {
        document.querySelectorAll('.wl-inner-tab').forEach(t => {
            t.classList.remove('active', 'bg-white', 'text-secondary', 'shadow-sm');
            t.classList.add('text-muted');
        });
        document.querySelectorAll('.wl-inner-content').forEach(c => c.style.display = 'none');
        const btn = document.querySelector('.wl-inner-tab[data-itab="' + tab + '"]');
        if (btn) { btn.classList.add('active', 'bg-white', 'text-secondary', 'shadow-sm'); btn.classList.remove('text-muted'); }
        const panel = document.getElementById('itab-' + tab);
        if (panel) panel.style.display = '';
    },

    async uploadImage(fileInput, type, fieldId) {
        const file = fileInput.files[0];
        if (!file) return;

        const zone = fileInput.closest('.wl-upload-zone');
        const previewId = 'preview-' + type;
        const $preview = document.getElementById(previewId);

        // Show local preview immediately
        const reader = new FileReader();
        reader.onload = (e) => {
            if ($preview) { $preview.innerHTML = '<img src="' + e.target.result + '" alt="">'; }
            if (zone) zone.classList.add('has-image');
        };
        reader.readAsDataURL(file);

        // Upload to server
        const formData = new FormData();
        formData.append('image', file);
        formData.append('type', type);

        try {
            const token = typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getToken() : null;
            const headers = {};
            if (token) headers['Authorization'] = 'Bearer ' + token;

            const resp = await fetch(this.siteUrl + '/api/proxy.php?action=organizer.widget-image', {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: headers,
            });
            const result = await resp.json();
            if (result.success && result.data?.url) {
                const $field = document.getElementById(fieldId);
                if ($field) $field.value = result.data.url;
            }
        } catch (e) {
            console.error('Upload failed:', e);
            alert('Eroare la încărcarea imaginii.');
        }
    },

    async saveWidgetConfig() {
        const config = {
            logo: this._v('full-logo'),
            bg_image: this._v('full-bg-image'),
            hero_image: this._v('full-hero-image'),
            home_title: this._v('full-home-title'),
            home_subtitle: this._v('full-home-subtitle'),
            address: this._v('full-address'),
            phone: this._v('full-phone'),
            theme: this._v('full-theme'),
            accent: this._v('full-accent'),
            return_url: this._v('full-return-url'),
        };
        // Also save terms and privacy content
        const terms = this._v('wl-terms-editor');
        const privacy = this._v('wl-privacy-editor');

        try {
            await AmbiletAPI.put('/organizer/widget-settings', {
                settings: { widget_config: config, widget_terms: terms, widget_privacy: privacy }
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
