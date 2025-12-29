<?php
/**
 * FAQ Page - Întrebări Frecvente
 * Ambilet Marketplace
 */
require_once 'includes/config.php';

$pageTitle = 'Întrebări Frecvente — ' . SITE_NAME;
$pageDescription = 'Răspunsuri rapide la cele mai comune întrebări despre Ambilet - bilete, plăți, rambursări și setări cont.';

// FAQ Categories with icons
$categories = [
    'all' => ['name' => 'Toate', 'icon' => 'grid'],
    'tickets' => ['name' => 'Bilete', 'icon' => 'ticket'],
    'payments' => ['name' => 'Plăți', 'icon' => 'card'],
    'account' => ['name' => 'Cont', 'icon' => 'user'],
    'refunds' => ['name' => 'Rambursări', 'icon' => 'refresh']
];

// FAQ Data organized by category
$faqs = [
    'tickets' => [
        [
            'question' => 'Cum descarc biletul meu?',
            'answer' => '<p>După finalizarea comenzii, biletul tău va fi disponibil în mai multe moduri:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li><strong>Email:</strong> Primești automat biletul în format PDF pe adresa de email folosită la comandă</li>
                    <li><strong>Contul tău:</strong> Accesează <a href="/account/tickets" class="font-medium text-primary hover:underline">Biletele mele</a> pentru a descărca sau vizualiza biletele</li>
                    <li><strong>Aplicația mobilă:</strong> Biletele sunt sincronizate automat în aplicație</li>
                </ul>
                <p class="mt-3">Codul QR de pe bilet este tot ce ai nevoie pentru a intra la eveniment. Poți să îl arăți direct de pe telefon sau printat.</p>',
            'open' => true
        ],
        [
            'question' => 'Pot transfera biletul altei persoane?',
            'answer' => '<p>Da, poți transfera biletele către altă persoană din contul tău:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li>Accesează <a href="/account/tickets" class="font-medium text-primary hover:underline">Biletele mele</a></li>
                    <li>Selectează biletul pe care vrei să îl transferi</li>
                    <li>Click pe "Transferă bilet"</li>
                    <li>Introdu adresa de email a destinatarului</li>
                </ul>
                <p class="mt-3">Destinatarul va primi un email cu noul bilet pe numele său. <strong class="text-secondary">Atenție:</strong> După transfer, biletul tău original devine invalid.</p>'
        ],
        [
            'question' => 'Nu am primit biletul pe email. Ce fac?',
            'answer' => '<p>Dacă nu ai primit biletul pe email, urmează acești pași:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li><strong>Verifică folderul Spam/Junk</strong> - uneori emailurile ajung acolo</li>
                    <li><strong>Verifică adresa de email</strong> - asigură-te că ai introdus corect adresa la comandă</li>
                    <li><strong>Așteaptă câteva minute</strong> - uneori livrarea poate dura până la 15 minute</li>
                    <li><strong>Descarcă din cont</strong> - biletul este întotdeauna disponibil în <a href="/account/tickets" class="font-medium text-primary hover:underline">Biletele mele</a></li>
                </ul>
                <p class="mt-3">Dacă problema persistă, <a href="/contact" class="font-medium text-primary hover:underline">contactează suportul</a> și te vom ajuta imediat.</p>'
        ]
    ],
    'payments' => [
        [
            'question' => 'Ce metode de plată acceptați?',
            'answer' => '<p>Acceptăm următoarele metode de plată:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li><strong>Carduri bancare:</strong> Visa, Mastercard, American Express</li>
                    <li><strong>Plăți mobile:</strong> Apple Pay, Google Pay</li>
                    <li><strong>Carduri cadou Ambilet</strong></li>
                    <li><strong>Plata în rate</strong> (pentru comenzi peste 500 LEI)</li>
                </ul>
                <p class="mt-3">Toate plățile sunt procesate securizat prin criptare SSL 256-bit.</p>'
        ],
        [
            'question' => 'Plata mea a eșuat. Ce pot face?',
            'answer' => '<p>Dacă plata a eșuat, încearcă următoarele:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li><strong>Verifică datele cardului</strong> - numărul, data expirării și CVV-ul</li>
                    <li><strong>Verifică fondurile disponibile</strong> - asigură-te că ai suficienți bani în cont</li>
                    <li><strong>Verifică limita cardului</strong> - unele bănci au limite zilnice pentru plăți online</li>
                    <li><strong>Încearcă alt card</strong> sau altă metodă de plată</li>
                    <li><strong>Contactează banca</strong> - uneori plățile online sunt blocate din motive de securitate</li>
                </ul>
                <p class="mt-3"><strong class="text-secondary">Important:</strong> Dacă ți s-au debitat banii dar nu ai primit biletul, <a href="/contact" class="font-medium text-primary hover:underline">contactează-ne imediat</a>.</p>'
        ],
        [
            'question' => 'Cum primesc factura pentru comandă?',
            'answer' => '<p>Factura este emisă automat pentru fiecare comandă:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li>O primești pe email împreună cu biletul</li>
                    <li>O poți descărca oricând din <a href="/account/orders" class="font-medium text-primary hover:underline">Istoricul comenzilor</a></li>
                </ul>
                <p class="mt-3">Dacă ai nevoie de factură pe persoană juridică, introdu datele companiei (CUI, adresă) în formularul de checkout înainte de finalizarea comenzii.</p>'
        ]
    ],
    'refunds' => [
        [
            'question' => 'Pot primi rambursare dacă nu mai pot participa?',
            'answer' => '<p>Politica de rambursare depinde de fiecare eveniment și organizator:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li><strong>Majoritatea evenimentelor:</strong> Nu oferă rambursare, dar poți transfera biletul altei persoane</li>
                    <li><strong>Unele evenimente:</strong> Permit rambursare până la o anumită dată (verifică termenii evenimentului)</li>
                    <li><strong>Eveniment anulat:</strong> Primești rambursare automată 100%</li>
                    <li><strong>Eveniment reprogramat:</strong> Biletul rămâne valid pentru noua dată sau poți solicita rambursare</li>
                </ul>
                <p class="mt-3">Verifică întotdeauna <strong class="text-secondary">Politica de rambursare</strong> afișată pe pagina evenimentului înainte de a cumpăra.</p>'
        ],
        [
            'question' => 'Cât durează procesarea rambursării?',
            'answer' => '<p>Timpul de procesare a rambursării variază:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li><strong>Procesare din partea noastră:</strong> 1-3 zile lucrătoare</li>
                    <li><strong>Returnare în cont:</strong> 5-14 zile lucrătoare (depinde de bancă)</li>
                </ul>
                <p class="mt-3">Vei primi un email de confirmare când inițiem rambursarea. Dacă nu primești banii în 14 zile lucrătoare, contactează banca ta sau <a href="/contact" class="font-medium text-primary hover:underline">echipa noastră de suport</a>.</p>'
        ]
    ],
    'account' => [
        [
            'question' => 'Cum îmi schimb parola?',
            'answer' => '<p>Pentru a schimba parola:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li>Accesează <a href="/account/settings" class="font-medium text-primary hover:underline">Setări cont</a></li>
                    <li>Click pe "Schimbă parola"</li>
                    <li>Introdu parola actuală și noua parolă</li>
                    <li>Confirmă modificarea</li>
                </ul>
                <p class="mt-3">Dacă ai uitat parola, folosește opțiunea <a href="/forgot-password" class="font-medium text-primary hover:underline">"Am uitat parola"</a> de pe pagina de login pentru a o reseta.</p>'
        ],
        [
            'question' => 'Cum îmi șterg contul?',
            'answer' => '<p>Pentru a șterge contul:</p>
                <ul class="pl-5 mt-3 space-y-2 list-disc">
                    <li>Accesează <a href="/account/settings" class="font-medium text-primary hover:underline">Setări cont</a></li>
                    <li>Scroll până la secțiunea "Zona periculoasă"</li>
                    <li>Click pe "Șterge contul"</li>
                    <li>Confirmă cu parola ta</li>
                </ul>
                <p class="mt-3"><strong class="text-secondary">Atenție:</strong> Ștergerea contului este permanentă. Vei pierde accesul la biletele nefolosite și istoricul comenzilor. Conform GDPR, toate datele tale personale vor fi șterse în maxim 30 de zile.</p>'
        ]
    ]
];

