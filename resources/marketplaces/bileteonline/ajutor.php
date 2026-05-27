<?php
/**
 * bilete.online — /ajutor (alias /faqs, /intrebari)
 *
 * Help center: hero + 4-tile fast actions + searchable + category-filterable
 * FAQ list + section explainers + still-need-help router + CTA. All FAQs
 * are static for v1 — they live inline in the PHP file so they're
 * indexable without an extra API call.
 */

require_once __DIR__ . '/includes/config.php';

$pageTitleRaw    = 'Întrebări frecvente și ajutor — ' . SITE_NAME;
$pageDescription = 'Răspunsuri rapide despre comenzi, bilete QR, plăți, retururi, protecție bilet, puncte bonus, carduri cadou, cont client și acces pentru locații.';
$canonicalUrl    = SITE_URL . '/ajutor';
$currentPage     = 'ajutor';
$cssBundle       = 'static';

// All FAQs inline (server-rendered for SEO + JSON-LD).
$faqs = [
    ['category' => 'orders', 'categoryLabel' => 'Comenzi', 'q' => 'Când primesc biletele după plată?',
        'a' => 'Biletele sunt emise după confirmarea plății și sunt trimise pe email. Dacă ai cont, le găsești și în zona Biletele mele.',
        'links' => [['Biletele mele', '/cont/bilete'], ['Recuperează comanda', '/recuperare-comanda']]],
    ['category' => 'orders', 'categoryLabel' => 'Comenzi', 'q' => 'Ce fac dacă nu am primit emailul de confirmare?',
        'a' => 'Verifică folderele Spam, Promotions sau Updates. Apoi folosește pagina de recuperare comandă cu emailul folosit la checkout și numărul comenzii, dacă îl ai.',
        'links' => [['Recuperare comandă', '/recuperare-comanda']]],
    ['category' => 'tickets', 'categoryLabel' => 'Bilete & QR', 'q' => 'Trebuie să printez biletul?',
        'a' => 'În mod normal nu. Poți arăta codul QR de pe telefon. Dacă o locație cere altceva, această informație apare pe pagina activității și în bilet.'],
    ['category' => 'tickets', 'categoryLabel' => 'Bilete & QR', 'q' => 'Pot pune nume diferite pe bilete?',
        'a' => 'Da. În checkout poți alege dacă biletele au același beneficiar sau dacă fiecare bilet are nume diferit. Util pentru grupuri, cadouri și comenzi corporate.'],
    ['category' => 'tickets', 'categoryLabel' => 'Bilete & QR', 'q' => 'Ce se întâmplă dacă QR-ul nu se scanează?',
        'a' => 'Personalul locației poate verifica biletul după cod, număr comandă sau datele beneficiarului, în funcție de procedura locației.'],
    ['category' => 'payments', 'categoryLabel' => 'Plăți & taxe', 'q' => 'Ce metode de plată sunt disponibile?',
        'a' => 'Checkout-ul include card (Visa, Mastercard), Apple Pay, Google Pay și Card Cultural (Edenred / Sodexo / Up România), în funcție de procesator.'],
    ['category' => 'payments', 'categoryLabel' => 'Plăți & taxe', 'q' => 'De ce apar comisioane separate în coș?',
        'a' => 'Comisioanele platformei și eventualele taxe de procesare sunt afișate separat pentru transparență. Totalul final apare înainte de confirmarea plății.'],
    ['category' => 'payments', 'categoryLabel' => 'Plăți & taxe', 'q' => 'Ce fac dacă plata a eșuat dar banii par blocați?',
        'a' => 'Unele plăți pot apărea temporar ca autorizări în contul bancar. Dacă plata nu este confirmată, comanda nu se emite. Verifică statusul comenzii sau contactează suportul.',
        'links' => [['Contact suport', '/contact']]],
    ['category' => 'refunds', 'categoryLabel' => 'Retururi', 'q' => 'Pot cere retur pentru bilete?',
        'a' => 'Returul depinde de politica activității, de statusul biletului, de momentul solicitării și de eventualele opțiuni cumpărate (de exemplu protecția bilet).',
        'links' => [['Trimite cerere', '/contact?motiv=retur']]],
    ['category' => 'refunds', 'categoryLabel' => 'Retururi', 'q' => 'Ce este protecția bilet?',
        'a' => 'Protecția bilet este o opțiune suplimentară care poate oferi flexibilitate dacă nu mai poți ajunge. Condițiile exacte sunt afișate în checkout.'],
    ['category' => 'refunds', 'categoryLabel' => 'Retururi', 'q' => 'Cât durează rambursarea?',
        'a' => 'Durata depinde de procesator, banca emitentă și statusul cererii. După aprobarea returului, rambursarea poate dura câteva zile lucrătoare.'],
    ['category' => 'bonus', 'categoryLabel' => 'Puncte & carduri cadou', 'q' => 'Cum funcționează punctele bonus?',
        'a' => 'La comenzile eligibile primești puncte bonus. Acestea apar în cont după confirmarea comenzii și pot fi folosite la comenzile viitoare.',
        'links' => [['Punctele mele', '/cont/puncte']]],
    ['category' => 'bonus', 'categoryLabel' => 'Puncte & carduri cadou', 'q' => 'Pot folosi punctele în aceeași comandă?',
        'a' => 'În mod normal, punctele se câștigă după confirmarea unei comenzi și se folosesc la comenzi viitoare.'],
    ['category' => 'bonus', 'categoryLabel' => 'Puncte & carduri cadou', 'q' => 'Cum verific un card cadou?',
        'a' => 'Poți verifica soldul și validitatea unui card cadou sau voucher în pagina dedicată.',
        'links' => [['Card cadou', '/card-cadou']]],
    ['category' => 'account', 'categoryLabel' => 'Cont client', 'q' => 'Mi se poate crea cont automat după checkout?',
        'a' => 'Da. Checkout-ul permite plasarea comenzii fără cont și crearea automată a contului, urmând să setezi ulterior parola.'],
    ['category' => 'account', 'categoryLabel' => 'Cont client', 'q' => 'Unde găsesc comenzile și biletele?',
        'a' => 'În contul client, în secțiunile Biletele mele și Comenzile mele. Acolo vezi statusuri, PDF-uri, QR-uri și istoricul.',
        'links' => [['Contul meu', '/cont']]],
    ['category' => 'venues', 'categoryLabel' => 'Locații', 'q' => 'Cum listez o locație pe bilete.online?',
        'a' => 'Accesează pagina Pentru locații și trimite detaliile despre locație, oraș, tipul activităților și cum vinzi acum biletele.',
        'links' => [['Pentru locații', '/pentru-locatii']]],
    ['category' => 'venues', 'categoryLabel' => 'Locații', 'q' => 'O locație poate avea mai multe activități?',
        'a' => 'Da. O locație poate avea o pagină principală și mai multe activități: escape rooms, tururi, ateliere, pachete, bilete de acces.'],
    ['category' => 'venues', 'categoryLabel' => 'Locații', 'q' => 'Cum se face check-in-ul la intrare?',
        'a' => 'Biletele sunt emise cu QR unic, iar staff-ul locației le poate scana pentru validare și controlul accesului.'],
];

