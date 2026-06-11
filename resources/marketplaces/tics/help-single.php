<?php
/**
 * TICS.ro - Help Center Single Article Page
 * Article with prose content, callouts, vote system, TOC sidebar, related articles
 */

require_once __DIR__ . '/includes/config.php';

// ============================================================================
// DEMO ARTICLE DATA
// ============================================================================

$article = [
    'title'       => 'Cum primesc biletul după achiziție?',
    'category'    => ['name' => 'Bilete & comenzi', 'slug' => 'bilete-comenzi'],
    'tags'        => [
        ['label' => 'Bilete & comenzi', 'class' => 'bg-indigo-50 text-indigo-700'],
        ['label' => 'Popular',          'class' => 'bg-amber-50 text-amber-700'],
    ],
    'lastUpdated' => '12 feb 2026',
    'views'       => '4.2k vizualizări',
    'readTime'    => '3 min citire',
];

// Content sections (used both in prose and TOC)
$tocSections = [
    ['id' => 'metode-primire', 'title' => 'Metode de primire'],
    ['id' => 'nu-am-primit',   'title' => 'Nu am primit biletul'],
    ['id' => 'retrimite',      'title' => 'Retrimite biletul'],
    ['id' => 'la-eveniment',   'title' => 'La eveniment'],
];

// Article content blocks (ordered prose sections with callouts)
$contentSections = [
    [
        'type' => 'paragraph',
        'text' => 'Imediat după finalizarea plății, biletul tău digital este generat automat și trimis pe adresa de email utilizată la achiziție. Procesul durează de obicei câteva secunde.',
    ],
    [
        'type'  => 'heading',
        'id'    => 'metode-primire',
        'title' => 'Metode de primire a biletului',
    ],
    [
        'type' => 'paragraph',
        'text' => 'Biletul tău ajunge la tine prin mai multe canale simultan:',
    ],
    [
        'type'  => 'ordered-list',
        'items' => [
            '<strong>Email</strong> — Primești un email de confirmare cu biletul atașat în format PDF. Email-ul conține codul QR unic și detaliile evenimentului.',
            '<strong>Contul TICS</strong> — Dacă ai cont, biletul apare automat în secțiunea <strong>Biletele mele</strong>.',
            '<strong>Aplicația mobilă</strong> — Biletul este sincronizat și salvat local pe telefon, funcționând și offline.',
        ],
    ],
    [
        'type'    => 'callout',
        'variant' => 'info',
        'icon'    => 'info',
        'html'    => '<strong>Nu este nevoie să printezi biletul.</strong> Prezintă codul QR direct de pe telefon la intrarea în locație.',
    ],
    [
        'type'  => 'heading',
        'id'    => 'nu-am-primit',
        'title' => 'Nu am primit biletul pe email?',
    ],
    [
        'type' => 'paragraph',
        'text' => 'Dacă nu găsești email-ul de confirmare, verifică:',
    ],
    [
        'type'  => 'unordered-list',
        'items' => [
            'Folderul <strong>Spam / Junk</strong> din căsuța de email',
            'Că adresa de email introdusă la achiziție este corectă (verifică în istoricul comenzilor)',
            'Că plata a fost procesată cu succes — în caz contrar, nu se generează bilet',
        ],
    ],
    [
        'type'    => 'callout',
        'variant' => 'warn',
        'icon'    => 'warn',
        'html'    => 'Dacă ai folosit Apple Pay sau Google Pay, email-ul de confirmare este trimis la adresa asociată contului tău Apple/Google, nu la cea din profilul TICS.',
    ],
    [
        'type'  => 'heading',
        'id'    => 'retrimite',
        'title' => 'Retrimite biletul',
    ],
    [
        'type' => 'paragraph',
        'text' => 'Poți retrimite biletul pe email oricând:',
    ],
    [
        'type'  => 'ordered-list',
        'items' => [
            'Autentifică-te în contul tău pe <a href="#">tics.ro</a> sau în aplicație',
            'Mergi la <strong>Biletele mele</strong>',
            'Selectează biletul dorit',
            'Apasă <strong>Retrimite pe email</strong>',
        ],
    ],
    [
        'type'    => 'callout',
        'variant' => 'success',
        'icon'    => 'success',
        'html'    => '<strong>Tip:</strong> Dacă ai cumpărat fără cont, poți recupera biletul folosind funcția „Recuperare bilet" de pe pagina principală — ai nevoie doar de adresa de email.',
    ],
    [
        'type'  => 'heading',
        'id'    => 'la-eveniment',
        'title' => 'La eveniment',
    ],
    [
        'type' => 'paragraph',
        'text' => 'La intrarea în locație, arată <strong>codul QR</strong> de pe bilet. Acesta este scanat de staff cu aplicația TICS Check-in. După scanare, biletul este marcat ca „utilizat" și nu mai poate fi folosit din nou.',
    ],
    [
        'type' => 'paragraph',
        'text' => 'Dacă ai mai multe bilete (ex. pentru prieteni), fiecare are un cod QR unic și trebuie prezentat separat.',
    ],
];

