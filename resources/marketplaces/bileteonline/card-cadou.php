<?php
/**
 * /card-cadou — Gift card landing page.
 * Pure static landing with Alpine.js configurator (live preview).
 */
require_once __DIR__ . '/includes/config.php';

$pageTitleRaw = 'Card cadou bilete.online — dăruiește o experiență, nu un obiect';
$pageDescription = 'Card cadou digital bilete.online pentru activități, experiențe și ieșiri memorabile. Alegi valoarea, scrii mesajul, destinatarul primește email cu cod unic.';
$pageKeywords = 'card cadou bilete online, card cadou experiente, voucher cadou activitati, cadou digital online, gift card romania';
$canonicalUrl = SITE_URL . '/card-cadou';
$currentPage = 'card-cadou';
$cssBundle = 'static';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Card cadou', 'url' => $canonicalUrl],
];

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'Card cadou bilete.online',
        'description' => 'Card cadou digital pentru activități și experiențe — escape rooms, muzee, parcuri, ateliere, natură.',
        'brand' => ['@type' => 'Brand', 'name' => 'bilete.online'],
        'offers' => [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'RON',
            'lowPrice' => '50',
            'highPrice' => '1000',
            'offerCount' => '6',
        ],
    ],
];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<div x-data="giftCardPage()">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_16%,rgba(232,69,39,.28),transparent_30%),radial-gradient(circle_at_15%_75%,rgba(218,154,51,.26),transparent_30%),radial-gradient(circle_at_48%_44%,rgba(30,74,61,.16),transparent_34%)]" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span aria-hidden="true">/</span>
            <span class="text-ink">Card cadou</span>
        </nav>

        <div class="mt-8 grid lg:grid-cols-[1fr_.92fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">CARD CADOU DIGITAL · EXPERIENȚE · BILETE QR</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl lg:text-[6.8rem] font-700 leading-[.82]">Dăruiește ceva de făcut.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Un card cadou bilete.online nu obligă pe nimeni să aleagă un obiect. Îi lași să aleagă o experiență: escape room, muzeu, parc, atelier, natură sau o ieșire de weekend.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#cumpara" class="rounded-full bg-vermilion text-paper px-6 py-4 font-700 text-lg hover:bg-vermilion-d transition">Cumpără card cadou</a>
                    <a href="#cum-functioneaza" class="rounded-full border-2 border-ink px-6 py-4 font-700 text-lg hover:bg-ink hover:text-paper transition">Cum funcționează</a>
                </div>
                <div class="mt-10 grid grid-cols-3 gap-3 max-w-2xl">
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">LIVRARE</p><p class="font-display text-3xl font-700">email</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">VALORI</p><p class="font-display text-3xl font-700">flex</p></div>
                    <div class="rounded-2xl bg-paper/75 border border-ink/10 p-4"><p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">FOLOSIRE</p><p class="font-display text-3xl font-700">online</p></div>
                </div>
            </div>

            <div class="relative min-h-[600px]" aria-hidden="true">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-3 shadow-deep"></div>
                <div class="ticket absolute inset-x-0 top-0 mx-auto max-w-[520px] min-h-[350px] rounded-[2rem] border-2 border-ink bg-vermilion text-paper overflow-hidden shadow-deep -rotate-3" style="--perf:100%">
                    <div class="absolute inset-0 opacity-20 bg-dotgrid-light-md"></div>
                    <div class="relative p-8 sm:p-10 min-h-[350px] flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-4">
                                <p class="font-mono text-xs tracking-[.22em] text-paper/65">GIFT CARD</p>
                                <p class="font-mono text-xs text-paper/65">bilete.online</p>
                            </div>
                            <p class="mt-10 font-display text-7xl sm:text-8xl font-700 leading-none" x-text="money(amount)"></p>
                            <p class="mt-4 text-paper/75 text-lg">pentru <strong x-text="recipient || 'cineva care merită o ieșire bună'"></strong></p>
                        </div>
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p class="font-mono text-[10px] tracking-[.18em] text-paper/55">COD EXEMPLU</p>
                                <p class="font-mono tracking-[.25em] text-lg">GIFT-2026-WOW</p>
                            </div>
                            <div class="w-16 h-16 rounded-2xl bg-paper text-ink grid place-items-center rotate-6">
                                <svg viewBox="0 0 24 24" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7m0 0S9.5 2 7 4s5 3 5 3Zm0 0s2.5-5 5-3-5 3-5 3Z"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="floaty absolute left-0 bottom-28 w-60 rounded-3xl border-2 border-ink bg-paper p-5 shadow-deep rotate-6">
                    <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">MESAJ PERSONALIZAT</p>
                    <p class="mt-2 font-display text-2xl font-700">„Alege o experiență care te scoate din casă."</p>
                </div>

                <div class="floaty absolute right-0 bottom-6 w-64 rounded-3xl border-2 border-ink bg-forest text-paper p-5 shadow-deep -rotate-6" style="animation-delay:-2.3s">
                    <p class="font-mono text-[10px] tracking-[.18em] text-paper/50">SE POATE FOLOSI LA</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-700">
                        <span class="rounded-full bg-paper text-ink px-3 py-1">escape rooms</span>
                        <span class="rounded-full bg-paper/10 px-3 py-1">muzee</span>
                        <span class="rounded-full bg-paper/10 px-3 py-1">parcuri</span>
                        <span class="rounded-full bg-paper/10 px-3 py-1">ateliere</span>
                        <span class="rounded-full bg-paper/10 px-3 py-1">natură</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- WHY -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
    <div class="grid lg:grid-cols-[.8fr_1.2fr] gap-10 items-start">
        <div class="lg:sticky lg:top-28">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">DE CE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Un cadou care nu rămâne pe raft.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Cardul cadou este perfect când nu știi exact ce activitate ar prefera cineva, dar știi sigur că i-ar prinde bine o ieșire, o experiență sau un moment memorabil.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <article class="soft-card p-6"><h3 class="font-display text-3xl font-700">Libertate de alegere</h3><p class="mt-2 text-ink-soft">Destinatarul alege orașul, categoria, data și activitatea potrivită.</p></article>
            <article class="soft-card p-6 bg-mint"><h3 class="font-display text-3xl font-700">Livrare rapidă</h3><p class="mt-2 text-ink-soft">Cardul poate fi livrat digital pe email, imediat sau programat.</p></article>
            <article class="soft-card p-6"><h3 class="font-display text-3xl font-700">Mesaj personalizat</h3><p class="mt-2 text-ink-soft">Adaugi un mesaj care transformă cardul într-un cadou personal.</p></article>
            <article class="soft-card p-6 bg-ink text-paper"><h3 class="font-display text-3xl font-700">Sold reutilizabil</h3><p class="mt-2 text-paper/60">Dacă nu se folosește integral, soldul poate rămâne disponibil conform regulamentului.</p></article>
        </div>
    </div>
