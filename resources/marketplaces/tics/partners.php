<?php
/**
 * TICS.ro - Partners / Pricing Page
 * Hero, benefits, comparison, pricing cards, partner logos, testimonial, FAQ, contact form
 */

require_once __DIR__ . '/includes/config.php';

// Page settings
$pageTitle = 'Parteneri — Devino partener TICS';
$pageDescription = 'Alătură-te ecosistemului de ticketing cu cel mai mic comision din România. Comision de 1%, cashflow direct, white-label complet.';
$bodyClass = 'bg-white';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Parteneri', 'url' => null],
];

// Hero stats
$heroStats = [
    ['value' => '1%', 'label' => 'Comision'],
    ['value' => '500+', 'label' => 'Evenimente'],
    ['value' => '100+', 'label' => 'Parteneri'],
    ['value' => '24h', 'label' => 'Cashflow direct'],
];

// Benefits
$benefits = [
    ['icon' => '&#x1F4B0;', 'iconBg' => 'bg-green-100', 'title' => 'Comision de doar 1%', 'description' => 'Cel mai mic comision din piață. Competitorii cer 5-10%. La un eveniment de 100.000 RON, economisești între 4.000 și 9.000 RON.'],
    ['icon' => '&#x26A1;', 'iconBg' => 'bg-blue-100', 'title' => 'Cashflow direct', 'description' => 'Banii ajung direct în contul tău. Nu mai aștepți 30-60 de zile după eveniment — ai acces imediat la fonduri.'],
    ['icon' => '&#x1F3F7;&#xFE0F;', 'iconBg' => 'bg-purple-100', 'title' => 'White-label complet', 'description' => 'Vinde bilete pe domeniul tău, cu branding-ul tău. Publicul tău nu va ști niciodată că TICS e în spate.'],
    ['icon' => '&#x1F4CA;', 'iconBg' => 'bg-orange-100', 'title' => 'Dashboard & Analytics', 'description' => 'Dashboard în timp real cu vânzări, conversii și date demografice. Export CSV, integrare Google Analytics.'],
    ['icon' => '&#x1F9FE;', 'iconBg' => 'bg-rose-100', 'title' => 'Fiscalizare automată', 'description' => 'Facturi și bonuri fiscale generate automat conform legislației românești. Integrare ANAF completă.'],
    ['icon' => '&#x1F4F1;', 'iconBg' => 'bg-teal-100', 'title' => 'Scanare & Check-in', 'description' => 'Aplicație mobilă pentru staff cu scanare QR, statistici live la poartă și managementul accesului.'],
];

// Comparison table
$comparisonRows = [
    ['feature' => 'Comision', 'tics' => '<span class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">1%</span>', 'iabilet' => '5-8%', 'eventim' => '7-10%'],
    ['feature' => 'Cashflow direct', 'tics' => '<span class="text-green-600">&#x2713;</span>', 'iabilet' => '<span class="text-red-400">&#x2717;</span>', 'eventim' => '<span class="text-red-400">&#x2717;</span>'],
    ['feature' => 'White-label', 'tics' => '<span class="text-green-600">&#x2713;</span>', 'iabilet' => '<span class="text-red-400">&#x2717;</span>', 'eventim' => '<span class="text-gray-400">Parțial</span>'],
    ['feature' => 'Fiscalizare RO', 'tics' => '<span class="text-green-600">&#x2713;</span>', 'iabilet' => '<span class="text-green-600">&#x2713;</span>', 'eventim' => '<span class="text-red-400">&#x2717;</span>'],
    ['feature' => 'API & Integrări', 'tics' => '<span class="text-green-600">&#x2713;</span>', 'iabilet' => '<span class="text-gray-400">Limitat</span>', 'eventim' => '<span class="text-red-400">&#x2717;</span>'],
    ['feature' => 'Carduri beneficii', 'tics' => '<span class="text-green-600">&#x2713;</span>', 'iabilet' => '<span class="text-red-400">&#x2717;</span>', 'eventim' => '<span class="text-red-400">&#x2717;</span>'],
];

