<?php
/**
 * bilete.online — /autentificare (alias /login)
 *
 * Unified auth page covering 4 flows in one shell:
 *   - Client × Login    → BileteOnlineAuth.loginCustomer(email, password)
 *   - Client × Register → BileteOnlineAuth.registerCustomer({...})
 *   - Venue  × Login    → BileteOnlineAuth.loginOrganizer(email, password)
 *   - Venue  × Register → BileteOnlineAuth.registerOrganizer({...})
 *
 * Alpine.js handles tab state (accountType + mode). The form posts via
 * BileteOnlineAuth which already manages cookies, tokens, redirects and
 * the bileteonline:auth:login event.
 *
 * "Recuperare comandă" + "Magic link" CTAs are visual-only for v1 — they
 * link to the placeholder pages /recuperare-comanda and /seteaza-parola.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Autentificare și creare cont — ' . SITE_NAME;
$pageDescription = 'Intră în contul tău bilete.online sau creează un cont nou pentru bilete, comenzi, puncte bonus și carduri cadou. Login separat pentru locații / organizatori.';
$canonicalUrl    = SITE_URL . '/autentificare';
$noindex         = true;     // login pages must not be indexed
$currentPage     = 'login';
$cssBundle       = 'auth';

$preselect    = isset($_GET['ca']) && $_GET['ca'] === 'venue' ? 'venue' : 'client';
$preselectMode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';
$redirectAfter = $_GET['redirect'] ?? '/cont';
$prefillEmail  = isset($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) ? $_GET['email'] : '';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="authPage(<?= htmlspecialchars(json_encode([
    'accountType'    => $preselect,
    'mode'           => $preselectMode,
    'redirectAfter'  => $redirectAfter,
    'prefillEmail'   => $prefillEmail,
]), ENT_QUOTES) ?>)">

<!-- HERO + form -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_84%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_14%_76%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.18),transparent_30%)]"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Autentificare</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[.95fr_1.05fr] gap-12 items-center">

            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">CLIENT · LOCAȚIE · CONTURI</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl lg:text-[6.2rem] font-bold leading-[.82]" x-text="heroTitle()"></h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed" x-text="heroDescription()"></p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <button @click="accountType='client'; mode='login'" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold text-lg hover:bg-vermilion-d transition">Intră ca client</button>
                    <button @click="accountType='venue'; mode='login'" class="rounded-full border-2 border-ink px-6 py-4 font-bold text-lg hover:bg-ink hover:text-paper transition">Intră ca locație</button>
                </div>

                <div class="mt-10 grid grid-cols-3 gap-3 max-w-2xl">
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4">
                        <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">CLIENT</p>
                        <p class="font-display text-3xl font-bold">bilete</p>
                    </div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4">
                        <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">BONUS</p>
                        <p class="font-display text-3xl font-bold">puncte</p>
                    </div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4">
                        <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">VENUE</p>
                        <p class="font-display text-3xl font-bold">QR</p>
                    </div>
                </div>
            </div>

            <section class="relative" aria-label="Autentificare și creare cont">
                <div class="absolute inset-x-8 top-8 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">

                    <div class="p-5 sm:p-6 border-b-2 border-dashed border-ink/15">
                        <div class="grid grid-cols-2 gap-2 rounded-2xl bg-paper-2 p-1.5 border border-ink/10">
                            <button @click="accountType='client'" :class="accountType==='client' ? 'bg-ink text-paper shadow' : 'text-ink-soft hover:text-ink'" class="rounded-xl px-4 py-3 font-bold transition">Client</button>
                            <button @click="accountType='venue'" :class="accountType==='venue' ? 'bg-ink text-paper shadow' : 'text-ink-soft hover:text-ink'" class="rounded-xl px-4 py-3 font-bold transition">Locație</button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 rounded-2xl bg-paper-2 p-1.5 border border-ink/10">
                            <button @click="mode='login'" :class="mode==='login' ? 'bg-vermilion text-paper shadow' : 'text-ink-soft hover:text-ink'" class="rounded-xl px-4 py-3 font-bold transition">Login</button>
                            <button @click="mode='register'" :class="mode==='register' ? 'bg-vermilion text-paper shadow' : 'text-ink-soft hover:text-ink'" class="rounded-xl px-4 py-3 font-bold transition">Creează cont</button>
                        </div>
                    </div>

                    <div class="p-6 sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-mono text-xs tracking-[.18em] text-ink-soft" x-text="accountType==='client' ? 'CONT CLIENT' : 'CONT LOCAȚIE'"></p>
                                <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none" x-text="formTitle()"></h2>
                            </div>
                            <span class="grid place-items-center w-14 h-14 rounded-2xl" :class="accountType==='client' ? 'bg-vermilion text-paper' : 'bg-forest text-paper'">
                                <svg x-show="accountType==='client'" viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M9 7v10" stroke-dasharray="2 2"/></svg>
                                <svg x-show="accountType==='venue'" x-cloak viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4 6 3v15M9 10h1M9 14h1M14 10h1M14 14h1"/></svg>
                            </span>
                        </div>
                        <p class="mt-4 text-ink-soft leading-relaxed" x-text="formDescription()"></p>

                        <!-- shared error/success banner -->
                        <div x-show="message" x-cloak class="mt-5 rounded-2xl border-2 px-4 py-3 text-sm font-medium" :class="messageType === 'error' ? 'border-vermilion bg-vermilion/10 text-vermilion' : 'border-forest bg-mint text-forest'" x-text="message"></div>

                        <!-- 2FA challenge — shown after submitLogin when backend returns requires_2fa -->
                        <form x-show="mode==='login' && twofa.required" x-cloak x-transition.opacity class="mt-7 space-y-4" @submit.prevent="submit2faCode()">
                            <div class="rounded-2xl bg-mint border border-forest/20 p-4">
                                <p class="font-bold text-forest">Autentificare în doi pași</p>
                                <p class="mt-1 text-sm text-ink-soft">Deschide aplicația de autentificare (Google Authenticator, Authy, 1Password) și introdu codul de 6 cifre. Sau folosește unul dintre codurile de recuperare salvate.</p>
                            </div>
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Cod TOTP sau cod de recuperare</span>
                                <input class="field font-mono text-lg tracking-widest" x-model="twofa.code" placeholder="123 456" autocomplete="one-time-code" inputmode="text" required>
                            </label>
                            <button type="submit" :disabled="submitting" class="w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                <span x-show="!submitting">Verifică și intră</span>
                                <span x-show="submitting" x-cloak>Se verifică…</span>
                            </button>
                            <button type="button" @click="cancel2fa()" class="w-full text-sm font-bold text-ink-soft hover:text-ink">← Înapoi la login</button>
                        </form>

                        <!-- LOGIN -->
                        <form x-show="mode==='login' && !twofa.required" x-transition.opacity class="mt-7 space-y-4" @submit.prevent="submitLogin()">
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Email</span>
                                <input class="field" type="email" x-model="login.email" :placeholder="accountType==='client' ? 'emailul folosit la comandă' : 'email organizator / staff'" autocomplete="email" required>
                            </label>
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Parolă</span>
                                <div class="relative">
                                    <input class="field pr-14" :type="showPassword ? 'text' : 'password'" x-model="login.password" placeholder="parola contului" autocomplete="current-password" required>
                                    <button type="button" @click="showPassword=!showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 font-bold text-sm text-vermilion" x-text="showPassword ? 'ascunde' : 'arată'"></button>
                                </div>
                            </label>
                            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" x-model="login.remember" class="w-4 h-4 accent-vermilion">
                                    <span>Ține-mă minte</span>
                                </label>
                                <a href="/parola-uitata" class="font-bold text-vermilion underline-wobble">Am uitat parola</a>
                            </div>
                            <template x-if="accountType==='client'">
                                <div class="rounded-2xl bg-mint border border-forest/20 p-4">
                                    <p class="font-bold text-forest">Ai cumpărat fără cont?</p>
                                    <p class="mt-1 text-sm text-ink-soft">Poți recupera comanda după email și număr comandă sau îți poți seta parola pentru contul creat automat.</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <a href="/recuperare-comanda" class="rounded-full bg-forest text-paper px-4 py-2 text-sm font-bold">Recuperează comanda</a>
                                        <a href="/parola-uitata" class="rounded-full border border-forest/30 px-4 py-2 text-sm font-bold text-forest">Setează parola</a>
                                    </div>
                                </div>
                            </template>
                            <template x-if="accountType==='venue'">
                                <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                    <p class="font-bold">Acces pentru staff și locații</p>
                                    <p class="mt-1 text-sm text-ink-soft">Folosește emailul cu care ai fost invitat în dashboard. Pentru conturi noi, solicită demo sau invită staff din contul principal.</p>
                                </div>
                            </template>
                            <button type="submit" :disabled="submitting" class="w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                <span x-show="!submitting" x-text="accountType==='client' ? 'Intră în contul client' : 'Intră în dashboard locație'"></span>
                                <span x-show="submitting" x-cloak>Se conectează…</span>
                            </button>
                        </form>

                        <!-- REGISTER -->
                        <form x-show="mode==='register'" x-transition.opacity x-cloak class="mt-7 space-y-4" @submit.prevent="submitRegister()">

                            <template x-if="accountType==='client'">
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Prenume</span>
                                        <input class="field" x-model="register.first_name" placeholder="Prenume" autocomplete="given-name" required>
                                    </label>
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Nume</span>
                                        <input class="field" x-model="register.last_name" placeholder="Nume" autocomplete="family-name" required>
                                    </label>
                                </div>
                            </template>

                            <template x-if="accountType==='venue'">
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Nume contact</span>
                                        <input class="field" x-model="register.contact_name" placeholder="Nume și prenume" required>
                                    </label>
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Nume locație</span>
                                        <input class="field" x-model="register.venue_name" placeholder="ex. Mystery Rooms Brașov" required>
                                    </label>
                                </div>
                            </template>

                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Email</span>
                                <input class="field" type="email" x-model="register.email" :placeholder="accountType==='client' ? 'email@example.ro' : 'email@locatie.ro'" autocomplete="email" required>
                            </label>

                            <template x-if="accountType==='venue'">
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Telefon</span>
                                        <input class="field" type="tel" x-model="register.phone" placeholder="+40...">
                                    </label>
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Oraș</span>
                                        <input class="field" x-model="register.city" placeholder="ex. Brașov">
                                    </label>
                                </div>
                            </template>

                            <template x-if="accountType==='client'">
                                <label>
                                    <span class="block mb-1.5 text-sm font-bold">Telefon (opțional)</span>
                                    <input class="field" type="tel" x-model="register.phone" placeholder="0722 123 456">
                                </label>
                            </template>

                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Parolă</span>
                                <div class="relative">
                                    <input class="field pr-14" :type="showPassword ? 'text' : 'password'" x-model="register.password" placeholder="minim 8 caractere" autocomplete="new-password" minlength="8" required>
                                    <button type="button" @click="showPassword=!showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 font-bold text-sm text-vermilion" x-text="showPassword ? 'ascunde' : 'arată'"></button>
                                </div>
                            </label>

                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Confirmă parola</span>
                                <input class="field" :type="showPassword ? 'text' : 'password'" x-model="register.password_confirmation" placeholder="repetă parola" autocomplete="new-password" minlength="8" required>
                            </label>

                            <template x-if="accountType==='client'">
                                <div class="rounded-2xl bg-mint border border-forest/20 p-4">
                                    <p class="font-bold text-forest">Ce primești în cont?</p>
                                    <p class="mt-1 text-sm text-ink-soft">Biletele tale, comenzile, punctele bonus, cardurile cadou, recenziile și preferințele pentru recomandări.</p>
                                </div>
                            </template>

                            <template x-if="accountType==='venue'">
                                <div class="rounded-2xl bg-rose border border-vermilion/20 p-4">
                                    <p class="font-bold text-vermilion">Contul de locație poate necesita aprobare.</p>
                                    <p class="mt-1 text-sm text-ink-soft">După trimitere, echipa poate verifica locația și configura accesul la dashboard.</p>
                                </div>
                            </template>

                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" x-model="register.terms" class="mt-1 w-5 h-5 accent-vermilion" required>
                                <span>Accept <a href="/termeni" class="font-bold text-vermilion underline-wobble">Termenii și condițiile</a> și <a href="/confidentialitate" class="font-bold text-vermilion underline-wobble">Politica de confidențialitate</a>.</span>
                            </label>

                            <label class="flex items-start gap-3 text-sm" x-show="accountType==='client'">
                                <input type="checkbox" x-model="register.newsletter" class="mt-1 w-5 h-5 accent-vermilion">
                                <span>Vreau să primesc recomandări, oferte și idei de activități în newsletter.</span>
                            </label>

                            <button type="submit" :disabled="submitting" class="w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                <span x-show="!submitting" x-text="accountType==='client' ? 'Creează cont client' : 'Solicită cont de locație'"></span>
                                <span x-show="submitting" x-cloak>Se procesează…</span>
                            </button>
                        </form>

                        <div class="mt-6 pt-5 border-t border-ink/10 flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span class="text-ink-soft" x-text="mode==='login' ? 'Nu ai cont?' : 'Ai deja cont?'"></span>
                            <button @click="mode = mode==='login' ? 'register' : 'login'; message=''" class="font-bold text-vermilion underline-wobble" x-text="mode==='login' ? 'Creează unul acum' : 'Intră în cont'"></button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
    <div class="grid lg:grid-cols-[.85fr_1.15fr] gap-10 items-start">
        <div class="lg:sticky lg:top-28">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">DE CE CONT</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Contul corect pentru rolul corect.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Clientul are nevoie să-și găsească rapid biletele și beneficiile. Locația are nevoie de vânzări, check-in, rapoarte și administrare.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <article class="rounded-3xl border-2 border-ink/10 bg-paper-2/65 p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">CLIENT</p>
                <h3 class="mt-3 font-display text-3xl font-bold">Biletele mele</h3>
                <p class="mt-2 text-ink-soft">PDF-uri, QR-uri, calendar, statusuri și detalii de acces.</p>
            </article>
            <article class="rounded-3xl border-2 border-ink/10 bg-mint p-6">
                <p class="font-mono text-xs tracking-[.18em] text-forest">CLIENT</p>
                <h3 class="mt-3 font-display text-3xl font-bold">Puncte bonus</h3>
                <p class="mt-2 text-ink-soft">Sold, istoric, folosire în comenzi viitoare și beneficii.</p>
            </article>
            <article class="rounded-3xl border-2 border-ink/10 bg-ink p-6 text-paper">
                <p class="font-mono text-xs tracking-[.18em] text-ochre">LOCAȚIE</p>
                <h3 class="mt-3 font-display text-3xl font-bold">Dashboard</h3>
                <p class="mt-2 text-paper/60">Activități, bilete, comenzi, clienți, staff și recenzii.</p>
            </article>
            <article class="rounded-3xl border-2 border-ink/10 bg-paper-2/65 p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">LOCAȚIE</p>
                <h3 class="mt-3 font-display text-3xl font-bold">QR check-in</h3>
                <p class="mt-2 text-ink-soft">Validare bilete la intrare și statusuri clare pentru fiecare cod.</p>
            </article>
        </div>
    </div>
</section>

<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Întrebări despre conturi</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Am cumpărat fără cont. Cum intru?', 'Dacă ai cumpărat fără cont, poți recupera comanda folosind emailul și numărul comenzii. În unele cazuri, contul poate fi creat automat după checkout și trebuie doar să setezi parola.'],
            ['Login-ul de client este diferit de login-ul pentru locații?', 'Da. Clienții intră pentru bilete, comenzi și puncte. Locațiile intră pentru administrarea activităților, comenzilor, scanărilor și rapoartelor.'],
            ['Ce fac dacă linkul de resetare a expirat?', 'Soliciți un link nou din pagina de resetare parolă. Linkurile temporare expiră din motive de securitate.'],
            ['Cum obțin cont de locație?', 'Poți selecta Locație și Creează cont sau poți merge în pagina Pentru locații. Contul poate necesita verificare înainte de activare.'],
        ]; foreach ($faqs as $i => $faq): ?>
            <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                <button @click="open=open===<?= $i ?>?null:<?= $i ?>" class="w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
                    <span class="font-display text-2xl sm:text-3xl font-bold"><?= htmlspecialchars($faq[0]) ?></span>
                    <span class="text-3xl font-bold" x-text="open===<?= $i ?>?'−':'+'"></span>
                </button>
                <div x-show="open===<?= $i ?>" x-collapse class="px-5 sm:px-6 pb-6 text-ink-soft leading-relaxed"><?= htmlspecialchars($faq[1]) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

</main>

<script>
function authPage(initial) {
    return {
        accountType: initial.accountType || 'client',
        mode: initial.mode || 'login',
        redirectAfter: initial.redirectAfter || '/cont',
        showPassword: false,
        submitting: false,
        message: '',
        messageType: 'error',
        login: { email: (initial.prefillEmail || ''), password: '', remember: true },
        twofa: { required: false, challenge: '', code: '' },
        register: {
            first_name: '', last_name: '',
            contact_name: '', venue_name: '',
            email: (initial.prefillEmail || ''), phone: '', city: '',
            password: '', password_confirmation: '',
            terms: false, newsletter: true,
        },

        init() {
            // Already authenticated → skip the form and go to the account.
            // auth.js may boot after Alpine, so retry on its init/login events.
            const go = () => {
                if (window.BileteOnlineAuth
                    && typeof BileteOnlineAuth.isLoggedIn === 'function'
                    && BileteOnlineAuth.isLoggedIn()) {
                    const isOrg = BileteOnlineAuth.isOrganizer && BileteOnlineAuth.isOrganizer();
                    window.location.replace(isOrg ? '/organizator/panou' : (this.redirectAfter || '/cont'));
                    return true;
                }
                return false;
            };
            if (! go()) {
                window.addEventListener('bileteonline:auth:init', go);
                window.addEventListener('bileteonline:auth:login', go);
                setTimeout(go, 400);
            }
        },

        heroTitle() {
            if (this.accountType === 'venue') return this.mode === 'login' ? 'Intră în dashboard-ul locației.' : 'Listează-ți locația.';
            return this.mode === 'login' ? 'Intră în contul tău.' : 'Creează cont pentru biletele tale.';
        },
        heroDescription() {
            if (this.accountType === 'venue') return this.mode === 'login'
                ? 'Acces pentru locații, organizatori și staff: activități, bilete, comenzi, scanări, recenzii și rapoarte.'
                : 'Solicită cont pentru locația ta și transformă activitățile în bilete online cu QR, pagini SEO și dashboard.';
            return this.mode === 'login'
                ? 'Găsește rapid biletele, comenzile, punctele bonus, cardurile cadou și preferințele tale.'
                : 'Un cont client îți păstrează biletele, comenzile, punctele bonus, recenziile și cardurile cadou într-un singur loc.';
        },
        formTitle() {
            if (this.accountType === 'venue') return this.mode === 'login' ? 'Login locație' : 'Solicită cont locație';
            return this.mode === 'login' ? 'Login client' : 'Cont client nou';
        },
        formDescription() {
            if (this.accountType === 'venue') return this.mode === 'login'
                ? 'Intră cu emailul de administrator sau staff primit pentru locația ta.'
                : 'Trimite detaliile locației. Conturile de locație pot fi verificate înainte de activare.';
            return this.mode === 'login'
                ? 'Folosește emailul cu care ai cumpărat sau cu care ți-ai creat contul.'
                : 'Creează cont ca să ai acces la bilete, comenzi, puncte bonus și carduri cadou.';
        },

        showMessage(msg, type) {
            this.message = msg;
            this.messageType = type || 'error';
        },

        async submitLogin() {
            if (typeof BileteOnlineAuth === 'undefined') {
                this.showMessage('Sistemul de autentificare nu este încărcat. Reîncarcă pagina.', 'error');
                return;
            }
            this.message = '';
            this.submitting = true;
            try {
                const fn = this.accountType === 'venue'
                    ? BileteOnlineAuth.loginOrganizer.bind(BileteOnlineAuth)
                    : BileteOnlineAuth.loginCustomer.bind(BileteOnlineAuth);
                const result = await fn(this.login.email.trim(), this.login.password);

                // 2FA challenge — accountType=client only; show the code form instead of finishing login.
                if (result && result.success && result.requires2fa) {
                    this.twofa.required = true;
                    this.twofa.challenge = result.challenge;
                    this.twofa.code = '';
                    this.submitting = false;
                    return;
                }

                if (result && result.success) {
                    this.showMessage('Conectare reușită. Te redirecționăm…', 'success');
                    const target = this.accountType === 'venue' ? '/organizator/panou' : (this.redirectAfter || '/cont');
                    setTimeout(() => { window.location.href = target; }, 500);
                } else {
                    this.showMessage((result && result.message) || 'Email sau parolă incorecte.', 'error');
                    this.submitting = false;
                }
            } catch (e) {
                this.showMessage('Eroare la conectare. Încearcă din nou.', 'error');
                this.submitting = false;
            }
        },

        async submit2faCode() {
            if (typeof BileteOnlineAuth === 'undefined' || ! this.twofa.challenge) {
                this.showMessage('Sesiunea a expirat. Reia autentificarea.', 'error');
                this.cancel2fa();
                return;
            }
            if (! (this.twofa.code || '').trim()) {
                this.showMessage('Introdu codul.', 'error');
                return;
            }
            this.submitting = true;
            try {
                const r = await BileteOnlineAuth.finishCustomer2faLogin(this.twofa.challenge, this.twofa.code.trim());
                if (r && r.success) {
                    this.showMessage('Cod corect. Te redirecționăm…', 'success');
                    const target = this.redirectAfter || '/cont';
                    setTimeout(() => { window.location.href = target; }, 500);
                } else {
                    this.showMessage((r && r.message) || 'Codul nu este valid.', 'error');
                    this.submitting = false;
                }
            } catch (e) {
                this.showMessage('Eroare la verificare.', 'error');
                this.submitting = false;
            }
        },

        cancel2fa() {
            this.twofa = { required: false, challenge: '', code: '' };
            this.submitting = false;
        },

        async submitRegister() {
            if (typeof BileteOnlineAuth === 'undefined') {
                this.showMessage('Sistemul de înregistrare nu este încărcat. Reîncarcă pagina.', 'error');
                return;
            }
            if (!this.register.terms) {
                this.showMessage('Trebuie să accepți termenii și condițiile.', 'error');
                return;
            }
            if (this.register.password !== this.register.password_confirmation) {
                this.showMessage('Parolele nu coincid.', 'error');
                return;
            }
            if ((this.register.password || '').length < 8) {
                this.showMessage('Parola trebuie să aibă minim 8 caractere.', 'error');
                return;
            }

            this.message = '';
            this.submitting = true;

            try {
                let payload, result;
                if (this.accountType === 'venue') {
                    payload = {
                        contact_name: this.register.contact_name.trim(),
                        venue_name: this.register.venue_name.trim(),
                        name: this.register.venue_name.trim(),
                        email: this.register.email.trim(),
                        phone: (this.register.phone || '').replace(/\s/g, ''),
                        city: (this.register.city || '').trim(),
                        password: this.register.password,
                        password_confirmation: this.register.password_confirmation,
                    };
                    result = await BileteOnlineAuth.registerOrganizer(payload);
                } else {
                    payload = {
                        first_name: this.register.first_name.trim(),
                        last_name: this.register.last_name.trim(),
                        email: this.register.email.trim(),
                        phone: (this.register.phone || '').replace(/\s/g, ''),
                        password: this.register.password,
                        password_confirmation: this.register.password_confirmation,
                        newsletter: !!this.register.newsletter,
                    };
                    result = await BileteOnlineAuth.registerCustomer(payload);
                }

                if (result && result.success) {
                    this.showMessage('Cont creat cu succes. Te redirecționăm…', 'success');
                    try {
                        if (window.EPASTracking && typeof EPASTracking.trackSignUp === 'function') {
                            EPASTracking.trackSignUp(this.accountType === 'venue' ? 'organizer' : 'email', { email: payload.email });
                        }
                    } catch (e) { /* tracking never breaks signup */ }
                    const target = this.accountType === 'venue' ? '/organizator/panou' : '/verify-email';
                    setTimeout(() => { window.location.href = target; }, 1200);
                } else {
                    this.showMessage((result && result.message) || 'Înregistrarea a eșuat.', 'error');
                    this.submitting = false;
                }
            } catch (e) {
                this.showMessage('Eroare la înregistrare. Încearcă din nou.', 'error');
                this.submitting = false;
            }
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
