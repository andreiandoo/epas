<?php
/**
 * bilete.online — /recuperare-comanda
 *
 * Guest order recovery. The customer enters their order number + the email
 * used at checkout; we verify the pair server-side (POST /customer/recover-order),
 * re-send the tickets email, and — when logged in — let them attach the
 * order to their account (POST /customer/recover-order/attach).
 *
 * All verification happens on the backend; this page is just the form +
 * result UI. See OrderRecoveryController for the security model.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Recuperează comanda — ' . SITE_NAME;
$pageDescription = 'Nu găsești biletele sau emailul de confirmare? Introdu numărul comenzii și emailul folosit la cumpărare ca să-ți retrimitem biletele și să atașezi comanda la cont.';
$canonicalUrl    = SITE_URL . '/recuperare-comanda';
$noindex         = true;
$currentPage     = 'recuperare-comanda';
$cssBundle       = 'auth';

// Prefill order number from query (?order=MKT-XXXX) — handy from emails.
$prefillOrder = isset($_GET['order']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string) $_GET['order']) : '';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="recoverOrderPage(<?= htmlspecialchars(json_encode([
    'prefillOrder' => $prefillOrder,
]), ENT_QUOTES) ?>)">

<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_84%_16%,rgba(232,69,39,.2),transparent_30%),radial-gradient(circle_at_14%_74%,rgba(30,74,61,.18),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.16),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span>
            <a href="/ajutor" class="hover:text-vermilion">Ajutor</a><span>/</span>
            <span class="text-ink">Recuperare comandă</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[1fr_1.05fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">RECUPERARE COMANDĂ · BILETE QR</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Nu-ți găsești biletele?</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Introdu numărul comenzii și emailul folosit la cumpărare. Îți retrimitem biletele pe email și, dacă ești logat, poți atașa comanda la contul tău.
                </p>
                <div class="mt-8 grid grid-cols-3 gap-3 max-w-2xl">
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 1</p><p class="font-display text-2xl font-bold">Cod + email</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 2</p><p class="font-display text-2xl font-bold">Verificare</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 3</p><p class="font-display text-2xl font-bold">Bilete</p></div>
                </div>
            </div>

            <section class="relative" aria-label="Formular recuperare comandă">
                <div class="absolute inset-x-8 top-8 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">

                    <!-- FORM -->
                    <div x-show="!result" class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CAUTĂ COMANDA</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Recuperare</h2>
                        <p class="mt-3 text-ink-soft">Datele trebuie să corespundă comenzii originale.</p>

                        <div x-show="error" x-cloak class="mt-5 rounded-2xl border-2 border-vermilion bg-vermilion/10 text-vermilion px-4 py-3 text-sm font-medium" x-text="error"></div>

                        <form @submit.prevent="recover()" class="mt-6 space-y-4">
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Număr comandă</span>
                                <input class="field font-mono uppercase" x-model="orderNumber" placeholder="ex. MKT-W08ABJWH" autocomplete="off" required>
                            </label>
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Email folosit la comandă</span>
                                <input class="field" type="email" x-model="email" placeholder="email@exemplu.ro" autocomplete="email" required>
                            </label>
                            <button type="submit" :disabled="submitting" class="w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                <span x-show="!submitting">Caută și retrimite biletele</span>
                                <span x-show="submitting" x-cloak>Se caută…</span>
                            </button>
                        </form>

                        <p class="mt-6 pt-5 border-t border-ink/10 text-sm text-ink-soft">
                            Ai deja cont?
                            <a href="/autentificare" class="font-bold text-vermilion underline-wobble">Intră în cont</a>
                            și vezi toate comenzile în <a href="/cont/comenzi" class="font-bold text-vermilion underline-wobble">Comenzile mele</a>.
                        </p>
                    </div>

                    <!-- RESULT -->
                    <div x-show="result" x-cloak class="p-6 sm:p-8">
                        <div class="mx-auto w-16 h-16 grid place-items-center bg-mint border-2 border-forest/30 rounded-full mb-4">
                            <svg class="w-8 h-8 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="font-mono text-xs tracking-[.18em] text-forest">COMANDĂ GĂSITĂ</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none" x-text="result && result.order ? result.order.order_number : ''"></h2>

                        <div class="mt-5 rounded-2xl border-2 border-ink/10 bg-paper-2/60 p-5 space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><span class="text-ink-soft">Activitate / eveniment</span><strong x-text="(result && result.order && result.order.event_name) || '—'"></strong></div>
                            <div class="flex justify-between gap-3"><span class="text-ink-soft">Bilete</span><strong x-text="result && result.order ? result.order.ticket_count : 0"></strong></div>
                            <div class="flex justify-between gap-3"><span class="text-ink-soft">Total</span><strong x-text="result && result.order ? (Number(result.order.total).toFixed(2) + ' ' + result.order.currency) : ''"></strong></div>
                            <div class="flex justify-between gap-3"><span class="text-ink-soft">Status</span><strong x-text="statusLabel()"></strong></div>
                        </div>

                        <div x-show="result && result.email_resent" class="mt-4 rounded-2xl bg-mint border border-forest/20 p-4 text-sm">
                            <p class="font-bold text-forest">Ți-am retrimis biletele pe email</p>
                            <p class="mt-1 text-ink-soft">Verifică inbox-ul (și folderul Spam) pentru <strong x-text="email"></strong>.</p>
                        </div>

                        <!-- Attach / account paths -->
                        <div class="mt-5 space-y-3">
                            <!-- Logged in: attach -->
                            <template x-if="isLoggedIn && !attached">
                                <button @click="attach()" :disabled="attaching" class="w-full rounded-full bg-ink text-paper px-6 py-4 font-bold hover:bg-vermilion transition disabled:opacity-60">
                                    <span x-show="!attaching">Atașează comanda la contul meu</span>
                                    <span x-show="attaching" x-cloak>Se atașează…</span>
                                </button>
                            </template>
                            <template x-if="attached">
                                <div class="rounded-2xl bg-mint border border-forest/20 p-4 text-sm">
                                    <p class="font-bold text-forest">Comanda a fost atașată contului tău ✓</p>
                                    <a href="/cont/comenzi" class="mt-2 inline-flex font-bold text-forest underline-wobble">Vezi în Comenzile mele →</a>
                                </div>
                            </template>

                            <!-- Not logged in -->
                            <template x-if="!isLoggedIn">
                                <div class="rounded-2xl border-2 border-ink/10 bg-paper-2/60 p-4">
                                    <p class="font-bold">Vrei comanda în contul tău?</p>
                                    <p class="mt-1 text-sm text-ink-soft" x-text="result && result.has_account ? 'Intră în cont cu acest email, apoi revino aici pentru a o atașa.' : 'Creează-ți un cont cu acest email ca să ai biletele mereu la îndemână.'"></p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <a :href="'/autentificare?email=' + encodeURIComponent(email)" class="rounded-full bg-vermilion text-paper px-4 py-2 text-sm font-bold">Intră în cont</a>
                                        <a x-show="!(result && result.has_account)" :href="'/inregistrare?email=' + encodeURIComponent(email)" class="rounded-full border-2 border-ink px-4 py-2 text-sm font-bold">Creează cont</a>
                                    </div>
                                </div>
                            </template>

                            <button @click="reset()" class="w-full rounded-full border-2 border-ink/15 px-6 py-3 font-bold hover:border-ink transition">Caută altă comandă</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<!-- HELP -->
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">ÎNTREBĂRI</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Despre recuperarea comenzii</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Unde găsesc numărul comenzii?', 'Este în emailul de confirmare primit după plată (format MKT-XXXXXXXX sau ACT-XXXXXXXX). Dacă l-ai pierdut, caută în inbox „bilete.online" sau verifică folderul Spam.'],
            ['Ce email folosesc?', 'Emailul cu care ai făcut comanda — cel la care ai cerut să primești biletele. Trebuie să corespundă exact comenzii.'],
            ['Am cumpărat fără cont. Pot atașa comanda?', 'Da. Creează-ți un cont cu același email, intră în cont, apoi revino pe această pagină și apasă „Atașează comanda la contul meu".'],
            ['Nu primesc emailul cu biletele. Ce fac?', 'Verifică Spam / Promoții, apoi reîncearcă aici. Dacă tot nu apare, scrie-ne din pagina de Contact cu numărul comenzii.'],
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
    <div class="mt-8 text-center">
        <a href="/contact" class="inline-flex rounded-full bg-ink text-paper px-6 py-4 font-bold hover:bg-vermilion transition">Tot nu găsești comanda? Scrie-ne</a>
    </div>
</section>

</main>

<script>
function recoverOrderPage(initial) {
    return {
        orderNumber: (initial.prefillOrder || ''),
        email: '',
        submitting: false,
        attaching: false,
        attached: false,
        error: '',
        result: null,
        isLoggedIn: false,

        init() {
            try { this.isLoggedIn = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) {}
        },

        statusLabel() {
            if (! this.result || ! this.result.order) return '';
            const map = { paid: 'Plătită', confirmed: 'Confirmată', completed: 'Finalizată', pending: 'În așteptare', cancelled: 'Anulată', refunded: 'Rambursată' };
            return map[this.result.order.status] || this.result.order.status;
        },

        async recover() {
            this.error = '';
            const orderNumber = (this.orderNumber || '').trim();
            const email = (this.email || '').trim();
            if (! orderNumber || ! email) { this.error = 'Completează numărul comenzii și emailul.'; return; }

            this.submitting = true;
            try {
                const resp = await BileteOnlineAPI.post('/customer/recover-order', {
                    order_number: orderNumber,
                    email: email,
                    resend: true,
                });
                if (resp && resp.success) {
                    this.result = resp.data;
                    this.attached = !!(resp.data && resp.data.order && resp.data.order.is_attached);
                } else {
                    this.error = (resp && resp.message) || 'Nu am găsit comanda. Verifică datele.';
                }
            } catch (e) {
                this.error = (e && e.message) || 'Nu am găsit o comandă cu aceste date. Verifică numărul comenzii și emailul.';
            }
            this.submitting = false;
        },

        async attach() {
            if (! this.isLoggedIn) return;
            this.attaching = true;
            this.error = '';
            try {
                const resp = await BileteOnlineAPI.post('/customer/recover-order/attach', {
                    order_number: (this.orderNumber || '').trim(),
                    email: (this.email || '').trim(),
                });
                if (resp && resp.success) {
                    this.attached = true;
                    if (typeof BileteOnlineNotifications !== 'undefined') {
                        BileteOnlineNotifications.success('Comanda a fost atașată contului tău.');
                    }
                } else {
                    this.error = (resp && resp.message) || 'Nu am putut atașa comanda.';
                }
            } catch (e) {
                this.error = (e && e.message) || 'Nu am putut atașa comanda.';
            }
            this.attaching = false;
        },

        reset() {
            this.result = null;
            this.attached = false;
            this.error = '';
            this.orderNumber = '';
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
