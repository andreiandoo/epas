<?php
/**
 * bilete.online — /voucher (Verificare card cadou)
 *
 * Public page. Customer enters a gift card code (and optional PIN when
 * the card has one) → we call POST /customer/gift-cards/check-balance
 * and display sold + validitate + status. No auth required.
 *
 * The existing GiftCardController::checkBalance does the validation
 * (code masked in response, marketplace-scoped, PIN required when set).
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Verifică un card cadou — ' . SITE_NAME;
$pageDescription = 'Verifică soldul disponibil și valabilitatea unui card cadou bilete.online. Introdu codul și, dacă este necesar, PIN-ul.';
$canonicalUrl    = SITE_URL . '/voucher';
$noindex         = true;
$currentPage     = 'voucher';
$cssBundle       = 'auth';

// Prefill code from query (?cod=XXXX) — useful from emails.
$prefillCode = isset($_GET['cod']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string) $_GET['cod']) : '';
if (! $prefillCode && isset($_GET['code'])) {
    $prefillCode = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $_GET['code']);
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="voucherPage(<?= htmlspecialchars(json_encode([
    'prefillCode' => $prefillCode,
]), ENT_QUOTES) ?>)">

<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_84%_16%,rgba(218,154,51,.22),transparent_32%),radial-gradient(circle_at_14%_74%,rgba(30,74,61,.18),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(232,69,39,.14),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span>
            <a href="/ajutor" class="hover:text-vermilion">Ajutor</a><span>/</span>
            <span class="text-ink">Verifică voucher</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[1fr_1.05fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre bg-paper/70">CARD CADOU · VOUCHER · SOLD</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Verifică un card cadou.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Introdu codul cardului cadou ca să vezi cât mai are disponibil și până când poate fi folosit. Pentru cardurile cu PIN, ai nevoie și de PIN.
                </p>
                <div class="mt-8 grid grid-cols-3 gap-3 max-w-2xl">
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 1</p><p class="font-display text-2xl font-bold">Cod</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 2</p><p class="font-display text-2xl font-bold">Sold</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">PASUL 3</p><p class="font-display text-2xl font-bold">Folosește</p></div>
                </div>
            </div>

            <section class="relative" aria-label="Formular verificare voucher">
                <div class="absolute inset-x-8 top-8 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">

                    <!-- FORM -->
                    <div x-show="!result" class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">VERIFICĂ CODUL</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Card cadou</h2>
                        <p class="mt-3 text-ink-soft">Codul apare pe cardul fizic sau în emailul de cadou.</p>

                        <div x-show="error" x-cloak class="mt-5 rounded-2xl border-2 border-vermilion bg-vermilion/10 text-vermilion px-4 py-3 text-sm font-medium" x-text="error"></div>

                        <form @submit.prevent="check()" class="mt-6 space-y-4">
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">Cod card cadou</span>
                                <input class="field font-mono uppercase tracking-wider" x-model="code" placeholder="ex. GIFT-2026-XXXX" autocomplete="off" required>
                            </label>
                            <label>
                                <span class="block mb-1.5 text-sm font-bold">PIN (dacă este necesar)</span>
                                <input class="field font-mono" x-model="pin" placeholder="opțional" autocomplete="off" inputmode="numeric">
                                <span class="mt-1.5 block text-xs text-ink-soft">Doar cardurile fizice au PIN, tipărit pe spatele cardului.</span>
                            </label>
                            <button type="submit" :disabled="submitting" class="w-full rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                <span x-show="!submitting">Verifică soldul</span>
                                <span x-show="submitting" x-cloak>Se verifică…</span>
                            </button>
                        </form>

                        <p class="mt-6 pt-5 border-t border-ink/10 text-sm text-ink-soft">
                            Nu ai încă un card cadou?
                            <a href="/card-cadou" class="font-bold text-vermilion underline-wobble">Cumpără unul</a>
                        </p>
                    </div>

                    <!-- RESULT -->
                    <div x-show="result" x-cloak class="p-6 sm:p-8">
                        <div class="mx-auto w-16 h-16 grid place-items-center rounded-full mb-4"
                             :class="result && result.is_usable ? 'bg-mint border-2 border-forest/30' : 'bg-rose border-2 border-vermilion/30'">
                            <svg x-show="result && result.is_usable" class="w-8 h-8 text-forest" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                            <svg x-show="result && !result.is_usable" class="w-8 h-8 text-vermilion" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <p class="font-mono text-xs tracking-[.18em]" :class="result && result.is_usable ? 'text-forest' : 'text-vermilion'" x-text="result && result.is_usable ? 'CARD VALABIL' : 'CARD INDISPONIBIL'"></p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none font-mono" x-text="result && result.code"></h2>

                        <div class="mt-6 rounded-2xl border-2 border-ink/10 bg-paper-2/60 p-5 grid sm:grid-cols-2 gap-4">
                            <div class="text-center sm:text-left">
                                <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">SOLD DISPONIBIL</p>
                                <p class="mt-1 font-display text-5xl font-bold text-vermilion" x-text="formatMoney(result && result.balance)"></p>
                                <p class="mt-1 text-xs text-ink-soft" x-text="(result && result.currency) || 'RON'"></p>
                            </div>
                            <div class="text-center sm:text-left sm:border-l-2 sm:border-dashed sm:border-ink/15 sm:pl-5">
                                <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">VALABIL PÂNĂ LA</p>
                                <p class="mt-1 font-display text-2xl font-bold leading-tight" x-text="formatDate(result && result.expires_at)"></p>
                                <p class="mt-1 text-xs" :class="(result && result.days_until_expiry > 30) ? 'text-ink-soft' : 'text-vermilion font-bold'" x-text="expiryLabel()"></p>
                            </div>
                        </div>

                        <div class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-paper-2/60 border border-ink/10 p-4">
                                <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">STATUS</p>
                                <p class="mt-1 font-bold" x-text="(result && result.status_label) || (result && result.status) || '—'"></p>
                            </div>
                            <div class="rounded-2xl bg-paper-2/60 border border-ink/10 p-4">
                                <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">VALOARE INIȚIALĂ</p>
                                <p class="mt-1 font-bold" x-text="formatMoney(result && result.initial_amount) + ' ' + ((result && result.currency) || 'RON')"></p>
                            </div>
                        </div>

                        <div x-show="result && result.is_usable" class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4 text-sm">
                            <p class="font-bold text-forest">Cardul poate fi folosit la checkout</p>
                            <p class="mt-1 text-ink-soft">La finalizarea unei comenzi, introdu codul în câmpul „Card cadou / voucher" și soldul se scade automat.</p>
                        </div>
                        <div x-show="result && !result.is_usable" class="mt-5 rounded-2xl bg-rose border border-vermilion/20 p-4 text-sm">
                            <p class="font-bold text-vermilion">Cardul nu poate fi folosit acum</p>
                            <p class="mt-1 text-ink-soft" x-text="unusableReason()"></p>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="/categorii" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Folosește la o comandă</a>
                            <button @click="reset()" class="rounded-full border-2 border-ink/15 px-5 py-3 font-bold hover:border-ink transition">Verifică alt card</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<!-- FAQs -->
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">ÎNTREBĂRI</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Cardul cadou bilete.online</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Unde găsesc codul cardului cadou?', 'Pentru cardurile digitale, codul apare în emailul de cadou. Pentru cele fizice, este tipărit pe card. PIN-ul apare doar pe cardurile fizice, pe spate, sub o folie de răzuit.'],
            ['Cum folosesc cardul la checkout?', 'La finalizarea unei comenzi, introdu codul în câmpul „Card cadou / voucher". Soldul disponibil se scade automat. Dacă valoarea comenzii e mai mare, plătești diferența cu cardul bancar.'],
            ['Cardul are expirare?', 'Cardurile cadou bilete.online au valabilitate de 12 luni de la emitere. După această dată, soldul rămas nu mai poate fi folosit.'],
            ['Pot folosi cardul în mai multe comenzi?', 'Da. Soldul scade pe fiecare folosire până la epuizare sau expirare. Verifică oricând balanța curentă aici.'],
            ['Cardul nu este valid. Ce fac?', 'Verifică să fi tastat codul corect (atenție la 0 / O sau 1 / I). Dacă tot nu merge, scrie-ne la <a href="/contact" class="font-bold text-vermilion underline-wobble">Contact</a> cu codul și emailul cu care a fost primit.'],
        ]; foreach ($faqs as $i => $faq): ?>
            <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                <button @click="open=open===<?= $i ?>?null:<?= $i ?>" class="w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
                    <span class="font-display text-2xl sm:text-3xl font-bold"><?= htmlspecialchars($faq[0]) ?></span>
                    <span class="text-3xl font-bold" x-text="open===<?= $i ?>?'−':'+'"></span>
                </button>
                <div x-show="open===<?= $i ?>" x-collapse class="px-5 sm:px-6 pb-6 text-ink-soft leading-relaxed"><?= $faq[1] /* contains trusted markup */ ?></div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="mt-8 text-center">
        <a href="/card-cadou" class="inline-flex rounded-full bg-ink text-paper px-6 py-4 font-bold hover:bg-vermilion transition">Cumpără un card cadou</a>
    </div>
