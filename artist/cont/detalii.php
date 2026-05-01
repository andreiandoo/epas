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
                                <input type="text" data-field="name" required maxlength="255" class="form-input" placeholder="Ex: The Rockers">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Casă de discuri</label>
                                <input type="text" data-field="record_label" maxlength="255" class="form-input" placeholder="Ex: Universal Music">
                            </div>
                        </div>

                        <div class="mb-4 grid gap-4 lg:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">An înființare</label>
                                <input type="number" data-field="founded_year" min="1800" max="2100" class="form-input" placeholder="2018">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Membri</label>
                                <input type="number" data-field="members_count" min="1" max="500" class="form-input">
                            </div>
                        </div>
                    </div>

                    <!-- Categorii (multi-pills) -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Categorii</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Tipuri de artist</label>
                                <div data-multi="artist_types" class="flex min-h-[48px] flex-wrap gap-2 rounded-lg border border-border p-2"></div>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Genuri</label>
                                <div data-multi="artist_genres" class="flex min-h-[48px] flex-wrap gap-2 rounded-lg border border-border p-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Locație -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Locație</h2>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Țară</label>
                                <input type="text" data-field="country" maxlength="64" class="form-input" placeholder="România">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Județ</label>
                                <input type="text" data-field="state" maxlength="120" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Oraș</label>
                                <input type="text" data-field="city" maxlength="120" class="form-input" placeholder="București">
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
                            <textarea data-field="bio_html.ro" data-rich-editor rows="8" class="form-input" placeholder="Spune povestea ta…"></textarea>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-secondary">Engleză</label>
                            <textarea data-field="bio_html.en" data-rich-editor rows="8" class="form-input" placeholder="Tell your story…"></textarea>
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
                                <p class="text-sm text-muted">Albume, EP-uri, single-uri. Maxim 50.</p>
                            </div>
                            <button type="button" data-add="discography" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                        </div>
                        <div data-repeater="discography" class="space-y-3"></div>
                    </div>

                    <!-- Spotify -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Muzică pe Spotify</h2>
                        <p class="mb-4 text-sm text-muted">Lipește un link Spotify (artist sau album) pentru a fi afișat pe profilul tău.</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">URL Spotify</label>
                                <input type="url" data-field="spotify_url" class="form-input" placeholder="https://open.spotify.com/artist/...">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Spotify Artist ID</label>
                                <input type="text" data-field="spotify_id" maxlength="64" class="form-input" placeholder="opțional">
                            </div>
                        </div>
                    </div>

                    <!-- YouTube videos -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Videoclipuri YouTube</h2>
                                <p class="text-sm text-muted">Maxim 5 videoclipuri.</p>
                            </div>
                            <button type="button" data-add="youtube_videos" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                        </div>
                        <div class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-secondary">YouTube Channel ID (opțional)</label>
                            <input type="text" data-field="youtube_id" maxlength="64" class="form-input">
                        </div>
                        <div data-repeater="youtube_videos" class="space-y-3"></div>
                    </div>
                </div>

                <!-- ============================== TAB: SOCIAL MEDIA ============================== -->
                <div data-tab="social" class="tab-section hidden">
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-2 text-lg font-bold text-secondary">Social Media &amp; Web</h2>
                        <p class="mb-6 text-sm text-muted">Link-urile tale apar ca iconițe pe profilul public.</p>

                        <div class="space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Website oficial</label>
                                <input type="url" data-field="website" class="form-input" placeholder="https://...">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Facebook</label>
                                <input type="url" data-field="facebook_url" class="form-input" placeholder="https://facebook.com/...">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Instagram</label>
                                <input type="url" data-field="instagram_url" class="form-input" placeholder="https://instagram.com/...">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">TikTok</label>
                                <input type="url" data-field="tiktok_url" class="form-input" placeholder="https://tiktok.com/@...">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">YouTube</label>
                                <input type="url" data-field="youtube_url" class="form-input" placeholder="https://youtube.com/@...">
                            </div>
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
                                <input type="email" data-field="email" class="form-input" placeholder="contact@artist.ro">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="phone" class="form-input">
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
                                <input type="text" data-field="manager_first_name" maxlength="100" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Nume</label>
                                <input type="text" data-field="manager_last_name" maxlength="100" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Email</label>
                                <input type="email" data-field="manager_email" maxlength="190" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="manager_phone" maxlength="64" class="form-input">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-secondary">Website</label>
                                <input type="url" data-field="manager_website" maxlength="255" class="form-input">
                            </div>
                        </div>
                    </div>

                    <!-- Agent -->
                    <div class="rounded-2xl border border-border bg-white p-6">
                        <h2 class="mb-6 text-lg font-bold text-secondary">Agent booking</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Prenume</label>
                                <input type="text" data-field="agent_first_name" maxlength="100" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Nume</label>
                                <input type="text" data-field="agent_last_name" maxlength="100" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Email</label>
                                <input type="email" data-field="agent_email" maxlength="190" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="agent_phone" maxlength="64" class="form-input">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-secondary">Website</label>
                                <input type="url" data-field="agent_website" maxlength="255" class="form-input">
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
                                <input type="text" data-field="booking_agency.name" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Email</label>
                                <input type="email" data-field="booking_agency.email" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Telefon</label>
                                <input type="tel" data-field="booking_agency.phone" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Website</label>
                                <input type="url" data-field="booking_agency.website" class="form-input">
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
                                <input type="number" data-field="min_fee_concert" step="0.01" min="0" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Concert — maxim (RON)</label>
                                <input type="number" data-field="max_fee_concert" step="0.01" min="0" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Festival — minim (RON)</label>
                                <input type="number" data-field="min_fee_festival" step="0.01" min="0" class="form-input">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-secondary">Festival — maxim (RON)</label>
                                <input type="number" data-field="max_fee_festival" step="0.01" min="0" class="form-input">
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
