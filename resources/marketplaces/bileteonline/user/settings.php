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
                        <select class="field" x-model="profile.city">
                            <option value="">— alege orașul —</option>
                            <template x-for="c in availableCities" :key="c.slug || c.name">
                                <option :value="c.name" x-text="c.name"></option>
                            </template>
                        </select>
                        <span class="mt-1 block text-xs text-ink-soft">Alege din lista de orașe acoperite. Folosit pentru recomandări locale.</span>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Data nașterii</span>
                        <input class="field" type="text" inputmode="numeric" maxlength="10"
                               :value="birthDateDisplay"
                               @input="onBirthDateInput($event)"
                               @blur="normalizeBirthDate()"
                               placeholder="dd/mm/yyyy"
                               pattern="\d{2}/\d{2}/\d{4}">
                        <span class="mt-1 block text-xs text-ink-soft">Format: zi/lună/an (exemplu: 15/06/1992)</span>
                    </label>
                    <label class="md:col-span-2">
                        <span class="block mb-1.5 text-sm font-bold">Gen (opțional)</span>
                        <select class="field" x-model="profile.gender">
                            <option value="">— alege —</option>
                            <option value="female">Femeie</option>
                            <option value="male">Bărbat</option>
                            <option value="other">Altul</option>
                        </select>
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

                        <!-- 2FA — TOTP (Google Authenticator / Authy / 1Password) -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-display text-3xl font-bold">Autentificare în doi pași</h3>
                                    <p class="text-ink-soft">Recomandat pentru protecție suplimentară. Vei avea nevoie de un cod TOTP de 6 cifre la fiecare login.</p>
                                </div>
                                <span x-show="tfa.active" class="rounded-full bg-mint text-forest px-3 py-1 text-xs font-mono tracking-wider">ACTIV</span>
                                <span x-show="!tfa.active" class="rounded-full bg-paper border border-ink/10 text-ink-soft px-3 py-1 text-xs font-mono tracking-wider">INACTIV</span>
                            </div>

                            <!-- 2FA disabled — show "activate" button -->
                            <div x-show="!tfa.active && !tfa.setup" class="mt-4">
                                <button @click="tfaStart()" :disabled="saving" class="rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition disabled:opacity-60">
                                    <span x-show="!saving">Activează 2FA</span>
                                    <span x-show="saving" x-cloak>Se inițializează…</span>
                                </button>
                            </div>

                            <!-- 2FA setup wizard (after Activează clicked) -->
                            <div x-show="!tfa.active && tfa.setup" x-cloak class="mt-4 grid md:grid-cols-[200px_1fr] gap-5">
                                <div>
                                    <div id="tfa-qr" class="bg-paper rounded-2xl border-2 border-ink p-3 grid place-items-center min-h-[200px]"></div>
                                    <p class="mt-2 text-xs text-ink-soft text-center">Scanează QR cu Google Authenticator, Authy, 1Password sau Bitwarden.</p>
                                </div>
                                <div>
                                    <p class="text-sm text-ink-soft">Sau introdu secretul manual:</p>
                                    <code class="block mt-1 p-3 rounded-2xl bg-paper border border-ink/10 font-mono text-sm break-all" x-text="tfa.secret"></code>
                                    <p class="mt-4 text-sm font-bold">Pas 2 — introdu codul de 6 cifre afișat în aplicație:</p>
                                    <input class="field mt-2 font-mono text-lg tracking-widest" maxlength="6" inputmode="numeric" placeholder="123456" x-model="tfa.confirmCode" @keydown.enter.prevent="tfaConfirm()">
                                    <div class="mt-4 flex gap-2">
                                        <button @click="tfaConfirm()" :disabled="saving || tfa.confirmCode.length !== 6" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                            <span x-show="!saving">Verifică și activează</span>
                                            <span x-show="saving" x-cloak>Se verifică…</span>
                                        </button>
                                        <button @click="tfa.setup = false; tfa.secret = ''; tfa.qrUrl = ''" class="rounded-full border-2 border-ink/20 px-5 py-3 font-bold">Renunță</button>
                                    </div>
                                </div>
                                <!-- Recovery codes (shown after initiate; user must save them now) -->
                                <div class="md:col-span-2 mt-2 rounded-2xl bg-rose border-2 border-vermilion p-4">
                                    <p class="font-bold text-vermilion">⚠ Coduri de recuperare</p>
                                    <p class="mt-1 text-sm text-ink-soft">Salvează aceste 10 coduri într-un loc sigur — fiecare poate fi folosit O SINGURĂ DATĂ dacă pierzi accesul la aplicație.</p>
                                    <div class="mt-3 grid grid-cols-2 gap-1.5 font-mono text-sm">
                                        <template x-for="code in tfa.recoveryCodes" :key="code">
                                            <span class="bg-paper rounded-lg px-3 py-1.5 border border-ink/10" x-text="code"></span>
                                        </template>
                                    </div>
                                    <button @click="copyRecoveryCodes()" class="mt-3 text-xs rounded-full bg-ink text-paper px-3 py-1.5 font-bold">Copiază codurile</button>
                                </div>
                            </div>

                            <!-- 2FA active — show disable + regen options -->
                            <div x-show="tfa.active" x-cloak class="mt-4 space-y-3">
                                <p class="text-sm text-ink-soft">
                                    2FA e activ din <strong x-text="tfa.confirmedAt ? new Date(tfa.confirmedAt).toLocaleDateString('ro-RO') : ''"></strong>.
                                    Mai ai <strong x-text="tfa.recoveryCodesRemaining"></strong> coduri de recuperare neutilizate.
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <button @click="tfa.disableModal = true" class="rounded-full border-2 border-vermilion text-vermilion px-5 py-2 font-bold hover:bg-vermilion hover:text-paper transition">Dezactivează 2FA</button>
                                    <button @click="tfa.regenModal = true" class="rounded-full border-2 border-ink px-5 py-2 font-bold hover:bg-ink hover:text-paper transition">Regenerează coduri</button>
                                </div>

                                <!-- Disable modal -->
                                <div x-show="tfa.disableModal" x-cloak class="mt-3 rounded-2xl bg-paper border-2 border-vermilion p-4">
                                    <p class="font-bold">Confirmă parola pentru a dezactiva 2FA:</p>
                                    <div class="mt-3 flex gap-2">
                                        <input class="field flex-1" type="password" x-model="tfa.disablePassword" autocomplete="current-password">
                                        <button @click="tfaDisable()" :disabled="saving" class="rounded-full bg-vermilion text-paper px-4 py-2 font-bold">Dezactivează</button>
                                        <button @click="tfa.disableModal = false; tfa.disablePassword = ''" class="rounded-full border-2 border-ink/20 px-4 py-2 font-bold">Renunță</button>
                                    </div>
                                </div>

                                <!-- Regen recovery modal -->
                                <div x-show="tfa.regenModal" x-cloak class="mt-3 rounded-2xl bg-paper border-2 border-ink p-4">
                                    <p class="font-bold">Confirmă parola pentru a regenera codurile (cele vechi nu vor mai funcționa):</p>
                                    <div class="mt-3 flex gap-2">
                                        <input class="field flex-1" type="password" x-model="tfa.regenPassword" autocomplete="current-password">
                                        <button @click="tfaRegenerate()" :disabled="saving" class="rounded-full bg-ink text-paper px-4 py-2 font-bold">Regenerează</button>
                                        <button @click="tfa.regenModal = false; tfa.regenPassword = ''" class="rounded-full border-2 border-ink/20 px-4 py-2 font-bold">Renunță</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sesiuni active -->
                        <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <h3 class="font-display text-3xl font-bold">Sesiuni active</h3>
                            <p class="mt-1 text-sm text-ink-soft">Dispozitivele unde ești conectat acum. Închide-le pe cele necunoscute.</p>

                            <div x-show="loadingSessions" class="mt-3 h-12 rounded-2xl bg-paper/60 animate-pulse"></div>
                            <div x-show="!loadingSessions && sessions.length === 0" class="mt-3 text-sm text-ink-soft italic">Nu există sesiuni active.</div>

                            <div x-show="!loadingSessions && sessions.length > 0" class="mt-4 space-y-2">
                                <template x-for="s in sessions" :key="s.id">
                                    <div class="rounded-2xl bg-paper p-4 flex justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-bold" x-text="s.device || ('Sesiune #' + s.id)"></p>
                                            <p class="text-sm text-ink-soft">
                                                <span x-show="s.ip" x-text="'IP ' + s.ip"></span>
                                                <span x-show="s.last_used_at" class="ml-2">· activă <span x-text="formatRelative(s.last_used_at)"></span></span>
                                                <span x-show="!s.last_used_at && s.created_at" class="ml-2">· creată <span x-text="formatRelative(s.created_at)"></span></span>
                                            </p>
                                        </div>
                                        <div class="shrink-0">
                                            <span x-show="s.is_current" class="text-forest font-bold text-sm">aceasta</span>
                                            <button x-show="!s.is_current" @click="revokeSession(s.id)" class="text-vermilion font-bold text-sm hover:underline">Închide</button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <button @click="revokeOtherSessions()" :disabled="saving" class="rounded-full border-2 border-vermilion text-vermilion px-4 py-2 text-sm font-bold hover:bg-vermilion hover:text-paper transition disabled:opacity-60">
                                    Închide restul sesiunilor
                                </button>
                                <button @click="logoutEverywhere()" :disabled="saving" class="rounded-full border-2 border-ink/20 px-4 py-2 text-sm font-bold">
                                    Deconectare totală (inclusiv aceasta)
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
                                    <select class="field" x-model="profile.city">
                                        <option value="">— alege orașul —</option>
                                        <template x-for="c in availableCities" :key="c.slug || c.name">
                                            <option :value="c.name" x-text="c.name"></option>
                                        </template>
                                    </select>
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
                            <div x-show="!loadingCities" class="relative" @click.outside="secCitiesOpen=false" @keydown.escape="secCitiesOpen=false">
                                <!-- Multiselect trigger -->
                                <button type="button" @click="secCitiesOpen=!secCitiesOpen" :aria-expanded="secCitiesOpen"
                                        class="field flex w-full items-center justify-between gap-2 text-left">
                                    <span :class="interests.preferred_cities.length ? '' : 'text-ink-soft'"
                                          x-text="interests.preferred_cities.length ? (interests.preferred_cities.length + ' ' + (interests.preferred_cities.length === 1 ? 'oraș selectat' : 'orașe selectate')) : 'Alege orașe…'"></span>
                                    <svg class="w-4 h-4 transition-transform" :class="secCitiesOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                                </button>

                                <!-- Selected chips (removable) -->
                                <div x-show="interests.preferred_cities.length" class="mt-2 flex flex-wrap gap-1.5">
                                    <template x-for="c in interests.preferred_cities" :key="c">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-mint text-forest border-2 border-forest px-3 py-1 text-sm font-bold">
                                            <span x-text="c"></span>
                                            <button type="button" @click="toggleCity(c)" class="leading-none hover:text-vermilion" aria-label="Elimină orașul">×</button>
                                        </span>
                                    </template>
                                </div>

                                <!-- Dropdown panel -->
                                <div x-show="secCitiesOpen" x-cloak x-transition.origin.top
                                     class="absolute z-30 mt-2 w-full max-h-72 overflow-auto rounded-2xl border-2 border-ink bg-paper p-2 shadow-deep">
                                    <input type="text" x-model="secCitySearch" placeholder="Caută oraș…"
                                           class="mb-2 w-full rounded-xl border border-ink/15 bg-paper-2 px-3 py-2 text-sm outline-none focus:border-ink">
                                    <template x-for="city in availableCities.filter(c => c.name !== profile.city && c.name.toLowerCase().includes(secCitySearch.toLowerCase()))" :key="city.slug || city.name">
                                        <button type="button" @click="toggleCity(city.name)"
                                                class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left text-sm font-bold transition hover:bg-paper-2">
                                            <span x-text="city.name"></span>
                                            <svg x-show="interests.preferred_cities.includes(city.name)" class="w-4 h-4 text-forest" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </template>
                                    <p x-show="availableCities.filter(c => c.name !== profile.city && c.name.toLowerCase().includes(secCitySearch.toLowerCase())).length === 0"
                                       class="px-3 py-2 text-sm italic text-ink-soft">Niciun oraș găsit.</p>
                                </div>
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
                <p class="mt-3 text-ink-soft max-w-3xl">Adaugă persoane pentru care cumperi des bilete (copil, partener, prieten). Apar automat la checkout și ne ajută să-ți propunem activități potrivite.</p>

                <div class="mt-6 grid xl:grid-cols-[1fr_360px] gap-6">
                    <div>
                        <div x-show="loadingBeneficiaries" class="grid sm:grid-cols-2 gap-4">
                            <div class="h-32 rounded-3xl bg-paper-2/60 animate-pulse"></div>
                            <div class="h-32 rounded-3xl bg-paper-2/60 animate-pulse"></div>
                        </div>

                        <div x-show="!loadingBeneficiaries" class="grid sm:grid-cols-2 gap-4">
                            <template x-for="b in beneficiaries" :key="b.id">
                                <article class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="font-display text-2xl font-bold truncate" x-text="b.name"></h3>
                                            <p class="text-ink-soft text-sm">
                                                <span x-show="b.relation" x-text="relationLabel(b.relation)"></span>
                                                <span x-show="b.age" class="ml-1" x-text="'· ' + b.age + ' ani'"></span>
                                            </p>
                                        </div>
                                        <div class="flex gap-1 shrink-0">
                                            <button @click="editBeneficiary(b)" class="text-ink-soft hover:text-vermilion font-bold text-sm">Edit</button>
                                            <button @click="deleteBeneficiary(b.id)" class="text-vermilion font-bold text-sm">×</button>
                                        </div>
                                    </div>
                                    <div x-show="b.interests && b.interests.length" class="mt-3 flex flex-wrap gap-1">
                                        <template x-for="tag in (b.interests || [])" :key="tag">
                                            <span class="rounded-full bg-paper border border-ink/10 px-2.5 py-1 text-xs font-bold" x-text="tag"></span>
                                        </template>
                                    </div>
                                </article>
                            </template>

                            <button @click="newBeneficiary()" class="rounded-3xl border-2 border-dashed border-ink/20 bg-paper-2/40 p-5 text-center hover:border-ink/40 transition">
                                <p class="text-4xl">＋</p>
                                <p class="mt-2 font-bold">Adaugă beneficiar</p>
                            </button>
                        </div>

                        <!-- Inline form modal -->
                        <div x-show="beneficiaryForm.open" x-cloak class="mt-6 rounded-3xl border-2 border-ink bg-paper p-5">
                            <h3 class="font-display text-3xl font-bold" x-text="beneficiaryForm.id ? 'Editează beneficiar' : 'Beneficiar nou'"></h3>
                            <form @submit.prevent="saveBeneficiary()" class="mt-4 grid sm:grid-cols-2 gap-3">
                                <label class="sm:col-span-2">
                                    <span class="block mb-1.5 text-sm font-bold">Nume complet</span>
                                    <input class="field" x-model="beneficiaryForm.name" required>
                                </label>
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Relație</span>
                                    <select class="field" x-model="beneficiaryForm.relation">
                                        <option value="">— alege —</option>
                                        <option value="self">Eu însumi</option>
                                        <option value="partner">Partener</option>
                                        <option value="child">Copil</option>
                                        <option value="parent">Părinte</option>
                                        <option value="sibling">Frate / soră</option>
                                        <option value="friend">Prieten</option>
                                        <option value="other">Altă relație</option>
                                    </select>
                                </label>
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Data nașterii</span>
                                    <input class="field" type="date" x-model="beneficiaryForm.birth_date" :max="todayIso()">
                                </label>
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Email (opțional)</span>
                                    <input class="field" type="email" x-model="beneficiaryForm.email">
                                </label>
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Telefon (opțional)</span>
                                    <input class="field" type="tel" x-model="beneficiaryForm.phone">
                                </label>
                                <label class="sm:col-span-2">
                                    <span class="block mb-1.5 text-sm font-bold">Note (opțional)</span>
                                    <textarea class="field min-h-20" x-model="beneficiaryForm.notes" placeholder="alergii, preferințe, mărime tricou…"></textarea>
                                </label>
                                <div class="sm:col-span-2 flex gap-2">
                                    <button type="submit" :disabled="saving" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                        <span x-show="!saving">Salvează</span>
                                        <span x-show="saving" x-cloak>Se salvează…</span>
                                    </button>
                                    <button type="button" @click="beneficiaryForm.open = false" class="rounded-full border-2 border-ink/20 px-5 py-3 font-bold">Renunță</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <aside class="rounded-3xl bg-mint border border-forest/20 p-6 self-start">
                        <p class="font-bold text-forest">De ce contează?</p>
                        <p class="mt-2 text-ink-soft">Beneficiarii frecvenți te scapă de retastarea numelor la checkout. Profilul familiei (vârste, interese) ajută motorul de recomandări să-ți propună activități potrivite pentru toată echipa.</p>
                        <p class="mt-3 text-xs text-ink-soft">Limită: 25 beneficiari per cont.</p>
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
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PLĂȚI · STRIPE</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Metode de plată și facturare</h2>
                <p class="mt-3 text-ink-soft max-w-3xl">Salvează cardul ca să nu-l mai introduci la fiecare comandă. Datele cardului sunt stocate la Stripe — niciodată pe serverele bilete.online.</p>

                <div class="mt-6 grid xl:grid-cols-[1fr_360px] gap-6">
                    <div class="space-y-4">

                        <!-- Configuration warning -->
                        <div x-show="!loadingCards && !pay.stripeConfigured" class="rounded-3xl bg-rose border-2 border-vermilion p-5">
                            <p class="font-bold text-vermilion">Procesatorul de plăți nu este încă activ</p>
                            <p class="mt-1 text-sm text-ink-soft">Echipa bilete.online finalizează integrarea Stripe. Vei putea salva carduri imediat ce e gata.</p>
                        </div>

                        <!-- Cards list -->
                        <div x-show="loadingCards" class="space-y-2">
                            <div class="h-20 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                            <div class="h-20 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                        </div>

                        <div x-show="!loadingCards && pay.stripeConfigured" class="space-y-3">
                            <template x-for="card in pay.cards" :key="card.id">
                                <article class="rounded-3xl bg-paper-2 border border-ink/10 p-5 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="grid place-items-center w-12 h-12 rounded-xl bg-paper border border-ink/10 font-bold text-xs uppercase" x-text="card.brand || 'CARD'"></div>
                                        <div class="min-w-0">
                                            <p class="font-display text-2xl font-bold">•••• <span x-text="card.last4"></span></p>
                                            <p class="text-sm text-ink-soft">
                                                expiră <span x-text="String(card.exp_month).padStart(2,'0') + '/' + String(card.exp_year).slice(-2)"></span>
                                                <span x-show="card.is_default" class="ml-2 text-forest font-bold">· implicit</span>
                                                <span x-show="card.is_expired" class="ml-2 text-vermilion font-bold">· expirat</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <button x-show="!card.is_default && !card.is_expired" @click="setCardDefault(card.id)" class="text-ink-soft hover:text-vermilion font-bold text-sm">implicit</button>
                                        <button @click="deleteCard(card.id)" class="text-vermilion font-bold text-sm">Șterge</button>
                                    </div>
                                </article>
                            </template>

                            <div x-show="pay.cards.length === 0" class="rounded-3xl border-2 border-dashed border-ink/20 bg-paper-2/50 p-6 text-center">
                                <p class="font-display text-2xl font-bold">Niciun card salvat</p>
                                <p class="mt-1 text-ink-soft text-sm">Adaugă un card ca să cumperi mai rapid data viitoare.</p>
                            </div>

                            <!-- Add card form (Stripe Elements) -->
                            <div x-show="!pay.addOpen" class="text-right">
                                <button @click="startAddCard()" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">+ Adaugă card</button>
                            </div>
                            <div x-show="pay.addOpen" x-cloak class="rounded-3xl bg-paper-2 border-2 border-ink p-5">
                                <h3 class="font-display text-2xl font-bold">Card nou</h3>
                                <p class="mt-1 text-sm text-ink-soft">Datele cardului sunt criptate și trimise direct la Stripe.</p>
                                <div id="bo-stripe-card-element" class="mt-4 p-3 rounded-2xl bg-paper border-2 border-ink/10 min-h-[44px]"></div>
                                <p id="bo-stripe-card-error" class="mt-2 text-sm text-vermilion font-bold"></p>
                                <div class="mt-4 flex gap-2">
                                    <button @click="submitNewCard()" :disabled="pay.submittingCard" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                        <span x-show="!pay.submittingCard">Salvează cardul</span>
                                        <span x-show="pay.submittingCard" x-cloak>Se salvează…</span>
                                    </button>
                                    <button @click="cancelAddCard()" class="rounded-full border-2 border-ink/20 px-5 py-3 font-bold">Renunță</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="rounded-3xl bg-paper-2 border border-ink/10 p-6 self-start">
                        <p class="font-bold">Date facturare</p>
                        <p class="mt-1 text-sm text-ink-soft">Salvăm datele de facturare cu profilul tău, pe baza câmpurilor din tab-ul „Date personale" (nume + adresă).</p>
                        <a @click.prevent="activeTab='personal'" href="#" class="mt-4 inline-flex rounded-full border-2 border-ink px-4 py-2 font-bold text-sm hover:bg-ink hover:text-paper transition">Mergi la datele personale</a>
                        <p class="mt-5 text-xs text-ink-soft">PCI-DSS: bilete.online nu stochează niciodată numere de card. Stocăm doar un identificator opac (Stripe payment method id) + brand + ultimele 4 cifre pentru afișare.</p>
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
                        <p class="mt-2 text-ink-soft">Descarcă o copie cu datele de cont, comenzile, biletele, preferințele, beneficiarii și punctele.</p>

                        <!-- No prior request -->
                        <div x-show="!gdpr.latest" class="mt-4">
                            <button @click="requestExport()" :disabled="gdpr.loading" class="rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition disabled:opacity-60">
                                <span x-show="!gdpr.loading">Solicită export</span>
                                <span x-show="gdpr.loading" x-cloak>Se trimite…</span>
                            </button>
                        </div>

                        <!-- Pending / processing -->
                        <div x-show="gdpr.latest && (gdpr.latest.status === 'pending' || gdpr.latest.status === 'processing')" x-cloak class="mt-4 rounded-2xl bg-paper border border-ochre/40 p-4">
                            <p class="font-bold text-ochre">Exportul tău se generează…</p>
                            <p class="mt-1 text-sm text-ink-soft">Cerere lansată
                                <span x-text="gdpr.latest && gdpr.latest.requested_at ? formatRelative(gdpr.latest.requested_at) : ''"></span>.
                                Vei primi un email când arhiva e gata. Poți închide pagina — continuă în fundal.</p>
                        </div>

                        <!-- Ready -->
                        <template x-if="gdpr.latest && gdpr.latest.status === 'completed' && gdpr.latest.download_url">
                            <div class="mt-4 rounded-2xl bg-mint border-2 border-forest/30 p-4">
                                <p class="font-bold text-forest">Arhiva este gata de descărcat</p>
                                <p class="mt-1 text-sm text-ink-soft">
                                    <span x-text="gdpr.latest.file_size_bytes ? formatBytes(gdpr.latest.file_size_bytes) : ''"></span>
                                    <template x-if="gdpr.latest.expires_at">
                                        <span class="ml-2">· expiră <span x-text="new Date(gdpr.latest.expires_at).toLocaleDateString('ro-RO')"></span></span>
                                    </template>
                                </p>
                                <div class="mt-3 flex gap-2 flex-wrap">
                                    <a :href="gdpr.latest.download_url" download class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Descarcă ZIP</a>
                                    <button @click="requestExport()" class="rounded-full border-2 border-ink/20 px-5 py-3 font-bold">Regenerează</button>
                                </div>
                            </div>
                        </template>

                        <!-- Failed -->
                        <template x-if="gdpr.latest && gdpr.latest.status === 'failed'">
                            <div class="mt-4 rounded-2xl bg-rose border-2 border-vermilion p-4">
                                <p class="font-bold text-vermilion">Exportul a eșuat</p>
                                <p class="mt-1 text-sm text-ink-soft" x-text="gdpr.latest.error_message ? gdpr.latest.error_message : 'Te rugăm să încerci din nou peste câteva minute.'"></p>
                                <button @click="requestExport()" class="mt-3 rounded-full bg-vermilion text-paper px-4 py-2 font-bold">Încearcă din nou</button>
                            </div>
                        </template>
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
            <div x-show="isAuth === false" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
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
        isAuth: null,
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
            // address removed — bilete.online doesn't ask for street address;
            // billing details come from the order itself.
        },
        emailVerified: false,

        // --- Password ---
        pw: { current_password: '', password: '', password_confirmation: '' },
        resendingVerify: false,
        resendCooldown: 0,

        // --- Preferences ---
        interests: { preferred_cities: [], event_categories: [] },
        lifestyle: { radius: '', budget: '', frequency: '', moment: '' },
        // Seeded synchronously so the "Oraș principal" <select> has options on
        // first paint — otherwise the saved city (set async from the profile)
        // has no matching <option> yet and the select renders blank on refresh.
        // loadCities() replaces this with the live list when the API responds.
        availableCities: ['București','Cluj-Napoca','Brașov','Timișoara','Iași','Constanța','Sibiu','Oradea','Craiova','Galați','Ploiești','Bacău','Pitești','Arad','Târgu Mureș','Baia Mare','Suceava','Râmnicu Vâlcea','Buzău','Botoșani','Satu Mare','Brăila','Drobeta-Turnu Severin','Deva','Alba Iulia','Hunedoara','Focșani','Bistrița','Reșița','Slatina','Călărași','Giurgiu','Târgoviște','Tulcea','Slobozia','Vaslui','Zalău','Sfântu Gheorghe','Piatra Neamț','Târgu Jiu','Miercurea Ciuc'].map(n => ({ name: n, slug: n.toLowerCase().replace(/[ăâ]/g,'a').replace(/[î]/g,'i').replace(/[șş]/g,'s').replace(/[țţ]/g,'t').replace(/\s+/g,'-') })),
        availableCategories: [],
        loadingCities: true,
        loadingCategories: true,
        secCitiesOpen: false,
        secCitySearch: '',

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

        // --- 2FA ---
        tfa: {
            active: false, confirmedAt: null, hasPendingSetup: false,
            recoveryCodesRemaining: 0,
            setup: false, secret: '', qrUrl: '', confirmCode: '', recoveryCodes: [],
            disableModal: false, disablePassword: '',
            regenModal: false, regenPassword: '',
        },

        // --- Sessions ---
        sessions: [],
        loadingSessions: true,

        // --- Beneficiaries ---
        beneficiaries: [],
        loadingBeneficiaries: true,
        beneficiaryForm: { open: false, id: null, name: '', relation: '', birth_date: '', email: '', phone: '', notes: '' },

        // --- Payment methods (Stripe) ---
        pay: {
            cards: [],
            stripeConfigured: false,
            publishableKey: null,
            addOpen: false,
            submittingCard: false,
            stripe: null,       // Stripe() instance
            elements: null,
            cardElement: null,
        },
        loadingCards: true,

        // --- GDPR export ---
        gdpr: { latest: null, history: [], loading: false, pollTimer: null },

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
                this.interests.event_categories.length > 0,
                this.interests.preferred_cities.length > 0,
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
            try { if (window.BileteOnlineAuth && BileteOnlineAuth.getToken && BileteOnlineAuth.getToken()) this.isAuth = true; } catch (e) {}
            if (this.isAuth === false) { this.loading = false; return; }

            this.load();
            this.loadCities();
            this.loadCategories();
            this.loadTfaStatus();
            this.loadSessions();
            this.loadBeneficiaries();
            this.loadCards();
            this.loadGdpr();

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

        // Birth date is stored as ISO (YYYY-MM-DD, what Laravel accepts) but
        // displayed to the user as dd/mm/yyyy because that's the RO convention.
        get birthDateDisplay() {
            const iso = this.profile.birth_date || '';
            if (! iso) return '';
            const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (m) return m[3] + '/' + m[2] + '/' + m[1];
            // Already in dd/mm/yyyy or other — pass through so the user keeps typing
            return iso;
        },

        onBirthDateInput(ev) {
            // Live auto-format: as the user types digits, insert "/" at the
            // right positions so the field always reads dd/mm/yyyy.
            let v = (ev.target.value || '').replace(/\D/g, '').slice(0, 8);
            if (v.length >= 5)      v = v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
            else if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
            ev.target.value = v;
            // Don't commit to profile.birth_date until blur — keeps the
            // model in a valid ISO state.
        },

        normalizeBirthDate() {
            // Read the literal field value, parse dd/mm/yyyy → ISO YYYY-MM-DD,
            // and write to profile.birth_date. Leaves the input as-is so the
            // displayed value still matches what the user typed.
            const el = document.activeElement && document.activeElement.tagName === 'INPUT'
                ? document.activeElement
                : document.querySelector('input[placeholder="dd/mm/yyyy"]');
            const raw = el ? el.value : '';
            if (! raw) { this.profile.birth_date = ''; return; }
            const m = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (! m) {
                this.flash('Formatul datei nașterii trebuie să fie zi/lună/an (exemplu: 15/06/1992).', 'error');
                return;
            }
            const dd = parseInt(m[1]), mm = parseInt(m[2]), yyyy = parseInt(m[3]);
            if (mm < 1 || mm > 12 || dd < 1 || dd > 31 || yyyy < 1900 || yyyy > new Date().getFullYear()) {
                this.flash('Data nașterii nu este validă.', 'error');
                return;
            }
            const iso = String(yyyy) + '-' + String(mm).padStart(2, '0') + '-' + String(dd).padStart(2, '0');
            this.profile.birth_date = iso;
        },

        flash(msg, type) {
            this.message = msg;
            this.messageType = type || 'success';
            clearTimeout(this._flashTimer);
            this._flashTimer = setTimeout(() => { this.message = ''; }, 4500);
        },

        // Apply customer data (from cache OR API) into the form fields.
        applyCustomer(u) {
            if (! u) return;
            // Use ??= so the API call doesn't overwrite text the user is
            // currently editing. First call wins; subsequent calls only fill
            // gaps left empty.
            this.profile.first_name = this.profile.first_name || u.first_name || '';
            this.profile.last_name  = this.profile.last_name  || u.last_name  || '';
            this.profile.email      = this.profile.email      || u.email      || '';
            this.profile.phone      = this.profile.phone      || u.phone      || '';
            this.profile.birth_date = this.profile.birth_date || u.birth_date || '';
            this.profile.gender     = this.profile.gender     || u.gender     || '';
            this.profile.city       = this.profile.city       || u.city       || '';
            if (typeof u.email_verified === 'boolean') this.emailVerified = u.email_verified;
            this.ensureSelectedCity();
        },

        // Make sure the saved primary city is always present as an <option> —
        // even if it isn't in the live/fallback list — so the
        // <select x-model="profile.city"> reliably shows it after a refresh.
        ensureSelectedCity() {
            const c = (this.profile.city || '').trim();
            if (c && ! this.availableCities.some(x => x.name === c)) {
                this.availableCities = [{ name: c, slug: c.toLowerCase().replace(/\s+/g, '-') }, ...this.availableCities];
            }
        },

        // --- Load customer data ---
        async load() {
            // 1) Instant prefill from the cached session object so the user
            //    sees their data without waiting for the API round-trip.
            try {
                const cached = window.BileteOnlineAuth && BileteOnlineAuth.getUser ? BileteOnlineAuth.getUser() : null;
                if (cached) this.applyCustomer(cached);
            } catch (e) {}

            // 2) Authoritative refresh from /customer/me. Backend response
            //    shape: { success: true, data: { customer: {...} } } — but we
            //    defend against a flat-data shape too.
            try {
                const r = await BileteOnlineAPI.customer.getProfile();
                if (r && r.success) this.isAuth = true;
                const root = (r && r.data) || {};
                const u = root.customer || root;

                this.applyCustomer(u);

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
                if (e && e.status === 401) {
                    this.isAuth = false;
                } else {
                    console.warn('settings load failed', e);
                }
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
                // /cities is unmapped in api.js — must go through the public
                // marketplace endpoint that footer + category pages also use.
                const r = await BileteOnlineAPI.get('/marketplace-events/cities', { per_page: 60 });
                const rows = (r && (r.data?.cities || r.data || [])) || [];
                if (Array.isArray(rows) && rows.length > 0) {
                    this.availableCities = rows.map(c => ({
                        name: c.name || c.label || c.city || String(c),
                        slug: c.slug || (c.name || '').toLowerCase(),
                    })).filter(c => c.name);
                }
                // else: keep the synchronously-seeded fallback already in
                // availableCities (don't blank the select).
            } catch (e) {
                // keep seeded fallback
            }
            this.ensureSelectedCity();
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
            // Fallback parent categories (bilete.online) so the picker is never
            // empty if the API call fails — mirrors loadCities(). Slugs match the
            // real marketplace_event_categories parents.
            const fallback = [
                { slug: 'escape-rooms',                  name: 'Escape rooms' },
                { slug: 'muzee-expozitii',               name: 'Muzee & expoziții' },
                { slug: 'parcuri-de-distractii',         name: 'Parcuri de distracții' },
                { slug: 'parcuri-de-aventura',           name: 'Parcuri de aventură' },
                { slug: 'natura-outdoor',                name: 'Natură & outdoor' },
                { slug: 'acvarii-zoo-animale',           name: 'Acvarii, zoo & animale' },
                { slug: 'ateliere-experiente-creative',  name: 'Ateliere & experiențe creative' },
                { slug: 'tururi-experiente-turistice',   name: 'Tururi & experiențe turistice' },
                { slug: 'educatie-invatare-experientiala', name: 'Educație & învățare' },
                { slug: 'familie-copii',                 name: 'Familie & copii' },
                { slug: 'corporate-grupuri',             name: 'Corporate & grupuri' },
                { slug: 'cultura-arta',                  name: 'Cultură & artă' },
            ];
            const flattenName = (n, slug) => (n && typeof n === 'object')
                ? (n.ro || n.en || Object.values(n)[0] || slug || '')
                : (n || slug || '');
            const applyFallback = () => {
                this.availableCategories = fallback.map(c => ({ slug: c.slug, name: c.name, emoji: emojiMap[c.slug] || '✨' }));
            };
            try {
                // Canonical entrypoint used by footer + category landing pages
                // (proxy action: 'categories'). all=1 returns categories without
                // events too — essential on activity-only marketplaces.
                const r = await BileteOnlineAPI.get('/marketplace-events/categories', { all: 1, parents_only: 1 });
                const rows = (r && (r.data?.categories || r.data || [])) || [];
                if (Array.isArray(rows) && rows.length > 0) {
                    this.availableCategories = rows.map(c => ({
                        slug: c.slug || '',
                        name: flattenName(c.name, c.slug),
                        emoji: emojiMap[c.slug] || '✨',
                    })).filter(c => c.slug && c.name);
                }
                if (this.availableCategories.length === 0) applyFallback();
            } catch (e) {
                applyFallback();
            }
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

        // ===================================================
        // === 2FA (TOTP) ====================================
        // ===================================================
        async loadTfaStatus() {
            try {
                const r = await BileteOnlineAPI.get('/customer/2fa/status');
                const d = (r && r.data) || {};
                this.tfa.active = !!d.two_factor_active;
                this.tfa.confirmedAt = d.confirmed_at || null;
                this.tfa.hasPendingSetup = !!d.has_pending_setup;
                this.tfa.recoveryCodesRemaining = d.recovery_codes_remaining || 0;
            } catch (e) {}
        },

        async tfaStart() {
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/2fa/initiate', {});
                if (r && r.success) {
                    const d = r.data || {};
                    this.tfa.secret = d.secret || '';
                    this.tfa.qrUrl = d.qr_url || '';
                    this.tfa.recoveryCodes = d.recovery_codes || [];
                    this.tfa.setup = true;
                    this.tfa.confirmCode = '';
                    await this.$nextTick();
                    this.renderTfaQr();
                } else {
                    this.flash((r && r.message) || 'Nu am putut iniția 2FA.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la inițierea 2FA.', 'error');
            }
            this.saving = false;
        },

        renderTfaQr() {
            const target = document.getElementById('tfa-qr');
            if (!target || !this.tfa.qrUrl) return;
            target.innerHTML = '';
            // lazy-load qrcode.js once
            const draw = () => {
                try {
                    new window.QRCode(target, {
                        text: this.tfa.qrUrl,
                        width: 180, height: 180,
                        correctLevel: window.QRCode.CorrectLevel.M,
                    });
                } catch (e) { console.warn('QR render failed', e); }
            };
            if (window.QRCode) { draw(); return; }
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
            s.onload = draw;
            document.head.appendChild(s);
        },

        async tfaConfirm() {
            if ((this.tfa.confirmCode || '').length < 6) return;
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/2fa/confirm', { code: this.tfa.confirmCode });
                if (r && r.success) {
                    this.flash('2FA activat cu succes.', 'success');
                    this.tfa.setup = false;
                    this.tfa.secret = '';
                    this.tfa.qrUrl = '';
                    this.tfa.confirmCode = '';
                    await this.loadTfaStatus();
                } else {
                    this.flash((r && r.message) || 'Codul nu este valid.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la verificare.', 'error');
            }
            this.saving = false;
        },

        async tfaDisable() {
            if (!this.tfa.disablePassword) { this.flash('Introdu parola.', 'error'); return; }
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/2fa/disable', { password: this.tfa.disablePassword });
                if (r && r.success) {
                    this.flash('2FA dezactivat.', 'success');
                    this.tfa.disableModal = false;
                    this.tfa.disablePassword = '';
                    await this.loadTfaStatus();
                } else {
                    this.flash((r && r.message) || 'Parola este incorectă.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la dezactivare.', 'error');
            }
            this.saving = false;
        },

        async tfaRegenerate() {
            if (!this.tfa.regenPassword) { this.flash('Introdu parola.', 'error'); return; }
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/2fa/recovery-codes/regenerate', { password: this.tfa.regenPassword });
                if (r && r.success) {
                    this.tfa.recoveryCodes = (r.data && r.data.recovery_codes) || [];
                    this.tfa.regenModal = false;
                    this.tfa.regenPassword = '';
                    this.flash('Codurile au fost regenerate. Salvează-le într-un loc sigur.', 'success');
                    await this.loadTfaStatus();
                } else {
                    this.flash((r && r.message) || 'Nu am putut regenera codurile.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la regenerare.', 'error');
            }
            this.saving = false;
        },

        copyRecoveryCodes() {
            const text = (this.tfa.recoveryCodes || []).join('\n');
            try { navigator.clipboard.writeText(text); this.flash('Codurile au fost copiate.', 'success'); }
            catch (e) { this.flash('Selectează manual codurile pentru a le copia.', 'error'); }
        },

        // ===================================================
        // === Sessions ======================================
        // ===================================================
        async loadSessions() {
            this.loadingSessions = true;
            try {
                const r = await BileteOnlineAPI.get('/customer/sessions');
                this.sessions = (r && r.data && r.data.sessions) || [];
            } catch (e) {}
            this.loadingSessions = false;
        },

        async revokeSession(id) {
            if (!confirm('Sigur închizi această sesiune?')) return;
            try {
                const r = await BileteOnlineAPI.delete('/customer/sessions/' + id, {});
                if (r && r.success) {
                    this.flash('Sesiunea a fost închisă.', 'success');
                    if (r.data && r.data.logged_out_current) {
                        setTimeout(() => { location.href = '/autentificare'; }, 800);
                    } else {
                        await this.loadSessions();
                    }
                } else {
                    this.flash((r && r.message) || 'Nu am putut închide sesiunea.', 'error');
                }
            } catch (e) {
                this.flash('Eroare la închiderea sesiunii.', 'error');
            }
        },

        async revokeOtherSessions() {
            if (!confirm('Închizi toate celelalte sesiuni? Vei rămâne logat doar pe acest dispozitiv.')) return;
            this.saving = true;
            try {
                const r = await BileteOnlineAPI.delete('/customer/sessions/all', {});
                if (r && r.success) {
                    this.flash((r.data && r.data.revoked ? r.data.revoked + ' sesiuni' : 'Sesiunile') + ' au fost închise.', 'success');
                    await this.loadSessions();
                }
            } catch (e) {}
            this.saving = false;
        },

        // ===================================================
        // === Beneficiaries (Familie) =======================
        // ===================================================
        async loadBeneficiaries() {
            this.loadingBeneficiaries = true;
            try {
                const r = await BileteOnlineAPI.get('/customer/beneficiaries');
                this.beneficiaries = (r && r.data && r.data.beneficiaries) || [];
            } catch (e) {}
            this.loadingBeneficiaries = false;
        },

        relationLabel(rel) {
            const map = { self: 'eu', partner: 'partener', child: 'copil', parent: 'părinte',
                          sibling: 'frate/soră', friend: 'prieten', other: 'alt' };
            return map[rel] || rel || '';
        },

        newBeneficiary() {
            this.beneficiaryForm = { open: true, id: null, name: '', relation: '', birth_date: '', email: '', phone: '', notes: '' };
        },

        editBeneficiary(b) {
            this.beneficiaryForm = {
                open: true,
                id: b.id,
                name: b.name || '',
                relation: b.relation || '',
                birth_date: b.birth_date || '',
                email: b.email || '',
                phone: b.phone || '',
                notes: b.notes || '',
            };
        },

        async saveBeneficiary() {
            const f = this.beneficiaryForm;
            if (!f.name) { this.flash('Numele este obligatoriu.', 'error'); return; }
            this.saving = true;
            const payload = {
                name: f.name,
                relation: f.relation || null,
                birth_date: f.birth_date || null,
                email: f.email || null,
                phone: f.phone || null,
                notes: f.notes || null,
            };
            try {
                let r;
                if (f.id) {
                    r = await BileteOnlineAPI.put('/customer/beneficiaries/' + f.id, payload);
                } else {
                    r = await BileteOnlineAPI.post('/customer/beneficiaries', payload);
                }
                if (r && r.success) {
                    this.flash(f.id ? 'Beneficiar actualizat.' : 'Beneficiar adăugat.', 'success');
                    this.beneficiaryForm.open = false;
                    await this.loadBeneficiaries();
                } else {
                    this.flash((r && r.message) || 'Nu am putut salva beneficiarul.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la salvare.', 'error');
            }
            this.saving = false;
        },

        async deleteBeneficiary(id) {
            if (!confirm('Sigur ștergi acest beneficiar?')) return;
            try {
                const r = await BileteOnlineAPI.delete('/customer/beneficiaries/' + id, {});
                if (r && r.success) {
                    this.flash('Beneficiar șters.', 'success');
                    await this.loadBeneficiaries();
                }
            } catch (e) {
                this.flash('Eroare la ștergere.', 'error');
            }
        },

        // ===================================================
        // === Payment methods (Stripe) ======================
        // ===================================================
        async loadCards() {
            this.loadingCards = true;
            try {
                const r = await BileteOnlineAPI.get('/customer/payment-methods');
                const d = (r && r.data) || {};
                this.pay.cards = d.payment_methods || [];
                this.pay.stripeConfigured = !!d.stripe_configured;
                this.pay.publishableKey = d.stripe_publishable_key || null;
            } catch (e) {}
            this.loadingCards = false;
        },

        async ensureStripeLoaded() {
            if (window.Stripe) return true;
            await new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://js.stripe.com/v3/';
                s.onload = resolve;
                s.onerror = reject;
                document.head.appendChild(s);
            });
            return !!window.Stripe;
        },

        async startAddCard() {
            if (!this.pay.stripeConfigured || !this.pay.publishableKey) {
                this.flash('Procesatorul de plăți nu este configurat încă.', 'error');
                return;
            }
            this.pay.addOpen = true;
            await this.$nextTick();
            await this.ensureStripeLoaded();
            if (!window.Stripe) { this.flash('Nu am putut încărca Stripe.', 'error'); return; }

            this.pay.stripe = window.Stripe(this.pay.publishableKey);
            this.pay.elements = this.pay.stripe.elements();
            this.pay.cardElement = this.pay.elements.create('card', {
                hidePostalCode: true,
                style: {
                    base: { fontFamily: '"Hanken Grotesk", Arial, sans-serif', fontSize: '16px', color: '#1B1714', '::placeholder': { color: '#5A4F41' } },
                    invalid: { color: '#E84527' },
                },
            });
            this.pay.cardElement.mount('#bo-stripe-card-element');
            const errEl = document.getElementById('bo-stripe-card-error');
            this.pay.cardElement.on('change', (ev) => { if (errEl) errEl.textContent = ev.error ? ev.error.message : ''; });
        },

        async submitNewCard() {
            if (!this.pay.stripe || !this.pay.cardElement) return;
            this.pay.submittingCard = true;
            try {
                const intentResp = await BileteOnlineAPI.post('/customer/payment-methods/setup-intent', {});
                if (!intentResp || !intentResp.success) {
                    this.flash((intentResp && intentResp.message) || 'Nu am putut iniția salvarea cardului.', 'error');
                    this.pay.submittingCard = false; return;
                }
                const clientSecret = intentResp.data.client_secret;

                const cardholderName = (this.profile.first_name + ' ' + this.profile.last_name).trim();
                const setup = await this.pay.stripe.confirmCardSetup(clientSecret, {
                    payment_method: {
                        card: this.pay.cardElement,
                        billing_details: { name: cardholderName || undefined, email: this.profile.email || undefined },
                    },
                });

                if (setup.error) {
                    const errEl = document.getElementById('bo-stripe-card-error');
                    if (errEl) errEl.textContent = setup.error.message;
                    this.flash(setup.error.message, 'error');
                    this.pay.submittingCard = false; return;
                }

                const confirmResp = await BileteOnlineAPI.post('/customer/payment-methods/confirm', {
                    setup_intent_id: setup.setupIntent.id,
                });
                if (confirmResp && confirmResp.success) {
                    this.flash('Cardul a fost salvat.', 'success');
                    this.cancelAddCard();
                    await this.loadCards();
                } else {
                    this.flash((confirmResp && confirmResp.message) || 'Cardul nu a putut fi salvat.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la salvarea cardului.', 'error');
            }
            this.pay.submittingCard = false;
        },

        cancelAddCard() {
            if (this.pay.cardElement) {
                try { this.pay.cardElement.destroy(); } catch (e) {}
            }
            this.pay.cardElement = null;
            this.pay.elements = null;
            this.pay.stripe = null;
            this.pay.addOpen = false;
            const errEl = document.getElementById('bo-stripe-card-error');
            if (errEl) errEl.textContent = '';
        },

        async setCardDefault(id) {
            try {
                const r = await BileteOnlineAPI.put('/customer/payment-methods/' + id + '/default', {});
                if (r && r.success) {
                    this.flash('Card setat ca implicit.', 'success');
                    await this.loadCards();
                }
            } catch (e) {}
        },

        async deleteCard(id) {
            if (!confirm('Sigur ștergi acest card?')) return;
            try {
                const r = await BileteOnlineAPI.delete('/customer/payment-methods/' + id, {});
                if (r && r.success) {
                    this.flash('Cardul a fost șters.', 'success');
                    await this.loadCards();
                }
            } catch (e) {
                this.flash('Eroare la ștergere.', 'error');
            }
        },

        // ===================================================
        // === GDPR data export ==============================
        // ===================================================
        async loadGdpr() {
            try {
                const r = await BileteOnlineAPI.get('/customer/gdpr/export/status');
                const d = (r && r.data) || {};
                this.gdpr.latest = d.latest || null;
                this.gdpr.history = d.history || [];
                if (this.gdpr.latest && (this.gdpr.latest.status === 'pending' || this.gdpr.latest.status === 'processing')) {
                    this.startGdprPolling();
                }
            } catch (e) {}
        },

        async requestExport() {
            this.gdpr.loading = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/gdpr/export', {});
                if (r && r.success) {
                    this.gdpr.latest = (r.data && r.data.request) || null;
                    this.flash('Cerere de export înregistrată.', 'success');
                    this.startGdprPolling();
                } else {
                    this.flash((r && r.message) || 'Nu am putut iniția exportul.', 'error');
                }
            } catch (e) {
                this.flash((e && e.message) || 'Eroare la cerere.', 'error');
            }
            this.gdpr.loading = false;
        },

        startGdprPolling() {
            if (this.gdpr.pollTimer) return;
            this.gdpr.pollTimer = setInterval(async () => {
                try {
                    const r = await BileteOnlineAPI.get('/customer/gdpr/export/status');
                    this.gdpr.latest = (r && r.data && r.data.latest) || this.gdpr.latest;
                    if (!this.gdpr.latest || (this.gdpr.latest.status !== 'pending' && this.gdpr.latest.status !== 'processing')) {
                        clearInterval(this.gdpr.pollTimer);
                        this.gdpr.pollTimer = null;
                        if (this.gdpr.latest && this.gdpr.latest.status === 'completed') {
                            this.flash('Arhiva ta de date e gata. Poți descărca din buton.', 'success');
                        }
                    }
                } catch (e) {}
            }, 5000);
        },

        formatBytes(n) {
            if (!n) return '';
            const units = ['B','KB','MB','GB'];
            let i = 0, v = n;
            while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
            return v.toFixed(v < 10 ? 1 : 0) + ' ' + units[i];
        },

        formatRelative(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            const diffSec = Math.round((Date.now() - d.getTime()) / 1000);
            if (diffSec < 60) return 'acum';
            if (diffSec < 3600) return 'acum ' + Math.floor(diffSec / 60) + ' min';
            if (diffSec < 86400) return 'acum ' + Math.floor(diffSec / 3600) + ' h';
            if (diffSec < 86400 * 30) return 'acum ' + Math.floor(diffSec / 86400) + ' zile';
            return d.toLocaleDateString('ro-RO');
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
