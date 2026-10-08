<?php
/**
 * Pagină legală comună. Setează $legalDoc = 'termeni' | 'confidentialitate' | 'cookies' înainte de include.
 * Dacă tenantul a publicat în panou propriile pagini de termeni / confidențialitate, se afișează acelea;
 * altfel rămâne textul-cadru de mai jos, marcat ca provizoriu.
 */
require_once __DIR__ . '/boot.php';

$docs = [
    'termeni'           => ['title' => 'Termeni și condiții', 'api' => '/tenant-client/pages/terms'],
    'confidentialitate' => ['title' => 'Politica de confidențialitate', 'api' => '/tenant-client/pages/privacy'],
    'cookies'           => ['title' => 'Politica de cookies', 'api' => null],
];
$legalDoc = isset($docs[$legalDoc ?? '']) ? $legalDoc : 'termeni';
$doc = $docs[$legalDoc];

// Conținutul publicat de tenant, dacă există
$custom = '';
if ($doc['api']) {
    $resp = api_get($doc['api'], ['locale' => 'ro'], 600);
    $d = $resp['data'] ?? null;
    if (($resp['success'] ?? false) && is_array($d)) {
        $custom = trim((string) ($d['content'] ?? ($d['body'] ?? ($d['html'] ?? ''))));
    }
}

$pageTitle = $doc['title'] . ' — ' . SITE_SHORT;
$bodyClass = 'is-light';
include __DIR__ . '/head.php';
?>
<main class="is-light">
    <section class="phead phead--slim">
        <div class="wrap">
            <span class="label">Informații legale</span>
            <h1><?= e($doc['title']) ?></h1>
        </div>
    </section>
    <div class="wrap">
        <article class="legal">
        <?php if ($custom !== ''): ?>
            <?= $custom /* HTML redactat de organizator în panou */ ?>
        <?php else: ?>
            <div class="note">Text-cadru pentru versiunea demonstrativă a site-ului. Forma finală o stabilește <?= e(SITE_NAME) ?>, ca organizator și vânzător al biletelor.</div>

            <?php if ($legalDoc === 'termeni'): ?>
                <h2>Cine vinde biletele</h2>
                <p>Biletele puse în vânzare pe acest site sunt emise de <?= e(SITE_NAME) ?>, organizatorul competițiilor. Plata se încasează de organizator prin procesatorul său de plăți, iar platforma tehnică de ticketing este furnizată de Tixello.</p>
                <h2>Comanda și plata</h2>
                <ul>
                    <li>Prețurile sunt afișate în lei și includ toate taxele, cu excepția cazului în care o taxă de procesare a plății apare distinct în coș, înainte de plată.</li>
                    <li>Biletele alese rămân rezervate un timp limitat, afișat în coș. După expirare, ele revin în vânzare.</li>
                    <li>Comanda este confirmată doar după acceptarea plății. Biletele sunt trimise la adresa de email introdusă la comandă.</li>
                </ul>
                <h2>Biletul și accesul</h2>
                <ul>
                    <li>Fiecare bilet are un cod QR unic, valabil pentru o singură intrare.</li>
                    <li>La competițiile cu locuri numerotate, biletul este valabil doar pentru locul înscris pe el.</li>
                    <li>Accesul se face conform regulamentului sălii și al competiției.</li>
                </ul>
                <h2>Anulări și rambursări</h2>
                <p>Dacă o competiție este anulată sau reprogramată, organizatorul anunță cumpărătorii pe email și comunică modul de rambursare sau de păstrare a biletelor.</p>
                <h2>Reclamații</h2>
                <p>Pentru orice nemulțumire legată de o comandă, scrie organizatorului. Dacă nu se ajunge la o soluție, te poți adresa <a href="https://anpc.ro/" target="_blank" rel="nofollow noopener">ANPC</a> sau poți folosi platformele de <a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener">soluționare alternativă</a> și <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener">soluționare online</a> a litigiilor.</p>

            <?php elseif ($legalDoc === 'confidentialitate'): ?>
                <h2>Ce date colectăm</h2>
                <ul>
                    <li>La comandă: nume, prenume, adresa de email și, opțional, numărul de telefon.</li>
                    <li>La crearea unui cont: aceleași date, plus parola, păstrată doar în formă criptată.</li>
                    <li>Datele cardului nu ajung la noi: sunt introduse direct în pagina procesatorului de plăți.</li>
                </ul>
                <h2>De ce le folosim</h2>
                <ul>
                    <li>Pentru emiterea și trimiterea biletelor și pentru evidența comenzilor.</li>
                    <li>Pentru anunțuri despre competiția la care ai bilet (schimbări de program, anulări).</li>
                    <li>Pentru noutăți despre alte competiții, doar dacă ai bifat această opțiune la comandă.</li>
                </ul>
                <h2>Cine are acces</h2>
                <p>Operatorul datelor este <?= e(SITE_NAME) ?>. Tixello prelucrează datele ca furnizor al platformei de ticketing, în numele organizatorului, iar procesatorul de plăți prelucrează datele necesare tranzacției.</p>
                <h2>Drepturile tale</h2>
                <p>Poți cere oricând accesul la datele tale, corectarea sau ștergerea lor și te poți opune prelucrării în scop de marketing. Cererile se adresează organizatorului.</p>

            <?php else: ?>
                <h2>Ce stocăm în browserul tău</h2>
                <ul>
                    <li><b>Coșul de cumpărături</b>: biletele alese și timpul de rezervare, păstrate local în browser până la finalizarea comenzii.</li>
                    <li><b>Sesiunea de cont</b>: dacă te autentifici, un identificator care te ține conectat pe acest dispozitiv.</li>
                    <li><b>Rezervarea locurilor</b>: la competițiile cu locuri numerotate, un cookie tehnic care leagă locurile blocate de vizita ta.</li>
                </ul>
                <h2>Ce nu folosim</h2>
                <p>În această versiune a site-ului nu rulează instrumente de publicitate sau de urmărire. Dacă organizatorul le va activa, ele vor porni doar cu acordul tău, cerut la prima vizită.</p>
                <h2>Cum le ștergi</h2>
                <p>Poți șterge oricând datele stocate din setările browserului. Coșul și sesiunea de cont se pierd, dar biletele deja cumpărate rămân valabile și le găsești în emailul de confirmare.</p>
            <?php endif; ?>
        <?php endif; ?>
        </article>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
