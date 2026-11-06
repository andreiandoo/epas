<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/api.php';
$pageTitle = 'Ajutor & Suport';
$currentPage = 'help';
$cssBundle = 'account';

$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'contact@ambilet.ro';
$supportPhone = defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '+40 726 13 15 16';
$siteName = defined('SITE_NAME') ? SITE_NAME : 'AmBilet';

// Întrebările din pagină, grupate pe aceleași categorii ca și cardurile de sus
$faqGroups = [
    'bilete' => [
        'title' => 'Bilete',
        'items' => [
            ['Cum îmi descarc biletele?', 'Biletele sunt în secțiunea <a href="/cont/bilete" class="text-primary">Biletele mele</a>. Deschide biletul dorit și descarcă-l în format PDF. Îți trimitem biletele și pe email, imediat după ce plata este confirmată.'],
            ['Nu am primit biletele pe email. Ce fac?', 'Verifică folderele Spam și Promoții. Biletele sunt oricum disponibile în <a href="/cont/bilete" class="text-primary">Biletele mele</a>, cât timp ești autentificat cu adresa de email folosită la comandă. Dacă nu le găsești nici acolo, scrie-ne la ' . htmlspecialchars($supportEmail) . ' cu numărul comenzii.'],
            ['Trebuie să printez biletul?', 'Nu este obligatoriu. La intrare poți arăta codul QR de pe telefon sau biletul printat. Asigură-te că telefonul este încărcat și că luminozitatea ecranului e suficientă pentru scanare.'],
            ['Cum funcționează accesul la eveniment?', 'La intrarea în locație, prezintă codul QR de pe bilet. Personalul îl scanează pentru validare. Fiecare cod poate fi folosit o singură dată, așa că nu distribui biletul altor persoane.'],
            ['Pot transfera un bilet altcuiva?', 'Transferul de bilete din cont nu este încă disponibil. Până atunci, dacă ai nevoie de ajutor cu un bilet, scrie-ne la ' . htmlspecialchars($supportEmail) . '.'],
        ],
    ],
    'plati' => [
        'title' => 'Plăți și rambursări',
        'items' => [
            ['Pot solicita o rambursare?', 'Biletele sunt nereturnabile, conform OUG nr. 34/2014, art. 16 lit. l), iar dacă renunți la participare, din orice motiv, contravaloarea biletului nu se rambursează. Excepție face anularea evenimentului de către organizator: rambursarea se face conform condițiilor comunicate de organizator. Taxa de procesare/ticketing nu se rambursează, nici în caz de anulare. <a href="/politica-retur" class="text-primary">Vezi politica completă</a>.'],
            ['Ce se întâmplă dacă evenimentul este anulat sau amânat?', 'Te anunțăm pe email. Pentru un eveniment anulat, rambursarea se face conform condițiilor comunicate de organizator. Dacă evenimentul este amânat, biletele rămân de obicei valabile pentru noua dată. Detaliile comenzii le găsești în <a href="/cont/comenzi" class="text-primary">Comenzile mele</a>.'],
            ['Unde găsesc factura?', 'Documentele comenzilor sunt în secțiunea <a href="/cont/facturi" class="text-primary">Facturi</a> din cont și în pagina fiecărei comenzi.'],
            ['Plata a eșuat, dar banii au fost blocați. Ce fac?', 'Dacă plata nu s-a finalizat, suma blocată este eliberată automat de banca ta, de regulă în câteva zile lucrătoare. Nu se emit bilete pentru o plată eșuată. Dacă banii au fost retrași și nu vezi comanda în cont, scrie-ne la ' . htmlspecialchars($supportEmail) . '.'],
        ],
    ],
    'cont' => [
        'title' => 'Cont',
        'items' => [
            ['Ce fac dacă am uitat parola?', 'Pe pagina de autentificare apasă „Am uitat parola” și introdu adresa de email. Vei primi un link pentru resetarea parolei, valabil 60 de minute.'],
            ['Cum îmi schimb datele contului?', 'Datele personale, parola și preferințele de notificare le schimbi din <a href="/cont/setari" class="text-primary">Setări</a>.'],
            ['Cum mă dezabonez de la newsletter?', 'Din <a href="/cont/setari" class="text-primary">Setări</a> poți opri emailurile de marketing. Emailurile despre comenzile tale (bilete, confirmări) le primești în continuare.'],
            ['Cum îmi șterg contul?', 'Poți cere ștergerea contului din <a href="/cont/setari" class="text-primary">Setări</a>. Documentele fiscale ale comenzilor se păstrează atât cât cere legea.'],
        ],
    ],
    'puncte' => [
        'title' => 'Puncte și recompense',
        'items' => [
            ['Cum funcționează punctele?', 'Primești puncte pentru comenzile plătite și pentru unele activități din cont. Câte puncte primești pe fiecare leu și cât valorează un punct vezi pe pagina <a href="/cont/puncte" class="text-primary">Puncte</a>.'],
            ['Ce este XP-ul și cum avansez în nivel?', 'XP-ul măsoară cât de activ ești: comenzi, participări la evenimente, recenzii, badge-uri. Fiecare nivel are nevoie de un anumit număr de XP. Când îl atingi, treci automat la nivelul următor, iar XP-ul în plus se adună la noul nivel.'],
            ['Ce sunt badge-urile?', 'Badge-urile sunt realizări pe care le deblochezi prin activitatea ta, de exemplu primul eveniment sau mai multe evenimente dintr-un gen. Fiecare badge aduce și XP. Le vezi pe pagina <a href="/cont/puncte" class="text-primary">Puncte</a>, la Badge-uri.'],
            ['Cum invit prieteni?', 'Din pagina <a href="/cont/invitatii" class="text-primary">Invitații</a> îți copiezi linkul personal și îl trimiți prietenilor.'],
        ],
    ],
];

