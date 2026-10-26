@extends('public.legal.layout')
@php($c = \App\Support\LegalInfo::company())
@php($authority = config('legal.authority'))

@section('title', 'Politica de confidențialitate')
@section('description', 'Cum prelucrează Tixello datele personale ale clienților, ale echipelor lor, ale cumpărătorilor de bilete și ale vizitatorilor.')

@section('toc')
    @foreach ([
        'cine' => 'Cine suntem', 'roluri' => 'Pe cine privește și în ce rol', 'clienti' => 'Datele clienților și ale echipelor',
        'cumparatori' => 'Datele cumpărătorilor de bilete', 'vizitatori' => 'Vizitatori, cookie-uri și resurse externe', 'destinatari' => 'Cui transmitem datele',
        'transferuri' => 'Transferuri în afara SEE', 'pastrare' => 'Cât păstrăm datele', 'securitate' => 'Cum protejăm datele', 'drepturi' => 'Drepturile tale',
        'minori' => 'Minori', 'modificari' => 'Modificări', 'contact' => 'Contact',
    ] as $id => $label)
        <li><a href="#{{ $id }}">{{ $loop->iteration }}. {{ $label }}</a></li>
    @endforeach
@endsection

@section('content')
<p class="eyebrow">Protecția datelor</p>
<h1>Politica de confidențialitate</h1>
<p class="meta">În vigoare din {{ config('legal.effective_date') }} · versiunea {{ config('legal.version') }}</p>

<div class="summary">
    <h2>Pe scurt</h2>
    <ul>
        <li>Datele clienților noștri (teatre, festivaluri, organizatori) și ale echipelor lor le folosim pentru cont, contract, facturare și suport.</li>
        <li>Datele cumpărătorilor de bilete le prelucrăm <strong>în numele organizatorului</strong>, ca să funcționeze vânzarea și intrarea la eveniment. Organizatorul decide ce face cu ele.</li>
        <li>Dacă un cumpărător își face cont Tixello sau își dă acordul, devenim și operator separat pentru datele lui și îi putem trimite informări, până își retrage acordul.</li>
        <li>Nu vindem date personale. Le transmitem doar furnizorilor de care avem nevoie ca platforma să meargă și autorităților, când legea o cere.</li>
        <li>Ne poți scrie oricând la <a href="mailto:{{ $c['privacy_email'] }}">{{ $c['privacy_email'] }}</a> ca să-ți vezi, corectezi sau ștergi datele.</li>
    </ul>
</div>

<h2 id="cine">1. Cine suntem</h2>
<p>Operatorul datelor, în situațiile descrise mai jos, este:</p>
<div class="company">
    <p><strong>{{ $c['name'] }}</strong></p>
    @if ($c['cui'])<p>CUI: {{ $c['cui'] }}</p>@endif
    @if ($c['reg_com'])<p>Nr. Registrul Comerțului: {{ $c['reg_com'] }}</p>@endif
    @if ($c['address'])<p>Sediu: {{ $c['address'] }}</p>@endif
    <p>Pentru date personale: <a href="mailto:{{ $c['privacy_email'] }}">{{ $c['privacy_email'] }}</a></p>
</div>
<p>Nu avem obligația legală de a numi un responsabil cu protecția datelor (DPO) și nu am numit unul. Toate cererile legate de date personale le trimiți la adresa de mai sus.</p>

<h2 id="roluri">2. Pe cine privește și în ce rol suntem</h2>
<table class="table">
    <thead><tr><th>Cine</th><th>Rolul Tixello</th></tr></thead>
    <tbody>
        <tr><td><strong>Clienții</strong>: firmele, instituțiile și persoanele care își fac cont ca să vândă bilete, plus membrii echipelor lor</td><td>Operator</td></tr>
        <tr><td><strong>Cumpărătorii de bilete</strong> ai clienților noștri</td><td>Persoană împuternicită, adică lucrăm în numele organizatorului. Organizatorul e operatorul și te informează prin politica lui.</td></tr>
        <tr><td><strong>Cumpărătorii care își fac cont Tixello sau își dau acordul</strong> pentru informări de la Tixello</td><td>Operator separat, pentru datele de cont și pentru informări, pe baza acordului</td></tr>
        <tr><td><strong>Vizitatorii</strong> site-ului core.tixello.com</td><td>Operator</td></tr>
    </tbody>
