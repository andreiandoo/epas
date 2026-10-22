<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Logo și materiale AmBilet pentru organizatori';
$pageDescription = 'Logo-ul oficial AmBilet în SVG, PNG și PDF, culorile brandului și texte gata de pus pe afișe, flyere și pe site-ul evenimentului tău.';
$transparentHeader = false;
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';

$brandDir = '/assets/brand/';
$zipUrl = $brandDir . 'ambilet-materiale-organizatori.zip';

$logos = [
    [
        'file' => 'ambilet-logo',
        'name' => 'Logo principal',
        'use' => 'Pe fundal alb sau deschis. Varianta pe care o recomandăm de fiecare dată când se poate.',
        'bg' => 'bg-white',
    ],
    [
        'file' => 'ambilet-logo-alb',
        'name' => 'Logo alb',
        'use' => 'Pe fundal închis, pe fotografii sau pe afișe colorate.',
        'bg' => 'bg-secondary',
    ],
    [
        'file' => 'ambilet-logo-negru',
        'name' => 'Logo negru',
        'use' => 'Pentru tipar alb-negru, ștampile sau presă monocromă.',
        'bg' => 'bg-surface',
    ],
];

$colors = [
    ['name' => 'Roșu logo', 'hex' => '#790000', 'rgb' => '121, 0, 0', 'cmyk' => '0, 100, 100, 53', 'note' => 'Culoarea biletului din logo'],
    ['name' => 'Alb logo', 'hex' => '#FAFAF8', 'rgb' => '250, 250, 248', 'cmyk' => '0, 0, 1, 2', 'note' => 'Textul și chenarul din logo', 'light' => true],
    ['name' => 'Roșu AmBilet', 'hex' => '#A51C30', 'rgb' => '165, 28, 48', 'cmyk' => '0, 83, 71, 35', 'note' => 'Butoane și accente pe site'],
    ['name' => 'Ardezie', 'hex' => '#1E293B', 'rgb' => '30, 41, 59', 'cmyk' => '49, 31, 0, 77', 'note' => 'Texte și fundaluri închise'],
    ['name' => 'Portocaliu', 'hex' => '#E67E22', 'rgb' => '230, 126, 34', 'cmyk' => '0, 45, 85, 10', 'note' => 'Evidențieri, folosit cu măsură'],
];

$posterLines = [
    'Bilete: ambilet.ro',
    'Bilete disponibile pe AmBilet.ro',
    'Cumpără bilete online pe AmBilet.ro',
];

$embedLogo = '<a href="https://ambilet.ro/bilete/numele-evenimentului" target="_blank" rel="noopener">' . "\n"
    . '  <img src="https://ambilet.ro/assets/brand/ambilet-logo.svg" alt="Bilete pe AmBilet.ro" width="160" height="72">' . "\n"
    . '</a>';

$embedButton = '<a href="https://ambilet.ro/bilete/numele-evenimentului" target="_blank" rel="noopener"' . "\n"
    . '   style="display:inline-block;padding:14px 28px;background:#A51C30;color:#fff;border-radius:10px;font:700 16px/1 sans-serif;text-decoration:none">' . "\n"
    . '  Cumpără bilete pe AmBilet.ro' . "\n"
    . '</a>';