// Articole din centrul de ajutor; dacă API-ul nu răspunde, rămân cele de bază
$popularArticles = [];
try {
    $resp = api_get('/kb/articles/popular', ['limit' => 4]);
    foreach (($resp['data']['articles'] ?? []) as $a) {
        if (empty($a['slug'])) continue;
        $popularArticles[] = [
            'url' => '/ajutor/articol/' . $a['slug'],
            'title' => ($a['type'] ?? '') === 'faq' ? ($a['question'] ?? '') : ($a['title'] ?? ''),
            'sub' => $a['category']['name'] ?? 'Centrul de ajutor',
        ];
    }
} catch (\Throwable $e) {
    $popularArticles = [];
}
if (!$popularArticles) {
    $popularArticles = [
        ['url' => '/ajutor/articol/bilete-electronice', 'title' => 'Cum funcționează biletele electronice', 'sub' => 'Bilete'],
        ['url' => '/ajutor/articol/politica-rambursare', 'title' => 'Politica de returnare și rambursare', 'sub' => 'Rambursări'],
        ['url' => '/ajutor/articol/metode-plata', 'title' => 'Metode de plată acceptate', 'sub' => 'Plăți'],
        ['url' => '/ajutor/articol/gestionare-cont', 'title' => 'Gestionarea contului', 'sub' => 'Cont și profil'],
    ];
}

require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .faq-answer { display: none; }
    .faq-item.active .faq-answer { display: block; }
    .faq-icon { transition: transform .2s ease; }
    .faq-item.active .faq-icon { transform: rotate(180deg); }
    .faq-group { scroll-margin-top: 96px; }
    .faq-group.flash { box-shadow: 0 0 0 2px rgba(165, 28, 48, .35); }
    .help-card { transition: border-color .2s ease, transform .2s ease; }
    .help-card:hover { transform: translateY(-2px); }
