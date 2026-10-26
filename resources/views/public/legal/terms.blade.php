@extends('public.legal.layout')
@php($c = \App\Support\LegalInfo::company())

@section('title', 'Termeni și condiții')
@section('description', 'Termenii și condițiile de folosire a platformei Tixello: contul, serviciile, comisionul, plățile, datele personale și răspunderea.')

@section('toc')
    @foreach ([
        'cine' => 'Cine suntem', 'definitii' => 'Ce înseamnă termenii', 'acord' => 'Acordul dintre noi', 'cont' => 'Contul și aprobarea',
        'servicii' => 'Ce îți oferim', 'rol' => 'Rolul nostru și al tău', 'obligatii' => 'Obligațiile tale', 'pret' => 'Prețuri, comision și plată',
        'continut' => 'Conținutul tău', 'proprietate' => 'Proprietatea intelectuală', 'date' => 'Datele personale', 'disponibilitate' => 'Disponibilitate și copii de siguranță',
        'raspundere' => 'Răspunderea', 'suspendare' => 'Suspendare și încetare', 'modificari' => 'Modificarea termenilor', 'lege' => 'Legea aplicabilă și litigii', 'finale' => 'Dispoziții finale',
    ] as $id => $label)
        <li><a href="#{{ $id }}">{{ $loop->iteration }}. {{ $label }}</a></li>
    @endforeach
@endsection

@section('content')
<p class="eyebrow">Document contractual</p>
<h1>Termeni și condiții</h1>
<p class="meta">În vigoare din {{ config('legal.effective_date') }} · versiunea {{ config('legal.version') }}</p>

<div class="summary">
    <h2>Pe scurt</h2>
    <ul>
        <li>Tixello îți dă uneltele prin care vinzi bilete: site, plată cu cardul, bilete pe email, scanare și panou de vânzări. <strong>Vânzătorul biletelor ești tu</strong>, nu noi.</li>
        <li>Plătești un comision din vânzările făcute prin platformă (1% sau 2%, plus un punct pentru site-ul găzduit de noi sau pentru unele funcții). Nu există abonament lunar obligatoriu.</li>
        <li>Banii din bilete ajung direct la tine, prin procesatorul tău de plăți. Rambursările, anulările și relația cu cumpărătorii sunt responsabilitatea ta.</li>
        <li>Pentru datele cumpărătorilor tăi lucrăm în numele tău. Pentru cumpărătorii care își fac cont Tixello sau ne dau acordul, devenim și operator separat.</li>
        <li>Răspunderea noastră e limitată la comisioanele pe care ni le-ai plătit în ultimele 12 luni.</li>
    </ul>
    <p style="margin-bottom:0">Rezumatul te ajută să te orientezi. Contează textul complet de mai jos.</p>
</div>

<h2 id="cine">1. Cine suntem</h2>
<p>Platforma Tixello (inclusiv site-ul core.tixello.com, panourile de administrare, site-urile de bilete găzduite pe subdomeniile noastre, aplicațiile și interfețele de programare) este operată de:</p>
<div class="company">
    <p><strong>{{ $c['name'] }}</strong></p>
    @if ($c['cui'])<p>CUI: {{ $c['cui'] }}</p>@endif
    @if ($c['reg_com'])<p>Nr. Registrul Comerțului: {{ $c['reg_com'] }}</p>@endif
    @if ($c['address'])<p>Sediu: {{ $c['address'] }}</p>@endif
    <p>Email: <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>@if ($c['phone']) · Telefon: {{ $c['phone'] }}@endif</p>
</div>
<p>În acest document, „Tixello”, „noi” și „al nostru” se referă la societatea de mai sus.</p>

<h2 id="definitii">2. Ce înseamnă termenii folosiți</h2>
<ul>
    <li><strong>Platforma</strong>: software-ul Tixello, cu tot ce ține de el: panouri, site-uri găzduite, aplicații, API-uri, emailuri și fișiere generate.</li>
    <li><strong>Clientul</strong> (sau „tu”): persoana juridică, PFA-ul sau persoana fizică care își face cont pe Platformă ca să vândă bilete, plus persoanele pe care le adaugă în contul său.</li>
    <li><strong>Contul</strong>: spațiul tău din Platformă, cu datele, evenimentele, vânzările și setările tale.</li>
    <li><strong>Eveniment</strong>: spectacolul, concertul, competiția, festivalul sau orice altă activitate pentru care vinzi bilete prin Platformă.</li>
    <li><strong>Cumpărătorul</strong>: persoana care cumpără, primește sau folosește un bilet sau o invitație la un Eveniment al tău.</li>
    <li><strong>Comisionul</strong>: suma pe care ne-o datorezi pentru vânzările făcute prin Platformă, calculată conform articolului 8.</li>
    <li><strong>Funcțiile suplimentare</strong> (microserviciile): module opționale, cum sunt vânzarea la intrare, magazinul sau conectorii de facturare, pe care le pornești din panou sau la înscriere.</li>
    <li><strong>Site-ul găzduit</strong>: site-ul de bilete pe care îl găzduim pe un subdomeniu Tixello (de exemplu, numele-tau.tics.ro).</li>