// Section icon styles
$sectionStyles = [
    'tickets' => 'bg-gradient-to-br from-red-100 to-red-200 text-primary',
    'payments' => 'bg-gradient-to-br from-blue-100 to-blue-200 text-blue-600',
    'account' => 'bg-gradient-to-br from-emerald-100 to-emerald-200 text-emerald-600',
    'refunds' => 'bg-gradient-to-br from-purple-100 to-purple-200 text-purple-600'
];

$sectionTitles = [
    'tickets' => 'Bilete',
    'payments' => 'Plăți',
    'refunds' => 'Rambursări',
    'account' => 'Cont & Setări'
];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include 'includes/head.php'; ?>
</head>
<body class="min-h-screen font-body bg-surface text-secondary">
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="relative px-6 py-16 overflow-hidden text-center bg-gradient-to-br from-slate-800 to-slate-900">
        <div class="absolute rounded-full -top-24 -right-24 w-96 h-96 bg-primary/30 blur-3xl"></div>
        <div class="relative z-10 max-w-2xl mx-auto">
            <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">Întrebări Frecvente</h1>
            <p class="text-lg text-white/70">Răspunsuri rapide la cele mai comune întrebări despre Ambilet</p>
        </div>
    </section>

    <main class="max-w-4xl px-6 py-12 mx-auto">
        <!-- Category Tabs -->
        <div class="flex flex-wrap justify-center gap-2 p-2 mb-8 bg-white border border-gray-200 rounded-2xl">
            <button class="flex items-center gap-2 px-6 py-3 text-sm font-semibold transition-all category-tab active rounded-xl" data-category="all">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span class="hidden sm:inline">Toate</span>
            </button>
            <button class="flex items-center gap-2 px-6 py-3 text-sm font-semibold transition-all category-tab rounded-xl" data-category="tickets">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/>
                </svg>
                <span class="hidden sm:inline">Bilete</span>
            </button>
            <button class="flex items-center gap-2 px-6 py-3 text-sm font-semibold transition-all category-tab rounded-xl" data-category="payments">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                <span class="hidden sm:inline">Plăți</span>
            </button>
            <button class="flex items-center gap-2 px-6 py-3 text-sm font-semibold transition-all category-tab rounded-xl" data-category="account">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                <span class="hidden sm:inline">Cont</span>
            </button>
            <button class="flex items-center gap-2 px-6 py-3 text-sm font-semibold transition-all category-tab rounded-xl" data-category="refunds">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                <span class="hidden sm:inline">Rambursări</span>
            </button>
        </div>

        <!-- FAQ Sections -->
        <?php foreach ($faqs as $category => $items): ?>
        <section class="mb-12 faq-section" data-category="<?= $category ?>">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center <?= $sectionStyles[$category] ?>">
                    <?php if ($category === 'tickets'): ?>
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/>
                        <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                    </svg>
                    <?php elseif ($category === 'payments'): ?>
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    <?php elseif ($category === 'refunds'): ?>
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                    <?php elseif ($category === 'account'): ?>
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    <?php endif; ?>
                </div>
                <h2 class="text-xl font-bold text-secondary"><?= $sectionTitles[$category] ?></h2>
            </div>

            <div class="space-y-3">
                <?php foreach ($items as $index => $faq): ?>
                <div class="faq-item bg-white rounded-2xl border border-gray-200 overflow-hidden transition-all hover:border-gray-300 <?= isset($faq['open']) && $faq['open'] ? 'open border-primary shadow-lg shadow-primary/10' : '' ?>">
                    <button class="flex items-center justify-between w-full px-6 py-5 text-left faq-question">
                        <span class="flex-1 pr-4 text-base font-semibold text-secondary"><?= $faq['question'] ?></span>
                        <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-gray-500 transition-all bg-gray-100 rounded-lg faq-icon">
                            <svg class="w-5 h-5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                    </button>
                    <div class="hidden px-6 pb-6 faq-answer">
                        <div class="pt-4 leading-relaxed text-gray-500 border-t border-gray-100">
                            <?= $faq['answer'] ?>
                        </div>
                        <div class="flex items-center gap-4 pt-4 mt-4 border-t border-gray-100">
                            <span class="text-xs text-gray-400">A fost util acest răspuns?</span>
                            <div class="flex gap-2">
                                <button class="helpful-btn flex items-center gap-1 px-3 py-1.5 bg-gray-100 rounded-md text-xs font-semibold text-gray-500 hover:bg-emerald-100 hover:text-emerald-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                                    </svg>
                                    Da
                                </button>
                                <button class="helpful-btn flex items-center gap-1 px-3 py-1.5 bg-gray-100 rounded-md text-xs font-semibold text-gray-500 hover:bg-red-100 hover:text-red-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/>
                                    </svg>
                                    Nu
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <!-- Contact CTA -->
        <div class="relative p-12 mt-12 overflow-hidden text-center bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl">
            <div class="absolute w-48 h-48 rounded-full -top-12 -right-12 bg-primary/30 blur-2xl"></div>
            <div class="relative z-10">
                <h2 class="mb-3 text-2xl font-bold text-white">Nu ai găsit răspunsul?</h2>
                <p class="mb-6 text-white/70">Echipa noastră de suport este disponibilă să te ajute cu orice întrebare.</p>
                <div class="flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="/contact" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-gradient-to-r from-primary to-primary-dark rounded-xl text-white font-semibold hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/40 transition-all">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Contactează suportul
                    </a>
                    <a href="/help" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-white/10 border border-white/20 rounded-xl text-white font-semibold hover:bg-white/15 transition-all">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Centru de ajutor
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>

    <script>
        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', () => {
                const item = button.parentElement;
                const answer = item.querySelector('.faq-answer');
                const icon = item.querySelector('.faq-icon');
                const wasOpen = item.classList.contains('open');

                // Close all items
                document.querySelectorAll('.faq-item').forEach(i => {
                    i.classList.remove('open', 'border-primary', 'shadow-lg', 'shadow-primary/10');
                    i.querySelector('.faq-answer').classList.add('hidden');
                    i.querySelector('.faq-icon').classList.remove('bg-primary', 'text-white');
                    i.querySelector('.faq-icon').classList.add('bg-gray-100', 'text-gray-500');
                    i.querySelector('.faq-icon svg').style.transform = '';
                });

                // Open clicked item if it wasn't open
                if (!wasOpen) {
                    item.classList.add('open', 'border-primary', 'shadow-lg', 'shadow-primary/10');
                    answer.classList.remove('hidden');
                    icon.classList.remove('bg-gray-100', 'text-gray-500');
                    icon.classList.add('bg-primary', 'text-white');
                    icon.querySelector('svg').style.transform = 'rotate(180deg)';
                }
            });
        });

        // Category Tabs
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const category = tab.dataset.category;

                // Update active tab
                document.querySelectorAll('.category-tab').forEach(t => {
                    t.classList.remove('active', 'bg-gradient-to-r', 'from-primary', 'to-primary-dark', 'text-white');
                    t.classList.add('text-gray-500', 'hover:text-secondary', 'hover:bg-gray-50');
                });
                tab.classList.add('active', 'bg-gradient-to-r', 'from-primary', 'to-primary-dark', 'text-white');
                tab.classList.remove('text-gray-500', 'hover:text-secondary', 'hover:bg-gray-50');

                // Show/hide sections
                document.querySelectorAll('.faq-section').forEach(section => {
                    if (category === 'all' || section.dataset.category === category) {
                        section.classList.remove('hidden');
                    } else {
                        section.classList.add('hidden');
                    }
                });
            });
        });

        // Initialize first tab as active
        document.querySelector('.category-tab.active').classList.add('bg-gradient-to-r', 'from-primary', 'to-primary-dark', 'text-white');
        document.querySelector('.category-tab.active').classList.remove('text-gray-500');

        // Initialize open FAQ items
        document.querySelectorAll('.faq-item.open').forEach(item => {
            item.querySelector('.faq-answer').classList.remove('hidden');
            const icon = item.querySelector('.faq-icon');
            icon.classList.remove('bg-gray-100', 'text-gray-500');
            icon.classList.add('bg-primary', 'text-white');
            icon.querySelector('svg').style.transform = 'rotate(180deg)';
        });
    </script>
</body>
</html>