</table>

<h2 id="clienti">3. Datele clienților și ale echipelor lor</h2>
<h3>3.1. Ce date prelucrăm</h3>
<ul>
    <li><strong>De cont</strong>: nume, prenume, email, telefon, parola (salvată doar criptată, nu o putem citi), rolul în cont.</li>
    <li><strong>De confirmare și acord</strong>: confirmarea emailului (codul trimis nu rămâne în jurnalele noastre), data, ora, adresa IP și versiunea termenilor acceptați.</li>
    <li><strong>De facturare</strong>: pentru firme și PFA, denumirea, CUI-ul, numărul de la Registrul Comerțului, adresa și situația TVA, luate din bazele publice ale ANAF. Pentru persoane fizice, numele, adresa, CNP-ul, seria și numărul actului de identitate, păstrate criptat și folosite doar în contract.</li>
    <li><strong>Despre activitate</strong>: tipul de evenimente, numele public, alegerile de la înscriere (site, model, procesator, funcții), comisionul, contractul și facturile.</li>
    <li><strong>De folosire</strong>: acțiunile făcute în panou (jurnal de activitate), inclusiv exporturile de date, adrese IP, tipul de dispozitiv și browser, erori tehnice.</li>
    <li><strong>De plată</strong> pentru funcțiile cu abonament: le procesează Stripe. Noi nu vedem și nu păstrăm datele cardului.</li>
    <li><strong>Comunicări</strong>: emailurile și mesajele schimbate cu noi.</li>
</ul>
<h3>3.2. De ce și pe ce bază</h3>
<table class="table">
    <thead><tr><th>Scop</th><th>Temei legal (GDPR)</th></tr></thead>
    <tbody>
        <tr><td>Crearea, verificarea și aprobarea contului; încheierea și executarea contractului; funcționarea platformei</td><td>Contract, art. 6 alin. (1) lit. b</td></tr>
        <tr><td>Verificarea firmei la ANAF</td><td>Contract și interes legitim (să lucrăm cu firme reale și active), art. 6 alin. (1) lit. b și f</td></tr>
        <tr><td>Facturare, contabilitate, e-Factura, răspunsuri către autorități</td><td>Obligație legală, art. 6 alin. (1) lit. c</td></tr>
        <tr><td>Suport, emailuri despre cont, schimbări de preț sau de termeni</td><td>Contract și interes legitim, art. 6 alin. (1) lit. b și f</td></tr>
        <tr><td>Securitate, jurnale, prevenirea fraudei și a abuzurilor</td><td>Interes legitim, art. 6 alin. (1) lit. f</td></tr>
        <tr><td>Statistici și îmbunătățirea platformei, pe date agregate sau anonimizate</td><td>Interes legitim, art. 6 alin. (1) lit. f</td></tr>
        <tr><td>Noutăți și oferte despre Tixello</td><td>Interes legitim, pentru servicii similare celor pe care le folosești; te poți dezabona oricând, din orice email</td></tr>
        <tr><td>Apărarea drepturilor noastre în caz de litigiu</td><td>Interes legitim, art. 6 alin. (1) lit. f</td></tr>
    </tbody>
</table>
<p>Fără datele cerute la înscriere nu putem încheia contractul și nu putem emite facturi.</p>

<h2 id="cumparatori">4. Datele cumpărătorilor de bilete</h2>
<h3>4.1. Când lucrăm în numele organizatorului</h3>
<p>Când cumperi un bilet la un eveniment vândut prin Tixello, organizatorul evenimentului e operatorul datelor tale. Noi le prelucrăm în numele lui, după instrucțiunile lui și conform acordului de prelucrare din <a href="/termeni-si-conditii#date">Termeni și condiții</a>. Pentru detalii, drepturi și ce face organizatorul cu datele, citește politica de confidențialitate a organizatorului sau scrie-i direct. Dacă ne scrii nouă, îi transmitem cererea.</p>
<p>În această calitate prelucrăm:</p>
<ul>
    <li>nume, email, telefon și, dacă le dai, oraș, dată de naștere, date de facturare (inclusiv pentru firme);</li>
    <li>comanda, biletele, locurile, prețurile, codurile de reducere și starea plății. Datele cardului le procesează direct procesatorul de plăți al organizatorului, noi nu le vedem;</li>
    <li>validarea biletului la intrare (ora și punctul de acces);</li>
    <li>acordurile tale de marketing și dezabonările;</li>
    <li>date tehnice despre vizita pe site-ul de bilete, în funcție de setările organizatorului și de acordul tău pentru cookie-uri.</li>
