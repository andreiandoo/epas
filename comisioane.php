<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Comisioane';
$transparentHeader = true;
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-16 md:py-20 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-3xl mx-auto text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary/20 border border-primary/30 rounded-full text-sm font-semibold text-accent mb-6">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Preturi transparente
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4 tracking-tight">Comisioane simple si corecte</h1>
            <p class="text-lg text-white/70 leading-relaxed">Fara costuri ascunse. Platesti doar pentru ce folosesti. Primii 100 de bilete sunt intotdeauna gratuit.</p>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-16 md:py-20">
        <!-- Pricing Cards -->
        <section class="mb-20">
            <div class="text-center mb-12">
                <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Planuri</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800 mb-4">Alege planul potrivit</h2>
                <p class="text-[17px] text-slate-500 max-w-xl mx-auto leading-relaxed">De la evenimente mici la festivaluri, avem solutia pentru tine.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 md:gap-8">
                <!-- Starter -->
                <div class="bg-white rounded-3xl p-8 md:p-10 border-2 border-slate-200 hover:border-slate-300 hover:shadow-[0_20px_60px_rgba(0,0,0,0.08)] transition-all">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-100 to-sky-200 text-sky-600 flex items-center justify-center mb-6">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    </div>
                    <div class="text-2xl font-bold text-slate-800 mb-2">Starter</div>
                    <div class="text-sm text-slate-500 mb-6 leading-relaxed">Pentru organizatori care incep si vor sa testeze platforma.</div>
                    <div class="mb-8 pb-8 border-b border-slate-200">
                        <div class="text-5xl font-extrabold text-slate-800">2% <span class="text-lg font-medium text-slate-500">+ 1 RON/bilet</span></div>
                        <div class="text-sm text-slate-400 mt-2">Primele 100 bilete gratuit</div>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Pana la 500 bilete/eveniment</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Dashboard de baza</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Suport prin email</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Bilete PDF</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-400"><svg class="w-5 h-5 text-slate-300 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Branding personalizat</li>
                    </ul>
                    <a href="/organizer/register" class="block w-full py-4 text-center bg-slate-100 rounded-xl text-[15px] font-bold text-slate-700 hover:bg-slate-200 transition-all">Incepe gratuit</a>
                </div>

                <!-- Pro (Featured) -->
                <div class="bg-white rounded-3xl p-8 md:p-10 border-2 border-primary relative hover:shadow-[0_20px_60px_rgba(0,0,0,0.08)] transition-all">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-full text-xs font-bold text-white">Popular</div>
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary/15 to-primary/5 text-primary flex items-center justify-center mb-6">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <div class="text-2xl font-bold text-slate-800 mb-2">Pro</div>
                    <div class="text-sm text-slate-500 mb-6 leading-relaxed">Pentru organizatori activi cu evenimente regulate.</div>
                    <div class="mb-8 pb-8 border-b border-slate-200">
                        <div class="text-5xl font-extrabold text-slate-800">1.5% <span class="text-lg font-medium text-slate-500">+ 0.5 RON/bilet</span></div>
                        <div class="text-sm text-slate-400 mt-2">De la 10 evenimente/an</div>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Bilete nelimitate</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Dashboard avansat + rapoarte</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Suport prioritar</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Branding personalizat</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Aplicatie check-in</li>
                    </ul>
                    <a href="/contact" class="block w-full py-4 text-center bg-gradient-to-br from-primary to-red-600 rounded-xl text-[15px] font-bold text-white hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(165,28,48,0.3)] transition-all">Contacteaza-ne</a>
                </div>

                <!-- Enterprise -->
                <div class="bg-white rounded-3xl p-8 md:p-10 border-2 border-slate-200 hover:border-slate-300 hover:shadow-[0_20px_60px_rgba(0,0,0,0.08)] transition-all">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-slate-800 to-slate-700 text-white flex items-center justify-center mb-6">
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    </div>
                    <div class="text-2xl font-bold text-slate-800 mb-2">Enterprise</div>
                    <div class="text-sm text-slate-500 mb-6 leading-relaxed">Pentru festivaluri mari si companii de productie.</div>
                    <div class="mb-8 pb-8 border-b border-slate-200">
                        <div class="text-5xl font-extrabold text-slate-800">Custom</div>
                        <div class="text-sm text-slate-400 mt-2">Comision negociabil</div>
                    </div>
                    <ul class="space-y-4 mb-8">
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Totul din Pro</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Account manager dedicat</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>SLA garantat</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Integrare API completa</li>
                        <li class="flex items-start gap-3 text-[15px] text-slate-700"><svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>White-label disponibil</li>
                    </ul>
                    <a href="/contact" class="block w-full py-4 text-center bg-slate-100 rounded-xl text-[15px] font-bold text-slate-700 hover:bg-slate-200 transition-all">Contacteaza vanzari</a>
                </div>
            </div>

            <!-- Info Boxes -->
            <div class="grid md:grid-cols-3 gap-6 mt-12">
                <div class="bg-white rounded-2xl p-7 border border-slate-200">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </div>
                    <h4 class="text-base font-bold text-slate-800 mb-2">Procesare plati incluse</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">Comisioanele includ procesarea platilor prin card. Nu exista taxe suplimentare ascunse.</p>
                </div>
                <div class="bg-white rounded-2xl p-7 border border-slate-200">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h4 class="text-base font-bold text-slate-800 mb-2">Plati rapide</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">Primesti banii in cont in maxim 3 zile lucratoare dupa eveniment.</p>
                </div>
                <div class="bg-white rounded-2xl p-7 border border-slate-200">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 text-primary flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h4 class="text-base font-bold text-slate-800 mb-2">Garantie de satisfactie</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">Nu esti multumit? Primesti banii inapoi pentru primul eveniment.</p>
                </div>
            </div>
        </section>

        <!-- Calculator -->
        <section class="mb-20">
            <div class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-8 md:p-12 grid md:grid-cols-2 gap-10 md:gap-12 items-center">
                <div>
                    <h3 class="text-2xl md:text-[28px] font-bold text-white mb-4">Calculeaza costurile</h3>
                    <p class="text-base text-white/70 leading-relaxed">Introdu detaliile evenimentului tau pentru a vedea exact cat vei plati. Nicio surpriza, doar transparenta.</p>
                </div>
                <div class="bg-white/5 rounded-2xl p-6 md:p-8 border border-white/10">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-white/80 mb-2">Pret bilet (RON)</label>
                            <input type="number" id="ticketPrice" value="100" placeholder="ex: 100" class="w-full px-4 py-3.5 bg-white/10 border border-white/20 rounded-xl text-base text-white placeholder:text-white/40 focus:outline-none focus:border-primary focus:bg-white/15">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-white/80 mb-2">Numar bilete</label>
                            <input type="number" id="ticketCount" value="500" placeholder="ex: 500" class="w-full px-4 py-3.5 bg-white/10 border border-white/20 rounded-xl text-base text-white placeholder:text-white/40 focus:outline-none focus:border-primary focus:bg-white/15">
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-white/80 mb-2">Plan</label>
                        <select id="planSelect" class="w-full px-4 py-3.5 bg-white/10 border border-white/20 rounded-xl text-base text-white focus:outline-none focus:border-primary focus:bg-white/15">
                            <option value="starter">Starter (2% + 1 RON)</option>
                            <option value="pro">Pro (1.5% + 0.5 RON)</option>
                        </select>
                    </div>
                    <div class="pt-6 border-t border-white/10 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-white/60">Venituri totale</span>
                            <span class="text-base font-semibold text-white" id="totalRevenue">50,000 RON</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-white/60">Comision AmBilet</span>
                            <span class="text-base font-semibold text-white" id="totalFee">1,500 RON</span>
                        </div>
                        <div class="flex justify-between items-center pt-4 mt-2 border-t border-white/10">
                            <span class="text-base font-semibold text-white">Primesti in cont</span>
                            <span class="text-2xl font-extrabold text-accent" id="netRevenue">48,500 RON</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="mb-20">
            <div class="text-center mb-12">
                <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-6">Intrebari frecvente</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800">Ai intrebari despre preturi?</h2>
            </div>
            <div class="max-w-3xl mx-auto space-y-4">
                <div class="faq-item bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="faq-question flex items-center justify-between p-6 cursor-pointer hover:bg-slate-50 transition-colors">
                        <h4 class="text-base font-semibold text-slate-800 pr-4">Cine plateste comisionul - organizatorul sau cumparatorul?</h4>
                        <svg class="w-6 h-6 text-slate-500 flex-shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="faq-answer hidden px-6 pb-6 text-[15px] text-slate-500 leading-relaxed">Tu alegi! Poti absorbi comisionul in pretul biletului (cumparatorul vede doar pretul final) sau il poti adauga separat (cumparatorul vede pretul + taxa serviciu). Majoritatea organizatorilor aleg sa includa comisionul in pret pentru o experienta mai buna.</div>
                </div>
                <div class="faq-item bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="faq-question flex items-center justify-between p-6 cursor-pointer hover:bg-slate-50 transition-colors">
                        <h4 class="text-base font-semibold text-slate-800 pr-4">Exista costuri pentru evenimentele gratuite?</h4>
                        <svg class="w-6 h-6 text-slate-500 flex-shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="faq-answer hidden px-6 pb-6 text-[15px] text-slate-500 leading-relaxed">Nu! Evenimentele cu intrare gratuita sunt 100% gratuite pe AmBilet. Poti gestiona inregistrari si check-in fara niciun cost.</div>
                </div>
                <div class="faq-item bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="faq-question flex items-center justify-between p-6 cursor-pointer hover:bg-slate-50 transition-colors">
                        <h4 class="text-base font-semibold text-slate-800 pr-4">Cand primesc banii din vanzari?</h4>
                        <svg class="w-6 h-6 text-slate-500 flex-shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="faq-answer hidden px-6 pb-6 text-[15px] text-slate-500 leading-relaxed">In mod standard, transferam banii in maximum 3 zile lucratoare dupa eveniment. Pentru clientii Enterprise, oferim optiunea de plati saptamanale sau in avans.</div>
                </div>
                <div class="faq-item bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="faq-question flex items-center justify-between p-6 cursor-pointer hover:bg-slate-50 transition-colors">
                        <h4 class="text-base font-semibold text-slate-800 pr-4">Pot schimba planul ulterior?</h4>
                        <svg class="w-6 h-6 text-slate-500 flex-shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="faq-answer hidden px-6 pb-6 text-[15px] text-slate-500 leading-relaxed">Da, poti face upgrade sau downgrade oricand. Modificarea se aplica incepand cu urmatorul eveniment creat.</div>
                </div>
                <div class="faq-item bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <div class="faq-question flex items-center justify-between p-6 cursor-pointer hover:bg-slate-50 transition-colors">
                        <h4 class="text-base font-semibold text-slate-800 pr-4">Exista taxe pentru procesarea platilor?</h4>
                        <svg class="w-6 h-6 text-slate-500 flex-shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="faq-answer hidden px-6 pb-6 text-[15px] text-slate-500 leading-relaxed">Nu. Comisioanele noastre includ deja costurile de procesare a platilor prin card. Nu exista taxe suplimentare ascunse.</div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-white border-2 border-slate-200 rounded-3xl p-10 md:p-16 text-center">
            <h2 class="text-2xl md:text-[32px] font-extrabold text-slate-800 mb-4">Pregatit sa incepi?</h2>
            <p class="text-[17px] text-slate-500 mb-8">Creeaza contul gratuit si organizeaza primul tau eveniment in cateva minute.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/organizer/register" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-[15px] font-bold bg-gradient-to-br from-primary to-red-600 text-white hover:-translate-y-0.5 hover:shadow-[0_12px_32px_rgba(165,28,48,0.3)] transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>
                    Creeaza cont gratuit
                </a>
                <a href="/contact" class="inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-[15px] font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Vorbeste cu vanzari
                </a>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
