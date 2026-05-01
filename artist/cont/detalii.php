<?php
/**
 * Artist Account — Profile Editor
 * Layout follows resources/marketplaces/ambilet/designs/artist/profile.html.
 * Five top-level tabs (Profil public / Biografie / Galerie & Media /
 * Social Media / Booking & Contact). Every editable field on the
 * Artist record (per ProfileController whitelist) is exposed.
 *
 * Mechanics: single GET on load (/artist/profile + /artist/profile/taxonomies),
 * single PUT on Save with the entire dirty state; image uploads are
 * out-of-band (POST /artist/profile/image, path staged in JS until Save).
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Detalii';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<?php require __DIR__ . '/_partials/sidebar.php'; ?>

<main class="lg:ml-64 pt-16 lg:pt-0">
    <div class="p-4 lg:p-8">
        <!-- Page header -->
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Detalii Artist</h1>
                <p class="mt-1 text-muted">Profilul tău public, vizibil pe pagina evenimentelor și pe pagina ta dedicată.</p>
            </div>
            <div class="flex items-center gap-3 self-start lg:self-auto">
                <span id="dirty-indicator" class="hidden rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Modificări nesalvate</span>
                <a id="public-profile-link" href="#" target="_blank" rel="noopener" class="btn btn-secondary hidden">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Vezi profilul public
                </a>
            </div>
        </div>

        <!-- Loading state -->
        <div id="detalii-loading" class="rounded-2xl border border-border bg-white p-12 text-center">
            <div class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
            <p class="text-muted">Se încarcă datele profilului…</p>
        </div>

        <!-- Unlinked notice -->
        <div id="detalii-unlinked" class="hidden rounded-2xl border-2 border-amber-200 bg-amber-50 p-8">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-6 w-6 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h3 class="font-semibold text-amber-900">Profilul nu este încă asociat</h3>
                    <p class="mt-1 text-sm text-amber-800">Echipa nu a linkat încă un profil de artist contului tău. Te vom contacta prin email când e gata.</p>
                </div>
            </div>
        </div>

        <!-- Editor -->
        <div id="detalii-editor" class="hidden">
            <!-- Tab nav -->
            <div class="mb-6 flex flex-wrap gap-2 overflow-x-auto border-b border-border pb-4">
                <button type="button" data-tab-target="profile" class="tab-btn whitespace-nowrap rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors">
                    Profil public
                </button>
                <button type="button" data-tab-target="bio" class="tab-btn whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium text-muted hover:bg-surface">
                    Biografie
                </button>
                <button type="button" data-tab-target="gallery" class="tab-btn whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium text-muted hover:bg-surface">
                    Galerie &amp; Media
                </button>
                <button type="button" data-tab-target="social" class="tab-btn whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium text-muted hover:bg-surface">
                    Social Media
                </button>
                <button type="button" data-tab-target="booking" class="tab-btn whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium text-muted hover:bg-surface">
                    Booking &amp; Contact
                </button>
            </div>

            <form id="detalii-form" class="space-y-6">
                <!-- ============================== TAB: PROFIL PUBLIC ============================== -->
                <div data-tab="profile" class="tab-section space-y-6">
                    <!-- Hero card with avatar + name -->
                    <div class="overflow-hidden rounded-2xl border border-border bg-white">
                        <!-- Cover (uses main_image_url) -->
                        <div class="relative h-48 bg-gradient-to-br from-secondary via-primary-dark to-primary lg:h-64">
                            <img id="cover-preview" src="" class="absolute inset-0 hidden h-full w-full object-cover" alt="">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>

                            <button type="button" data-cover-upload class="absolute right-4 top-4 flex items-center gap-2 rounded-lg bg-white/90 px-3 py-2 text-sm font-medium text-secondary backdrop-blur transition-colors hover:bg-white">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Schimbă imaginea principală
                            </button>
                            <input type="file" data-cover-input data-image-field="main_image_url" data-image-type="main" accept="image/jpeg,image/png,image/webp" class="hidden">
                            <p class="absolute bottom-3 right-4 text-xs text-white/70">Recomandat 1920×1080+</p>
                        </div>

                        <div class="relative -mt-16 px-6 pb-6 lg:-mt-20">
                            <div class="flex flex-col items-start gap-4 lg:flex-row lg:items-end lg:gap-6">
                                <!-- Logo (avatar) -->
                                <div class="relative">
                                    <div class="h-32 w-32 overflow-hidden rounded-2xl border-4 border-white bg-surface shadow-xl lg:h-40 lg:w-40">
                                        <img id="logo-preview" src="" class="hidden h-full w-full object-cover" alt="">
                                        <div id="logo-placeholder" class="flex h-full w-full items-center justify-center">
                                            <svg class="h-12 w-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </div>
                                    </div>
                                    <button type="button" data-logo-upload class="absolute bottom-2 right-2 flex h-9 w-9 items-center justify-center rounded-full bg-primary text-white shadow-lg transition-colors hover:bg-primary-dark">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <input type="file" data-logo-input data-image-field="logo_url" data-image-type="logo" accept="image/jpeg,image/png,image/webp" class="hidden">
                                </div>

                                <div class="min-w-0 flex-1 pb-2">
                                    <h2 id="hero-name" class="truncate text-2xl font-bold text-secondary lg:text-3xl">—</h2>
                                    <div class="mt-2 flex items-center gap-2 text-sm text-muted">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        <span class="font-mono text-xs">ambilet.ro/artist/<span id="hero-slug" class="font-semibold text-secondary">—</span></span>
                                    </div>
                                </div>

                                <!-- Portrait — secondary square image -->
                                <div class="relative">
                                    <p class="mb-2 text-xs font-medium text-secondary">Portret</p>
                                    <div class="h-24 w-24 overflow-hidden rounded-xl border border-border bg-surface">
                                        <img id="portrait-preview" src="" class="hidden h-full w-full object-cover" alt="">
                                        <div id="portrait-placeholder" class="flex h-full w-full items-center justify-center text-muted">
                                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    </div>
                                    <button type="button" data-portrait-upload class="mt-2 text-xs font-medium text-primary hover:underline">Încarcă</button>
                                    <input type="file" data-portrait-input data-image-field="portrait_url" data-image-type="portrait" accept="image/jpeg,image/png,image/webp" class="hidden">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Identity fields -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-6 text-lg font-bold text-secondary">Informații generale</h2>

                        <div class="mb-4 grid gap-4 lg:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Nume artist *</label>
                                <input type="text" data-field="name" required maxlength="255" class="input" placeholder="Ex: The Rockers">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Casă de discuri</label>
                                <input type="text" data-field="record_label" maxlength="255" class="input" placeholder="Ex: Universal Music">
                            </div>
                        </div>

                        <div class="mb-4 grid gap-4 lg:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">An înființare</label>
                                <input type="number" data-field="founded_year" min="1800" max="2100" class="input" placeholder="2018">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Membri</label>
                                <input type="number" data-field="members_count" min="1" max="500" class="input">
                            </div>
                        </div>
                    </div>

                    <!-- Categorii (multi-select with search) -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Categorii</h2>
                        <div class="space-y-6">
                            <!-- Tipuri de artist -->
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="text-sm font-medium text-secondary">Tipuri de artist</label>
                                    <span class="text-xs text-muted"><span data-multi-count="artist_types">0</span> selectate</span>
                                </div>
                                <div class="relative mb-2">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <input type="text" data-multi-search="artist_types" placeholder="Caută tipuri…" class="input pl-9">
                                </div>
                                <div class="rounded-lg border border-border bg-surface/50 p-3">
                                    <div data-multi="artist_types" class="flex min-h-[48px] max-h-[200px] flex-wrap gap-2 overflow-y-auto"></div>
                                </div>
                            </div>

                            <!-- Genuri -->
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="text-sm font-medium text-secondary">Genuri</label>
                                    <span class="text-xs text-muted"><span data-multi-count="artist_genres">0</span> selectate</span>
                                </div>
                                <div class="relative mb-2">
                                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <input type="text" data-multi-search="artist_genres" placeholder="Caută genuri…" class="input pl-9">
                                </div>
                                <div class="rounded-lg border border-border bg-surface/50 p-3">
                                    <div data-multi="artist_genres" class="flex min-h-[48px] max-h-[200px] flex-wrap gap-2 overflow-y-auto"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Locație -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Locație</h2>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Țară</label>
                                <input type="text" data-field="country" maxlength="64" class="input" placeholder="România">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Județ</label>
                                <input type="text" data-field="state" maxlength="120" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Oraș</label>
                                <input type="text" data-field="city" maxlength="120" class="input" placeholder="București">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================== TAB: BIOGRAFIE ============================== -->
                <div data-tab="bio" class="tab-section hidden space-y-6">
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Biografie</h2>
                        <p class="mb-6 text-sm text-muted">Spune-le organizatorilor și fanilor cine ești și ce te face memorabil pe scenă.</p>

                        <div class="mb-6">
                            <label class="mb-2 block text-sm font-medium text-secondary">Română</label>
                            <textarea data-field="bio_html.ro" data-rich-editor rows="8" class="input" placeholder="Spune povestea ta…"></textarea>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-secondary">Engleză</label>
                            <textarea data-field="bio_html.en" data-rich-editor rows="8" class="input" placeholder="Tell your story…"></textarea>
                        </div>
                        <p class="mt-3 text-xs text-muted">Folosește butoanele pentru a formata textul. HTML primit este sanitizat la salvare.</p>
                    </div>

                    <!-- Realizări (achievements) -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Realizări notabile</h2>
                                <p class="text-sm text-muted">Premii, clasamente, momente importante. Maxim 20.</p>
                            </div>
                            <button type="button" data-add="achievements" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                        </div>
                        <div data-repeater="achievements" class="space-y-3"></div>
                    </div>
                </div>

                <!-- ============================== TAB: GALERIE & MEDIA ============================== -->
                <div data-tab="gallery" class="tab-section hidden space-y-6">
                    <!-- Discografie -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Discografie</h2>
                                <p class="text-sm text-muted">Albume, EP-uri, single-uri. Coperțile recomandate 500×500px (jpg/png/webp). Maxim 50 intrări.</p>
                            </div>
                            <button type="button" data-add="discography" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                        </div>
                        <div data-repeater="discography" class="space-y-3"></div>
                    </div>

                    <!-- Spotify -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#1DB954]/10">
                                <svg class="h-5 w-5 text-[#1DB954]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.42 1.56-.299.421-1.02.599-1.56.3z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Muzică pe Spotify</h2>
                                <p class="text-sm text-muted">Pentru a afișa muzica ta pe profilul public.</p>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 flex items-center gap-1.5 text-sm font-medium text-secondary">
                                    URL Spotify
                                    <span class="info-tip group relative">
                                        <svg class="h-4 w-4 cursor-help text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="pointer-events-none absolute left-1/2 top-full z-20 mt-2 hidden w-72 -translate-x-1/2 rounded-lg bg-secondary p-3 text-xs font-normal text-white shadow-lg group-hover:block">
                                            Deschide aplicația Spotify, găsește pagina ta de artist, apasă "..." → "Share" → "Copy link to artist".
                                        </span>
                                    </span>
                                </label>
                                <input type="url" data-field="spotify_url" class="input" placeholder="https://open.spotify.com/artist/...">
                            </div>
                            <div>
                                <label class="mb-2 flex items-center gap-1.5 text-sm font-medium text-secondary">
                                    Spotify Artist ID
                                    <span class="info-tip group relative">
                                        <svg class="h-4 w-4 cursor-help text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="pointer-events-none absolute left-1/2 top-full z-20 mt-2 hidden w-72 -translate-x-1/2 rounded-lg bg-secondary p-3 text-xs font-normal text-white shadow-lg group-hover:block">
                                            Este șirul de 22 caractere de la finalul URL-ului Spotify. Ex: <code class="text-accent">22txT5wgsQOCNzvnMaTiUq</code> din <code class="text-accent">spotify.com/artist/22txT5wgsQOCNzvnMaTiUq</code>. Necesar pentru a sincroniza statisticile.
                                        </span>
                                    </span>
                                </label>
                                <input type="text" data-field="spotify_id" maxlength="64" class="input" placeholder="ex: 22txT5wgsQOCNzvnMaTiUq">
                            </div>
                        </div>
                    </div>

                    <!-- YouTube videos -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FF0000]/10">
                                <svg class="h-5 w-5 text-[#FF0000]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h2 class="text-lg font-bold text-secondary">Videoclipuri YouTube</h2>
                                <p class="text-sm text-muted">Maxim 5 videoclipuri afișate pe profil.</p>
                            </div>
                            <button type="button" data-add="youtube_videos" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                        </div>
                        <div class="mb-4">
                            <label class="mb-2 flex items-center gap-1.5 text-sm font-medium text-secondary">
                                YouTube Channel ID
                                <span class="info-tip group relative">
                                    <svg class="h-4 w-4 cursor-help text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="pointer-events-none absolute left-1/2 top-full z-20 mt-2 hidden w-72 -translate-x-1/2 rounded-lg bg-secondary p-3 text-xs font-normal text-white shadow-lg group-hover:block">
                                        Începe cu <code class="text-accent">UC</code> și are 24 de caractere. Mergi pe canalul tău YouTube → "Despre" / "About" → "Distribuie canal" → "Copiază ID canal". Necesar pentru sincronizarea abonaților și vizualizărilor.
                                    </span>
                                </span>
                            </label>
                            <input type="text" data-field="youtube_id" maxlength="64" class="input" placeholder="ex: UCxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div data-repeater="youtube_videos" class="space-y-3"></div>
                    </div>
                </div>

                <!-- ============================== TAB: SOCIAL MEDIA ============================== -->
                <div data-tab="social" class="tab-section hidden space-y-6">
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Social Media &amp; Web</h2>
                        <p class="mb-6 text-sm text-muted">Link-urile tale apar ca iconițe pe profilul public.</p>

                        <div class="space-y-3">
                            <!-- Website -->
                            <div class="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:border-primary/30">
                                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-secondary/10">
                                    <svg class="h-5 w-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-muted">Website oficial</label>
                                    <input type="url" data-field="website" class="input border-0 p-0 focus:ring-0" style="border:none;padding:0;box-shadow:none" placeholder="https://...">
                                </div>
                            </div>

                            <!-- Facebook -->
                            <div class="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:border-[#1877F2]/30">
                                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-[#1877F2]/10">
                                    <svg class="h-5 w-5 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-muted">Facebook</label>
                                    <input type="url" data-field="facebook_url" class="input border-0 p-0 focus:ring-0" style="border:none;padding:0;box-shadow:none" placeholder="https://facebook.com/numele-tau">
                                </div>
                            </div>

                            <!-- Instagram -->
                            <div class="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:border-[#E1306C]/30">
                                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#FFDC80] via-[#E1306C] to-[#5B51DB]">
                                    <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-muted">Instagram</label>
                                    <input type="url" data-field="instagram_url" class="input border-0 p-0 focus:ring-0" style="border:none;padding:0;box-shadow:none" placeholder="https://instagram.com/numele-tau">
                                </div>
                            </div>

                            <!-- TikTok -->
                            <div class="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:border-black/30">
                                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-black">
                                    <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-muted">TikTok</label>
                                    <input type="url" data-field="tiktok_url" class="input border-0 p-0 focus:ring-0" style="border:none;padding:0;box-shadow:none" placeholder="https://tiktok.com/@numele-tau">
                                </div>
                            </div>

                            <!-- YouTube -->
                            <div class="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:border-[#FF0000]/30">
                                <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-[#FF0000]/10">
                                    <svg class="h-5 w-5 text-[#FF0000]" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-1 block text-xs font-medium uppercase tracking-wider text-muted">YouTube</label>
                                    <input type="url" data-field="youtube_url" class="input border-0 p-0 focus:ring-0" style="border:none;padding:0;box-shadow:none" placeholder="https://youtube.com/@numele-tau">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Refresh stats -->
                    <div class="rounded-2xl border-2 border-dashed border-primary/30 bg-primary/5 p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                    <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-secondary">Sincronizează statisticile</h3>
                                    <p class="text-sm text-muted">Calculăm totaluri pentru abonați, vizualizări și fani de pe Spotify, YouTube, Facebook, Instagram, TikTok pe baza ID-urilor de mai sus.</p>
                                    <p id="last-stats-refresh" class="mt-1 text-xs text-muted"></p>
                                </div>
                            </div>
                            <button type="button" id="refresh-stats-btn" class="btn btn-primary self-start lg:self-auto">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Actualizează acum
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================== TAB: BOOKING & CONTACT ============================== -->
                <div data-tab="booking" class="tab-section hidden space-y-6">
                    <!-- Public contact -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Contact public</h2>
                        <p class="mb-6 text-sm text-muted">Vizibil pe pagina ta dedicată.</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Email contact</label>
                                <input type="email" data-field="email" class="input" placeholder="contact@artist.ro">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="phone" class="input">
                            </div>
                        </div>
                    </div>

                    <!-- Manager -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Manager</h2>
                        <p class="mb-6 text-sm text-muted">Persoana de contact pentru solicitări de booking.</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Prenume</label>
                                <input type="text" data-field="manager_first_name" maxlength="100" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Nume</label>
                                <input type="text" data-field="manager_last_name" maxlength="100" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Email</label>
                                <input type="email" data-field="manager_email" maxlength="190" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="manager_phone" maxlength="64" class="input">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-secondary">Website</label>
                                <input type="url" data-field="manager_website" maxlength="255" class="input">
                            </div>
                        </div>
                    </div>

                    <!-- Agent -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-6 text-lg font-bold text-secondary">Agent booking</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Prenume</label>
                                <input type="text" data-field="agent_first_name" maxlength="100" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Nume</label>
                                <input type="text" data-field="agent_last_name" maxlength="100" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Email</label>
                                <input type="email" data-field="agent_email" maxlength="190" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="agent_phone" maxlength="64" class="input">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-secondary">Website</label>
                                <input type="url" data-field="agent_website" maxlength="255" class="input">
                            </div>
                        </div>
                    </div>

                    <!-- Booking agency -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Agenție de booking</h2>
                        <p class="mb-6 text-sm text-muted">Dacă lucrezi prin intermediul unei agenții.</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Nume agenție</label>
                                <input type="text" data-field="booking_agency.name" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Email</label>
                                <input type="email" data-field="booking_agency.email" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="booking_agency.phone" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Website</label>
                                <input type="url" data-field="booking_agency.website" class="input">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-secondary">Servicii</label>
                                <div class="flex flex-wrap gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" data-service="booking" class="h-4 w-4 rounded text-primary">
                                        Booking
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" data-service="management" class="h-4 w-4 rounded text-primary">
                                        Management
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="checkbox" data-service="pr" class="h-4 w-4 rounded text-primary">
                                        PR
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarife -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Onorariu indicativ</h2>
                        <p class="mb-6 text-sm text-muted">Vizibil organizatorilor logați. Lasă necompletat dacă preferi să discuți direct.</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Concert — minim (RON)</label>
                                <input type="number" data-field="min_fee_concert" step="0.01" min="0" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Concert — maxim (RON)</label>
                                <input type="number" data-field="max_fee_concert" step="0.01" min="0" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Festival — minim (RON)</label>
                                <input type="number" data-field="min_fee_festival" step="0.01" min="0" class="input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Festival — maxim (RON)</label>
                                <input type="number" data-field="max_fee_festival" step="0.01" min="0" class="input">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky save bar -->
                <div class="sticky bottom-0 -mx-4 -mb-4 flex items-center justify-end gap-3 border-t border-border bg-white p-4 lg:-mx-0 lg:-mb-0 lg:rounded-2xl">
                    <button type="button" id="cancel-btn" class="btn btn-secondary">Anulează modificările</button>
                    <button type="submit" id="save-btn" class="btn btn-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Salvează modificările
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-detalii.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
