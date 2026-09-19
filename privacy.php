<?php
/**
 * Privacy policy: /confidentialitate (v2 design, includes/v2/legal.php layout).
 *
 * What TIXELLO S.R.L. collects on bilete.online and why, who else gets it (the operators of the activities, the
 * processors), how long it stays, the visitor's rights. Written from what the platform does: customer accounts and
 * orders, tickets and check-in, points, referrals and gift cards, reviews, support, newsletter, the venue signup and
 * operator accounts (company data from ANAF), cookies and the tracking allowed in the consent banner. Cookies have
 * their own page (/cookies); this one links to it.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// static text: 30-minute page cache
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/legal.php';

$lgEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'contact@bilete.online';
$lgMail = '<a href="mailto:' . v2_e($lgEmail) . '">' . v2_e($lgEmail) . '</a>';

$lgSections = [
    ['cine-suntem', 'Cine suntem și ce acoperă această politică', <<<HTML
<p>Platforma bilete.online este operată de <strong>TIXELLO S.R.L.</strong>, cu datele de identificare de mai sus. Pentru datele prelucrate prin platformă (conturi, comenzi, bilete, programul de puncte, suport, înscrierea operatorilor), TIXELLO S.R.L. este <strong>operator de date</strong> în sensul Regulamentului (UE) 2016/679 (GDPR).</p>
<p>Activitățile listate pe bilete.online sunt oferite de operatorii lor (locații, organizatori, ghizi). Când cumperi un bilet, operatorul activității primește datele de care are nevoie ca să te primească și devine, pentru acele date, operator de date independent (vezi secțiunea „Cui transmitem datele”).</p>
<p>Pentru orice întrebare despre datele tale ne scrii la {$lgMail}, cu subiectul „Date personale”.</p>
HTML],
    ['ce-date', 'Ce date colectăm', <<<'HTML'
<ul>
  <li><strong>Contul de client:</strong> numele, adresa de email, telefonul, parola (păstrată doar sub formă criptată, ireversibil), preferințele de comunicare și, dacă o adaugi, data nașterii (pentru bonusul de ziua ta).</li>
  <li><strong>Comenzile:</strong> activitățile, data și ora rezervate, tipul și numărul biletelor, numele beneficiarilor (dacă le completezi), datele de facturare (nume sau firmă, CUI, adresă), sumele, metoda de plată și starea plății. Datele cardului le introduci direct la procesatorul de plăți: noi nu le vedem și nu le păstrăm. Pentru cardurile salvate primim de la procesator doar tipul cardului, ultimele cifre și data expirării.</li>
  <li><strong>Accesul la activitate:</strong> starea biletului și momentul în care a fost scanat la intrare.</li>
  <li><strong>Puncte, invitații și carduri cadou:</strong> soldul și istoricul punctelor, codul tău de invitație și conturile create prin el, soldul și folosirea cardurilor cadou.</li>
  <li><strong>Recenzii:</strong> nota, textul și fotografiile pe care le publici.</li>
  <li><strong>Suport:</strong> mesajele trimise prin formularul de contact, prin email sau prin tichetele din cont.</li>
  <li><strong>Newsletter:</strong> adresa de email, preferințele și data abonării.</li>
  <li><strong>Operatori (parteneri):</strong> datele din formularul de înscriere (nume, email, telefon, numele și orașul locației, site-ul, ce îți trebuie, mesajul tău), CUI-ul și datele firmei preluate din registrul public ANAF, datele contului de operator (reprezentant, date bancare, documentele încărcate, contractul) și paginile pentru parteneri vizitate înainte de înscriere (printr-un identificator de sesiune și parametrii campaniei din care ai venit).</li>
  <li><strong>Date tehnice:</strong> adresa IP, tipul de browser și de dispozitiv, paginile vizitate, jurnalele de securitate și de erori, cookies și tehnologii similare, descrise în <a href="/cookies">Politica de cookies</a>.</li>
</ul>
<p>Datele vin de la tine, din folosirea platformei, de la procesatorul de plăți (confirmarea plății), de la operatorul activității (scanarea biletului) și, pentru firme, din registrul public ANAF.</p>
HTML],
    ['scopuri', 'De ce folosim datele și pe ce temei', <<<'HTML'
<div class="lg-table" role="region" aria-label="Scopuri și temeiuri" tabindex="0">
<table>
  <thead><tr><th scope="col">Scop</th><th scope="col">Temei legal (art. 6 GDPR)</th></tr></thead>
  <tbody>
    <tr><td>Contul, autentificarea și setările lui</td><td>Executarea contractului (alin. 1 lit. b)</td></tr>
    <tr><td>Comenzile, plata, emiterea și trimiterea biletelor, accesul la activitate</td><td>Executarea contractului (lit. b)</td></tr>
    <tr><td>Documentele fiscale și evidența contabilă</td><td>Obligație legală (lit. c)</td></tr>
    <tr><td>Suport, reclamații, anulări și rambursări</td><td>Executarea contractului (lit. b) și obligație legală (lit. c)</td></tr>
    <tr><td>Programul de puncte, invitațiile și cardurile cadou</td><td>Executarea contractului (lit. b)</td></tr>
    <tr><td>Newsletter și mesaje comerciale</td><td>Consimțământ (lit. a), pe care îl retragi oricând</td></tr>
    <tr><td>Recomandări personalizate pe site</td><td>Consimțământ pentru cookies de personalizare (lit. a)</td></tr>
    <tr><td>Analiză (Google Analytics) și campanii (pixeli Meta, Google Ads, TikTok)</td><td>Consimțământ pentru cookies de analiză și marketing (lit. a)</td></tr>
    <tr><td>Securitate, prevenirea fraudei și a abuzurilor, măsurarea agregată a audienței, îmbunătățirea platformei</td><td>Interes legitim (lit. f)</td></tr>
    <tr><td>Înscrierea operatorilor, contul de operator și contractul de parteneriat</td><td>Demersuri înainte de încheierea contractului și executarea lui (lit. b)</td></tr>
    <tr><td>Verificarea firmei în registrul public ANAF</td><td>Interes legitim (lit. f): lucrăm doar cu firme reale și active</td></tr>
    <tr><td>Constatarea, exercitarea sau apărarea unor drepturi în instanță</td><td>Interes legitim (lit. f)</td></tr>
  </tbody>
</table>
</div>
<p>Nu luăm decizii bazate exclusiv pe prelucrare automată care să producă efecte juridice asupra ta. Recomandările de activități sunt simple sugestii.</p>
HTML],
    ['destinatari', 'Cui transmitem datele', <<<'HTML'
<p><strong>Nu vindem datele tale.</strong> Le transmitem doar cât e nevoie, astfel:</p>
<ul>
  <li><strong>Operatorul activității</strong> pentru care ai cumpărat: numele tău și al beneficiarilor, datele de contact din comandă, biletele, data și ora rezervării și starea de check-in. Le folosește pentru organizarea activității și pentru obligațiile lui legale.</li>
  <li><strong>Procesatorul de plăți</strong> (în prezent Stripe) și, pentru cardurile culturale, emitentul cardului, pentru încasarea și securitatea plății.</li>
  <li><strong>Furnizori de infrastructură:</strong> găzduire și servere, Cloudflare (livrarea paginilor și protecție împotriva atacurilor), furnizori de email tranzacțional (de exemplu Brevo), servicii de hărți (CARTO și OpenStreetMap primesc adresa IP când se încarcă o hartă).</li>
  <li><strong>Analiză și publicitate</strong> (Google, Meta, TikTok), doar dacă ai acceptat cookies de analiză sau de marketing. Dacă operatorul activității folosește Meta Conversions API, confirmarea unei cumpărări poate ajunge la Meta cu datele de contact criptate ireversibil (hash), pentru măsurarea campaniilor lui.</li>
  <li><strong>GetYourGuide</strong>, partener ale cărui oferte apar pe unele pagini: când intri pe o ofertă GetYourGuide, se aplică politica lor de confidențialitate.</li>
  <li><strong>Consultanți</strong> (contabilitate, juridic), obligați la confidențialitate.</li>
  <li><strong>Autorități</strong> (de exemplu ANAF, instanțe, organe de cercetare), atunci când legea ne obligă.</li>
</ul>
<p>Furnizorii care prelucrează date în numele nostru o fac pe bază de contract, doar după instrucțiunile noastre și cu măsuri de securitate adecvate.</p>
HTML],
    ['transferuri', 'Transferuri în afara Spațiului Economic European', <<<'HTML'
<p>Unii furnizori (de exemplu Stripe, Google, Meta, Cloudflare) pot prelucra date și în afara Spațiului Economic European, inclusiv în Statele Unite. Transferurile se fac doar cu garanțiile cerute de GDPR: decizia de adecvare a Comisiei Europene (EU-U.S. Data Privacy Framework, pentru companiile certificate) sau clauze contractuale standard aprobate de Comisie.</p>
HTML],
    ['pastrare', 'Cât timp păstrăm datele', <<<'HTML'
<ul>
  <li><strong>Contul de client:</strong> cât timp este activ. Dacă îl ștergi, datele de profil se șterg sau se anonimizează, iar comenzile rămân în evidența contabilă.</li>
  <li><strong>Comenzi, documente fiscale și contabile:</strong> până la 10 ani, cât cere legislația financiar-contabilă.</li>
  <li><strong>Biletele și scanările:</strong> cât timp păstrăm comanda de care țin.</li>
  <li><strong>Suport și reclamații:</strong> până la 3 ani de la închiderea solicitării.</li>
  <li><strong>Newsletter:</strong> până te dezabonezi (linkul e în fiecare email).</li>
  <li><strong>Puncte:</strong> până la expirare, după regulile programului.</li>
  <li><strong>Înscrierile operatorilor fără cont activ:</strong> până la 2 ani de la ultima interacțiune.</li>
  <li><strong>Jurnale tehnice și de securitate:</strong> până la 12 luni.</li>
  <li><strong>Cookies:</strong> conform <a href="/cookies">Politicii de cookies</a>.</li>
</ul>
<p>La final, datele se șterg sau se anonimizează, cu excepția celor pe care legea ne obligă să le păstrăm mai mult sau a celor necesare într-un litigiu în curs.</p>
HTML],
    ['drepturi', 'Drepturile tale', <<<HTML
<p>Conform GDPR, ai dreptul:</p>
<ul>
  <li>să afli ce date avem despre tine și să primești o copie a lor (acces);</li>
  <li>să corectezi datele greșite sau incomplete (rectificare);</li>
  <li>să ceri ștergerea lor, când nu mai există un motiv legal să le păstrăm (ștergere);</li>
  <li>să ceri restricționarea prelucrării, în cazurile prevăzute de lege;</li>
  <li>să primești datele într-un format structurat și să le transmiți altui operator (portabilitate);</li>
  <li>să te opui prelucrării bazate pe interes legitim și, oricând, marketingului direct;</li>
  <li>să îți retragi consimțământul, fără să afecteze prelucrarea făcută până atunci;</li>
  <li>să depui plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (ANSPDCP), B-dul G-ral. Gheorghe Magheru nr. 28-30, sector 1, București, <a href="https://www.dataprotection.ro" rel="noopener" target="_blank">www.dataprotection.ro</a>.</li>
</ul>
<p>Multe le faci direct din cont: la <strong>Setări</strong> îți modifici datele, îți descarci datele și îți ștergi contul. Pentru rest, scrie-ne la {$lgMail}. Îți răspundem în cel mult o lună; pentru cereri complexe termenul se poate prelungi cu încă două luni, iar atunci te anunțăm. Putem cere informații suplimentare ca să confirmăm că cererea vine de la tine.</p>
HTML],
    ['securitate', 'Cum protejăm datele', <<<'HTML'
<p>Folosim conexiuni criptate (HTTPS), parole păstrate doar criptat, acces la date pe roluri, autentificare în doi pași (opțională, din Setări), plăți prin procesatori certificați PCI DSS, copii de siguranță și monitorizarea accesului. Dacă totuși are loc un incident care îți poate afecta datele, anunțăm ANSPDCP în 72 de ore și te anunțăm și pe tine când riscul este ridicat.</p>
<p>Și tu ne ajuți: păstrează parola doar pentru tine și nu publica biletele sau codurile QR, pentru că oricine le are poate intra în locul tău.</p>
HTML],
    ['minori', 'Minorii', <<<'HTML'
<p>Contul de client se poate crea de la 16 ani. Pentru copiii mai mici, biletele le cumpără un părinte sau tutore, care completează doar datele necesare despre copil (de exemplu numele pe bilet sau categoria de vârstă).</p>
HTML],
    ['cookies', 'Cookies și tehnologii similare', <<<'HTML'
<p>Folosim cookies necesare pentru funcționarea platformei și, doar cu acordul tău, cookies de analiză, personalizare și marketing. Detaliile și setările sunt în <a href="/cookies">Politica de cookies</a>. Îți schimbi alegerea oricând, din linkul <strong>Cookies</strong> din subsolul oricărei pagini.</p>
HTML],
    ['modificari', 'Modificări ale acestei politici', <<<'HTML'
<p>Actualizăm politica atunci când se schimbă ce facem cu datele sau legea. Data ultimei actualizări apare la începutul paginii. Despre schimbările importante te anunțăm pe email sau printr-un mesaj pe platformă.</p>
HTML],
];

$pageTitleRaw = 'Politica de confidențialitate — ' . SITE_NAME;
$pageDescription = 'Ce date colectează bilete.online, de ce, cui le transmite, cât timp le păstrează și cum îți exerciți drepturile. Operator: TIXELLO S.R.L.';
$canonicalUrl = SITE_URL . '/confidentialitate';

$v2Styles = ['legal.css'];
$v2Scripts = ['legal.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';

v2_legal_render([
    'key' => 'confidentialitate',
    'kicker' => 'Politică de confidențialitate',
    'title' => 'Cum avem grijă de datele tale',
    'lead' => 'Pe scurt: folosim datele doar ca să-ți funcționeze contul, comenzile și biletele, nu le vindem, iar analiza și publicitatea pornesc doar cu acordul tău. Mai jos găsești toate detaliile.',
    'sections' => $lgSections,
]);

include __DIR__ . '/includes/v2/footer.php';
