<?php
/**
 * Terms and conditions: /termeni (v2 design, includes/v2/legal.php layout).
 *
 * The rules of bilete.online as the platform works today: TIXELLO S.R.L. sells tickets on behalf of the operators of
 * the activities (the operator is responsible for the activity itself), the order and payment (the ticketing fee and
 * any other cost shown before paying, Stripe, cultural cards with their surcharge), electronic tickets with a QR
 * code, cancellation and refunds (no 14-day withdrawal for dated leisure services, OUG 34/2014 art. 16 lit. l; full
 * refund including the fee when the operator cancels, as on Ambilet), gift cards (12 months), the points programme,
 * reviews, what isn't allowed, the operators' side (signup creates a pending account, nothing public before approval),
 * liability, complaints (ANPC, SAL) and the applicable law.
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
    ['despre', 'Despre bilete.online și acești termeni', <<<'HTML'
<p>bilete.online este o platformă prin care descoperi și cumperi bilete la activități, atracții și experiențe din România. Platforma este operată de <strong>TIXELLO S.R.L.</strong> (datele complete sunt mai sus), numită în continuare „noi”.</p>
<p>Acești termeni se aplică oricui folosește site-ul: vizitatorilor, clienților și operatorilor care își listează activitățile. Când îți creezi un cont sau plasezi o comandă, confirmi că i-ai citit și îi accepți. Datele personale le prelucrăm conform <a href="/confidentialitate">Politicii de confidențialitate</a>.</p>
HTML],
    ['definitii', 'Ce înseamnă termenii folosiți', <<<'HTML'
<ul>
  <li><strong>Platforma:</strong> site-ul bilete.online și serviciile legate de el (contul, emailurile, biletele electronice).</li>
  <li><strong>Client:</strong> persoana care cumpără bilete prin platformă.</li>
  <li><strong>Operator:</strong> locația, organizatorul sau ghidul care oferă activitatea și care răspunde de desfășurarea ei.</li>
  <li><strong>Activitate:</strong> atracția, experiența, turul, atelierul sau orice alt serviciu de agrement listat pe platformă.</li>
  <li><strong>Bilet:</strong> dovada electronică a dreptului de acces la activitate, cu un cod QR unic.</li>
  <li><strong>Comandă:</strong> cumpărarea unuia sau mai multor bilete, într-o singură plată.</li>
</ul>
HTML],
    ['rolul-nostru', 'Rolul nostru și al operatorilor', <<<'HTML'
<p>Vindem biletele <strong>în numele și pe seama operatorilor</strong>. Contractul pentru activitate se încheie între tine și operator, care răspunde de desfășurarea ei, de siguranță, de calitate, de autorizațiile necesare și de informațiile despre activitate (program, durată, vârstă minimă, reguli de acces, accesibilitate, ce include biletul).</p>
<p>Noi răspundem de funcționarea platformei, de procesarea comenzii, de încasarea plății în numele operatorului și de emiterea și trimiterea biletelor. Verificăm operatorii înainte ca activitățile lor să devină publice, dar nu putem garanta desfășurarea fiecărei activități.</p>
HTML],
    ['cont', 'Contul tău', <<<'HTML'
<ul>
  <li>Contul este gratuit și se poate crea de la 16 ani. Poți cumpăra și fără cont, cu adresa de email.</li>
  <li>Datele din cont trebuie să fie reale și actuale. Parola e personală: nu o împărți cu nimeni. Pentru mai multă siguranță, activează autentificarea în doi pași din Setări.</li>
  <li>Dacă observi acces neautorizat în cont, schimbă parola și anunță-ne imediat.</li>
  <li>Îți poți șterge contul oricând din Setări. Comenzile rămân în evidența noastră cât cere legea.</li>
  <li>Putem suspenda sau închide conturile folosite pentru fraudă, abuz sau încălcarea acestor termeni.</li>
</ul>
HTML],
    ['comanda', 'Comanda', <<<'HTML'
<ul>
  <li>Alegi activitatea, data și ora (unde e cazul), tipurile de bilete și opțiunile, apoi plătești în checkout.</li>
  <li>Cât timp finalizezi plata, locurile alese sunt rezervate temporar. Dacă plata nu se face în timpul afișat, rezervarea expiră și locurile se eliberează.</li>
  <li>Comanda este confirmată când plata este acceptată. Primești pe email confirmarea și biletele, care apar și în contul tău, la <strong>Biletele mele</strong>.</li>
  <li>Verifică datele înainte să plătești: activitatea, data, ora, numărul de bilete și adresa de email.</li>
  <li>Dacă un preț afișat este evident greșit (o eroare tehnică sau de introducere), te anunțăm și poți alege între prețul corect și anularea comenzii, cu rambursarea integrală a sumei plătite.</li>
</ul>
HTML],
    ['preturi-plata', 'Prețuri, taxe și plată', <<<'HTML'
<ul>
  <li>Prețurile biletelor sunt stabilite de operatori și sunt afișate în lei, cu TVA inclus acolo unde se aplică.</li>
  <li>Peste prețul biletelor se adaugă <strong>comisionul de ticketing</strong> bilete.online. Îl vezi separat în coș și în checkout, înainte să plătești. Tot acolo apare orice alt cost, dacă există: de exemplu costul de procesare a plății, protecția biletului, dacă o alegi, sau comisionul suplimentar pentru cardul cultural.</li>
  <li>Plătești cu cardul (Visa, Mastercard, Maestro), cu Apple Pay sau cu Google Pay, prin procesatorul de plăți (în prezent Stripe), cu autentificare 3D Secure. Unde este acceptat, poți plăti și cu cardul cultural (Edenred, Pluxee, Up), cu comisionul suplimentar afișat la plată.</li>
  <li>Nu vedem și nu păstrăm datele cardului tău: le introduci direct la procesatorul de plăți.</li>
  <li>Codurile promoționale, cardurile cadou și punctele se aplică înainte de plată, după regulile lor. Nu se cumulează decât dacă se arată asta în coș.</li>
  <li>Documentele fiscale se emit conform legii. Dacă ai nevoie de factură pe firmă, completează datele de facturare în checkout.</li>
</ul>
HTML],
    ['bilete', 'Biletele', <<<'HTML'
<ul>
  <li>Biletele sunt electronice. Le primești pe email și le găsești în cont. Le arăți la intrare pe telefon sau tipărite.</li>
  <li>Fiecare bilet are un cod QR unic, valabil pentru o singură intrare. La prima scanare biletul este folosit, iar copiile lui nu mai sunt valabile. Nu publica și nu trimite biletele altor persoane decât celor care vin cu tine.</li>
  <li>Pentru biletele cu reducere (copii, elevi, studenți, pensionari etc.), operatorul poate cere la intrare un act doveditor.</li>
  <li>Respectă regulile operatorului afișate pe pagina activității: ora de sosire, vârsta minimă, echipamentul, regulile de siguranță. La întârziere se aplică regulile operatorului.</li>
  <li>Revânzarea biletelor în scop comercial fără acordul operatorului este interzisă. Biletele obținute prin fraudă pot fi anulate fără despăgubire.</li>
</ul>
HTML],
    ['anulare-rambursare', 'Anulare, reprogramare și rambursare', <<<HTML
<p><strong>Dreptul de retragere.</strong> Pentru biletele la activități de agrement cu o dată sau o perioadă stabilită, dreptul de retragere de 14 zile nu se aplică (OUG nr. 34/2014, art. 16 lit. l).</p>
<ul>
  <li><strong>Dacă operatorul anulează activitatea</strong>, primești înapoi întreaga sumă plătită pentru biletele afectate, inclusiv comisionul de ticketing.</li>
  <li><strong>Dacă activitatea este reprogramată</strong>, biletul rămâne valabil pentru noua dată. Dacă nu poți ajunge atunci, poți cere rambursarea în 14 zile de la anunțarea noii date.</li>
  <li><strong>Dacă renunți tu</strong>, se aplică politica de anulare a operatorului, afișată pe pagina activității. Dacă activitatea nu are o astfel de politică, biletele nu se rambursează. Dacă ai ales la checkout protecția biletului, se aplică și condițiile ei.</li>
</ul>
<p>Ceri anularea sau rambursarea din <a href="/contact">pagina de contact</a> sau la {$lgMail}, cu numărul comenzii. Banii se returnează pe aceeași metodă de plată. Durata până apar în cont depinde și de banca ta. La rambursare, punctele folosite în comandă îți revin, iar cele primite pentru ea se anulează. Sumele plătite cu un card cadou se întorc pe card.</p>
HTML],
    ['carduri-cadou', 'Carduri cadou', <<<'HTML'
<ul>
  <li>Un card cadou este valabil 12 luni de la emitere, dacă la cumpărare nu se arată altă perioadă.</li>
  <li>Soldul se folosește în una sau mai multe comenzi, până se epuizează sau până expiră cardul. Verifici soldul oricând pe pagina <a href="/voucher">Verifică un card cadou</a>.</li>
  <li>Cardul cadou nu se preschimbă în bani și nu se rambursează, cu excepția situațiilor prevăzute de lege.</li>
  <li>Codul cardului funcționează ca numerarul: păstrează-l în siguranță. Nu răspundem pentru folosirea lui de către altcineva căruia i l-ai dat sau care l-a găsit.</li>
</ul>
HTML],
    ['puncte', 'Programul de puncte', <<<'HTML'
<ul>
  <li>Primești puncte pentru comenzile plătite. Ele apar „în așteptare” și devin disponibile după ce activitatea a avut loc.</li>
  <li>Poți primi puncte și de ziua ta (dacă ai data nașterii în cont) și pentru prietenii invitați cu codul tău: le primiți amândoi, după ce prietenul își face cont și cumpără, în limitele programului.</li>
  <li>Cât valorează punctele, câte primești, cât poți folosi pe o comandă și când expiră le vezi în cont, la <strong>Punctele mele</strong>, și în coș, înainte de plată.</li>
  <li>Cu punctele reduci prețul biletelor sau participi la concursurile bilete.online, după regulamentul fiecărui concurs.</li>
  <li>Punctele nu au valoare în bani, nu se transferă altui cont și expiră după perioada afișată în cont.</li>
  <li>Punctele primite pentru comenzi anulate sau rambursate se retrag. Punctele obținute prin fraudă (conturi multiple, invitații false, comenzi fictive) se anulează, iar contul poate fi suspendat.</li>
  <li>Putem schimba sau încheia programul cu un anunț făcut cu cel puțin 30 de zile înainte. Punctele deja disponibile le poți folosi până la data anunțată.</li>
</ul>
HTML],
    ['recenzii', 'Recenzii', <<<'HTML'
<ul>
  <li>Recenziile trebuie să fie sincere și să se refere la experiența ta la activitatea respectivă.</li>
  <li>Nu sunt permise limbajul ofensator, datele personale ale altor persoane, reclamele sau conținutul care încalcă legea sau drepturile altora.</li>
  <li>Putem modera, ascunde sau șterge recenziile care nu respectă aceste reguli.</li>
  <li>Când publici o recenzie, ne dai dreptul neexclusiv și gratuit să o afișăm, împreună cu fotografiile, pe platformă și în materialele bilete.online.</li>
</ul>
HTML],
    ['reguli', 'Ce nu este permis', <<<'HTML'
<ul>
  <li>să folosești platforma pentru fraudă sau cu o identitate falsă;</li>
  <li>să folosești roboți sau programe automate pentru a cumpăra bilete sau pentru a copia conținutul platformei;</li>
  <li>să încerci să ocolești măsurile de securitate sau să perturbi funcționarea platformei;</li>
  <li>să revinzi biletele în scop comercial fără acordul operatorului;</li>
  <li>să copiezi sau să republici conținutul platformei fără acordul nostru.</li>
</ul>
HTML],
    ['operatori', 'Pentru operatori', <<<'HTML'
<ul>
  <li>Te înscrii pe pagina <a href="/inregistrare-locatie">Înregistrare locație</a>. Îți creăm pe loc contul de operator, în care poți intra imediat.</li>
  <li>Până când un operator bilete.online îți aprobă cererea (de regulă în cel mult 24 de ore), nimic din ce adaugi în cont nu este public.</li>
  <li>Comisionul, încasarea și plata sumelor, rapoartele și celelalte condiții comerciale sunt stabilite în contractul de parteneriat, pe care îl semnezi electronic în cont.</li>
  <li>Răspunzi de corectitudinea informațiilor publicate, de desfășurarea activităților, de autorizațiile necesare, de siguranța participanților, de obligațiile fiscale și de politica de anulare pe care o afișezi.</li>
  <li>Datele participanților le primești doar pentru organizarea activității și le folosești conform legii.</li>
</ul>
HTML],
    ['proprietate', 'Proprietate intelectuală', <<<'HTML'
<p>Designul, textele, marca bilete.online, codul și bazele de date ale platformei ne aparțin sau le folosim cu acordul titularilor. Fotografiile și descrierile activităților aparțin operatorilor, care ne permit să le afișăm. Nu le poți copia sau folosi în alt scop fără acordul titularului.</p>
HTML],
    ['raspundere', 'Răspundere', <<<'HTML'
<ul>
  <li>Facem tot ce ține de noi ca platforma să funcționeze fără întreruperi, dar pot apărea pauze pentru mentenanță sau probleme tehnice independente de noi.</li>
  <li>Nu răspundem pentru desfășurarea activității, de care răspunde operatorul, pentru informațiile publicate de operatori sau pentru pierderile cauzate de împărțirea biletelor, a codurilor sau a parolei cu alte persoane.</li>
  <li>Nimic din acești termeni nu îți limitează drepturile de consumator prevăzute de lege și nici răspunderea noastră acolo unde legea nu permite limitarea ei.</li>
</ul>
HTML],
    ['forta-majora', 'Forța majoră', <<<'HTML'
<p>Nici noi, nici operatorii nu răspundem pentru neexecutarea obligațiilor cauzată de un eveniment de forță majoră (de exemplu calamități naturale, restricții impuse de autorități, epidemii). Pentru activitățile anulate din acest motiv se aplică regulile de anulare și rambursare de mai sus.</p>
HTML],
    ['reclamatii', 'Reclamații și soluționarea litigiilor', <<<HTML
<p>Pentru orice nemulțumire, scrie-ne din <a href="/contact">pagina de contact</a> sau la {$lgMail}. Îți răspundem cât mai repede, în cel mult 30 de zile.</p>
<p>Dacă nu ajungem la o soluție, te poți adresa Autorității Naționale pentru Protecția Consumatorilor (<a href="https://anpc.ro" rel="noopener" target="_blank">anpc.ro</a>) sau poți folosi procedura de soluționare alternativă a litigiilor (<a href="https://anpc.ro/ce-este-sal/" rel="noopener" target="_blank">SAL</a>).</p>
<p>Acești termeni sunt guvernați de legea română. Litigiile care nu se rezolvă pe cale amiabilă sunt de competența instanțelor din România.</p>
HTML],
    ['modificari', 'Modificări ale termenilor', <<<'HTML'
<p>Putem actualiza acești termeni când se schimbă platforma sau legea. Data ultimei actualizări apare la începutul paginii. Comenzilor plasate deja li se aplică termenii în vigoare la data comenzii. Despre schimbările importante te anunțăm pe email sau printr-un mesaj pe platformă.</p>
HTML],
];

$pageTitleRaw = 'Termeni și condiții — ' . SITE_NAME;
$pageDescription = 'Regulile bilete.online: comanda și plata, biletele, anularea și rambursarea, cardurile cadou, programul de puncte, recenziile și condițiile pentru operatori.';
$canonicalUrl = SITE_URL . '/termeni';

$v2Styles = ['legal.css'];
$v2Scripts = ['legal.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';

v2_legal_render([
    'key' => 'termeni',
    'kicker' => 'Termeni și condiții',
    'title' => 'Regulile bilete.online, pe înțeles',
    'lead' => 'Cum cumperi, cum folosești biletele, ce se întâmplă la anulare și ce reguli au cardurile cadou, punctele și recenziile. Plus ce se aplică operatorilor care își listează activitățile.',
    'sections' => $lgSections,
]);

include __DIR__ . '/includes/v2/footer.php';
