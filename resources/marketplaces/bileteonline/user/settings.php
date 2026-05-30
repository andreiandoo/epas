<?php
/**
 * bilete.online — /cont/setari (Setări cont, v2 design — 7 tab-uri)
 *
 * Tabs (matches template-client-settings-bilete-online-v2.html):
 *   1. Date personale  — prenume/nume/email/telefon/oraș/data nașterii/gen
 *                        → PUT /customer/profile
 *   2. Securitate      — schimbă parolă (PUT /customer/password) + verifică email
 *                        (POST /customer/resend-verification). 2FA + sesiuni = în curând.
 *   3. Preferințe      — categorii + orașe (chips) + lifestyle (rază/buget/frecvență/moment)
 *                        → PUT /customer/settings (interests.event_categories, interests.preferred_cities,
 *                        interests.lifestyle.{radius,budget,frequency,moment}).
 *                        Oraș principal = customer.city (sync separat via /customer/profile).
 *   4. Familie         — UI prezent; persistență = "în curând"
 *   5. Notificări      — 6 toggle-uri (tickets, points, recommendations, newsletter,
 *                        reviews, support) → settings.notification_preferences.*
 *   6. Plăți           — "în curând" (necesită integrare cu provider plăți)
 *   7. Privacy / GDPR  — Personalizare (settings.personalization_enabled), Ștergere cont,
 *                        Export = "în curând", Tracking marketing = link /cookies.
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Setări cont — ' . SITE_NAME;
$pageDescription = 'Gestionează datele tale de contact, parola, preferințele pentru recomandări, profilul familiei, notificările, plățile și opțiunile de confidențialitate.';
$canonicalUrl    = SITE_URL . '/cont/setari';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'settings'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientSettingsPage()" x-init="init()">

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_390px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">ACCOUNT SETTINGS</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Setări cont</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">Administrează datele personale, securitatea, preferințele pentru recomandări, notificările, plățile și opțiunile de confidențialitate.</p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <button @click="activeTab='preferences'" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Completează preferințe</button>
                            <button @click="activeTab='privacy'" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Privacy &amp; GDPR</button>
                        </div>
                    </div>
                    <div class="rounded-[2rem] border-2 border-paper/20 bg-paper text-ink p-6 rotate-[-2deg]">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PROFILE COMPLETION</p>
                        <p class="mt-3 font-display text-7xl font-bold leading-none"><span x-text="profileCompletion">0</span>%</p>
                        <p class="mt-2 text-ink-soft">profil complet pentru recomandări</p>
                        <div class="mt-5 h-4 rounded-full bg-paper-2 border border-ink/10 overflow-hidden">
                            <div class="h-full bg-vermilion rounded-full transition-all" :style="'width:' + profileCompletion + '%'"></div>
                        </div>
                        <p class="mt-3 text-sm text-ink-soft" x-text="missingHint || 'Toate câmpurile esențiale sunt completate.'"></p>
                    </div>
                </div>
            </section>

            <!-- 4 STAT CARDS -->
            <section class="mt-6 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SECURITATE</p>
                    <p class="mt-3 font-display text-5xl font-bold" x-text="emailVerified ? 'bună' : 'medie'">bună</p>
                    <p class="mt-1 text-ink-soft" x-text="emailVerified ? 'email verificat' : 'verifică email-ul'">email verificat</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">PREFERINȚE</p>
                    <p class="mt-3 font-display text-5xl font-bold"><span x-text="profileCompletion">0</span>%</p>
                    <p class="mt-1 text-ink-soft">pentru recomandări</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">NOTIFICĂRI</p>
                    <p class="mt-3 font-display text-5xl font-bold" x-text="activeNotifications + '/' + totalNotifications">0/6</p>
                    <p class="mt-1 text-ink-soft">email + push</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">GDPR</p>
                    <p class="mt-3 font-display text-5xl font-bold">control</p>
                    <p class="mt-1 text-ink-soft">export / ștergere</p>
                </article>
            </section>

            <!-- TAB BAR -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-4 sm:p-5 shadow-ticket">
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <template x-for="tab in tabs" :key="tab.key">
                        <button @click="activeTab = tab.key" :class="activeTab === tab.key ? 'bg-ink text-paper' : 'bg-paper-2 text-ink hover:bg-ink/5'" class="shrink-0 rounded-full px-5 py-3 font-bold transition" x-text="tab.label"></button>
                    </template>
                </div>
            </section>

            <!-- Save banner -->
            <div x-show="message" x-cloak class="mt-5 rounded-2xl border-2 px-4 py-3 text-sm font-bold" :class="messageType === 'error' ? 'border-vermilion bg-vermilion/10 text-vermilion' : 'border-forest bg-mint text-forest'" x-text="message"></div>

            <!-- ===================== 1. DATE PERSONALE ===================== -->
            <section x-show="activeTab === 'personal'" class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">DATE PERSONALE</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Cine ești și cum te contactăm</h2>
                <p class="mt-3 text-ink-soft max-w-3xl">Aceste informații apar pe bilete, pe email-urile de confirmare și pe facturi.</p>

                <form @submit.prevent="saveProfile()" class="mt-6 grid md:grid-cols-2 gap-4">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Prenume</span>
                        <input class="field" x-model="profile.first_name" autocomplete="given-name" required>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Nume</span>
                        <input class="field" x-model="profile.last_name" autocomplete="family-name" required>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Email</span>
                        <input class="field bg-paper-2/60" type="email" x-model="profile.email" autocomplete="email" disabled title="Email-ul nu poate fi schimbat de pe această pagină. Contactează-ne dacă ai nevoie de actualizare.">
                        <span class="mt-1 inline-flex items-center gap-1 text-xs"
                              :class="emailVerified ? 'text-forest' : 'text-vermilion'">
                            <span x-show="emailVerified">✓ Email verificat</span>
                            <span x-show="!emailVerified">⚠ Email neverificat</span>
                        </span>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Telefon</span>
                        <input class="field" type="tel" x-model="profile.phone" autocomplete="tel" placeholder="0722 123 456">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Oraș principal</span>
                        <input class="field" x-model="profile.city" placeholder="București, Cluj-Napoca, Brașov…">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Data nașterii</span>
                        <input class="field" type="date" x-model="profile.birth_date" :max="todayIso()">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Gen (opțional)</span>
                        <select class="field" x-model="profile.gender">
                            <option value="">— alege —</option>
                            <option value="female">Femeie</option>
                            <option value="male">Bărbat</option>
                            <option value="other">Altul</option>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Adresă (opțional)</span>
                        <input class="field" x-model="profile.address" autocomplete="street-address" placeholder="Strada, număr, ap.">
                    </label>

                    <div class="md:col-span-2 flex flex-wrap gap-2 pt-2">
                        <button type="submit" :disabled="saving" class="rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                            <span x-show="!saving">Salvează datele</span>
                            <span x-show="saving" x-cloak>Se salvează…</span>
                        </button>
                        <button type="button" x-show="!emailVerified" @click="resendVerification()" :disabled="resendingVerify || resendCooldown > 0" class="rounded-full border-2 border-ink px-6 py-3 font-bold hover:bg-ink hover:text-paper transition disabled:opacity-60">
                            <span x-show="!resendingVerify && resendCooldown === 0">Trimite link verificare</span>
                            <span x-show="resendingVerify" x-cloak>Se trimite…</span>
                            <span x-show="resendCooldown > 0" x-cloak>Retrimite în <span x-text="resendCooldown"></span>s</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- ===================== 2. SECURITATE ===================== -->
            <section x-show="activeTab === 'security'" x-cloak class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SECURITATE</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Parolă, sesiuni și protecția contului</h2>

                <div class="mt-6 grid xl:grid-cols-[1fr_380px] gap-6">
                    <div class="space-y-4">
                        <!-- Schimbă parola -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <h3 class="font-display text-3xl font-bold">Schimbă parola</h3>
                            <form @submit.prevent="savePassword()" class="mt-4 grid gap-3">
                                <input class="field" type="password" placeholder="Parolă curentă" x-model="pw.current_password" autocomplete="current-password" required>
                                <div class="grid md:grid-cols-2 gap-3">
                                    <input class="field" type="password" placeholder="Parolă nouă" x-model="pw.password" autocomplete="new-password" minlength="8" required>
                                    <input class="field" type="password" placeholder="Confirmă parola nouă" x-model="pw.password_confirmation" autocomplete="new-password" minlength="8" required>
                                </div>
                                <button type="submit" :disabled="saving" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                    <span x-show="!saving">Actualizează parola</span>
                                    <span x-show="saving" x-cloak>Se salvează…</span>
                                </button>
                            </form>
                        </div>

                        <!-- 2FA — în curând -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5 flex items-center justify-between gap-4 opacity-70">
                            <div>
                                <h3 class="font-display text-3xl font-bold">Autentificare în doi pași</h3>
                                <p class="text-ink-soft">Recomandat pentru protecție suplimentară.</p>
                            </div>
                            <span class="rounded-full bg-ochre/20 border border-ochre/40 text-ochre px-3 py-1 text-xs font-mono tracking-wider">în curând</span>
                        </div>

                        <!-- Sesiuni active -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <h3 class="font-display text-3xl font-bold">Sesiuni active</h3>
                            <div class="mt-4 space-y-2">
                                <div class="rounded-2xl bg-paper p-4 flex justify-between gap-3">
                                    <span>
                                        <strong x-text="currentDeviceLabel">Browser · Device</strong><br>
                                        <span class="text-sm text-ink-soft">sesiunea curentă</span>
                                    </span>
                                    <span class="text-forest font-bold">curentă</span>
                                </div>
                                <p class="text-xs text-ink-soft mt-2">Lista completă de sesiuni active este <strong>în curând</strong>. Pentru a te deconecta de pe alt dispozitiv, folosește butonul de <strong>Deconectare totală</strong> de mai jos.</p>
                                <button @click="logoutEverywhere()" :disabled="saving" class="mt-2 rounded-full border-2 border-vermilion text-vermilion px-5 py-2 font-bold hover:bg-vermilion hover:text-paper transition disabled:opacity-60">
                                    <span x-show="!saving">Deconectare totală</span>
                                    <span x-show="saving" x-cloak>Se procesează…</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <aside class="rounded-3xl bg-mint border border-forest/20 p-6 self-start">
                        <p class="font-bold text-forest">Status securitate</p>
                        <p class="mt-2 text-ink-soft" x-text="emailVerified ? 'Email verificat. Parolă activă. Activează 2FA când va fi disponibil.' : 'Email-ul tău nu e verificat. Verifică-l ca să te poți recupera contul în cazul pierderii parolei.'"></p>
                    </aside>
                </div>
            </section>

            <!-- ===================== 3. PREFERINȚE ===================== -->
            <section x-show="activeTab === 'preferences'" x-cloak id="profil-preferinte" class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PREFERINȚE RECOMANDĂRI</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Ce fel de activități vrei să primești?</h2>
                <p class="mt-3 text-ink-soft max-w-3xl">Aceste câmpuri sunt cele mai importante pentru pagina de
                    <a href="/cont/recomandari" class="text-vermilion font-bold underline-wobble">Recomandări</a>.
                    Cu cât sunt mai clare, cu atât putem propune activități mai potrivite.
                </p>

                <div class="mt-6 grid xl:grid-cols-[1fr_360px] gap-6">
                    <div class="space-y-6">

                        <!-- Categorii preferate -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <h3 class="font-display text-3xl font-bold">Categorii preferate</h3>
                            <div x-show="loadingCategories" class="mt-3 h-10 rounded-2xl bg-paper/60 animate-pulse"></div>
                            <div x-show="!loadingCategories && availableCategories.length === 0" class="mt-3 text-sm text-ink-soft italic">Categoriile nu au putut fi încărcate.</div>
                            <div x-show="!loadingCategories && availableCategories.length > 0" class="mt-4 flex flex-wrap gap-2">
                                <template x-for="cat in availableCategories" :key="cat.slug">
                                    <button type="button" @click="toggleCategory(cat.slug)"
                                            :class="interests.event_categories.includes(cat.slug) ? 'bg-ink text-paper border-ink' : 'bg-paper border-ink/10 hover:border-ink'"
                                            class="rounded-full px-4 py-2 font-bold border-2 transition">
                                        <span x-text="cat.emoji + ' ' + cat.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Orașe de interes -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <h3 class="font-display text-3xl font-bold">Orașe de interes</h3>
                            <div class="mt-4 grid md:grid-cols-2 gap-4">
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Oraș principal</span>
                                    <input class="field" x-model="profile.city" list="cities-list-pref" placeholder="București, Cluj-Napoca…">
                                    <datalist id="cities-list-pref">
                                        <template x-for="c in availableCities" :key="c.slug || c.name">
                                            <option :value="c.name"></option>
                                        </template>
                                    </datalist>
                                    <span class="mt-1 block text-xs text-ink-soft">Folosit ca semnal principal pentru recomandări.</span>
                                </label>
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Rază recomandări</span>
                                    <select class="field" x-model="lifestyle.radius">
                                        <option value="">— alege —</option>
                                        <option value="city">Doar orașul meu</option>
                                        <option value="25km">+25 km</option>
                                        <option value="50km">+50 km</option>
                                        <option value="country">Toată țara</option>
                                    </select>
                                </label>
                            </div>
                            <p class="mt-4 mb-2 text-sm font-bold">Orașe secundare unde mergi des:</p>
                            <div x-show="loadingCities" class="h-10 rounded-2xl bg-paper/60 animate-pulse"></div>
                            <div x-show="!loadingCities" class="flex flex-wrap gap-2">
                                <template x-for="city in availableCities" :key="city.slug || city.name">
                                    <button type="button" @click="toggleCity(city.name)"
                                            :class="interests.preferred_cities.includes(city.name) ? 'bg-mint text-forest border-forest' : 'bg-paper border-ink/10 hover:border-ink'"
                                            class="rounded-full px-3 py-1.5 text-sm font-bold border-2 transition">
                                        <span x-text="city.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Buget și ritm -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <h3 class="font-display text-3xl font-bold">Buget și ritm</h3>
                            <div class="mt-4 grid md:grid-cols-3 gap-4">
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Buget per persoană</span>
                                    <select class="field" x-model="lifestyle.budget">
                                        <option value="">— alege —</option>
                                        <option value="under_50">sub 50 lei</option>
                                        <option value="50_120">50–120 lei</option>
                                        <option value="120_250">120–250 lei</option>
                                        <option value="250_plus">250+ lei</option>
                                    </select>
                                </label>
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Frecvență</span>
                                    <select class="field" x-model="lifestyle.frequency">
                                        <option value="">— alege —</option>
                                        <option value="spontaneous">spontan</option>
                                        <option value="monthly">2-3 ori / lună</option>
                                        <option value="weekly">săptămânal</option>
                                    </select>
                                </label>
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Tip moment</span>
                                    <select class="field" x-model="lifestyle.moment">
                                        <option value="">— alege —</option>
                                        <option value="weekend">weekend</option>
                                        <option value="afterwork">după job</option>
                                        <option value="vacations">vacanțe</option>
                                        <option value="anytime">oricând</option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>

                    <aside class="rounded-3xl bg-ink text-paper p-6 self-start">
                        <p class="font-mono text-xs tracking-[.18em] text-paper/45">RECOMMENDATION ENGINE</p>
                        <h3 class="mt-2 font-display text-4xl font-bold leading-none">Semnale utile</h3>
                        <div class="mt-5 space-y-2 text-paper/70 text-sm">
                            <p :class="interests.event_categories.length > 0 ? 'text-mint' : ''">• Categorii preferate <span x-show="interests.event_categories.length > 0" x-text="'('+interests.event_categories.length+')'"></span></p>
                            <p :class="(profile.city || interests.preferred_cities.length > 0) ? 'text-mint' : ''">• Orașe și rază</p>
                            <p :class="lifestyle.budget ? 'text-mint' : ''">• Buget</p>
                            <p :class="lifestyle.frequency ? 'text-mint' : ''">• Frecvență</p>
                            <p :class="lifestyle.moment ? 'text-mint' : ''">• Tip moment</p>
                            <p class="text-paper/40">• Istoric comenzi (automat)</p>
                        </div>
                        <button @click="savePreferences()" :disabled="saving" class="mt-6 w-full rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                            <span x-show="!saving">Salvează preferințele</span>
                            <span x-show="saving" x-cloak>Se salvează…</span>
                        </button>
                    </aside>
                </div>
            </section>

            <!-- ===================== 4. FAMILIE / BENEFICIARI ===================== -->
            <section x-show="activeTab === 'family'" x-cloak class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FAMILIE &amp; BENEFICIARI</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Beneficiari salvați și profil familie</h2>

                <div class="mt-6 grid xl:grid-cols-[1fr_380px] gap-6">
                    <div>
                        <div class="rounded-3xl border-2 border-dashed border-ink/20 bg-paper-2/50 p-8 text-center">
                            <p class="text-5xl">👨‍👩‍👧</p>
                            <p class="mt-3 font-display text-3xl font-bold">Lista de beneficiari vine în curând</p>
                            <p class="mt-2 text-ink-soft max-w-md mx-auto">Vei putea salva membri de familie (cu vârstă, alergii, preferințe) ca să poți cumpăra mai rapid bilete pentru ei și să primești recomandări mai bine țintite.</p>
                            <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-ochre/20 border border-ochre/40 text-ochre px-4 py-2 text-sm font-mono tracking-wider">în pregătire</p>
                        </div>
                    </div>
                    <aside class="rounded-3xl bg-mint border border-forest/20 p-6 self-start">
                        <p class="font-bold text-forest">De ce contează?</p>
                        <p class="mt-2 text-ink-soft">Beneficiarii frecvenți te scapă de retastarea numelor la checkout, iar profilul familiei (vârste, interese) ajută motorul de recomandări să-ți propună activități potrivite pentru toată echipa.</p>
                    </aside>
                </div>
            </section>

            <!-- ===================== 5. NOTIFICĂRI ===================== -->
            <section x-show="activeTab === 'notifications'" x-cloak class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">NOTIFICĂRI &amp; NEWSLETTER</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Ce vrei să primești?</h2>

                <div class="mt-6 grid xl:grid-cols-2 gap-4">
                    <template x-for="setting in notificationSettings" :key="setting.key">
                        <label class="rounded-3xl bg-paper-2 border border-ink/10 p-5 flex items-start justify-between gap-4 cursor-pointer hover:border-ink transition">
                            <span class="flex-1">
                                <span class="block font-display text-2xl font-bold" x-text="setting.title"></span>
                                <span class="mt-1 block text-ink-soft text-sm" x-text="setting.desc"></span>
                            </span>
                            <input type="checkbox" :checked="notif[setting.key]" @change="notif[setting.key] = $event.target.checked" class="mt-1 w-6 h-6 accent-vermilion shrink-0">
                        </label>
                    </template>
                </div>

                <div class="mt-6 rounded-3xl bg-rose border border-vermilion/20 p-5">
                    <p class="font-bold text-vermilion">Newsletter personalizat</p>
                    <p class="mt-1 text-ink-soft">Poți primi recomandări pe orașe, activități pentru copii, oferte, puncte care expiră și ghiduri editoriale. Dezabonarea e disponibilă în orice email.</p>
                </div>

                <button @click="saveNotifications()" :disabled="saving" class="mt-6 rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                    <span x-show="!saving">Salvează notificările</span>
                    <span x-show="saving" x-cloak>Se salvează…</span>
                </button>
            </section>

            <!-- ===================== 6. PLĂȚI ===================== -->
            <section x-show="activeTab === 'payments'" x-cloak class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PLĂȚI</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Metode de plată și facturare</h2>

                <div class="mt-6 grid xl:grid-cols-[1fr_360px] gap-6">
                    <div class="space-y-4">
                        <div class="rounded-3xl border-2 border-dashed border-ink/20 bg-paper-2/50 p-8 text-center">
                            <p class="text-5xl">💳</p>
                            <p class="mt-3 font-display text-3xl font-bold">Carduri salvate</p>
                            <p class="mt-2 text-ink-soft max-w-md mx-auto">Procesorul de plăți pentru bilete.online este în curs de integrare. Momentan, cardul se introduce la fiecare checkout. Salvarea cardului vine curând.</p>
                            <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-ochre/20 border border-ochre/40 text-ochre px-4 py-2 text-sm font-mono tracking-wider">în integrare</p>
                        </div>
                    </div>
                    <aside class="rounded-3xl bg-paper-2 border border-ink/10 p-6">
                        <p class="font-bold">Date facturare</p>
                        <p class="mt-1 text-sm text-ink-soft">Salvăm datele de facturare cu profilul tău, pe baza câmpurilor din tab-ul „Date personale" (nume + adresă).</p>
                        <a @click.prevent="activeTab='personal'" href="#" class="mt-4 inline-flex rounded-full border-2 border-ink px-4 py-2 font-bold text-sm hover:bg-ink hover:text-paper transition">Mergi la datele personale</a>
                    </aside>
                </div>
            </section>

            <!-- ===================== 7. PRIVACY / GDPR ===================== -->
            <section x-show="activeTab === 'privacy'" x-cloak class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PRIVACY &amp; GDPR</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Controlezi datele tale</h2>

                <div class="mt-6 grid xl:grid-cols-2 gap-5">

                    <!-- Export -->
                    <article class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                        <h3 class="font-display text-3xl font-bold">Export date personale</h3>
                        <p class="mt-2 text-ink-soft">Descarcă o copie cu datele de cont, comenzile, biletele, preferințele și punctele.</p>
                        <button @click="requestExport()" class="mt-4 rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Solicită export</button>
                    </article>

                    <!-- Personalizare -->
                    <article class="rounded-3xl bg-mint border border-forest/20 p-5">
                        <h3 class="font-display text-3xl font-bold">Personalizare recomandări</h3>
                        <p class="mt-2 text-ink-soft">Permite folosirea istoricului, recenziilor și preferințelor pentru recomandări mai relevante.</p>
                        <label class="mt-4 inline-flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" x-model="personalizationEnabled" @change="savePersonalization()" class="w-6 h-6 accent-forest">
                            <span class="font-bold" x-text="personalizationEnabled ? 'Activ' : 'Inactiv'"></span>
                        </label>
                    </article>

                    <!-- Tracking marketing -->
                    <article class="rounded-3xl bg-rose border border-vermilion/20 p-5">
                        <h3 class="font-display text-3xl font-bold">Tracking marketing</h3>
                        <p class="mt-2 text-ink-soft">Controlează consimțământul pentru pixeli, analytics și campanii personalizate.</p>
                        <a href="/cookies" class="mt-4 inline-flex rounded-full border border-vermilion/30 text-vermilion px-5 py-3 font-bold hover:bg-vermilion hover:text-paper transition">Setări cookies</a>
                    </article>

                    <!-- Ștergere cont -->
                    <article class="rounded-3xl border-2 border-vermilion bg-paper p-5">
                        <h3 class="font-display text-3xl font-bold text-vermilion">Ștergere cont</h3>
                        <p class="mt-2 text-ink-soft">Datele personale vor fi anonimizate. Comenzile rămân în istoricul fiscal conform obligațiilor legale. Nu poți șterge contul dacă ai bilete viitoare neutilizate.</p>
                        <form @submit.prevent="confirmDelete()" class="mt-4 grid gap-3">
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Parola curentă</span>
                                <input class="field" type="password" x-model="del.password" required>
                            </label>
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Motivul ștergerii (opțional)</span>
                                <textarea class="field min-h-24" x-model="del.reason"></textarea>
                            </label>
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" x-model="del.confirmed" class="mt-1 w-5 h-5 accent-vermilion" required>
                                <span>Înțeleg că datele mele vor fi anonimizate și că această acțiune este definitivă.</span>
                            </label>
                            <button type="submit" :disabled="!del.confirmed || saving" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-40">
                                <span x-show="!saving">Șterge contul</span>
                                <span x-show="saving" x-cloak>Se șterge…</span>
                            </button>
                        </form>
                    </article>
                </div>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/setari" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientSettingsPage() {
    return {
        // --- UI state ---
        loading: true,
        isAuth: true,
        saving: false,
        message: '',
        messageType: 'success',
        activeTab: 'personal',
        tabs: [
            { key: 'personal',      label: 'Date personale' },
            { key: 'security',      label: 'Securitate' },
            { key: 'preferences',   label: 'Preferințe' },
            { key: 'family',        label: 'Familie' },
            { key: 'notifications', label: 'Notificări' },
            { key: 'payments',      label: 'Plăți' },
            { key: 'privacy',       label: 'Privacy / GDPR' },
        ],

        // --- Profile (PUT /customer/profile) ---
        profile: {
            first_name: '',
            last_name:  '',
            email:      '',
            phone:      '',
            birth_date: '',
            gender:     '',
            city:       '',
            address:    '',
        },
        emailVerified: false,

        // --- Password ---
        pw: { current_password: '', password: '', password_confirmation: '' },
        resendingVerify: false,
        resendCooldown: 0,

        // --- Preferences ---
        interests: { preferred_cities: [], event_categories: [] },
        lifestyle: { radius: '', budget: '', frequency: '', moment: '' },
        availableCities: [],
        availableCategories: [],
        loadingCities: true,
        loadingCategories: true,

        // --- Notifications ---
        notif: {
            tickets:         true,
            points:          true,
            recommendations: true,
            newsletter:      false,
            reviews:         true,
            support:         true,
        },
        notificationSettings: [
            { key: 'tickets',         title: 'Bilete și comenzi', desc: 'confirmări, QR, modificări, remindere înainte de activitate' },
            { key: 'points',          title: 'Puncte bonus',      desc: 'puncte câștigate, puncte care expiră, campanii loyalty' },
            { key: 'recommendations', title: 'Recomandări',       desc: 'activități potrivite după profil, oraș și istoric' },
            { key: 'newsletter',      title: 'Newsletter',        desc: 'ghiduri, oferte, activități noi și idei de weekend' },
            { key: 'reviews',         title: 'Recenzii',          desc: 'remindere pentru activități evaluate și status moderare' },
            { key: 'support',         title: 'Support',           desc: 'răspunsuri la tichete și actualizări de retur' },
        ],

        // --- Privacy ---
        personalizationEnabled: true,

        // --- Delete ---
        del: { password: '', reason: '', confirmed: false },

        // --- Derived ---
        get currentDeviceLabel() {
            try {
                const ua = navigator.userAgent || '';
                let browser = 'Browser';
                if (/Edg/i.test(ua)) browser = 'Edge';
                else if (/Chrome/i.test(ua) && !/Edg/i.test(ua)) browser = 'Chrome';
                else if (/Safari/i.test(ua) && !/Chrome/i.test(ua)) browser = 'Safari';
                else if (/Firefox/i.test(ua)) browser = 'Firefox';
                let os = 'Device';
                if (/Windows/i.test(ua)) os = 'Windows';
                else if (/Macintosh|Mac OS/i.test(ua)) os = 'macOS';
                else if (/iPhone|iPad/i.test(ua)) os = 'iOS';
                else if (/Android/i.test(ua)) os = 'Android';
                else if (/Linux/i.test(ua)) os = 'Linux';
                return browser + ' · ' + os;
            } catch (e) { return 'Browser · Device'; }
        },

        get profileCompletion() {
            // Mirrors backend calculateProfileCompletion, but client-side so the
            // hero updates live as the user types. Fields: first_name, last_name,
            // phone, birth_date, gender, city, state, interests.
            const fields = [
                !!this.profile.first_name,
                !!this.profile.last_name,
                !!this.profile.phone,
                !!this.profile.birth_date,
                !!this.profile.gender,
                !!this.profile.city,
                !!this.profile.address,
                this.interests.event_categories.length > 0,
            ];
            const completed = fields.filter(Boolean).length;
            return Math.round((completed / fields.length) * 100);
        },

        get missingHint() {
            const missing = [];
            if (! this.profile.birth_date) missing.push('data nașterii');
            if (! this.profile.city) missing.push('oraș principal');
            if (this.interests.event_categories.length === 0) missing.push('categorii preferate');
            if (! this.profile.phone) missing.push('telefon');
            if (missing.length === 0) return '';
            return 'Mai completează: ' + missing.slice(0, 3).join(', ') + '.';
        },

        get activeNotifications() {
            return Object.values(this.notif).filter(Boolean).length;
        },
        get totalNotifications() { return this.notificationSettings.length; },

        // --- Lifecycle ---
        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); }
            catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; return; }

            this.load();
            this.loadCities();
            this.loadCategories();

            // Jump to specific tab via hash (#profil-preferinte, #securitate, etc.)
            const h = (location.hash || '').replace('#', '').toLowerCase();
            if (h.includes('preferinte') || h.includes('preferences')) this.activeTab = 'preferences';
            else if (h.includes('securitate') || h.includes('security') || h.includes('parol')) this.activeTab = 'security';
            else if (h.includes('familie') || h.includes('family')) this.activeTab = 'family';
            else if (h.includes('notific')) this.activeTab = 'notifications';
            else if (h.includes('plat') || h.includes('payment')) this.activeTab = 'payments';
            else if (h.includes('privacy') || h.includes('gdpr') || h.includes('danger') || h.includes('sterg')) this.activeTab = 'privacy';
        },

        todayIso() {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        },

        flash(msg, type) {
            this.message = msg;
            this.messageType = type || 'success';
            clearTimeout(this._flashTimer);
            this._flashTimer = setTimeout(() => { this.message = ''; }, 4500);
        },

        // --- Load customer data ---
        async load() {
            try {
                const r = await BileteOnlineAPI.customer.getProfile();
                // Backend response shape: { success:true, data:{ customer:{...} } }
                // — but we also defend against flat-data shape just in case.
                const root = (r && r.data) || {};
                const u = root.customer || root;

                this.profile = {
                    first_name: u.first_name || '',
                    last_name:  u.last_name  || '',
                    email:      u.email      || '',
                    phone:      u.phone      || '',
                    birth_date: u.birth_date || '',
                    gender:     u.gender     || '',
                    city:       u.city       || '',
                    address:    u.address    || '',
                };
                this.emailVerified = !!u.email_verified;

                const s = u.settings || u.preferences || {};

                // Notifications — defensive: read from notification_preferences first,
                // fall back to flat email_* keys we wrote in earlier builds.
                const np = s.notification_preferences || {};
                this.notif = {
                    tickets:         np.tickets         ?? np.reminders      ?? s.email_reminders       ?? true,
                    points:          np.points          ?? true,
                    recommendations: np.recommendations ?? s.email_recommendations ?? true,
                    newsletter:      np.newsletter      ?? s.email_newsletter ?? s.newsletter           ?? false,
                    reviews:         np.reviews         ?? true,
                    support:         np.support         ?? true,
                };

                // Taste profile — interests nested under settings.interests
                const it = s.interests || {};
                this.interests = {
                    preferred_cities: Array.isArray(it.preferred_cities) ? it.preferred_cities.slice(0, 30) : [],
                    event_categories: Array.isArray(it.event_categories) ? it.event_categories.slice(0, 30) : [],
                };
                const ls = it.lifestyle || {};
                this.lifestyle = {
                    radius:    ls.radius    || '',
                    budget:    ls.budget    || '',
                    frequency: ls.frequency || '',
                    moment:    ls.moment    || '',
                };

                // Personalization opt-in (defaults to true when undefined)
                this.personalizationEnabled = (typeof s.personalization_enabled === 'boolean') ? s.personalization_enabled : true;
            } catch (e) {
                console.warn('settings load failed', e);
            }
            this.loading = false;
        },

        async loadCities() {
            const fallback = [
                'București', 'Cluj-Napoca', 'Brașov', 'Timișoara', 'Iași', 'Constanța',
                'Sibiu', 'Oradea', 'Craiova', 'Galați', 'Ploiești', 'Bacău', 'Pitești',
                'Arad', 'Târgu Mureș', 'Baia Mare', 'Suceava', 'Râmnicu Vâlcea',
            ];
            try {
                const r = await BileteOnlineAPI.get('/cities', { limit: 60 });
                const rows = (r && (r.data?.cities || r.data || [])) || [];
                if (Array.isArray(rows) && rows.length > 0) {
                    this.availableCities = rows.map(c => ({
                        name: c.name || c.label || c.city || String(c),
                        slug: c.slug || (c.name || '').toLowerCase(),
                    })).filter(c => c.name);
                } else {
                    this.availableCities = fallback.map(n => ({ name: n, slug: n.toLowerCase() }));
                }
            } catch (e) {
                this.availableCities = fallback.map(n => ({ name: n, slug: n.toLowerCase() }));
            }
            this.loadingCities = false;
        },

        async loadCategories() {
            const emojiMap = {
                'escape-rooms':                '🔐',
                'muzee-expozitii':             '🏛️',
                'parcuri-de-distractii':       '🎢',
                'parcuri-de-aventura':         '🌲',
                'acvarii-zoo-animale':         '🐠',
                'ateliere-experiente-creative':'🎨',
                'spa-wellness':                '💆',
                'sport-fitness':               '🏃',
                'tururi-experiente':           '🚶',
                'gastronomie':                 '🍽️',
            };
            try {
                const r = await BileteOnlineAPI.get('/events/categories', { all: 1, parents_only: 1 });
                const rows = (r && (r.data?.categories || r.data || [])) || [];
                if (Array.isArray(rows) && rows.length > 0) {
                    this.availableCategories = rows.map(c => ({
                        slug: c.slug || '',
                        name: c.name || c.slug || '',
                        emoji: emojiMap[c.slug] || '✨',
                    })).filter(c => c.slug && c.name);
                }
            } catch (e) {}
            this.loadingCategories = false;
        },

        toggleCity(name) {
            const idx = this.interests.preferred_cities.indexOf(name);
            if (idx >= 0) this.interests.preferred_cities.splice(idx, 1);
            else if (this.interests.preferred_cities.length < 20) this.interests.preferred_cities.push(name);
        },

        toggleCategory(slug) {
            const idx = this.interests.event_categories.indexOf(slug);
            if (idx >= 0) this.interests.event_categories.splice(idx, 1);
            else if (this.interests.event_categories.length < 20) this.interests.event_categories.push(slug);
        },

        // --- Save profile (date personale) ---
        async saveProfile() {
            this.saving = true;
            try {
                const payload = {
                    first_name: this.profile.first_name || '',
                    last_name:  this.profile.last_name  || '',
                    phone:      this.profile.phone      || null,
                    birth_date: this.profile.birth_date || null,
                    gender:     this.profile.gender     || null,
                    city:       this.profile.city       || null,
                    address:    this.profile.address    || null,
                };
                const r = await BileteOnlineAPI.customer.updateProfile(payload);
                if (r && r.success) this.flash('Datele au fost salvate.', 'success');
                else this.flash((r && r.message) || 'Nu am putut salva datele.', 'error');
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la salvare.', 'error');
            }
            this.saving = false;
        },

        // --- Save password ---
        async savePassword() {
            if (this.pw.password !== this.pw.password_confirmation) {
                this.flash('Parolele nu coincid.', 'error');
                return;
            }
            if ((this.pw.password || '').length < 8) {
                this.flash('Parola nouă trebuie să aibă minim 8 caractere.', 'error');
                return;
            }
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.put('/customer/password', {
                    current_password: this.pw.current_password,
                    password: this.pw.password,
                    password_confirmation: this.pw.password_confirmation,
                });
                if (r && r.success) {
                    this.flash('Parola a fost schimbată.', 'success');
                    this.pw = { current_password: '', password: '', password_confirmation: '' };
                } else {
                    this.flash((r && r.message) || 'Parola curentă nu este corectă.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la schimbarea parolei.', 'error');
            }
            this.saving = false;
        },

        // --- Email verification ---
        async resendVerification() {
            if (this.resendCooldown > 0) return;
            this.resendingVerify = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/resend-verification', {});
                if (r && r.success) this.flash('Email-ul de verificare a fost retrimis.', 'success');
                else this.flash((r && r.message) || 'Nu am putut retrimite link-ul.', 'error');
            } catch (e) {
                this.flash('Eroare la retrimitere.', 'error');
            }
            this.resendingVerify = false;
            this.resendCooldown = 60;
            const timer = setInterval(() => {
                this.resendCooldown--;
                if (this.resendCooldown <= 0) clearInterval(timer);
            }, 1000);
        },

        // --- Logout everywhere ---
        async logoutEverywhere() {
            if (! confirm('Te vom deconecta de pe TOATE dispozitivele, inclusiv acesta. Continui?')) return;
            this.saving = true;
            try {
                await BileteOnlineAPI.post('/customer/logout', {});
            } catch (e) {}
            try { BileteOnlineAuth.logoutCustomer && BileteOnlineAuth.logoutCustomer(); } catch (e) {}
            location.href = '/autentificare';
        },

        // --- Save preferences (taste profile) ---
        async savePreferences() {
            this.saving = true;
            try {
                // City sync to top-level customer.city
                if (this.profile.city !== undefined) {
                    try {
                        await BileteOnlineAPI.customer.updateProfile({
                            first_name: this.profile.first_name || '',
                            last_name:  this.profile.last_name  || '',
                            city:       this.profile.city       || null,
                        });
                    } catch (e) {}
                }

                const payload = {
                    interests: {
                        preferred_cities: (this.interests.preferred_cities || []).filter(Boolean),
                        event_categories: (this.interests.event_categories || []).filter(Boolean),
                        lifestyle: {
                            radius:    this.lifestyle.radius    || null,
                            budget:    this.lifestyle.budget    || null,
                            frequency: this.lifestyle.frequency || null,
                            moment:    this.lifestyle.moment    || null,
                        },
                    },
                };
                const r = await BileteOnlineAPI.put('/customer/settings', payload);
                if (r && r.success) this.flash('Preferințele au fost salvate.', 'success');
                else this.flash((r && r.message) || 'Nu am putut salva preferințele.', 'error');
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la salvare.', 'error');
            }
            this.saving = false;
        },

        // --- Save notifications ---
        async saveNotifications() {
            this.saving = true;
            try {
                const payload = {
                    notification_preferences: {
                        // New canonical keys
                        tickets:         !!this.notif.tickets,
                        points:          !!this.notif.points,
                        recommendations: !!this.notif.recommendations,
                        newsletter:      !!this.notif.newsletter,
                        reviews:         !!this.notif.reviews,
                        support:         !!this.notif.support,
                        // Legacy keys mirror new ones for back-compat with email logic
                        reminders:       !!this.notif.tickets,
                    },
                };
                const r = await BileteOnlineAPI.put('/customer/settings', payload);
                if (r && r.success) this.flash('Notificările au fost salvate.', 'success');
                else this.flash((r && r.message) || 'Nu am putut salva notificările.', 'error');
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la salvare.', 'error');
            }
            this.saving = false;
        },

        // --- Save personalization ---
        async savePersonalization() {
            try {
                const r = await BileteOnlineAPI.put('/customer/settings', {
                    personalization_enabled: !!this.personalizationEnabled,
                });
                if (r && r.success) this.flash('Setarea de personalizare a fost salvată.', 'success');
                else this.flash((r && r.message) || 'Nu am putut salva.', 'error');
            } catch (e) {
                this.flash('Eroare la salvare.', 'error');
            }
        },

        // --- Export ---
        requestExport() {
            this.flash('Export-ul GDPR vine în curând. Pentru o copie a datelor, scrie-ne la suport.', 'success');
        },

        // --- Delete account ---
        async confirmDelete() {
            if (! confirm('Ești sigur că vrei să-ți ștergi contul? Acțiunea NU poate fi anulată.')) return;
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.customer.deleteAccount(this.del.password, this.del.reason);
                if (r && r.success) {
                    this.flash('Contul a fost șters.', 'success');
                    setTimeout(() => {
                        try { BileteOnlineAuth.logoutCustomer && BileteOnlineAuth.logoutCustomer(); } catch (e) {}
                        location.href = '/';
                    }, 1200);
                } else {
                    this.flash((r && r.message) || 'Nu am putut șterge contul.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la ștergere.', 'error');
            }
            this.saving = false;
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