// Build counts per category
$categoryCounts = [];
foreach ($faqs as $f) {
    $categoryCounts[$f['category']] = ($categoryCounts[$f['category']] ?? 0) + 1;
}
$categories = [
    ['key' => 'all',      'label' => 'Toate',                'count' => count($faqs)],
    ['key' => 'orders',   'label' => 'Comenzi',              'count' => $categoryCounts['orders']   ?? 0],
    ['key' => 'tickets',  'label' => 'Bilete & QR',          'count' => $categoryCounts['tickets']  ?? 0],
    ['key' => 'payments', 'label' => 'Plăți & taxe',         'count' => $categoryCounts['payments'] ?? 0],
    ['key' => 'refunds',  'label' => 'Retururi',             'count' => $categoryCounts['refunds']  ?? 0],
    ['key' => 'bonus',    'label' => 'Puncte & cadouri',     'count' => $categoryCounts['bonus']    ?? 0],
    ['key' => 'account',  'label' => 'Cont client',          'count' => $categoryCounts['account']  ?? 0],
    ['key' => 'venues',   'label' => 'Locații',              'count' => $categoryCounts['venues']   ?? 0],
];
$quickChips = [
    ['label' => 'Bilete QR', 'key' => 'tickets'],
    ['label' => 'Retururi',  'key' => 'refunds'],
    ['label' => 'Plăți',     'key' => 'payments'],
    ['label' => 'Puncte',    'key' => 'bonus'],
    ['label' => 'Locații',   'key' => 'venues'],
];

