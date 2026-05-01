<?php
/**
 * Extended Artist — Smart EPK Editor (Modulul 3)
 *
 * Editor live wired la API. Layout in 4 tab-uri:
 *   - Editor: 12 secțiuni configurabile (toggle + data per fiecare), branding
 *   - Versiuni: listă cu max 3 variante (create / clone / activate / delete)
 *   - Preview: iframe către /epk/{artist_slug}/{variant_slug}
 *   - Analytics: stub "Disponibil în curând" (Faza B)
 *
 * State management: Alpine.js (consistent cu restul portalului).
 * Save: explicit (NU autosave). Indicator "modificări nesalvate" când dirty.
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Extended Artist — Smart EPK';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen" x-data="epkEditor()" x-init="load()">
    <div class="p-4 lg:p-6">

        {{-- Header + global save bar --}}
        <header class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Smart EPK</h1>
                <p class="mt-1 text-sm text-muted">Press kit dinamic, share-abil, cu stats LIVE din platformă.</p>
                <p class="mt-2 text-xs text-muted font-mono" x-show="state.epk">
                    URL public: <a :href="publicUrl()" target="_blank" rel="noopener" class="text-primary underline" x-text="publicUrl()"></a>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span x-show="dirty && !saving" class="text-xs text-amber-600 font-medium">Modificări nesalvate</span>
                <span x-show="saving" class="text-xs text-blue-600 font-medium">Se salvează...</span>
                <button type="button" @click="saveActive()" :disabled="!dirty || saving"
                    class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md transition-all disabled:opacity-50 hover:bg-primary-dark">
                    Salvează
                </button>
            </div>
        </header>

        {{-- Tabs --}}
        <div class="mb-6 border-b border-border">
            <nav class="flex gap-6">
                <template x-for="t in ['editor', 'versions', 'preview', 'analytics']" :key="t">
                    <button @click="tab = t"
                        :class="tab === t ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-secondary'"
                        class="border-b-2 px-1 py-3 text-sm font-medium capitalize transition-colors">
                        <span x-text="tabLabel(t)"></span>
                    </button>
                </template>
            </nav>
        </div>

        <div x-show="loading" class="rounded-2xl border border-border bg-white p-8 text-center text-muted">
            Se încarcă EPK-ul...
        </div>

        <div x-show="!loading">

            {{-- ============================ EDITOR TAB ============================ --}}
            <div x-show="tab === 'editor'" class="space-y-6">

                <div class="rounded-2xl border border-border bg-white p-6">
                    <h2 class="mb-4 text-lg font-bold text-secondary">Variantă activă</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs text-muted uppercase">Nume variantă</label>
                            <input type="text" x-model="active.name" @input="markDirty()" maxlength="100" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-muted uppercase">Audiență țintă</label>
                            <input type="text" x-model="active.target" @input="markDirty()" maxlength="100" placeholder="Universal" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-muted uppercase">Slug URL</label>
                            <input type="text" x-model="active.slug" @input="markDirty()" maxlength="100" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm font-mono">
                        </div>
                        <div>
                            <label class="text-xs text-muted uppercase">Culoare accent</label>
                            <div class="mt-1 flex items-center gap-2">
                                <template x-for="c in accentColors" :key="c">
                                    <button type="button" @click="active.accent_color = c; markDirty()"
                                        :class="active.accent_color === c ? 'ring-2 ring-offset-2 ring-secondary' : ''"
                                        class="h-8 w-8 rounded-full border" :style="`background-color: ${c}`"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sectiunile celor 12 module --}}
                <template x-for="section in active.sections" :key="section.id">
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="text-base font-bold text-secondary" x-text="sectionLabel(section.id)"></h3>
                                <p class="text-xs text-muted" x-text="sectionHelp(section.id)"></p>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <span class="text-xs text-muted">Afișează</span>
                                <input type="checkbox" x-model="section.enabled" @change="markDirty()" class="h-5 w-5 rounded">
                            </label>
                        </div>

                        <div x-show="section.enabled" class="space-y-3">

                            {{-- HERO --}}
                            <template x-if="section.id === 'hero'">
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-xs text-muted uppercase">Stage name</label>
                                        <input type="text" x-model="section.data.stage_name" @input="markDirty()" maxlength="80" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted uppercase">Tagline</label>
                                        <input type="text" x-model="section.data.tagline" @input="markDirty()" maxlength="120" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted uppercase">Imagine cover</label>
                                        <div class="mt-1 flex items-center gap-3">
                                            <div x-show="section.data.cover_image" class="h-20 w-32 rounded-lg bg-cover bg-center" :style="`background-image: url(${section.data.cover_image})`"></div>
                                            <button type="button" @click="uploadImage('hero', (url) => { section.data.cover_image = url; markDirty(); })" class="rounded-lg border border-border px-3 py-1.5 text-xs">Schimbă cover</button>
                                            <button type="button" x-show="section.data.cover_image" @click="section.data.cover_image = null; markDirty()" class="rounded-lg border border-red-200 text-red-600 px-3 py-1.5 text-xs">Elimină</button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- STATS (read-only LIVE) --}}
                            <template x-if="section.id === 'stats'">
                                <div class="space-y-3">
                                    <p class="text-xs text-muted italic">Cifrele vin LIVE din platformă (nu pot fi editate).</p>
                                    <template x-for="key in ['tickets_sold', 'events_played', 'cities', 'countries', 'peak_audience']" :key="key">
                                        <label class="flex items-center justify-between gap-3 py-2 border-b border-border">
                                            <div>
                                                <span class="text-sm font-medium text-secondary" x-text="statLabel(key)"></span>
                                                <span class="ml-2 text-xs text-muted" x-text="state.live_stats?.[key]?.display ?? '—'"></span>
                                            </div>
                                            <input type="checkbox" :checked="section.data.show?.[key] ?? true" @change="section.data.show = { ...section.data.show, [key]: $event.target.checked }; markDirty()" class="h-5 w-5">
                                        </label>
                                    </template>
                                </div>
                            </template>

                            {{-- BIO --}}
                            <template x-if="section.id === 'bio'">
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-xs text-muted uppercase">Bio scurt (max 280 caractere)</label>
                                        <textarea x-model="section.data.bio_short" @input="markDirty()" maxlength="280" rows="3" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm"></textarea>
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted uppercase">Bio extins</label>
                                        <textarea x-model="section.data.bio_long" @input="markDirty()" rows="8" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm"></textarea>
                                    </div>
                                </div>
                            </template>

                            {{-- GALLERY --}}
                            <template x-if="section.id === 'gallery'">
                                <div class="space-y-3">
                                    <p class="text-xs text-muted" x-text="`Max 12 imagini. Acum: ${(section.data.images || []).length}`"></p>
                                    <div class="grid grid-cols-3 gap-2 md:grid-cols-4 lg:grid-cols-6">
                                        <template x-for="(img, idx) in section.data.images || []" :key="idx">
                                            <div class="relative aspect-square rounded-lg bg-cover bg-center overflow-hidden border border-border" :style="`background-image: url(${img})`">
                                                <button type="button" @click="section.data.images.splice(idx, 1); markDirty()" class="absolute top-1 right-1 h-6 w-6 rounded-full bg-red-500 text-white text-xs">✕</button>
                                                <span x-show="idx === 0" class="absolute bottom-1 left-1 rounded bg-primary px-2 py-0.5 text-[10px] font-bold text-white">PRINCIPAL</span>
                                            </div>
                                        </template>
                                        <button type="button"
                                            x-show="(section.data.images || []).length < 12"
                                            @click="uploadImage('gallery', (url) => { section.data.images = [...(section.data.images || []), url]; markDirty(); })"
                                            class="aspect-square rounded-lg border-2 border-dashed border-border flex items-center justify-center text-2xl text-muted hover:bg-surface">+</button>
                                    </div>
                                </div>
                            </template>

                            {{-- SPOTIFY --}}
                            <template x-if="section.id === 'spotify'">
                                <div>
                                    <label class="text-xs text-muted uppercase">Link Spotify (artist / album / playlist)</label>
                                    <input type="url" x-model="section.data.spotify_url" @input="markDirty()" placeholder="https://open.spotify.com/artist/..." class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                                </div>
                            </template>

                            {{-- YOUTUBE --}}
                            <template x-if="section.id === 'youtube'">
                                <div class="space-y-2">
                                    <p class="text-xs text-muted">Max 3 videoclipuri YouTube</p>
                                    <template x-for="(v, idx) in section.data.videos || []" :key="idx">
                                        <div class="flex gap-2">
                                            <input type="url" x-model="section.data.videos[idx]" @input="markDirty()" placeholder="https://youtube.com/watch?v=..." class="flex-1 rounded-lg border border-border px-3 py-2 text-sm">
                                            <button type="button" @click="section.data.videos.splice(idx, 1); markDirty()" class="rounded-lg border border-red-200 text-red-600 px-3 text-xs">✕</button>
                                        </div>
                                    </template>
                                    <button type="button"
                                        x-show="(section.data.videos || []).length < 3"
                                        @click="section.data.videos = [...(section.data.videos || []), '']; markDirty()"
                                        class="rounded-lg border border-border px-3 py-1.5 text-xs">+ Adaugă video</button>
                                </div>
                            </template>

                            {{-- ACHIEVEMENTS --}}
                            <template x-if="section.id === 'achievements'">
                                <div class="space-y-2">
                                    <template x-for="(a, idx) in section.data.items || []" :key="idx">
                                        <div class="flex gap-2">
                                            <input type="number" x-model.number="section.data.items[idx].year" @input="markDirty()" placeholder="An" class="w-24 rounded-lg border border-border px-3 py-2 text-sm">
                                            <input type="text" x-model="section.data.items[idx].text" @input="markDirty()" placeholder="Realizare" class="flex-1 rounded-lg border border-border px-3 py-2 text-sm">
                                            <button type="button" @click="section.data.items.splice(idx, 1); markDirty()" class="rounded-lg border border-red-200 text-red-600 px-3 text-xs">✕</button>
                                        </div>
                                    </template>
                                    <button type="button" @click="section.data.items = [...(section.data.items || []), { year: new Date().getFullYear(), text: '' }]; markDirty()" class="rounded-lg border border-border px-3 py-1.5 text-xs">+ Adaugă realizare</button>
                                </div>
                            </template>

                            {{-- PRESS QUOTES --}}
                            <template x-if="section.id === 'press_quotes'">
                                <div class="space-y-3">
                                    <template x-for="(q, idx) in section.data.quotes || []" :key="idx">
                                        <div class="space-y-2 rounded-lg border border-border p-3">
                                            <textarea x-model="section.data.quotes[idx].text" @input="markDirty()" rows="2" placeholder="Citat" class="w-full rounded-lg border border-border px-3 py-2 text-sm"></textarea>
                                            <div class="grid grid-cols-2 gap-2">
                                                <input type="text" x-model="section.data.quotes[idx].source" @input="markDirty()" placeholder="Sursă (ex: Adevărul)" class="rounded-lg border border-border px-3 py-2 text-sm">
                                                <input type="url" x-model="section.data.quotes[idx].url" @input="markDirty()" placeholder="URL articol" class="rounded-lg border border-border px-3 py-2 text-sm">
                                            </div>
                                            <button type="button" @click="section.data.quotes.splice(idx, 1); markDirty()" class="text-xs text-red-600">Șterge citat</button>
                                        </div>
                                    </template>
                                    <button type="button" @click="section.data.quotes = [...(section.data.quotes || []), { text: '', source: '', url: '' }]; markDirty()" class="rounded-lg border border-border px-3 py-1.5 text-xs">+ Adaugă citat</button>
                                </div>
                            </template>

                            {{-- PAST EVENTS --}}
                            <template x-if="section.id === 'past_events'">
                                <div class="space-y-3">
                                    <p class="text-xs text-muted">Toate evenimentele tale viitoare/trecute apar automat. Ascunde manual cele pe care nu vrei să fie afișate.</p>
                                    <div>
                                        <label class="text-xs text-muted uppercase">Limită afișare</label>
                                        <input type="number" min="1" max="50" x-model.number="section.data.limit" @input="markDirty()" class="mt-1 w-24 rounded-lg border border-border px-3 py-2 text-sm">
                                    </div>
                                    <div class="max-h-64 overflow-y-auto space-y-1 border border-border rounded-lg p-2">
                                        <template x-for="ev in state.past_events || []" :key="ev.id">
                                            <label class="flex items-center justify-between gap-2 p-2 hover:bg-surface rounded cursor-pointer">
                                                <span class="text-sm" x-text="`${ev.day} ${ev.month} ${ev.year} — ${ev.title} (${ev.venue})`"></span>
                                                <input type="checkbox"
                                                    :checked="!(section.data.hidden_event_ids || []).includes(ev.id)"
                                                    @change="toggleHiddenEvent(section, ev.id)"
                                                    class="h-4 w-4">
                                            </label>
                                        </template>
                                        <p x-show="!state.past_events?.length" class="text-xs text-muted p-2">Nu ai evenimente trecute încă.</p>
                                    </div>
                                </div>
                            </template>

                            {{-- RIDER --}}
                            <template x-if="section.id === 'rider'">
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-xs text-muted uppercase">Rider tehnic (PDF)</label>
                                        <div class="mt-1 flex items-center gap-3">
                                            <a x-show="section.data.rider_pdf_url" :href="section.data.rider_pdf_url" target="_blank" class="text-sm text-primary underline">PDF curent</a>
                                            <button type="button" @click="uploadRider()" class="rounded-lg border border-border px-3 py-1.5 text-xs">Încarcă PDF</button>
                                        </div>
                                    </div>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" x-model="section.data.gated" @change="markDirty()" class="h-5 w-5">
                                        <span class="text-sm">Cere email înainte de download (lead capture)</span>
                                    </label>
                                </div>
                            </template>

                            {{-- SOCIAL --}}
                            <template x-if="section.id === 'social'">
                                <div class="space-y-2">
                                    <template x-for="key in ['website', 'facebook', 'instagram', 'tiktok', 'youtube']" :key="key">
                                        <div>
                                            <label class="text-xs text-muted uppercase" x-text="key"></label>
                                            <input type="url" x-model="section.data[key]" @input="markDirty()" :placeholder="`https://${key}.com/...`" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- CONTACT --}}
                            <template x-if="section.id === 'contact'">
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-xs text-muted uppercase">Email contact</label>
                                        <input type="email" x-model="section.data.email" @input="markDirty()" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="text-xs text-muted uppercase">Telefon</label>
                                        <input type="tel" x-model="section.data.phone" @input="markDirty()" class="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm">
                                    </div>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" x-model="section.data.show_booking_cta" @change="markDirty()" class="h-5 w-5">
                                        <span class="text-sm">Arată butonul „Cere booking"</span>
                                    </label>
                                </div>
                            </template>

                        </div>
                    </div>
                </template>

                {{-- QR + PDF actions --}}
                <div class="rounded-2xl border border-border bg-white p-6">
                    <h3 class="mb-3 text-base font-bold text-secondary">Distribuție</h3>
                    <div class="flex flex-wrap gap-3">
                        <a :href="`/api/proxy.php?action=artist.epk.variant.pdf&id=${active.id}&token=${token()}`" target="_blank" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-surface">📄 Descarcă Press Kit PDF</a>
                        <button type="button" @click="showQR = !showQR" class="rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-surface">🔲 Generează QR</button>
                    </div>
                    <div x-show="showQR" class="mt-4">
                        <img :src="`/api/proxy.php?action=artist.epk.variant.qr&id=${active.id}&token=${token()}`" alt="QR Code" class="h-48 w-48">
                        <p class="mt-2 text-xs text-muted">Scanează cu telefonul → ajunge la EPK.</p>
                    </div>
                </div>

            </div>

            {{-- ============================ VERSIONS TAB ============================ --}}
            <div x-show="tab === 'versions'" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="v in state.variants" :key="v.id">
                        <div class="rounded-2xl border-2 bg-white p-4" :class="v.id === state.active_variant_id ? 'border-primary' : 'border-border'">
                            <div class="h-24 rounded-lg mb-3" :style="`background: linear-gradient(135deg, ${v.accent_color}, ${v.accent_color}AA)`"></div>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-bold text-secondary" x-text="v.name"></p>
                                    <p class="text-xs text-muted" x-text="v.target || 'Universal'"></p>
                                </div>
                                <span x-show="v.id === state.active_variant_id" class="rounded-full bg-primary text-white px-2 py-0.5 text-[10px] font-bold">ACTIVĂ</span>
                            </div>
                            <p class="mt-2 text-xs text-muted font-mono" x-text="`/${v.slug}`"></p>
                            <div class="mt-3 flex flex-wrap gap-1">
                                <button type="button" :disabled="v.id === state.active_variant_id" @click="activateVariant(v.id)" class="rounded-lg border border-border px-2 py-1 text-xs disabled:opacity-30 hover:bg-surface">Setează activă</button>
                                <button type="button" @click="cloneVariant(v.id)" class="rounded-lg border border-border px-2 py-1 text-xs hover:bg-surface">Clonează</button>
                                <button type="button" :disabled="state.variants.length <= 1" @click="deleteVariant(v.id)" class="rounded-lg border border-red-200 text-red-600 px-2 py-1 text-xs disabled:opacity-30">Șterge</button>
                            </div>
                        </div>
                    </template>

                    <button type="button" x-show="state.variants?.length < state.limits?.max_variants" @click="newVariant()" class="rounded-2xl border-2 border-dashed border-border bg-white p-4 flex flex-col items-center justify-center text-center hover:bg-surface">
                        <span class="text-4xl text-muted mb-2">+</span>
                        <span class="font-bold text-secondary">Variantă nouă</span>
                        <span class="text-xs text-muted">Pornește de la zero sau duplică o existentă</span>
                    </button>
                </div>
            </div>

            {{-- ============================ PREVIEW TAB ============================ --}}
            <div x-show="tab === 'preview'" class="rounded-2xl border border-border bg-white overflow-hidden">
                <div class="flex items-center justify-between border-b border-border p-3">
                    <p class="text-sm text-muted">Pagina publică (varianta activă)</p>
                    <a :href="publicUrl()" target="_blank" rel="noopener" class="text-sm text-primary underline">Deschide într-un tab nou →</a>
                </div>
                <iframe :src="publicUrl()" class="w-full" style="height: 70vh; border: 0"></iframe>
            </div>

            {{-- ============================ ANALYTICS TAB (stub Faza B) ============================ --}}
            <div x-show="tab === 'analytics'" class="rounded-2xl border-2 border-dashed border-border bg-white p-10 text-center">
                <h2 class="mb-2 text-xl font-bold text-secondary">Analytics — în curând</h2>
                <p class="text-sm text-muted max-w-md mx-auto">Dashboard cu views, surse de trafic, hot leads (organizatori care vizitează EPK), engagement per secțiune. Disponibil în Faza B.</p>
            </div>

        </div>
    </div>
</main>

<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function epkEditor() {
    return {
        loading: true,
        saving: false,
        dirty: false,
        tab: 'editor',
        showQR: false,
        state: {
            epk: null,
            variants: [],
            active_variant_id: null,
            live_stats: {},
            past_events: [],
            limits: {},
            marketplace_domain: '',
        },
        active: { id: null, sections: [], accent_color: '#A51C30', name: '', target: '', slug: '' },
        accentColors: ['#A51C30', '#E67E22', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899'],

        token() { return localStorage.getItem('ambilet_artist_token'); },

        async load() {
            this.loading = true;
            try {
                const res = await fetch('/api/proxy.php?action=artist.epk', {
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                const payload = await res.json();
                if (!payload?.data) throw new Error(payload?.message || 'Load failed');
                this.state = {
                    epk: payload.data.id,
                    variants: payload.data.variants,
                    active_variant_id: payload.data.active_variant_id,
                    live_stats: payload.data.live_stats,
                    past_events: payload.data.past_events,
                    limits: payload.data.limits,
                    marketplace_domain: payload.data.marketplace_domain,
                    artist: payload.data.artist,
                };
                this.loadActiveVariant();
            } catch (e) {
                alert('Eroare la încărcare: ' + e.message);
            } finally {
                this.loading = false;
            }
        },

        loadActiveVariant() {
            const v = this.state.variants.find(x => x.id === this.state.active_variant_id) || this.state.variants[0];
            if (v) {
                this.active = JSON.parse(JSON.stringify(v));
                this.dirty = false;
            }
        },

        markDirty() { this.dirty = true; },

        async saveActive() {
            if (!this.active.id || this.saving) return;
            this.saving = true;
            try {
                // Proxy.php remaps action -> PATCH upstream regardless of browser HTTP verb,
                // so we POST here pentru a evita CORS preflight (same-origin oricum).
                const res = await fetch(`/api/proxy.php?action=artist.epk.variant.update&id=${this.active.id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                    body: JSON.stringify({
                        name: this.active.name,
                        target: this.active.target,
                        slug: this.active.slug,
                        accent_color: this.active.accent_color,
                        template: this.active.template,
                        sections: this.active.sections,
                    }),
                });
                const payload = await res.json();
                if (!res.ok) throw new Error(payload?.message || 'Save failed');
                // refresh full state to pick up server-canonical slug etc.
                await this.load();
            } catch (e) {
                alert('Eroare la salvare: ' + e.message);
            } finally {
                this.saving = false;
            }
        },

        async newVariant() {
            const name = prompt('Nume variantă (ex: EPK Festival)');
            if (!name) return;
            const target = prompt('Audiență țintă (ex: Festival-uri)') || 'Universal';
            try {
                await fetch('/api/proxy.php?action=artist.epk.variant.create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                    body: JSON.stringify({ name, target }),
                });
                await this.load();
                this.tab = 'editor';
            } catch (e) {
                alert('Eroare: ' + e.message);
            }
        },

        async cloneVariant(id) {
            try {
                await fetch(`/api/proxy.php?action=artist.epk.variant.clone&id=${id}`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                await this.load();
            } catch (e) {
                alert('Eroare: ' + e.message);
            }
        },

        async activateVariant(id) {
            try {
                await fetch(`/api/proxy.php?action=artist.epk.variant.activate&id=${id}`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                await this.load();
            } catch (e) {
                alert('Eroare: ' + e.message);
            }
        },

        async deleteVariant(id) {
            if (!confirm('Sigur ștergi varianta? (acțiune ireversibilă)')) return;
            try {
                await fetch(`/api/proxy.php?action=artist.epk.variant.delete&id=${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                await this.load();
            } catch (e) {
                alert('Eroare: ' + e.message);
            }
        },

        uploadImage(type, callback) {
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
                        callback(payload.data.url);
                    } else {
                        alert('Upload eșuat: ' + (payload?.message || 'unknown'));
                    }
                } catch (e) {
                    alert('Upload eșuat: ' + e.message);
                }
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
                } catch (e) {
                    alert('Upload PDF eșuat: ' + e.message);
                }
            };
            input.click();
        },

        toggleHiddenEvent(section, eventId) {
            const ids = section.data.hidden_event_ids || [];
            const idx = ids.indexOf(eventId);
            if (idx >= 0) {
                ids.splice(idx, 1);
            } else {
                ids.push(eventId);
            }
            section.data.hidden_event_ids = [...ids];
            this.markDirty();
        },

        publicUrl() {
            if (!this.state.artist || !this.state.marketplace_domain) return '#';
            const domain = this.state.marketplace_domain.startsWith('http') ? this.state.marketplace_domain : 'https://' + this.state.marketplace_domain;
            return `${domain}/epk/${this.state.artist.slug}`;
        },

        tabLabel(t) {
            return { editor: 'Editor', versions: 'Variante', preview: 'Preview', analytics: 'Analytics' }[t];
        },

        sectionLabel(id) {
            return {
                hero: 'Hero',
                stats: 'Stats verificate (LIVE)',
                bio: 'Biografie',
                gallery: 'Galerie',
                spotify: 'Spotify',
                youtube: 'YouTube',
                achievements: 'Realizări',
                press_quotes: 'Citate presă',
                past_events: 'Evenimente trecute',
                rider: 'Rider tehnic',
                social: 'Social media',
                contact: 'Contact + Booking CTA',
            }[id] || id;
        },

        sectionHelp(id) {
            return {
                hero: 'Numele afișat mare + tagline + cover image',
                stats: 'Cifrele LIVE pe care le afișezi (toggleable)',
                bio: 'Bio scurt (cards/list) + bio extins (pagina dedicată)',
                gallery: 'Imagini live, max 12',
                spotify: 'Embed Spotify (artist / album / playlist)',
                youtube: 'Max 3 videoclipuri YouTube',
                achievements: 'Timeline cu an + descriere',
                press_quotes: 'Citate din presă cu sursă și link',
                past_events: 'Evenimente trecute auto-pull, hideabile',
                rider: 'PDF rider, opțional gated cu lead capture',
                social: 'Linkuri către conturi sociale',
                contact: 'Email, telefon, buton booking',
            }[id] || '';
        },

        statLabel(key) {
            return {
                tickets_sold: 'Bilete vândute',
                events_played: 'Concerte',
                cities: 'Orașe',
                countries: 'Țări',
                peak_audience: 'Audiență max',
            }[key] || key;
        },
    };
}
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>
