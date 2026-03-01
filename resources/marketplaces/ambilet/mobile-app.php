<?php
/**
 * Mobile App Landing Page
 */
require_once 'includes/config.php';

$pageTitle = 'Aplicația Mobilă — AmBilet.ro';
$pageDescription = 'Descarcă aplicația AmBilet și ai toate biletele în buzunar. Scanare rapidă, notificări pentru evenimente și acces instant la experiențe.';

$cssBundle = 'static';
require_once 'includes/head.php';
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="relative bg-gradient-to-br from-slate-800 to-slate-900 pt-20 pb-0 overflow-hidden min-h-[700px]">
    <!-- Background decorations -->
    <div class="absolute -top-48 -right-24 w-[600px] h-[600px] bg-gradient-radial from-primary/25 to-transparent rounded-full"></div>
    <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-gradient-radial from-primary/15 to-transparent rounded-full"></div>

    <div class="max-w-6xl mx-auto px-6 md:px-12 relative z-10">
        <div class="grid lg:grid-cols-2 gap-16 items-end">
            <!-- Hero Content -->
            <div class="pb-20 text-center lg:text-left">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/20 border border-primary/30 rounded-full text-sm font-semibold text-red-400 mb-6">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    Aplicație mobilă
                </div>

                <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-5 tracking-tight leading-tight">
                    Biletele tale, <span class="bg-gradient-to-r from-red-400 to-amber-400 bg-clip-text text-transparent">mereu la îndemână</span>
                </h1>

                <p class="text-lg text-white/90 leading-relaxed mb-8 max-w-lg mx-auto lg:mx-0">
                    Descarcă aplicația AmBilet și ai toate biletele în buzunar. Scanare rapidă, notificări pentru evenimente și acces instant la experiențe.
                </p>

                <!-- App Store Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mb-10">
                    <a href="#" class="flex items-center gap-3 px-6 py-3.5 bg-white rounded-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-black/30">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                        </svg>
                        <div class="text-left">
                            <div class="text-xs text-slate-500">Descarcă din</div>
                            <div class="text-base font-bold text-slate-800">App Store</div>
                        </div>
                    </a>
                    <a href="#" class="flex items-center gap-3 px-6 py-3.5 bg-white rounded-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-black/30">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-3.198l2.807 1.626a1 1 0 010 1.73l-2.808 1.626L15.206 12l2.492-2.491zM5.864 2.658L16.8 8.99l-2.302 2.302-8.634-8.634z"/>
                        </svg>
                        <div class="text-left">
                            <div class="text-xs text-slate-500">Disponibil pe</div>
                            <div class="text-base font-bold text-slate-800">Google Play</div>
                        </div>
                    </a>
                </div>

                <!-- Stats -->
                <div class="flex gap-10 justify-center lg:justify-start">
                    <div>
                        <div class="text-3xl font-extrabold text-white">50K+</div>
                        <div class="text-sm text-white/90">Descărcări</div>
                    </div>
                    <div>
                        <div class="text-3xl font-extrabold text-white">4.8★</div>
                        <div class="text-sm text-white/90">Rating mediu</div>
                    </div>
                    <div>
                        <div class="text-3xl font-extrabold text-white">100K+</div>
                        <div class="text-sm text-white/90">Bilete scanate</div>
                    </div>
                </div>
            </div>

            <!-- Phone Mockup -->
            <div class="relative flex justify-center lg:-mb-24">
                <div class="relative w-72">
                    <svg class="w-full drop-shadow-2xl" viewBox="0 0 300 620" fill="none">
                        <rect x="2" y="2" width="296" height="616" rx="40" fill="#1E293B" stroke="#334155" stroke-width="4"/>
                        <rect x="12" y="12" width="276" height="596" rx="32" fill="#0F172A"/>
                        <rect x="100" y="8" width="100" height="24" rx="12" fill="#1E293B"/>
                    </svg>
                    <div class="absolute top-3 left-3 right-3 bottom-3 bg-gradient-to-b from-primary to-primary-dark rounded-[32px] overflow-hidden">
                        <div class="p-10 pt-10 text-center text-white">
                            <svg class="w-16 h-16 mx-auto mb-4" viewBox="0 0 48 48" fill="none">
                                <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="white"/>
                                <rect x="20" y="27" width="8" height="8" rx="1.5" fill="#A51C30"/>
                            </svg>
                            <div class="text-lg font-bold mb-2">Biletele mele</div>
                            <div class="text-xs opacity-80 mb-6">1 bilet activ</div>
                            <div class="bg-white rounded-2xl p-4 mb-4">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-sm font-bold text-slate-800">Concert Smiley</span>
                                    <span class="px-2 py-1 bg-green-100 text-green-600 text-[10px] font-bold rounded">Valid</span>
                                </div>
                                <div class="text-[11px] text-slate-500 text-left">
                                    15 Ian 2025 • 20:00<br>
                                    Sala Palatului, București
                                </div>
                                <div class="w-20 h-20 bg-slate-100 rounded-lg mt-4 mx-auto flex items-center justify-center">
                                    <svg class="w-16 h-16 text-slate-800" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M3 3h6v6H3V3zm2 2v2h2V5H5zm8-2h6v6h-6V3zm2 2v2h2V5h-2zM3 13h6v6H3v-6zm2 2v2h2v-2H5zm13-2h1v1h-1v-1zm-3 0h1v1h-1v-1zm0 4h1v1h-1v-1zm3 0h1v1h-1v-1zm0 3h1v1h-1v-1zm-3 0h1v1h-1v-1zm-4-4h1v1h-1v-1zm0-3h1v1h-1v-1zm3 0h1v1h-1v-1zm0 6h1v1h-1v-1zm4-3h1v1h-1v-1z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-6xl mx-auto px-6 md:px-12 py-20">

    <!-- Features Section -->
    <section class="mb-24">
        <div class="text-center mb-12">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-r from-primary to-primary-light rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">
                Funcționalități
            </span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800 mb-4 tracking-tight">
                Tot ce ai nevoie, într-o singură aplicație
            </h2>
            <p class="text-lg text-slate-500 max-w-xl mx-auto leading-relaxed">
                Descoperă toate funcționalitățile care fac experiența ta la evenimente mai simplă.
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="bg-white rounded-3xl p-9 border border-slate-200 text-center transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-slate-200/50 hover:border-primary">
                <div class="w-[72px] h-[72px] rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center mx-auto mb-6 text-primary">
                    <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Bilete digitale</h3>
                <p class="text-[15px] text-slate-500 leading-relaxed">
                    Toate biletele tale într-un singur loc. Scanare instantă cu codul QR, fără să mai tipărești nimic.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="bg-white rounded-3xl p-9 border border-slate-200 text-center transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-slate-200/50 hover:border-primary">
                <div class="w-[72px] h-[72px] rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center mx-auto mb-6 text-primary">
                    <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Notificări smart</h3>
                <p class="text-[15px] text-slate-500 leading-relaxed">
                    Primești reminder-uri înainte de eveniment și actualizări importante direct pe telefon.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="bg-white rounded-3xl p-9 border border-slate-200 text-center transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-slate-200/50 hover:border-primary">
                <div class="w-[72px] h-[72px] rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center mx-auto mb-6 text-primary">
                    <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Descoperă evenimente</h3>
                <p class="text-[15px] text-slate-500 leading-relaxed">
                    Explorează evenimente din orașul tău, filtrează după preferințe și găsește experiențe noi.
                </p>
            </div>

            <!-- Feature 4 -->
            <div class="bg-white rounded-3xl p-9 border border-slate-200 text-center transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-slate-200/50 hover:border-primary">
                <div class="w-[72px] h-[72px] rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center mx-auto mb-6 text-primary">
                    <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Favorite & Watchlist</h3>
                <p class="text-[15px] text-slate-500 leading-relaxed">
                    Salvează evenimentele preferate și primești alertă când biletele sunt disponibile.
                </p>
            </div>

            <!-- Feature 5 -->
            <div class="bg-white rounded-3xl p-9 border border-slate-200 text-center transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-slate-200/50 hover:border-primary">
                <div class="w-[72px] h-[72px] rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center mx-auto mb-6 text-primary">
                    <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Plată securizată</h3>
                <p class="text-[15px] text-slate-500 leading-relaxed">
                    Cumpără bilete rapid cu Apple Pay, Google Pay sau card salvat. 100% securizat.
                </p>
            </div>

            <!-- Feature 6 -->
            <div class="bg-white rounded-3xl p-9 border border-slate-200 text-center transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-slate-200/50 hover:border-primary">
                <div class="w-[72px] h-[72px] rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center mx-auto mb-6 text-primary">
                    <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Transfer bilete</h3>
                <p class="text-[15px] text-slate-500 leading-relaxed">
                    Trimite bilete prietenilor direct din aplicație. Simplu și instant.
                </p>
            </div>
        </div>
    </section>

    <!-- Screenshots Section -->
    <section class="mb-24">
        <div class="bg-gradient-to-br from-slate-800 to-slate-600 rounded-[32px] p-10 md:p-16 overflow-hidden">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">Preview aplicație</h2>
                <p class="text-lg text-white/90">Explorează interfața intuitivă și descoperă cât de ușor e să folosești AmBilet.</p>
            </div>
            <div class="flex gap-6 justify-center flex-wrap">
                <div class="w-48 bg-white rounded-3xl p-2 transition-transform duration-300 hover:scale-105">
                    <div class="w-full aspect-[9/19] bg-gradient-to-b from-slate-100 to-slate-200 rounded-2xl flex items-center justify-center">
                        <span class="text-sm text-slate-400 font-semibold">Ecran Acasă</span>
                    </div>
                </div>
                <div class="w-48 bg-white rounded-3xl p-2 transition-transform duration-300 hover:scale-105">
                    <div class="w-full aspect-[9/19] bg-gradient-to-b from-slate-100 to-slate-200 rounded-2xl flex items-center justify-center">
                        <span class="text-sm text-slate-400 font-semibold">Biletele Mele</span>
                    </div>
                </div>
                <div class="w-48 bg-white rounded-3xl p-2 transition-transform duration-300 hover:scale-105">
                    <div class="w-full aspect-[9/19] bg-gradient-to-b from-slate-100 to-slate-200 rounded-2xl flex items-center justify-center">
                        <span class="text-sm text-slate-400 font-semibold">Detalii Bilet</span>
                    </div>
                </div>
                <div class="w-48 bg-white rounded-3xl p-2 transition-transform duration-300 hover:scale-105">
                    <div class="w-full aspect-[9/19] bg-gradient-to-b from-slate-100 to-slate-200 rounded-2xl flex items-center justify-center">
                        <span class="text-sm text-slate-400 font-semibold">Căutare</span>
                    </div>
                </div>
                <div class="w-48 bg-white rounded-3xl p-2 transition-transform duration-300 hover:scale-105">
                    <div class="w-full aspect-[9/19] bg-gradient-to-b from-slate-100 to-slate-200 rounded-2xl flex items-center justify-center">
                        <span class="text-sm text-slate-400 font-semibold">Profil</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section class="mb-24">
        <div class="text-center mb-12">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-r from-primary to-primary-light rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">
                Recenzii
            </span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800 tracking-tight">
                Ce spun utilizatorii
            </h2>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Review 1 -->
            <div class="bg-white rounded-2xl p-7 border border-slate-200">
                <div class="flex gap-1 mb-4">
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <p class="text-[15px] text-slate-700 leading-relaxed mb-5">
                    "Super aplicație! Am intrat la concert în 2 secunde doar arătând telefonul. Nu mai tipăresc bilete niciodată."
                </p>
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-sm font-bold text-white">MC</div>
                    <div>
                        <div class="text-sm font-bold text-slate-800">Maria C.</div>
                        <div class="text-xs text-slate-500">App Store</div>
                    </div>
                </div>
            </div>

            <!-- Review 2 -->
            <div class="bg-white rounded-2xl p-7 border border-slate-200">
                <div class="flex gap-1 mb-4">
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <p class="text-[15px] text-slate-700 leading-relaxed mb-5">
                    "Notificările sunt geniale. M-au salvat când era să uit de un concert. Recomand cu încredere!"
                </p>
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-sm font-bold text-white">AP</div>
                    <div>
                        <div class="text-sm font-bold text-slate-800">Andrei P.</div>
                        <div class="text-xs text-slate-500">Google Play</div>
                    </div>
                </div>
            </div>

            <!-- Review 3 -->
            <div class="bg-white rounded-2xl p-7 border border-slate-200">
                <div class="flex gap-1 mb-4">
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <svg class="w-[18px] h-[18px] text-amber-400 fill-current" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <p class="text-[15px] text-slate-700 leading-relaxed mb-5">
                    "Interfața e foarte intuitivă și transferul de bilete către prieteni funcționează perfect. Top!"
                </p>
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-sm font-bold text-white">ED</div>
                    <div>
                        <div class="text-sm font-bold text-slate-800">Elena D.</div>
                        <div class="text-xs text-slate-500">App Store</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- QR Download Section -->
    <section class="mb-24">
        <div class="bg-white rounded-3xl p-10 md:p-16 border border-slate-200 grid lg:grid-cols-2 gap-16 items-center">
            <div class="text-center lg:text-left">
                <h2 class="text-3xl font-extrabold text-slate-800 mb-4">Descarcă acum</h2>
                <p class="text-lg text-slate-500 leading-relaxed mb-6">
                    Scanează codul QR cu telefonul sau folosește link-urile de download pentru a instala aplicația AmBilet.
                </p>
                <ul class="space-y-2.5 inline-block text-left">
                    <li class="flex items-center gap-3 text-[15px] text-slate-700">
                        <svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Gratuit pe iOS și Android
                    </li>
                    <li class="flex items-center gap-3 text-[15px] text-slate-700">
                        <svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Sincronizare automată cu contul web
                    </li>
                    <li class="flex items-center gap-3 text-[15px] text-slate-700">
                        <svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Funcționează offline pentru bilete
                    </li>
                    <li class="flex items-center gap-3 text-[15px] text-slate-700">
                        <svg class="w-5 h-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Actualizări regulate cu funcții noi
                    </li>
                </ul>
            </div>
            <div class="text-center">
                <div class="inline-block bg-white border-2 border-slate-200 rounded-3xl p-6">
                    <div class="w-52 h-52 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                        <svg class="w-40 h-40 text-slate-800" viewBox="0 0 100 100" fill="currentColor">
                            <rect x="0" y="0" width="30" height="30" rx="4"/>
                            <rect x="70" y="0" width="30" height="30" rx="4"/>
                            <rect x="0" y="70" width="30" height="30" rx="4"/>
                            <rect x="8" y="8" width="14" height="14" fill="white" rx="2"/>
                            <rect x="78" y="8" width="14" height="14" fill="white" rx="2"/>
                            <rect x="8" y="78" width="14" height="14" fill="white" rx="2"/>
                            <rect x="11" y="11" width="8" height="8" rx="1"/>
                            <rect x="81" y="11" width="8" height="8" rx="1"/>
                            <rect x="11" y="81" width="8" height="8" rx="1"/>
                            <rect x="40" y="0" width="8" height="8"/>
                            <rect x="52" y="0" width="8" height="8"/>
                            <rect x="40" y="12" width="8" height="8"/>
                            <rect x="0" y="40" width="8" height="8"/>
                            <rect x="12" y="40" width="8" height="8"/>
                            <rect x="0" y="52" width="8" height="8"/>
                            <rect x="40" y="40" width="20" height="20" rx="4"/>
                            <rect x="70" y="40" width="8" height="8"/>
                            <rect x="82" y="40" width="8" height="8"/>
                            <rect x="70" y="52" width="8" height="8"/>
                            <rect x="92" y="52" width="8" height="8"/>
                            <rect x="40" y="70" width="8" height="8"/>
                            <rect x="52" y="82" width="8" height="8"/>
                            <rect x="70" y="70" width="8" height="8"/>
                            <rect x="82" y="82" width="8" height="8"/>
                            <rect x="92" y="70" width="8" height="8"/>
                        </svg>
                    </div>
                    <div class="text-sm text-slate-500">Scanează pentru download</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-gradient-to-br from-primary to-primary-dark rounded-[32px] p-12 md:p-20 text-center relative overflow-hidden">
        <div class="absolute -top-24 -left-24 w-72 h-72 bg-gradient-radial from-white/10 to-transparent rounded-full"></div>
        <div class="relative z-10">
            <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">Hai în comunitatea AmBilet!</h2>
            <p class="text-lg text-white/85 mb-8">Descarcă aplicația și bucură-te de experiențe memorabile la evenimente.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="#" class="flex items-center gap-3 px-6 py-3.5 bg-white rounded-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-black/30">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                    </svg>
                    <div class="text-left">
                        <div class="text-xs text-slate-500">Descarcă din</div>
                        <div class="text-base font-bold text-slate-800">App Store</div>
                    </div>
                </a>
                <a href="#" class="flex items-center gap-3 px-6 py-3.5 bg-white rounded-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-black/30">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-3.198l2.807 1.626a1 1 0 010 1.73l-2.808 1.626L15.206 12l2.492-2.491zM5.864 2.658L16.8 8.99l-2.302 2.302-8.634-8.634z"/>
                    </svg>
                    <div class="text-left">
                        <div class="text-xs text-slate-500">Disponibil pe</div>
                        <div class="text-base font-bold text-slate-800">Google Play</div>
                    </div>
                </a>
            </div>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>
