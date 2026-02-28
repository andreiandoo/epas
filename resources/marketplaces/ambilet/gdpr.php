<?php
/**
 * GDPR Rights Page - Ambilet Marketplace
 * Information about GDPR rights and how to exercise them
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Drepturile Tale GDPR — Ambilet";
$pageDescription = "Ai control deplin asupra datelor tale personale. Află ce drepturi îți garantează GDPR și cum le poți exercita.";
$bodyClass = 'page-gdpr';
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
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <h1 class="text-[42px] font-extrabold text-white mb-4">Drepturile Tale GDPR</h1>
        <p class="text-lg leading-relaxed text-white/90">Ai control deplin asupra datelor tale personale. Află ce drepturi îți garantează GDPR și cum le poți exercita.</p>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-[1100px] mx-auto px-6 py-12">
    <!-- Intro Section -->
    <section class="p-8 mb-10 bg-white border border-gray-200 lg:p-10 rounded-2xl">
        <p class="text-base leading-relaxed text-gray-600 max-w-[800px]">
            Regulamentul General privind Protecția Datelor (GDPR) îți oferă o serie de drepturi importante referitoare la datele tale personale. La Ambilet, ne angajăm să respectăm și să facilităm exercitarea acestor drepturi. <strong class="text-gray-900">Toate cererile sunt procesate gratuit în termen de maximum 30 de zile.</strong>
        </p>
    </section>

    <!-- Rights Grid -->
    <div class="grid grid-cols-1 gap-6 mb-12 md:grid-cols-2">
        <!-- Right to Access -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul de acces</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 15 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Ai dreptul să afli dacă prelucrăm date despre tine și să primești o copie a acestora, împreună cu informații despre cum le folosim.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Ce poți solicita:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Categoriile de date prelucrate</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Scopurile prelucrării</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Destinatarii datelor</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Perioada de stocare</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>O copie a datelor tale</li>
                </ul>
            </div>
        </div>

        <!-- Right to Rectification -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul la rectificare</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 16 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Dacă datele tale sunt inexacte sau incomplete, ai dreptul să soliciți corectarea sau completarea acestora fără întârzieri nejustificate.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Exemple de rectificare:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Corectarea numelui sau adresei</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Actualizarea numărului de telefon</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Modificarea adresei de email</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Completarea datelor lipsă</li>
                </ul>
            </div>
        </div>

        <!-- Right to Erasure -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-red-500 to-red-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        <line x1="10" y1="11" x2="10" y2="17"/>
                        <line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul la ștergere</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 17 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Cunoscut și ca "dreptul de a fi uitat", îți permite să soliciți ștergerea datelor tale personale în anumite circumstanțe.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Când se aplică:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Datele nu mai sunt necesare</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Îți retragi consimțământul</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Te opui prelucrării</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Datele au fost prelucrate ilegal</li>
                </ul>
            </div>
        </div>

        <!-- Right to Restriction -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul la restricționare</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 18 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Poți solicita limitarea prelucrării datelor tale în timp ce verificăm exactitatea lor sau dacă te opui ștergerii.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Situații aplicabile:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Contești exactitatea datelor</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Prelucrarea este ilegală, dar nu vrei ștergerea</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Ai nevoie de date pentru un litigiu</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Ai exercitat dreptul la opoziție</li>
                </ul>
            </div>
        </div>

        <!-- Right to Portability -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-violet-500 to-violet-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul la portabilitate</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 20 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Ai dreptul să primești datele tale într-un format structurat, utilizat în mod curent și care poate fi citit automat.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Format și opțiuni:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Export în format JSON sau CSV</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Transfer direct la alt operator</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Include doar datele furnizate de tine</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Disponibil în 30 de zile</li>
                </ul>
            </div>
        </div>

        <!-- Right to Object -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-pink-500 to-pink-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul la opoziție</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 21 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Te poți opune prelucrării datelor tale în scopuri de marketing direct sau când prelucrarea se bazează pe interese legitime.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Poți te opune la:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Emailuri de marketing</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Notificări promoționale</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Profilare în scop publicitar</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Prelucrare bazată pe interes legitim</li>
                </ul>
            </div>
        </div>

        <!-- Right regarding profiling -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-teal-500 to-teal-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul privind deciziile automate</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 22 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Ai dreptul să nu fii supus unei decizii bazate exclusiv pe prelucrare automatizată, inclusiv profilare, care produce efecte juridice.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Ce înseamnă:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Intervenție umană în decizii importante</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Dreptul de a contesta decizia</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Explicații privind logica folosită</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Transparență în algoritmi</li>
                </ul>
            </div>
        </div>

        <!-- Right to Complaint -->
        <div class="p-8 transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center flex-shrink-0 w-14 h-14 rounded-xl bg-gradient-to-br from-primary to-primary-light">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Dreptul de a depune plângere</h3>
                    <span class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Articolul 77 GDPR</span>
                </div>
            </div>
            <p class="mb-5 text-[15px] leading-relaxed text-gray-500">
                Dacă consideri că drepturile tale au fost încălcate, poți depune o plângere la autoritatea de supraveghere din România.
            </p>
            <div class="p-4 bg-gray-50 rounded-xl">
                <div class="mb-2 text-xs font-bold tracking-wide text-gray-400 uppercase">Cum procedezi:</div>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Contactează mai întâi DPO-ul nostru</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Plângere la ANSPDCP dacă nu ești satisfăcut</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Plângerea este gratuită</li>
                    <li class="flex items-start gap-2 text-[13px] text-gray-600"><span class="font-bold text-emerald-500">✓</span>Posibilitatea de despăgubiri</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Request Section -->
    <section class="relative p-8 mb-12 overflow-hidden lg:p-12 bg-gradient-to-br from-gray-900 to-gray-700 rounded-3xl">
        <div class="absolute top-[-100px] right-[-100px] w-[300px] h-[300px] bg-primary/30 rounded-full blur-3xl"></div>
        <div class="relative z-10">
            <div class="mb-10 text-center">
                <h2 class="mb-3 text-2xl font-bold text-white lg:text-3xl">Cum îți exerciți drepturile</h2>
                <p class="text-white/90">Alege metoda care ți se potrivește cel mai bine</p>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="p-7 bg-white/10 border border-white/15 rounded-2xl text-center hover:bg-white/15 hover:-translate-y-1 transition-all">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 bg-primary/30 rounded-xl">
                        <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Din contul tău</h3>
                    <p class="mb-4 text-sm text-white/90">Accesează setările contului pentru a-ți descărca, modifica sau șterge datele.</p>
                    <a href="/cont/profil" class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-400">
                        Mergi la setări
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
                <div class="p-7 bg-white/10 border border-white/15 rounded-2xl text-center hover:bg-white/15 hover:-translate-y-1 transition-all">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 bg-primary/30 rounded-xl">
                        <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Prin email</h3>
                    <p class="mb-4 text-sm text-white/90">Trimite o cerere la DPO-ul nostru. Răspundem în maximum 30 de zile.</p>
                    <a href="mailto:dpo@ambilet.ro" class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-400">
                        dpo@ambilet.ro
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
                <div class="p-7 bg-white/10 border border-white/15 rounded-2xl text-center hover:bg-white/15 hover:-translate-y-1 transition-all">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 bg-primary/30 rounded-xl">
                        <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-white">Formular dedicat</h3>
                    <p class="mb-4 text-sm text-white/90">Completează formularul nostru securizat pentru cereri GDPR.</p>
                    <a href="/contact" class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-400">
                        Completează formularul
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section -->
    <section class="p-8 mb-12 bg-white border border-gray-200 lg:p-10 rounded-2xl">
        <h2 class="mb-8 text-2xl font-bold text-center text-gray-900">Procesul de soluționare a cererii</h2>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative text-center">
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 text-xl font-bold text-white rounded-full bg-gradient-to-br from-primary to-primary-light">1</div>
                <h3 class="mb-2 font-bold text-gray-900">Primim cererea</h3>
                <p class="text-[13px] text-gray-500 leading-relaxed">Înregistrăm cererea ta și îți trimitem confirmare în 24 de ore.</p>
            </div>
            <div class="relative text-center">
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 text-xl font-bold text-white rounded-full bg-gradient-to-br from-primary to-primary-light">2</div>
                <h3 class="mb-2 font-bold text-gray-900">Verificăm identitatea</h3>
                <p class="text-[13px] text-gray-500 leading-relaxed">Pentru siguranța ta, verificăm că cererea vine de la tine.</p>
            </div>
            <div class="relative text-center">
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 text-xl font-bold text-white rounded-full bg-gradient-to-br from-primary to-primary-light">3</div>
                <h3 class="mb-2 font-bold text-gray-900">Procesăm cererea</h3>
                <p class="text-[13px] text-gray-500 leading-relaxed">Analizăm și îndeplinim cererea ta în cel mult 30 de zile.</p>
            </div>
            <div class="relative text-center">
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 text-xl font-bold text-white rounded-full bg-gradient-to-br from-primary to-primary-light">4</div>
                <h3 class="mb-2 font-bold text-gray-900">Te informăm</h3>
                <p class="text-[13px] text-gray-500 leading-relaxed">Primești rezultatul pe email, inclusiv acțiunile întreprinse.</p>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="mb-12">
        <h2 class="mb-6 text-2xl font-bold text-center text-gray-900">Întrebări frecvente</h2>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="p-6 bg-white border border-gray-200 rounded-2xl">
                <h3 class="flex items-start gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Cât costă exercitarea drepturilor?
                </h3>
                <p class="pl-[30px] text-sm leading-relaxed text-gray-500">Exercitarea drepturilor GDPR este complet gratuită. Putem percepe o taxă rezonabilă doar pentru cereri repetitive sau excesive.</p>
            </div>
            <div class="p-6 bg-white border border-gray-200 rounded-2xl">
                <h3 class="flex items-start gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Cât durează procesarea cererii?
                </h3>
                <p class="pl-[30px] text-sm leading-relaxed text-gray-500">Răspundem în maximum 30 de zile. Pentru cereri complexe, termenul poate fi prelungit cu încă 60 de zile, cu notificarea ta prealabilă.</p>
            </div>
            <div class="p-6 bg-white border border-gray-200 rounded-2xl">
                <h3 class="flex items-start gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Pot fi refuzat?
                </h3>
                <p class="pl-[30px] text-sm leading-relaxed text-gray-500">În cazuri rare, putem refuza o cerere (ex: obligații legale, drepturile altora). Îți vom explica întotdeauna motivul și opțiunile disponibile.</p>
            </div>
            <div class="p-6 bg-white border border-gray-200 rounded-2xl">
                <h3 class="flex items-start gap-2.5 text-base font-bold text-gray-900 mb-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Ce se întâmplă cu biletele mele după ștergere?
                </h3>
                <p class="pl-[30px] text-sm leading-relaxed text-gray-500">Biletele pentru evenimente viitoare rămân valabile. Păstrăm doar datele necesare pentru validarea lor, fără a te putea identifica.</p>
            </div>
        </div>
    </section>

    <!-- DPO Contact Section -->
    <section class="grid grid-cols-1 gap-6 p-8 bg-white border border-gray-200 lg:grid-cols-2 lg:p-10 lg:gap-10 rounded-2xl">
        <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
            <div class="flex items-center justify-center flex-shrink-0 text-3xl font-extrabold text-white w-20 h-20 bg-gradient-to-br from-primary to-primary-light rounded-[20px]">DPO</div>
            <div>
                <h3 class="mb-1 text-xl font-bold text-gray-900">Responsabil Protecția Datelor</h3>
                <p class="mb-3 text-sm font-semibold text-primary">Data Protection Officer</p>
                <ul class="space-y-2">
                    <li class="flex items-center justify-center gap-2 text-sm text-gray-500 sm:justify-start">
                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <a href="mailto:dpo@ambilet.ro" class="text-primary">dpo@ambilet.ro</a>
                    </li>
                    <li class="flex items-center justify-center gap-2 text-sm text-gray-500 sm:justify-start">
                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/>
                        </svg>
                        <a href="tel:+40312345679" class="text-primary">+40 31 234 5679</a>
                    </li>
                    <li class="flex items-center justify-center gap-2 text-sm text-gray-500 sm:justify-start">
                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        Str. Exemplu nr. 123, București
                    </li>
                </ul>
            </div>
        </div>
        <div class="p-6 bg-gray-50 rounded-2xl">
            <h3 class="flex items-center gap-2 mb-3 text-base font-bold text-gray-900">
                <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 21h18"/>
                    <path d="M5 21V7l8-4 8 4v14"/>
                    <path d="M9 21v-8h6v8"/>
                </svg>
                Autoritatea de Supraveghere
            </h3>
            <p class="mb-4 text-sm leading-relaxed text-gray-500">Dacă nu ești mulțumit de răspunsul nostru sau consideri că drepturile tale au fost încălcate, poți depune o plângere la ANSPDCP.</p>
            <a href="https://www.dataprotection.ro" target="_blank" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-primary to-primary-light hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30">
                Vizitează ANSPDCP
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                    <polyline points="15 3 21 3 21 9"/>
                    <line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
            </a>
        </div>
    </section>
</main>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';

// Page-specific scripts
require_once __DIR__ . '/includes/scripts.php';
?>
