<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Declaratie de Accesibilitate';
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
                <svg class="w-10 h-10 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
            <h1 class="text-3xl md:text-[44px] font-extrabold text-white mb-4">Declaratie de Accesibilitate</h1>
            <p class="text-lg text-white/70 leading-relaxed">Ne angajam sa oferim o experienta digitala accesibila tuturor utilizatorilor, indiferent de abilitatile lor.</p>
            <div class="mt-6 text-sm text-white/50">Ultima actualizare: 15 Decembrie 2024</div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="max-w-[900px] mx-auto px-4 md:px-12 py-12 md:py-16">
            <!-- Table of Contents -->
            <div class="bg-surface rounded-2xl p-6 mb-8 border border-border">
                <h3 class="text-sm font-bold text-muted uppercase tracking-wider mb-4">Cuprins</h3>
                <ul class="space-y-2">
                    <li><a href="#angajament" class="text-[15px] text-primary flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        Angajamentul nostru
                    </a></li>
                    <li><a href="#standarde" class="text-[15px] text-primary flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        Standarde de conformitate
                    </a></li>
                    <li><a href="#functionalitati" class="text-[15px] text-primary flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        Functionalitati de accesibilitate
                    </a></li>
                    <li><a href="#limitari" class="text-[15px] text-primary flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        Limitari cunoscute
                    </a></li>
                    <li><a href="#feedback" class="text-[15px] text-primary flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        Feedback si contact
                    </a></li>
                </ul>
            </div>

            <!-- Our Commitment -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8" id="angajament">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    Angajamentul nostru
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">La AmBilet, credem ca evenimentele si experientele culturale ar trebui sa fie accesibile tuturor. Ne angajam sa asiguram ca platforma noastra digitala este utilizabila de catre persoanele cu dizabilitati, inclusiv cele cu deficiente de vedere, auz, mobilitate sau cognitive.</p>
                <p class="text-base text-slate-600 leading-relaxed mb-6">Lucram continuu pentru a imbunatati accesibilitatea platformei noastre si pentru a respecta cele mai bune practici si standarde din industrie.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="bg-surface rounded-2xl p-6 border border-border">
                        <h4 class="text-base font-bold text-secondary mb-2 flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Perceptie
                        </h4>
                        <p class="text-sm text-muted leading-relaxed">Continutul este prezentat in moduri care pot fi percepute de toti utilizatorii.</p>
                    </div>
                    <div class="bg-surface rounded-2xl p-6 border border-border">
                        <h4 class="text-base font-bold text-secondary mb-2 flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            Operabilitate
                        </h4>
                        <p class="text-sm text-muted leading-relaxed">Interfata poate fi navigata si operata folosind diverse dispozitive de intrare.</p>
                    </div>
                    <div class="bg-surface rounded-2xl p-6 border border-border">
                        <h4 class="text-base font-bold text-secondary mb-2 flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Intelegere
                        </h4>
                        <p class="text-sm text-muted leading-relaxed">Continutul si operarea interfetei sunt clare si predictibile.</p>
                    </div>
                    <div class="bg-surface rounded-2xl p-6 border border-border">
                        <h4 class="text-base font-bold text-secondary mb-2 flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Robustete
                        </h4>
                        <p class="text-sm text-muted leading-relaxed">Continutul este compatibil cu tehnologiile asistive actuale si viitoare.</p>
                    </div>
                </div>
            </div>

            <!-- Standards -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8" id="standarde">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Standarde de conformitate
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">AmBilet urmareste sa respecte standardele Web Content Accessibility Guidelines (WCAG) 2.1 la nivel AA. Aceste linii directoare explica cum sa facem continutul web mai accesibil pentru persoanele cu dizabilitati.</p>

                <div class="inline-flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-primary/10 to-primary/5 border border-primary/20 rounded-xl my-4">
                    <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-white text-xs font-extrabold">AA</div>
                    <span class="text-sm font-semibold text-primary">WCAG 2.1 Level AA Compliance Target</span>
                </div>

                <p class="text-base text-slate-600 leading-relaxed mb-4">Standardele WCAG definesc trei niveluri de conformitate:</p>

                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><strong>Nivel A</strong> — Cerintele de baza care trebuie indeplinite</span>
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><strong>Nivel AA</strong> — Abordeaza cele mai mari si comune bariere (tinta noastra)</span>
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><strong>Nivel AAA</strong> — Cel mai inalt nivel de accesibilitate</span>
                    </li>
                </ul>
            </div>

            <!-- Features -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8" id="functionalitati">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    Functionalitati de accesibilitate
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-6">Am implementat urmatoarele functionalitati pentru a imbunatati accesibilitatea platformei:</p>

                <h3 class="text-lg font-bold text-secondary mt-7 mb-3">Navigare si structura</h3>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Structura semantica HTML5 pentru navigare logica
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Skip links pentru a sari direct la continutul principal
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Navigare completa prin tastatura (Tab, Enter, Escape)
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Indicatori de focus vizibili pentru elementele interactive
                    </li>
                </ul>

                <h3 class="text-lg font-bold text-secondary mt-7 mb-3">Continut vizual</h3>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Contrast de culoare suficient pentru text si elemente UI
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Text alternativ (alt text) pentru toate imaginile informative
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Posibilitatea de a mari textul pana la 200% fara pierdere de functionalitate
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Design responsive care se adapteaza la diferite dimensiuni de ecran
                    </li>
                </ul>

                <h3 class="text-lg font-bold text-secondary mt-7 mb-3">Formulare si interactiuni</h3>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Etichete asociate corect cu campurile de formular
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Mesaje de eroare clare si descriptive
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Indicatii multiple pentru starile elementelor (nu doar culoare)
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Timp suficient pentru completarea formularelor
                    </li>
                </ul>

                <h3 class="text-lg font-bold text-secondary mt-7 mb-3">Tehnologii asistive</h3>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Compatibilitate cu cititoare de ecran (NVDA, JAWS, VoiceOver)
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Atribute ARIA pentru componente interactive complexe
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Landmarks ARIA pentru navigare rapida
                    </li>
                </ul>
            </div>

            <!-- Known Limitations -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12 mb-8" id="limitari">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Limitari cunoscute
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Desi ne straduim sa asiguram accesibilitatea completa, exista cateva limitari cunoscute pe care lucram sa le rezolvam:</p>

                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><strong>Continut generat de utilizatori:</strong> Unele descrieri de evenimente create de organizatori pot sa nu respecte integral standardele de accesibilitate. Lucram la ghiduri pentru organizatori.</span>
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><strong>Harti interactive:</strong> Hartile pentru locatii pot avea accesibilitate limitata. Oferim intotdeauna si adresa in format text.</span>
                    </li>
                    <li class="flex items-start gap-3 text-[15px] text-slate-600 leading-relaxed">
                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><strong>PDF-uri vechi:</strong> Unele documente PDF mai vechi pot sa nu fie complet accesibile. Le actualizam progresiv.</span>
                    </li>
                </ul>

                <p class="text-base text-slate-600 leading-relaxed mt-4">Daca intampinati dificultati cu oricare dintre aceste aspecte, va rugam sa ne contactati si vom gasi o solutie alternativa.</p>
            </div>

            <!-- Feedback & Contact -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-12" id="feedback">
                <h2 class="text-2xl font-bold text-secondary mb-5 flex items-center gap-3">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Feedback si contact
                </h2>
                <p class="text-base text-slate-600 leading-relaxed mb-4">Apreciem feedback-ul dumneavoastra privind accesibilitatea platformei AmBilet. Daca intampinati bariere de accesibilitate sau aveti sugestii de imbunatatire, va rugam sa ne contactati.</p>
                <p class="text-base text-slate-600 leading-relaxed mb-6">Ne angajam sa raspundem la solicitarile privind accesibilitatea in termen de 2 zile lucratoare si sa oferim solutii alternative cand este necesar.</p>

                <div class="bg-gradient-to-br from-secondary to-slate-600 rounded-2xl p-8 mt-8">
                    <h3 class="text-xl font-bold text-white mb-3">Contacteaza echipa de accesibilitate</h3>
                    <p class="text-[15px] text-white/70 mb-5">Suntem aici sa va ajutam sa aveti cea mai buna experienta pe platforma noastra.</p>
                    <div class="flex flex-col md:flex-row gap-4">
                        <a href="mailto:accesibilitate@ambilet.ro" class="flex items-center gap-2.5 px-5 py-3 bg-white/10 rounded-xl text-white text-sm font-medium hover:bg-white/15 transition-colors">
                            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            accesibilitate@ambilet.ro
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