</ul>
<h3>4.2. Când suntem operator separat</h3>
<p>Dacă îți faci cont Tixello ca cumpărător sau bifezi acordul de a primi informări de la Tixello, devenim operator separat pentru:</p>
<ul>
    <li>datele contului tău de cumpărător și istoricul biletelor cumpărate prin Tixello, ca să ai biletele într-un singur loc;</li>
    <li>informări și recomandări de evenimente, trimise pe email sau prin alte canale pentru care ți-ai dat acordul.</li>
</ul>
<p>Temeiul e acordul tău (art. 6 alin. (1) lit. a GDPR), iar pentru cont, contractul (lit. b). Îți poți retrage acordul oricând, din linkul de dezabonare sau scriindu-ne. Retragerea nu afectează prelucrările făcute înainte.</p>
<h3>4.3. Date agregate</h3>
<p>Din datele de vânzare putem obține statistici agregate sau anonimizate, de exemplu numărul de bilete vândute pe categorii de evenimente sau pe orașe. Din ele nu poți fi identificat și nu mai sunt date personale.</p>

<h2 id="vizitatori">5. Vizitatori, cookie-uri și resurse externe</h2>
<h3>5.1. Pe core.tixello.com</h3>
<p>Folosim doar cookie-uri strict necesare, pentru care nu e nevoie de acordul tău:</p>
<table class="table">
    <thead><tr><th>Cookie</th><th>La ce folosește</th><th>Cât durează</th></tr></thead>
    <tbody>
        <tr><td>Sesiune (<code>{{ config('session.cookie') }}</code>)</td><td>Te ține autentificat și păstrează pașii înscrierii</td><td>{{ config('session.lifetime') }} de minute de inactivitate</td></tr>
        <tr><td><code>XSRF-TOKEN</code></td><td>Protecție împotriva cererilor falsificate</td><td>Cât sesiunea</td></tr>
        <tr><td><code>remember_web_*</code></td><td>Doar dacă bifezi „ține-mă minte” la autentificare</td><td>Până te deconectezi</td></tr>
        <tr><td>Cookie-uri Cloudflare (de exemplu <code>__cf_bm</code>)</td><td>Protecție împotriva roboților și a atacurilor</td><td>Până la 30 de minute</td></tr>
    </tbody>
</table>
<p>Paginile încarcă fonturi de la Google Fonts și unele biblioteci de la rețele publice de livrare (unpkg.com, cdn.jsdelivr.net). Browserul tău le trimite, tehnic, adresa IP. Nu folosim pe core.tixello.com instrumente de analiză sau de publicitate.</p>
<h3>5.2. Pe site-urile de bilete ale organizatorilor</h3>
<p>Site-urile de bilete găzduite de noi pot folosi, dacă organizatorul le pornește, instrumente de analiză și de publicitate (Google Analytics, Meta Pixel, TikTok Pixel și altele). Acestea pornesc doar după ce îți dai acordul în bara de cookie-uri a site-ului, unde îl poți și retrage.</p>

<h2 id="destinatari">6. Cui transmitem datele</h2>
<p>Nu vindem și nu închiriem date personale. Le transmitem doar:</p>
<ul>
    <li><strong>furnizorilor noștri</strong> (persoane împuternicite), din lista de mai jos, cu contracte care îi obligă să protejeze datele;</li>
    <li><strong>organizatorului</strong> evenimentului, pentru datele cumpărătorilor lui;</li>
    <li><strong>procesatorilor de plăți</strong> aleși de organizator, care prelucrează plata ca operatori independenți, conform propriilor politici;</li>
    <li><strong>autorităților</strong> (de exemplu ANAF sau instanțe), când legea o cere;</li>
    <li><strong>consultanților</strong> noștri (contabil, auditor, avocat), obligați la confidențialitate;</li>
    <li>unui eventual <strong>cumpărător al activității</strong> Tixello, cu aceleași garanții de protecție, și doar cu informarea ta.</li>
