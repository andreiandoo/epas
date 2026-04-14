<?php
/**
 * Organizer Guide Page - Ambilet Marketplace
 * Guide for event organizers: requirements, fiscal obligations, taxes
 */
$pageCacheTTL = 1800; // 30 minutes (static page)
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Ghid Organizatori — Ambilet";
$pageDescription = "Ghid complet pentru organizatorii de evenimente: cum urci un eveniment pe AmBilet.ro, obligații fiscale, impozite, timbre și drepturi de autor.";
$bodyClass = 'page-organizer-guide';
$transparentHeader = false;

// Include head
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="py-16 text-center pt-28 bg-gradient-to-br from-gray-900 to-gray-700">
    <div class="max-w-[700px] mx-auto px-6">
        <div class="w-[72px] h-[72px] bg-primary/20 border border-primary/30 rounded-[20px] flex items-center justify-center mx-auto mb-6">
            <svg class="text-red-400 w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <h1 class="text-[42px] mobile:text-[28px] font-extrabold text-white mb-4">Ghid Organizatori</h1>
        <p class="text-lg leading-relaxed text-white/90">Tot ce trebuie să știi pentru a organiza un eveniment pe AmBilet.ro: de la listarea evenimentului, la obligațiile fiscale și impozitele aferente.</p>
        <div class="flex flex-col items-center justify-center gap-2 mt-5 text-sm sm:flex-row sm:gap-6 text-white/90">
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Ultima actualizare: Martie 2026
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
        <div class="hidden p-5 bg-white border border-gray-200 lg:block rounded-2xl">
            <div class="px-3 mb-4 text-xs font-bold tracking-wide text-gray-400 uppercase">Cuprins</div>
            <nav>
                <ul class="space-y-1">
                    <li><a href="#urcare-eveniment" class="block px-3 py-3 text-sm font-medium transition-colors rounded-lg nav-link text-primary bg-red-50">1. Urcarea evenimentului</a></li>
                    <li><a href="#scanare-bilete" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">2. Scanarea biletelor</a></li>
                    <li><a href="#inainte-eveniment" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">3. Înainte de eveniment</a></li>
                    <li><a href="#informatii-bilete" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">4. Informații bilete</a></li>
                    <li><a href="#dupa-eveniment" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">5. După eveniment</a></li>
                    <li><a href="#calcul-impozit" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">6. Calculul impozitului</a></li>
                    <li><a href="#tva" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">7. TVA</a></li>
                    <li><a href="#timbru-monumente" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">8. Timbrul monumentelor</a></li>
                    <li><a href="#taxa-timbru" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">9. Taxa de timbru</a></li>
                    <li><a href="#timbru-divertisment" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">10. Timbrul de divertisment</a></li>
                    <li><a href="#drepturi-autor" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">11. Drepturi de autor</a></li>
                </ul>
            </nav>
        </div>

        <div class="pt-6 mt-6 border-t border-gray-200 lg:mt-6 lg:pt-6 lg:border-t">
            <div class="mb-3 text-xs font-bold tracking-wide text-gray-400 uppercase">Linkuri utile</div>
            <div class="space-y-2">
                <a href="/contact" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Contact
                </a>
                <a href="/termeni" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Termeni și Condiții
                </a>
                <a href="/politica-retur" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="1 4 1 10 7 10"/>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                    </svg>
                    Politica de retur
                </a>
            </div>
        </div>
    </aside>

    <!-- Content -->
    <article class="bg-white rounded-[20px] p-8 lg:p-12 border border-gray-200">

        <!-- Section 1: Urcarea evenimentului -->
        <section id="urcare-eveniment" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">1</span>
                Urcarea evenimentului pe AmBilet.ro
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru urcarea unui eveniment pe platforma AmBilet.ro avem nevoie de următoarele:</p>

            <!-- Contract -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    Semnarea contractului
                </h4>
                <p class="text-[15px] leading-[1.8] text-gray-600">Semnarea unui contract direct pe platforma AmBilet.ro. Este necesară o copie CUI al organizatorului, împreună cu o copie buletin al celui care deține firma organizatoare, sau al președintelui în cazul unei asociații / fundații, pentru a redacta contractul în baza căruia se vor pune biletele în vânzare.</p>
            </div>

            <!-- Informatii eveniment -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Informații despre eveniment
                </h4>
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-3">Următoarele informații trebuie trimise la adresa <a href="mailto:contact@ambilet.ro" class="font-medium text-primary hover:underline">contact@ambilet.ro</a>:</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Denumire / titlu eveniment</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Descriere eveniment</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Locația de desfășurare + adresa</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Data de început și data de sfârșit</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Prețul / prețurile biletelor + stocuri</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Harta locației (pentru locuri numerotate)</span>
                </div>
            </div>

            <!-- Afise -->
            <div class="p-5 my-5 border-l-4 border-blue-500 bg-blue-50 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-blue-900 mb-0">
                    <strong class="text-blue-700">Afișe necesare:</strong>
                    Afiș orizontal <strong>obligatoriu</strong> (dimensiune minimă: 680 × 357 px, format .jpg sau .png) și/sau afiș vertical — opțional (dimensiune minimă: 600 × 840 px).
                </p>
            </div>
        </section>

        <!-- Section 2: Scanarea biletelor -->
        <section id="scanare-bilete" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">2</span>
                Scanarea biletelor
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600">Aplicația de mobil pentru scanat bilete se descarcă din panoul de control al organizatorului.</p>
        </section>

        <!-- Section 3: Înainte de eveniment -->
        <section id="inainte-eveniment" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">3</span>
                Înainte de eveniment
            </h2>

            <div class="p-5 my-6 border-l-4 bg-amber-50 border-amber-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-amber-900 mb-0">
                    <strong class="text-amber-700">Important:</strong>
                    Legislația din România obligă organizatorii de evenimente să fiscalizeze biletele la evenimentele organizate de către aceștia. Această fiscalizare poate fi făcută, la cerere, și de către AmBilet.ro în numele organizatorului.
                </p>
            </div>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Indiferent dacă evenimentul este înregistrat de către organizatorul evenimentului sau de către AmBilet.ro, pașii sunt aceeași:</p>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Se depune o <strong>Cerere de înregistrare / vizare</strong> la autoritatea competentă (Direcția Locală de Taxe și Impozite — DGITL — din orașul / sectorul în care urmează să fie organizat evenimentul), cu numărul maxim de bilete ce vor fi puse în vânzare.</p>

            <div class="p-5 my-5 border-l-4 border-red-400 bg-red-50 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-red-900 mb-0">
                    <strong class="text-red-700">Atenție:</strong>
                    În cazul în care contribuabilii organizează spectacole în raza teritorială de competență a altor autorități ale administrației publice locale decât cele de la domiciliul sau sediul lor, acestora le revine obligația de a înregistra abonamentele și biletele de intrare la compartimentele de specialitate ale autorităților publice locale în a căror rază teritorială se desfășoară spectacolele.
                </p>
            </div>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Cererea trebuie să fie depusă până în ziua evenimentului inclusiv, fizic (direct la ghișeul DGITL) sau online (la adresa de email sau platforma DGITL indicate de către AmBilet.ro). Există localități unde se cere expres ca înregistrarea / vizarea biletelor să fie făcută <strong>înainte</strong> ca acestea să fie puse în vânzare.</p>

            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-0">
                    <strong>Recomandare:</strong> Păstrați / arhivați toată corespondența avută către și de la DGITL. Se va întâmpla destul de des să vi se ceară explicații aferente.
                </p>
            </div>

            <div class="p-5 my-5 border-l-4 bg-amber-50 border-amber-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-amber-900 mb-0">
                    <strong class="text-amber-700">Important:</strong>
                    Dacă după ce ați înregistrat cererea doriți să adăugați categorii noi de preț, este necesară depunerea unei noi cereri care să includă noile tarife.
                </p>
            </div>
        </section>

        <!-- Section 4: Informații bilete -->
        <section id="informatii-bilete" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">4</span>
                Informații despre bilete
            </h2>

            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Sistem propriu de numerotare
                </h4>
                <p class="text-[15px] leading-[1.8] text-gray-600">Organizatorii (posesorii de CUI) pot emite bilete și abonamente de intrare la spectacole prin sistem propriu de înscriere și numerotare, folosind programul informatic propriu, care să conțină informații minime obligatorii conform Hotărârii Guvernului nr. 846/2002.</p>
            </div>

            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Biletul ca factură simplificată
                </h4>
                <p class="text-[15px] leading-[1.8] text-gray-600">Biletul de concert este o factură simplificată și poate fi înregistrată direct în contabilitatea clientului. Dacă clientul dorește o factură „standard", organizatorul are obligația de a emite o factură pentru suma totală a biletelor vândute, iar pentru biletele care au taxa de procesare adăugată peste prețul biletului, AmBilet.ro va emite clientului o factură pentru suma respectivă.</p>
            </div>

            <h3 class="mt-6 mb-4 text-lg font-bold text-gray-900">Informații minime obligatorii pe bilet</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-3">Conform legislației actuale, informațiile minime obligatorii ce trebuie să existe pe biletul de spectacol sunt:</p>
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="inline-flex items-center justify-center flex-shrink-0 w-5 h-5 text-xs font-bold rounded bg-primary/10 text-primary">a</span>Numele organizatorului de spectacole</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="inline-flex items-center justify-center flex-shrink-0 w-5 h-5 text-xs font-bold rounded bg-primary/10 text-primary">b</span>CIF / CUI al organizatorului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="inline-flex items-center justify-center flex-shrink-0 w-5 h-5 text-xs font-bold rounded bg-primary/10 text-primary">c</span>Numele spectacolului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="inline-flex items-center justify-center flex-shrink-0 w-5 h-5 text-xs font-bold rounded bg-primary/10 text-primary">d</span>Data spectacolului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="inline-flex items-center justify-center flex-shrink-0 w-5 h-5 text-xs font-bold rounded bg-primary/10 text-primary">e</span>Tariful biletului (lei)</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="inline-flex items-center justify-center flex-shrink-0 w-5 h-5 text-xs font-bold rounded bg-primary/10 text-primary">f</span>Categoria locului (lojă, stal, etc.)</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="inline-flex items-center justify-center flex-shrink-0 w-5 h-5 text-xs font-bold rounded bg-primary/10 text-primary">g</span>Seria fiscală a biletului</span>
                </div>
            </div>

            <div class="p-5 my-5 border-l-4 border-red-400 bg-red-50 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-red-900 mb-0">
                    <strong class="text-red-700">Sancțiuni:</strong>
                    Încălcarea normelor tehnice privind tipărirea, înregistrarea, vânzarea, evidența și gestionarea abonamentelor și biletelor de intrare la spectacole constituie contravenție și se sancționează cu amendă de la 325 lei la 1.578 lei.
                </p>
            </div>
        </section>

        <!-- Section 5: După eveniment -->
        <section id="dupa-eveniment" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">5</span>
                După eveniment
            </h2>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Conform codului fiscal, orice persoană care organizează o manifestare artistică, o competiție sportivă sau altă activitate distractivă în România are obligația de a plăti <strong>impozitul pe spectacole</strong>. Acest impozit se face în baza decontului de bilete vândute depus la Direcția de Taxe și Impozite Locale acolo unde a avut loc spectacolul.</p>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Ca atare, după eveniment organizatorul este obligat să depună <strong>decontul și procesul verbal de anulare a biletelor nevândute</strong>. Aceste acte vor fi obținute de la AmBilet.ro și depuse (fizic sau online) de către organizator, sau la cerere de către AmBilet.ro.</p>

            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-0">
                    <strong>Depunere fizică (la ghișeu):</strong> Documentele trebuie depuse în dublu exemplar la Direcția de Taxe și Impozite Locale. La documente trebuie atașată și o împuternicire din partea firmei organizatoare împreună cu copie CI a persoanei care depune actele.
                </p>
            </div>

            <div class="p-5 my-6 border-l-4 bg-amber-50 border-amber-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-amber-900 mb-2">
                    <strong class="text-amber-700">Termen limită:</strong>
                    Documentele se depun înainte de data de <strong>10 a lunii următoare</strong> celei în care se organizează spectacolul, iar impozitul se plătește până la aceeași dată. După această dată, administrațiile locale percep penalități de întârziere.
                </p>
                <p class="text-[15px] leading-[1.8] text-amber-900 mb-0">
                    <strong>Exemplu:</strong> Dacă un eveniment are loc pe 5 februarie, actele trebuie depuse înainte de 10 martie, iar impozitul trebuie plătit tot până pe 10 martie.
                </p>
            </div>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Impozitul pe spectacole se plătește la bugetul local al unității administrativ-teritoriale în raza căreia are loc manifestarea.</p>

            <div class="p-5 my-5 border-l-4 bg-emerald-50 border-emerald-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-emerald-900 mb-0">
                    <strong class="text-emerald-700">Scutire:</strong>
                    Spectacolele organizate în scopuri umanitare sunt scutite de la plata impozitului pe spectacole. Organizatorul are obligația de a prezenta, în momentul în care depune cererea de înregistrare a biletelor, un contract din care să rezulte că evenimentul este organizat în scop umanitar. Contractele se vor înregistra la compartimentele de specialitate ale autorităților administrației publice locale, prealabil organizării evenimentelor.
                </p>
            </div>
        </section>

        <!-- Section 6: Calculul impozitului -->
        <section id="calcul-impozit" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">6</span>
                Calculul impozitului pe spectacole
            </h2>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Impozitul pe spectacole se calculează prin aplicarea cotei de impozit la suma încasată din vânzarea biletelor de intrare și a abonamentelor. Consiliile locale hotărăsc cota de impozit după cum urmează:</p>

            <div class="my-5 overflow-x-auto border border-gray-200 rounded-xl">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-5 py-3 font-bold text-left text-gray-900">Tipul spectacolului</th>
                            <th class="px-5 py-3 font-bold text-right text-gray-900">Cotă maximă</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="px-5 py-3 text-gray-600">Teatru, balet, operă, operetă, concert filarmonic sau altă manifestare muzicală (concerte), film la cinematograf, spectacol de circ, competiție sportivă internă sau internațională</td>
                            <td class="px-5 py-3 font-bold text-right text-primary whitespace-nowrap">până la 2%</td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3 text-gray-600">Orice altă manifestare artistică</td>
                            <td class="px-5 py-3 font-bold text-right text-primary whitespace-nowrap">până la 5%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-0">
                    <strong>Notă:</strong> În anumite zone din România impozitul este 0%, dar în continuare se cere înregistrarea evenimentelor și depunerea deconturilor. Direcțiile de taxe care au o altă cotă de impozit: <strong>Timișoara 0%</strong>, <strong>Iași 0.1%</strong>, <strong>Vaslui și Pitești 2.4%</strong>.
                </p>
            </div>
        </section>

        <!-- Section 7: TVA -->
        <section id="tva" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">7</span>
                TVA (taxa pe valoarea adăugată)
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600">Asupra biletelor / abonamentelor vândute pentru târguri, expoziții și evenimente culturale, evenimente sportive, cinematografe etc., se aplică un TVA de <strong>21%</strong>. TVA-ul este inclus în prețul biletului.</p>
        </section>

        <!-- Section 9: Timbrul monumentelor istorice -->
        <section id="timbru-monumente" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">8</span>
                Timbrul monumentelor istorice
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Timbrul monumentelor istorice se aplică biletelor de intrare pentru manifestările culturale, sportive sau de agrement, târguri și expoziții desfășurate în spații situate în <strong>zona de protecție a monumentelor istorice</strong> sau zona învecinată monumentelor istorice.</p>
            <p class="text-[15px] leading-[1.8] text-gray-600">Timbrul monumentelor istorice este în cuantum de <strong>2%</strong> din toate încasările provenite din vânzarea de bilete.</p>
        </section>

        <!-- Section 9: Taxa de timbru -->
        <section id="taxa-timbru" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">9</span>
                Taxa de timbru (cinematografic, muzical, folcloric)
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Taxa de timbru se aplică încasărilor din biletele și abonamentele vândute la spectacol.</p>

            <!-- Timbrul cinematografic -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">Timbrul cinematografic — 2%</h4>
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-3">Toate persoanele fizice sau juridice, autorizate de către Centrul Național al Cinematografiei să organizeze spectacole cinematografice sau video în țară, sunt obligate să adauge la prețul de vânzare al biletului procentul de 2%. Pe fiecare bilet este obligatoriu trecută mențiunea <em>„Prețul include timbrul cinematografic"</em>.</p>
                <p class="text-sm text-gray-500"><strong>Beneficiari:</strong> Uniunea Cineaștilor din România (UCIR), Uniunea Autorilor și Realizatorilor de Film din România (U.A.R.F.)</p>
            </div>

            <!-- Timbrul muzical -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">Timbrul muzical — 5%</h4>
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-3">Timbrul muzical se determină prin aplicarea procentului de 5% la prețul unui bilet.</p>
                <p class="text-sm text-gray-500"><strong>Beneficiari:</strong> U.C.I.M.R., U.I.C.C.M., U.C.M.R., U.C.R.R.M.R.</p>
            </div>

            <!-- Timbrul teatral -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">Timbrul teatral — 5%</h4>
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-3">Timbrul teatral se percepe pentru spectacolele organizate în țară de persoanele juridice și persoanele fizice autorizate care produc spectacole cu artiști profesioniști din domeniul teatrului.</p>
                <p class="text-sm text-gray-500"><strong>Beneficiari:</strong> Uniunea Teatrală din România (UNITER)</p>
            </div>

            <!-- Timbrul folcloric -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">Timbrul folcloric — 5%</h4>
                <p class="text-[15px] leading-[1.8] text-gray-600 mb-3">Timbrul folcloric se percepe pentru toate spectacolele folclorice organizate în țară, inclusiv pentru spectacolele din cabarete, discoteci, baruri și restaurante, cu intrare pe bază de bilet, cu caracter exclusiv folcloric, precum și pentru înregistrările cu caracter folcloric.</p>
                <p class="text-sm text-gray-500"><strong>Beneficiari:</strong> U.C.M.R., U.C.I.M.R.</p>
            </div>
        </section>

        <!-- Section 10: Timbrul de divertisment -->
        <section id="timbru-divertisment" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">10</span>
                Timbrul de divertisment — 3%
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Timbrul de divertisment se percepe pentru spectacolele artistico-sportive, altele decât cele la care se aplică alte timbre, și pentru spectacolele de circ organizate în țară.</p>
            <p class="text-sm text-gray-500"><strong>Beneficiari:</strong> UNITER, U.C.M.R., U.C.I.M.R.</p>
        </section>

        <!-- Section 11: Drepturi de autor -->
        <section id="drepturi-autor" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">11</span>
                Drepturi de autor și drepturi conexe
            </h2>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Se aplică pentru:</p>
            <ul class="mb-4 space-y-2 text-[15px] leading-[1.8] text-gray-600 list-none">
                <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0 mt-2.5"></span>Totalitatea veniturilor obținute din: vânzarea de bilete, abonamente și din orice altă modalitate de tarifare a accesului publicului la spectacolul sau festivalul, din subvenții, din sponsorizări și din orice finanțare nerambursabilă.</li>
                <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0 mt-2.5"></span>În absența veniturilor, totalitatea bugetului de cheltuieli al spectacolului sau festivalului, reprezentat de cheltuielile precum cele cu onorariile artiștilor interpreți, cele pentru servicii și bunuri tehnice și sceno-tehnice, cu luminile, sonorizarea, scena, sala, decorurile, platforme, podiumuri, scaune, gradene, tribune, turnicheți, afișaje, artificii, spații închise sau deschise accesibile publicului sau artiștilor.</li>
            </ul>

            <div class="p-5 my-6 border-l-4 bg-amber-50 border-amber-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-amber-900 mb-0">
                    <strong class="text-amber-700">Important:</strong>
                    Pentru utilizarea operelor muzicale prin comunicare publică în spectacole și festivaluri este obligatorie încheierea prealabilă a <strong>autorizației licență neexclusivă</strong>, cu organismul de gestiune colectivă a drepturilor patrimoniale de autor de opere muzicale, în schimbul plății remunerației reglementate.
                </p>
            </div>

            <p class="text-[15px] leading-[1.8] text-gray-600 mb-3">Utilizatorul este obligat să depună cererea de încheiere a autorizației licență neexclusivă cu <strong>cel puțin 10 zile înainte</strong> de data începerii spectacolului sau festivalului, care trebuie să cuprindă:</p>

            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <div class="grid grid-cols-1 gap-2">
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Datele de identificare și de contact ale organizatorului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Denumirea, locația și data evenimentului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Denumirea artiștilor / formațiilor participante</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Bugetul de cheltuieli (în cazul în care evenimentul nu obține venituri)</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Capacitatea maximă de spectatori</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Semnătura olografă a reprezentantului legal</span>
                </div>
            </div>

            <div class="p-5 my-5 border-l-4 bg-emerald-50 border-emerald-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-emerald-900 mb-0">
                    <strong class="text-emerald-700">Excepție:</strong>
                    Nu trebuie încheiată autorizația de licență neexclusivă dacă organizatorul deține o cesiune pentru drepturile de autor asupra operelor ce sunt prezentate în cadrul spectacolului respectiv.
                </p>
            </div>
        </section>

        <!-- Contact CTA -->
        <div class="p-6 mt-8 text-center bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl">
            <h3 class="mb-2 text-lg font-bold text-gray-900">Ai întrebări?</h3>
            <p class="mb-4 text-sm text-gray-600">Echipa AmBilet este aici să te ajute cu orice nelămurire legată de organizarea evenimentului tău.</p>
            <a href="/contact" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white transition-all rounded-xl bg-primary hover:bg-primary-dark">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Contactează-ne
            </a>
        </div>
    </article>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';

$scriptsExtra = <<<'SCRIPTS'
<script>
const GuidePage = {
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
                        link.classList.add('text-gray-600');
                        if (link.getAttribute('href') === `#${entry.target.id}`) {
                            link.classList.remove('text-gray-600');
                            link.classList.add('bg-red-50', 'text-primary', 'font-semibold');
                        }
                    });
                }
            });
        }, { rootMargin: '-20% 0px -70% 0px' });

        sections.forEach(section => observer.observe(section));
    }
};

document.addEventListener('DOMContentLoaded', () => GuidePage.init());
</script>
<style>
@media print {
    header, .lg\:sticky, footer, button { display: none !important; }
    main { grid-template-columns: 1fr !important; padding: 0 !important; }
    article { border: none !important; box-shadow: none !important; padding: 0 !important; }
    section.py-16 { background: none !important; color: #1E293B !important; padding: 20px 0 !important; }
    section.py-16 h1 { color: #1E293B !important; }
    section.py-16 p, section.py-16 .flex { color: #64748B !important; }
    section.py-16 > div > div:first-child { display: none !important; }
}
</style>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
