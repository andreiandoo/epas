<?php
/**
 * TICS.ro - Blog Single Article Page
 * Article with reading progress, TOC, share, related articles
 */

require_once __DIR__ . '/includes/config.php';

$articleSlug = $_GET['slug'] ?? 'ghidul-festivalurilor-romania-2026';

// Demo article data
$article = [
    'slug' => 'ghidul-festivalurilor-romania-2026',
    'title' => 'Ghidul complet al festivalurilor din România 2026: ce merită și ce nu',
    'excerpt' => 'Am analizat toate festivalurile confirmate pentru 2026, de la Untold și Electric Castle la cele mai mici festivaluri boutique. Descoperă ce te așteaptă vara aceasta, cu prețuri, date și sfaturi.',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1200&h=600&fit=crop',
    'imageCaption' => 'Untold Festival 2025 — Foto: TICS.ro',
    'categories' => [
        ['name' => 'Festivaluri', 'slug' => 'festivaluri', 'color' => 'pink'],
        ['name' => 'Ghid', 'slug' => 'ghiduri', 'color' => 'indigo'],
    ],
    'author' => [
        'name' => 'Andrei Popescu',
        'role' => 'Editor-șef TICS Blog',
        'bio' => 'Editor-șef al blogului TICS.ro. Pasionat de festivaluri și muzică live, a participat la peste 80 de festivaluri în România și Europa.',
        'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop',
    ],
    'date' => '5 Feb 2026',
    'readTime' => '10 min citire',
    'views' => '12.4K',
];

// Demo tags
$articleTags = ['Festivaluri', 'Untold', 'Electric Castle', 'Bilete', 'Ghid'];

// Demo TOC sections
$tocSections = [
    ['id' => 'overview', 'title' => 'Panorama 2026'],
    ['id' => 'top5', 'title' => 'Top 5 festivaluri'],
    ['id' => 'budget', 'title' => 'Ghid de buget'],
    ['id' => 'tips', 'title' => 'Sfaturi de supraviețuire'],
    ['id' => 'conclusion', 'title' => 'Concluzie'],
];

// Demo related articles
$relatedArticles = [
    [
        'slug' => 'electric-castle-2026-primele-nume',
        'title' => 'Electric Castle 2026: primele nume confirmate',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500&h=320&fit=crop',
        'category' => ['name' => 'Știri', 'color' => 'blue'],
        'readTime' => '3 min',
        'date' => '28 Ian 2026',
    ],
    [
        'slug' => '10-lucruri-de-luat-la-festival',
        'title' => '10 lucruri de luat la festival pe care sigur le uiți',
        'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=500&h=320&fit=crop',
        'category' => ['name' => 'Tips', 'color' => 'green'],
        'readTime' => '5 min',
        'date' => '1 Feb 2026',
    ],
    [
        'slug' => 'recenzie-concert-subcarpati',
        'title' => 'Recenzie: Concertul Subcarpați de la Arenele Romane',
        'image' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=500&h=320&fit=crop',
        'category' => ['name' => 'Recenzie', 'color' => 'orange'],
        'readTime' => '7 min',
        'date' => '25 Ian 2026',
    ],
];

// Page settings
$pageTitle = $article['title'] . ' — Blog TICS.ro';
$pageDescription = $article['excerpt'];
$pageImage = $article['image'];
$bodyClass = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Blog', 'url' => '/blog'],
    ['name' => $article['categories'][0]['name'], 'url' => '/blog/categorie/' . $article['categories'][0]['slug']],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Reading progress -->
<div class="reading-progress" id="progressBar" style="width:0%"></div>

