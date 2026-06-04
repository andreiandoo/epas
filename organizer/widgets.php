<?php
/**
 * bilete.online — Organizator › Widget-uri (v3).
 * Route: /organizator/widget-uri (and /organizator/widgets)
 *
 * Embed widgets + whitelabel package: single-domain config, whitelabel
 * branding (logo/hero/bg/colour/terms/privacy) + ZIP download, plus
 * single-activity and list embed code generators with live preview. Ported
 * from ambilet to v3 + shell, wired to BileteOnlineAPI.organizer
 * (/organizer/me, /organizer/widget-settings, organizer.widget-image) and the
 * existing /embed/tixello-widget.js + /embed/generate-package.php infra.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Widget-uri embed';
$currentPage = 'widgets';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';

$inputCls  = 'w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink';
$labelCls  = 'mb-1.5 block text-xs font-bold text-ink-soft';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="font-display text-3xl font-bold leading-none">Widget-uri embed</h1>
            <p class="mt-1.5 text-sm text-ink-soft">Generează coduri de embed pentru a vinde bilete direct de pe site-ul tău.</p>
        </div>

        <div id="widget-disabled-alert" class="mb-6 hidden rounded-2xl border-2 border-ochre/30 bg-ochre/10 p-4 text-sm text-ink">
            <strong>Widget-urile nu sunt activate.</strong> Contactează administratorul marketplace-ului pentru a activa funcționalitatea de embed.
        </div>

        <!-- Domain -->
        <div id="domains-section" class="mb-6 hidden rounded-2xl border-2 border-ink bg-paper p-6">
            <h2 class="mb-2 font-display text-lg font-bold">Domeniu site</h2>
            <p class="mb-4 text-sm text-ink-soft">Introdu domeniul site-ului unde vei folosi widget-urile. Pentru subdomenii folosește wildcard: <code class="rounded bg-paper-2 px-1 py-0.5 text-xs">*.numedomeniu.ro</code></p>
            <div class="flex gap-2">
                <input type="text" id="domain-input" class="<?= $inputCls ?> flex-1" placeholder="ex: https://site-meu.ro sau *.site-meu.ro">
                <button onclick="WidgetsPage.saveDomain()" class="rounded-full bg-vermilion px-6 text-sm font-bold text-paper transition hover:bg-vermilion-d">Salvează</button>
            </div>
            <p id="domain-current" class="mt-3 hidden rounded-lg bg-forest/10 px-3 py-2 text-sm text-forest"></p>
            <p id="domains-save-hint" class="mt-2 hidden text-xs text-forest">Domeniul a fost salvat.</p>
        </div>

        <!-- Whitelabel -->
        <div id="whitelabel-section" class="mb-6 hidden">
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-6 flex items-center gap-3">
                    <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></span>
                    <div>
                        <h2 class="font-display text-lg font-bold">Widget Full — pachet whitelabel</h2>
                        <p class="text-sm text-ink-soft">Site propriu de bilete. Configurează branding-ul, salvează, apoi descarcă pachetul ZIP.</p>
                    </div>
                </div>

                <div class="mb-6 inline-flex gap-1 rounded-full border-2 border-ink bg-paper p-1">
                    <button onclick="WidgetsPage.showInnerTab('branding')" class="wl-inner-tab active rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition" data-itab="branding">Branding</button>
                    <button onclick="WidgetsPage.showInnerTab('terms')" class="wl-inner-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-itab="terms">Termeni</button>
                    <button onclick="WidgetsPage.showInnerTab('privacy')" class="wl-inner-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-itab="privacy">Confidențialitate</button>
                </div>

                <div id="itab-branding" class="wl-inner-content">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-5">
                            <div class="rounded-xl border-2 border-ink/15 bg-paper-2/50 p-4">
                                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-ink-soft">Culoare principală</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" id="full-accent" value="#D4A843" class="h-12 w-12 cursor-pointer rounded-lg border-0 p-0" oninput="var h=document.getElementById('full-accent-hex');if(h)h.value=this.value;">
                                    <input type="text" id="full-accent-hex" value="#D4A843" class="w-28 rounded-lg border-2 border-ink/15 bg-paper px-3 py-2 font-mono text-sm uppercase tracking-wide outline-none focus:border-ink" oninput="var el=document.getElementById('full-accent');if(el)el.value=this.value;">
                                    <span class="text-xs text-ink-soft">Butoane, link-uri, accente</span>
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-ink-soft">Logo organizator</label>
                                <div class="wl-upload-zone" onclick="this.querySelector('input[type=file]').click()">
                                    <input type="file" accept="image/*" style="display:none" onchange="WidgetsPage.uploadImage(this, 'logo', 'full-logo')">
                                    <input type="hidden" id="full-logo">
                                    <div class="wl-upload-preview" id="preview-logo"></div>
                                    <div class="wl-upload-text"><span class="mx-auto mb-2 grid h-10 w-10 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></span><span class="text-sm font-bold">Încarcă logo</span><span class="mt-0.5 block text-xs text-ink-soft">PNG, SVG sau JPG · max 5MB</span></div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-ink-soft">Imagine hero homepage</label>
                                <div class="wl-upload-zone wl-upload-wide" onclick="this.querySelector('input[type=file]').click()">
                                    <input type="file" accept="image/*" style="display:none" onchange="WidgetsPage.uploadImage(this, 'hero', 'full-hero-image')">
                                    <input type="hidden" id="full-hero-image">
                                    <div class="wl-upload-preview" id="preview-hero"></div>
                                    <div class="wl-upload-text"><span class="mx-auto mb-2 grid h-10 w-10 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span><span class="text-sm font-bold">Imagine hero</span><span class="mt-0.5 block text-xs text-ink-soft">Recomandat: 1920×800px</span></div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-ink-soft">Imagine de fundal <span class="font-normal normal-case text-ink-soft">(opțional)</span></label>
                                <div class="wl-upload-zone" onclick="this.querySelector('input[type=file]').click()">
                                    <input type="file" accept="image/*" style="display:none" onchange="WidgetsPage.uploadImage(this, 'background', 'full-bg-image')">
                                    <input type="hidden" id="full-bg-image">
                                    <div class="wl-upload-preview" id="preview-background"></div>
                                    <div class="wl-upload-text"><span class="mx-auto mb-2 grid h-10 w-10 place-items-center rounded-full bg-paper-2 text-ink-soft"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></span><span class="text-sm font-bold">Imagine fundal</span><span class="mt-0.5 block text-xs text-ink-soft">Se aplică pe toate paginile</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div><label class="<?= $labelCls ?>">Titlu hero (acceptă HTML)</label><input type="text" id="full-home-title" class="<?= $inputCls ?>" placeholder="ex: Seara perfectă<br>începe cu <em>noi.</em>"><p class="mt-1 text-xs text-ink-soft">Folosește &lt;em&gt; pentru accent, &lt;br&gt; pentru rând nou.</p></div>
                            <div><label class="<?= $labelCls ?>">Subtitlu hero</label><input type="text" id="full-home-subtitle" class="<?= $inputCls ?>" placeholder="ex: Cele mai bune activități din orașul tău"></div>
                            <div><label class="<?= $labelCls ?>">Adresă</label><input type="text" id="full-address" class="<?= $inputCls ?>" placeholder="ex: Str. Lipscani 45, București"></div>
                            <div><label class="<?= $labelCls ?>">Telefon</label><input type="text" id="full-phone" class="<?= $inputCls ?>" placeholder="ex: +40 721 234 567"></div>
                            <div><label class="<?= $labelCls ?>">Return URL (după plată)</label><input type="text" id="full-return-url" class="<?= $inputCls ?> bg-paper" readonly><p class="mt-1 text-xs text-ink-soft">Generat automat din domeniul configurat.</p></div>
                        </div>
                    </div>
                </div>

                <div id="itab-terms" class="wl-inner-content" style="display:none;">
                    <p class="mb-3 text-sm text-ink-soft">Conținutul paginii „Termeni și condiții" de pe site-ul whitelabel. Acceptă HTML.</p>
                    <textarea id="wl-terms-editor" class="<?= $inputCls ?>" rows="14" style="font-family:monospace;font-size:13px;line-height:1.6;" placeholder="Introdu termenii și condițiile aici…"></textarea>
                </div>
                <div id="itab-privacy" class="wl-inner-content" style="display:none;">
                    <p class="mb-3 text-sm text-ink-soft">Conținutul paginii „Politica de confidențialitate" de pe site-ul whitelabel. Acceptă HTML.</p>
                    <textarea id="wl-privacy-editor" class="<?= $inputCls ?>" rows="14" style="font-family:monospace;font-size:13px;line-height:1.6;" placeholder="Introdu politica de confidențialitate aici…"></textarea>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3 border-t-2 border-ink/10 pt-6">
                    <button onclick="WidgetsPage.saveWidgetConfig()" class="inline-flex items-center gap-2 rounded-full bg-forest px-6 py-3 text-sm font-bold text-paper transition hover:opacity-90"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Salvează setări</button>
                    <button onclick="WidgetsPage.downloadPackage()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-6 py-3 text-sm font-bold text-paper transition hover:bg-vermilion-d"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Descarcă pachet (.zip)</button>
                    <p id="widget-config-save-hint" class="hidden text-xs text-forest">Setările au fost salvate.</p>
                </div>
            </div>
        </div>

        <!-- Embed widget tabs -->
        <div id="widget-tabs-section" class="hidden">
            <div class="mb-6 inline-flex gap-1 rounded-full border-2 border-ink bg-paper p-1">
                <button onclick="WidgetsPage.showTab('single')" class="widget-tab active rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition" data-tab="single">Widget activitate</button>
                <button onclick="WidgetsPage.showTab('list')" class="widget-tab rounded-full px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink" data-tab="list">Widget listă</button>
            </div>

            <div id="tab-single" class="hidden widget-tab-content">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                        <h3 class="mb-4 font-display text-base font-bold">Configurare</h3>
                        <div class="space-y-4">
                            <div><label class="<?= $labelCls ?>">Activitate</label><select id="single-event" class="<?= $inputCls ?>" onchange="WidgetsPage.updateCode('single')"><option value="">Selectează o activitate…</option></select></div>
                            <div><label class="<?= $labelCls ?>">Temă</label><select id="single-theme" class="<?= $inputCls ?>" onchange="WidgetsPage.updateCode('single')"><option value="light">Light</option><option value="dark">Dark</option></select></div>
                            <div><label class="<?= $labelCls ?>">Stil card</label><select id="single-style" class="<?= $inputCls ?>" onchange="WidgetsPage.updateCode('single')"><option value="card">Card vertical</option><option value="horizontal">Card orizontal</option><option value="compact">Compact (doar buton)</option></select></div>
                        </div>
                        <div class="mt-6">
                            <label class="<?= $labelCls ?>">Cod de embed</label>
                            <textarea id="single-code" class="<?= $inputCls ?> font-mono text-xs" rows="5" readonly onclick="this.select()"></textarea>
                            <button onclick="WidgetsPage.copyCode('single')" class="mt-2 rounded-full bg-paper-2 px-4 py-2 text-sm font-bold transition hover:bg-ink hover:text-paper">Copiază codul</button>
                        </div>
                    </div>
                    <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                        <h3 class="mb-4 font-display text-base font-bold">Preview</h3>
                        <div id="single-preview" style="min-height:200px;"></div>
                    </div>
                </div>
            </div>

            <div id="tab-list" class="hidden widget-tab-content">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                        <h3 class="mb-4 font-display text-base font-bold">Configurare</h3>
                        <div class="space-y-4">
                            <div><label class="<?= $labelCls ?>">Nr. maxim activități</label><input type="number" id="list-limit" class="<?= $inputCls ?>" value="6" min="1" max="20" onchange="WidgetsPage.updateCode('list')"></div>
                            <div><label class="<?= $labelCls ?>">Temă</label><select id="list-theme" class="<?= $inputCls ?>" onchange="WidgetsPage.updateCode('list')"><option value="light">Light</option><option value="dark">Dark</option></select></div>
                            <div><label class="<?= $labelCls ?>">Layout</label><select id="list-layout" class="<?= $inputCls ?>" onchange="WidgetsPage.updateCode('list')"><option value="grid">Grid (carduri)</option><option value="list">Listă (orizontal)</option></select></div>
                        </div>
                        <div class="mt-6">
                            <label class="<?= $labelCls ?>">Cod de embed</label>
                            <textarea id="list-code" class="<?= $inputCls ?> font-mono text-xs" rows="5" readonly onclick="this.select()"></textarea>
                            <button onclick="WidgetsPage.copyCode('list')" class="mt-2 rounded-full bg-paper-2 px-4 py-2 text-sm font-bold transition hover:bg-ink hover:text-paper">Copiază codul</button>
                        </div>
                    </div>
                    <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                        <h3 class="mb-4 font-display text-base font-bold">Preview</h3>
                        <div id="list-preview" style="min-height:200px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<style>
.wl-upload-zone { position: relative; border: 2px dashed rgba(27,23,20,.15); border-radius: 16px; padding: 24px 16px; text-align: center; cursor: pointer; transition: all .25s ease; min-height: 100px; display: flex; align-items: center; justify-content: center; background: rgba(245,239,230,.5); }
.wl-upload-zone:hover { border-color: #E84527; background: rgba(232,69,39,.03); }
.wl-upload-zone.has-image { border-style: solid; border-color: rgba(27,23,20,.15); padding: 6px; }
.wl-upload-zone.has-image .wl-upload-text { display: none; }
.wl-upload-preview { position: absolute; inset: 6px; border-radius: 10px; overflow: hidden; display: none; }
.wl-upload-zone.has-image .wl-upload-preview { display: block; }
.wl-upload-preview img { width: 100%; height: 100%; object-fit: contain; }
.wl-upload-wide { min-height: 120px; }
.wl-upload-wide .wl-upload-preview img { object-fit: cover; }
</style>

<?php
$scriptsExtra = "<script>\nwindow.__WL = " . json_encode(['siteUrl' => rtrim(SITE_URL, '/'), 'siteName' => SITE_NAME]) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
const WidgetsPage = {
    organizer: null,
    domain: '',
    siteUrl: (window.__WL && window.__WL.siteUrl) || '',
    siteName: (window.__WL && window.__WL.siteName) || 'bilete.online',

    async init() {
        if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
        try {
            const resp = await BileteOnlineAPI.get('/organizer/me');
            this.organizer = (resp.data && resp.data.organizer) || resp.organizer || resp.data || resp;
        } catch (e) { return; }

        const settings = this.organizer.settings || {};
        if (!settings.widget_enabled) { document.getElementById('widget-disabled-alert').classList.remove('hidden'); return; }

        document.getElementById('domains-section').classList.remove('hidden');
        document.getElementById('whitelabel-section').classList.remove('hidden');
        document.getElementById('widget-tabs-section').classList.remove('hidden');

        const embedDomains = settings.embed_domains || [];
        this.domain = embedDomains[0] || '';
        if (this.domain) {
            document.getElementById('domain-input').value = this.domain;
            const cur = document.getElementById('domain-current');
            cur.textContent = 'Domeniu configurat: ' + this.domain;
            cur.classList.remove('hidden');
            this.setReturnUrl(this.domain);
        }

        const wc = settings.widget_config || {};
        const _s = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
        _s('full-logo', wc.logo || this.organizer.logo);
        _s('full-bg-image', wc.bg_image);
        _s('full-hero-image', wc.hero_image);
        _s('full-home-title', wc.home_title);
        _s('full-home-subtitle', wc.home_subtitle);
        _s('full-address', wc.address);
        _s('full-phone', wc.phone);
        _s('full-accent', wc.accent);
        _s('full-accent-hex', wc.accent);

        ['logo', 'hero_image', 'bg_image'].forEach(key => {
            const url = wc[key];
            if (!url) return;
            const previewId = 'preview-' + (key === 'hero_image' ? 'hero' : (key === 'bg_image' ? 'background' : key));
            const prev = document.getElementById(previewId);
            const zone = prev && prev.closest('.wl-upload-zone');
            if (prev && zone) { prev.innerHTML = '<img src="' + url + '" alt="">'; zone.classList.add('has-image'); }
            const map = { logo: 'full-logo', hero_image: 'full-hero-image', bg_image: 'full-bg-image' };
            _s(map[key], url);
        });
        _s('wl-terms-editor', settings.widget_terms || '');
        _s('wl-privacy-editor', settings.widget_privacy || '');

        await this.loadEvents();
        this.updateCode('single');
        this.updateCode('list');
    },

    setReturnUrl(domain) {
        const baseHost = domain.replace(/^\*\./, 'www.');
        const base = domain.startsWith('http') ? domain : 'https://' + baseHost;
        const el = document.getElementById('full-return-url');
        if (el) el.value = base + '/multumim';
    },

    async saveDomain() {
        const val = document.getElementById('domain-input').value.trim();
        if (!val) return;
        this.domain = val;
        try {
            await BileteOnlineAPI.put('/organizer/widget-settings', { settings: { embed_domains: [val] } });
            const cur = document.getElementById('domain-current');
            cur.textContent = 'Domeniu configurat: ' + val;
            cur.classList.remove('hidden');
            const hint = document.getElementById('domains-save-hint');
            hint.classList.remove('hidden'); setTimeout(() => hint.classList.add('hidden'), 2000);
            this.setReturnUrl(val);
        } catch (e) { alert('Eroare la salvare. Încearcă din nou.'); }
    },

    _v(id) { return (document.getElementById(id) && document.getElementById(id).value || '').trim(); },

    showInnerTab(tab) {
        document.querySelectorAll('.wl-inner-tab').forEach(t => { t.classList.remove('active', 'bg-vermilion', 'text-paper'); t.classList.add('text-ink-soft'); });
        document.querySelectorAll('.wl-inner-content').forEach(c => c.style.display = 'none');
        const btn = document.querySelector('.wl-inner-tab[data-itab="' + tab + '"]');
        if (btn) { btn.classList.add('active', 'bg-vermilion', 'text-paper'); btn.classList.remove('text-ink-soft'); }
        const panel = document.getElementById('itab-' + tab);
        if (panel) panel.style.display = '';
    },

    async uploadImage(fileInput, type, fieldId) {
        const file = fileInput.files[0];
        if (!file) return;
        const zone = fileInput.closest('.wl-upload-zone');
        const prev = document.getElementById('preview-' + type);
        const reader = new FileReader();
        reader.onload = (e) => { if (prev) prev.innerHTML = '<img src="' + e.target.result + '" alt="">'; if (zone) zone.classList.add('has-image'); };
        reader.readAsDataURL(file);
        const fd = new FormData(); fd.append('image', file); fd.append('type', type);
        try {
            const token = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? BileteOnlineAuth.getToken() : null;
            const headers = {}; if (token) headers['Authorization'] = 'Bearer ' + token;
            const resp = await fetch(this.siteUrl + '/api/proxy.php?action=organizer.widget-image', { method: 'POST', body: fd, credentials: 'include', headers });
            const result = await resp.json();
            if (result.success && result.data && result.data.url) { const f = document.getElementById(fieldId); if (f) f.value = result.data.url; }
        } catch (e) { alert('Eroare la încărcarea imaginii.'); }
    },

    async saveWidgetConfig() {
        const config = {
            logo: this._v('full-logo'), bg_image: this._v('full-bg-image'), hero_image: this._v('full-hero-image'),
            home_title: this._v('full-home-title'), home_subtitle: this._v('full-home-subtitle'),
            address: this._v('full-address'), phone: this._v('full-phone'),
            accent: this._v('full-accent'), return_url: this._v('full-return-url'),
        };
        try {
            await BileteOnlineAPI.put('/organizer/widget-settings', { settings: { widget_config: config, widget_terms: this._v('wl-terms-editor'), widget_privacy: this._v('wl-privacy-editor') } });
            const hint = document.getElementById('widget-config-save-hint');
            hint.classList.remove('hidden'); setTimeout(() => hint.classList.add('hidden'), 3000);
        } catch (e) { alert('Eroare la salvare. Încearcă din nou.'); }
    },

    async loadEvents() {
        try {
            const resp = await BileteOnlineAPI.get('/organizer/events', { status: 'published', limit: 50 });
            let events = resp.data || [];
            if (!Array.isArray(events)) events = events.events || events.items || [];
            const sel = document.getElementById('single-event');
            events.forEach(ev => { const o = document.createElement('option'); o.value = ev.slug; o.textContent = ev.title || ev.name || 'Activitate'; sel.appendChild(o); });
        } catch (e) {}
    },

    showTab(tab) {
        document.querySelectorAll('.widget-tab').forEach(t => { t.classList.remove('active', 'bg-vermilion', 'text-paper'); t.classList.add('text-ink-soft'); });
        document.querySelectorAll('.widget-tab-content').forEach(c => c.classList.add('hidden'));
        const btn = document.querySelector('.widget-tab[data-tab="' + tab + '"]');
        btn.classList.add('active', 'bg-vermilion', 'text-paper'); btn.classList.remove('text-ink-soft');
        document.getElementById('tab-' + tab).classList.remove('hidden');
    },

    updateCode(type) {
        const slug = (this.organizer && this.organizer.slug) || '';
        if (type === 'single') {
            const eventSlug = document.getElementById('single-event').value;
            const theme = document.getElementById('single-theme').value;
            const style = document.getElementById('single-style').value;
            if (!eventSlug) {
                document.getElementById('single-code').value = '<!-- Selectează o activitate -->';
                document.getElementById('single-preview').innerHTML = '<p class="text-sm text-ink-soft">Selectează o activitate din dropdown.</p>';
                return;
            }
            let attrs = '\n  data-type="single"\n  data-event="' + eventSlug + '"\n  data-organizer="' + slug + '"\n  data-theme="' + theme + '"';
            if (style !== 'card') attrs += '\n  data-style="' + style + '"';
            document.getElementById('single-code').value = '<div id="tixello-event"></div>\n<script src="' + this.siteUrl + '/embed/tixello-widget.js"' + attrs + '>\n<\/script>';
            document.getElementById('single-preview').innerHTML = '<div id="txw-preview-single"></div>';
            this.loadWidgetPreview('txw-preview-single', 'single', eventSlug, slug, theme);
        }
        if (type === 'list') {
            const limit = document.getElementById('list-limit').value;
            const theme = document.getElementById('list-theme').value;
            const layout = document.getElementById('list-layout').value;
            let attrs = '\n  data-type="list"\n  data-organizer="' + slug + '"\n  data-limit="' + limit + '"\n  data-theme="' + theme + '"';
            if (layout !== 'grid') attrs += '\n  data-layout="' + layout + '"';
            document.getElementById('list-code').value = '<div id="tixello-events"></div>\n<script src="' + this.siteUrl + '/embed/tixello-widget.js"' + attrs + '>\n<\/script>';
            document.getElementById('list-preview').innerHTML = '<div id="txw-preview-list"></div>';
            this.loadWidgetPreview('txw-preview-list', 'list', '', slug, theme, limit);
        }
    },

    loadWidgetPreview(containerId, type, eventSlug, orgSlug, theme, limit) {
        const existing = document.getElementById('txw-preview-script'); if (existing) existing.remove();
        const oldStyles = document.getElementById('txw-styles'); if (oldStyles) oldStyles.remove();
        const s = document.createElement('script');
        s.id = 'txw-preview-script';
        s.src = this.siteUrl + '/embed/tixello-widget.js?_t=' + (window.performance ? Math.floor(performance.now()) : '1');
        s.setAttribute('data-type', type);
        s.setAttribute('data-container', containerId);
        s.setAttribute('data-theme', theme);
        if (eventSlug) s.setAttribute('data-event', eventSlug);
        if (orgSlug) s.setAttribute('data-organizer', orgSlug);
        if (limit) s.setAttribute('data-limit', limit);
        document.body.appendChild(s);
    },

    downloadPackage() {
        const slug = (this.organizer && this.organizer.slug) || '';
        window.location.href = this.siteUrl + '/embed/generate-package.php?organizer=' + encodeURIComponent(slug);
    },

    copyCode(type) {
        const ta = document.getElementById(type + '-code');
        ta.select();
        if (navigator.clipboard) navigator.clipboard.writeText(ta.value).catch(() => document.execCommand('copy'));
        else document.execCommand('copy');
        const btn = ta.parentElement.querySelector('button');
        const orig = btn.textContent; btn.textContent = 'Copiat!'; setTimeout(() => btn.textContent = orig, 1500);
    },
};

document.addEventListener('DOMContentLoaded', () => WidgetsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
