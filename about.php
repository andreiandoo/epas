<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Despre Noi';
$transparentHeader = false;
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero Section -->
    <section class="relative px-6 py-20 overflow-hidden bg-gradient-to-br from-slate-800 to-slate-900 md:py-24 md:px-12">
        <div class="absolute -top-[300px] -right-[300px] w-[800px] h-[800px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="absolute -bottom-[200px] -left-[200px] w-[500px] h-[500px] bg-[radial-gradient(circle,rgba(165,28,48,0.1)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="relative z-10 max-w-4xl mx-auto text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 mb-6 text-sm font-semibold border rounded-full bg-primary/20 border-primary/30 text-accent">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Echipa AmBilet
            </div>
            <h1 class="text-4xl md:text-[56px] font-extrabold text-white mb-5 leading-tight tracking-tight">Conectam oamenii cu experiente memorabile</h1>
            <p class="max-w-2xl mx-auto text-lg leading-relaxed md:text-xl text-white/90">Suntem o echipa pasionata de tehnologie si evenimente, dedicata sa transformam modul in care romanii descopera si participa la evenimente.</p>
        </div>
    </section>

    <main class="max-w-6xl px-6 py-16 mx-auto md:px-12 md:py-20">
        <!-- Story Section -->
        <section class="mb-24">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Povestea noastra</span>
            <div class="grid items-center gap-12 md:grid-cols-2 md:gap-16">
                <div>
                    <h2 class="mb-6 text-3xl font-extrabold leading-tight md:text-4xl text-slate-800">Am inceput cu o idee simpla</h2>
                    <p class="text-[17px] text-slate-500 leading-relaxed mb-5">In 2024, am observat o problema: organizatorii de evenimente din Romania se luptau cu sisteme de ticketing complicate, scumpe si neadaptate pietei locale.</p>
                    <p class="text-[17px] text-slate-500 leading-relaxed mb-5">Am construit AmBilet pentru a rezolva asta. O platforma moderna, intuitiva, care pune accent pe experienta utilizatorului - atat pentru organizatori, cat si pentru participanti.</p>
                    <p class="text-[17px] text-slate-500 leading-relaxed">Astazi, AmBilet este alegerea a sute de organizatori din Romania, de la festivaluri mari la evenimente de nisa.</p>
                </div>
                <div class="bg-gradient-to-br from-primary to-red-800 rounded-3xl p-12 md:p-16 flex items-center justify-center min-h-[400px] relative overflow-hidden">
                    <div class="absolute -top-[50px] -right-[50px] w-[200px] h-[200px] bg-white/10 rounded-full"></div>
                    <svg class="text-white w-28 h-28 md:w-32 md:h-32 opacity-90" viewBox="0 0 48 48" fill="none">
                        <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="currentColor"/>
                        <line x1="17" y1="15" x2="31" y2="15" stroke="#A51C30" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round"/>
                        <line x1="15" y1="19" x2="33" y2="19" stroke="#A51C30" stroke-opacity="0.35" stroke-width="1.5" stroke-linecap="round"/>
                        <rect x="20" y="27" width="8" height="8" rx="1.5" fill="#A51C30"/>
                    </svg>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-6 lg:grid-cols-4 md:gap-8 mt-14">
                <div class="p-6 text-center transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-2 text-4xl font-extrabold md:text-5xl text-primary">500+</div>
                    <div class="text-sm md:text-[15px] text-slate-500 font-medium">Evenimente organizate</div>
                </div>
                <div class="p-6 text-center transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-2 text-4xl font-extrabold md:text-5xl text-primary">100K+</div>
                    <div class="text-sm md:text-[15px] text-slate-500 font-medium">Bilete vandute</div>
                </div>
                <div class="p-6 text-center transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-2 text-4xl font-extrabold md:text-5xl text-primary">200+</div>
                    <div class="text-sm md:text-[15px] text-slate-500 font-medium">Organizatori activi</div>
                </div>
                <div class="p-6 text-center transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:-translate-y-1 hover:shadow-xl">
                    <div class="mb-2 text-4xl font-extrabold md:text-5xl text-primary">99.9%</div>
                    <div class="text-sm md:text-[15px] text-slate-500 font-medium">Uptime garantat</div>
                </div>
            </div>
        </section>

        <!-- Mission & Vision -->
        <section class="mb-24">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Ce ne ghideaza</span>
            <div class="grid gap-6 md:grid-cols-2 md:gap-8">
                <div class="p-8 bg-white border rounded-3xl md:p-10 border-slate-200">
                    <div class="flex items-center justify-center w-16 h-16 mb-6 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-slate-800">Misiunea noastra</h3>
                    <p class="text-base leading-relaxed text-slate-500">Sa democratizam accesul la tehnologie de ticketing de calitate pentru toti organizatorii de evenimente din Romania, indiferent de marimea lor.</p>
                </div>
                <div class="p-8 bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl md:p-10">
                    <div class="flex items-center justify-center w-16 h-16 mb-6 text-white rounded-2xl bg-white/10">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </div>
                    <h3 class="mb-4 text-2xl font-bold text-white">Viziunea noastra</h3>
                    <p class="text-base leading-relaxed text-white/90">Sa devenim platforma preferata de ticketing din Europa de Est, recunoscuta pentru inovatie, fiabilitate si focus pe comunitate.</p>
                </div>
            </div>
        </section>

        <!-- Values -->
        <section class="mb-24">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Valorile noastre</span>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 md:gap-8">
                <div class="p-6 transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:border-primary hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/10">
                    <div class="flex items-center justify-center mb-5 w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <h4 class="mb-3 text-lg font-bold text-slate-800">Excelenta</h4>
                    <p class="text-sm leading-relaxed text-slate-500">Ne straduim sa livram cea mai buna experienta posibila in fiecare detaliu al platformei noastre.</p>
                </div>
                <div class="p-6 transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:border-primary hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/10">
                    <div class="flex items-center justify-center mb-5 w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h4 class="mb-3 text-lg font-bold text-slate-800">Comunitate</h4>
                    <p class="text-sm leading-relaxed text-slate-500">Construim impreuna cu organizatorii si participantii. Feedback-ul lor ne ghideaza dezvoltarea.</p>
                </div>
                <div class="p-6 transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:border-primary hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/10">
                    <div class="flex items-center justify-center mb-5 w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <h4 class="mb-3 text-lg font-bold text-slate-800">Inovatie</h4>
                    <p class="text-sm leading-relaxed text-slate-500">Adoptam cele mai noi tehnologii pentru a oferi solutii moderne si eficiente.</p>
                </div>
                <div class="p-6 transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:border-primary hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/10">
                    <div class="flex items-center justify-center mb-5 w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <h4 class="mb-3 text-lg font-bold text-slate-800">Incredere</h4>
                    <p class="text-sm leading-relaxed text-slate-500">Securitatea datelor si a tranzactiilor este prioritatea noastra numarul unu.</p>
                </div>
                <div class="p-6 transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:border-primary hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/10">
                    <div class="flex items-center justify-center mb-5 w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h4 class="mb-3 text-lg font-bold text-slate-800">Transparenta</h4>
                    <p class="text-sm leading-relaxed text-slate-500">Comunicam deschis preturile, politicile si orice schimbare care afecteaza utilizatorii.</p>
                </div>
                <div class="p-6 transition-all bg-white border rounded-2xl md:p-8 border-slate-200 hover:border-primary hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/10">
                    <div class="flex items-center justify-center mb-5 w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.93 4.93l4.24 4.24"/><path d="M14.83 14.83l4.24 4.24"/><circle cx="12" cy="12" r="2.5"/><path d="M6.34 17.66l4.24-4.24"/><path d="M14.83 9.17l4.24-4.24"/></svg>
                    </div>
                    <h4 class="mb-3 text-lg font-bold text-slate-800">Sustenabilitate</h4>
                    <p class="text-sm leading-relaxed text-slate-500">Promovam bilete digitale si practici eco-friendly in industria evenimentelor.</p>
                </div>
            </div>
        </section>

        <!-- Team -->
        <section class="mb-24">
            <div class="mb-12 text-center">
                <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Echipa</span>
                <h2 class="mb-3 text-3xl font-extrabold md:text-4xl text-slate-800">Oamenii din spatele AmBilet</h2>
                <p class="text-[17px] text-slate-500">O echipa mica dar dedicata, unita de pasiunea pentru evenimente si tehnologie.</p>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 md:gap-8">
                <div class="text-center">
                    <div class="relative flex items-center justify-center mx-auto mb-5 overflow-hidden text-4xl font-bold rounded-full w-36 h-36 md:w-40 md:h-40 bg-gradient-to-br from-slate-200 to-slate-300 md:text-5xl text-slate-400">
                        <div class="absolute border-2 border-dashed rounded-full inset-1 border-slate-300"></div>
                        AP
                    </div>
                    <div class="mb-1 text-lg font-bold text-slate-800">Alexandru Popescu</div>
                    <div class="mb-3 text-sm font-medium text-primary">Co-fondator & CEO</div>
                    <div class="flex justify-center gap-2">
                        <a href="#" class="flex items-center justify-center transition-all rounded-lg w-9 h-9 bg-slate-100 text-slate-500 hover:bg-primary hover:text-white">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                        </a>
                        <a href="#" class="flex items-center justify-center transition-all rounded-lg w-9 h-9 bg-slate-100 text-slate-500 hover:bg-primary hover:text-white">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
                        </a>
                    </div>
                </div>
                <div class="text-center">
                    <div class="relative flex items-center justify-center mx-auto mb-5 overflow-hidden text-4xl font-bold rounded-full w-36 h-36 md:w-40 md:h-40 bg-gradient-to-br from-slate-200 to-slate-300 md:text-5xl text-slate-400">
                        <div class="absolute border-2 border-dashed rounded-full inset-1 border-slate-300"></div>
                        MI
                    </div>
                    <div class="mb-1 text-lg font-bold text-slate-800">Maria Ionescu</div>
                    <div class="mb-3 text-sm font-medium text-primary">Co-fondator & CTO</div>
                    <div class="flex justify-center gap-2">
                        <a href="#" class="flex items-center justify-center transition-all rounded-lg w-9 h-9 bg-slate-100 text-slate-500 hover:bg-primary hover:text-white">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                        </a>
                        <a href="#" class="flex items-center justify-center transition-all rounded-lg w-9 h-9 bg-slate-100 text-slate-500 hover:bg-primary hover:text-white">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                        </a>
                    </div>
                </div>
                <div class="text-center">
                    <div class="relative flex items-center justify-center mx-auto mb-5 overflow-hidden text-4xl font-bold rounded-full w-36 h-36 md:w-40 md:h-40 bg-gradient-to-br from-slate-200 to-slate-300 md:text-5xl text-slate-400">
                        <div class="absolute border-2 border-dashed rounded-full inset-1 border-slate-300"></div>
                        AD
                    </div>
                    <div class="mb-1 text-lg font-bold text-slate-800">Andrei Dumitrescu</div>
                    <div class="mb-3 text-sm font-medium text-primary">Head of Product</div>
                    <div class="flex justify-center gap-2">
                        <a href="#" class="flex items-center justify-center transition-all rounded-lg w-9 h-9 bg-slate-100 text-slate-500 hover:bg-primary hover:text-white">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                        </a>
                    </div>
                </div>
                <div class="text-center">
                    <div class="relative flex items-center justify-center mx-auto mb-5 overflow-hidden text-4xl font-bold rounded-full w-36 h-36 md:w-40 md:h-40 bg-gradient-to-br from-slate-200 to-slate-300 md:text-5xl text-slate-400">
                        <div class="absolute border-2 border-dashed rounded-full inset-1 border-slate-300"></div>
                        ES
                    </div>
                    <div class="mb-1 text-lg font-bold text-slate-800">Elena Stan</div>
                    <div class="mb-3 text-sm font-medium text-primary">Customer Success</div>
                    <div class="flex justify-center gap-2">
                        <a href="#" class="flex items-center justify-center transition-all rounded-lg w-9 h-9 bg-slate-100 text-slate-500 hover:bg-primary hover:text-white">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Timeline -->
        <section class="mb-24">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Parcursul nostru</span>
            <div class="relative pl-10">
                <div class="absolute left-[7px] top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary to-slate-200"></div>
                <div class="relative pb-12">
                    <div class="absolute -left-10 top-1 w-4 h-4 rounded-full bg-primary border-[3px] border-slate-50 shadow-[0_0_0_3px_rgba(165,28,48,0.2)]"></div>
                    <div class="mb-2 text-sm font-bold text-primary">Ianuarie 2024</div>
                    <div class="mb-2 text-xl font-bold text-slate-800">Fondarea AmBilet</div>
                    <div class="text-[15px] text-slate-500 leading-relaxed">Am pornit cu o echipa de 2 persoane si o viziune clara: sa simplificam ticketing-ul in Romania.</div>
                </div>
                <div class="relative pb-12">
                    <div class="absolute -left-10 top-1 w-4 h-4 rounded-full bg-primary border-[3px] border-slate-50 shadow-[0_0_0_3px_rgba(165,28,48,0.2)]"></div>
                    <div class="mb-2 text-sm font-bold text-primary">Martie 2024</div>
                    <div class="mb-2 text-xl font-bold text-slate-800">Lansarea platformei</div>
                    <div class="text-[15px] text-slate-500 leading-relaxed">Prima versiune a platformei a fost lansata, cu primii 10 organizatori de test.</div>
                </div>
                <div class="relative pb-12">
                    <div class="absolute -left-10 top-1 w-4 h-4 rounded-full bg-primary border-[3px] border-slate-50 shadow-[0_0_0_3px_rgba(165,28,48,0.2)]"></div>
                    <div class="mb-2 text-sm font-bold text-primary">Iunie 2024</div>
                    <div class="mb-2 text-xl font-bold text-slate-800">100 de organizatori</div>
                    <div class="text-[15px] text-slate-500 leading-relaxed">Am atins milestone-ul de 100 de organizatori activi pe platforma.</div>
                </div>
                <div class="relative">
                    <div class="absolute -left-10 top-1 w-4 h-4 rounded-full bg-primary border-[3px] border-slate-50 shadow-[0_0_0_3px_rgba(165,28,48,0.2)]"></div>
                    <div class="mb-2 text-sm font-bold text-primary">Decembrie 2024</div>
                    <div class="mb-2 text-xl font-bold text-slate-800">100.000 de bilete vandute</div>
                    <div class="text-[15px] text-slate-500 leading-relaxed">Am celebrat vanzarea biletului cu numarul 100.000 si am lansat aplicatia mobila.</div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-[32px] p-12 md:p-20 text-center relative overflow-hidden">
            <div class="absolute -top-[150px] -right-[150px] w-[400px] h-[400px] bg-[radial-gradient(circle,rgba(165,28,48,0.2),transparent_70%)] pointer-events-none"></div>
            <div class="relative z-10">
                <h2 class="text-3xl md:text-[40px] font-extrabold text-white mb-4">Hai sa construim impreuna</h2>
                <p class="max-w-lg mx-auto mb-8 text-lg text-white/90">Vrei sa faci parte din echipa AmBilet sau sa colaborezi cu noi?</p>
                <div class="flex flex-col justify-center gap-4 sm:flex-row">
                    <a href="/cariere" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 md:px-9 md:py-[18px] rounded-xl text-base font-bold bg-gradient-to-br from-primary to-red-600 text-white hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(165,28,48,0.4)] transition-all">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        Vezi cariere
                    </a>
                    <a href="/contact" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 md:px-9 md:py-[18px] rounded-xl text-base font-bold bg-white/10 border border-white/20 text-white hover:bg-white/15 transition-all">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Contacteaza-ne
                    </a>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