// FAQ Accordion
document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
        const item = question.parentElement;
        const answer = item.querySelector('.faq-answer');
        const icon = question.querySelector('svg');

        // Close all other items
        document.querySelectorAll('.faq-item').forEach(other => {
            if (other !== item) {
                other.querySelector('.faq-answer').classList.add('hidden');
                other.querySelector('.faq-question svg').style.transform = '';
            }
        });

        // Toggle current item
        answer.classList.toggle('hidden');
        icon.style.transform = answer.classList.contains('hidden') ? '' : 'rotate(180deg)';
    });
});

// Calculator
function calculateFees() {
    const price = parseFloat(document.getElementById('ticketPrice').value) || 0;
    const count = parseInt(document.getElementById('ticketCount').value) || 0;
    const plan = document.getElementById('planSelect').value;

    const totalRevenue = price * count;
    let fee;

    if (plan === 'starter') {
        fee = (totalRevenue * 0.02) + (count * 1);
    } else {
        fee = (totalRevenue * 0.015) + (count * 0.5);
    }

    const netRevenue = totalRevenue - fee;

    document.getElementById('totalRevenue').textContent = totalRevenue.toLocaleString('ro-RO') + ' RON';
    document.getElementById('totalFee').textContent = fee.toLocaleString('ro-RO') + ' RON';
    document.getElementById('netRevenue').textContent = netRevenue.toLocaleString('ro-RO') + ' RON';
}

document.getElementById('ticketPrice').addEventListener('input', calculateFees);
document.getElementById('ticketCount').addEventListener('input', calculateFees);
document.getElementById('planSelect').addEventListener('change', calculateFees);

calculateFees();
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';
?>