</ul>
<h3>Furnizorii folosiți pentru orice cont</h3>
<table class="table">
    <thead><tr><th>Furnizor</th><th>Pentru ce</th><th>Unde</th></tr></thead>
    <tbody>
        <tr><td>{{ config('legal.hosting_provider') === 'furnizorul nostru de servere' ? 'Furnizorul de găzduire' : config('legal.hosting_provider') }}</td><td>Serverele pe care rulează aplicația și baza de date</td><td>{{ config('legal.hosting_location') }}</td></tr>
        @foreach (array_slice(config('legal.subprocessors.always'), 1) as $p)
            <tr><td>{{ $p['name'] }}</td><td>{{ $p['purpose'] }}</td><td>{{ $p['location'] }}</td></tr>
        @endforeach
    </tbody>
</table>
<h3>Furnizori folosiți doar dacă organizatorul pornește funcția</h3>
<table class="table">
    <thead><tr><th>Furnizor</th><th>Pentru ce</th><th>Unde</th></tr></thead>
    <tbody>
        @foreach (config('legal.subprocessors.optional') as $p)
            <tr><td>{{ $p['name'] }}</td><td>{{ $p['purpose'] }}</td><td>{{ $p['location'] }}</td></tr>
        @endforeach
    </tbody>
</table>

<h2 id="transferuri">7. Transferuri în afara Spațiului Economic European</h2>
<p>Serverele principale sunt în Uniunea Europeană. Unii furnizori, de exemplu Cloudflare, Stripe, Meta, Google, Twilio sau Zoom, pot prelucra date și în afara SEE, mai ales în SUA. Atunci transferul are loc pe baza deciziei de adecvare UE–SUA (EU-US Data Privacy Framework), pentru furnizorii certificați, sau a clauzelor contractuale standard aprobate de Comisia Europeană, cu măsuri suplimentare unde e nevoie. La cerere îți trimitem o copie a garanțiilor folosite.</p>

<h2 id="pastrare">8. Cât păstrăm datele</h2>
<table class="table">
    <thead><tr><th>Date</th><th>Cât le păstrăm</th></tr></thead>
    <tbody>
        <tr><td>Contul clientului, contractul și dovada acceptării termenilor</td><td>Cât e activ contul, apoi 3 ani (termenul general de prescripție), pentru apărarea în caz de litigiu</td></tr>
        <tr><td>Facturi și documente contabile</td><td>Termenele din legislația contabilă și fiscală (Legea nr. 82/1991), de regulă între 5 și 10 ani</td></tr>
        <tr><td>Înscrieri începute și netrimise</td><td>Cel mult 12 luni de la ultima activitate</td></tr>
        <tr><td>Codul de confirmare a emailului</td><td>30 de minute; nu rămâne în jurnale</td></tr>
        <tr><td>Datele cumpărătorilor prelucrate pentru organizator</td><td>Cât decide organizatorul, cât timp contul lui e activ; după încetare, 30 de zile pentru export, apoi ștergere în cel mult 90 de zile, cu excepția celor pe care legea ne obligă să le păstrăm</td></tr>
        <tr><td>Contul de cumpărător Tixello și acordurile de informare</td><td>Până ștergi contul sau îți retragi acordul</td></tr>
        <tr><td>Jurnalul emailurilor trimise</td><td>Cel mult 24 de luni</td></tr>
        <tr><td>Jurnalul de activitate din panou (acțiuni, exporturi)</td><td>Cât e activ contul, apoi 3 ani</td></tr>
        <tr><td>Jurnale tehnice ale serverului</td><td>Se rotesc automat; cel mult 12 luni</td></tr>
        <tr><td>Copii de siguranță ale bazei de date</td><td>{{ config('legal.backup_retention_days') }} zile, prin rotație</td></tr>
    </tbody>