// Vote stats
$voteStats = [
    'positivePercent' => 94,
    'totalVotes'      => 847,
];

// Quick actions (sidebar)
$quickActions = [
    [
        'label' => 'Distribuie',
        'url'   => '#',
        'icon'  => '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>',
    ],
    [
        'label' => 'Printează',
        'url'   => '#',
        'icon'  => '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>',
    ],
];

// Related articles
$relatedArticles = [
    [
        'title'    => 'Pot returna sau schimba un bilet?',
        'category' => 'Bilete & comenzi',
        'url'      => '#',
    ],
    [
        'title'    => 'Cum transfer un bilet altcuiva?',
        'category' => 'Bilete & comenzi',
        'url'      => '#',
    ],
    [
        'title'    => 'Ce metode de plată acceptați?',
        'category' => 'Plăți & facturare',
        'url'      => '#',
    ],
    [
        'title'    => 'Biletul funcționează offline?',
        'category' => 'Aplicația mobilă',
        'url'      => '#',
    ],
];

// ============================================================================
// PAGE SETTINGS
// ============================================================================

$pageTitle       = $article['title'];
$pageDescription = 'Află cum primești biletul după achiziție pe TICS.ro — email, cont sau aplicația mobilă.';
$bodyClass       = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Centru de ajutor',          'url' => '/centru-ajutor'],
    ['name' => $article['category']['name'], 'url' => '/centru-ajutor/' . $article['category']['slug']],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 lg:px-8 py-4">
        <nav class="flex items-center gap-2 text-sm text-gray-500 flex-wrap">
            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                <a href="<?= e($crumb['url']) ?>" class="hover:text-gray-900 transition-colors"><?= e($crumb['name']) ?></a>
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <?php endforeach; ?>
            <span class="text-gray-900 font-medium"><?= e($article['title']) ?></span>
        </nav>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 lg:px-8 py-8">
    <div class="flex gap-10">

        <!-- Main Content -->
        <article class="flex-1 min-w-0">
            <!-- Article Header -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:p-8 mb-6">
                <div class="flex items-center gap-2 mb-4">
                    <?php foreach ($article['tags'] as $tag): ?>
                        <span class="px-2.5 py-1 <?= e($tag['class']) ?> text-xs font-semibold rounded-full"><?= e($tag['label']) ?></span>
                    <?php endforeach; ?>
                </div>
                <h1 class="text-2xl lg:text-[1.75rem] font-bold text-gray-900 leading-tight mb-3"><?= e($article['title']) ?></h1>
                <div class="flex items-center gap-4 flex-wrap text-sm text-gray-400">
                    <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Actualizat: <?= e($article['lastUpdated']) ?></span>
                    <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><?= e($article['views']) ?></span>
                    <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg><?= e($article['readTime']) ?></span>
                </div>
            </div>

            <!-- Article Body -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:p-8">
                <div class="prose max-w-none">
                    <?php foreach ($contentSections as $section): ?>
                        <?php if ($section['type'] === 'paragraph'): ?>
                            <p><?= $section['text'] ?></p>

                        <?php elseif ($section['type'] === 'heading'): ?>
                            <h2 id="<?= e($section['id']) ?>"><?= e($section['title']) ?></h2>

                        <?php elseif ($section['type'] === 'ordered-list'): ?>
                            <ol>
                                <?php foreach ($section['items'] as $item): ?>
                                    <li><?= $item ?></li>
                                <?php endforeach; ?>
                            </ol>

                        <?php elseif ($section['type'] === 'unordered-list'): ?>
                            <ul>
                                <?php foreach ($section['items'] as $item): ?>
                                    <li><?= $item ?></li>
                                <?php endforeach; ?>
                            </ul>

                        <?php elseif ($section['type'] === 'callout'): ?>
                            <div class="callout callout-<?= e($section['variant']) ?>">
                                <?php if ($section['icon'] === 'info'): ?>
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                <?php elseif ($section['icon'] === 'warn'): ?>
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <?php elseif ($section['icon'] === 'success'): ?>
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <?php endif; ?>
                                <div><?= $section['html'] ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Vote Section -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:p-8 mt-4" id="voteSection">
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-900 mb-4">A fost util acest articol?</p>
                    <div id="voteButtons" class="flex items-center justify-center gap-3">
                        <button onclick="vote('yes',this)" class="vote-btn flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:border-green-300 hover:bg-green-50">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
                            Da, mulțumesc!
                        </button>
                        <button onclick="vote('no',this)" class="vote-btn flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:border-red-300 hover:bg-red-50">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76.94m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg>
                            Nu, am nevoie de mai mult
                        </button>
                    </div>
                    <!-- Thank you message -->
                    <div id="voteThanks" class="hidden"></div>
                    <!-- Feedback form -->
                    <div id="feedbackWrap" class="feedback-area mt-4">
                        <div>
                            <div class="max-w-md mx-auto">
                                <p class="text-sm text-gray-600 mb-3" id="feedbackLabel"></p>
                                <textarea id="feedbackText" rows="3" placeholder="Spune-ne cum putem îmbunătăți acest articol..." class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 resize-none placeholder:text-gray-400"></textarea>
                                <div class="flex items-center justify-end gap-2 mt-2">
                                    <button onclick="closeFeedback()" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-900 transition-colors">Renunță</button>
                                    <button onclick="submitFeedback()" class="px-5 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors">Trimite feedback</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Stats bar -->
                    <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-center gap-6 text-xs text-gray-400">
                        <span id="voteStats"><span class="text-green-600 font-semibold"><?= e($voteStats['positivePercent']) ?>%</span> au considerat util · <?= e($voteStats['totalVotes']) ?> voturi</span>
                    </div>
                </div>
            </div>

            <!-- Related Articles -->
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Articole similare</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <?php foreach ($relatedArticles as $rel): ?>
                        <a href="<?= e($rel['url']) ?>" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-indigo-200 transition-colors group block">
                            <p class="text-sm font-medium text-gray-900 group-hover:text-indigo-600 transition-colors mb-1"><?= e($rel['title']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($rel['category']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="hidden lg:block w-56 flex-shrink-0">
            <div class="sticky top-24">
                <!-- TOC -->
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Cuprins</p>
                <nav class="mb-8 space-y-0.5" id="toc">
                    <?php foreach ($tocSections as $i => $sec): ?>
                        <a href="#<?= e($sec['id']) ?>" class="toc-link<?= $i === 0 ? ' active' : '' ?>"><?= e($sec['title']) ?></a>
                    <?php endforeach; ?>
                </nav>

                <!-- Quick actions -->
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Acțiuni rapide</p>
                <div class="space-y-1.5 mb-8">
                    <?php foreach ($quickActions as $action): ?>
                        <a href="<?= e($action['url']) ?>" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors py-1"><?= $action['icon'] ?><?= e($action['label']) ?></a>
                    <?php endforeach; ?>
                </div>

                <!-- Chat CTA -->
                <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-300 mb-2">Ai nevoie de ajutor?</p>
                    <a href="#" class="block w-full py-2 bg-white text-gray-900 text-xs font-semibold rounded-lg hover:bg-gray-100 transition-colors">Deschide chat</a>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
// Vote system
function vote(type,btn){
    const btns=document.getElementById('voteButtons');
    const thanks=document.getElementById('voteThanks');
    const fbWrap=document.getElementById('feedbackWrap');
    const fbLabel=document.getElementById('feedbackLabel');

    // Ripple
    const ripple=document.createElement('span');ripple.className='ripple';
    const rect=btn.getBoundingClientRect();
    ripple.style.width=ripple.style.height=Math.max(rect.width,rect.height)+'px';
    ripple.style.left=0;ripple.style.top=0;btn.appendChild(ripple);
    setTimeout(()=>ripple.remove(),600);

    // Apply states
    document.querySelectorAll('.vote-btn').forEach(b=>b.classList.add('voted'));
    if(type==='yes'){
        btn.classList.add('voted-yes');
        thanks.innerHTML='<div class="vote-thanks mt-3"><span class="inline-flex items-center gap-1.5 text-sm text-green-600 font-medium"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Mulțumim pentru feedback!</span></div>';
        thanks.classList.remove('hidden');
    } else {
        btn.classList.add('voted-no');
        fbLabel.textContent='Ne pare rău. Ce informație ai fi avut nevoie?';
        fbWrap.classList.add('open');
        thanks.classList.add('hidden');
    }
}
function closeFeedback(){document.getElementById('feedbackWrap').classList.remove('open');const t=document.getElementById('voteThanks');t.innerHTML='<div class="vote-thanks mt-3"><span class="text-sm text-gray-500">Feedback anulat.</span></div>';t.classList.remove('hidden')}
function submitFeedback(){const v=document.getElementById('feedbackText').value;document.getElementById('feedbackWrap').classList.remove('open');const t=document.getElementById('voteThanks');t.innerHTML='<div class="vote-thanks mt-3"><span class="inline-flex items-center gap-1.5 text-sm text-indigo-600 font-medium"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>Feedback trimis! Mulțumim.</span></div>';t.classList.remove('hidden')}

// TOC active tracking
const tocLinks=document.querySelectorAll('.toc-link');
const sections=[<?= implode(',', array_map(fn($s) => "'" . $s['id'] . "'", $tocSections)) ?>].map(id=>document.getElementById(id)).filter(Boolean);
window.addEventListener('scroll',()=>{let cur='';sections.forEach(s=>{if(s.getBoundingClientRect().top<160)cur=s.id});tocLinks.forEach(l=>{l.classList.toggle('active',l.getAttribute('href')==='#'+cur)})});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