</ul>

<h2 id="acord">3. Acordul dintre noi</h2>
<p>Acești termeni formează contractul dintre tine și Tixello pentru folosirea Platformei. Îi accepți bifând căsuța de la înscriere. Păstrăm data, ora, adresa IP și versiunea documentului pe care l-ai acceptat. Acceptarea electronică are aceeași valoare ca o semnătură, conform Legii nr. 365/2002 privind comerțul electronic.</p>
<p>După aprobarea contului îți trimitem și un contract pe email. Dacă contractul semnat separat cu noi diferă de acești termeni, prevalează contractul semnat, dar numai în punctele în care diferă.</p>
<p>Ca să folosești Platforma trebuie să ai capacitate deplină de exercițiu și, dacă reprezinți o firmă sau o instituție, dreptul de a o angaja. Platforma e făcută pentru activități profesionale: vânzarea de bilete la Evenimente.</p>

<h2 id="cont">4. Contul și aprobarea</h2>
<h3>4.1. Înscrierea</h3>
<p>La înscriere ne dai datele de contact, confirmi adresa de email cu un cod și ne spui pe cine facem contractul. Pentru firme și PFA-uri luăm denumirea, numărul de la Registrul Comerțului, adresa și situația TVA din bazele publice ale ANAF. Firma trebuie să fie activă. Pentru persoane fizice ne dai CNP-ul și datele din actul de identitate, care apar doar în contract.</p>
<h3>4.2. Verificarea și aprobarea</h3>
<p>Fiecare cont nou intră în verificare. Cât e în verificare poți pregăti evenimentele în panou, dar nu poți vinde și site-ul nu se publică. Putem aproba sau refuza contul. Dacă îl refuzăm, îți spunem motivul pe email. Putem cere documente suplimentare când datele nu se potrivesc sau când avem motive să credem că un cont e folosit pentru fraudă.</p>
<h3>4.3. Datele corecte și siguranța contului</h3>
<ul>
    <li>Te obligi să ne dai date reale și să le ții la zi. Răspunzi de consecințele datelor greșite, de exemplu pe facturi sau în contract.</li>
    <li>Păstrezi parola doar pentru tine și ne anunți imediat dacă bănuiești că altcineva are acces la cont.</li>
    <li>Răspunzi de tot ce fac în cont persoanele cărora le dai acces, ca și cum ai fi făcut tu.</li>
</ul>

<h2 id="servicii">5. Ce îți oferim</h2>
<p>În funcție de ce alegi și de ce pornești, Platforma îți permite să:</p>
<ul>
    <li>creezi evenimente, tipuri de bilete, prețuri, hărți de locuri și invitații;</li>
    <li>vinzi bilete online (pe Site-ul găzduit, pe site-ul tău sau prin widget), la casă și la intrare;</li>
    <li>trimiți biletele pe email, le validezi la intrare și urmărești vânzările și rapoartele;</li>
    <li>folosești Funcțiile suplimentare: magazin, coduri de reducere, vânzare la intrare, conectori de facturare, măsurarea reclamelor, mesaje și altele.</li>
</ul>
<p>Platforma evoluează: putem adăuga, schimba sau scoate funcții. Dacă scoatem o funcție pe care o plătești separat, te anunțăm cu cel puțin 30 de zile înainte și nu mai plătești pentru ea. Funcțiile marcate „beta” sunt în test și le oferim așa cum sunt.</p>

