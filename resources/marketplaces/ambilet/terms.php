<?php
/**
 * Terms and Conditions Page - Ambilet Marketplace
 * Legal terms, privacy policy, and conditions of use
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Termeni și Condiții — Ambilet";
$pageDescription = "Termenii și condițiile de utilizare a platformei Ambilet. Citește cu atenție înainte de a folosi serviciile noastre.";
$bodyClass = 'page-terms';
$transparentHeader = true;

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="py-16 text-center bg-gradient-to-br from-gray-900 to-gray-700">
    <div class="max-w-[700px] mx-auto px-6">
        <h1 class="text-[42px] font-extrabold text-white mb-4">Termeni și Condiții</h1>
        <p class="text-lg leading-relaxed text-white/70">Vă rugăm să citiți cu atenție termenii și condițiile de utilizare a platformei Ambilet înainte de a folosi serviciile noastre.</p>
        <div class="flex items-center justify-center gap-6 mt-5 text-sm text-white/60">
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Ultima actualizare: 15 Ianuarie 2025
            </span>
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                ~15 minute de citit
            </span>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-[1100px] mx-auto px-6 py-12 grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-12">
    <!-- Sidebar Navigation -->
    <aside class="lg:sticky lg:top-24 lg:h-fit">
        <div class="p-5 bg-white border border-gray-200 rounded-2xl">
            <div class="px-3 mb-4 text-xs font-bold tracking-wide text-gray-400 uppercase">Cuprins</div>
            <nav>
                <ul class="space-y-1">
                    <li><a href="#introducere" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">1. Introducere</a></li>
                    <li><a href="#definitii" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">2. Definiții</a></li>
                    <li><a href="#cont" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">3. Contul de utilizator</a></li>
                    <li><a href="#achizitii" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">4. Achiziția biletelor</a></li>
                    <li><a href="#plati" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">5. Plăți și facturare</a></li>
                    <li><a href="#rambursari" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">6. Rambursări</a></li>
                    <li><a href="#obligatii" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">7. Obligații utilizator</a></li>
                    <li><a href="#proprietate" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">8. Proprietate intelectuală</a></li>
                    <li><a href="#raspundere" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">9. Limitarea răspunderii</a></li>
                    <li><a href="#contact" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">10. Contact</a></li>
                </ul>
            </nav>
        </div>

        <div class="pt-6 mt-6 border-t border-gray-200">
            <div class="mb-3 text-xs font-bold tracking-wide text-gray-400 uppercase">Alte documente legale</div>
            <div class="space-y-2">
                <a href="/confidentialitate" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Politica de confidențialitate
                </a>
                <a href="/cookies" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="4"/>
                    </svg>
                    Politica de cookies
                </a>
                <a href="/gdpr" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Drepturile GDPR
                </a>
            </div>
        </div>
    </aside>

    <!-- Content -->
    <article class="bg-white rounded-[20px] p-12 border border-gray-200">
        <!-- Section 1 -->
        <section id="introducere" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">1</span>
                Introducere
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Bine ați venit pe Ambilet! Acești Termeni și Condiții ("Termeni") guvernează utilizarea site-ului nostru web, a aplicațiilor mobile și a serviciilor conexe (colectiv, "Platforma"). Prin accesarea sau utilizarea Platformei noastre, sunteți de acord să fiți obligat de acești Termeni.</p>

            <div class="p-5 my-6 border-l-4 bg-red-50 border-primary rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-red-900 mb-0"><strong class="text-primary">Important:</strong> Dacă nu sunteți de acord cu acești Termeni, vă rugăm să nu utilizați Platforma noastră. Continuarea utilizării constituie acceptarea integrală a acestor Termeni.</p>
            </div>

            <p class="text-[15px] leading-[1.8] text-gray-600">Ambilet este o platformă de ticketing care conectează organizatorii de evenimente cu publicul, facilitând vânzarea și distribuția biletelor pentru diverse tipuri de evenimente: concerte, festivaluri, spectacole de teatru, evenimente sportive și multe altele.</p>
        </section>

        <!-- Section 2 -->
        <section id="definitii" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">2</span>
                Definiții
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">În cadrul acestor Termeni, următorii termeni vor avea semnificațiile de mai jos:</p>

            <table class="w-full my-6 text-sm border-collapse">
                <thead>
                    <tr>
                        <th class="p-3.5 text-left border-b border-gray-200 bg-gray-50 font-semibold text-gray-900">Termen</th>
                        <th class="p-3.5 text-left border-b border-gray-200 bg-gray-50 font-semibold text-gray-900">Definiție</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Platformă</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Site-ul web Ambilet, aplicațiile mobile și toate serviciile conexe</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Utilizator</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Orice persoană care accesează sau utilizează Platforma</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Organizator</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Persoana fizică sau juridică care creează și gestionează evenimente pe Platformă</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Bilet</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Documentul electronic care conferă dreptul de acces la un eveniment</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Eveniment</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Orice activitate listată pe Platformă pentru care se vând bilete</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Section 3 -->
        <section id="cont" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">3</span>
                Contul de utilizator
            </h2>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">3.1 Crearea contului</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru a achiziționa bilete, trebuie să vă creați un cont pe Platformă. Sunteți responsabil pentru:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Furnizarea de informații exacte și actualizate</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Păstrarea confidențialității datelor de autentificare</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Toate activitățile desfășurate prin intermediul contului dumneavoastră</li>
            </ul>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">3.2 Eligibilitate</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru a utiliza Platforma, trebuie să aveți cel puțin 18 ani sau vârsta majoratului în jurisdicția dumneavoastră. Minorii pot utiliza Platforma doar cu consimțământul părintelui sau tutorelui legal.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">3.3 Suspendarea contului</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600">Ne rezervăm dreptul de a suspenda sau închide contul dumneavoastră în cazul încălcării acestor Termeni sau în cazul activităților frauduloase.</p>
        </section>

        <!-- Section 4 -->
        <section id="achizitii" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">4</span>
                Achiziția biletelor
            </h2>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">4.1 Procesul de achiziție</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Achiziția biletelor se face exclusiv prin intermediul Platformei. Fiecare achiziție este supusă disponibilității și confirmării plății. Un bilet este considerat achiziționat doar după primirea confirmării prin email.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">4.2 Prețuri și taxe</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Toate prețurile afișate includ TVA acolo unde este cazul. Taxele de serviciu ale Ambilet sunt afișate separat în procesul de checkout. Prețul final include:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Prețul biletului stabilit de organizator</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Taxa de serviciu Ambilet</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Taxa de procesare a plății (unde este cazul)</li>
            </ul>

            <div class="p-5 my-6 border-l-4 bg-sky-50 border-sky-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-sky-900 mb-0"><strong>Notă:</strong> Prețurile pot varia în funcție de tipul biletului, data achiziției și promoțiile active. Verificați întotdeauna prețul final înainte de finalizarea comenzii.</p>
            </div>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">4.3 Limitări</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600">Organizatorii pot impune limite privind numărul de bilete care pot fi achiziționate per comandă sau per utilizator. Aceste limitări sunt afișate pe pagina evenimentului.</p>
        </section>

        <!-- Section 5 -->
        <section id="plati" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">5</span>
                Plăți și facturare
            </h2>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">5.1 Metode de plată acceptate</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Acceptăm următoarele metode de plată:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Carduri de credit/debit (Visa, Mastercard, American Express)</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Transfer bancar</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Plata în rate prin parteneri (unde este disponibil)</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Portofele digitale (Apple Pay, Google Pay)</li>
            </ul>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">5.2 Securitatea plăților</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Toate tranzacțiile sunt procesate prin protocoale securizate (SSL/TLS). Nu stocăm datele complete ale cardurilor pe serverele noastre. Plățile sunt procesate de parteneri autorizați PCI-DSS.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">5.3 Facturare</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600">După fiecare achiziție, veți primi automat pe email factura fiscală. Facturile sunt disponibile și în contul dumneavoastră, în secțiunea "Comenzile mele".</p>
        </section>

        <!-- Section 6 -->
        <section id="rambursari" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">6</span>
                Politica de rambursare
            </h2>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">6.1 Rambursări pentru evenimente anulate</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">În cazul anulării unui eveniment de către organizator, aveți dreptul la rambursarea integrală a sumei plătite, inclusiv taxele de serviciu. Rambursarea se face automat în termen de 14 zile lucrătoare.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">6.2 Evenimente reprogramate</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru evenimentele reprogramate, biletele rămân valabile pentru noua dată. Dacă nu puteți participa la noua dată, puteți solicita rambursarea în termen de 14 zile de la anunțul reprogramării.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">6.3 Renunțări voluntare</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">În cazul renunțării voluntare la participare, politica de rambursare este stabilită de fiecare organizator în parte. Consultați descrierea evenimentului pentru detalii specifice.</p>

            <div class="p-5 my-6 border-l-4 bg-red-50 border-primary rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-red-900 mb-0"><strong class="text-primary">Atenție:</strong> Biletele nu pot fi returnate sau rambursate în ultimele 48 de ore înainte de eveniment, cu excepția cazurilor în care legea impune altfel.</p>
            </div>
        </section>

        <!-- Section 7 -->
        <section id="obligatii" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">7</span>
                Obligațiile utilizatorului
            </h2>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Ca utilizator al Platformei, vă angajați să:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Furnizați informații exacte și complete la crearea contului</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Nu revândeți biletele la prețuri mai mari decât cele de achiziție</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Nu utilizați Platforma în scopuri ilegale sau neautorizate</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Nu încercați să accesați neautorizat sistemele noastre</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Respectați regulamentele specifice fiecărui eveniment</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Nu creați conturi false sau multiple pentru a eluda restricțiile</li>
            </ul>
        </section>

        <!-- Section 8 -->
        <section id="proprietate" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">8</span>
                Proprietate intelectuală
            </h2>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Toate elementele Platformei, inclusiv dar fără a se limita la logo-uri, texte, imagini, design, cod sursă și funcționalități, sunt proprietatea Ambilet sau a licențiatorilor săi și sunt protejate de legile privind drepturile de autor și proprietatea intelectuală.</p>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Nu aveți dreptul să:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Copiați, modificați sau distribuiți conținutul Platformei</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Utilizați marca Ambilet fără autorizație scrisă</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Faceți inginerie inversă asupra software-ului nostru</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Extrageți sistematic date de pe Platformă</li>
            </ul>
        </section>

        <!-- Section 9 -->
        <section id="raspundere" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">9</span>
                Limitarea răspunderii
            </h2>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Ambilet acționează ca intermediar între organizatori și participanți. Nu suntem responsabili pentru:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Calitatea sau desfășurarea evenimentelor</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Modificările sau anulările decise de organizatori</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Pierderile indirecte sau consecvențiale</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Întreruperile tehnice cauzate de factori externi</li>
            </ul>

            <p class="text-[15px] leading-[1.8] text-gray-600">Răspunderea noastră totală nu va depăși valoarea biletelor achiziționate în ultimele 12 luni.</p>
        </section>

        <!-- Section 10 -->
        <section id="contact" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">10</span>
                Contact
            </h2>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru întrebări sau nelămuriri privind acești Termeni, ne puteți contacta la:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Email:</strong> <a href="mailto:legal@ambilet.ro" class="font-medium text-primary">legal@ambilet.ro</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Telefon:</strong> <a href="tel:+40312345678" class="font-medium text-primary">+40 31 234 5678</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Adresă:</strong> Str. Exemplu nr. 123, București, România</li>
            </ul>

            <p class="text-[15px] leading-[1.8] text-gray-600">Timpul mediu de răspuns este de 2-3 zile lucrătoare.</p>
        </section>

        <!-- Print Section -->
        <div class="flex flex-col items-center justify-between gap-4 pt-8 mt-8 border-t border-gray-200 sm:flex-row">
            <div class="text-[13px] text-gray-400">
                Ultima actualizare: 15 Ianuarie 2025 • Versiunea 2.1
            </div>
            <button onclick="window.print()" class="flex items-center gap-2 px-5 py-3 text-sm font-semibold text-gray-600 transition-all border border-gray-200 rounded-lg bg-gray-50 hover:bg-gray-900 hover:border-gray-900 hover:text-white">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Printează documentul
            </button>
        </div>
    </article>
</main>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';

// Page-specific scripts
$scriptsExtra = <<<'SCRIPTS'
<script>
const TermsPage = {
    init() {
        this.initSmoothScroll();
        this.initActiveNavigation();
    },

    initSmoothScroll() {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    const offset = 100;
                    const top = target.getBoundingClientRect().top + window.scrollY - offset;
                    window.scrollTo({ top, behavior: 'smooth' });
                }
            });
        });
    },

    initActiveNavigation() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    navLinks.forEach(link => {
                        link.classList.remove('bg-red-50', 'text-primary', 'font-semibold');
                        if (link.getAttribute('href') === `#${entry.target.id}`) {
                            link.classList.add('bg-red-50', 'text-primary', 'font-semibold');
                        }
                    });
                }
            });
        }, { rootMargin: '-20% 0px -70% 0px' });

        sections.forEach(section => observer.observe(section));
    }
};

document.addEventListener('DOMContentLoaded', () => TermsPage.init());
</script>
<style>
@media print {
    header, .lg\\:sticky, footer, .print-section button { display: none !important; }
    main { grid-template-columns: 1fr !important; padding: 0 !important; }
    article { border: none !important; box-shadow: none !important; padding: 0 !important; }
    .page-hero { background: none !important; color: #1E293B !important; padding: 20px 0 !important; }
    .page-hero h1 { color: #1E293B !important; }
    .page-hero p, .page-hero .flex { color: #64748B !important; }
}
</style>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