// Pricing plans
$plans = [
    [
        'name' => 'Starter',
        'price' => 'Gratuit',
        'priceSuffix' => '',
        'subtitle' => 'pentru evenimente mici',
        'features' => ['Comision 1%', 'Până la 500 bilete/ev.', 'Dashboard de bază', 'Suport email'],
        'checkColor' => 'text-green-500',
        'cta' => 'Începe gratuit',
        'ctaClass' => 'border border-gray-200 text-gray-700 hover:bg-gray-50',
        'dark' => false,
        'popular' => false,
    ],
    [
        'name' => 'Pro',
        'price' => '199 RON',
        'priceSuffix' => '/lună',
        'subtitle' => 'pentru organizatori activi',
        'features' => ['Comision 1%', 'Bilete nelimitate', 'White-label', 'Analytics avansat', 'Suport prioritar'],
        'checkColor' => 'text-indigo-400',
        'cta' => 'Începe acum',
        'ctaClass' => 'bg-indigo-500 text-white hover:bg-indigo-400',
        'dark' => true,
        'popular' => true,
    ],
    [
        'name' => 'Enterprise',
        'price' => 'Custom',
        'priceSuffix' => '',
        'subtitle' => 'pentru festivaluri & rețele',
        'features' => ['Comision negociabil', 'Tot din Pro +', 'API dedicat', 'Account manager', 'SLA garantat'],
        'checkColor' => 'text-green-500',
        'cta' => 'Cere ofertă',
        'ctaClass' => 'border border-gray-200 text-gray-700 hover:bg-gray-50',
        'dark' => false,
        'popular' => false,
    ],
];

// Partner logos
$partnerLogos = ['AmBilet', 'Festival X', 'Club Y', 'Arena Z', 'Brezing', 'Sala W'];

// Testimonial
$testimonial = [
    'quote' => 'De când am trecut pe TICS, nu numai că am redus costurile cu ticketing-ul de la 7% la 1%, dar am și acces instantaneu la bani. E o diferență enormă pentru cashflow.',
    'author' => 'Mihai Dragomir',
    'role' => 'Director, Festival XYZ',
];