</style>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        <!-- Search Header -->
        <div class="relative p-6 mb-8 overflow-hidden text-center text-white bg-gradient-to-r from-primary via-primary-dark to-secondary rounded-2xl lg:p-10">
            <div class="absolute top-0 right-0 w-64 h-64 translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5"></div>
            <div class="relative">
                <h1 class="mb-2 text-2xl font-bold lg:text-3xl">Cum te putem ajuta?</h1>
                <p class="mb-6 text-white/90">Caută răspunsuri sau contactează echipa de suport</p>
                <div class="relative max-w-lg mx-auto">
                    <input type="text" id="search-input" placeholder="Caută un subiect..." class="w-full py-4 pl-12 pr-4 text-white border bg-white/10 border-white/20 rounded-xl placeholder-white/50 focus:outline-none focus:bg-white/20 focus:border-white/40">
                    <svg class="absolute w-5 h-5 -translate-y-1/2 text-white/90 left-4 top-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        </div>

        <!-- Quick Help Cards: sar la grupul de întrebări -->
        <div class="grid gap-4 mb-8 sm:grid-cols-2 lg:grid-cols-4">
            <a href="#bilete" data-faq-jump="bilete" class="p-5 text-center bg-white border help-card rounded-xl border-border hover:border-primary">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-primary/10 rounded-xl">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <h3 class="mb-1 font-semibold text-secondary">Bilete</h3>
                <p class="text-xs text-muted">Descărcare, acces la eveniment</p>
            </a>
            <a href="#plati" data-faq-jump="plati" class="p-5 text-center bg-white border help-card rounded-xl border-border hover:border-primary">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-success/10 rounded-xl">
                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <h3 class="mb-1 font-semibold text-secondary">Plăți</h3>
                <p class="text-xs text-muted">Facturi, rambursări, erori</p>
            </a>
            <a href="#cont" data-faq-jump="cont" class="p-5 text-center bg-white border help-card rounded-xl border-border hover:border-primary">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-accent/10 rounded-xl">
                    <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 class="mb-1 font-semibold text-secondary">Cont</h3>
                <p class="text-xs text-muted">Profil, parolă, date</p>
            </a>
            <a href="#puncte" data-faq-jump="puncte" class="p-5 text-center bg-white border help-card rounded-xl border-border hover:border-primary">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-blue-500/10 rounded-xl">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="mb-1 font-semibold text-secondary">Puncte</h3>
                <p class="text-xs text-muted">Recompense, niveluri, badge-uri</p>
            </a>
        </div>

        <!-- FAQ Section -->
        <div class="p-5 mb-8 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
            <h2 class="mb-6 text-lg font-bold text-secondary">Întrebări frecvente</h2>

            <div class="space-y-8" id="faq-list">
                <?php foreach ($faqGroups as $groupId => $group): ?>
                <div class="transition-shadow faq-group rounded-xl" id="<?= $groupId ?>">
                    <h3 class="mb-3 text-sm font-semibold tracking-wide uppercase text-muted"><?= htmlspecialchars($group['title']) ?></h3>
                    <div class="space-y-3">
                        <?php foreach ($group['items'] as $item): ?>
                        <div class="overflow-hidden border faq-item border-border rounded-xl">
                            <button type="button" onclick="toggleFaq(this)" aria-expanded="false" class="flex items-center justify-between w-full gap-4 p-4 text-left">
                                <span class="font-medium text-secondary"><?= htmlspecialchars($item[0]) ?></span>
                                <svg class="flex-shrink-0 w-5 h-5 faq-icon text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div class="px-4 pb-4 faq-answer">
                                <p class="text-sm text-muted"><?= $item[1] ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <p id="faq-empty" class="hidden text-sm text-muted">Nu am găsit nimic pentru căutarea ta. Încearcă <a href="/ajutor" class="text-primary">centrul de ajutor</a> sau scrie-ne.</p>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="grid gap-4 mb-8 md:grid-cols-3">
            <div class="p-5 text-center bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center mx-auto mb-4 w-14 h-14 bg-primary/10 rounded-xl">
                    <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <h3 class="mb-1 font-bold text-secondary">Chat live</h3>
                <p class="mb-3 text-sm text-muted">Scrie-ne direct din pagină</p>
                <button type="button" onclick="openChat()" class="w-full btn btn-primary bg-primary py-2.5 text-white font-semibold rounded-xl text-sm">
                    Începe conversația
                </button>
            </div>

            <div class="p-5 text-center bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center mx-auto mb-4 w-14 h-14 bg-success/10 rounded-xl">
                    <svg class="w-7 h-7 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="mb-1 font-bold text-secondary">Email</h3>
                <p class="mb-3 text-sm text-muted"><?= htmlspecialchars($supportEmail) ?></p>
                <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" class="block w-full py-2.5 bg-surface text-secondary font-semibold rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                    Trimite email
                </a>
            </div>

            <div class="p-5 text-center bg-white border rounded-xl border-border">
                <div class="flex items-center justify-center mx-auto mb-4 w-14 h-14 bg-accent/10 rounded-xl">
                    <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <h3 class="mb-1 font-bold text-secondary">Telefon</h3>
                <p class="mb-3 text-sm text-muted"><?= htmlspecialchars($supportPhone) ?></p>
                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>" class="block w-full py-2.5 bg-surface text-secondary font-semibold rounded-xl text-sm hover:bg-primary/10 hover:text-primary transition-colors">
                    Sună acum
                </a>
            </div>
        </div>

        <!-- Help Articles -->
        <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <h2 class="text-lg font-bold text-secondary">Articole populare</h2>
                <a href="/ajutor" class="text-sm font-medium text-primary">Centrul de ajutor →</a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach ($popularArticles as $article): ?>
                <a href="<?= htmlspecialchars($article['url']) ?>" class="flex items-center gap-3 p-4 transition-colors bg-surface rounded-xl hover:bg-primary/5">
                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-lg bg-primary/10">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-secondary"><?= htmlspecialchars($article['title']) ?></p>
                        <p class="text-xs text-muted"><?= htmlspecialchars($article['sub']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Support Hours -->
        <div class="mt-8 text-center">
            <p class="text-sm text-muted">Program suport: Luni – Vineri 09:00 – 20:00 · Sâmbătă 10:00 – 16:00</p>
        </div>
<?php
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php';
?>

<?php
$scriptsExtra = <<<'JS'
<script>
function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    const open = item.classList.toggle('active');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}

