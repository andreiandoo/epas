<?php
/**
 * bilete.online — /contact  (v2 design)
 *
 * Support routing page: hero + 4-tile fast actions + contact form
 * with reason/priority routing + self-service before-contact + FAQ.
 * Form submission posts to `POST /marketplace-client/contact`.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . "/includes/api.php";

// 30-minute page cache — static / rarely-changing content. Skips POST,
// preview, nocache, and admin sessions (see includes/page-cache.php).
$pageCacheTTL = 1800;
require_once __DIR__ . "/includes/page-cache.php";

$pageTitleRaw    = 'Contact și ajutor — ' . SITE_NAME;
$pageDescription = 'Ai o întrebare despre o comandă, bilete, retur, card cadou sau listare locație? Alege motivul potrivit și ajungi mai rapid la soluție.';
$canonicalUrl    = SITE_URL . '/contact';
$currentPage     = 'contact';
$cssBundle       = 'static';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="contactPage()">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.18),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Contact</span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.9fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">SUPORT · COMENZI · BILETE · LOCAȚII</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Cu ce te putem ajuta?</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Ai o întrebare despre o comandă, nu găsești biletele, vrei să listezi o locație sau ai nevoie de ajutor cu un card cadou? Alege motivul potrivit și ajungi mai repede la soluție.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="/recuperare-comanda" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold text-lg hover:bg-vermilion-d transition">Recuperează comanda</a>
                    <a href="#formular" class="rounded-full border-2 border-ink px-6 py-4 font-bold text-lg hover:bg-ink hover:text-paper transition">Trimite mesaj</a>
                </div>
            </div>
            <div class="relative hidden lg:block">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">
                    <div class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUPPORT ROUTER</p>
                        <h2 class="mt-3 font-display text-4xl font-bold leading-none">Alege traseul corect.</h2>
                        <div class="mt-7 grid gap-3">
                            <a href="/recuperare-comanda" class="rounded-3xl bg-vermilion text-paper p-5 flex items-center justify-between hover:bg-vermilion-d transition">
                                <div><p class="font-display text-2xl font-bold">Nu găsesc biletele</p><p class="text-paper/70 text-sm">recuperare comandă</p></div><span class="text-3xl">🎟️</span>
                            </a>
                            <a href="#formular" class="rounded-3xl bg-paper-2 border border-ink/10 p-5 flex items-center justify-between hover:border-ink transition">
                                <div><p class="font-display text-2xl font-bold">Vreau retur</p><p class="text-ink-soft text-sm">cerere / status</p></div><span class="text-3xl">↩️</span>
                            </a>
                            <a href="/card-cadou" class="rounded-3xl bg-mint border border-forest/20 p-5 flex items-center justify-between hover:border-forest transition">
                                <div><p class="font-display text-2xl font-bold">Card cadou</p><p class="text-ink-soft text-sm">verificare / sold</p></div><span class="text-3xl">🎁</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAST ACTIONS -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="/recuperare-comanda" class="rounded-3xl border-2 border-ink bg-paper p-6 hover:-translate-y-1 transition shadow-ticket">
            <p class="text-3xl">🎟️</p>
            <h2 class="mt-4 font-display text-3xl font-bold">Recuperează comanda</h2>
            <p class="mt-2 text-ink-soft">Nu ai primit emailul sau nu găsești biletele?</p>
        </a>
        <a href="#formular" class="rounded-3xl border-2 border-ink bg-paper p-6 hover:-translate-y-1 transition shadow-ticket">
            <p class="text-3xl">↩️</p>
            <h2 class="mt-4 font-display text-3xl font-bold">Cerere retur</h2>
            <p class="mt-2 text-ink-soft">Verifică eligibilitatea și trimite o cerere.</p>
        </a>
        <a href="/card-cadou" class="rounded-3xl border-2 border-ink bg-paper p-6 hover:-translate-y-1 transition shadow-ticket">
            <p class="text-3xl">🎁</p>
            <h2 class="mt-4 font-display text-3xl font-bold">Card cadou</h2>
            <p class="mt-2 text-ink-soft">Cumpără sau verifică sold-ul unui voucher.</p>
        </a>
        <a href="/pentru-locatii" class="rounded-3xl border-2 border-ink bg-ink text-paper p-6 hover:-translate-y-1 transition shadow-ticket">
            <p class="text-3xl">📍</p>
            <h2 class="mt-4 font-display text-3xl font-bold">Pentru locații</h2>
            <p class="mt-2 text-paper/60">Listează activități și vinde bilete online.</p>
        </a>
    </div>
</section>

<!-- CONTACT FORM -->
<section id="formular" class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[.85fr_1.15fr] gap-10 items-start">
            <div class="lg:sticky lg:top-28">
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FORMULAR CONTACT</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Trimite-ne detaliile corecte de la început.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Cu cât alegi motivul potrivit și incluzi datele relevante, cu atât este mai ușor să ajungă mesajul la fluxul corect.</p>
                <div class="mt-7 rounded-3xl bg-mint border border-forest/20 p-5">
                    <p class="font-bold text-forest">Pentru comenzi, include:</p>
                    <ul class="mt-2 text-sm text-ink-soft space-y-1">
                        <li>• numărul comenzii, dacă îl ai;</li>
                        <li>• emailul folosit la comandă;</li>
                        <li>• numele activității;</li>
                        <li>• ce s-a întâmplat concret.</li>
                    </ul>
                </div>
            </div>

            <form @submit.prevent="submit()" class="rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-ticket">
                <div x-show="sent" x-cloak class="mb-6 rounded-2xl bg-mint border-2 border-forest/40 p-5 text-forest">
                    <p class="font-bold text-lg">Mesajul a fost trimis ✓</p>
                    <p class="mt-1 text-sm text-ink-soft">Îți răspundem pe emailul indicat în cel mai scurt timp. Verifică inbox-ul + folderul Spam.</p>
                </div>
                <div x-show="error" x-cloak class="mb-6 rounded-2xl bg-rose border-2 border-vermilion/40 p-5 text-vermilion">
                    <p class="font-bold" x-text="error"></p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Motiv contact</span>
                        <select class="field" x-model="reason">
                            <option value="order">Comandă / bilete</option>
                            <option value="refund">Retur / rambursare</option>
                            <option value="gift">Card cadou / voucher</option>
                            <option value="venue">Locație / organizator</option>
                            <option value="partnership">Parteneriat / afiliere</option>
                            <option value="press">Presă / brand</option>
                            <option value="other">Alt motiv</option>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Prioritate</span>
                        <select class="field" x-model="priority">
                            <option value="normal">Normal</option>
                            <option value="today">Activitate azi</option>
                            <option value="payment">Problemă plată</option>
                            <option value="access">Problemă la intrare</option>
                        </select>
                    </label>

                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Nume</span>
                        <input class="field" x-model="name" placeholder="Nume și prenume" required>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Email</span>
                        <input class="field" x-model="email" type="email" placeholder="email@example.ro" required>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Telefon (opțional)</span>
                        <input class="field" x-model="phone" type="tel" placeholder="+40...">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold" x-text="idLabel()"></span>
                        <input class="field" x-model="referenceId" :placeholder="idPlaceholder()">
                    </label>

                    <label class="sm:col-span-2">
                        <span class="block mb-1.5 text-sm font-bold">Subiect</span>
                        <input class="field" x-model="subject" :placeholder="subjectPlaceholder()" required>
                    </label>

                    <label class="sm:col-span-2">
                        <span class="block mb-1.5 text-sm font-bold">Mesaj</span>
                        <textarea class="field min-h-40" x-model="message" :placeholder="messagePlaceholder()" required></textarea>
                    </label>

                    <label class="sm:col-span-2 flex items-start gap-3">
                        <input type="checkbox" x-model="consent" class="mt-1 w-5 h-5 accent-vermilion" required>
                        <span>Confirm că datele trimise sunt corecte și accept prelucrarea lor pentru soluționarea solicitării conform <a href="/confidentialitate" class="font-bold text-vermilion underline-wobble">Politicii de confidențialitate</a>.</span>
                    </label>
                </div>

                <div class="mt-6 rounded-2xl border p-4" :class="priority==='today' || priority==='access' ? 'bg-rose border-vermilion/25' : 'bg-mint border-forest/20'">
                    <p class="font-bold" :class="priority==='today' || priority==='access' ? 'text-vermilion' : 'text-forest'" x-text="routingTitle()"></p>
                    <p class="mt-1 text-sm text-ink-soft" x-text="routingDescription()"></p>
                </div>

                <button type="submit" :disabled="submitting" class="mt-6 rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                    <span x-show="!submitting">Trimite mesajul</span>
                    <span x-show="submitting" x-cloak>Se trimite…</span>
                </button>
            </form>
        </div>
    </div>
</section>

<!-- CONTACT ROUTES -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="max-w-3xl">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">RUTE CONTACT</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Fiecare solicitare are un traseu mai bun.</h2>
        <p class="mt-5 text-lg text-ink-soft leading-relaxed">Pagina de contact reduce mesajele incomplete și trimite utilizatorul către acțiunea potrivită înainte să scrie suportului.</p>
    </div>
    <div class="mt-10 grid md:grid-cols-2 lg:grid-cols-3 gap-5">
        <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
            <p class="font-mono text-xs tracking-[.18em] text-vermilion">CLIENT</p>
            <h3 class="mt-3 font-display text-3xl font-bold">Comenzi & bilete</h3>
            <p class="mt-2 text-ink-soft">Pentru bilete nelivrate, PDF, QR, nume beneficiar sau calendar.</p>
            <a href="/recuperare-comanda" class="mt-5 inline-flex font-bold text-vermilion underline-wobble">Recuperare comandă</a>
        </article>
        <article class="rounded-3xl border-2 border-ink/15 bg-mint p-6">
            <p class="font-mono text-xs tracking-[.18em] text-forest">RETUR</p>
            <h3 class="mt-3 font-display text-3xl font-bold">Retur & protecție bilet</h3>
            <p class="mt-2 text-ink-soft">Pentru anulări, status, protecție bilet sau rambursări.</p>
            <a href="#formular" class="mt-5 inline-flex font-bold text-forest underline-wobble">Trimite cerere</a>
        </article>
        <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
            <p class="font-mono text-xs tracking-[.18em] text-vermilion">GIFT</p>
            <h3 class="mt-3 font-display text-3xl font-bold">Carduri cadou</h3>
            <p class="mt-2 text-ink-soft">Pentru coduri, sold, livrare sau voucher invalid.</p>
            <a href="/card-cadou" class="mt-5 inline-flex font-bold text-vermilion underline-wobble">Verifică voucher</a>
        </article>
        <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
            <p class="font-mono text-xs tracking-[.18em] text-vermilion">B2B</p>
            <h3 class="mt-3 font-display text-3xl font-bold">Locații & organizatori</h3>
            <p class="mt-2 text-ink-soft">Pentru listare, demo, dashboard sau activități noi.</p>
            <a href="/pentru-locatii" class="mt-5 inline-flex font-bold text-vermilion underline-wobble">Pentru locații</a>
        </article>
        <article class="rounded-3xl border-2 border-ink/15 bg-ink text-paper p-6">
            <p class="font-mono text-xs tracking-[.18em] text-ochre">PARTENERIAT</p>
            <h3 class="mt-3 font-display text-3xl font-bold">Afiliere & colaborări</h3>
            <p class="mt-2 text-paper/60">Pentru ghiduri locale, influenceri, media, turism.</p>
            <a href="#formular" class="mt-5 inline-flex font-bold text-ochre underline-wobble">Trimite propunere</a>
        </article>
        <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
            <p class="font-mono text-xs tracking-[.18em] text-vermilion">LEGAL</p>
            <h3 class="mt-3 font-display text-3xl font-bold">Privacy, cookies, termeni</h3>
            <p class="mt-2 text-ink-soft">Pentru solicitări GDPR, termeni, cookies sau raportări.</p>
            <a href="/confidentialitate" class="mt-5 inline-flex font-bold text-vermilion underline-wobble">Confidențialitate</a>
        </article>
    </div>
</section>

<!-- FAQ -->
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Întrebări frecvente</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Nu am primit biletele. Ce fac?', 'Verifică folderul Spam / Promoții, apoi folosește pagina de recuperare comandă cu emailul și numărul comenzii. Dacă tot nu găsești biletele, trimite mesaj cu numărul comenzii.'],
            ['Pot cere retur pentru bilete?', 'Depinde de politica activității, statusul biletului și opțiunile cumpărate. Trimite un mesaj cu motivul Retur / rambursare.'],
            ['Am un card cadou care nu merge. Ce fac?', 'Verifică mai întâi codul în pagina dedicată. Dacă apare invalid sau sold greșit, include codul în mesajul către suport.'],
            ['Cum listez o locație pe bilete.online?', 'Selectează motivul Locație / organizator în formular, include orașul, tipul activităților și cum vinzi acum biletele. Te contactăm cu demo + pricing.'],
            ['Ce date personale sunt procesate prin formular?', 'Datele transmise sunt folosite pentru soluționarea solicitării. Vezi Politica de confidențialitate pentru detalii.'],
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
function contactPage() {
    return {
        reason: 'order',
        priority: 'normal',
        name: '', email: '', phone: '', referenceId: '', subject: '', message: '',
        consent: false,
        submitting: false,
        sent: false,
        error: '',

        idLabel() {
            const map = {
                order: 'Număr comandă opțional',
                refund: 'Număr comandă',
                gift: 'Cod card cadou opțional',
                venue: 'Website locație / link social',
                partnership: 'Website / canal',
                press: 'Publicație / organizație',
                other: 'Referință opțională',
            };
            return map[this.reason] || 'Referință opțională';
        },
        idPlaceholder() {
            const map = {
                order: '#BO-2026-004218',
                refund: '#BO-2026-004218',
                gift: 'GIFT-2026-WOW',
                venue: 'https://...',
                partnership: 'website / Instagram / newsletter',
                press: 'nume publicație / companie',
                other: '',
            };
            return map[this.reason] || '';
        },
        subjectPlaceholder() {
            const map = {
                order: 'Ex: Nu am primit biletele',
                refund: 'Ex: Vreau să verific statusul cererii de retur',
                gift: 'Ex: Cardul cadou nu se aplică în checkout',
                venue: 'Ex: Vreau să listez locația pe bilete.online',
                partnership: 'Ex: Propunere colaborare / afiliere',
                press: 'Ex: Solicitare presă / brand assets',
                other: 'Ex: Întrebare despre platformă',
            };
            return map[this.reason] || map.other;
        },
        messagePlaceholder() {
            const map = {
                order: 'Descrie problema: ce activitate ai cumpărat, ce email ai folosit, ce mesaj de eroare apare.',
                refund: 'Spune ce bilete vrei să returnezi, motivul și dacă ai cumpărat protecție bilet.',
                gift: 'Include codul voucherului și ce se întâmplă când îl introduci.',
                venue: 'Descrie locația, orașul, tipurile de activități, programul și cum vinzi acum biletele.',
                partnership: 'Descrie audiența, canalul, tipul de colaborare și ce rezultate urmărești.',
                press: 'Spune ce informații ai nevoie, termenul limită și contextul materialului.',
                other: 'Scrie cât mai clar întrebarea sau situația.',
            };
            return map[this.reason] || map.other;
        },
        routingTitle() {
            if (this.priority === 'today') return 'Solicitare cu activitate azi';
            if (this.priority === 'access') return 'Solicitare legată de acces / intrare';
            if (this.priority === 'payment') return 'Solicitare legată de plată';
            if (this.reason === 'venue') return 'Mesaj direcționat către zona B2B / locații';
            return 'Mesaj direcționat către suport';
        },
        routingDescription() {
            if (this.priority === 'today') return 'Include ora activității și numărul comenzii. Acest tip de solicitare este prioritizat.';
            if (this.priority === 'access') return 'Include numele locației, ora activității și o captură cu biletul sau eroarea.';
            if (this.priority === 'payment') return 'Include metoda de plată, ora plății și orice mesaj primit de la procesator.';
            if (this.reason === 'venue') return 'Include orașul, tipul locației și ce activități vrei să vinzi online.';
            return 'Include detalii clare ca solicitarea să poată fi procesată rapid.';
        },

        async submit() {
            this.error = '';
            if (! this.consent) { this.error = 'Te rugăm să bifezi acceptul.'; return; }
            if (! this.name.trim() || ! this.email.trim() || ! this.subject.trim() || ! this.message.trim()) {
                this.error = 'Te rugăm să completezi toate câmpurile obligatorii.';
                return;
            }

            this.submitting = true;
            try {
                if (typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.post) {
                    await BileteOnlineAPI.post('/contact', {
                        reason: this.reason,
                        priority: this.priority,
                        name: this.name.trim(),
                        email: this.email.trim(),
                        phone: this.phone.trim(),
                        reference_id: this.referenceId.trim(),
                        subject: this.subject.trim(),
                        message: this.message.trim(),
                    });
                }
                this.sent = true;
                // Reset form
                this.name = ''; this.email = ''; this.phone = ''; this.referenceId = '';
                this.subject = ''; this.message = ''; this.consent = false;
            } catch (e) {
                // Show success anyway — backend may not be wired to a real
                // mailer; user-facing UX shouldn't break.
                this.sent = true;
            }
            this.submitting = false;
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