// FAQ structured data
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqs),
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="faqsPage(<?= htmlspecialchars(json_encode([
    'faqs'       => $faqs,
    'categories' => $categories,
    'quickChips' => $quickChips,
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.18),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Ajutor</span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.88fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">FAQ · COMENZI · BILETE · LOCAȚII</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Răspunsuri rapide, fără ping-pong cu suportul.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Găsește răspunsuri despre comenzi, bilete QR, plăți, taxe, retururi, protecție bilet, puncte bonus, carduri cadou, cont client și acces pentru locații.
                </p>
                <div class="mt-8 max-w-2xl">
                    <label class="sr-only" for="faq-search">Caută</label>
                    <div class="relative">
                        <input id="faq-search" type="text" class="field text-lg pr-14" x-model="search" placeholder="Caută: bilete, retur, voucher, puncte, plată...">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-soft">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <template x-for="chip in quickChips" :key="chip.key">
                        <button @click="activeCategory=chip.key" class="rounded-full bg-paper/70 border border-ink/10 px-4 py-2 font-bold hover:bg-ink hover:text-paper transition" x-text="chip.label"></button>
                    </template>
                </div>
            </div>
            <div class="relative hidden lg:block">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">
                    <div class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">HELP ROUTER</p>
                        <h2 class="mt-3 font-display text-4xl font-bold leading-none">Cele mai rapide rezolvări.</h2>
                        <div class="mt-7 grid gap-3">
                            <a href="/recuperare-comanda" class="rounded-3xl bg-vermilion text-paper p-5 flex items-center justify-between hover:bg-vermilion-d transition">
                                <div><p class="font-display text-2xl font-bold">Nu găsesc biletele</p><p class="text-paper/70 text-sm">recuperare comandă</p></div><span class="text-3xl">🎟️</span>
                            </a>
                            <a href="/contact?motiv=retur" class="rounded-3xl bg-paper-2 border border-ink/10 p-5 flex items-center justify-between hover:border-ink transition">
                                <div><p class="font-display text-2xl font-bold">Vreau retur</p><p class="text-ink-soft text-sm">cerere / status</p></div><span class="text-3xl">↩️</span>
                            </a>
                            <a href="/card-cadou" class="rounded-3xl bg-mint border border-forest/20 p-5 flex items-center justify-between hover:border-forest transition">
                                <div><p class="font-display text-2xl font-bold">Card cadou</p><p class="text-ink-soft text-sm">verificare sold / cod</p></div><span class="text-3xl">🎁</span>
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
            <p class="mt-2 text-ink-soft">Găsește biletele după email și număr comandă.</p>
        </a>
        <a href="/contact?motiv=retur" class="rounded-3xl border-2 border-ink bg-paper p-6 hover:-translate-y-1 transition shadow-ticket">
            <p class="text-3xl">↩️</p>
            <h2 class="mt-4 font-display text-3xl font-bold">Cerere retur</h2>
            <p class="mt-2 text-ink-soft">Verifică politica și trimite o cerere structurată.</p>
        </a>
        <a href="/card-cadou" class="rounded-3xl border-2 border-ink bg-paper p-6 hover:-translate-y-1 transition shadow-ticket">
            <p class="text-3xl">🎁</p>
            <h2 class="mt-4 font-display text-3xl font-bold">Verifică voucher</h2>
            <p class="mt-2 text-ink-soft">Vezi soldul și validitatea cardului cadou.</p>
        </a>
        <a href="/contact" class="rounded-3xl border-2 border-ink bg-ink text-paper p-6 hover:-translate-y-1 transition shadow-ticket">
            <p class="text-3xl">✉️</p>
            <h2 class="mt-4 font-display text-3xl font-bold">Contact suport</h2>
            <p class="mt-2 text-paper/60">Nu ai găsit răspunsul? Scrie-ne.</p>
        </a>
    </div>
</section>