// FAQ items
$faqItems = [
    ['question' => 'Cum funcționează comisionul de 1%?', 'answer' => 'Comisionul de 1% se aplică pe fiecare bilet vândut și include procesarea plăților, infrastructura de ticketing și suportul tehnic. Nu există costuri ascunse sau taxe suplimentare.'],
    ['question' => 'Cum funcționează cashflow-ul direct?', 'answer' => 'Plățile sunt procesate direct în contul tău bancar prin sistemul RoPay. Nu intermediem banii — aceștia ajung la tine în maxim 24 de ore lucrătoare de la vânzare.'],
    ['question' => 'Pot folosi TICS pe domeniul meu?', 'answer' => 'Da, planurile Pro și Enterprise includ white-label complet. Pagina de vânzare, emailurile și biletele vor avea branding-ul tău, pe domeniul tău.'],
    ['question' => 'Ce metode de plată sunt acceptate?', 'answer' => 'Acceptăm carduri Visa/Mastercard, transfer bancar, Apple Pay, Google Pay și carduri de beneficii (Edenred, Pluxee, Up Romania). Toate într-un checkout fluid.'],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-to-b from-gray-50 to-white">
        <div class="absolute top-20 right-10 w-72 h-72 bg-indigo-100 rounded-full blur-3xl opacity-40 float"></div>
        <div class="absolute bottom-10 left-10 w-60 h-60 bg-violet-100 rounded-full blur-3xl opacity-40 float" style="animation-delay:2s"></div>
        <div class="max-w-6xl mx-auto px-4 lg:px-8 py-16 lg:py-24 relative">
            <div class="max-w-3xl mx-auto text-center">
                <div class="anim inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-full mb-6">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-sm font-medium text-indigo-700">Acceptăm noi parteneri</span>
                </div>
                <h1 class="anim ad1 text-4xl lg:text-5xl font-bold text-gray-900 leading-tight mb-5">Crește-ți afacerea cu <span class="bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">TICS.ro</span></h1>
                <p class="anim ad2 text-lg text-gray-600 leading-relaxed mb-8 max-w-2xl mx-auto">Alătură-te ecosistemului de ticketing cu cel mai mic comision din România. Oferim organizatorilor de evenimente controlul complet asupra vânzărilor, cashflow-ului și datelor.</p>
                <div class="anim ad3 flex items-center justify-center gap-3">
                    <a href="#contact" class="px-7 py-3.5 bg-gray-900 text-white text-sm font-semibold rounded-full hover:bg-gray-800 transition-colors shadow-lg shadow-gray-900/20">Devino partener</a>
                    <a href="#benefits" class="px-7 py-3.5 border border-gray-200 text-gray-700 text-sm font-semibold rounded-full hover:bg-gray-50 transition-colors">Află mai multe</a>
                </div>
            </div>
            <!-- Stats -->
            <div class="anim ad4 grid grid-cols-2 md:grid-cols-4 gap-4 mt-16 max-w-3xl mx-auto">
                <?php foreach ($heroStats as $stat): ?>
                <div class="text-center p-5 bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <p class="text-3xl font-bold text-gray-900"><?= e($stat['value']) ?></p>
                    <p class="text-sm text-gray-500 mt-1"><?= e($stat['label']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section id="benefits" class="py-16 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 lg:px-8">
            <div class="text-center mb-12">
                <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider mb-2">De ce TICS</p>
                <h2 class="text-2xl lg:text-3xl font-bold text-gray-900">Tot ce ai nevoie, fără compromisuri</h2>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($benefits as $benefit): ?>
                <div class="feature-card bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="w-12 h-12 <?= e($benefit['iconBg']) ?> rounded-xl flex items-center justify-center mb-4 text-xl"><?= $benefit['icon'] ?></div>
                    <h3 class="font-semibold text-gray-900 mb-2"><?= e($benefit['title']) ?></h3>
                    <p class="text-sm text-gray-600 leading-relaxed"><?= e($benefit['description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Comparison -->
    <section class="py-16 bg-white">
        <div class="max-w-4xl mx-auto px-4 lg:px-8">
            <div class="text-center mb-10"><h2 class="text-2xl font-bold text-gray-900">TICS vs. competiție</h2></div>
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left py-4 px-5 font-medium text-gray-500">Funcționalitate</th>
                            <th class="py-4 px-4 text-center">
                                <span class="inline-flex items-center gap-1.5 font-semibold text-gray-900">
                                    <div class="w-5 h-5 bg-gray-900 rounded flex items-center justify-center"><span class="text-white text-xs font-bold">T</span></div>
                                    TICS
                                </span>
                            </th>
                            <th class="py-4 px-4 text-center font-medium text-gray-400">iaBilet</th>
                            <th class="py-4 px-4 text-center font-medium text-gray-400">Eventim</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparisonRows as $index => $row): ?>
                        <tr class="<?= $index < count($comparisonRows) - 1 ? 'border-b border-gray-100' : '' ?>">
                            <td class="py-3.5 px-5 text-gray-700"><?= e($row['feature']) ?></td>
                            <td class="py-3.5 px-4 text-center"><?= $row['tics'] ?></td>
                            <td class="py-3.5 px-4 text-center text-gray-400"><?= $row['iabilet'] ?></td>
                            <td class="py-3.5 px-4 text-center text-gray-400"><?= $row['eventim'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Partner Plans -->
    <section id="plans" class="py-16 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 lg:px-8">
            <div class="text-center mb-12">
                <p class="text-sm font-semibold text-indigo-600 uppercase tracking-wider mb-2">Planuri</p>
                <h2 class="text-2xl lg:text-3xl font-bold text-gray-900">Alege planul potrivit</h2>
            </div>
            <div class="grid md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                <?php foreach ($plans as $plan): ?>
                <div class="plan-card <?= $plan['dark'] ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200' ?> rounded-2xl p-7<?= $plan['popular'] ? ' relative overflow-hidden' : '' ?>">
                    <?php if ($plan['popular']): ?>
                    <div class="absolute top-0 right-0 px-4 py-1.5 bg-indigo-500 text-white text-xs font-bold rounded-bl-xl">Popular</div>
                    <?php endif; ?>
                    <p class="text-sm font-semibold <?= $plan['dark'] ? 'text-gray-400' : 'text-gray-500' ?> uppercase tracking-wider mb-1"><?= e($plan['name']) ?></p>
                    <p class="text-3xl font-bold <?= $plan['dark'] ? '' : 'text-gray-900' ?> mb-1"><?= e($plan['price']) ?><?php if ($plan['priceSuffix']): ?><span class="text-base font-normal <?= $plan['dark'] ? 'text-gray-400' : 'text-gray-500' ?>"><?= e($plan['priceSuffix']) ?></span><?php endif; ?></p>
                    <p class="text-sm <?= $plan['dark'] ? 'text-gray-400' : 'text-gray-500' ?> mb-6"><?= e($plan['subtitle']) ?></p>
                    <ul class="space-y-3 mb-8 text-sm <?= $plan['dark'] ? 'text-gray-300' : 'text-gray-600' ?>">
                        <?php foreach ($plan['features'] as $feature): ?>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 <?= e($plan['checkColor']) ?> flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <?= e($feature) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="#contact" class="block w-full py-3 <?= $plan['ctaClass'] ?> text-sm font-semibold rounded-full text-center transition-colors"><?= e($plan['cta']) ?></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Partner Logos -->
    <section id="partners" class="py-16 bg-white">
        <div class="max-w-6xl mx-auto px-4 lg:px-8">
            <div class="text-center mb-10">
                <p class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-2">Ei ne-au ales</p>
                <h2 class="text-2xl font-bold text-gray-900">Parteneri de încredere</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($partnerLogos as $logo): ?>
                <div class="logo-card bg-gray-50 rounded-2xl border border-gray-100 p-6 flex items-center justify-center aspect-[3/2]">
                    <span class="text-lg font-bold text-gray-300"><?= e($logo) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Testimonial -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 lg:px-8 text-center">
            <svg class="w-10 h-10 text-gray-300 mx-auto mb-6" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151C7.546 6.068 5.983 8.789 5.983 11H10v10H0z"/></svg>
            <blockquote class="text-xl lg:text-2xl font-medium text-gray-900 leading-relaxed mb-6">&ldquo;<?= e($testimonial['quote']) ?>&rdquo;</blockquote>
            <div class="flex items-center justify-center gap-3">
                <div class="w-12 h-12 bg-gray-300 rounded-full"></div>
                <div class="text-left">
                    <p class="font-semibold text-gray-900"><?= e($testimonial['author']) ?></p>
                    <p class="text-sm text-gray-500"><?= e($testimonial['role']) ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-16 bg-white">
        <div class="max-w-2xl mx-auto px-4 lg:px-8">
            <div class="text-center mb-10"><h2 class="text-2xl font-bold text-gray-900">Întrebări frecvente</h2></div>
            <div class="space-y-3">
                <?php foreach ($faqItems as $faq): ?>
                <div class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
                    <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-5 text-left">
                        <span class="font-medium text-gray-900 text-sm pr-4"><?= e($faq['question']) ?></span>
                        <svg class="faq-icon w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
                    </button>
                    <div class="faq-content">
                        <p class="px-5 pb-5 text-sm text-gray-600 leading-relaxed"><?= e($faq['answer']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contact CTA -->
    <section id="contact" class="py-16 bg-gray-900 text-white">
        <div class="max-w-3xl mx-auto px-4 lg:px-8">
            <div class="text-center mb-10">
                <h2 class="text-2xl lg:text-3xl font-bold">Hai să discutăm</h2>
                <p class="text-gray-400 mt-2">Completează formularul și te contactăm în maxim 24 de ore.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="text-xs text-gray-400 mb-1 block">Nume complet *</label><input type="text" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-gray-500 outline-none focus:border-indigo-500 text-sm" placeholder="Ion Popescu"></div>
                <div><label class="text-xs text-gray-400 mb-1 block">Email *</label><input type="email" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-gray-500 outline-none focus:border-indigo-500 text-sm" placeholder="ion@companie.ro"></div>
                <div><label class="text-xs text-gray-400 mb-1 block">Companie</label><input type="text" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-gray-500 outline-none focus:border-indigo-500 text-sm" placeholder="Numele companiei"></div>
                <div><label class="text-xs text-gray-400 mb-1 block">Telefon</label><input type="tel" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-gray-500 outline-none focus:border-indigo-500 text-sm" placeholder="+40 7XX XXX XXX"></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-400 mb-1 block">Mesaj</label><textarea rows="3" class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-gray-500 outline-none focus:border-indigo-500 text-sm resize-none" placeholder="Spune-ne despre evenimentele tale..."></textarea></div>
            </div>
            <button class="mt-5 w-full sm:w-auto px-8 py-3.5 bg-indigo-500 text-white text-sm font-semibold rounded-full hover:bg-indigo-400 transition-colors">Trimite mesajul</button>
        </div>
    </section>

<?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    function toggleFaq(btn){const c=btn.nextElementSibling;const i=btn.querySelector('.faq-icon');c.classList.toggle('open');i.classList.toggle('open')}
    </script>
</body>
</html>
