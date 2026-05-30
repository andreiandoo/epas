<?php
/**
 * bilete.online — /verify-email
 *
 * Three-state page:
 *   1. Token in URL (?token=…&email=…) → automatically verify via
 *      POST /customer/verify-email, show success / failure card.
 *   2. No token (default landing after register) → show "check your inbox"
 *      pending state with resend button.
 *
 * All flows use BileteOnlineAPI.customer.verifyEmail / resendVerification
 * which are already wired through the proxy.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Verifică emailul — ' . SITE_NAME;
$pageDescription = 'Verifică emailul tău pentru a activa toate funcționalitățile contului bilete.online.';
$canonicalUrl    = SITE_URL . '/verify-email';
$noindex         = true;
$currentPage     = 'verify-email';
$cssBundle       = 'auth';

// Token + email are passed by the verification email link.
// We read them server-side too so the initial state of Alpine is correct
// even before the page hydrates — avoids a "pending" flash before
// "processing" kicks in.
$initialToken = isset($_GET['token']) ? preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $_GET['token']) : '';
$initialEmail = isset($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL) ? $_GET['email'] : '';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="verifyEmailPage(<?= htmlspecialchars(json_encode([
    'token' => $initialToken,
    'email' => $initialEmail,
]), ENT_QUOTES) ?>)" x-init="init()">

<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_84%_18%,rgba(232,69,39,.2),transparent_32%),radial-gradient(circle_at_14%_70%,rgba(30,74,61,.2),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.16),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span>
            <span class="text-ink">Verificare email</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[1fr_1.05fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">VERIFICARE EMAIL · CONT CLIENT</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]" x-text="heroTitle()"></h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed" x-text="heroSubtitle()"></p>

                <div class="mt-8 grid grid-cols-3 gap-3 max-w-2xl">
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 1</p><p class="font-display text-2xl font-bold">Email</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 2</p><p class="font-display text-2xl font-bold">Click link</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 3</p><p class="font-display text-2xl font-bold">Activ</p></div>
                </div>
            </div>

            <section class="relative" aria-label="Stare verificare email">
                <div class="absolute inset-x-8 top-8 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">

                    <!-- ===== PROCESSING (token present, in flight) ===== -->
                    <div x-show="state === 'processing'" class="p-8 sm:p-10 text-center">
                        <div class="mx-auto w-20 h-20 grid place-items-center mb-5">
                            <div class="w-16 h-16 border-4 border-ink/15 border-t-vermilion rounded-full animate-spin"></div>
                        </div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SE VERIFICĂ</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Se procesează…</h2>
                        <p class="mt-3 text-ink-soft">Verificăm linkul tău. Durează doar o secundă.</p>
                    </div>

                    <!-- ===== SUCCESS ===== -->
                    <div x-show="state === 'success'" x-cloak class="p-8 sm:p-10 text-center">
                        <div class="mx-auto w-20 h-20 grid place-items-center bg-mint border-2 border-forest/40 rounded-full mb-5">
                            <svg class="w-10 h-10 text-forest" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="font-mono text-xs tracking-[.18em] text-forest">EMAIL VERIFICAT</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Bravo!</h2>
                        <p class="mt-3 text-ink-soft">Contul tău este activ. Acum poți folosi toate funcționalitățile bilete.online.</p>

                        <div class="mt-7 flex flex-col sm:flex-row gap-3">
                            <a href="/cont" class="flex-1 rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Mergi la cont</a>
                            <a href="/categorii" class="flex-1 rounded-full border-2 border-ink px-6 py-4 font-bold hover:bg-ink hover:text-paper transition">Descoperă activități</a>
                        </div>
                    </div>

                    <!-- ===== ERROR (token invalid / expired) ===== -->
                    <div x-show="state === 'error'" x-cloak class="p-8 sm:p-10 text-center">
                        <div class="mx-auto w-20 h-20 grid place-items-center bg-rose border-2 border-vermilion/40 rounded-full mb-5">
                            <svg class="w-10 h-10 text-vermilion" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <p class="font-mono text-xs tracking-[.18em] text-vermilion">VERIFICARE EȘUATĂ</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Linkul nu a mers</h2>
                        <p class="mt-3 text-ink-soft" x-text="errorMessage || 'Linkul de verificare este invalid sau a expirat. Solicită unul nou.'"></p>

                        <button @click="resend()" :disabled="resending" class="mt-7 w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                            <span x-show="!resending && !resent">Retrimite emailul de verificare</span>
                            <span x-show="resending" x-cloak>Se trimite…</span>
                            <span x-show="resent && !resending" x-cloak class="text-mint">✓ Email retrimis</span>
                        </button>

                        <p class="mt-4 text-sm text-ink-soft">
                            sau
                            <a href="/cont" class="font-bold text-vermilion underline-wobble">mergi la cont</a>
                            (unele funcționalități pot fi limitate fără email verificat)
                        </p>
                    </div>

                    <!-- ===== PENDING (default after registration) ===== -->
                    <div x-show="state === 'pending'" x-cloak class="p-8 sm:p-10">
                        <div class="mx-auto w-20 h-20 grid place-items-center bg-paper-2 border-2 border-ink/10 rounded-full mb-5">
                            <svg class="w-10 h-10 text-vermilion" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft text-center">VERIFICĂ INBOX-UL</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none text-center">Aproape gata.</h2>
                        <p class="mt-3 text-ink-soft text-center">
                            <template x-if="userEmail">
                                <span>Ți-am trimis un email de verificare la <strong x-text="userEmail" class="font-mono"></strong>.</span>
                            </template>
                            <template x-if="!userEmail">
                                <span>Ți-am trimis un email de verificare.</span>
                            </template>
                            <br>Apasă pe linkul din email pentru a activa contul.
                        </p>

                        <div x-show="resent" x-cloak class="mt-5 rounded-2xl bg-mint border-2 border-forest/40 p-4 text-sm">
                            <p class="font-bold text-forest">✓ Email retrimis cu succes</p>
                            <p class="mt-1 text-ink-soft">Verifică inbox-ul în câteva minute.</p>
                        </div>

                        <div class="mt-6 rounded-2xl bg-paper-2/60 border border-ink/10 p-5">
                            <p class="font-bold flex items-center gap-2">
                                <svg class="w-5 h-5 text-vermilion shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Nu ai primit emailul?
                            </p>
                            <ul class="mt-3 space-y-2 text-sm text-ink-soft">
                                <li class="flex items-start gap-2"><span class="text-vermilion shrink-0 mt-0.5">·</span> Verifică folderele <strong>Spam</strong> și <strong>Promoții</strong>.</li>
                                <li class="flex items-start gap-2"><span class="text-vermilion shrink-0 mt-0.5">·</span> Poate dura 1–2 minute să ajungă.</li>
                                <li class="flex items-start gap-2"><span class="text-vermilion shrink-0 mt-0.5">·</span> Verifică să fie corect emailul cu care te-ai înregistrat.</li>
                            </ul>
                        </div>

                        <button @click="resend()" :disabled="resending || resent" class="mt-5 w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                            <span x-show="!resending && !resent">Retrimite emailul</span>
                            <span x-show="resending" x-cloak>Se trimite…</span>
                            <span x-show="resent && !resending" x-cloak>Trimis ✓ — așteaptă 60s pentru următoarea încercare</span>
                        </button>

                        <a href="/cont" class="mt-3 block w-full text-center rounded-full border-2 border-ink/15 px-6 py-3.5 font-bold hover:border-ink transition">
                            Mergi la cont
                        </a>

                        <p class="mt-4 text-xs text-center text-ink-soft">
                            Poți folosi contul și fără verificare, dar unele funcționalități necesită email verificat.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

</main>

<script>
function verifyEmailPage(initial) {
    return {
        // 'processing' | 'success' | 'error' | 'pending'
        state: 'pending',
        token: (initial.token || ''),
        email: (initial.email || ''),
        userEmail: '',
        errorMessage: '',
        resending: false,
        resent: false,
        resendCooldown: 60_000,

        async init() {
            // Show cached customer email on the pending state so the message
            // is personalized ("...la andrei@example.ro").
            try {
                if (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()) {
                    const u = BileteOnlineAuth.getUser && BileteOnlineAuth.getUser();
                    if (u && u.email) this.userEmail = u.email;
                }
            } catch (e) {}

            if (this.token && this.email) {
                this.state = 'processing';
                await this.verify();
            }
        },

        async verify() {
            if (typeof BileteOnlineAPI === 'undefined' || ! BileteOnlineAPI.customer || ! BileteOnlineAPI.customer.verifyEmail) {
                this.state = 'error';
                this.errorMessage = 'Nu am putut încărca sistemul de verificare. Reîncarcă pagina.';
                return;
            }
            try {
                const r = await BileteOnlineAPI.customer.verifyEmail(this.token, this.email);
                if (r && r.success) {
                    // Refresh cached customer data so isLoggedIn user reflects verified state
                    try {
                        if (r.data && r.data.customer && BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn() && BileteOnlineAuth.KEYS) {
                            localStorage.setItem(BileteOnlineAuth.KEYS.CUSTOMER_DATA, JSON.stringify(r.data.customer));
                        }
                    } catch (e) {}
                    this.state = 'success';
                } else {
                    this.state = 'error';
                    this.errorMessage = (r && r.message) || 'Linkul de verificare este invalid sau a expirat.';
                }
            } catch (e) {
                this.state = 'error';
                this.errorMessage = (e && (e.message || (e.data && e.data.message))) || 'A apărut o eroare la verificare. Reîncearcă mai târziu.';
            }
        },

        async resend() {
            if (this.resending || this.resent) return;
            // Need an authenticated session OR an email we can resend to.
            // Falls back to the email from the URL token link.
            let email = this.userEmail || this.email;
            if (! email) {
                try {
                    if (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()) {
                        const u = BileteOnlineAuth.getUser && BileteOnlineAuth.getUser();
                        email = (u && u.email) || '';
                    }
                } catch (e) {}
            }
            if (! email) {
                if (window.BileteOnlineNotifications && BileteOnlineNotifications.error) {
                    BileteOnlineNotifications.error('Autentifică-te ca să-ți putem retrimite emailul.');
                }
                return;
            }

            this.resending = true;
            try {
                if (typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.customer && BileteOnlineAPI.customer.resendVerification) {
                    await BileteOnlineAPI.customer.resendVerification(email);
                }
                this.resent = true;
                if (window.BileteOnlineNotifications && BileteOnlineNotifications.success) {
                    BileteOnlineNotifications.success('Email de verificare retrimis. Verifică inbox-ul.');
                }
                // 60s cooldown before next attempt to avoid abuse
                setTimeout(() => { this.resent = false; }, this.resendCooldown);
            } catch (e) {
                if (window.BileteOnlineNotifications && BileteOnlineNotifications.error) {
                    BileteOnlineNotifications.error('Nu am putut retrimite emailul. Reîncearcă în câteva minute.');
                }
            }
            this.resending = false;
        },

        heroTitle() {
            if (this.state === 'success') return 'Email verificat ✓';
            if (this.state === 'error') return 'Linkul nu e valid.';
            if (this.state === 'processing') return 'Se verifică…';
            return 'Verifică-ți emailul.';
        },
        heroSubtitle() {
            if (this.state === 'success') return 'Contul tău este complet activ — descoperă, rezervă și intră cu QR.';
            if (this.state === 'error') return 'Linkurile de verificare expiră din motive de securitate. Solicită unul nou și încearcă din nou.';
            if (this.state === 'processing') return 'Verificăm linkul tău. Te rugăm să nu închizi pagina.';
            return 'Ți-am trimis un email cu un link de verificare. Un click și contul tău e gata.';
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
