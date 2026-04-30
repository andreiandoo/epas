<?php
/**
 * Artist Account — Profile Editor (Etapa 5)
 *
 * 14 sections in a single form, navigated via a left-side tab list. The
 * full Artist record is fetched once on load (GET /artist/profile) and
 * the form is populated; the user makes changes across as many tabs as
 * they want and clicks "Salvează" once at the bottom (single PUT).
 *
 * Image uploads are out-of-band: the file picker POSTs immediately to
 * /artist/profile/image and the returned path is staged into the form.
 * The path is only persisted on the artist record when the global Save
 * runs, which means cancelled previews don't leak.
 *
 * Bio_html is plain textarea per locale — server-side HtmlSanitizer
 * scrubs anything dangerous on PUT.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$pageTitle = 'Cont Artist — Detalii';
$bodyClass = 'min-h-screen bg-surface';
$cssBundle = 'account';
require_once dirname(__DIR__, 2) . '/includes/head.php';
?>

<div class="flex min-h-screen">
    <?php require __DIR__ . '/_partials/sidebar.php'; ?>
    <div class="flex flex-col flex-1 min-w-0">
        <?php require __DIR__ . '/_partials/header.php'; ?>

        <main class="flex-1 p-6 lg:p-10">
            <div class="max-w-6xl mx-auto">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-secondary">Detalii profil</h1>
                        <p class="mt-1 text-muted">Editează informațiile publice ale profilului tău de artist.</p>
                    </div>
                    <span id="dirty-indicator" class="hidden px-3 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-700">Modificări nesalvate</span>
                </div>

                <!-- Loading state -->
                <div id="detalii-loading" class="p-12 text-center bg-white border rounded-2xl border-border">
                    <div class="w-12 h-12 mx-auto mb-4 border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                    <p class="text-muted">Se încarcă datele profilului…</p>
                </div>

                <!-- Error state (no linked profile) -->
                <div id="detalii-unlinked" class="hidden p-8 border-2 rounded-2xl border-amber-200 bg-amber-50">
                    <div class="flex items-start gap-3">
                        <svg class="flex-shrink-0 w-6 h-6 mt-0.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-amber-900">Profilul nu este încă asociat</h3>
                            <p class="mt-1 text-sm text-amber-800">Echipa nu a linkat încă un profil de artist contului tău. Te vom contacta prin email când e gata.</p>
                        </div>
                    </div>
                </div>

                <!-- Editor (shown after data loads) -->
                <div id="detalii-editor" class="hidden grid lg:grid-cols-[260px_1fr] gap-6">
                    <!-- Tab nav (sidebar within page) -->
                    <aside class="lg:sticky lg:self-start lg:top-6">
                        <nav class="p-3 bg-white border rounded-2xl border-border">
                            <ul id="tab-nav" class="space-y-1">
                                <!-- Tabs populated by JS for clean state mgmt -->
                            </ul>
                        </nav>
                    </aside>

                    <!-- Form (every tab is a section, only one visible at a time) -->
                    <form id="detalii-form" class="space-y-6">
                        <!-- Identitate -->
                        <section data-tab="identitate" class="tab-section p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Identitate</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-medium text-secondary">Nume artist</label>
                                    <input type="text" data-field="name" maxlength="255" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">An de înființare</label>
                                    <input type="number" data-field="founded_year" min="1800" max="2100" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Membri</label>
                                    <input type="number" data-field="members_count" min="1" max="500" class="w-full input">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-medium text-secondary">Casă de discuri</label>
                                    <input type="text" data-field="record_label" maxlength="255" class="w-full input">
                                </div>
                            </div>
                        </section>

                        <!-- Media -->
                        <section data-tab="media" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Media</h2>
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <?php foreach ([
                                    'main_image_url' => ['Imagine principală', 'main', 'Folosită ca background pe pagina ta publică (1920×1080+).'],
                                    'logo_url' => ['Logo', 'logo', 'Folosit ca avatar (recomandat pătrat, 512×512).'],
                                    'portrait_url' => ['Portret', 'portrait', 'Imagine portret (3:4 sau 4:5).'],
                                ] as $field => [$label, $type, $hint]): ?>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary"><?= $label ?></label>
                                    <div class="image-uploader" data-field="<?= $field ?>" data-type="<?= $type ?>">
                                        <div class="preview hidden mb-2 overflow-hidden border rounded-lg aspect-square border-border">
                                            <img class="object-cover w-full h-full" alt="">
                                        </div>
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-secondary file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary-dark">
                                        <p class="mt-1 text-xs text-muted"><?= $hint ?></p>
                                        <button type="button" class="clear-btn hidden mt-2 text-xs text-red-600 hover:underline">Șterge</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <!-- Biografie -->
                        <section data-tab="biografie" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Biografie</h2>
                            <div class="space-y-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Română</label>
                                    <textarea data-field="bio_html.ro" rows="8" class="w-full input font-mono text-sm" placeholder="Spune povestea ta…"></textarea>
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Engleză</label>
                                    <textarea data-field="bio_html.en" rows="8" class="w-full input font-mono text-sm" placeholder="Tell your story…"></textarea>
                                </div>
                                <p class="text-xs text-muted">HTML simplu permis: &lt;b&gt;, &lt;i&gt;, &lt;p&gt;, &lt;br&gt;, &lt;a href&gt;.</p>
                            </div>
                        </section>

                        <!-- Achievements (repeater) -->
                        <section data-tab="achievements" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-secondary">Realizări</h2>
                                <button type="button" data-add="achievements" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                            </div>
                            <div data-repeater="achievements" class="space-y-3"></div>
                        </section>

                        <!-- Discografie (repeater) -->
                        <section data-tab="discografie" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-secondary">Discografie</h2>
                                <button type="button" data-add="discography" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                            </div>
                            <div data-repeater="discography" class="space-y-3"></div>
                        </section>

                        <!-- Locație -->
                        <section data-tab="locatie" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Locație</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Țară</label>
                                    <input type="text" data-field="country" maxlength="64" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Județ</label>
                                    <input type="text" data-field="state" maxlength="120" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Oraș</label>
                                    <input type="text" data-field="city" maxlength="120" class="w-full input">
                                </div>
                            </div>
                        </section>

                        <!-- Categorii -->
                        <section data-tab="categorii" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Categorii</h2>
                            <div class="space-y-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Tipuri de artist</label>
                                    <div data-multi="artist_types" class="flex flex-wrap gap-2 p-2 border rounded-lg border-border min-h-[48px]"></div>
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Genuri</label>
                                    <div data-multi="artist_genres" class="flex flex-wrap gap-2 p-2 border rounded-lg border-border min-h-[48px]"></div>
                                </div>
                            </div>
                        </section>

                        <!-- Social Media -->
                        <section data-tab="social" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Social Media & Link-uri</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <?php foreach ([
                                    'website' => 'Website',
                                    'facebook_url' => 'Facebook',
                                    'instagram_url' => 'Instagram',
                                    'tiktok_url' => 'TikTok',
                                    'youtube_url' => 'YouTube',
                                    'spotify_url' => 'Spotify',
                                    'spotify_id' => 'Spotify Artist ID',
                                    'youtube_id' => 'YouTube Channel ID',
                                ] as $field => $label): ?>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary"><?= $label ?></label>
                                    <input type="text" data-field="<?= $field ?>" class="w-full input">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <!-- YouTube videos (repeater, max 5) -->
                        <section data-tab="videoclipuri" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-secondary">Videoclipuri YouTube</h2>
                                <button type="button" data-add="youtube_videos" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                            </div>
                            <p class="mb-3 text-xs text-muted">Maxim 5 videoclipuri.</p>
                            <div data-repeater="youtube_videos" class="space-y-3"></div>
                        </section>

                        <!-- Tarife -->
                        <section data-tab="tarife" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Tarife (RON)</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Concert — minim</label>
                                    <input type="number" data-field="min_fee_concert" step="0.01" min="0" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Concert — maxim</label>
                                    <input type="number" data-field="max_fee_concert" step="0.01" min="0" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Festival — minim</label>
                                    <input type="number" data-field="min_fee_festival" step="0.01" min="0" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Festival — maxim</label>
                                    <input type="number" data-field="max_fee_festival" step="0.01" min="0" class="w-full input">
                                </div>
                            </div>
                        </section>

                        <!-- Contact -->
                        <section data-tab="contact" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Contact public</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Email</label>
                                    <input type="email" data-field="email" class="w-full input">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-secondary">Telefon</label>
                                    <input type="tel" data-field="phone" class="w-full input">
                                </div>
                            </div>
                        </section>

                        <!-- Manager -->
                        <section data-tab="manager" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Manager</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Prenume</label><input type="text" data-field="manager_first_name" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Nume</label><input type="text" data-field="manager_last_name" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Email</label><input type="email" data-field="manager_email" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Telefon</label><input type="tel" data-field="manager_phone" class="w-full input"></div>
                                <div class="md:col-span-2"><label class="block mb-2 text-sm font-medium text-secondary">Website</label><input type="text" data-field="manager_website" class="w-full input"></div>
                            </div>
                        </section>

                        <!-- Agent -->
                        <section data-tab="agent" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Agent booking</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Prenume</label><input type="text" data-field="agent_first_name" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Nume</label><input type="text" data-field="agent_last_name" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Email</label><input type="email" data-field="agent_email" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Telefon</label><input type="tel" data-field="agent_phone" class="w-full input"></div>
                                <div class="md:col-span-2"><label class="block mb-2 text-sm font-medium text-secondary">Website</label><input type="text" data-field="agent_website" class="w-full input"></div>
                            </div>
                        </section>

                        <!-- Agenție booking -->
                        <section data-tab="agentie" class="tab-section hidden p-6 bg-white border rounded-2xl border-border">
                            <h2 class="mb-4 text-lg font-semibold text-secondary">Agenție de booking</h2>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Nume agenție</label><input type="text" data-field="booking_agency.name" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Email</label><input type="email" data-field="booking_agency.email" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Telefon</label><input type="tel" data-field="booking_agency.phone" class="w-full input"></div>
                                <div><label class="block mb-2 text-sm font-medium text-secondary">Website</label><input type="text" data-field="booking_agency.website" class="w-full input"></div>
                                <div class="md:col-span-2">
                                    <label class="block mb-2 text-sm font-medium text-secondary">Servicii</label>
                                    <div class="flex flex-wrap gap-3">
                                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" data-service="booking" class="w-4 h-4 rounded text-primary"> Booking</label>
                                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" data-service="management" class="w-4 h-4 rounded text-primary"> Management</label>
                                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" data-service="pr" class="w-4 h-4 rounded text-primary"> PR</label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Sticky save bar -->
                        <div class="sticky bottom-0 -mx-6 -mb-6 lg:mx-0 lg:mb-0 lg:rounded-2xl flex items-center justify-end gap-3 p-4 border-t bg-white border-border">
                            <button type="button" id="cancel-btn" class="btn">Anulează modificările</button>
                            <button type="submit" id="save-btn" class="text-white btn bg-primary btn-primary">Salvează</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
$scriptsExtra = ''
    . '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>'
    . '<script defer src="' . asset('assets/js/pages/artist-cont-detalii.js') . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