$copyIcon = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
$downloadIcon = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
$checkIcon = '<svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
$xIcon = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
?>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-16 md:py-20 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.25)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-10 md:gap-12 items-center relative z-10">
            <div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4 tracking-tight">Logo și materiale AmBilet</h1>
                <p class="text-lg text-white/90 leading-relaxed mb-8 max-w-xl">Vinzi bilete prin AmBilet? Aici găsești logo-ul oficial pentru afișe, flyere, bannere și site-ul evenimentului, plus culorile și textele pe care le poți folosi.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="<?= $zipUrl ?>" download class="inline-flex items-center gap-2.5 px-7 py-4 bg-gradient-to-br from-primary to-primary-light text-white rounded-xl text-[15px] font-bold hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(165,28,48,0.4)] transition-all">
                        <?= str_replace('w-4 h-4', 'w-5 h-5', $downloadIcon) ?>
                        Descarcă tot pachetul (ZIP)
                    </a>
                    <a href="#reguli" class="inline-flex items-center gap-2 px-6 py-4 bg-white/10 border border-white/20 text-white rounded-xl text-[15px] font-semibold hover:bg-white/15 transition-all">Cum folosești logo-ul</a>
                </div>
            </div>
            <div class="bg-white rounded-3xl p-8 md:p-12 flex items-center justify-center min-h-[240px] md:min-h-[320px] shadow-[0_24px_64px_rgba(0,0,0,0.35)]">
                <img src="<?= $brandDir ?>ambilet-logo.svg" alt="Logo AmBilet.ro" width="500" height="225" class="w-full max-w-[380px] h-auto">
            </div>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-16 md:py-20">

        <!-- Logos -->
        <section id="logo" class="mb-20 scroll-mt-28">
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-3">Descarcă logo-ul</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8 max-w-2xl">SVG și PDF sunt vectoriale, bune pentru tipografie la orice mărime. PNG are 3000 px lățime și fundal transparent, pentru social media și site-uri.</p>
            <div class="grid md:grid-cols-3 gap-6">
                <?php foreach ($logos as $logo): ?>
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200 hover:border-primary hover:shadow-[0_8px_32px_rgba(165,28,48,0.1)] transition-all flex flex-col">
                    <div class="p-8 md:p-10 <?= $logo['bg'] ?> flex items-center justify-center min-h-[180px] border-b border-slate-200">
                        <img src="<?= $brandDir . $logo['file'] ?>.svg" alt="<?= htmlspecialchars($logo['name']) ?> AmBilet" width="500" height="225" loading="lazy" class="w-full max-w-[240px] h-auto">
                    </div>
                    <div class="p-5 md:p-6 flex flex-col flex-1">
                        <div class="text-[15px] font-bold text-slate-800 mb-1"><?= $logo['name'] ?></div>
                        <div class="text-sm text-slate-500 mb-5 flex-1"><?= $logo['use'] ?></div>
                        <div class="flex gap-2">
                            <?php foreach (['svg' => 'SVG', 'png' => 'PNG', 'pdf' => 'PDF'] as $ext => $label): ?>
                            <a href="<?= $brandDir . $logo['file'] . '.' . $ext ?>" download class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-600 hover:bg-primary hover:text-white transition-all"><?= $downloadIcon ?><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Usage rules -->
        <section id="reguli" class="mb-20 scroll-mt-28">
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-8">Cum folosești logo-ul pe afiș</h2>
            <div class="grid lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8">
                    <ul class="space-y-4 text-[15px] text-slate-600 leading-relaxed">
                        <li class="flex gap-3"><span class="text-success mt-0.5"><?= $checkIcon ?></span><span>Lasă în jurul logo-ului un spațiu liber cel puțin cât înălțimea literei „A”. Nu lipi de el text, alte logo-uri sau marginea afișului.</span></li>
                        <li class="flex gap-3"><span class="text-success mt-0.5"><?= $checkIcon ?></span><span>Mărime minimă: 25 mm lățime la tipar și 100 px pe ecran. Sub atât, textul din bilet nu se mai citește.</span></li>
                        <li class="flex gap-3"><span class="text-success mt-0.5"><?= $checkIcon ?></span><span>Locul obișnuit e în partea de jos a afișului, lângă data, locația și ceilalți parteneri, sub textul „Bilete:”.</span></li>
                        <li class="flex gap-3"><span class="text-success mt-0.5"><?= $checkIcon ?></span><span>Pe fotografii sau fundaluri aglomerate folosește logo-ul alb, într-o zonă cât mai liniștită a imaginii.</span></li>
                        <li class="flex gap-3"><span class="text-success mt-0.5"><?= $checkIcon ?></span><span>Pentru tipografie trimite fișierul SVG sau PDF, nu o captură de ecran.</span></li>
                    </ul>
                </div>
                <div class="bg-surface rounded-2xl border border-slate-200 p-6 md:p-8 flex flex-col items-center justify-center">
                    <div class="relative inline-block p-[34px] md:p-[44px] border-2 border-dashed border-primary/40 rounded-xl bg-white">
                        <img src="<?= $brandDir ?>ambilet-logo.svg" alt="" width="500" height="225" loading="lazy" class="w-[200px] md:w-[260px] h-auto">
                        <span class="absolute top-1/2 left-1 -translate-y-1/2 text-[11px] font-bold text-primary">A</span>
                        <span class="absolute top-1 left-1/2 -translate-x-1/2 text-[11px] font-bold text-primary">A</span>
                    </div>
                    <p class="text-sm text-slate-500 mt-4 text-center">Zona punctată rămâne liberă</p>
                </div>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php
                $donts = [
                    ['label' => 'Nu-l întinde sau turti', 'style' => 'transform:scaleX(1.45)'],
                    ['label' => 'Nu-l roti', 'style' => 'transform:rotate(-14deg)'],
                    ['label' => 'Nu adăuga umbre sau efecte', 'style' => 'filter:drop-shadow(5px 5px 0 #E67E22) drop-shadow(0 0 8px rgba(30,41,59,.6))'],
                    ['label' => 'Nu-l decolora', 'style' => 'filter:grayscale(1);opacity:.35'],
                ];
                foreach ($donts as $dont): ?>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="h-[120px] flex items-center justify-center bg-surface overflow-hidden">
                        <img src="<?= $brandDir ?>ambilet-logo.svg" alt="" width="500" height="225" loading="lazy" class="w-[110px] h-auto" style="<?= $dont['style'] ?>">
                    </div>
                    <div class="flex items-center gap-2 px-4 py-3 text-sm font-semibold text-slate-700 border-t border-slate-200">
                        <span class="flex items-center justify-center w-5 h-5 rounded-full bg-error text-white flex-shrink-0"><?= str_replace('w-4 h-4', 'w-3 h-3', $xIcon) ?></span>
                        <?= $dont['label'] ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Poster texts -->
        <section class="mb-20">
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-3">Texte pentru afișe și flyere</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8 max-w-2xl">Scrie numele exact, „AmBilet.ro” sau „ambilet.ro”. Dacă ai loc, pune și un cod QR generat din linkul evenimentului tău de pe ambilet.ro, ca oamenii să ajungă direct la bilete.</p>
            <div class="grid md:grid-cols-3 gap-4">
                <?php foreach ($posterLines as $line): ?>
                <button type="button" data-copy="<?= htmlspecialchars($line) ?>" class="group flex items-center justify-between gap-4 text-left px-5 py-4 bg-white border border-slate-200 rounded-xl hover:border-primary transition-all">
                    <span class="text-[15px] font-bold text-slate-800"><?= htmlspecialchars($line) ?></span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-400 group-hover:text-primary flex-shrink-0" data-copy-label><?= $copyIcon ?>Copiază</span>
                </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Website embed -->
        <section class="mb-20">
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-3">Pe site-ul tău</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8 max-w-2xl">Copiază codul în pagina evenimentului și înlocuiește „numele-evenimentului” cu linkul tău de pe ambilet.ro, de exemplu ambilet.ro/bilete/concert-exemplu.</p>
            <div class="grid lg:grid-cols-2 gap-6">
                <?php foreach ([['title' => 'Logo cu link', 'code' => $embedLogo, 'preview' => '<img src="' . $brandDir . 'ambilet-logo.svg" alt="Bilete pe AmBilet.ro" width="160" height="72" loading="lazy">'], ['title' => 'Buton „Cumpără bilete”', 'code' => $embedButton, 'preview' => '<span style="display:inline-block;padding:14px 28px;background:#A51C30;color:#fff;border-radius:10px;font:700 16px/1 sans-serif">Cumpără bilete pe AmBilet.ro</span>']] as $embed): ?>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col">
                    <div class="flex items-center justify-center min-h-[140px] p-6 bg-surface border-b border-slate-200"><?= $embed['preview'] ?></div>
                    <div class="p-5 md:p-6 flex flex-col flex-1">
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <div class="text-[15px] font-bold text-slate-800"><?= $embed['title'] ?></div>
                            <button type="button" data-copy="<?= htmlspecialchars($embed['code']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-600 hover:bg-primary hover:text-white transition-all"><span class="flex items-center gap-1.5" data-copy-label><?= $copyIcon ?>Copiază codul</span></button>
                        </div>
                        <pre class="flex-1 text-xs leading-relaxed text-slate-100 bg-secondary rounded-xl p-4 overflow-x-auto whitespace-pre"><code><?= htmlspecialchars($embed['code']) ?></code></pre>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Colors -->
        <section class="mb-20">
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-3">Culorile oficiale</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8 max-w-2xl">Valorile CMYK sunt orientative; pentru tipar cere o probă de culoare de la tipografie. Apasă pe un cod ca să-l copiezi.</p>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 md:gap-6">
                <?php foreach ($colors as $color): ?>
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200">
                    <div class="h-[110px] <?= !empty($color['light']) ? 'border-b border-slate-200' : '' ?>" style="background:<?= $color['hex'] ?>"></div>
                    <div class="p-4 md:p-5">
                        <div class="text-[15px] font-bold text-slate-800">
                            <?= $color['name'] ?>
                        </div>
                        <div class="text-xs text-slate-500 mb-3"><?= $color['note'] ?></div>
                        <div class="space-y-1 text-[13px] text-slate-600">
                            <button type="button" data-copy="<?= $color['hex'] ?>" class="block font-bold text-slate-800 hover:text-primary transition-colors"><span data-copy-label><?= $color['hex'] ?></span></button>
                            <button type="button" data-copy="<?= $color['rgb'] ?>" class="block hover:text-primary transition-colors text-left"><span data-copy-label>RGB <?= $color['rgb'] ?></span></button>
                            <button type="button" data-copy="<?= $color['cmyk'] ?>" class="block hover:text-primary transition-colors text-left"><span data-copy-label>CMYK <?= $color['cmyk'] ?></span></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Typography -->
        <section class="mb-20">
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-3">Font</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8 max-w-2xl">Pe site și în materialele noastre folosim Plus Jakarta Sans. E gratuit, îl poți descărca de pe <a href="https://fonts.google.com/specimen/Plus+Jakarta+Sans" target="_blank" rel="noopener" class="text-primary font-semibold hover:underline">Google Fonts</a>. Pe afișele tale folosește fontul evenimentului; nu e nevoie să-l schimbi pentru noi.</p>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-10">
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-5 border-b border-slate-100">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Titluri</div>
                        <div class="text-xs text-slate-400">ExtraBold 800</div>
                    </div>
                    <div class="text-[32px] md:text-5xl font-extrabold text-slate-800 tracking-tight">Bilete pentru orice ocazie</div>
                </div>
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-5 border-b border-slate-100">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Subtitluri</div>
                        <div class="text-xs text-slate-400">Bold 700</div>
                    </div>
                    <div class="text-xl md:text-2xl font-bold text-slate-800">Descoperă evenimente</div>
                </div>
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-5">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Text</div>
                        <div class="text-xs text-slate-400">Regular 400</div>
                    </div>
                    <div class="text-base text-slate-500 leading-relaxed">AmBilet este platforma de ticketing care conectează organizatorii cu publicul lor.</div>
                </div>
            </div>
        </section>

        <!-- About -->
        <section class="mb-20">
            <div class="grid md:grid-cols-2 gap-10 md:gap-12 items-start">
                <div>
                    <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-4">Despre AmBilet, pe scurt</h2>
                    <p class="text-base text-slate-500 leading-relaxed mb-6">Dacă scrii un comunicat de presă sau o descriere de eveniment și vrei să ne prezinți în două rânduri, poți folosi textul de alături.</p>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center p-5 bg-surface rounded-2xl">
                            <div class="text-2xl md:text-3xl font-extrabold text-primary mb-1">500+</div>
                            <div class="text-sm text-slate-500">Evenimente</div>
                        </div>
                        <div class="text-center p-5 bg-surface rounded-2xl">
                            <div class="text-2xl md:text-3xl font-extrabold text-primary mb-1">100K+</div>
                            <div class="text-sm text-slate-500">Bilete vândute</div>
                        </div>
                        <div class="text-center p-5 bg-surface rounded-2xl">
                            <div class="text-2xl md:text-3xl font-extrabold text-primary mb-1">200+</div>
                            <div class="text-sm text-slate-500">Organizatori</div>
                        </div>
                    </div>
                </div>
                <?php $aboutText = 'AmBilet.ro este o platformă românească de ticketing pentru concerte, teatru, festivaluri, stand-up și alte evenimente. Organizatorii își vând biletele online, iar publicul le cumpără rapid, cu plata cu cardul și biletul primit pe email.'; ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8">
                    <p class="text-[15px] text-slate-700 leading-relaxed mb-5"><?= $aboutText ?></p>
                    <button type="button" data-copy="<?= htmlspecialchars($aboutText) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-600 hover:bg-primary hover:text-white transition-all"><span class="flex items-center gap-1.5" data-copy-label><?= $copyIcon ?>Copiază textul</span></button>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-10 md:p-16 text-center relative overflow-hidden">
            <div class="absolute -top-[100px] -right-[100px] w-[300px] h-[300px] bg-[radial-gradient(circle,rgba(165,28,48,0.2),transparent_70%)] pointer-events-none"></div>
            <div class="relative z-10">
                <h2 class="text-2xl md:text-[32px] font-extrabold text-white mb-3">Ai nevoie de alt format?</h2>
                <p class="text-base text-white/90 mb-8 max-w-xl mx-auto">Scrie-ne dacă îți trebuie logo-ul într-o variantă anume, un banner pentru eveniment sau vrei să verificăm afișul înainte de tipar.</p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="mailto:organizatori@ambilet.ro" class="inline-flex items-center gap-3 px-7 py-4 bg-white/10 border border-white/20 rounded-xl hover:bg-white/15 transition-all">
                        <svg class="w-6 h-6 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span class="text-left"><span class="block text-xs text-white/70">Organizatori</span><span class="block text-base font-semibold text-white">organizatori@ambilet.ro</span></span>
                    </a>
                    <a href="mailto:press@ambilet.ro" class="inline-flex items-center gap-3 px-7 py-4 bg-white/10 border border-white/20 rounded-xl hover:bg-white/15 transition-all">
                        <svg class="w-6 h-6 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span class="text-left"><span class="block text-xs text-white/70">Presă</span><span class="block text-base font-semibold text-white">press@ambilet.ro</span></span>
                    </a>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var text = btn.getAttribute('data-copy');
        var label = btn.querySelector('[data-copy-label]');
        var done = function () {
            if (!label || label.dataset.busy) return;
            var prev = label.innerHTML;
            label.dataset.busy = '1';
            label.textContent = 'Copiat';
            setTimeout(function () { label.innerHTML = prev; delete label.dataset.busy; }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            ta.remove();
        }
    });
});
</script>