</section>

</main>

<script>
function voucherPage(initial) {
    return {
        code: (initial.prefillCode || ''),
        pin: '',
        submitting: false,
        error: '',
        result: null,

        async check() {
            this.error = '';
            const code = (this.code || '').trim().toUpperCase();
            if (! code) { this.error = 'Introdu codul cardului.'; return; }

            this.submitting = true;
            try {
                const payload = { code };
                if ((this.pin || '').trim()) payload.pin = this.pin.trim();
                const resp = await BileteOnlineAPI.post('/customer/gift-cards/check-balance', payload);
                if (resp && resp.success) {
                    this.result = resp.data;
                } else {
                    this.error = (resp && resp.message) || 'Cardul nu poate fi verificat.';
                }
            } catch (e) {
                // BileteOnlineAPI throws APIError objects with .message / .data
                const msg = e && (e.message || (e.data && e.data.message));
                if (e && e.status === 404) {
                    this.error = 'Codul introdus nu este valid sau cardul nu există.';
                } else if (e && e.status === 403) {
                    this.error = 'PIN incorect. Verifică PIN-ul de pe cardul fizic.';
                } else {
                    this.error = msg || 'Cardul nu poate fi verificat.';
                }
            }
            this.submitting = false;
        },

        reset() {
            this.result = null;
            this.error = '';
            this.code = '';
            this.pin = '';
        },

        expiryLabel() {
            if (! this.result) return '';
            const d = this.result.days_until_expiry;
            if (d == null) return '';
            if (d < 0) return 'expirat';
            if (d === 0) return 'expiră astăzi';
            if (d === 1) return 'mai e 1 zi';
            if (d <= 30) return 'mai sunt ' + d + ' zile';
            return d + ' zile rămase';
        },

        unusableReason() {
            if (! this.result) return '';
            if (this.result.balance == null || Number(this.result.balance) <= 0) return 'Soldul cardului este 0 lei.';
            if (this.result.days_until_expiry != null && this.result.days_until_expiry < 0) return 'Cardul a expirat.';
            if (this.result.status && this.result.status !== 'active' && this.result.status !== 'usable') return 'Status curent: ' + (this.result.status_label || this.result.status) + '.';
            return 'Contactează suportul pentru detalii.';
        },

        formatMoney(n) {
            if (n == null) return '—';
            return new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);
        },
        formatDate(d) {
            if (! d) return '—';
            try {
                const dt = new Date(d);
                if (isNaN(dt)) return d;
                const months = ['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
                return dt.getDate() + ' ' + months[dt.getMonth()] + ' ' + dt.getFullYear();
            } catch (e) { return d; }
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