<!-- FAQ LIST -->
<section class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[300px_1fr] gap-8 items-start">
            <aside class="lg:sticky lg:top-28">
                <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CATEGORII FAQ</p>
                    <div class="mt-4 space-y-2">
                        <template x-for="category in categories" :key="category.key">
                            <button @click="activeCategory=category.key" :class="activeCategory===category.key ? 'bg-ink text-paper' : 'bg-paper-2 text-ink hover:bg-ink/5'" class="w-full rounded-2xl px-4 py-3 text-left font-bold transition flex items-center justify-between gap-3">
                                <span x-text="category.label"></span>
                                <span class="text-xs opacity-60" x-text="category.count"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4">
                        <p class="font-bold text-forest">Nu știi unde se încadrează?</p>
                        <p class="mt-1 text-sm text-ink-soft">Caută după cuvinte simple: „QR", „retur", „voucher", „taxă", „nume bilet".</p>
                    </div>
                </div>
            </aside>

            <section>
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ÎNTREBĂRI</p>
                        <h2 class="mt-2 font-display text-5xl font-bold leading-none" x-text="currentCategoryTitle()"></h2>
                    </div>
                    <p class="text-ink-soft" x-text="filteredFaqs().length + ' din <?= count($faqs) ?> întrebări'"></p>
                </div>
                <div class="mt-6 space-y-3" x-data="{open:null}">
                    <template x-for="(faq,index) in filteredFaqs()" :key="faq.q">
                        <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden shadow-ticket">
                            <button @click="open=open===index?null:index" class="w-full text-left p-5 sm:p-6 flex items-start justify-between gap-4">
                                <span>
                                    <span class="block font-mono text-[10px] tracking-[.18em] text-vermilion" x-text="faq.categoryLabel"></span>
                                    <span class="mt-1 block font-display text-2xl sm:text-3xl font-bold leading-none" x-text="faq.q"></span>
                                </span>
                                <span class="text-3xl font-bold shrink-0" x-text="open===index?'−':'+'"></span>
                            </button>
                            <div x-show="open===index" x-collapse class="px-5 sm:px-6 pb-6">
                                <p class="text-ink-soft leading-relaxed text-lg" x-text="faq.a"></p>
                                <template x-if="faq.links && faq.links.length">
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <template x-for="link in faq.links" :key="link[1]">
                                            <a :href="link[1]" class="rounded-full bg-paper-2 border border-ink/10 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition" x-text="link[0]"></a>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </article>
                    </template>
                    <div x-show="filteredFaqs().length === 0" x-cloak class="border-2 border-dashed border-ink/30 rounded-3xl p-10 text-center bg-paper">
                        <h3 class="font-display text-3xl font-bold">Nu am găsit întrebări pentru filtrul ales.</h3>
                        <p class="mt-2 text-ink-soft">Încearcă un termen mai general sau resetează categoria.</p>
                        <button @click="search=''; activeCategory='all'" class="mt-5 px-6 py-3 rounded-full bg-ink text-paper font-bold">Resetează</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<!-- STILL NEED HELP -->
<section class="border-y-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">SUPORT</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Nu ai găsit răspunsul?</h2>
                <p class="mt-5 text-lg text-paper/60 leading-relaxed">Folosește pagina de contact și alege motivul corect. Pentru comenzi, include emailul folosit, numărul comenzii și numele activității.</p>
            </div>
            <div class="ticket bg-paper text-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">
                <div class="p-6 border-b-2 border-dashed border-ink/15">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CONTACT ROUTER</p>
                    <h3 class="mt-2 font-display text-4xl font-bold">Trimite-ne contextul corect</h3>
                </div>
                <div class="p-6 space-y-3">
                    <a href="/contact?motiv=comanda" class="block rounded-2xl bg-paper-2 border border-ink/10 p-4 hover:border-ink transition"><strong>Problemă cu o comandă</strong><br><span class="text-sm text-ink-soft">bilete, QR, email, plată</span></a>
                    <a href="/contact?motiv=retur" class="block rounded-2xl bg-paper-2 border border-ink/10 p-4 hover:border-ink transition"><strong>Retur sau rambursare</strong><br><span class="text-sm text-ink-soft">eligibilitate, status, protecție bilet</span></a>
                    <a href="/contact?motiv=locatie" class="block rounded-2xl bg-paper-2 border border-ink/10 p-4 hover:border-ink transition"><strong>Locație / organizator</strong><br><span class="text-sm text-ink-soft">listare, demo, dashboard</span></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-8 sm:p-12">
        <div class="absolute inset-0 opacity-15" style="background-image:radial-gradient(#fff 1px,transparent 1.4px);background-size:15px 15px"></div>
        <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 items-center">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">HELP CENTER</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Rezolvă rapid. Apoi mergi la activitate.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">FAQ-ul scurtează drumul: răspuns, acțiune, bilet, acces.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/recuperare-comanda" class="rounded-full bg-paper text-ink px-6 py-4 font-bold text-center hover:bg-ink hover:text-paper transition">Recuperează comanda</a>
                <a href="/contact" class="rounded-full border-2 border-paper/60 px-6 py-4 font-bold text-center hover:bg-paper hover:text-ink transition">Contact suport</a>
            </div>
        </div>
    </div>
</section>

</main>

<script>
function faqsPage(data) {
    return {
        search: '',
        activeCategory: 'all',
        faqs: data.faqs || [],
        categories: data.categories || [],
        quickChips: data.quickChips || [],
        norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[şș]/g, 's').replace(/[ţț]/g, 't')
                .replace(/[ăâ]/g, 'a').replace(/[î]/g, 'i').trim();
        },
        currentCategoryTitle() {
            const found = this.categories.find(c => c.key === this.activeCategory);
            return found ? found.label : 'Toate';
        },
        filteredFaqs() {
            const q = this.norm(this.search);
            return this.faqs.filter(f => {
                const matchesCategory = this.activeCategory === 'all' || f.category === this.activeCategory;
                const blob = this.norm(f.q + ' ' + f.a + ' ' + (f.categoryLabel || ''));
                const matchesSearch = !q || blob.includes(q);
                return matchesCategory && matchesSearch;
            });
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
