<?php
/**
 * TICS.ro - FAQ Page
 * Frequently asked questions with search, category filter, and accordion
 */

require_once __DIR__ . '/includes/config.php';

// ============================================================================
// FAQ CATEGORIES
// ============================================================================

$faqCategories = [
    ['name' => 'Bilete',       'slug' => 'bilete',       'icon' => '🎫'],
    ['name' => 'Plăți',        'slug' => 'plati',        'icon' => '💳'],
    ['name' => 'Cont',         'slug' => 'cont',         'icon' => '👤'],
    ['name' => 'Organizatori', 'slug' => 'organizatori', 'icon' => '🏢'],
    ['name' => 'Tehnic',       'slug' => 'tehnic',       'icon' => '⚙️'],
];

// ============================================================================
// FAQ ITEMS
// ============================================================================

$faqItems = [
    // BILETE
    [
        'category' => 'bilete',
        'icon'     => '🎫',
        'iconBg'   => 'bg-indigo-50',
        'question' => 'Cum primesc biletul după achiziție?',
        'answer'   => 'Imediat după confirmarea plății, biletul este trimis automat pe adresa de email utilizată la achiziție. Biletul conține un cod QR unic care este scanat la intrarea în locație. Poți accesa biletele oricând și din secțiunea <strong>Biletele mele</strong> din contul tău, sau direct din aplicația mobilă TICS. Nu este nevoie să printezi biletul — prezintă doar codul QR de pe telefon.',
        'tags'     => 'Bilete · Cele mai citite',
    ],
    [
        'category' => 'bilete',
        'icon'     => '🔄',
        'iconBg'   => 'bg-indigo-50',
        'question' => 'Pot returna sau schimba un bilet?',
        'answer'   => 'Politica de retur depinde de organizatorul evenimentului. Unele evenimente permit returnarea biletelor cu până la 48 de ore înainte de eveniment, altele nu. Verifică termenii și condițiile din pagina evenimentului. Poți oricând <strong>transfera biletul</strong> altei persoane din secțiunea Biletele mele — gratuit și instant.',
        'tags'     => 'Bilete',
    ],
    [
        'category' => 'bilete',
        'icon'     => '📲',
        'iconBg'   => 'bg-indigo-50',
        'question' => 'Pot transfera biletul altcuiva?',
        'answer'   => 'Da! Din secțiunea <strong>Biletele mele</strong>, selectează biletul dorit și apasă „Transferă". Introdu adresa de email a persoanei care va primi biletul. Transferul este instantaneu, gratuit și generează un nou cod QR pe numele noului deținător. Biletul tău original este anulat automat.',
        'tags'     => 'Bilete',
    ],

    // PLATI
    [
        'category' => 'plati',
        'icon'     => '💳',
        'iconBg'   => 'bg-green-50',
        'question' => 'Ce metode de plată acceptați?',
        'answer'   => 'Acceptăm: <strong>Visa, Mastercard</strong> (debit și credit), <strong>Apple Pay, Google Pay</strong>, <strong>transfer bancar instant</strong> (via RoPay), și <strong>carduri de beneficii</strong> — Edenred, Pluxee (fostul Sodexo) și Up Romania. La checkout alegi metoda preferată, iar plata se procesează în câteva secunde.',
        'tags'     => 'Plăți · Popular',
    ],
    [
        'category' => 'plati',
        'icon'     => '🧾',
        'iconBg'   => 'bg-green-50',
        'question' => 'Primesc factură pentru achiziție?',
        'answer'   => 'Da, factura fiscală este generată automat și trimisă pe email în câteva minute de la confirmarea plății. O poți descărca oricând din contul tău, secțiunea <strong>Istoricul comenzilor</strong>. Dacă ai nevoie de factură pe numele companiei, completează datele de facturare în momentul achiziției.',
        'tags'     => 'Plăți',
    ],
    [
        'category' => 'plati',
        'icon'     => '⏳',
        'iconBg'   => 'bg-green-50',
        'question' => 'Plata nu a fost procesată. Ce fac?',
        'answer'   => 'Dacă plata nu a fost procesată, verifică mai întâi că ai suficiente fonduri pe card și că nu ai restricții de la bancă pentru plăți online. Încearcă din nou sau folosește o altă metodă de plată. Dacă suma a fost reținută dar nu ai primit biletul, contactează-ne — banii vor fi returnați automat în 3-5 zile lucrătoare dacă tranzacția nu a fost finalizată.',
        'tags'     => 'Plăți',
    ],

    // CONT
    [
        'category' => 'cont',
        'icon'     => '👤',
        'iconBg'   => 'bg-purple-50',
        'question' => 'Am nevoie de cont pentru a cumpăra bilete?',
        'answer'   => 'Nu este obligatoriu. Poți cumpăra bilete ca <strong>vizitator</strong> folosind doar adresa de email. Însă, cu un cont TICS beneficiezi de: bilete salvate într-un singur loc, istoric complet de comenzi, transferuri rapide, notificări pentru artiștii favoriți și acces la oferte exclusive.',
        'tags'     => 'Cont',
    ],
    [
        'category' => 'cont',
        'icon'     => '🔐',
        'iconBg'   => 'bg-purple-50',
        'question' => 'Mi-am uitat parola. Cum o resetez?',
        'answer'   => 'Accesează pagina de <strong>Autentificare</strong> și apasă pe „Am uitat parola". Introdu adresa de email asociată contului și vei primi un link de resetare în câteva secunde. Link-ul este valabil 1 oră. Dacă nu primești email-ul, verifică și folderul de Spam/Junk.',
        'tags'     => 'Cont',
    ],

    // ORGANIZATORI
    [
        'category' => 'organizatori',
        'icon'     => '🏢',
        'iconBg'   => 'bg-orange-50',
        'question' => 'Cum devin organizator pe TICS?',
        'answer'   => 'Completează formularul din pagina <strong>Parteneri</strong> sau scrie-ne direct la partners@tics.ro. Echipa noastră te va contacta în maxim 24 de ore. Procesul de onboarding durează de obicei 1-2 zile lucrătoare și include configurarea contului, integrarea cu procesatorul de plăți și accesul la dashboard.',
        'tags'     => 'Organizatori',
    ],
    [
        'category' => 'organizatori',
        'icon'     => '💰',
        'iconBg'   => 'bg-orange-50',
        'question' => 'Când primesc banii din vânzări?',
        'answer'   => 'Spre deosebire de alte platforme unde aștepți 30-60 de zile, pe TICS banii ajung <strong>direct în contul tău</strong>. Plățile sunt procesate prin contul tău de merchant, ceea ce înseamnă acces imediat la fonduri (maxim 24h lucrătoare). Comisionul TICS de 1% este facturat separat.',
        'tags'     => 'Organizatori · Popular',
    ],

    // TEHNIC
    [
        'category' => 'tehnic',
        'icon'     => '📱',
        'iconBg'   => 'bg-sky-50',
        'question' => 'Biletul funcționează offline?',
        'answer'   => 'Da, biletele din aplicația mobilă TICS sunt salvate local pe dispozitiv. Codul QR funcționează și fără conexiune la internet. Recomandăm totuși să deschizi aplicația înainte de eveniment pentru a sincroniza eventuale actualizări.',
        'tags'     => 'Tehnic',
    ],
    [
        'category' => 'tehnic',
        'icon'     => '🔗',
        'iconBg'   => 'bg-sky-50',
        'question' => 'Aveți API pentru integrări?',
        'answer'   => 'Da, TICS oferă un <strong>REST API complet</strong> documentat pentru integrări cu website-uri, aplicații mobile sau sisteme interne. API-ul permite listarea evenimentelor, vânzarea de bilete, verificarea statusului și gestionarea check-in-ului. Documentația este disponibilă la <strong>developers.tics.ro</strong>. Accesul la API este inclus în planurile Pro și Enterprise.',
        'tags'     => 'Tehnic',
    ],
];

