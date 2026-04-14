<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Press Kit';
$transparentHeader = false;
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-16 md:py-20 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-3xl mx-auto text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/20 border border-primary/30 rounded-full text-sm font-semibold text-accent mb-6">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Media Resources
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4 tracking-tight">Press Kit</h1>
            <p class="text-lg text-white/90 leading-relaxed mb-8 max-w-xl mx-auto">Tot ce ai nevoie pentru a scrie despre AmBilet. Logo-uri, culori, fonturi si informatii despre brand.</p>
            <a href="/assets/downloads/ambilet-press-kit.zip" class="inline-flex items-center gap-2.5 px-8 py-4 bg-gradient-to-br from-primary to-red-600 text-white rounded-xl text-[15px] font-bold hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(165,28,48,0.4)] transition-all">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descarca Press Kit
            </a>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-16 md:py-20">
        <!-- Brand Story -->
        <section class="mb-20">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Despre Noi</span>
            <div class="grid md:grid-cols-2 gap-10 md:gap-12 items-center">
                <div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-4">Povestea AmBilet</h3>
                    <p class="text-[15px] text-slate-500 leading-relaxed mb-4">AmBilet este platforma de ticketing creata pentru industria evenimentelor din Romania. Oferim organizatorilor uneltele necesare pentru a vinde bilete, gestiona participantii si a crea experiente memorabile.</p>
                    <p class="text-[15px] text-slate-500 leading-relaxed mb-8">Lansat in 2024, AmBilet s-a nascut din dorinta de a simplifica procesul de ticketing si de a conecta oamenii cu evenimentele pe care le iubesc.</p>
                    <div class="grid grid-cols-3 gap-4 md:gap-6">
                        <div class="text-center p-5 md:p-6 bg-slate-50 rounded-2xl">
                            <div class="text-3xl md:text-[32px] font-extrabold text-primary mb-1">500+</div>
                            <div class="text-sm text-slate-500">Evenimente</div>
                        </div>
                        <div class="text-center p-5 md:p-6 bg-slate-50 rounded-2xl">
                            <div class="text-3xl md:text-[32px] font-extrabold text-primary mb-1">100K+</div>
                            <div class="text-sm text-slate-500">Bilete vandute</div>
                        </div>
                        <div class="text-center p-5 md:p-6 bg-slate-50 rounded-2xl">
                            <div class="text-3xl md:text-[32px] font-extrabold text-primary mb-1">200+</div>
                            <div class="text-sm text-slate-500">Organizatori</div>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-10 md:p-12 flex items-center justify-center min-h-[350px] md:min-h-[400px]">
                    <div class="flex items-center gap-5">
                        <svg class="w-16 h-16 md:w-20 md:h-20" viewBox="0 0 48 48" fill="none">
                            <defs>
                                <linearGradient id="heroGrad" x1="6" y1="10" x2="42" y2="38">
                                    <stop stop-color="#A51C30"/>
                                    <stop offset="1" stop-color="#C41E3A"/>
                                </linearGradient>
                            </defs>
                            <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#heroGrad)"/>
                            <line x1="17" y1="15" x2="31" y2="15" stroke="white" stroke-opacity="0.25" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="15" y1="19" x2="33" y2="19" stroke="white" stroke-opacity="0.35" stroke-width="1.5" stroke-linecap="round"/>
                            <rect x="20" y="27" width="8" height="8" rx="1.5" fill="white"/>
                        </svg>
                        <div class="text-4xl md:text-[56px] font-extrabold flex">
                            <span class="text-white">Am</span>
                            <span class="text-accent">Bilet</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Logos -->
        <section class="mb-20">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Logo-uri</span>
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-4">Descarca logo-urile</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8 max-w-xl">Logo-urile sunt disponibile in mai multe formate pentru diverse utilizari.</p>
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Light background -->
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200 hover:border-primary hover:shadow-[0_8px_32px_rgba(165,28,48,0.1)] transition-all">
                    <div class="p-10 md:p-12 bg-slate-50 flex items-center justify-center min-h-[160px]">
                        <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
                            <defs><linearGradient id="dl1" x1="6" y1="10" x2="42" y2="38"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
                            <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#dl1)"/>
                            <rect x="20" y="27" width="8" height="8" rx="1.5" fill="white"/>
                        </svg>
                        <span class="text-[28px] font-extrabold ml-3 flex"><span class="text-slate-800">Am</span><span class="text-primary">Bilet</span></span>
                    </div>
                    <div class="p-5 md:p-6 border-t border-slate-200">
                        <div class="text-[15px] font-bold text-slate-800 mb-1">Logo Principal</div>
                        <div class="text-sm text-slate-500 mb-4">Pentru fundal deschis</div>
                        <div class="flex gap-2">
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">SVG</a>
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">PNG</a>
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">PDF</a>
                        </div>
                    </div>
                </div>
                <!-- Dark background -->
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200 hover:border-primary hover:shadow-[0_8px_32px_rgba(165,28,48,0.1)] transition-all">
                    <div class="p-10 md:p-12 bg-slate-800 flex items-center justify-center min-h-[160px]">
                        <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
                            <defs><linearGradient id="dl2" x1="6" y1="10" x2="42" y2="38"><stop stop-color="#A51C30"/><stop offset="1" stop-color="#C41E3A"/></linearGradient></defs>
                            <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="url(#dl2)"/>
                            <rect x="20" y="27" width="8" height="8" rx="1.5" fill="white"/>
                        </svg>
                        <span class="text-[28px] font-extrabold ml-3 flex"><span class="text-white">Am</span><span class="text-accent">Bilet</span></span>
                    </div>
                    <div class="p-5 md:p-6 border-t border-slate-200">
                        <div class="text-[15px] font-bold text-slate-800 mb-1">Logo pe Dark</div>
                        <div class="text-sm text-slate-500 mb-4">Pentru fundal inchis</div>
                        <div class="flex gap-2">
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">SVG</a>
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">PNG</a>
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">PDF</a>
                        </div>
                    </div>
                </div>
                <!-- Gradient background -->
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200 hover:border-primary hover:shadow-[0_8px_32px_rgba(165,28,48,0.1)] transition-all">
                    <div class="p-10 md:p-12 bg-gradient-to-br from-primary to-red-800 flex items-center justify-center min-h-[160px]">
                        <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
                            <path d="M8 13C8 10.79 9.79 9 12 9H36C38.21 9 40 10.79 40 13V19C37.79 19 36 20.79 36 23V25C36 27.21 37.79 29 40 29V35C40 37.21 38.21 39 36 39H12C9.79 39 8 37.21 8 35V29C10.21 29 12 27.21 12 25V23C12 20.79 10.21 19 8 19V13Z" fill="white"/>
                            <rect x="20" y="27" width="8" height="8" rx="1.5" fill="#A51C30"/>
                        </svg>
                        <span class="text-[28px] font-extrabold ml-3 flex"><span class="text-white/85">Am</span><span class="text-white">Bilet</span></span>
                    </div>
                    <div class="p-5 md:p-6 border-t border-slate-200">
                        <div class="text-[15px] font-bold text-slate-800 mb-1">Logo Mono White</div>
                        <div class="text-sm text-slate-500 mb-4">Pentru fundal colorat</div>
                        <div class="flex gap-2">
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">SVG</a>
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">PNG</a>
                            <a href="#" class="px-3.5 py-2 bg-slate-100 rounded-lg text-xs font-semibold text-slate-500 hover:bg-primary hover:text-white transition-all">PDF</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Colors -->
        <section class="mb-20">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Culori</span>
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-4">Paleta de culori</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8">Culorile oficiale ale brandului AmBilet.</p>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200">
                    <div class="h-[120px] bg-primary"></div>
                    <div class="p-5">
                        <div class="text-[15px] font-bold text-slate-800 mb-2">Primary Red</div>
                        <div class="space-y-1 font-mono text-sm text-slate-500">
                            <div>HEX: #A51C30</div>
                            <div>RGB: 165, 28, 48</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200">
                    <div class="h-[120px] bg-slate-800"></div>
                    <div class="p-5">
                        <div class="text-[15px] font-bold text-slate-800 mb-2">Dark Slate</div>
                        <div class="space-y-1 font-mono text-sm text-slate-500">
                            <div>HEX: #1E293B</div>
                            <div>RGB: 30, 41, 59</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200">
                    <div class="h-[120px] bg-red-400"></div>
                    <div class="p-5">
                        <div class="text-[15px] font-bold text-slate-800 mb-2">Coral Accent</div>
                        <div class="space-y-1 font-mono text-sm text-slate-500">
                            <div>HEX: #f87171</div>
                            <div>RGB: 248, 113, 113</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl overflow-hidden border border-slate-200">
                    <div class="h-[120px] bg-slate-50 border-b border-slate-200"></div>
                    <div class="p-5">
                        <div class="text-[15px] font-bold text-slate-800 mb-2">Light Gray</div>
                        <div class="space-y-1 font-mono text-sm text-slate-500">
                            <div>HEX: #F8FAFC</div>
                            <div>RGB: 248, 250, 252</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Typography -->
        <section class="mb-20">
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Tipografie</span>
            <h2 class="text-3xl md:text-[32px] font-extrabold text-slate-800 mb-4">Font principal</h2>
            <p class="text-base text-slate-500 leading-relaxed mb-8">Folosim Plus Jakarta Sans pentru toate materialele.</p>
            <div class="bg-white rounded-3xl border border-slate-200 p-6 md:p-10">
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-6 border-b border-slate-100">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Heading 1</div>
                        <div class="text-xs text-slate-400">48px / 800</div>
                    </div>
                    <div class="text-[32px] md:text-5xl font-extrabold text-slate-800 tracking-tight">AmBilet Platform</div>
                </div>
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-6 border-b border-slate-100">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Heading 2</div>
                        <div class="text-xs text-slate-400">36px / 700</div>
                    </div>
                    <div class="text-2xl md:text-4xl font-bold text-slate-800">Descopera evenimente</div>
                </div>
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-6 border-b border-slate-100">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Heading 3</div>
                        <div class="text-xs text-slate-400">24px / 700</div>
                    </div>
                    <div class="text-xl md:text-2xl font-bold text-slate-800">Bilete pentru orice ocazie</div>
                </div>
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-6 border-b border-slate-100">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Body</div>
                        <div class="text-xs text-slate-400">16px / 400</div>
                    </div>
                    <div class="text-base text-slate-500 leading-relaxed">AmBilet este platforma de ticketing care conecteaza organizatorii cu publicul lor.</div>
                </div>
                <div class="flex flex-col md:flex-row md:items-baseline gap-3 md:gap-8 py-6">
                    <div class="md:w-[120px] flex-shrink-0">
                        <div class="text-sm font-semibold text-slate-500">Small</div>
                        <div class="text-xs text-slate-400">14px / 400</div>
                    </div>
                    <div class="text-sm text-slate-400">Toate drepturile rezervate &copy; <?= date('Y') ?> AmBilet.ro</div>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-10 md:p-16 text-center relative overflow-hidden">
            <div class="absolute -top-[100px] -right-[100px] w-[300px] h-[300px] bg-[radial-gradient(circle,rgba(165,28,48,0.2),transparent_70%)] pointer-events-none"></div>
            <div class="relative z-10">
                <h2 class="text-2xl md:text-[32px] font-extrabold text-white mb-3">Contact pentru presa</h2>
                <p class="text-base text-white/90 mb-8">Pentru interviuri, parteneriate media sau informatii suplimentare.</p>
                <div class="inline-flex items-center gap-3 px-8 py-4 bg-white/10 border border-white/20 rounded-xl">
                    <svg class="w-6 h-6 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <a href="mailto:press@ambilet.ro" class="text-lg font-semibold text-white">press@ambilet.ro</a>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
