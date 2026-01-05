<?php
/**
 * Privacy Policy Page - Ambilet Marketplace
 * Legal privacy policy and data protection information
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Politica de Confidențialitate — Ambilet";
$pageDescription = "Află cum colectăm, utilizăm și protejăm datele tale personale când folosești serviciile Ambilet.";
$bodyClass = 'page-privacy';
$transparentHeader = true;

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="py-16 text-center bg-gradient-to-br from-gray-900 to-gray-700">
    <div class="max-w-[700px] mx-auto px-6">
        <div class="w-[72px] h-[72px] bg-primary/20 border border-primary/30 rounded-[20px] flex items-center justify-center mx-auto mb-6">
            <svg class="w-9 h-9 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </div>
        <h1 class="text-[42px] font-extrabold text-white mb-4">Politica de Confidențialitate</h1>
        <p class="text-lg leading-relaxed text-white/70">Află cum colectăm, utilizăm și protejăm datele tale personale când folosești serviciile Ambilet.</p>
        <div class="flex flex-col items-center justify-center gap-2 mt-5 text-sm sm:flex-row sm:gap-6 text-white/60">
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
                ~12 minute de citit
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
                    <li><a href="#introducere" class="block px-3 py-3 text-sm font-medium transition-colors rounded-lg nav-link text-primary bg-red-50">1. Introducere</a></li>
                    <li><a href="#date-colectate" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">2. Date colectate</a></li>
                    <li><a href="#scopuri" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">3. Scopurile prelucrării</a></li>
                    <li><a href="#temeiuri" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">4. Temeiuri legale</a></li>
                    <li><a href="#partajare" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">5. Partajarea datelor</a></li>
                    <li><a href="#stocare" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">6. Perioada de stocare</a></li>
                    <li><a href="#securitate" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">7. Securitatea datelor</a></li>
                    <li><a href="#drepturi" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">8. Drepturile tale</a></li>
                    <li><a href="#minori" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">9. Protecția minorilor</a></li>
                    <li><a href="#modificari" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">10. Modificări</a></li>
                    <li><a href="#contact" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">11. Contact DPO</a></li>
                </ul>
            </nav>
        </div>

        <div class="pt-6 mt-6 border-t border-gray-200 lg:mt-6 lg:pt-6 lg:border-t">
            <div class="mb-3 text-xs font-bold tracking-wide text-gray-400 uppercase">Alte documente legale</div>
            <div class="space-y-2">
                <a href="/termeni" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Termeni și Condiții
                </a>
                <a href="/cookies" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="4"/>
                        <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/>
                        <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/>
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
    <article class="bg-white rounded-[20px] p-8 lg:p-12 border border-gray-200">
        <!-- Section 1 -->
        <section id="introducere" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">1</span>
                Introducere
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Ambilet SRL ("Ambilet", "noi", "nostru") este operatorul datelor cu caracter personal colectate prin intermediul platformei noastre de ticketing. Ne angajăm să protejăm confidențialitatea datelor tale și să respectăm cerințele Regulamentului General privind Protecția Datelor (GDPR) și ale legislației române în vigoare.</p>

            <div class="p-5 my-6 border-l-4 bg-emerald-50 border-emerald-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-emerald-900 mb-0"><strong class="text-emerald-700">Angajamentul nostru:</strong> Tratăm datele tale personale cu cea mai mare responsabilitate. Colectăm doar datele strict necesare și le protejăm prin măsuri tehnice și organizatorice adecvate.</p>
            </div>

            <p class="text-[15px] leading-[1.8] text-gray-600">Această politică de confidențialitate explică ce date colectăm, de ce le colectăm, cum le utilizăm și care sunt drepturile tale în calitate de persoană vizată.</p>
        </section>

        <!-- Section 2 -->
        <section id="date-colectate" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">2</span>
                Datele pe care le colectăm
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Colectăm diferite categorii de date personale în funcție de modul în care interacționezi cu platforma noastră:</p>

            <!-- Data Card: Identity -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Date de identificare
                </h4>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Nume și prenume</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Adresa de email</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Număr de telefon</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Data nașterii</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Adresa de domiciliu</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>CNP (pentru facturi)</span>
                </div>
            </div>

            <!-- Data Card: Payment -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Date de plată
                </h4>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Ultimele 4 cifre ale cardului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Data expirării cardului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Tipul cardului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Adresa de facturare</span>
                </div>
            </div>

            <!-- Data Card: Technical -->
            <div class="p-5 my-5 bg-gray-50 rounded-xl">
                <h4 class="flex items-center gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                    Date tehnice
                </h4>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Adresa IP</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Tipul browserului</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Sistemul de operare</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Date de localizare</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Cookie-uri</span>
                    <span class="flex items-center gap-2 text-sm text-gray-600"><span class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0"></span>Istoricul navigării</span>
                </div>
            </div>

            <div class="p-5 my-6 border-l-4 bg-sky-50 border-sky-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-sky-900 mb-0"><strong>Notă:</strong> Nu stocăm niciodată numărul complet al cardului sau codul CVV. Plățile sunt procesate de parteneri certificați PCI-DSS.</p>
            </div>
        </section>

        <!-- Section 3 -->
        <section id="scopuri" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">3</span>
                Scopurile prelucrării datelor
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Utilizăm datele tale personale pentru următoarele scopuri:</p>

            <table class="w-full my-6 text-sm border-collapse">
                <thead>
                    <tr>
                        <th class="p-3.5 text-left border-b border-gray-200 bg-gray-50 font-semibold text-gray-900">Scop</th>
                        <th class="p-3.5 text-left border-b border-gray-200 bg-gray-50 font-semibold text-gray-900">Descriere</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Furnizarea serviciilor</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Procesarea comenzilor, emiterea biletelor, gestionarea contului</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Comunicări tranzacționale</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Confirmări de comandă, actualizări despre evenimente, notificări importante</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Marketing</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Newsletter, oferte personalizate, recomandări (cu consimțământul tău)</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Îmbunătățirea serviciilor</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Analiză statistică, optimizarea experienței utilizatorului</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Securitate</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Prevenirea fraudelor, detectarea activităților suspecte</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600"><strong>Obligații legale</strong></td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Conformitate fiscală, răspunsuri la solicitări legale</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Section 4 -->
        <section id="temeiuri" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">4</span>
                Temeiurile legale ale prelucrării
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Prelucrăm datele tale personale pe baza următoarelor temeiuri legale prevăzute de GDPR:</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">4.1 Executarea contractului (Art. 6(1)(b) GDPR)</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Prelucrarea datelor necesare pentru a-ți furniza serviciile solicitate: crearea contului, procesarea comenzilor, emiterea biletelor și comunicările tranzacționale.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">4.2 Consimțământ (Art. 6(1)(a) GDPR)</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru comunicări de marketing și cookie-uri non-esențiale, solicităm consimțământul tău explicit. Poți retrage acest consimțământ în orice moment.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">4.3 Interese legitime (Art. 6(1)(f) GDPR)</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru îmbunătățirea serviciilor, prevenirea fraudelor și securitatea platformei, ne bazăm pe interesele noastre legitime, asigurându-ne că acestea nu prevalează asupra drepturilor tale.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">4.4 Obligații legale (Art. 6(1)(c) GDPR)</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600">Păstrăm anumite date pentru a respecta obligațiile fiscale și contabile impuse de legislația română.</p>
        </section>

        <!-- Section 5 -->
        <section id="partajare" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">5</span>
                Partajarea datelor cu terți
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Putem partaja datele tale cu următoarele categorii de destinatari:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Organizatori de evenimente:</strong> Numele și datele de contact pentru accesul la eveniment</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Procesatori de plăți:</strong> Stripe, PayU pentru procesarea tranzacțiilor</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Furnizori de servicii:</strong> Hosting, email, analiză web</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Autorități publice:</strong> Când legea impune acest lucru</li>
            </ul>

            <div class="p-5 my-6 border-l-4 bg-red-50 border-primary rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-red-900 mb-0"><strong class="text-primary">Important:</strong> Nu vindem și nu închiriem niciodată datele tale personale către terți în scopuri de marketing.</p>
            </div>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">5.1 Transferuri internaționale</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600">Unii dintre partenerii noștri pot prelucra date în afara Spațiului Economic European. În aceste cazuri, ne asigurăm că există garanții adecvate conform GDPR (Clauze Contractuale Standard, Decizii de Adecvare).</p>
        </section>

        <!-- Section 6 -->
        <section id="stocare" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">6</span>
                Perioada de stocare
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Păstrăm datele tale personale doar atât timp cât este necesar pentru scopurile pentru care au fost colectate:</p>

            <table class="w-full my-6 text-sm border-collapse">
                <thead>
                    <tr>
                        <th class="p-3.5 text-left border-b border-gray-200 bg-gray-50 font-semibold text-gray-900">Categorie de date</th>
                        <th class="p-3.5 text-left border-b border-gray-200 bg-gray-50 font-semibold text-gray-900">Perioada de păstrare</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Date cont activ</td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Pe durata existenței contului + 3 ani</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Date tranzacționale</td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">10 ani (obligații fiscale)</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Date de marketing</td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Până la retragerea consimțământului</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Loguri de securitate</td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">12 luni</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Cookie-uri</td>
                        <td class="p-3.5 border-b border-gray-200 text-gray-600">Maximum 13 luni</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Section 7 -->
        <section id="securitate" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">7</span>
                Securitatea datelor
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Implementăm măsuri tehnice și organizatorice adecvate pentru a proteja datele tale:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Criptare SSL/TLS pentru toate comunicațiile</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Criptarea datelor sensibile în repaus</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Autentificare cu doi factori disponibilă</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Monitorizare continuă pentru detectarea intruziunilor</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Acces restricționat pe baza principiului "need-to-know"</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Training periodic pentru angajați privind protecția datelor</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Audituri de securitate regulate</li>
            </ul>
        </section>

        <!-- Section 8 -->
        <section id="drepturi" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">8</span>
                Drepturile tale
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Conform GDPR, ai următoarele drepturi privind datele tale personale:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Dreptul de acces:</strong> Poți solicita o copie a datelor pe care le deținem despre tine.</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Dreptul la rectificare:</strong> Poți corecta datele inexacte sau incomplete</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Dreptul la ștergere:</strong> Poți solicita ștergerea datelor ("dreptul de a fi uitat")</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Dreptul la restricționare:</strong> Poți limita prelucrarea datelor</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Dreptul la portabilitate:</strong> Poți primi datele într-un format structurat</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Dreptul la opoziție:</strong> Te poți opune prelucrării bazate pe interese legitime</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Dreptul de a nu fi supus deciziilor automate:</strong> Inclusiv profilării cu efecte legale</li>
            </ul>

            <p class="text-[15px] leading-[1.8] text-gray-600">Pentru exercitarea acestor drepturi, consultă pagina <a href="/gdpr" class="font-medium text-primary hover:underline">Drepturile GDPR</a> sau contactează-ne la <a href="mailto:dpo@ambilet.ro" class="font-medium text-primary hover:underline">dpo@ambilet.ro</a>.</p>
        </section>

        <!-- Section 9 -->
        <section id="minori" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">9</span>
                Protecția minorilor
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Platforma Ambilet nu este destinată persoanelor sub 16 ani. Nu colectăm cu bună știință date personale de la minori sub această vârstă. Dacă ești părinte sau tutore și afli că copilul tău ne-a furnizat date personale, te rugăm să ne contactezi imediat.</p>

            <p class="text-[15px] leading-[1.8] text-gray-600">Pentru minorii între 16 și 18 ani, recomandăm utilizarea platformei sub supravegherea unui adult.</p>
        </section>

        <!-- Section 10 -->
        <section id="modificari" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">10</span>
                Modificări ale politicii
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Ne rezervăm dreptul de a actualiza această politică de confidențialitate periodic. În cazul unor modificări substanțiale, te vom notifica prin:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600">Email la adresa asociată contului tău</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Notificare vizibilă pe platformă</li>
                <li class="text-[15px] leading-[1.8] text-gray-600">Actualizarea datei "Ultima actualizare" din această pagină</li>
            </ul>
            <p class="text-[15px] leading-[1.8] text-gray-600">Te încurajăm să consultezi periodic această pagină pentru a fi informat despre modul în care îți protejăm datele.</p>
        </section>

        <!-- Section 11 -->
        <section id="contact" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">11</span>
                Contactul responsabilului cu protecția datelor
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru orice întrebări sau solicitări legate de datele tale personale, poți contacta Responsabilul nostru cu Protecția Datelor (DPO):</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Email:</strong> <a href="mailto:dpo@ambilet.ro" class="font-medium text-primary hover:underline">dpo@ambilet.ro</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Telefon:</strong> <a href="tel:+40312345679" class="font-medium text-primary hover:underline">+40 31 234 5679</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Adresă:</strong> Str. Exemplu nr. 123, București, România</li>
            </ul>

            <p class="text-[15px] leading-[1.8] text-gray-600">De asemenea, ai dreptul de a depune o plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (ANSPDCP) la <a href="https://www.dataprotection.ro" target="_blank" class="font-medium text-primary hover:underline">www.dataprotection.ro</a>.</p>
        </section>

        <!-- Print Section -->
        <div class="flex flex-col items-center justify-between gap-4 pt-8 mt-8 border-t border-gray-200 sm:flex-row">
            <div class="text-[13px] text-gray-400">
                Ultima actualizare: 15 Ianuarie 2025 • Versiunea 3.0
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
const PrivacyPage = {
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

document.addEventListener('DOMContentLoaded', () => PrivacyPage.init());
</script>
<style>
@media print {
    header, .lg\\:sticky, footer, button { display: none !important; }
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
