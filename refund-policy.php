<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Politica de Returnare';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body class="flex flex-col min-h-screen font-['Plus_Jakarta_Sans'] bg-surface text-secondary">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-secondary to-slate-900 py-20 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-48 -right-24 w-[500px] h-[500px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)]"></div>
        <div class="max-w-3xl mx-auto text-center relative z-10">
            <div class="w-20 h-20 rounded-2xl bg-primary/20 flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            </div>
            <h1 class="text-3xl md:text-[44px] font-extrabold text-white mb-4">Politica de Returnare</h1>
            <p class="text-lg text-white/90 leading-relaxed">Informatii complete despre rambursari, anulari si procedurile de returnare a biletelor.</p>
            <div class="mt-6 text-sm text-white/90">Ultima actualizare: 15 Decembrie 2024</div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="max-w-[900px] mx-auto px-4 md:px-12 py-12 md:py-16">
            <!-- Alert Box -->
            <div class="flex gap-4 p-6 bg-amber-50 border border-amber-300 rounded-2xl mb-8">
                <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    <h3 class="text-base font-bold text-amber-900 mb-1">Nota importanta</h3>
                    <p class="text-sm text-amber-700 leading-relaxed">Politica de returnare poate varia in functie de organizatorul evenimentului. Verifica intotdeauna termenii specifici afisati pe pagina evenimentului inainte de achizitie.</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10">
                <div class="bg-white rounded-2xl p-6 border border-border text-center">
                    <div class="w-14 h-14 rounded-xl bg-green-100 text-green-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-secondary mb-2">Eveniment anulat</h3>
                    <p class="text-sm text-muted leading-relaxed">Rambursare 100% automata in 5-10 zile lucratoare</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-border text-center">
                    <div class="w-14 h-14 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-secondary mb-2">Renuntare anticipata</h3>
                    <p class="text-sm text-muted leading-relaxed">Rambursare posibila cu minim 7 zile inainte</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-border text-center">
                    <div class="w-14 h-14 rounded-xl bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-secondary mb-2">Sub 48 de ore</h3>
                    <p class="text-sm text-muted leading-relaxed">Rambursare de regula nu este posibila</p>
                </div>
            </div>

            <!-- When Can I Get Refund -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Cand pot obtine rambursare?
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Dreptul la rambursare depinde de mai multi factori: tipul evenimentului, politica organizatorului si timpul ramas pana la eveniment.</p>

                <div class="space-y-5 mt-6">
                    <!-- Scenario 1 -->
                    <div class="flex gap-5 p-6 bg-surface rounded-2xl border border-border">
                        <div class="w-12 h-12 rounded-xl bg-green-100 text-green-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-2">Eveniment anulat de organizator</h4>
                            <p class="text-sm text-muted leading-relaxed mb-2">Daca organizatorul anuleaza evenimentul, primesti automat rambursarea completa a sumei platite, inclusiv taxele de serviciu.</p>
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-600">Rambursare 100%</span>
                        </div>
                    </div>

                    <!-- Scenario 2 -->
                    <div class="flex gap-5 p-6 bg-surface rounded-2xl border border-border">
                        <div class="w-12 h-12 rounded-xl bg-green-100 text-green-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-2">Eveniment reprogramat (nu poti participa)</h4>
                            <p class="text-sm text-muted leading-relaxed mb-2">Daca evenimentul este mutat la o noua data si nu poti participa, poti solicita rambursarea in termen de 14 zile de la anunt.</p>
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-600">Rambursare 100%</span>
                        </div>
                    </div>

                    <!-- Scenario 3 -->
                    <div class="flex gap-5 p-6 bg-surface rounded-2xl border border-border">
                        <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-2">Renuntare cu 7+ zile inainte</h4>
                            <p class="text-sm text-muted leading-relaxed mb-2">Poti solicita rambursarea cu minim 7 zile inainte de eveniment. Taxa de serviciu poate fi retinuta.</p>
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-600">Rambursare partiala</span>
                        </div>
                    </div>

                    <!-- Scenario 4 -->
                    <div class="flex gap-5 p-6 bg-surface rounded-2xl border border-border">
                        <div class="w-12 h-12 rounded-xl bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-2">Renuntare sub 48 de ore</h4>
                            <p class="text-sm text-muted leading-relaxed mb-2">In majoritatea cazurilor, rambursarea nu este posibila cu mai putin de 48 de ore inainte de eveniment.</p>
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-600">Fara rambursare</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terms & Conditions Timeline -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Termene si conditii
                </h2>

                <div class="mt-6">
                    <div class="flex flex-col md:flex-row gap-4 md:gap-5 py-5 border-b border-slate-100">
                        <div class="md:w-36 flex-shrink-0">
                            <span class="inline-block px-4 py-2 bg-gradient-to-r from-primary/10 to-primary/5 rounded-lg text-sm font-bold text-primary">30+ zile</span>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">Rambursare completa disponibila</h4>
                            <p class="text-sm text-muted leading-relaxed">Cu mai mult de 30 de zile inainte de eveniment, poti obtine rambursarea completa a biletului, inclusiv taxa de serviciu.</p>
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row gap-4 md:gap-5 py-5 border-b border-slate-100">
                        <div class="md:w-36 flex-shrink-0">
                            <span class="inline-block px-4 py-2 bg-gradient-to-r from-primary/10 to-primary/5 rounded-lg text-sm font-bold text-primary">7-30 zile</span>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">Rambursare cu retinere taxa</h4>
                            <p class="text-sm text-muted leading-relaxed">Primesti valoarea biletului minus taxa de serviciu AmBilet (de regula 5-10% din valoare).</p>
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row gap-4 md:gap-5 py-5 border-b border-slate-100">
                        <div class="md:w-36 flex-shrink-0">
                            <span class="inline-block px-4 py-2 bg-gradient-to-r from-primary/10 to-primary/5 rounded-lg text-sm font-bold text-primary">48h - 7 zile</span>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">La discretia organizatorului</h4>
                            <p class="text-sm text-muted leading-relaxed">Rambursarea depinde de politica organizatorului. Contacteaza-ne pentru a verifica optiunile disponibile.</p>
                        </div>
                    </div>
                    <div class="flex flex-col md:flex-row gap-4 md:gap-5 py-5">
                        <div class="md:w-36 flex-shrink-0">
                            <span class="inline-block px-4 py-2 bg-gradient-to-r from-primary/10 to-primary/5 rounded-lg text-sm font-bold text-primary">Sub 48h</span>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">Rambursare indisponibila</h4>
                            <p class="text-sm text-muted leading-relaxed">De regula, nu se acorda rambursari cu mai putin de 48 de ore inainte de eveniment.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How to Request Refund -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Cum solicit rambursarea?
                </h2>

                <div class="mt-6">
                    <div class="flex gap-5 py-5 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-red-700 flex items-center justify-center text-base font-extrabold text-white flex-shrink-0">1</div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">Acceseaza contul tau</h4>
                            <p class="text-sm text-muted leading-relaxed">Intra in contul AmBilet si navigheaza la sectiunea "Comenzile mele" sau "Biletele mele".</p>
                        </div>
                    </div>
                    <div class="flex gap-5 py-5 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-red-700 flex items-center justify-center text-base font-extrabold text-white flex-shrink-0">2</div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">Selecteaza comanda</h4>
                            <p class="text-sm text-muted leading-relaxed">Gaseste comanda pentru care doresti rambursarea si deschide detaliile acesteia.</p>
                        </div>
                    </div>
                    <div class="flex gap-5 py-5 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-red-700 flex items-center justify-center text-base font-extrabold text-white flex-shrink-0">3</div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">Solicita rambursarea</h4>
                            <p class="text-sm text-muted leading-relaxed">Apasa butonul "Solicita rambursare" si completeaza motivul cererii.</p>
                        </div>
                    </div>
                    <div class="flex gap-5 py-5">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-red-700 flex items-center justify-center text-base font-extrabold text-white flex-shrink-0">4</div>
                        <div>
                            <h4 class="text-base font-bold text-secondary mb-1">Asteapta confirmarea</h4>
                            <p class="text-sm text-muted leading-relaxed">Vei primi un email de confirmare. Rambursarea se proceseaza in 5-10 zile lucratoare.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Refund Processing -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    Procesarea rambursarii
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Odata aprobata cererea de rambursare, suma va fi returnata prin aceeasi metoda de plata folosita la achizitie:</p>

                <ul class="my-4 ml-6 space-y-2">
                    <li class="text-[15px] text-slate-600 leading-relaxed"><strong>Card bancar:</strong> 5-10 zile lucratoare pentru procesare</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed"><strong>Apple Pay / Google Pay:</strong> 5-10 zile lucratoare</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed"><strong>Card cadou AmBilet:</strong> Instant, credit in cont</li>
                </ul>

                <p class="text-base text-slate-600 leading-relaxed mb-4">Timpul exact de procesare poate varia in functie de banca emitenta a cardului. Daca nu primesti rambursarea in termen de 14 zile de la aprobare, te rugam sa ne contactezi.</p>

                <h3 class="text-lg font-bold text-secondary mt-7 mb-3">Rambursare partiala</h3>
                <p class="text-base text-slate-600 leading-relaxed">In cazul comenzilor cu mai multe bilete, poti solicita rambursarea pentru unul sau mai multe bilete, nu neaparat pentru intreaga comanda.</p>
            </div>

            <!-- No Refund Situations -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    Situatii fara drept la rambursare
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Rambursarea nu se acorda in urmatoarele situatii:</p>

                <ul class="my-4 ml-6 space-y-2 list-disc">
                    <li class="text-[15px] text-slate-600 leading-relaxed">Bilete folosite sau scanate la intrare</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed">Cerere facuta cu mai putin de 48 de ore inainte de eveniment</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed">Bilete pentru evenimente marcate explicit ca "Fara returnare"</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed">Bilete achizitionate la promotii speciale non-rambursabile</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed">Schimbarea planurilor personale fara motiv valid</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed">Bilete transferate catre alte persoane</li>
                </ul>
            </div>

            <!-- CTA Box -->
            <div class="bg-white border-2 border-border rounded-2xl p-8 text-center mb-10">
                <h3 class="text-xl font-bold text-secondary mb-2">Ai nevoie de ajutor?</h3>
                <p class="text-[15px] text-muted mb-5">Echipa noastra de suport iti poate raspunde la orice intrebari despre rambursari.</p>
                <a href="/contact" class="inline-flex items-center gap-2 px-7 py-3.5 bg-gradient-to-r from-primary to-red-700 text-white rounded-xl text-[15px] font-bold hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30 transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contacteaza suportul
                </a>
            </div>

            <!-- Contact Section -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contacteaza-ne
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Daca ai intrebari sau ai nevoie de asistenta pentru o cerere de rambursare, suntem aici sa te ajutam.</p>

                <div class="bg-gradient-to-br from-secondary to-slate-600 rounded-2xl p-8 mt-8">
                    <h3 class="text-xl font-bold text-white mb-3">Echipa de suport</h3>
                    <p class="text-[15px] text-white/90 mb-5">Timpul mediu de raspuns: sub 4 ore in zilele lucratoare.</p>
                    <div class="flex flex-col md:flex-row gap-4">
                        <a href="mailto:suport@ambilet.ro" class="flex items-center gap-2.5 px-5 py-3 bg-white/10 rounded-xl text-white text-sm font-medium hover:bg-white/15 transition-colors">
                            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            suport@ambilet.ro
                        </a>
                        <a href="tel:+40312345678" class="flex items-center gap-2.5 px-5 py-3 bg-white/10 rounded-xl text-white text-sm font-medium hover:bg-white/15 transition-colors">
                            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            +40 31 234 5678
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <?php include __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
