<?php
/**
 * bilete.online — /parola-uitata
 *
 * Resetare parolă pentru contul de client. Pentru locații / organizatori,
 * fluxul există separat sub /organizator/forgot-password.
 *
 * Fluxul:
 *  1. Utilizatorul introduce emailul.
 *  2. Apel POST /customer/forgot-password.
 *  3. Indiferent de răspuns, afișăm state-ul "email trimis" (evităm
 *     email-enumeration). Erorile reale se loghează doar pe server.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Ai uitat parola? — ' . SITE_NAME;
$pageDescription = 'Resetează parola contului tău bilete.online. Trimitem un link sigur pe emailul cu care te-ai înregistrat.';
$canonicalUrl    = SITE_URL . '/parola-uitata';
$noindex         = true;
$currentPage     = 'forgot-password';
$cssBundle       = 'auth';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="forgotPasswordPage()">

<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_18%,rgba(232,69,39,.18),transparent_30%),radial-gradient(circle_at_18%_70%,rgba(30,74,61,.18),transparent_34%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span>
            <a href="/autentificare" class="hover:text-vermilion">Autentificare</a><span>/</span>
            <span class="text-ink">Parolă uitată</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[1fr_.9fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">RESETARE PAROLĂ · CLIENT</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Ai uitat parola?</h1>
                <p class="mt-6 max-w-xl text-xl text-ink-soft leading-relaxed">Nu-ți face griji — îți trimitem un link sigur pe emailul contului. Are valabilitate limitată din motive de securitate.</p>

                <div class="mt-8 grid sm:grid-cols-3 gap-3 max-w-xl">
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4">
                        <p class="font-mono text-[10px] tracking-[.18em] text-vermilion">PASUL 1</p>
                        <p class="mt-1 font-display text-xl font-bold">Email</p>
                    </div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4">
                        <p class="font-mono text-[10px] tracking-[.18em] text-vermilion">PASUL 2</p>
                        <p class="mt-1 font-display text-xl font-bold">Link</p>
                    </div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4">
                        <p class="font-mono text-[10px] tracking-[.18em] text-vermilion">PASUL 3</p>
                        <p class="mt-1 font-display text-xl font-bold">Parolă nouă</p>
                    </div>
                </div>
            </div>

            <section class="relative" aria-label="Trimite link de resetare">
                <div class="absolute inset-x-8 top-8 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">

                    <!-- Initial form -->
                    <div x-show="!sent" class="p-6 sm:p-8">
                        <a href="/autentificare" class="inline-flex items-center gap-2 text-sm text-ink-soft hover:text-vermilion">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                            Înapoi la autentificare
                        </a>
                        <h2 class="mt-4 font-display text-4xl sm:text-5xl font-bold leading-none">Trimite link</h2>
                        <p class="mt-3 text-ink-soft">Introdu emailul contului. Dacă există un cont asociat, vei primi un link de resetare în câteva minute.</p>

                        <div x-show="error" x-cloak class="mt-5 rounded-2xl border-2 border-vermilion bg-vermilion/10 text-vermilion px-4 py-3 text-sm font-medium" x-text="error"></div>

                        <form @submit.prevent="submit()" class="mt-6 space-y-4">
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Email</span>
                                <input type="email" x-model="email" class="field" placeholder="email@exemplu.ro" autocomplete="email" required>
                            </label>
                            <button type="submit" :disabled="submitting" class="w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                <span x-show="!submitting">Trimite link de resetare</span>
                                <span x-show="submitting" x-cloak>Se trimite…</span>
                            </button>
                        </form>

                        <p class="mt-6 pt-5 border-t border-ink/10 text-sm text-ink-soft">
                            Ai uitat și emailul folosit?
                            <a href="/recuperare-comanda" class="font-bold text-vermilion underline-wobble">Caută comanda după număr</a>
                        </p>
                    </div>

                    <!-- Success state -->
                    <div x-show="sent" x-cloak class="p-6 sm:p-8 text-center">
                        <div class="mx-auto w-20 h-20 grid place-items-center bg-mint border-2 border-forest/30 rounded-full mb-5">
                            <svg class="w-10 h-10 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <p class="font-mono text-xs tracking-[.18em] text-forest">EMAIL TRIMIS</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Verifică inbox-ul</h2>
                        <p class="mt-3 text-ink-soft">Am trimis instrucțiuni de resetare la:</p>
                        <p class="mt-1 font-bold font-mono text-ink" x-text="sentEmail"></p>

                        <div class="mt-6 text-left rounded-2xl border-2 border-ink/10 bg-paper-2/65 p-5">
                            <p class="font-bold text-ink">Nu ai primit nimic?</p>
                            <ul class="mt-3 space-y-2 text-sm text-ink-soft">
                                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-vermilion shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg> Verifică folderul Spam / Junk.</li>
                                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-vermilion shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg> Confirmă că emailul scris este corect.</li>
                                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-vermilion shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg> Poate dura 1-2 minute să ajungă.</li>
                            </ul>
                        </div>

                        <div class="mt-6 flex flex-col sm:flex-row gap-2">
                            <button @click="resend()" :disabled="resending" class="flex-1 rounded-full border-2 border-ink px-5 py-3 font-bold text-ink hover:bg-ink hover:text-paper transition disabled:opacity-60">
                                <span x-show="!resending && !resent">Retrimite emailul</span>
                                <span x-show="resending" x-cloak>Se retrimite…</span>
                                <span x-show="resent" x-cloak class="text-forest">Email retrimis ✓</span>
                            </button>
                            <a href="/autentificare" class="flex-1 rounded-full bg-vermilion text-paper px-5 py-3 font-bold text-center hover:bg-vermilion-d transition">Înapoi la login</a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

</main>

<script>
function forgotPasswordPage() {
    return {
        email: '',
        sent: false,
        sentEmail: '',
        submitting: false,
        resending: false,
        resent: false,
        error: '',
        async submit() {
            this.error = '';
            const email = this.email.trim();
            if (!email) { this.error = 'Te rugăm să introduci emailul.'; return; }

            this.submitting = true;
            try {
                if (typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.post) {
                    await BileteOnlineAPI.post('/customer/forgot-password', { email });
                }
            } catch (e) {
                // Always show success — never leak whether the email exists.
            }
            this.sentEmail = email;
            this.sent = true;
            this.submitting = false;
        },
        async resend() {
            if (!this.sentEmail) return;
            this.resending = true;
            this.resent = false;
            try {
                if (typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.post) {
                    await BileteOnlineAPI.post('/customer/forgot-password', { email: this.sentEmail });
                }
            } catch (e) {}
            this.resending = false;
            this.resent = true;
            setTimeout(() => { this.resent = false; }, 30000);
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
