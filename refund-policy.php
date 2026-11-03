<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Politica de returnare și rambursare';
$cssBundle = 'static';
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
            <h1 class="text-3xl md:text-[44px] font-extrabold text-white mb-4">Politica de returnare și rambursare</h1>
            <p class="text-lg text-white/90 leading-relaxed">Ce se întâmplă cu biletul dacă nu mai poți ajunge sau dacă evenimentul este anulat.</p>
            <div class="mt-6 text-sm text-white/90">Ultima actualizare: 3 noiembrie 2026</div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="max-w-[900px] mx-auto px-4 md:px-12 py-12 md:py-16">
            <!-- Alert Box -->
            <div class="flex gap-4 p-6 bg-amber-50 border border-amber-300 rounded-2xl mb-8">
                <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    <h3 class="text-base font-bold text-amber-900 mb-1">Notă importantă</h3>
                    <p class="text-sm text-amber-700 leading-relaxed">Biletele pentru evenimente sunt nereturnabile. Rambursarea se face doar dacă evenimentul este anulat de organizator, iar taxa de procesare/ticketing nu se rambursează în niciun caz.</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10">
                <div class="bg-white rounded-2xl p-6 border border-border text-center">
                    <div class="w-14 h-14 rounded-xl bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-secondary mb-2">Renunțare la participare</h3>
                    <p class="text-sm text-muted leading-relaxed">Biletul nu se returnează, iar contravaloarea lui nu se rambursează</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-border text-center">
                    <div class="w-14 h-14 rounded-xl bg-green-100 text-green-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-secondary mb-2">Eveniment anulat</h3>
                    <p class="text-sm text-muted leading-relaxed">Rambursare conform condițiilor comunicate de organizator</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-border text-center">
                    <div class="w-14 h-14 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-secondary mb-2">Taxa de procesare</h3>
                    <p class="text-sm text-muted leading-relaxed">Nu se rambursează, nici dacă evenimentul este anulat</p>
                </div>
            </div>

            <!-- Policy -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Politica de returnare și rambursare
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Biletele achiziționate pentru eveniment sunt nereturnabile, în conformitate cu prevederile OUG nr. 34/2014, art. 16 lit. l), privind excepțiile de la dreptul de retragere pentru serviciile referitoare la activități de agrement, spectacole și concerte, atunci când contractul prevede o dată sau o perioadă specifică de executare.</p>
                <p class="text-base text-slate-600 leading-relaxed mb-4">În cazul în care cumpărătorul renunță, din orice motiv, la participarea la eveniment, contravaloarea biletului nu poate fi rambursată.</p>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Fac excepție situațiile în care evenimentul este anulat de organizator. În acest caz, rambursarea sumelor achitate se va realiza conform condițiilor comunicate de organizator și cu respectarea legislației aplicabile.</p>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Taxa de procesare/ticketing nu este rambursabilă, inclusiv în situația anulării evenimentului de către organizator.</p>
                <p class="text-base text-slate-600 leading-relaxed">Prin achiziționarea biletului, cumpărătorul declară că a luat cunoștință de prezenta politică de returnare și rambursare și că este de acord cu prevederile acesteia.</p>
            </div>

            <!-- Cancelled Event -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Ce se întâmplă dacă evenimentul este anulat?
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Te anunțăm pe email imediat ce organizatorul anulează evenimentul. Rambursarea se face conform condițiilor comunicate de organizator, de regulă prin aceeași metodă de plată folosită la achiziție.</p>
                <ul class="my-4 ml-6 space-y-2 list-disc">
                    <li class="text-[15px] text-slate-600 leading-relaxed">Se rambursează contravaloarea biletelor</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed">Taxa de procesare/ticketing nu se rambursează</li>
                    <li class="text-[15px] text-slate-600 leading-relaxed">Pe card, banii ajung de obicei în 5-10 zile lucrătoare de la procesare, în funcție de banca emitentă</li>
                </ul>
                <p class="text-base text-slate-600 leading-relaxed">Dacă nu primești rambursarea în 14 zile de la confirmare, scrie-ne și verificăm.</p>
            </div>

            <!-- Can't Attend -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Nu mai poți ajunge la eveniment?
                </h2>
                <p class="text-base text-slate-600 leading-relaxed">Biletul nu poate fi returnat și nici rambursat. Dacă evenimentul permite, îl poți transfera unei alte persoane din contul tău AmBilet, din secțiunea „Biletele mele”.</p>
            </div>

            <!-- CTA Box -->
            <div class="bg-white border-2 border-border rounded-2xl p-8 text-center mb-10">
                <h3 class="text-xl font-bold text-secondary mb-2">Ai nevoie de ajutor?</h3>
                <p class="text-[15px] text-muted mb-5">Echipa noastră de suport îți răspunde la orice întrebare despre bilete și rambursări.</p>
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