<!-- Article Header -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 lg:px-8 py-8 lg:py-12">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
            <a href="/blog" class="hover:text-gray-900 transition-colors">Blog</a><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/blog/categorie/<?= e($article['categories'][0]['slug']) ?>" class="hover:text-gray-900 transition-colors"><?= e($article['categories'][0]['name']) ?></a>
        </div>
        <div class="flex items-center gap-2 mb-4">
            <?php foreach ($article['categories'] as $cat): ?>
            <span class="px-2.5 py-1 bg-<?= e($cat['color']) ?>-100 text-<?= e($cat['color']) ?>-700 text-xs font-medium rounded-full"><?= e($cat['name']) ?></span>
            <?php endforeach; ?>
        </div>
        <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 leading-tight mb-5"><?= e($article['title']) ?></h1>
        <p class="text-lg text-gray-600 leading-relaxed mb-6"><?= e($article['excerpt']) ?></p>

        <!-- Author + Meta -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-6 border-t border-gray-100">
            <div class="flex items-center gap-3">
                <img src="<?= e($article['author']['avatar']) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-gray-100" alt="<?= e($article['author']['name']) ?>">
                <div><p class="font-medium text-gray-900"><?= e($article['author']['name']) ?></p><p class="text-sm text-gray-500"><?= e($article['author']['role']) ?></p></div>
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg><?= e($article['date']) ?></span>
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><?= e($article['readTime']) ?></span>
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><?= e($article['views']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Featured Image -->
<div class="max-w-4xl mx-auto px-4 lg:px-8 -mb-8 relative z-10" style="margin-top:-1px">
    <div class="relative aspect-[2/1] rounded-2xl overflow-hidden shadow-lg">
        <img src="<?= e($article['image']) ?>" class="absolute inset-0 w-full h-full object-cover" alt="<?= e($article['title']) ?>">
    </div>
    <p class="text-xs text-gray-400 text-center mt-2"><?= e($article['imageCaption']) ?></p>
</div>

<main class="max-w-6xl mx-auto px-4 lg:px-8 pt-16 pb-10">
    <div class="lg:flex gap-10">
        <!-- Article Body -->
        <article class="flex-1 max-w-3xl">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:p-10">
                <div class="prose text-base" id="articleContent">
                    <h2 id="overview">Panorama festivalurilor 2026</h2>
                    <p>România a devenit în ultimii ani una dintre cele mai importante destinații europene pentru festivaluri muzicale. Cu <strong>peste 50 de festivaluri confirmate</strong> pentru sezonul 2026, alegerea poate fi copleșitoare. Tocmai de aceea am creat acest ghid — să te ajutăm să alegi experiența perfectă.</p>
                    <p>De la mega-evenimente precum Untold și Electric Castle, care atrag sute de mii de participanți, până la festivaluri boutique de câteva sute de persoane, oferta este extrem de diversificată.</p>

                    <blockquote>„2026 promite a fi anul în care festivalurile românești vor atinge maturitatea. Line-up-urile sunt mai diverse, experiențele mai complexe." — Vlad Caia, curator muzical</blockquote>

                    <h2 id="top5">Top 5 festivaluri pe care nu le poți rata</h2>
                    <h3>1. Untold Festival — Cluj-Napoca</h3>
                    <p>Cel mai mare festival din România revine cu o ediție aniversară de 10 ani. <strong>Datele confirmate: 6-9 august 2026</strong>. Prețul abonamentului pleacă de la 599 RON în faza de early bird, iar organizatorii promit un headliner „care nu a mai cântat niciodată în România".</p>

                    <h3>2. Electric Castle — Bonțida</h3>
                    <p>Festivalul de la castelul Bánffy continuă să fie cel mai apreciat de publicul alternativ. <strong>Date: 15-19 iulie 2026</strong>. Noutatea anului: o scenă dedicată exclusiv artiștilor români.</p>

                    <h3>3. Neversea — Constanța</h3>
                    <p>Festivalul de pe plajă revine cu o formulă extinsă pe 4 zile. Atmosfera unică cu apusurile peste mare și line-up-ul puternic de EDM fac din Neversea o experiență unică.</p>

                    <h3>4. Summer Well — Buftea</h3>
                    <p>Elegant, rafinat și cu un line-up mereu surprinzător. Summer Well rămâne festivalul pentru cei care preferă indie-ul și alternativul într-un cadru de poveste.</p>

                    <h3>5. Jazz in the Park — Cluj-Napoca</h3>
                    <p>Gratuit și de calitate excepțională. Jazz in the Park este o bijuterie a scenei culturale clujene, cu artiști internaționali de jazz, world music și experimental.</p>

                    <img src="https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=900&h=450&fit=crop" alt="Electric Castle 2025">
                    <p class="text-sm text-gray-500" style="margin-top:-.75rem;text-align:center">Electric Castle — scena principală la apus</p>

                    <h2 id="budget">Ghid de buget: cât costă un festival</h2>
                    <p>Un weekend la festival poate costa între 300 RON (pentru un festival mic, fără cazare specială) și peste 2.000 RON (abonament VIP + cazare la un festival mare). Iată un breakdown tipic:</p>
                    <ul>
                        <li><strong>Abonament general:</strong> 300 - 700 RON</li>
                        <li><strong>Transport:</strong> 50 - 200 RON (în funcție de distanță)</li>
                        <li><strong>Cazare/Camping:</strong> 0 - 500 RON</li>
                        <li><strong>Mâncare și băuturi:</strong> 150 - 400 RON</li>
                        <li><strong>Extras (merch, activități):</strong> 50 - 200 RON</li>
                    </ul>
                    <p><strong>Total estimat per festival:</strong> 550 - 2.000 RON</p>

                    <h2 id="tips">Sfaturi de supraviețuire</h2>
                    <p>Indiferent de festivalul ales, câteva reguli de aur te pot salva:</p>
                    <ul>
                        <li><strong>Cumpără biletele devreme</strong> — early bird-ul poate fi cu 40% mai ieftin</li>
                        <li><strong>Investește în dopuri de urechi de calitate</strong> — urechile tale îți vor mulțumi</li>
                        <li><strong>Poartă încălțăminte confortabilă</strong> — vei merge 15-20 km pe zi</li>
                        <li><strong>Baterie externă de mare capacitate</strong> — minimum 20.000 mAh</li>
                        <li><strong>Pălărie și cremă solară</strong> — chiar și în zilele înnorate</li>
                    </ul>

                    <h2 id="conclusion">Concluzie</h2>
                    <p>România oferă un ecosistem de festivaluri incredibil de divers. Fie că ești fan de EDM, rock, jazz sau indie, există cu siguranță un festival care ți se potrivește. Sfatul nostru: alege maximum 2-3 festivaluri pe sezon și bucură-te din plin de fiecare experiență.</p>
                    <p>Toate biletele pentru festivalurile menționate sunt disponibile pe <a href="/">TICS.ro</a>. Urmărește paginile festivalurilor pentru notificări când se pun în vânzare noi tranșe de bilete.</p>
                </div>
            </div>

            <!-- Tags -->
            <div class="flex flex-wrap items-center gap-2 mt-6">
                <span class="text-xs text-gray-400 font-medium">Etichete:</span>
                <?php foreach ($articleTags as $tag): ?>
                <a href="#" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-medium rounded-full hover:border-gray-300 hover:bg-gray-50 transition-all"><?= e($tag) ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Share bar -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm font-medium text-gray-900">Distribuie articolul:</p>
                <div class="flex items-center gap-2">
                    <a href="#" onclick="shareOnFacebook();return false" class="share-btn w-10 h-10 bg-[#1877f2] text-white rounded-full flex items-center justify-center"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                    <a href="#" onclick="shareOnX();return false" class="share-btn w-10 h-10 bg-black text-white rounded-full flex items-center justify-center"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                    <a href="#" onclick="shareOnLinkedIn();return false" class="share-btn w-10 h-10 bg-[#0a66c2] text-white rounded-full flex items-center justify-center"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                    <button onclick="copyLink()" class="share-btn w-10 h-10 bg-gray-100 text-gray-600 rounded-full flex items-center justify-center hover:bg-gray-200"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg></button>
                </div>
            </div>

            <!-- Author box -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mt-6 flex gap-4">
                <img src="<?= e($article['author']['avatar']) ?>" class="w-16 h-16 rounded-xl object-cover flex-shrink-0" alt="<?= e($article['author']['name']) ?>">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-1"><?= e($article['author']['name']) ?></h3>
                    <p class="text-sm text-gray-600 mb-3"><?= e($article['author']['bio']) ?></p>
                    <a href="#" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toate articolele &rarr;</a>
                </div>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="hidden lg:block w-64 flex-shrink-0 space-y-6 mt-0">
            <div class="sticky top-24 space-y-6">
                <!-- TOC -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-3 text-sm">Cuprins</h3>
                    <nav class="space-y-1">
                        <?php foreach ($tocSections as $i => $sec): ?>
                        <a href="#<?= e($sec['id']) ?>" class="toc-link <?= $i === 0 ? 'active ' : '' ?>block text-sm text-gray-600 py-1.5 pl-3"><?= e($sec['title']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <!-- CTA -->
                <div class="bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-5 text-white">
                    <p class="font-semibold mb-1 text-sm">🎫 Bilete disponibile</p>
                    <p class="text-xs text-white/70 mb-3">Toate festivalurile menționate au bilete pe TICS.ro</p>
                    <a href="/evenimente" class="block w-full py-2.5 bg-white text-gray-900 text-sm font-semibold rounded-xl text-center hover:bg-gray-100 transition-colors">Cumpără bilete</a>
                </div>
                <!-- Newsletter mini -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-2 text-sm">📬 Newsletter</h3>
                    <p class="text-xs text-gray-500 mb-3">Articole noi în fiecare săptămână.</p>
                    <input type="email" placeholder="email@exemplu.ro" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-gray-900/10 mb-2">
                    <button class="w-full py-2 bg-gray-900 text-white text-xs font-semibold rounded-lg hover:bg-gray-800 transition-colors">Abonează-te</button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Related Articles -->
    <section class="mt-12">
        <h2 class="text-lg font-semibold text-gray-900 mb-5">Articole similare</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($relatedArticles as $rel): ?>
            <a href="/blog/<?= e($rel['slug']) ?>" class="blog-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                <div class="relative aspect-[16/10] overflow-hidden"><img src="<?= e($rel['image']) ?>" class="absolute inset-0 w-full h-full object-cover blog-img" alt="<?= e($rel['title']) ?>"></div>
                <div class="p-5">
                    <div class="flex items-center gap-2 mb-2"><span class="px-2 py-0.5 bg-<?= e($rel['category']['color']) ?>-100 text-<?= e($rel['category']['color']) ?>-700 text-xs font-medium rounded-full"><?= e($rel['category']['name']) ?></span><span class="text-xs text-gray-400"><?= e($rel['readTime']) ?></span></div>
                    <h3 class="font-semibold text-gray-900 mb-2 leading-snug group-hover:text-indigo-600 transition-colors"><?= e($rel['title']) ?></h3>
                    <p class="text-xs text-gray-500"><?= e($rel['date']) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<script>
// Reading progress
window.addEventListener('scroll',()=>{const st=window.scrollY,dh=document.documentElement.scrollHeight-window.innerHeight;document.getElementById('progressBar').style.width=Math.min(100,st/dh*100)+'%'});
// TOC active tracking
const sections=[<?php echo implode(',', array_map(fn($s) => "'" . $s['id'] . "'", $tocSections)); ?>];
window.addEventListener('scroll',()=>{let current='';sections.forEach(id=>{const el=document.getElementById(id);if(el&&el.getBoundingClientRect().top<150)current=id});document.querySelectorAll('.toc-link').forEach(l=>{l.classList.toggle('active',l.getAttribute('href')==='#'+current)})});
// Share functions
function shareOnFacebook(){window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(window.location.href),'_blank','width=600,height=400')}
function shareOnX(){window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent(window.location.href)+'&text='+encodeURIComponent(document.title),'_blank','width=600,height=400')}
function shareOnLinkedIn(){window.open('https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(window.location.href),'_blank','width=600,height=400')}
function copyLink(){navigator.clipboard.writeText(window.location.href)}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
