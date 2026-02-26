<?php
/**
 * Cookie Policy Page - Ambilet Marketplace
 * Information about cookies and how to manage preferences
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Politica de Cookies — Ambilet";
$pageDescription = "Află ce sunt cookie-urile, cum le folosim și cum îți poți gestiona preferințele pentru o experiență personalizată.";
$bodyClass = 'page-cookies';
$transparentHeader = false;

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
                <circle cx="12" cy="12" r="10"/>
                <circle cx="12" cy="12" r="4"/>
                <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/>
                <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/>
                <line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/>
                <line x1="4.93" y1="19.07" x2="9.17" y2="14.83"/>
            </svg>
        </div>
        <h1 class="text-[42px] font-extrabold text-white mb-4">Politica de Cookies</h1>
        <p class="text-lg leading-relaxed text-white/70">Află ce sunt cookie-urile, cum le folosim și cum îți poți gestiona preferințele pentru o experiență personalizată.</p>
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
                ~8 minute de citit
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
                    <li><a href="#ce-sunt" class="block px-3 py-3 text-sm font-medium transition-colors rounded-lg nav-link text-primary bg-red-50">1. Ce sunt cookie-urile</a></li>
                    <li><a href="#tipuri" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">2. Tipuri de cookie-uri</a></li>
                    <li><a href="#esentiale" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">3. Cookie-uri esențiale</a></li>
                    <li><a href="#functionale" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">4. Cookie-uri funcționale</a></li>
                    <li><a href="#analitice" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">5. Cookie-uri analitice</a></li>
                    <li><a href="#marketing" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">6. Cookie-uri de marketing</a></li>
                    <li><a href="#gestionare" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">7. Gestionarea preferințelor</a></li>
                    <li><a href="#terti" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">8. Cookie-uri terțe</a></li>
                    <li><a href="#contact" class="block px-3 py-3 text-sm font-medium text-gray-600 transition-colors rounded-lg nav-link hover:bg-gray-50 hover:text-gray-900">9. Contact</a></li>
                </ul>
            </nav>
        </div>

        <div class="hidden p-6 mt-6 text-center lg:block bg-gradient-to-br from-primary to-primary-light rounded-2xl">
            <h3 class="mb-2 text-base font-bold text-white">Setări cookie-uri</h3>
            <p class="mb-4 text-[13px] text-white/80">Modifică preferințele tale oricând</p>
            <button id="openCookieSettings" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold transition-all bg-white rounded-lg text-primary hover:-translate-y-0.5 hover:shadow-lg">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                Deschide setările
            </button>
        </div>

        <div class="pt-6 mt-6 border-t border-gray-200 lg:mt-6 lg:pt-6 lg:border-t">
            <div class="mb-3 text-xs font-bold tracking-wide text-gray-400 uppercase">Alte documente legale</div>
            <div class="space-y-2">
                <a href="/confidentialitate" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Politica de confidențialitate
                </a>
                <a href="/termeni" class="flex items-center gap-2.5 p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    Termeni și Condiții
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
        <section id="ce-sunt" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">1</span>
                Ce sunt cookie-urile?
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Cookie-urile sunt mici fișiere text care sunt stocate pe dispozitivul tău (computer, telefon, tabletă) atunci când vizitezi un website. Acestea permit site-ului să îți recunoască dispozitivul și să rețină anumite informații despre vizita ta, precum preferințele de limbă sau datele de autentificare.</p>

            <div class="p-5 my-6 border-l-4 bg-sky-50 border-sky-500 rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-sky-900 mb-0"><strong>Știai că?</strong> Numele "cookie" vine de la termenul "magic cookie" folosit în programare, care descrie un pachet de date transmis între programe.</p>
            </div>

            <p class="text-[15px] leading-[1.8] text-gray-600">Cookie-urile nu pot accesa alte date de pe dispozitivul tău și nu pot transmite viruși sau alte programe malițioase.</p>
        </section>

        <section id="tipuri" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">2</span>
                Tipurile de cookie-uri pe care le folosim
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Clasificăm cookie-urile în funcție de scopul lor și de durata pentru care sunt stocate:</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">După durată:</h3>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Cookie-uri de sesiune:</strong> Sunt temporare și se șterg când închizi browserul</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Cookie-uri persistente:</strong> Rămân pe dispozitivul tău pentru o perioadă determinată sau până când le ștergi manual</li>
            </ul>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">După proveniență:</h3>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Cookie-uri proprii (first-party):</strong> Sunt setate de Ambilet</li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Cookie-uri terțe (third-party):</strong> Sunt setate de partenerii noștri (ex: Google, Facebook)</li>
            </ul>
        </section>

        <section id="esentiale" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">3</span>
                Cookie-uri esențiale
            </h2>

            <div class="p-6 my-5 bg-gray-50 border border-gray-200 rounded-2xl">
                <div class="flex flex-col items-start justify-between gap-4 mb-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-3.5">
                        <div class="flex items-center justify-center w-12 h-12 text-white rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-900">Cookie-uri strict necesare</h4>
                            <span class="text-xs text-gray-500">Întotdeauna active</span>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center w-[52px] h-7 cursor-not-allowed opacity-60">
                        <input type="checkbox" checked disabled class="sr-only peer">
                        <div class="w-full h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600 peer-checked:after:translate-x-6 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[22px] after:w-[22px] after:shadow-md after:transition-all"></div>
                    </label>
                </div>
                <p class="text-sm leading-relaxed text-gray-500 mb-4">Aceste cookie-uri sunt necesare pentru funcționarea de bază a platformei. Fără ele, nu poți naviga pe site sau folosi funcții esențiale precum coșul de cumpărături sau autentificarea. Nu pot fi dezactivate.</p>

                <div class="pt-4 border-t border-gray-200">
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">session_id</span>
                        <span class="text-gray-500">Menține sesiunea de autentificare</span>
                        <span class="text-right text-gray-400">Sesiune</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">csrf_token</span>
                        <span class="text-gray-500">Protecție împotriva atacurilor CSRF</span>
                        <span class="text-right text-gray-400">Sesiune</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">cart_items</span>
                        <span class="text-gray-500">Stochează produsele din coș</span>
                        <span class="text-right text-gray-400">7 zile</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 text-[13px]">
                        <span class="font-semibold text-gray-900">cookie_consent</span>
                        <span class="text-gray-500">Stochează preferințele tale pentru cookie-uri</span>
                        <span class="text-right text-gray-400">12 luni</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="functionale" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">4</span>
                Cookie-uri funcționale
            </h2>

            <div class="p-6 my-5 bg-gray-50 border border-gray-200 rounded-2xl">
                <div class="flex flex-col items-start justify-between gap-4 mb-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-3.5">
                        <div class="flex items-center justify-center w-12 h-12 text-white rounded-xl bg-gradient-to-br from-blue-500 to-blue-600">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-900">Cookie-uri de funcționalitate</h4>
                            <span class="text-xs text-gray-500">Opțional</span>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center w-[52px] h-7 cursor-pointer">
                        <input type="checkbox" checked class="sr-only peer" id="cookieFunctional">
                        <div class="w-full h-full bg-gray-300 rounded-full peer-checked:bg-gradient-to-r peer-checked:from-emerald-500 peer-checked:to-emerald-600 peer-checked:after:translate-x-6 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[22px] after:w-[22px] after:shadow-md after:transition-all"></div>
                    </label>
                </div>
                <p class="text-sm leading-relaxed text-gray-500 mb-4">Aceste cookie-uri permit funcționalități îmbunătățite și personalizare, cum ar fi memorarea preferințelor tale de limbă sau regiune. Dezactivarea lor poate afecta unele funcții ale site-ului.</p>

                <div class="pt-4 border-t border-gray-200">
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">user_preferences</span>
                        <span class="text-gray-500">Preferințe de afișare și limbă</span>
                        <span class="text-right text-gray-400">12 luni</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">recently_viewed</span>
                        <span class="text-gray-500">Evenimente vizualizate recent</span>
                        <span class="text-right text-gray-400">30 zile</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 text-[13px]">
                        <span class="font-semibold text-gray-900">location_pref</span>
                        <span class="text-gray-500">Locația preferată pentru evenimente</span>
                        <span class="text-right text-gray-400">12 luni</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="analitice" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">5</span>
                Cookie-uri analitice
            </h2>

            <div class="p-6 my-5 bg-gray-50 border border-gray-200 rounded-2xl">
                <div class="flex flex-col items-start justify-between gap-4 mb-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-3.5">
                        <div class="flex items-center justify-center w-12 h-12 text-white rounded-xl bg-gradient-to-br from-amber-500 to-amber-600">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-900">Cookie-uri de analiză și statistică</h4>
                            <span class="text-xs text-gray-500">Opțional</span>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center w-[52px] h-7 cursor-pointer">
                        <input type="checkbox" checked class="sr-only peer" id="cookieAnalytics">
                        <div class="w-full h-full bg-gray-300 rounded-full peer-checked:bg-gradient-to-r peer-checked:from-emerald-500 peer-checked:to-emerald-600 peer-checked:after:translate-x-6 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[22px] after:w-[22px] after:shadow-md after:transition-all"></div>
                    </label>
                </div>
                <p class="text-sm leading-relaxed text-gray-500 mb-4">Ne ajută să înțelegem cum folosești platforma, ce pagini vizitezi și cum putem îmbunătăți experiența. Datele sunt anonimizate și agregate.</p>

                <div class="pt-4 border-t border-gray-200">
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">_ga</span>
                        <span class="text-gray-500">Google Analytics - identificare utilizatori</span>
                        <span class="text-right text-gray-400">24 luni</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">_ga_*</span>
                        <span class="text-gray-500">Google Analytics 4 - stare sesiune</span>
                        <span class="text-right text-gray-400">24 luni</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">_gid</span>
                        <span class="text-gray-500">Google Analytics - distincție utilizatori</span>
                        <span class="text-right text-gray-400">24 ore</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 text-[13px]">
                        <span class="font-semibold text-gray-900">hotjar_*</span>
                        <span class="text-gray-500">Hotjar - hărți termice și feedback</span>
                        <span class="text-right text-gray-400">12 luni</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="marketing" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">6</span>
                Cookie-uri de marketing
            </h2>

            <div class="p-6 my-5 bg-gray-50 border border-gray-200 rounded-2xl">
                <div class="flex flex-col items-start justify-between gap-4 mb-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-3.5">
                        <div class="flex items-center justify-center w-12 h-12 text-white rounded-xl bg-gradient-to-br from-primary to-primary-light">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-900">Cookie-uri de publicitate și remarketing</h4>
                            <span class="text-xs text-gray-500">Opțional</span>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center w-[52px] h-7 cursor-pointer">
                        <input type="checkbox" class="sr-only peer" id="cookieMarketing">
                        <div class="w-full h-full bg-gray-300 rounded-full peer-checked:bg-gradient-to-r peer-checked:from-emerald-500 peer-checked:to-emerald-600 peer-checked:after:translate-x-6 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[22px] after:w-[22px] after:shadow-md after:transition-all"></div>
                    </label>
                </div>
                <p class="text-sm leading-relaxed text-gray-500 mb-4">Sunt folosite pentru a-ți afișa reclame relevante pe alte site-uri pe care le vizitezi. Dacă le dezactivezi, vei vedea în continuare reclame, dar acestea nu vor fi personalizate.</p>

                <div class="pt-4 border-t border-gray-200">
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">_fbp</span>
                        <span class="text-gray-500">Facebook Pixel - tracking conversii</span>
                        <span class="text-right text-gray-400">3 luni</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">_gcl_au</span>
                        <span class="text-gray-500">Google Ads - tracking conversii</span>
                        <span class="text-right text-gray-400">3 luni</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 border-b border-gray-200 text-[13px]">
                        <span class="font-semibold text-gray-900">IDE</span>
                        <span class="text-gray-500">Google DoubleClick - retargeting</span>
                        <span class="text-right text-gray-400">13 luni</span>
                    </div>
                    <div class="grid grid-cols-[1fr_1fr_100px] gap-4 py-3 text-[13px]">
                        <span class="font-semibold text-gray-900">TikTok_*</span>
                        <span class="text-gray-500">TikTok Pixel - tracking campanii</span>
                        <span class="text-right text-gray-400">13 luni</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="gestionare" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">7</span>
                Cum îți gestionezi preferințele
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Ai control deplin asupra cookie-urilor folosite pe dispozitivul tău:</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">7.1 Prin bannerul de consimțământ</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">La prima vizită, îți afișăm un banner unde poți alege ce tipuri de cookie-uri accepți. Poți modifica oricând aceste preferințe folosind butonul "Setări cookie-uri" din footer-ul paginii.</p>

            <h3 class="mb-3 text-lg font-bold text-gray-900 mt-7">7.2 Prin setările browserului</h3>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Majoritatea browserelor îți permit să controlezi cookie-urile prin setări. Iată linkuri pentru browserele populare:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><a href="https://support.google.com/chrome/answer/95647" target="_blank" class="font-medium text-primary">Google Chrome</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><a href="https://support.mozilla.org/ro/kb/activarea-si-dezactivarea-cookie-urilor" target="_blank" class="font-medium text-primary">Mozilla Firefox</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><a href="https://support.apple.com/ro-ro/guide/safari/sfri11471/mac" target="_blank" class="font-medium text-primary">Safari</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><a href="https://support.microsoft.com/ro-ro/microsoft-edge/ștergerea-modulelor-cookie-în-microsoft-edge" target="_blank" class="font-medium text-primary">Microsoft Edge</a></li>
            </ul>

            <div class="p-5 my-6 border-l-4 bg-red-50 border-primary rounded-r-xl">
                <p class="text-[15px] leading-[1.8] text-red-900 mb-0"><strong class="text-primary">Atenție:</strong> Blocarea tuturor cookie-urilor poate afecta funcționalitatea platformei. Recomandăm să păstrezi cel puțin cookie-urile esențiale active.</p>
            </div>
        </section>

        <section id="terti" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">8</span>
                Cookie-uri de la terți
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Folosim servicii de la terți care pot seta propriile lor cookie-uri. Nu avem control asupra acestor cookie-uri, dar le selectăm cu grijă pe cei care respectă GDPR. Principalii noștri parteneri sunt:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Google (Analytics, Ads):</strong> <a href="https://policies.google.com/privacy" target="_blank" class="font-medium text-primary">Politica de confidențialitate Google</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Meta (Facebook, Instagram):</strong> <a href="https://www.facebook.com/privacy/policy/" target="_blank" class="font-medium text-primary">Politica Meta</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Stripe (plăți):</strong> <a href="https://stripe.com/privacy" target="_blank" class="font-medium text-primary">Politica Stripe</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Hotjar (analiză UX):</strong> <a href="https://www.hotjar.com/legal/policies/privacy/" target="_blank" class="font-medium text-primary">Politica Hotjar</a></li>
            </ul>
        </section>

        <section id="contact" class="mb-10">
            <h2 class="flex items-center gap-3 pb-3 mb-5 text-2xl font-bold text-gray-900 border-b-2 border-gray-100">
                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white rounded-lg bg-gradient-to-br from-primary to-primary-light">9</span>
                Contact
            </h2>
            <p class="text-[15px] leading-[1.8] text-gray-600 mb-4">Pentru întrebări despre utilizarea cookie-urilor pe platforma Ambilet, ne poți contacta la:</p>
            <ul class="my-4 pl-6 list-disc space-y-2.5">
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>Email:</strong> <a href="mailto:privacy@ambilet.ro" class="font-medium text-primary">privacy@ambilet.ro</a></li>
                <li class="text-[15px] leading-[1.8] text-gray-600"><strong>DPO:</strong> <a href="mailto:dpo@ambilet.ro" class="font-medium text-primary">dpo@ambilet.ro</a></li>
            </ul>
        </section>

        <!-- Print Section -->
        <div class="flex flex-col items-center justify-between gap-4 pt-8 mt-8 border-t border-gray-200 sm:flex-row">
            <div class="text-[13px] text-gray-400">
                Ultima actualizare: 15 Ianuarie 2025 • Versiunea 2.0
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
const CookiesPage = {
    init() {
        this.initSmoothScroll();
        this.initActiveNavigation();
        this.initCookieSettingsButton();
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
    },

    initCookieSettingsButton() {
        const btn = document.getElementById('openCookieSettings');
        if (btn && typeof AmbiletCookies !== 'undefined') {
            btn.addEventListener('click', () => {
                AmbiletCookies.showModal();
            });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => CookiesPage.init());
</script>
<style>
@media print {
    header, .lg\\:sticky, footer, button, label { display: none !important; }
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
