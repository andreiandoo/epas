<?php
/**
 * Extended Artist — Smart EPK Editor (Modulul 3)
 *
 * Implementare fidelă a designului din designs/artist/epk.html.
 *
 * Layout: header cu URL bar + 4 tab-uri (Editor / Analytics / Variante / Preview).
 * Editor: sidebar stânga (sectiuni + branding), main panel dreapta (form per sectiune).
 *
 * State: Alpine.js. Data ce vine de la API:
 *   - variants[] (max 3)
 *   - active_variant_id
 *   - live_stats (din Artist::computeKpis)
 *   - past_events (din Artist::events)
 * Salvare explicită per variantă; preview live via Alpine reactivity.
 *
 * Tab-ul Analytics afișează DATE MOCK pentru a demonstra layout-ul; va fi
 * wired la API real în Faza B.
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Extended Artist — Smart EPK';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<style>
    /* EPK editor — stiluri custom adaugate peste theme-ul ambilet */
    .epk-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.875rem; transition: all 0.15s; cursor: pointer; border: none; }
    .epk-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .epk-btn-primary { background: #A51C30; color: white; }
    .epk-btn-primary:hover:not(:disabled) { background: #8B1728; }
    .epk-btn-secondary { background: white; color: #1E293B; border: 1px solid #E2E8F0; }
    .epk-btn-secondary:hover:not(:disabled) { background: #F8FAFC; }
    .epk-btn-sm { padding: 0.4rem 0.875rem; font-size: 0.8125rem; }
    .epk-input { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #E2E8F0; border-radius: 0.75rem; font-size: 0.875rem; background: white; }
    .epk-input:focus { outline: none; border-color: #A51C30; box-shadow: 0 0 0 3px rgba(165,28,48,0.1); }
    .epk-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .epk-pro-badge { background: linear-gradient(135deg, #E67E22, #A51C30); color: white; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 0.25rem; letter-spacing: 0.5px; }
    .epk-section-card:hover .epk-drag-handle { opacity: 1; }
    .epk-drag-handle { cursor: move; opacity: 0.4; transition: opacity 0.15s; }

    /* Public preview render (inline in tab Preview) */
    .epk-public { background: #0a0a0f; color: white; }
    .epk-display { font-family: 'Playfair Display', Georgia, serif; }
    .epk-stat-glow { background: radial-gradient(circle at 50% 0%, rgba(230, 126, 34, 0.15), transparent 50%); }
</style>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen" x-data="smartEpk()" x-init="init()" x-cloak>
    <div class="p-4 lg:p-8">

        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="epk-pro-badge">PRO</span>
                <span class="text-xs text-muted uppercase tracking-wider font-semibold">Extended Artist · Smart Electronic Press Kit</span>
            </div>
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-secondary">Smart EPK</h1>
                    <p class="text-muted mt-1">Press kit dinamic, share-uibil, cu stats verificate din platformă</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span x-show="dirty && !saving" class="text-xs text-amber-600 font-medium">Modificări nesalvate</span>
                    <span x-show="saving" class="text-xs text-blue-600 font-medium">Se salvează...</span>
                    <button type="button" @click="saveActive()" :disabled="!dirty || saving" class="epk-btn epk-btn-primary epk-btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Salvează
                    </button>
                    <button type="button" @click="copyUrl()" class="epk-btn epk-btn-secondary epk-btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <span x-text="urlCopied ? 'Copiat!' : 'Copiază link'"></span>
                    </button>
                    <button type="button" @click="setTab('preview')" class="epk-btn epk-btn-primary epk-btn-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Vezi public
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading state -->
        <div x-show="loading" class="bg-white rounded-2xl border border-border p-12 text-center text-muted">
            Se încarcă EPK-ul...
        </div>

        <div x-show="!loading">

            <!-- URL bar -->
            <div class="bg-white border border-border rounded-2xl p-4 mb-6 flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-2 px-3 py-2 bg-surface rounded-lg flex-1 min-w-0">
                    <svg class="w-4 h-4 text-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span class="font-mono text-sm text-secondary truncate" x-text="publicUrlDisplay()"></span>
                </div>
                <div class="flex gap-2">
                    <a :href="qrUrl()" target="_blank" class="epk-btn epk-btn-secondary epk-btn-sm" title="QR code">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        QR
                    </a>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="border-b border-border overflow-x-auto">
                    <div class="flex gap-1 p-2 min-w-max">
                        <template x-for="t in tabs" :key="t.id">
                            <button @click="setTab(t.id)"
                                    :class="tab === t.id ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-secondary'"
                                    class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="t.icon"></svg>
                                <span x-text="t.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- ============ TAB: EDITOR ============ -->
                <div x-show="tab === 'editor'" class="p-6">
                    <div class="grid lg:grid-cols-12 gap-6">
                        <!-- Left: section list + branding -->
                        <div class="lg:col-span-4">
                            <div class="sticky top-6">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-bold text-secondary">Secțiuni</h3>
                                    <span class="text-xs text-muted"><span x-text="enabledSections.length"></span>/<span x-text="sections.length"></span> active</span>
                                </div>
                                <p class="text-xs text-muted mb-4">Activează/dezactivează secțiunile pe care le afișezi în EPK.</p>

                                <div class="space-y-2">
                                    <template x-for="(section, idx) in sections" :key="section.id">
                                        <div @click="selectedSection = section.id"
                                             :class="[
                                                 selectedSection === section.id ? 'border-primary bg-primary/5' : 'border-border bg-white',
                                                 !section.enabled ? 'opacity-50' : ''
                                             ]"
                                             class="epk-section-card border rounded-xl p-3 cursor-pointer hover:border-primary/30 transition-colors flex items-center gap-3">
                                            <svg class="epk-drag-handle w-4 h-4 text-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" :class="section.enabled ? 'bg-primary/10' : 'bg-surface'">
                                                <svg class="w-4 h-4" :class="section.enabled ? 'text-primary' : 'text-muted'" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-html="section.icon"></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-sm text-secondary truncate" x-text="section.label"></p>
                                                <p class="text-xs text-muted truncate" x-text="section.summary"></p>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" @click.stop>
                                                <input type="checkbox" x-model="section.enabled" @change="markDirty()" class="sr-only peer">
                                                <div class="w-9 h-5 bg-surface peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-success"></div>
                                            </label>
                                        </div>
                                    </template>
                                </div>

                                <!-- Branding -->
                                <div class="mt-6 p-4 bg-surface rounded-xl">
                                    <h4 class="font-bold text-secondary text-sm mb-3">Branding</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs text-muted mb-1">Culoare accent</label>
                                            <div class="flex gap-2 flex-wrap">
                                                <template x-for="c in accentColors" :key="c">
                                                    <button @click="branding.accent = c; markDirty()" :style="`background: ${c}`" :class="branding.accent === c ? 'ring-2 ring-offset-2 ring-secondary' : ''" class="w-8 h-8 rounded-lg transition-all"></button>
                                                </template>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-muted mb-1">Template</label>
                                            <select x-model="branding.template" @change="markDirty()" class="epk-input text-sm">
                                                <option value="modern">Modern (default)</option>
                                                <option value="classic">Clasic</option>
                                                <option value="minimal">Minimalist</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Variant meta inputs -->
                                <div class="mt-4 p-4 bg-surface rounded-xl">
                                    <h4 class="font-bold text-secondary text-sm mb-3">Identificare variantă</h4>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs text-muted mb-1">Nume variantă</label>
                                            <input type="text" x-model="active.name" @input="markDirty()" maxlength="100" placeholder="Default" class="epk-input text-sm">
                                            <p class="text-[11px] text-muted mt-1">Numele intern al variantei (vizibil doar de tine în Variante).</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-muted mb-1">Audiență țintă</label>
                                            <input type="text" x-model="active.target" @input="markDirty()" maxlength="100" placeholder="Universal / Festival-uri / Cluburi & bars" class="epk-input text-sm">
                                            <p class="text-[11px] text-muted mt-1">Pentru cine e gândită această variantă. Apare în Hero pe pagina publică.</p>
                                        </div>
                                        <div x-show="active.id !== state.active_variant_id">
                                            <label class="block text-xs text-muted mb-1">Slug URL (suffix)</label>
                                            <input type="text" x-model="active.slug" @input="markDirty()" maxlength="100" class="epk-input text-sm font-mono">
                                            <p class="text-[11px] text-muted mt-1">Apare în URL pentru variantele non-active: <span class="font-mono" x-text="`/epk/${state.artist.slug}/${active.slug}`"></span></p>
                                        </div>
                                        <div x-show="active.id === state.active_variant_id" class="text-[11px] text-muted bg-blue-50 border border-blue-200 rounded-lg p-2">
                                            ℹ️ Aceasta e <strong>varianta activă</strong>. URL-ul public este <span class="font-mono" x-text="`/epk/${state.artist.slug}`"></span> (slug-ul nu apare în URL pentru varianta activă).
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: section editor -->
                        <div class="lg:col-span-8">
                            <template x-for="section in sections" :key="section.id + '-edit'">
                                <div x-show="selectedSection === section.id">
                                    <div class="bg-white border border-border rounded-2xl p-6">
                                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-border">
                                            <div>
                                                <h3 class="font-bold text-secondary text-lg" x-text="section.label"></h3>
                                                <p class="text-sm text-muted" x-text="section.description"></p>
                                            </div>
                                            <span class="epk-badge" :class="section.enabled ? 'bg-success/10 text-success' : 'bg-muted/10 text-muted'">
                                                <span x-text="section.enabled ? 'Activă' : 'Inactivă'"></span>
                                            </span>
                                        </div>

                                        <!-- HERO -->
                                        <template x-if="section.id === 'hero'">
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-secondary mb-2">Nume artist</label>
                                                    <input type="text" x-model="data.stage_name" @input="markDirty()" class="epk-input">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-secondary mb-2">Tagline</label>
                                                    <input type="text" x-model="data.tagline" @input="markDirty()" maxlength="120" class="epk-input">
                                                    <p class="text-xs text-muted mt-1"><span x-text="(data.tagline || '').length"></span>/120</p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-secondary mb-2">Cover image</label>
                                                    <div @click="uploadImage('hero')" class="aspect-video rounded-xl flex items-center justify-center text-white/70 cursor-pointer hover:opacity-80 transition-opacity overflow-hidden bg-cover bg-center"
                                                         :style="data.cover_image ? `background-image: url(${data.cover_image})` : 'background: linear-gradient(135deg, #1E293B, #8B1728)'">
                                                        <span class="text-sm bg-black/30 px-3 py-1 rounded" x-text="data.cover_image ? '+ Schimbă cover' : '+ Adaugă cover'"></span>
                                                    </div>
                                                    <button x-show="data.cover_image" @click.prevent="data.cover_image = null; markDirty()" type="button" class="text-xs text-error mt-2 hover:underline">Elimină cover</button>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- STATS -->
                                        <template x-if="section.id === 'stats'">
                                            <div class="space-y-6">
                                                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 flex items-start gap-2">
                                                    <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                    <p class="text-xs text-blue-900">Cifrele vin <strong>direct din platformă</strong>. Sunt verificate. Activează-le pe cele relevante pentru tine.</p>
                                                </div>

                                                <!-- Live stats from platform -->
                                                <div>
                                                    <h4 class="text-sm font-bold text-secondary mb-3">Stats live (din platformă)</h4>
                                                    <div class="grid sm:grid-cols-2 gap-3">
                                                        <template x-for="stat in liveStats()" :key="stat.key">
                                                            <div class="border border-border rounded-xl p-4 flex items-center justify-between">
                                                                <div>
                                                                    <p class="text-xs text-muted uppercase tracking-wider font-semibold" x-text="stat.label"></p>
                                                                    <p class="text-2xl font-bold text-secondary mt-1" x-text="stat.value"></p>
                                                                </div>
                                                                <label class="relative inline-flex items-center cursor-pointer">
                                                                    <input type="checkbox" x-model="stat.show" @change="markDirty()" class="sr-only peer">
                                                                    <div class="w-9 h-5 bg-surface peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-success"></div>
                                                                </label>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>

                                                <!-- Social stats -->
                                                <div>
                                                    <h4 class="text-sm font-bold text-secondary mb-3">Stats sociale</h4>
                                                    <p class="text-xs text-muted mb-3">Followers de pe social media (din profilul tău).</p>
                                                    <div class="grid sm:grid-cols-2 gap-3">
                                                        <template x-for="stat in socialStats()" :key="stat.key">
                                                            <div class="border border-border rounded-xl p-4 flex items-center justify-between">
                                                                <div>
                                                                    <p class="text-xs text-muted uppercase tracking-wider font-semibold" x-text="stat.label"></p>
                                                                    <p class="text-2xl font-bold text-secondary mt-1" x-text="stat.value"></p>
                                                                </div>
                                                                <label class="relative inline-flex items-center cursor-pointer">
                                                                    <input type="checkbox" x-model="stat.show" @change="markDirty()" class="sr-only peer">
                                                                    <div class="w-9 h-5 bg-surface peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-success"></div>
                                                                </label>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>

                                                <!-- Custom stats — artist-defined -->
                                                <div>
                                                    <h4 class="text-sm font-bold text-secondary mb-3">Stats personalizate</h4>
                                                    <p class="text-xs text-muted mb-3">Adaugă orice cifră vrei tu (ex: „Premii câștigate: 7" sau „Țări vizitate: 12"). Max 6.</p>
                                                    <div class="space-y-2">
                                                        <template x-for="(cs, i) in data.custom_stats" :key="i">
                                                            <div class="flex gap-2 items-center">
                                                                <input type="text" x-model="cs.label" @input="markDirty()" maxlength="40" placeholder="Etichetă (ex: Ani de carieră)" class="epk-input" style="flex:1 1 auto; min-width:0">
                                                                <input type="text" x-model="cs.value" @input="markDirty()" maxlength="20" placeholder="Valoare (ex: 12)" class="epk-input" style="width:8rem; flex:0 0 8rem">
                                                                <button @click="data.custom_stats.splice(i, 1); markDirty()" class="p-2 text-muted hover:text-error rounded-lg flex-shrink-0">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <button x-show="(data.custom_stats || []).length < 6" @click="data.custom_stats.push({ label: '', value: '' }); markDirty()" class="mt-2 text-sm text-primary font-medium hover:underline">+ Adaugă stat personalizat</button>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- BIO -->
                                        <template x-if="section.id === 'bio'">
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-secondary mb-2">Bio scurt (pentru carduri)</label>
                                                    <textarea x-model="data.bio_short" @input="markDirty()" rows="3" maxlength="280" class="epk-input"></textarea>
                                                    <p class="text-xs text-muted mt-1"><span x-text="(data.bio_short || '').length"></span>/280</p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-secondary mb-2">Bio extins</label>
                                                    <textarea x-model="data.bio_long" @input="markDirty()" rows="8" data-rich-editor data-bio-long-editor class="epk-input"></textarea>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- GALLERY -->
                                        <template x-if="section.id === 'gallery'">
                                            <div>
                                                <p class="text-sm text-muted mb-3">Maxim 12 imagini. Prima e marcată „PRINCIPAL" pe pagina publică.</p>
                                                <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                                                    <template x-for="(img, i) in nonEmptyGallery()" :key="i + '-' + img">
                                                        <div class="relative aspect-square bg-cover bg-center rounded-lg overflow-hidden group bg-surface" :style="`background-image: url(${img})`">
                                                            <span x-show="i === 0" class="absolute top-1 left-1 text-[10px] bg-primary text-white px-1.5 py-0.5 rounded font-bold">PRINCIPAL</span>
                                                            <button @click="removeGalleryImage(i); markDirty()" class="absolute top-1 right-1 w-6 h-6 bg-error text-white rounded-full opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <button x-show="nonEmptyGallery().length < 12" @click="uploadImage('gallery')" class="aspect-square border-2 border-dashed border-border rounded-lg flex items-center justify-center text-muted hover:border-primary/30 hover:text-primary transition-colors">
                                                        <span class="text-2xl">+</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- SPOTIFY -->
                                        <template x-if="section.id === 'spotify'">
                                            <div>
                                                <label class="block text-sm font-medium text-secondary mb-2">Link Spotify</label>
                                                <input type="url" x-model="data.spotify_url" @input="markDirty()" class="epk-input" placeholder="https://open.spotify.com/artist/...">
                                                <p class="text-xs text-muted mt-2">Acceptă: artist, album sau playlist. Se afișează card cu link direct.</p>
                                            </div>
                                        </template>

                                        <!-- YOUTUBE -->
                                        <template x-if="section.id === 'youtube'">
                                            <div>
                                                <label class="block text-sm font-medium text-secondary mb-2">Videoclipuri YouTube (max 4)</label>
                                                <div class="space-y-2">
                                                    <template x-for="(v, i) in data.youtube_videos" :key="i">
                                                        <div class="flex gap-2">
                                                            <input type="url" x-model="data.youtube_videos[i].url" @input="markDirty()" class="epk-input flex-1" placeholder="https://youtube.com/watch?v=...">
                                                            <button @click="data.youtube_videos.splice(i, 1); markDirty()" class="p-2.5 text-muted hover:text-error rounded-lg hover:bg-error/5">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <button x-show="data.youtube_videos.length < 4" @click="data.youtube_videos.push({ url: '' }); markDirty()" class="text-sm text-primary font-medium hover:underline">+ Adaugă video</button>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- ACHIEVEMENTS -->
                                        <template x-if="section.id === 'achievements'">
                                            <div>
                                                <p class="text-sm text-muted mb-3">Realizări notabile, cronologic descrescător.</p>
                                                <div class="space-y-2">
                                                    <template x-for="(a, i) in data.achievements" :key="i">
                                                        <div class="flex gap-2 items-center">
                                                            <input type="number" x-model.number="a.year" @input="markDirty()" placeholder="An" class="epk-input" style="width:6rem; flex:0 0 6rem">
                                                            <input type="text" x-model="a.text" @input="markDirty()" placeholder="Realizare (ex: Cap de afiș Untold)" class="epk-input" style="flex:1 1 auto; min-width:0">
                                                            <button @click="data.achievements.splice(i, 1); markDirty()" class="p-2 text-muted hover:text-error rounded-lg flex-shrink-0">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                                <button @click="data.achievements.push({ year: new Date().getFullYear(), text: '' }); markDirty()" class="text-sm text-primary font-medium hover:underline mt-2">+ Adaugă realizare</button>
                                            </div>
                                        </template>

                                        <!-- PRESS QUOTES -->
                                        <template x-if="section.id === 'press_quotes'">
                                            <div>
                                                <p class="text-sm text-muted mb-3">Citate din presă cu sursă și link.</p>
                                                <div class="space-y-3">
                                                    <template x-for="(q, i) in data.press_quotes" :key="i">
                                                        <div class="border border-border rounded-xl p-3">
                                                            <textarea x-model="q.text" @input="markDirty()" rows="2" class="epk-input mb-2" placeholder="Citat..."></textarea>
                                                            <div class="grid grid-cols-2 gap-2 mb-2">
                                                                <input type="text" x-model="q.source" @input="markDirty()" class="epk-input" placeholder="Sursă (ex: Adevărul)">
                                                                <input type="url" x-model="q.url" @input="markDirty()" class="epk-input" placeholder="Link">
                                                            </div>
                                                            <button @click="data.press_quotes.splice(i, 1); markDirty()" class="text-xs text-error hover:underline">Șterge citat</button>
                                                        </div>
                                                    </template>
                                                </div>
                                                <button @click="data.press_quotes.push({ text: '', source: '', url: '' }); markDirty()" class="text-sm text-primary font-medium hover:underline mt-2">+ Adaugă citat</button>
                                            </div>
                                        </template>

                                        <!-- PAST EVENTS -->
                                        <template x-if="section.id === 'past_events'">
                                            <div>
                                                <p class="text-sm text-muted mb-3">Evenimente trecute (auto din platformă). Bifează „Ascunde" pentru cele pe care nu vrei să apară.</p>
                                                <div class="space-y-2 max-h-96 overflow-y-auto">
                                                    <template x-for="ev in state.past_events" :key="ev.id">
                                                        <div class="flex items-center gap-3 p-3 border border-border rounded-xl">
                                                            <div class="w-10 h-10 rounded-lg bg-surface flex flex-col items-center justify-center flex-shrink-0">
                                                                <span class="text-xs font-bold text-primary leading-none" x-text="ev.day"></span>
                                                                <span class="text-[10px] text-muted uppercase" x-text="ev.month"></span>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <p class="font-medium text-sm text-secondary truncate" x-text="ev.title"></p>
                                                                <p class="text-xs text-muted truncate" x-text="ev.venue"></p>
                                                            </div>
                                                            <label class="flex items-center gap-2">
                                                                <input type="checkbox" :checked="(data.past_events_hidden || []).includes(ev.id)" @change="togglePastEventHidden(ev.id)" class="w-4 h-4 rounded">
                                                                <span class="text-xs text-muted">Ascunde</span>
                                                            </label>
                                                        </div>
                                                    </template>
                                                    <p x-show="!state.past_events?.length" class="text-sm text-muted p-3">Nu ai evenimente trecute încă.</p>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- RIDER -->
                                        <template x-if="section.id === 'rider'">
                                            <div class="space-y-4">
                                                <div class="flex items-center gap-3 p-4 border border-border rounded-xl">
                                                    <svg class="w-8 h-8 text-accent flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-medium text-secondary">Rider tehnic</p>
                                                        <p class="text-xs text-muted truncate">
                                                            <a x-show="data.rider_pdf_url" :href="data.rider_pdf_url" target="_blank" class="text-primary hover:underline" x-text="data.rider_pdf_url"></a>
                                                            <span x-show="!data.rider_pdf_url" class="italic">Niciun PDF încărcat</span>
                                                        </p>
                                                    </div>
                                                    <button @click="uploadRider()" class="epk-btn epk-btn-secondary epk-btn-sm">
                                                        <span x-text="data.rider_pdf_url ? 'Înlocuiește' : 'Încarcă PDF'"></span>
                                                    </button>
                                                </div>
                                                <label class="flex items-center gap-2 cursor-pointer pt-2">
                                                    <input type="checkbox" x-model="data.rider_gated" @change="markDirty()" class="w-4 h-4 rounded text-primary">
                                                    <span class="text-sm text-secondary">Cere email înainte de descărcare (generează lead)</span>
                                                </label>
                                            </div>
                                        </template>

                                        <!-- SOCIAL -->
                                        <template x-if="section.id === 'social'">
                                            <div class="space-y-3">
                                                <template x-for="s in socialPlatforms" :key="s.key">
                                                    <div>
                                                        <label class="block text-sm font-medium text-secondary mb-1" x-text="s.label"></label>
                                                        <input type="url" x-model="data.social[s.key]" @input="markDirty()" :placeholder="s.placeholder" class="epk-input">
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- CONTACT -->
                                        <template x-if="section.id === 'contact'">
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-secondary mb-1">Email contact</label>
                                                    <input type="email" x-model="data.contact_email" @input="markDirty()" class="epk-input">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-secondary mb-1">Telefon</label>
                                                    <input type="tel" x-model="data.contact_phone" @input="markDirty()" class="epk-input">
                                                </div>
                                                <label class="flex items-center gap-2 cursor-pointer pt-2">
                                                    <input type="checkbox" x-model="data.show_booking_cta" @change="markDirty()" class="w-4 h-4 rounded text-primary">
                                                    <span class="text-sm text-secondary">Afișează buton „Cere booking"</span>
                                                </label>

                                                <div x-show="data.show_booking_cta" class="pt-2">
                                                    <label class="block text-sm font-medium text-secondary mb-1">Tipuri de evenimente pentru booking</label>
                                                    <p class="text-xs text-muted mb-2">Apar sub butonul „Booking" pe pagina publică (ex: „Concerte · Festivaluri · Corporate"). Lasă gol ca să ascunzi rândul.</p>
                                                    <div class="space-y-2">
                                                        <template x-for="(t, i) in data.event_types" :key="i">
                                                            <div class="flex gap-2 items-center">
                                                                <input type="text" x-model="data.event_types[i]" @input="markDirty()" maxlength="40" class="epk-input" style="flex:1 1 auto; min-width:0">
                                                                <button @click="data.event_types.splice(i, 1); markDirty()" class="p-2 text-muted hover:text-error rounded-lg flex-shrink-0">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <button x-show="(data.event_types || []).length < 8" @click="data.event_types.push(''); markDirty()" class="mt-2 text-sm text-primary font-medium hover:underline">+ Adaugă tip eveniment</button>
                                                </div>
                                            </div>
                                        </template>

                                        <div class="mt-6 pt-6 border-t border-border flex justify-end gap-2">
                                            <button @click="loadActiveVariant()" :disabled="!dirty" class="epk-btn epk-btn-secondary epk-btn-sm">Anulează</button>
                                            <button @click="saveActive()" :disabled="!dirty || saving" class="epk-btn epk-btn-primary epk-btn-sm">Salvează</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- ============ TAB: ANALYTICS (mock data — Faza B) ============ -->
                <div x-show="tab === 'analytics'" class="p-6">
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-6 flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <p class="text-sm text-amber-900"><strong>Date demo</strong> — tracking-ul real va fi activat în Faza B (sprintul următor). Layout-ul de mai jos arată ce vei vedea după lansare.</p>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">Vizualizări (30 zile)</p>
                            <p class="text-2xl font-bold text-secondary">2.847</p>
                            <p class="text-xs text-success mt-1">+34% vs perioada anterioară</p>
                        </div>
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">Vizitatori unici</p>
                            <p class="text-2xl font-bold text-secondary">1.923</p>
                            <p class="text-xs text-success mt-1">+28%</p>
                        </div>
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">Timp mediu pe pagină</p>
                            <p class="text-2xl font-bold text-secondary">3:42</p>
                            <p class="text-xs text-muted mt-1">Bun (>2 min)</p>
                        </div>
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">CTA „Cere booking"</p>
                            <p class="text-2xl font-bold text-secondary">47</p>
                            <p class="text-xs text-success mt-1">2.4% conversion rate</p>
                        </div>
                    </div>

                    <div class="grid lg:grid-cols-2 gap-6 mb-6">
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <h3 class="font-bold text-secondary mb-1">Trafic zilnic</h3>
                            <p class="text-sm text-muted mb-4">Vizualizări în ultimele 30 de zile</p>
                            <div class="relative h-[260px]"><canvas id="trafficChart"></canvas></div>
                        </div>
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <h3 class="font-bold text-secondary mb-1">Surse de trafic</h3>
                            <p class="text-sm text-muted mb-4">De unde vin vizitatorii</p>
                            <div class="relative h-[260px]"><canvas id="sourcesChart"></canvas></div>
                        </div>
                    </div>

                    <div class="bg-white border border-border rounded-2xl p-5 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-bold text-secondary flex items-center gap-2">🔥 Hot Leads</h3>
                                <p class="text-sm text-muted">Vizitatori care au revenit de mai multe ori — semnal puternic de interes</p>
                            </div>
                            <span class="epk-badge bg-error/10 text-error">3 active</span>
                        </div>
                        <div class="space-y-3">
                            <template x-for="lead in hotLeads" :key="lead.id">
                                <div class="flex items-center gap-4 p-4 bg-error/5 border border-error/20 rounded-xl">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-error to-primary flex items-center justify-center text-white font-bold flex-shrink-0" x-text="lead.initials"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-secondary" x-text="lead.name"></p>
                                        <p class="text-xs text-muted">
                                            <span x-text="lead.type"></span> · <span x-text="lead.city"></span> · ultima vizită <span x-text="lead.lastVisit"></span>
                                        </p>
                                    </div>
                                    <div class="text-right hidden sm:block">
                                        <p class="text-lg font-bold text-error" x-text="lead.visits + ' vizite'"></p>
                                        <p class="text-xs text-muted" x-text="lead.timeSpent + ' total'"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="grid lg:grid-cols-2 gap-6">
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <h3 class="font-bold text-secondary mb-4">Engagement pe secțiuni</h3>
                            <p class="text-sm text-muted mb-4">Cât % din vizitatori ajung la fiecare secțiune</p>
                            <div class="space-y-3">
                                <template x-for="se in sectionEngagement" :key="se.name">
                                    <div>
                                        <div class="flex items-center justify-between text-sm mb-1">
                                            <span class="font-medium text-secondary" x-text="se.name"></span>
                                            <span class="text-muted"><span x-text="se.pct"></span>%</span>
                                        </div>
                                        <div class="h-2 bg-surface rounded-full overflow-hidden">
                                            <div class="h-full bg-primary rounded-full" :style="`width: ${se.pct}%`"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="bg-white border border-border rounded-2xl p-5">
                            <h3 class="font-bold text-secondary mb-4">Acțiuni cele mai frecvente</h3>
                            <div class="space-y-3">
                                <template x-for="a in topActions" :key="a.label">
                                    <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <span class="text-2xl" x-text="a.icon"></span>
                                            <span class="font-medium text-secondary text-sm" x-text="a.label"></span>
                                        </div>
                                        <span class="font-bold text-secondary" x-text="a.count.toLocaleString('ro-RO')"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ TAB: VARIANTS ============ -->
                <div x-show="tab === 'versions'" class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Variante EPK</h2>
                            <p class="text-sm text-muted">Creează versiuni adaptate pentru diferite tipuri de organizatori</p>
                        </div>
                        <button x-show="versions.length < state.limits?.max_variants" @click="newVariant()" class="epk-btn epk-btn-primary epk-btn-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Variantă nouă
                        </button>
                    </div>

                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="v in versions" :key="v.id">
                            <div class="border-2 rounded-2xl overflow-hidden transition-all hover:shadow-md"
                                 :class="v.id === state.active_variant_id ? 'border-primary' : 'border-border'">
                                <div class="aspect-video relative bg-cover bg-center" :style="variantPreviewStyle(v)">
                                    <!-- Overlay pentru lizibilitate când e cover image -->
                                    <div x-show="variantHasCover(v)" class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/20"></div>
                                    <span x-show="v.id === state.active_variant_id" class="absolute top-2 left-2 epk-badge bg-primary text-white z-10">Activă</span>
                                    <div class="absolute bottom-2 right-2 flex gap-1 z-10">
                                        <a :href="variantPublicUrl(v)" target="_blank" class="w-7 h-7 bg-white/90 backdrop-blur rounded-lg flex items-center justify-center text-secondary hover:bg-white transition-colors" title="Vezi public">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-bold text-secondary" x-text="v.name"></h4>
                                        <span class="text-xs text-muted" x-text="v.target || 'Universal'"></span>
                                    </div>
                                    <p class="text-xs text-muted font-mono mb-3">/<span x-text="v.slug"></span></p>

                                    <div class="grid grid-cols-3 gap-2 text-center mb-3">
                                        <div>
                                            <p class="text-xs text-muted">Vizite</p>
                                            <p class="text-sm font-bold text-secondary" x-text="(v.views_count || 0).toLocaleString('ro-RO')"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-muted">Convers.</p>
                                            <p class="text-sm font-bold text-secondary" x-text="v.conversion_pct + '%'"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-muted">Secțiuni</p>
                                            <p class="text-sm font-bold text-secondary" x-text="(v.sections || []).filter(s => s.enabled).length + '/12'"></p>
                                        </div>
                                    </div>

                                    <div class="flex gap-1">
                                        <button @click="activateVariant(v.id)" :disabled="v.id === state.active_variant_id" class="epk-btn epk-btn-secondary epk-btn-sm flex-1">
                                            <span x-text="v.id === state.active_variant_id ? 'Activă' : 'Setează activă'"></span>
                                        </button>
                                        <button @click="cloneVariant(v.id)" class="epk-btn epk-btn-secondary epk-btn-sm" title="Duplică">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </button>
                                        <button @click="deleteVariant(v.id)" :disabled="versions.length <= 1" class="epk-btn epk-btn-secondary epk-btn-sm" title="Șterge">
                                            <svg class="w-4 h-4 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <button x-show="versions.length < state.limits?.max_variants" @click="newVariant()" class="border-2 border-dashed border-border rounded-2xl flex flex-col items-center justify-center p-8 text-center hover:border-primary/30 hover:bg-primary/5 cursor-pointer transition-all min-h-[280px]">
                            <div class="w-12 h-12 bg-surface rounded-full flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            </div>
                            <p class="font-semibold text-secondary">Variantă nouă</p>
                            <p class="text-xs text-muted mt-1">Pornește de la zero sau duplică una existentă</p>
                        </button>
                    </div>
                </div>

                <!-- ============ TAB: PUBLIC PREVIEW ============ -->
                <div x-show="tab === 'preview'" class="bg-secondary">
                    <div class="bg-secondary border-b border-white/10 p-4 flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <span class="text-white/60 text-sm">Previzualizare:</span>
                            <div class="inline-flex bg-white/5 rounded-lg p-1">
                                <button @click="previewDevice = 'desktop'" :class="previewDevice === 'desktop' ? 'bg-white/15 text-white' : 'text-white/50 hover:text-white'" class="px-3 py-1 rounded text-sm font-medium transition-colors">Desktop</button>
                                <button @click="previewDevice = 'mobile'" :class="previewDevice === 'mobile' ? 'bg-white/15 text-white' : 'text-white/50 hover:text-white'" class="px-3 py-1 rounded text-sm font-medium transition-colors">Mobil</button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <a :href="publicUrl()" target="_blank" rel="noopener" class="text-sm text-white/60 hover:text-white underline">Deschide URL real →</a>
                        </div>
                    </div>
                    <div class="bg-surface p-4">
                        <iframe :src="publicUrl() + '?_t=' + previewBust" :class="previewDevice === 'mobile' ? 'mx-auto max-w-md' : 'w-full'" class="w-full bg-black rounded-xl" style="height: 80vh; border: 0"></iframe>
                        <p class="text-center text-xs text-muted mt-3">Preview-ul folosește pagina publică reală — modificările se reflectă după <strong>Salvează</strong>.</p>
                    </div>
                </div>

            </div><!-- /tabs -->

        </div>
    </div>
</main>

<style>[x-cloak]{display:none!important}</style>

<script>
function smartEpk() {
    return {
        // ========== UI state ==========
        loading: true,
        saving: false,
        dirty: false,
        urlCopied: false,
        tab: 'editor',
        selectedSection: 'hero',
        previewDevice: 'desktop',
        previewBust: Date.now(),
        charts: {},

        // ========== Static config ==========
        tabs: [
            { id: 'editor',    label: 'Editor',         icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>' },
            { id: 'analytics', label: 'Analytics',      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>' },
            { id: 'versions',  label: 'Variante',       icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>' },
            { id: 'preview',   label: 'Preview public', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>' },
        ],

        accentColors: ['#A51C30', '#E67E22', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899'],

        sectionsMeta: [
            { id: 'hero',          label: 'Hero',                  summary: 'Nume, tagline, cover',     description: 'Prima impresie. Nume scenă, tagline și imagine cover.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/>' },
            { id: 'stats',         label: 'Stats verificate',      summary: '5 metrici live',           description: 'Cifre creditabile direct din baza de date Ambilet. Nu pot fi falsificate.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2"/>' },
            { id: 'bio',           label: 'Biografie',             summary: 'Scurt + extins',           description: 'Spune-le organizatorilor cine ești și ce te face memorabil.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' },
            { id: 'gallery',       label: 'Galerie foto',          summary: 'Max 12 imagini',           description: 'Imagini de pe scenă, de la repetiții, portrete. Recomandat 8-12.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>' },
            { id: 'spotify',       label: 'Spotify',               summary: 'Embed muzică',             description: 'Card cu link către artist, album sau playlist.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>' },
            { id: 'youtube',       label: 'YouTube',               summary: 'Max 4 videoclipuri',       description: 'Videoclipuri embed (max 4). Cele mai relevante.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>' },
            { id: 'achievements',  label: 'Realizări',             summary: 'Timeline cronologic',      description: 'Premii, festivaluri majore, momente cheie.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>' },
            { id: 'press_quotes',  label: 'Press quotes',          summary: 'Citate presă',             description: 'Citate din articole cu sursă și link.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-4 4z"/>' },
            { id: 'past_events',   label: 'Concerte trecute',      summary: 'Auto din platformă',       description: 'Showcase de evenimente trecute. Bifează „Ascunde" pentru cele pe care nu le vrei.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>' },
            { id: 'rider',         label: 'Rider tehnic',          summary: 'PDF + lead capture',       description: 'PDF descărcabil. Opțional: cere email înainte (lead capture).', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' },
            { id: 'social',        label: 'Social media',          summary: '5 platforme',              description: 'Iconițe link către Facebook, Instagram, TikTok, etc.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>' },
            { id: 'contact',       label: 'Contact + Booking CTA', summary: 'Email, telefon, CTA',      description: 'Cum poate fi contactat artistul. Butonul „Cere booking" duce în marketplace.', icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>' },
        ],

        socialPlatforms: [
            { key: 'website',   label: 'Website',   placeholder: 'https://...' },
            { key: 'facebook',  label: 'Facebook',  placeholder: 'https://facebook.com/...' },
            { key: 'instagram', label: 'Instagram', placeholder: 'https://instagram.com/...' },
            { key: 'tiktok',    label: 'TikTok',    placeholder: 'https://tiktok.com/@...' },
            { key: 'youtube',   label: 'YouTube',   placeholder: 'https://youtube.com/@...' },
        ],

        statKeys: [
            // LIVE stats din platformă
            { key: 'tickets_sold',              label: 'Bilete vândute',           group: 'live' },
            { key: 'events_played',             label: 'Concerte',                 group: 'live' },
            { key: 'cities',                    label: 'Orașe',                    group: 'live' },
            { key: 'countries',                 label: 'Țări',                     group: 'live' },
            { key: 'peak_audience',             label: 'Audiență max',             group: 'live' },
            // Social stats din profilul artistului
            { key: 'instagram_followers',       label: 'Followers Instagram',      group: 'social' },
            { key: 'facebook_followers',        label: 'Followers Facebook',       group: 'social' },
            { key: 'youtube_followers',         label: 'Subscriberi YouTube',      group: 'social' },
            { key: 'youtube_views',             label: 'Vizualizări YouTube',      group: 'social' },
            { key: 'spotify_monthly_listeners', label: 'Ascultători lunari Spotify', group: 'social' },
            { key: 'spotify_popularity',        label: 'Popularitate Spotify',     group: 'social' },
            { key: 'tiktok_followers',          label: 'Followers TikTok',         group: 'social' },
        ],

        // ========== Server state ==========
        state: {
            epk_id: null,
            active_variant_id: null,
            live_stats: {},
            past_events: [],
            limits: { max_variants: 3, max_gallery_images: 12, max_youtube_videos: 4 },
            marketplace_domain: '',
            artist: { slug: '', name: '' },
            artist_profile: {}, // fallback values din profilul artistului (social, contact, images)
        },
        versions: [],

        // ========== Active variant editing state ==========
        active: { id: null, name: '', target: '', slug: '', accent_color: '#A51C30', template: 'modern' },
        sections: [],   // [{id, label, summary, description, icon, enabled}]
        data: {},       // flat editing object — synced with sections
        branding: { accent: '#A51C30', template: 'modern' },
        stats: [],      // [{key, label, value, show}]

        // ========== Mock analytics (Faza B placeholder) ==========
        hotLeads: [
            { id: 1, initials: 'EC', name: 'Electric Castle Productions', type: 'Organizator festival', city: 'Cluj-Napoca', visits: 5, lastVisit: 'acum 2 ore', timeSpent: '14 min' },
            { id: 2, initials: 'BK', name: 'Berăria H Booking',           type: 'Venue',                city: 'București',   visits: 4, lastVisit: 'ieri',        timeSpent: '8 min' },
            { id: 3, initials: 'SV', name: 'Summer Vibes',                type: 'Organizator festival', city: 'Mamaia',      visits: 3, lastVisit: 'acum 3 zile', timeSpent: '11 min' },
        ],
        sectionEngagement: [
            { name: 'Hero', pct: 100 }, { name: 'Stats verificate', pct: 92 }, { name: 'Biografie', pct: 78 },
            { name: 'Galerie', pct: 71 }, { name: 'Spotify', pct: 64 }, { name: 'Past events', pct: 58 },
            { name: 'Press quotes', pct: 47 }, { name: 'Booking CTA', pct: 32 },
        ],
        topActions: [
            { icon: '🎯', label: 'Click „Cere booking"',    count: 47 },
            { icon: '📥', label: 'Download Press Kit',      count: 89 },
            { icon: '📥', label: 'Download Rider tehnic',   count: 32 },
            { icon: '🎵', label: 'Click Spotify',           count: 312 },
            { icon: '📞', label: 'Click telefon',           count: 18 },
            { icon: '📧', label: 'Click email',             count: 28 },
        ],

        // ========== Computed ==========
        get enabledSections() { return this.sections.filter(s => s.enabled); },

        // ========== Lifecycle ==========
        async init() {
            await this.load();
            this.$nextTick(() => this.renderTab(this.tab));
            // Re-wire WYSIWYG când utilizatorul navighează între secțiuni —
            // textarea[data-rich-editor] e creat/distrus de Alpine x-if per secțiune.
            this.$watch('selectedSection', () => {
                this.$nextTick(() => this.wireRichEditors());
            });
            // Re-wire și după schimbare de tab (Editor → ... → Editor recreează DOM)
            this.$watch('tab', () => {
                if (this.tab === 'editor') {
                    this.$nextTick(() => this.wireRichEditors());
                }
            });
        },

        token() { return localStorage.getItem('ambilet_artist_token'); },

        async load() {
            this.loading = true;
            try {
                const res = await fetch('/api/proxy.php?action=artist.epk', {
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                const payload = await res.json();
                if (!payload?.data) throw new Error(payload?.message || 'Load failed');
                const d = payload.data;
                this.state = {
                    epk_id: d.id,
                    active_variant_id: d.active_variant_id,
                    live_stats: d.live_stats || {},
                    past_events: d.past_events || [],
                    limits: d.limits || this.state.limits,
                    marketplace_domain: d.marketplace_domain || '',
                    artist: d.artist || { slug: '', name: '' },
                    artist_profile: d.artist_profile || {},
                };
                this.versions = d.variants || [];
                this.loadActiveVariant();
                // Wire WYSIWYG editors după ce DOM-ul are noile valori
                this.$nextTick(() => this.wireRichEditors());
            } catch (e) {
                alert('Eroare la încărcare: ' + e.message);
            } finally {
                this.loading = false;
            }
        },

        // Map variant.sections (server format) → flat data + sections array (UI format)
        loadActiveVariant() {
            const v = this.versions.find(x => x.id === this.state.active_variant_id) || this.versions[0];
            if (!v) return;

            this.active = {
                id: v.id, name: v.name, target: v.target || '', slug: v.slug,
                accent_color: v.accent_color, template: v.template,
            };
            this.branding = { accent: v.accent_color, template: v.template };

            // Build sections array (merge metadata with server enabled flags).
            // Auto-enable se face mai jos, după ce data e populat (cu fallback la artist_profile).
            const serverSections = (v.sections || []).reduce((acc, s) => { acc[s.id] = s; return acc; }, {});
            const sectionEnabledMap = {};
            this.sectionsMeta.forEach(meta => {
                const s = serverSections[meta.id] || {};
                sectionEnabledMap[meta.id] = s.enabled ?? true;
            });

            // Build flat data object from sections data
            const get = (id, key, def) => serverSections[id]?.data?.[key] ?? def;
            const profile = this.state.artist_profile || {};
            // fallback helper: returnează valoarea dacă e completă, altfel valoarea fallback
            const fb = (val, fallback) => (val !== null && val !== undefined && val !== '') ? val : (fallback || '');

            // Filter gallery to non-empty strings, fallback la imaginile din profil dacă tot gol
            let gallery = (get('gallery', 'images', []) || []).filter(img => typeof img === 'string' && img.length > 0);
            if (gallery.length === 0) {
                gallery = [profile.main_image_url, profile.portrait_url].filter(x => !!x);
            }

            // Bio long: dacă e gol, ia bio_html.ro / bio_html.en din profil
            let bioLong = get('bio', 'bio_long', '');
            if (!bioLong && profile.bio_html) {
                bioLong = profile.bio_html.ro || profile.bio_html.en || Object.values(profile.bio_html)[0] || '';
            }

            this.data = {
                stage_name: fb(get('hero', 'stage_name', null), this.state.artist.name),
                tagline: get('hero', 'tagline', ''),
                cover_image: fb(get('hero', 'cover_image', null), profile.main_image_url),
                bio_short: get('bio', 'bio_short', ''),
                bio_long: bioLong,
                gallery: gallery,
                spotify_url: fb(get('spotify', 'spotify_url', null), profile.spotify_url),
                // Normalize la [{url: '...'}] indiferent de forma stocată anterior
                youtube_videos: this.normalizeYoutubeVideos(get('youtube', 'videos', null), profile.youtube_videos),
                achievements: this.firstNonEmptyArray(get('achievements', 'items', []), profile.achievements),
                press_quotes: get('press_quotes', 'quotes', []),
                past_events_hidden: get('past_events', 'hidden_event_ids', []),
                past_events_limit: get('past_events', 'limit', 12),
                rider_pdf_url: get('rider', 'rider_pdf_url', null),
                rider_pdf_path: get('rider', 'rider_pdf_path', null),
                rider_gated: get('rider', 'gated', false),
                social: this.mergeSocial(get('social', null, null), profile),
                contact_email: fb(get('contact', 'email', null), profile.email),
                contact_phone: fb(get('contact', 'phone', null), profile.phone),
                show_booking_cta: get('contact', 'show_booking_cta', true),
                // Tipuri evenimente pentru CTA Booking — editabile de artist
                event_types: (() => {
                    const e = get('contact', 'event_types', null);
                    if (Array.isArray(e) && e.length > 0) return e;
                    return ['Concerte', 'Festivaluri', 'Evenimente private', 'Corporate'];
                })(),
                // Custom stats — array de {label, value} adăugate manual de artist
                custom_stats: Array.isArray(get('stats', 'custom', [])) ? get('stats', 'custom', []) : [],
            };
            // Stats: merge live values with show flags from server
            const showFlags = get('stats', 'show', {});
            this.stats = this.statKeys.map(s => ({
                key: s.key,
                label: s.label,
                group: s.group,
                value: this.state.live_stats?.[s.key]?.display ?? '—',
                show: showFlags[s.key] ?? (s.group === 'live'), // LIVE stats default ON, social default OFF
            }));

            // Build this.sections AFTER this.data e construit, pentru auto-enable
            // bazat pe conținut. Dacă serverul a returnat enabled=false dar avem
            // date populate prin fallback (din artist_profile), forțăm enabled=true.
            const hasData = (sectionId) => {
                switch (sectionId) {
                    case 'youtube': return this.data.youtube_videos.length > 0;
                    case 'spotify': return !!this.data.spotify_url;
                    case 'achievements': return this.data.achievements.length > 0;
                    case 'social':
                        return !!(this.data.social.website || this.data.social.facebook ||
                                 this.data.social.instagram || this.data.social.tiktok ||
                                 this.data.social.youtube);
                    case 'gallery': return this.nonEmptyGallery().length > 0;
                    default: return false;
                }
            };
            this.sections = this.sectionsMeta.map(meta => {
                let enabled = sectionEnabledMap[meta.id];
                // Dacă server a zis false dar avem date populate prin fallback → enable
                if (!enabled && hasData(meta.id)) enabled = true;
                return { ...meta, enabled };
            });

            this.dirty = false;
        },

        markDirty() { this.dirty = true; },

        // Map flat data + sections + stats → variant.sections array (server format) for save
        buildSectionsForSave() {
            const showFlags = {};
            this.stats.forEach(s => { showFlags[s.key] = s.show; });

            const dataByid = {
                hero: { stage_name: this.data.stage_name, tagline: this.data.tagline, cover_image: this.data.cover_image },
                stats: { show: showFlags, custom: this.data.custom_stats || [] },
                bio: { bio_short: this.data.bio_short, bio_long: this.data.bio_long },
                gallery: { images: this.data.gallery },
                spotify: { spotify_url: this.data.spotify_url },
                youtube: { videos: this.data.youtube_videos },
                achievements: { items: this.data.achievements },
                press_quotes: { quotes: this.data.press_quotes },
                past_events: { hidden_event_ids: this.data.past_events_hidden, limit: this.data.past_events_limit },
                rider: { rider_pdf_url: this.data.rider_pdf_url, rider_pdf_path: this.data.rider_pdf_path, gated: this.data.rider_gated },
                social: this.data.social,
                contact: {
                    email: this.data.contact_email,
                    phone: this.data.contact_phone,
                    show_booking_cta: this.data.show_booking_cta,
                    event_types: (this.data.event_types || []).filter(t => typeof t === 'string' && t.trim().length > 0),
                },
            };

            return this.sections.map(s => ({
                id: s.id,
                enabled: s.enabled,
                data: dataByid[s.id] || {},
            }));
        },

        async saveActive() {
            if (!this.active.id || this.saving) return;
            this.saving = true;
            try {
                const body = {
                    name: this.active.name,
                    target: this.active.target,
                    slug: this.active.slug,
                    accent_color: this.branding.accent,
                    template: this.branding.template,
                    sections: this.buildSectionsForSave(),
                };
                const res = await fetch(`/api/proxy.php?action=artist.epk.variant.update&id=${this.active.id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                    body: JSON.stringify(body),
                });
                const payload = await res.json();
                if (!res.ok || payload?.success === false) throw new Error(payload?.message || 'Save failed');
                await this.load();
                this.previewBust = Date.now();
            } catch (e) {
                alert('Eroare la salvare: ' + e.message);
            } finally {
                this.saving = false;
            }
        },

        async newVariant() {
            const name = prompt('Nume variantă (ex: EPK Festival)');
            if (!name) return;
            const target = prompt('Audiență țintă (ex: Festival-uri)') || '';
            try {
                const res = await fetch('/api/proxy.php?action=artist.epk.variant.create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                    body: JSON.stringify({ name, target }),
                });
                const payload = await res.json();
                if (!res.ok || payload?.success === false) throw new Error(payload?.message || 'Create failed');
                await this.load();
                this.setTab('editor');
            } catch (e) { alert('Eroare: ' + e.message); }
        },

        async cloneVariant(id) {
            try {
                const res = await fetch(`/api/proxy.php?action=artist.epk.variant.clone&id=${id}`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                const payload = await res.json();
                if (!res.ok || payload?.success === false) throw new Error(payload?.message || 'Clone failed');
                await this.load();
            } catch (e) { alert('Eroare: ' + e.message); }
        },

        async activateVariant(id) {
            try {
                const res = await fetch(`/api/proxy.php?action=artist.epk.variant.activate&id=${id}`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                const payload = await res.json();
                if (!res.ok || payload?.success === false) throw new Error(payload?.message || 'Activate failed');
                await this.load();
                this.previewBust = Date.now();
            } catch (e) { alert('Eroare: ' + e.message); }
        },

        async deleteVariant(id) {
            if (!confirm('Sigur ștergi varianta? Acțiunea nu poate fi reversată.')) return;
            try {
                const res = await fetch(`/api/proxy.php?action=artist.epk.variant.delete&id=${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                const payload = await res.json();
                if (!res.ok || payload?.success === false) throw new Error(payload?.message || 'Delete failed');
                await this.load();
            } catch (e) { alert('Eroare: ' + e.message); }
        },

        uploadImage(type) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/jpeg,image/png,image/webp';
            input.onchange = async () => {
                if (!input.files[0]) return;
                const fd = new FormData();
                fd.append('image', input.files[0]);
                fd.append('type', type);
                try {
                    const res = await fetch(`/api/proxy.php?action=artist.epk.variant.upload&id=${this.active.id}`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                        body: fd,
                    });
                    const payload = await res.json();
                    if (payload?.data?.url) {
                        if (type === 'hero') {
                            this.data.cover_image = payload.data.url;
                        } else if (type === 'gallery') {
                            this.data.gallery = [...(this.data.gallery || []), payload.data.url];
                        }
                        this.markDirty();
                    } else {
                        alert('Upload eșuat: ' + (payload?.message || 'unknown'));
                    }
                } catch (e) { alert('Upload eșuat: ' + e.message); }
            };
            input.click();
        },

        uploadRider() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'application/pdf';
            input.onchange = async () => {
                if (!input.files[0]) return;
                const fd = new FormData();
                fd.append('rider', input.files[0]);
                try {
                    const res = await fetch(`/api/proxy.php?action=artist.epk.variant.upload_rider&id=${this.active.id}`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                        body: fd,
                    });
                    const payload = await res.json();
                    if (payload?.data?.url) {
                        await this.load();
                    } else {
                        alert('Upload PDF eșuat: ' + (payload?.message || 'unknown'));
                    }
                } catch (e) { alert('Upload PDF eșuat: ' + e.message); }
            };
            input.click();
        },

        togglePastEventHidden(id) {
            const arr = this.data.past_events_hidden || [];
            const idx = arr.indexOf(id);
            if (idx >= 0) arr.splice(idx, 1); else arr.push(id);
            this.data.past_events_hidden = [...arr];
            this.markDirty();
        },

        // ========== Data normalization & fallback helpers ==========
        mergeSocial(serverSocial, profile) {
            const out = { website: '', facebook: '', instagram: '', tiktok: '', youtube: '' };
            const fb = (val, fallback) => (val !== null && val !== undefined && val !== '') ? val : (fallback || '');
            if (serverSocial && typeof serverSocial === 'object') {
                Object.assign(out, serverSocial);
            }
            // Fallback la profilul artistului pentru câmpurile goale
            out.website = fb(out.website, profile.website);
            out.facebook = fb(out.facebook, profile.facebook_url);
            out.instagram = fb(out.instagram, profile.instagram_url);
            out.tiktok = fb(out.tiktok, profile.tiktok_url);
            out.youtube = fb(out.youtube, profile.youtube_url);
            return out;
        },

        normalizeYoutubeVideos(serverVideos, profileVideos) {
            const norm = (arr) => (arr || [])
                .map(v => ({ url: typeof v === 'string' ? v : (v?.url || '') }))
                .filter(v => !!v.url);

            const fromServer = norm(serverVideos);
            if (fromServer.length > 0) return fromServer;
            return norm(profileVideos);
        },

        firstNonEmptyArray(primary, fallback) {
            if (Array.isArray(primary) && primary.length > 0) return primary;
            if (Array.isArray(fallback) && fallback.length > 0) return fallback;
            return [];
        },

        nonEmptyGallery() {
            return (this.data.gallery || []).filter(img => typeof img === 'string' && img.length > 0);
        },

        liveStats() {
            return this.stats.filter(s => s.group === 'live');
        },

        socialStats() {
            return this.stats.filter(s => s.group === 'social');
        },

        // ========== Variant card preview helpers ==========
        variantHasCover(v) {
            const hero = (v.sections || []).find(s => s.id === 'hero');
            return !!(hero?.data?.cover_image);
        },

        variantPreviewStyle(v) {
            const hero = (v.sections || []).find(s => s.id === 'hero');
            const cover = hero?.data?.cover_image;
            const accent = v.accent_color || '#A51C30';
            if (cover) {
                return `background-image: url('${cover}'); background-color: ${accent}`;
            }
            // Fallback la gradient atunci când nu există cover image
            return `background: linear-gradient(135deg, ${accent}, ${accent}88)`;
        },

        removeGalleryImage(displayIdx) {
            // displayIdx e index-ul în lista filtrată; trebuie să găsim corespondentul în array-ul real
            const filtered = this.nonEmptyGallery();
            const target = filtered[displayIdx];
            const realIdx = (this.data.gallery || []).indexOf(target);
            if (realIdx >= 0) {
                this.data.gallery.splice(realIdx, 1);
            }
        },

        // ========== Rich text editor (WYSIWYG pentru bio extins) ==========
        // Inline copy of the editor from artist-cont-detalii.js — same toolbar.
        wireRichEditors() {
            document.querySelectorAll('textarea[data-rich-editor]').forEach(textarea => {
                if (textarea._editorAttached) {
                    if (textarea._editor) textarea._editor.innerHTML = textarea.value || '';
                    return;
                }
                this.attachRichEditor(textarea);
            });
        },

        attachRichEditor(textarea) {
            const wrap = document.createElement('div');
            wrap.className = 'overflow-hidden rounded-lg border border-border bg-white';

            const toolbar = document.createElement('div');
            toolbar.className = 'flex flex-wrap items-center gap-1 border-b border-border bg-surface px-2 py-1';

            const editor = document.createElement('div');
            editor.contentEditable = 'true';
            editor.className = 'prose prose-sm max-h-[500px] min-h-[200px] max-w-none overflow-y-auto bg-white p-3 focus:outline-none';
            editor.innerHTML = textarea.value || '';

            const sync = () => {
                textarea.value = editor.innerHTML;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            };

            const buttons = [
                { cmd: 'bold', label: 'B', title: 'Bold (Ctrl+B)', style: 'font-weight:700' },
                { cmd: 'italic', label: 'I', title: 'Italic (Ctrl+I)', style: 'font-style:italic' },
                { cmd: 'underline', label: 'U', title: 'Underline', style: 'text-decoration:underline' },
                { sep: true },
                { cmd: 'formatBlock', arg: '<h3>', label: 'H', title: 'Subtitlu', style: 'font-weight:700' },
                { cmd: 'formatBlock', arg: '<p>', label: 'P', title: 'Paragraf' },
                { sep: true },
                { cmd: 'insertUnorderedList', label: '•', title: 'Listă bullets' },
                { cmd: 'insertOrderedList', label: '1.', title: 'Listă numerotată' },
                { sep: true },
                { cmd: 'createLink', label: '🔗', title: 'Inserează link', prompt: 'URL:' },
                { cmd: 'unlink', label: '⛔', title: 'Șterge link' },
                { sep: true },
                { cmd: 'removeFormat', label: '⌫', title: 'Curăță formatare' },
            ];

            buttons.forEach(btn => {
                if (btn.sep) {
                    const sep = document.createElement('span');
                    sep.className = 'mx-1 w-px self-stretch bg-border';
                    toolbar.appendChild(sep);
                    return;
                }
                const b = document.createElement('button');
                b.type = 'button';
                b.title = btn.title;
                b.className = 'min-w-[28px] rounded px-2 py-1 text-sm text-secondary hover:bg-primary/10';
                if (btn.style) b.setAttribute('style', btn.style);
                b.textContent = btn.label;
                b.addEventListener('mousedown', e => e.preventDefault());
                b.addEventListener('click', () => {
                    let arg = btn.arg;
                    if (btn.prompt) {
                        arg = window.prompt(btn.prompt, 'https://');
                        if (!arg) return;
                    }
                    editor.focus();
                    try { document.execCommand(btn.cmd, false, arg); } catch (e) { /* old browsers */ }
                    sync();
                });
                toolbar.appendChild(b);
            });

            editor.addEventListener('input', sync);
            editor.addEventListener('blur', sync);
            editor.addEventListener('paste', e => {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
            });

            wrap.appendChild(toolbar);
            wrap.appendChild(editor);

            textarea.style.display = 'none';
            textarea.parentNode.insertBefore(wrap, textarea);

            textarea._editorAttached = true;
            textarea._editor = editor;
        },

        // ========== URL helpers ==========
        domainBase() {
            if (!this.state.marketplace_domain) return '';
            const d = this.state.marketplace_domain;
            return d.startsWith('http') ? d : 'https://' + d;
        },

        publicUrl() {
            if (!this.state.artist?.slug) return '#';
            // Active variant: /epk/{slug}; non-active: /epk/{slug}/{variant_slug}
            const isActive = this.active.id === this.state.active_variant_id;
            const path = isActive
                ? `/epk/${this.state.artist.slug}`
                : `/epk/${this.state.artist.slug}/${this.active.slug}`;
            return this.domainBase() + path;
        },

        publicUrlDisplay() {
            const url = this.publicUrl();
            return url.replace(/^https?:\/\//, '');
        },

        variantPublicUrl(v) {
            if (!this.state.artist?.slug) return '#';
            const isActive = v.id === this.state.active_variant_id;
            const path = isActive
                ? `/epk/${this.state.artist.slug}`
                : `/epk/${this.state.artist.slug}/${v.slug}`;
            return this.domainBase() + path;
        },

        qrUrl() {
            // Folosim api.qrserver.com (gratuit, fără auth) pentru a evita
            // dependența de endroid/qr-code pe server. URL-ul scanat va fi
            // pagina publică EPK din varianta activă.
            const url = this.publicUrl();
            if (url === '#') return '#';
            return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=${encodeURIComponent(url)}`;
        },

        pdfUrl() {
            if (!this.active.id) return '#';
            return `/api/proxy.php?action=artist.epk.variant.pdf&id=${this.active.id}&token=${this.token()}`;
        },

        copyUrl() {
            navigator.clipboard?.writeText(this.publicUrl());
            this.urlCopied = true;
            setTimeout(() => this.urlCopied = false, 2000);
        },

        setTab(id) {
            this.tab = id;
            if (id === 'preview') this.previewBust = Date.now();
            this.$nextTick(() => this.renderTab(id));
        },

        renderTab(id) {
            if (id === 'analytics') {
                this.renderTrafficChart();
                this.renderSourcesChart();
            }
        },

        destroyChart(key) {
            if (this.charts[key]) { this.charts[key].destroy(); delete this.charts[key]; }
        },

        renderTrafficChart() {
            this.destroyChart('traffic');
            const el = document.getElementById('trafficChart');
            if (!el || typeof Chart === 'undefined') return;
            const labels = Array.from({ length: 30 }, (_, i) => i + 1);
            const data = Array.from({ length: 30 }, () => Math.floor(Math.random() * 80) + 40);
            this.charts.traffic = new Chart(el.getContext('2d'), {
                type: 'line',
                data: { labels, datasets: [{ data, borderColor: '#A51C30', backgroundColor: 'rgba(165,28,48,0.1)', fill: true, tension: 0.4, pointRadius: 0, borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        },

        renderSourcesChart() {
            this.destroyChart('sources');
            const el = document.getElementById('sourcesChart');
            if (!el || typeof Chart === 'undefined') return;
            this.charts.sources = new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels: ['Direct (link)', 'Booking Marketplace', 'Email organizator', 'Social media', 'Search'],
                    datasets: [{ data: [42, 28, 15, 10, 5], backgroundColor: ['#A51C30', '#E67E22', '#10B981', '#3B82F6', '#94A3B8'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'right' } } }
            });
        },
    }
}
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>