// Chatul live se încarcă și pe pagina asta; dacă nu e activ, trimitem la Contact
function openChat() {
    const bubble = document.querySelector('.amb-chat-bubble');
    const panel = document.querySelector('.amb-chat-panel');
    if (bubble) {
        if (!panel || !panel.classList.contains('open')) bubble.click();
        return;
    }
    window.location.href = '/contact';
}

function jumpToGroup(id) {
    const group = document.getElementById(id);
    if (!group) return;
    const search = document.getElementById('search-input');
    if (search && search.value) { search.value = ''; filterFaq(''); }
    group.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const first = group.querySelector('.faq-item');
    if (first && !first.classList.contains('active')) toggleFaq(first.querySelector('button'));
    group.classList.add('flash');
    setTimeout(() => group.classList.remove('flash'), 1200);
}

function filterFaq(query) {
    const q = query.trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('.faq-group').forEach(group => {
        let groupShown = 0;
        group.querySelectorAll('.faq-item').forEach(item => {
            const match = !q || item.textContent.toLowerCase().includes(q);
            item.style.display = match ? '' : 'none';
            if (match) groupShown++;
        });
        group.style.display = groupShown ? '' : 'none';
        shown += groupShown;
    });
    document.getElementById('faq-empty').classList.toggle('hidden', shown > 0);
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isAuthenticated()) {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            }
            const headerPoints = document.getElementById('header-user-points');
            if (headerPoints) {
                headerPoints.textContent = (user.points || 0).toLocaleString();
            }
        }
    }

    document.querySelectorAll('[data-faq-jump]').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            history.replaceState(null, '', '#' + card.dataset.faqJump);
            jumpToGroup(card.dataset.faqJump);
        });
    });

    const hash = location.hash.replace('#', '');
    if (hash && document.getElementById(hash) && document.getElementById(hash).classList.contains('faq-group')) {
        jumpToGroup(hash);
    }

    document.getElementById('search-input').addEventListener('input', (e) => filterFaq(e.target.value));
});
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