// ============================================================================
// PAGE SETTINGS
// ============================================================================

$pageTitle = 'Întrebări frecvente';
$pageDescription = 'Găsește rapid răspunsuri la cele mai frecvente întrebări despre TICS.ro — bilete, plăți, cont, organizatori și tehnic.';
$bodyClass = 'bg-white';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'FAQ', 'url' => null],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-50 via-white to-white"></div>
        <div class="max-w-5xl mx-auto px-4 lg:px-8 py-14 lg:py-20 relative">
            <div class="text-center max-w-2xl mx-auto">
                <div class="anim inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-100 to-violet-100 rounded-2xl mb-6 shadow-sm"><span class="text-3xl">💬</span></div>
                <h1 class="anim anim-d1 text-3xl lg:text-4xl font-bold text-gray-900 mb-4">Cum te putem ajuta?</h1>
                <p class="anim anim-d2 text-gray-500 mb-8">Găsește rapid răspunsuri la cele mai frecvente întrebări despre TICS.ro</p>
                <!-- Search -->
                <div class="anim anim-d3 search-glow bg-white rounded-2xl border border-gray-200 flex items-center gap-3 px-5 py-4 max-w-lg mx-auto shadow-sm">
                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="faqSearch" placeholder="Caută o întrebare..." class="flex-1 bg-transparent text-sm outline-none placeholder:text-gray-400" oninput="filterFaq(this.value)">
                    <kbd class="hidden sm:inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-400 text-[10px] font-mono rounded">⌘K</kbd>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <div class="max-w-5xl mx-auto px-4 lg:px-8 -mt-2 mb-8">
        <div class="flex items-center justify-center gap-8 text-sm text-gray-400">
            <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 bg-green-500 rounded-full pulse-dot"></span>Echipă online acum</span>
            <span>Răspuns mediu: &lt; 2h</span>
            <span><?= count($faqItems) ?> articole</span>
        </div>
    </div>

    <!-- Categories -->
    <div class="max-w-5xl mx-auto px-4 lg:px-8 mb-8">
        <div class="flex items-center justify-center gap-1 flex-wrap" id="catBtns">
            <button class="cat-btn active px-4 py-2.5 text-sm text-gray-900 rounded-lg" onclick="filterCat('all',this)">Toate</button>
            <?php foreach ($faqCategories as $cat): ?>
            <button class="cat-btn px-4 py-2.5 text-sm text-gray-500 rounded-lg" onclick="filterCat('<?= e($cat['slug']) ?>',this)"><?= $cat['icon'] ?> <?= e($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- FAQ Content -->
    <main class="max-w-3xl mx-auto px-4 lg:px-8 pb-20">
        <div id="faqList" class="space-y-2">
            <?php foreach ($faqItems as $item): ?>
            <div class="faq-item border border-gray-100 p-5 cursor-pointer" data-cat="<?= e($item['category']) ?>" onclick="toggleFaq(this)">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 <?= e($item['iconBg']) ?> rounded-xl flex items-center justify-center text-lg flex-shrink-0"><?= $item['icon'] ?></div>
                    <div class="flex-1 min-w-0"><h3 class="font-medium text-gray-900 text-[15px]"><?= e($item['question']) ?></h3><p class="text-xs text-gray-400 mt-0.5"><?= e($item['tags']) ?></p></div>
                    <svg class="faq-chevron w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div class="faq-answer mt-0"><div><div class="pl-14 pt-4 pb-1 text-sm text-gray-600 leading-relaxed"><?= $item['answer'] ?></div></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- No results -->
        <div id="noResults" class="hidden text-center py-12">
            <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4">🔍</div>
            <p class="font-medium text-gray-900 mb-1">Nu am găsit nimic</p>
            <p class="text-sm text-gray-500">Încearcă alte cuvinte cheie sau <a href="/contact" class="text-indigo-600 hover:underline">contactează-ne direct</a>.</p>
        </div>

        <!-- Contact CTA -->
        <div class="mt-14 bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl p-8 lg:p-10 text-center overflow-hidden relative">
            <div class="absolute top-0 right-0 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="relative">
                <h3 class="text-xl font-bold text-white mb-2">Nu ai găsit răspunsul?</h3>
                <p class="text-gray-400 text-sm mb-6">Scrie-ne și îți răspundem în maxim 2 ore în zilele lucrătoare.</p>
                <div class="flex items-center justify-center gap-3 flex-wrap">
                    <a href="/contact" class="px-6 py-3 bg-white text-gray-900 text-sm font-semibold rounded-full hover:bg-gray-100 transition-colors flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>Trimite email</a>
                    <a href="/contact" class="px-6 py-3 border border-white/20 text-white text-sm font-semibold rounded-full hover:bg-white/10 transition-colors flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>Live chat</a>
                </div>
            </div>
        </div>
    </main>

    <script>
    function toggleFaq(el){const wasOpen=el.classList.contains('open');document.querySelectorAll('.faq-item.open').forEach(item=>{item.classList.remove('open');item.querySelector('.faq-answer').classList.remove('open')});if(!wasOpen){el.classList.add('open');el.querySelector('.faq-answer').classList.add('open')}}
    function filterCat(cat,btn){document.querySelectorAll('.cat-btn').forEach(b=>{b.classList.remove('active');b.classList.add('text-gray-500')});btn.classList.add('active');btn.classList.remove('text-gray-500');const items=document.querySelectorAll('.faq-item');let visible=0;items.forEach(item=>{if(cat==='all'||item.dataset.cat===cat){item.style.display='';visible++}else{item.style.display='none'}});document.getElementById('noResults').classList.toggle('hidden',visible>0)}
    function filterFaq(q){q=q.toLowerCase().trim();const items=document.querySelectorAll('.faq-item');let visible=0;document.querySelectorAll('.cat-btn').forEach(b=>{b.classList.remove('active');b.classList.add('text-gray-500')});document.querySelector('.cat-btn').classList.add('active');document.querySelector('.cat-btn').classList.remove('text-gray-500');items.forEach(item=>{const text=item.textContent.toLowerCase();if(!q||text.includes(q)){item.style.display='';visible++}else{item.style.display='none'}});document.getElementById('noResults').classList.toggle('hidden',visible>0)}
    document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key==='k'){e.preventDefault();document.getElementById('faqSearch').focus()}})
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