<h2 id="rol">6. Rolul nostru și al tău</h2>
<p><strong>Tixello e furnizor de tehnologie. Tu ești organizatorul și vânzătorul biletelor.</strong> Contractul de vânzare a biletului se încheie între tine și Cumpărător. Noi nu suntem parte în acest contract și nu suntem organizatorul Evenimentelor tale.</p>
<p>Concret, tu răspunzi față de Cumpărători pentru:</p>
<ul>
    <li>desfășurarea Evenimentului așa cum l-ai anunțat: data, ora, locul, programul, artiștii;</li>
    <li>informațiile afișate: prețul total, condițiile de acces, restricțiile de vârstă, regulile locului;</li>
    <li><strong>anularea, amânarea sau schimbarea Evenimentului și rambursarea biletelor</strong>. Platforma îți dă unealta de rambursare, dar decizia și banii sunt ai tăi, fiindcă plata a ajuns la tine prin procesatorul tău;</li>
    <li>contestațiile și cererile de restituire depuse de Cumpărători la bancă (chargeback), inclusiv costurile lor;</li>
    <li>siguranța participanților și respectarea regulilor locului.</li>
</ul>
<p>Pentru biletele la evenimente cu dată fixă, dreptul de retragere de 14 zile nu se aplică (art. 16 lit. l din OUG nr. 34/2014). Dacă oferi totuși posibilitatea de returnare, condițiile le stabilești tu și le afișezi la vânzare.</p>

<h2 id="obligatii">7. Obligațiile tale</h2>
<h3>7.1. Respectarea legii</h3>
<p>Ai grijă ca Evenimentele și vânzarea biletelor să respecte legea. De exemplu, și fără ca lista să fie completă:</p>
<ul>
    <li>obligațiile fiscale: impozitul pe spectacole, TVA-ul, emiterea documentelor fiscale și înregistrarea biletelor la autoritatea locală, unde e cazul. Platforma te ajută cu documente (decont, cerere de avizare, procese-verbale), dar corectitudinea și depunerea lor sunt responsabilitatea ta;</li>
    <li>folosirea aparatelor de marcat electronice fiscale pentru încasările în numerar la intrare, conform OUG nr. 28/1999;</li>
    <li>drepturile de autor și drepturile conexe pentru operele prezentate, avizele și autorizațiile necesare Evenimentului;</li>
    <li>regulile de protecție a consumatorilor și ale comerțului electronic;</li>
    <li>regulile de protecție a datelor personale, ca operator al datelor Cumpărătorilor tăi (vezi articolul 11).</li>
</ul>
<h3>7.2. Ce nu ai voie să faci</h3>
<ul>
    <li>să vinzi bilete la evenimente care nu există, care nu sunt ale tale sau pe care nu ai dreptul să le vinzi;</li>
    <li>să prezinți informații înșelătoare despre Eveniment, preț sau disponibilitate;</li>
    <li>să folosești Platforma pentru spălare de bani, fraudă cu carduri, spam sau orice activitate ilegală;</li>
    <li>să încerci să ocolești securitatea Platformei, să o încarci artificial, să o copiezi sau să extragi automat date care nu sunt ale tale;</li>
    <li>să trimiți mesaje de marketing celor care nu și-au dat acordul.</li>
</ul>
<p>Ne despăgubești pentru pagubele și cheltuielile, inclusiv amenzi și onorarii de avocat, pe care le suportăm din cauza încălcării acestor obligații de către tine.</p>

<h2 id="pret">8. Prețuri, comision și plată</h2>
<h3>8.1. Comisionul</h3>
<table class="table">
    <thead><tr><th>Cum vinzi</th><th>Comision</th></tr></thead>
    <tbody>
        <tr><td>Doar prin Tixello (online, la casă și la intrare)</td><td><strong>1%</strong></td></tr>
        <tr><td>Prin Tixello și prin alte platforme sau agenții</td><td><strong>2%</strong></td></tr>
        <tr><td>În plus, dacă site-ul tău de bilete e găzduit de noi pe un subdomeniu Tixello sau dacă pornești o funcție marcată „+1 punct la comision”</td><td><strong>+1 punct</strong>, o singură dată, oricâte astfel de opțiuni ai</td></tr>
    </tbody>
</table>
<p>Comisionul se calculează la valoarea totală a comenzilor înregistrate prin Platformă în perioada de facturare, mai puțin comenzile anulate. Modelul de site ales nu schimbă comisionul. Comisionul aplicabil contului tău e afișat la înscriere, în contract și în panou.</p>
<p>Dacă declari că vinzi doar prin Tixello, dar aflăm că vinzi și prin alte canale, putem trece comisionul la nivelul corespunzător, cu notificare pe email, începând cu perioada următoare.</p>
<h3>8.2. Facturarea</h3>
<ul>
    <li>Factura de comision se emite o dată la 30 de zile, pentru vânzările din perioada respectivă. Dacă nu ai vândut nimic, nu primești factură.</li>
    <li>Termenul de plată e de {{ $c['payment_days'] }} zile de la emiterea facturii. La sume se adaugă TVA, dacă e cazul. Facturile se transmit și prin sistemul e-Factura, unde legea o cere.</li>
    <li>Pentru întârzieri datorezi dobânda penalizatoare prevăzută de OG nr. 13/2011 și de Legea nr. 72/2013. După 15 zile de întârziere și o notificare pe email, putem suspenda vânzarea până la plată.</li>