</table>
<p>După aceste termene ștergem datele sau le anonimizăm, astfel încât să nu mai poată fi legate de o persoană.</p>

<h2 id="securitate">9. Cum protejăm datele</h2>
<ul>
    <li>conexiuni criptate (HTTPS) pentru toate paginile și interfețele;</li>
    <li>parole salvate doar criptat, cu algoritmi pentru care parola originală nu poate fi recuperată; datele de identitate ale persoanelor fizice și cheile de acces la servicii, criptate în baza de date;</li>
    <li>acces la date pe roluri, doar pentru cei care au nevoie de el, cu jurnal al acțiunilor și al exporturilor;</li>
    <li>protecție împotriva atacurilor prin Cloudflare și limitarea numărului de cereri pe formulare;</li>
    <li>copii de siguranță zilnice ale bazei de date;</li>
    <li>verificarea manuală a fiecărui cont nou înainte de a putea vinde.</li>
</ul>
<p>Nicio măsură nu elimină complet riscurile. Dacă apare o încălcare a securității care îți poate afecta drepturile, anunțăm autoritatea în 72 de ore și te anunțăm și pe tine, când legea o cere. Organizatorii sunt anunțați în cel mult 48 de ore pentru datele cumpărătorilor lor.</p>

<h2 id="drepturi">10. Drepturile tale</h2>
<p>Conform GDPR, ai dreptul:</p>
<ul>
    <li>să afli dacă prelucrăm date despre tine și să primești o copie (dreptul de acces);</li>
    <li>să ceri corectarea datelor greșite (rectificare) și ștergerea lor, când nu mai avem un temei să le păstrăm;</li>
    <li>să ceri restricționarea prelucrării și să primești datele într-un format structurat, ca să le duci altundeva (portabilitate);</li>
    <li>să te opui prelucrărilor bazate pe interesul nostru legitim, inclusiv celor de marketing, oricând;</li>
    <li>să-ți retragi acordul oricând, pentru prelucrările bazate pe acord;</li>
    <li>să nu faci obiectul unei decizii bazate exclusiv pe prelucrare automată care să producă efecte juridice asupra ta. Noi nu luăm astfel de decizii: fiecare cont nou e verificat de un om.</li>
</ul>
<p>Scrie-ne la <a href="mailto:{{ $c['privacy_email'] }}">{{ $c['privacy_email'] }}</a>. Îți răspundem în cel mult o lună. Termenul se poate prelungi cu două luni pentru cereri complexe, caz în care te anunțăm. Putem cere informații care să confirme că cererea e a ta. Pentru datele prelucrate în numele unui organizator, îi transmitem lui cererea și îl ajutăm să răspundă.</p>
<p>Dacă crezi că îți încălcăm drepturile, poți depune o plângere la {{ $authority['name'] }}, {{ $authority['address'] }}, <a href="{{ $authority['website'] }}" target="_blank" rel="noopener">{{ str_replace('https://', '', $authority['website']) }}</a>. Te rugăm să ne scrii întâi nouă, ca să încercăm să rezolvăm.</p>

<h2 id="minori">11. Minori</h2>
<p>Conturile de client sunt doar pentru persoane majore. Biletele pot fi cumpărate și pentru minori, dar cumpărătorul trebuie să fi împlinit 16 ani sau să aibă acordul părintelui. Organizatorul răspunde de regulile de vârstă ale evenimentului. Dacă afli că am primit date despre un copil fără acordul necesar, scrie-ne și le ștergem.</p>

<h2 id="modificari">12. Modificări</h2>
<p>Putem actualiza această politică, de exemplu când schimbăm furnizori sau adăugăm funcții. Versiunea și data sunt sus, pe pagină. Despre schimbările importante îi anunțăm pe clienți pe email, cu cel puțin 15 zile înainte. Schimbările de furnizori îi privesc pe organizatori, ca operatori.</p>

<h2 id="contact">13. Contact</h2>
<p>Pentru orice întrebare despre datele personale: <a href="mailto:{{ $c['privacy_email'] }}">{{ $c['privacy_email'] }}</a>@if ($c['address']), sau prin poștă la {{ $c['address'] }}, în atenția „Protecția datelor”@endif.</p>
@endsection
