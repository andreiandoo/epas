<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Parteneri';
$transparentHeader = false;
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-20 md:py-24 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-[300px] -right-[300px] w-[800px] h-[800px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="absolute -bottom-[200px] -left-[200px] w-[500px] h-[500px] bg-[radial-gradient(circle,rgba(165,28,48,0.1)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/20 border border-primary/30 rounded-full text-sm font-semibold text-accent mb-6">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Parteneri AmBilet
            </div>
            <h1 class="text-4xl md:text-[56px] font-extrabold text-white mb-5 leading-tight tracking-tight">Crestem impreuna</h1>
            <p class="text-lg md:text-xl text-white/90 leading-relaxed max-w-2xl mx-auto">Alatura-te retelei noastre de parteneri si beneficiaza de avantaje exclusive. Impreuna construim viitorul evenimentelor din Romania.</p>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-16 md:py-20">
        <!-- Stats Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8 bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-8 md:p-12 mb-20">
            <div class="text-center">
                <div class="text-4xl md:text-5xl font-extrabold text-white mb-2">50+</div>
                <div class="text-sm text-white/90">Parteneri activi</div>
            </div>
            <div class="text-center">
                <div class="text-4xl md:text-5xl font-extrabold text-white mb-2">100K+</div>
                <div class="text-sm text-white/90">Clienti referiti</div>
            </div>
            <div class="text-center">
                <div class="text-4xl md:text-5xl font-extrabold text-white mb-2">25%</div>
                <div class="text-sm text-white/90">Comision mediu</div>
            </div>
            <div class="text-center">
                <div class="text-4xl md:text-5xl font-extrabold text-white mb-2">48h</div>
                <div class="text-sm text-white/90">Timp de aprobare</div>
            </div>
        </div>

        <!-- Partner Types -->
        <section class="mb-24">
            <div class="text-center mb-12">
                <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Tipuri de parteneriate</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800 mb-4">Alege programul potrivit pentru tine</h2>
                <p class="text-[17px] text-slate-500 max-w-xl mx-auto leading-relaxed">Oferim mai multe tipuri de parteneriate, adaptate nevoilor si obiectivelor tale.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-6 md:gap-8">
                <div class="bg-white rounded-3xl p-8 md:p-10 border border-slate-200 text-center hover:-translate-y-2 hover:shadow-[0_20px_60px_rgba(0,0,0,0.1)] hover:border-primary transition-all">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>
                    </div>
                    <h3 class="text-[22px] font-bold text-slate-800 mb-3">Afiliat</h3>
                    <p class="text-[15px] text-slate-500 leading-relaxed mb-6">Castiga comision pentru fiecare client pe care il referi catre AmBilet. Perfect pentru bloggeri, influenceri si creatori de continut.</p>
                    <a href="/contact?type=affiliate" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-br from-primary to-red-600 text-white rounded-xl text-sm font-semibold hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(165,28,48,0.3)] transition-all">
                        Aplica acum
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
                <div class="bg-white rounded-3xl p-8 md:p-10 border border-slate-200 text-center hover:-translate-y-2 hover:shadow-[0_20px_60px_rgba(0,0,0,0.1)] hover:border-primary transition-all">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </div>
                    <h3 class="text-[22px] font-bold text-slate-800 mb-3">Reseller</h3>
                    <p class="text-[15px] text-slate-500 leading-relaxed mb-6">Ofera solutii de ticketing clientilor tai sub brandul propriu. Ideal pentru agentii de evenimente si companii de productie.</p>
                    <a href="/contact?type=reseller" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-br from-primary to-red-600 text-white rounded-xl text-sm font-semibold hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(165,28,48,0.3)] transition-all">
                        Aplica acum
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
                <div class="bg-white rounded-3xl p-8 md:p-10 border border-slate-200 text-center hover:-translate-y-2 hover:shadow-[0_20px_60px_rgba(0,0,0,0.1)] hover:border-primary transition-all">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    </div>
                    <h3 class="text-[22px] font-bold text-slate-800 mb-3">Integrare</h3>
                    <p class="text-[15px] text-slate-500 leading-relaxed mb-6">Integreaza AmBilet in platforma ta prin API. Pentru dezvoltatori si companii tech care vor sa ofere ticketing.</p>
                    <a href="/contact?type=integration" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-br from-primary to-red-600 text-white rounded-xl text-sm font-semibold hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(165,28,48,0.3)] transition-all">
                        Aplica acum
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Benefits -->
        <section class="mb-24">
            <div class="text-center mb-12">
                <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Beneficii</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800 mb-4">De ce sa devii partener AmBilet</h2>
                <p class="text-[17px] text-slate-500 max-w-xl mx-auto leading-relaxed">Partenerii nostri beneficiaza de avantaje exclusive si suport dedicat.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="flex gap-5 bg-white rounded-2xl p-8 border border-slate-200">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center flex-shrink-0">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2">Comisioane competitive</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">Castiga pana la 25% din valoarea fiecarei tranzactii generate prin referrals.</p>
                    </div>
                </div>
                <div class="flex gap-5 bg-white rounded-2xl p-8 border border-slate-200">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center flex-shrink-0">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2">Dashboard dedicat</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">Monitorizeaza performanta, track-uieste conversiile si gestioneaza platile dintr-un singur loc.</p>
                    </div>
                </div>
                <div class="flex gap-5 bg-white rounded-2xl p-8 border border-slate-200">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center flex-shrink-0">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2">Suport prioritar</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">Acces la echipa noastra de suport dedicata partenerilor, cu timp de raspuns garantat.</p>
                    </div>
                </div>
                <div class="flex gap-5 bg-white rounded-2xl p-8 border border-slate-200">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center flex-shrink-0">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2">Materiale de marketing</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">Primesti acces la banere, template-uri si ghiduri pentru promovare eficienta.</p>
                    </div>
                </div>
                <div class="flex gap-5 bg-white rounded-2xl p-8 border border-slate-200">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center flex-shrink-0">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2">Plati rapide</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">Primesti comisioanele lunar, direct in contul tau bancar, fara intarzieri.</p>
                    </div>
                </div>
                <div class="flex gap-5 bg-white rounded-2xl p-8 border border-slate-200">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center flex-shrink-0">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800 mb-2">Program de recunoastere</h4>
                        <p class="text-sm text-slate-500 leading-relaxed">Top performerii primesc bonusuri, invitatii la evenimente exclusive si vizibilitate pe platforma.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it Works -->
        <section class="mb-24">
            <div class="text-center mb-12">
                <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Cum functioneaza</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800">4 pasi simpli pentru a incepe</h2>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center relative">
                    <div class="hidden lg:block absolute top-10 right-0 w-[calc(100%-40px)] h-0.5 bg-slate-200 translate-x-1/2"></div>
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center mx-auto mb-5 text-3xl font-extrabold text-white relative z-10">1</div>
                    <h4 class="text-lg font-bold text-slate-800 mb-2">Aplica</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">Completeaza formularul de aplicare cu detalii despre tine si afacerea ta.</p>
                </div>
                <div class="text-center relative">
                    <div class="hidden lg:block absolute top-10 right-0 w-[calc(100%-40px)] h-0.5 bg-slate-200 translate-x-1/2"></div>
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center mx-auto mb-5 text-3xl font-extrabold text-white relative z-10">2</div>
                    <h4 class="text-lg font-bold text-slate-800 mb-2">Aprobare</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">Echipa noastra analizeaza aplicatia si te contacteaza in 48 de ore.</p>
                </div>
                <div class="text-center relative">
                    <div class="hidden lg:block absolute top-10 right-0 w-[calc(100%-40px)] h-0.5 bg-slate-200 translate-x-1/2"></div>
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center mx-auto mb-5 text-3xl font-extrabold text-white relative z-10">3</div>
                    <h4 class="text-lg font-bold text-slate-800 mb-2">Promoveaza</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">Primesti link-uri unice si materiale de marketing pentru a promova AmBilet.</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center mx-auto mb-5 text-3xl font-extrabold text-white relative z-10">4</div>
                    <h4 class="text-lg font-bold text-slate-800 mb-2">Castiga</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">Pentru fiecare client referit, primesti comision din tranzactiile generate.</p>
                </div>
            </div>
        </section>

        <!-- Partner Logos -->
        <section class="mb-24">
            <div class="bg-white rounded-3xl p-10 md:p-16 border border-slate-200">
                <div class="text-sm font-semibold text-slate-400 uppercase tracking-wider text-center mb-10">Partenerii nostri de incredere</div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 md:gap-10">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="h-[60px] bg-slate-50 rounded-xl flex items-center justify-center px-4">
                        <span class="text-lg font-bold text-slate-300">Partner <?= $i ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="mb-24">
            <div class="text-center mb-12">
                <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Testimoniale</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800">Ce spun partenerii nostri</h2>
            </div>
            <div class="grid md:grid-cols-2 gap-6 md:gap-8">
                <div class="bg-white rounded-3xl p-8 md:p-10 border border-slate-200 relative">
                    <div class="absolute top-6 left-8 text-7xl font-extrabold text-primary/10 font-serif leading-none">"</div>
                    <p class="text-[17px] text-slate-700 leading-relaxed mb-6 italic relative z-10">Programul de afiliere AmBilet mi-a adus un venit suplimentar consistent. Dashboard-ul este intuitiv si platile vin mereu la timp.</p>
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-lg font-bold text-slate-400">MC</div>
                        <div>
                            <div class="text-base font-bold text-slate-800">Mihai Constantinescu</div>
                            <div class="text-sm text-slate-500">Blogger, EventsRomania.ro</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-8 md:p-10 border border-slate-200 relative">
                    <div class="absolute top-6 left-8 text-7xl font-extrabold text-primary/10 font-serif leading-none">"</div>
                    <p class="text-[17px] text-slate-700 leading-relaxed mb-6 italic relative z-10">Ca agentie de evenimente, parteneriatul cu AmBilet ne-a permis sa oferim clientilor o solutie completa de ticketing fara investitii suplimentare.</p>
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-lg font-bold text-slate-400">AP</div>
                        <div>
                            <div class="text-base font-bold text-slate-800">Ana Popa</div>
                            <div class="text-sm text-slate-500">Director, EventPro Agency</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-gradient-to-br from-primary to-red-800 rounded-[32px] p-10 md:p-20 text-center relative overflow-hidden">
            <div class="absolute -top-[100px] -right-[100px] w-[300px] h-[300px] bg-[radial-gradient(circle,rgba(255,255,255,0.1),transparent_70%)] pointer-events-none"></div>
            <div class="absolute -bottom-[100px] -left-[100px] w-[300px] h-[300px] bg-[radial-gradient(circle,rgba(255,255,255,0.08),transparent_70%)] pointer-events-none"></div>
            <div class="relative z-10">
                <h2 class="text-3xl md:text-[40px] font-extrabold text-white mb-4">Devino partener AmBilet</h2>
                <p class="text-lg text-white/80 mb-8 max-w-lg mx-auto">Lasa-ne adresa ta de email si te contactam pentru a discuta despre oportunitati.</p>
                <form class="flex flex-col sm:flex-row gap-4 max-w-lg mx-auto">
                    <input type="email" placeholder="Adresa ta de email" class="flex-1 px-6 py-[18px] bg-white/15 border border-white/25 rounded-xl text-base text-white placeholder:text-white/90 focus:outline-none focus:bg-white/20 focus:border-white/40">
                    <button type="submit" class="px-9 py-[18px] bg-white rounded-xl text-base font-bold text-primary hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(0,0,0,0.2)] transition-all">Trimite</button>
                </form>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