</ul>
<h3>8.3. Funcțiile suplimentare și site-ul făcut la comandă</h3>
<ul>
    <li>Prețul, moneda și frecvența fiecărei Funcții suplimentare (lunar, o singură dată sau după utilizare) apar în panou înainte să o pornești. Funcțiile cu abonament se plătesc cu cardul, prin Stripe. Le poți opri oricând; perioada deja începută nu se rambursează.</li>
    <li>Funcțiile marcate „+1 punct la comision” nu au abonament: costul lor e punctul de comision din tabelul de mai sus.</li>
    <li>Un site de bilete făcut în întregime pentru tine costă {{ number_format((float) config('onboarding.custom_site_price'), 0, ',', '.') }} {{ config('onboarding.custom_site_currency') === 'EUR' ? 'euro' : config('onboarding.custom_site_currency') }}, plată unică. Ce conține se stabilește în scris înainte să începem lucrul, iar plata o faci după ce stabilim detaliile.</li>
</ul>
<h3>8.4. Plățile Cumpărătorilor</h3>
<p>Plata biletelor trece prin procesatorul de plăți ales de tine (de exemplu Netopia, Stripe, EuPlătesc sau PayU), cu care ai un contract direct. Banii din bilete ajung la tine prin acest procesator, nu prin Tixello. Comisioanele procesatorului se plătesc separat, conform contractului tău cu el.</p>
<h3>8.5. Schimbarea prețurilor</h3>
<p>Putem schimba comisionul și prețurile Funcțiilor suplimentare cu o notificare pe email trimisă cu cel puțin 30 de zile înainte. Dacă nu ești de acord, poți închide contul înainte de data schimbării, fără alte costuri decât cele datorate până atunci.</p>

<h2 id="continut">9. Conținutul tău</h2>
<p>Textele, imaginile, logo-urile, descrierile evenimentelor și celelalte materiale pe care le încarci rămân ale tale. Ne dai dreptul neexclusiv, gratuit și valabil pe durata contractului de a le găzdui, afișa, adapta tehnic (de exemplu redimensionarea imaginilor) și transmite, în măsura necesară funcționării Platformei, inclusiv pe Site-ul găzduit și în emailurile către Cumpărători.</p>
<p>Garantezi că ai toate drepturile necesare asupra acestui conținut, inclusiv acordul persoanelor care apar în imagini. Putem ascunde sau șterge conținutul vădit ilegal ori care încalcă drepturile altcuiva și te anunțăm despre asta.</p>

<h2 id="proprietate">10. Proprietatea intelectuală</h2>
<p>Platforma, codul, designul, modelele de site, documentația și mărcile Tixello sunt proprietatea noastră sau a partenerilor noștri. Îți dăm un drept neexclusiv și netransmisibil de a le folosi pe durata contractului, doar pentru vânzarea biletelor la Evenimentele tale. Nu ai voie să copiezi, să modifici, să decompilezi sau să revinzi Platforma ori modelele de site.</p>
<p>Dacă ne trimiți idei sau sugestii, le putem folosi pentru îmbunătățirea Platformei fără obligații față de tine.</p>