</section>

<!-- CONFIGURATOR -->
<section id="cumpara" class="border-y-2 border-ink bg-paper-2/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[1fr_460px] gap-8 items-start">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">CONFIGURATOR</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Construiește cardul cadou.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed max-w-2xl">Alege valoarea, destinatarul, mesajul și momentul livrării. Cardul se generează și se trimite digital pe email.</p>

                <form class="mt-8 rounded-[2rem] border-2 border-ink bg-paper p-6 sm:p-8 shadow-ticket">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label>
                            <span class="block mb-1.5 text-sm font-700">Valoare card</span>
                            <select class="field" x-model.number="amount">
                                <option :value="50">50 lei</option>
                                <option :value="100">100 lei</option>
                                <option :value="150">150 lei</option>
                                <option :value="250">250 lei</option>
                                <option :value="500">500 lei</option>
                                <option :value="1000">1000 lei</option>
                            </select>
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-700">Pentru cine este?</span>
                            <input class="field" x-model="recipient" placeholder="ex. Maria, Alex, Ana și Vlad" />
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-700">Email destinatar</span>
                            <input class="field" type="email" placeholder="destinatar@example.ro" />
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-700">Când se trimite?</span>
                            <select class="field" x-model="delivery">
                                <option value="now">Imediat după cumpărare</option>
                                <option value="scheduled">La o dată aleasă</option>
                                <option value="me">Îl trimit eu mai târziu</option>
                            </select>
                        </label>
                        <label x-show="delivery==='scheduled'" x-collapse>
                            <span class="block mb-1.5 text-sm font-700">Data trimiterii</span>
                            <input class="field" type="date" />
                        </label>
                        <label>
                            <span class="block mb-1.5 text-sm font-700">Design</span>
                            <select class="field" x-model="theme">
                                <option value="vermilion">Wow / surpriză</option>
                                <option value="forest">Natură / calm</option>
                                <option value="ochre">Sărbătoare</option>
                                <option value="ink">Premium</option>
                            </select>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="block mb-1.5 text-sm font-700">Mesaj personalizat</span>
                            <textarea class="field min-h-32" x-model="message" maxlength="180" placeholder="Scrie un mesaj scurt pentru destinatar."></textarea>
                            <span class="mt-1 block text-xs text-ink-soft" x-text="message.length + '/180 caractere'"></span>
                        </label>
                    </div>

                    <div class="mt-6 rounded-2xl bg-mint border border-forest/20 p-4">
                        <p class="font-700 text-forest">Ce primește destinatarul?</p>
                        <p class="mt-1 text-sm text-ink-soft">Un email cu cardul cadou, cod unic, mesajul tău și link direct către activitățile eligibile.</p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="button" class="rounded-full bg-vermilion text-paper px-6 py-4 font-700 hover:bg-vermilion-d transition">Adaugă în coș</button>
                        <button type="button" class="rounded-full border-2 border-ink px-6 py-4 font-700 hover:bg-ink hover:text-paper transition">Previzualizează</button>
                    </div>
                </form>
            </div>

            <aside class="lg:sticky lg:top-28">
                <div class="ticket rounded-[2rem] border-2 border-ink overflow-hidden shadow-deep"
                     :class="{
                       'bg-vermilion text-paper': theme==='vermilion',
                       'bg-forest text-paper': theme==='forest',
                       'bg-ochre text-ink': theme==='ochre',
                       'bg-ink text-paper': theme==='ink'
                     }"
                     style="--perf:100%">
                    <div class="relative min-h-[520px] p-8 flex flex-col justify-between">
                        <div class="absolute inset-0 opacity-20 bg-dotgrid-light-md" aria-hidden="true"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between gap-4">
                                <p class="font-mono text-xs tracking-[.22em] opacity-65">GIFT CARD</p>
                                <p class="font-mono text-xs opacity-65">bilete.online</p>
                            </div>
                            <p class="mt-12 font-display text-7xl font-700 leading-none" x-text="money(amount)"></p>
                            <p class="mt-4 text-lg opacity-75">pentru <strong x-text="recipient || 'cineva drag'"></strong></p>
                            <div class="mt-8 rounded-3xl bg-white/10 border border-white/15 p-5">
                                <p class="font-display text-2xl font-700" x-text="message || 'Alege o experiență care te scoate din casă.'"></p>
                            </div>
                        </div>

                        <div class="relative flex items-end justify-between gap-4">
                            <div>
                                <p class="font-mono text-[10px] tracking-[.18em] opacity-60">COD CARD</p>
                                <p class="font-mono tracking-[.25em] text-lg">GIFT-2026-WOW</p>
                            </div>
                            <div class="w-20 h-20 rounded-2xl bg-paper text-ink grid place-items-center rotate-6">
                                <svg viewBox="0 0 24 24" class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7m0 0S9.5 2 7 4s5 3 5 3Zm0 0s2.5-5 5-3-5 3-5 3Z"/></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section id="cum-functioneaza" class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="max-w-3xl">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">CUM FUNCȚIONEAZĂ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Din cadou în bilet, în câțiva pași.</h2>
    </div>
    <div class="mt-10 grid md:grid-cols-4 gap-4">
        <?php
        $steps = [
            ['n' => 1, 'title' => 'Alegi valoarea',  'desc' => 'Selectezi suma potrivită sau o valoare personalizată.', 'dark' => false],
            ['n' => 2, 'title' => 'Scrii mesajul',   'desc' => 'Adaugi numele destinatarului și o urare personală.',    'dark' => false],
            ['n' => 3, 'title' => 'Îl trimiți',      'desc' => 'Cardul ajunge pe email imediat sau la data aleasă.',     'dark' => false],
            ['n' => 4, 'title' => 'Ei aleg experiența', 'desc' => 'Codul se folosește în coș sau checkout pentru activități eligibile.', 'dark' => true],
        ];
        foreach ($steps as $s):
        ?>
            <article class="rounded-3xl border-2 border-ink <?= $s['dark'] ? 'bg-ink text-paper' : 'bg-paper' ?> p-6">
                <p class="font-display text-5xl font-700 <?= $s['dark'] ? 'text-ochre' : 'text-vermilion' ?>"><?= $s['n'] ?></p>
                <h3 class="mt-3 font-display text-2xl font-700"><?= htmlspecialchars($s['title']) ?></h3>
                <p class="mt-2 <?= $s['dark'] ? 'text-paper/60' : 'text-ink-soft' ?>"><?= htmlspecialchars($s['desc']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- USE CASES -->
<section class="border-y-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[.9fr_1.1fr] gap-10 items-start">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">PENTRU CE OCAZII</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Când nu vrei încă un cadou generic.</h2>
                <p class="mt-5 text-lg text-paper/60 leading-relaxed">Cardul cadou funcționează pentru oameni diferiți pentru că nu presupune că știi exact ce vor. Le dai opțiuni, nu o alegere forțată.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <?php
                $useCases = [
                    ['t' => 'Zi de naștere', 'd' => 'Pentru cineva care preferă amintiri în loc de obiecte.'],
                    ['t' => 'Cuplu',         'd' => 'O ieșire în doi: muzeu, atelier, escape room sau tur.'],
                    ['t' => 'Familie',       'd' => 'Activități pentru copii, weekenduri și vacanțe.'],
                    ['t' => 'Corporate',     'd' => 'Cadouri pentru echipe, clienți sau parteneri.'],
                    ['t' => 'Last minute',   'd' => 'Cadou digital, rapid, fără livrare fizică.'],
                    ['t' => 'Mulțumesc',     'd' => 'Un gest elegant pentru cineva care a ajutat.'],
                ];
                foreach ($useCases as $uc): ?>
                    <article class="rounded-3xl bg-paper/10 border border-paper/10 p-5"><h3 class="font-display text-3xl font-700"><?= htmlspecialchars($uc['t']) ?></h3><p class="mt-2 text-paper/60"><?= htmlspecialchars($uc['d']) ?></p></article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ELIGIBLE -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
        <div>
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">ACTIVITĂȚI ELIGIBILE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">La ce se poate folosi?</h2>
            <p class="mt-5 text-lg text-ink-soft max-w-3xl">Cardul cadou poate fi folosit pentru activitățile eligibile din platformă: escape rooms, muzee, parcuri, ateliere, natură sau experiențe pentru familie.</p>
        </div>
        <a href="/categorii" class="rounded-full bg-ink text-paper px-6 py-4 font-700 hover:bg-vermilion transition">Vezi toate categoriile</a>
    </div>

    <div class="mt-10 grid md:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php
        $eligible = [
            ['href' => '/escape-rooms',         'emoji' => '🕵️', 'title' => 'Escape rooms'],
            ['href' => '/muzee-expozitii',      'emoji' => '🖼️', 'title' => 'Muzee'],
            ['href' => '/parcuri-de-distractii','emoji' => '🎡', 'title' => 'Parcuri'],
            ['href' => '/natura-outdoor',       'emoji' => '🌲', 'title' => 'Natură'],
            ['href' => '/ateliere-experiente-creative', 'emoji' => '🎨', 'title' => 'Ateliere'],
            ['href' => '/familie-copii',        'emoji' => '👨‍👩‍👧', 'title' => 'Familie'],
        ];
        foreach ($eligible as $e): ?>
            <a href="<?= htmlspecialchars($e['href'], ENT_QUOTES) ?>" class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-5 hover:border-ink transition">
                <p class="text-3xl"><?= $e['emoji'] ?></p>
                <h3 class="mt-3 font-display text-2xl font-700"><?= htmlspecialchars($e['title']) ?></h3>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- BALANCE / TRUST -->
<section class="border-y-2 border-ink bg-paper-2/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
        <div class="grid lg:grid-cols-2 gap-8 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">GESTIONARE SOLD</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Cod unic. Sold clar. Folosire simplă.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Destinatarul introduce codul în coș sau checkout. Dacă valoarea comenzii este mai mică decât soldul disponibil, diferența poate rămâne pe card, conform regulamentului.</p>
            </div>
            <div class="ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-ticket" style="--perf:100%">
                <div class="p-6 sm:p-8">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">VERIFICARE CARD</p>
                    <h3 class="mt-3 font-display text-4xl font-700">GIFT-2026-WOW</h3>
                    <div class="mt-6 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-mint border border-forest/20 p-4"><p class="text-sm text-ink-soft">Sold disponibil</p><p class="font-display text-4xl font-700">180 lei</p></div>
                        <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4"><p class="text-sm text-ink-soft">Valabil până la</p><p class="font-display text-4xl font-700">2027</p></div>
                    </div>
                    <a href="/voucher" class="mt-6 inline-flex rounded-full bg-vermilion text-paper px-6 py-4 font-700 hover:bg-vermilion-d transition">Verifică un card</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{ open: 0 }">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Întrebări frecvente</h2>
    </div>

    <div class="mt-10 space-y-3">
        <?php
        $faqs = [
            ['q' => 'Cum se livrează cardul cadou?',                'a' => 'Cardul cadou este livrat digital pe email, fie către tine, fie direct către destinatar, în funcție de opțiunea aleasă.'],
            ['q' => 'Unde poate fi folosit?',                       'a' => 'Poate fi folosit pentru activitățile eligibile de pe bilete.online: escape rooms, muzee, parcuri, ateliere, natură și alte experiențe listate.'],
            ['q' => 'Poate fi folosit parțial?',                    'a' => 'Da. Dacă soldul cardului este mai mare decât valoarea comenzii, diferența poate rămâne disponibilă până la expirarea cardului, conform regulamentului.'],
            ['q' => 'Pot programa trimiterea?',                     'a' => 'Da, cardul poate fi trimis imediat sau programat pentru o dată aleasă, dacă această opțiune este activă în checkout.'],
            ['q' => 'Pot cumpăra carduri cadou pentru companie?',   'a' => 'Da. Pentru volume mai mari sau cadouri corporate, poți folosi formularul de contact sau o pagină dedicată comenzilor bulk.'],
        ];
        foreach ($faqs as $i => $f): ?>
            <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                <button type="button" @click="open = open === <?= $i ?> ? null : <?= $i ?>" :aria-expanded="open === <?= $i ?>" class="w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
                    <span class="font-display text-2xl sm:text-3xl font-700"><?= htmlspecialchars($f['q']) ?></span>
                    <span class="text-3xl font-700" x-text="open === <?= $i ?> ? '−' : '+'"></span>
                </button>
                <div x-show="open === <?= $i ?>" x-collapse x-cloak class="px-5 sm:px-6 pb-6 text-ink-soft leading-relaxed"><?= htmlspecialchars($f['a']) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- FINAL CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 pb-16 sm:pb-20">
    <div class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-8 sm:p-12">
        <div class="absolute inset-0 opacity-15 bg-dotgrid-cta" aria-hidden="true"></div>
        <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 items-center">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">CADOU DIGITAL</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-700 leading-[.9]">Trimite o experiență, nu încă un obiect.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Alege valoarea, scrie mesajul și lasă destinatarul să aleagă activitatea potrivită.</p>
            </div>
            <a href="#cumpara" class="rounded-full bg-paper text-ink px-6 py-4 font-700 text-center hover:bg-ink hover:text-paper transition">Cumpără card cadou</a>
        </div>
    </div>
</section>

</div><!-- /x-data="giftCardPage()" -->

<script>
// Gift card live-preview state. Plain inline because it's only used on this page.
function giftCardPage() {
    return {
        amount: 250,
        recipient: '',
        delivery: 'now',
        theme: 'vermilion',
        message: '',
        money(v) {
            return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON', maximumFractionDigits: 0 }).format(v);
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