<h2 id="date">11. Datele personale</h2>
<p>Cum prelucrăm datele personale e descris pe larg în <a href="/politica-confidentialitate">Politica de confidențialitate</a>, care face parte din acești termeni. Aici sunt regulile dintre noi.</p>
<h3>11.1. Datele Cumpărătorilor tăi: lucrăm în numele tău</h3>
<p>Pentru datele Cumpărătorilor pe care le prelucrăm ca să vinzi bilete, <strong>tu ești operatorul, iar Tixello e persoana împuternicită</strong>, în sensul articolului 28 din Regulamentul (UE) 2016/679 (GDPR). Acest articol ține loc de acord de prelucrare a datelor. În această calitate, Tixello:</p>
<ul>
    <li>prelucrează datele doar după instrucțiunile tale. Instrucțiunile sunt acești termeni și setările pe care le faci în cont;</li>
    <li>se asigură că persoanele care au acces la date sunt obligate să păstreze confidențialitatea;</li>
    <li>aplică măsurile de securitate descrise în Politica de confidențialitate;</li>
    <li>folosește furnizorii (sub-împuterniciții) din lista publicată în Politica de confidențialitate, cu obligații de protecție a datelor cel puțin la fel de stricte. Te anunțăm pe email despre schimbările de furnizori cu cel puțin 15 zile înainte; te poți opune motivat, iar dacă nu găsim o soluție poți închide contul;</li>
    <li>te ajută, în limita posibilităților tehnice, să răspunzi cererilor Cumpărătorilor (acces, ștergere, export) și în evaluările de impact sau consultările cu autoritatea;</li>
    <li>te anunță fără întârziere nejustificată, în cel mult 48 de ore de la constatare, despre orice încălcare a securității care afectează datele tale;</li>
    <li>la încetarea contractului îți permite să exporți datele timp de 30 de zile, apoi le șterge în cel mult 90 de zile, cu excepția celor pe care legea ne obligă să le păstrăm;</li>
    <li>îți pune la dispoziție informațiile necesare pentru a verifica respectarea acestor obligații și acceptă verificări rezonabile, anunțate cu cel puțin 30 de zile înainte, cel mult o dată pe an, pe cheltuiala ta.</li>
</ul>
<p>Datele prelucrate în această calitate sunt: date de identificare și de contact (nume, email, telefon), comenzi, bilete, locuri, scanări la intrare, date de facturare, acorduri de marketing, mesaje schimbate cu tine și date tehnice despre navigare. Le prelucrăm cât timp contul e activ, pentru vânzarea și validarea biletelor, comunicările legate de comenzi, rapoarte și funcțiile pe care le pornești.</p>
<h3>11.2. Când Tixello e operator separat</h3>
<p>Tixello prelucrează date în nume propriu, ca operator separat, în următoarele situații:</p>
<ul>
    <li><strong>datele tale și ale echipei tale</strong>: cont, contract, facturare, suport, comunicări despre serviciu;</li>
    <li><strong>Cumpărătorii care își fac cont Tixello sau care își dau acordul</strong> să primească informări și oferte de la Tixello. Pentru ei Tixello e operator separat de tine, pe baza acordului lor, pe care îl pot retrage oricând;</li>
    <li><strong>date agregate sau anonimizate</strong>, din care nu poate fi identificată o persoană, folosite pentru statistici, cercetare de piață și îmbunătățirea Platformei. Nu facem publice date care să te identifice ca client fără acordul tău;</li>
    <li>securitatea Platformei, prevenirea fraudei și respectarea obligațiilor legale, de exemplu cele fiscale.</li>
</ul>
<h3>11.3. Obligațiile tale față de Cumpărători</h3>
<p>Ca operator, ai nevoie de o bază legală pentru prelucrarea datelor Cumpărătorilor și trebuie să-i informezi. În politica ta de confidențialitate spui că folosești Tixello ca furnizor pentru vânzarea biletelor. Ceri acordul pentru marketing doar acolo unde legea îl cere. Când folosești funcții care trimit date către terți (de exemplu Facebook Conversions API, pixeli de urmărire sau exportul de clienți), te asiguri că ai acordul necesar, inclusiv pentru cookie-uri.</p>

<h2 id="disponibilitate">12. Disponibilitate și copii de siguranță</h2>
<p>Facem tot ce ne stă în putere ca Platforma să funcționeze fără întreruperi, dar nu putem garanta că va fi disponibilă în orice moment. Lucrările de întreținere le facem, pe cât posibil, în perioade cu trafic redus. Pe cele mari le anunțăm din timp.</p>
<p>Facem zilnic copii de siguranță ale bazei de date și le păstrăm {{ config('legal.backup_retention_days') }} zile. Te sfătuim să-ți păstrezi și tu exporturi ale datelor importante, de exemplu rapoartele de vânzări.</p>

<h2 id="raspundere">13. Răspunderea</h2>
<ul>
    <li>Răspunderea totală a Tixello față de tine, indiferent de cauză, e limitată la <strong>comisioanele pe care ni le-ai plătit în ultimele 12 luni</strong> dinaintea faptei care a produs paguba.</li>
    <li>Nu răspundem pentru pagube indirecte: profit sau vânzări pierdute, pierderi de imagine, costuri ale Evenimentului ori pretenții ale Cumpărătorilor legate de Eveniment.</li>
    <li>Nu răspundem pentru funcționarea serviciilor terților pe care le folosești prin Platformă: procesatori de plăți, ANAF, bănci, furnizori de email sau SMS, rețele sociale.</li>
    <li>Limitările de mai sus nu se aplică pagubelor cauzate cu intenție sau din culpă gravă și nici situațiilor în care legea nu permite limitarea răspunderii (art. 1355 Cod civil).</li>
    <li>Nicio parte nu răspunde pentru neexecutarea cauzată de forță majoră sau de caz fortuit, cum sunt atacuri informatice de amploare, căderi ale rețelelor publice sau decizii ale autorităților. Partea afectată o anunță pe cealaltă cât mai repede.</li>
</ul>

<h2 id="suspendare">14. Suspendare și încetare</h2>
<h3>14.1. Durata</h3>
<p>Contractul se încheie pe durată nedeterminată, de la aprobarea contului.</p>
<h3>14.2. Încetarea</h3>
<ul>
    <li><strong>Tu</strong> poți închide contul oricând, cu un email de la adresa contului. Plătești comisioanele și abonamentele datorate până la data închiderii.</li>
    <li><strong>Noi</strong> putem înceta contractul cu un preaviz de 30 de zile, trimis pe email.</li>
    <li>Putem înceta contractul imediat, cu notificare, dacă încalci grav acești termeni: fraudă, evenimente inexistente, folosirea Platformei pentru activități ilegale sau neplata după suspendare.</li>
</ul>
<h3>14.3. Suspendarea</h3>
<p>Putem suspenda, total sau parțial, vânzarea ori accesul la cont când e nevoie ca să oprim o fraudă, ca să protejăm Cumpărătorii sau Platforma, ori dacă o autoritate ne-o cere. Te anunțăm și ridicăm suspendarea imediat ce dispare motivul.</p>
<h3>14.4. Ce se întâmplă după încetare</h3>
<p>Biletele deja vândute rămân valabile pentru Evenimentele programate, iar validarea lor la intrare funcționează până la data ultimului Eveniment vândut, dar nu mai mult de 90 de zile. Ai 30 de zile să-ți exporți datele. Obligațiile de plată, de confidențialitate, de despăgubire și cele legate de datele personale rămân valabile și după încetare.</p>

<h2 id="modificari">15. Modificarea termenilor</h2>
<p>Putem modifica acești termeni. Te anunțăm pe email cu cel puțin 30 de zile înainte să intre în vigoare noua versiune. Excepție fac schimbările cerute de lege sau cele care nu te dezavantajează: acestea pot intra în vigoare mai repede. Dacă nu ești de acord, poți închide contul înainte de data intrării în vigoare. Dacă folosești Platforma și după această dată, înseamnă că accepți noua versiune.</p>

<h2 id="lege">16. Legea aplicabilă și litigii</h2>
<p>Contractului i se aplică legea română. Încercăm să rezolvăm orice neînțelegere pe cale amiabilă; scrie-ne la <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>. Dacă nu reușim în 30 de zile, litigiul se soluționează de instanțele competente de la sediul Tixello.</p>
<p>Dacă folosești Platforma ca persoană fizică și ești considerat consumator, beneficiezi și de drepturile date de legislația protecției consumatorilor, inclusiv de posibilitatea de a te adresa Autorității Naționale pentru Protecția Consumatorilor (<a href="https://anpc.ro" target="_blank" rel="noopener">anpc.ro</a>) și entităților de soluționare alternativă a litigiilor (SAL), conform OG nr. 38/2015.</p>

<h2 id="finale">17. Dispoziții finale</h2>
<ul>
    <li>Comunicările dintre noi se fac pe email, la adresa contului tău și la <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a>. Un email se consideră primit în ziua lucrătoare de după trimitere.</li>
    <li>Dacă o prevedere e declarată nevalabilă, restul termenilor rămân în vigoare, iar prevederea respectivă se înlocuiește cu una valabilă, cât mai apropiată de intenția inițială.</li>
    <li>Nu poți transfera contractul altcuiva fără acordul nostru scris. Noi îl putem transfera unei societăți din grupul nostru sau celui care preia activitatea Platformei, cu notificare.</li>
    <li>Faptul că nu cerem la un moment dat respectarea unei prevederi nu înseamnă că renunțăm la ea.</li>
    <li>Acești termeni sunt redactați în limba română. Orice traducere e doar informativă.</li>
</ul>
@endsection
