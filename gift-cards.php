<?php
/**
 * Gift Cards Page - Carduri Cadou
 * Ambilet Marketplace
 */
require_once 'includes/config.php';

$pageTitle = 'Carduri Cadou — ' . SITE_NAME;
$pageDescription = 'Dăruiește experiențe, nu obiecte. Cardurile cadou Ambilet sunt valabile pentru concerte, festivaluri, teatru și multe altele.';
$transparentHeader = false;
// Predefined amounts
$amounts = [
    ['value' => 50, 'description' => 'Pentru un film sau spectacol'],
    ['value' => 100, 'description' => 'Pentru un concert local'],
    ['value' => 250, 'description' => 'Pentru orice eveniment', 'popular' => true],
    ['value' => 500, 'description' => 'Pentru experiențe premium']
];

// Card designs
$designs = [
    ['slug' => 'red', 'gradient' => 'bg-gradient-to-br from-primary to-[#7f1627]'],
    ['slug' => 'dark', 'gradient' => 'bg-gradient-to-br from-slate-800 to-slate-700'],
    ['slug' => 'purple', 'gradient' => 'bg-gradient-to-br from-purple-600 to-purple-800'],
    ['slug' => 'gold', 'gradient' => 'bg-gradient-to-br from-amber-500 to-amber-600'],
    ['slug' => 'green', 'gradient' => 'bg-gradient-to-br from-emerald-500 to-emerald-600'],
    ['slug' => 'pink', 'gradient' => 'bg-gradient-to-br from-pink-500 to-pink-600']
];

// How it works steps
$steps = [
    ['icon' => 'clock', 'title' => 'Alege valoarea', 'description' => 'Selectează suma dorită între 25 și 2000 LEI'],
    ['icon' => 'edit', 'title' => 'Personalizează', 'description' => 'Adaugă un mesaj special și alege designul'],
    ['icon' => 'activity', 'title' => 'Trimite instant', 'description' => 'Cardul ajunge pe email imediat sau la data aleasă'],
    ['icon' => 'heart', 'title' => 'Bucură-te!', 'description' => 'Destinatarul alege evenimentul preferat']
];

// FAQ items
$faqs = [
    ['question' => 'Cât timp este valabil cardul?', 'answer' => 'Cardurile cadou Ambilet sunt valabile timp de 12 luni de la data achiziției.'],
    ['question' => 'Se poate folosi pentru orice eveniment?', 'answer' => 'Da! Cardurile cadou pot fi folosite pentru orice eveniment disponibil pe Ambilet.ro.'],
    ['question' => 'Pot combina mai multe carduri?', 'answer' => 'Absolut! Poți folosi mai multe carduri cadou pentru aceeași comandă.'],
    ['question' => 'Pot primi restul dacă nu acoper biletul?', 'answer' => 'Diferența poate fi plătită cu cardul sau alte metode de plată disponibile.']
];
$cssBundle = 'static';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include 'includes/head.php'; ?>
</head>
<body class="min-h-screen font-body bg-surface text-secondary">
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="relative px-6 pt-40 pb-20 overflow-hidden text-center bg-gradient-to-br from-slate-800 to-slate-900">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary/40 rounded-full blur-[200px]"></div>
        <div class="relative z-10 max-w-2xl mx-auto">
            <div class="inline-flex items-center gap-2 px-4 py-2 mb-6 text-sm font-semibold text-red-300 border rounded-full bg-primary/20 border-primary/30">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                Cadoul perfect
            </div>
            <h1 class="mb-4 text-4xl font-extrabold leading-tight text-white md:text-5xl">
                Dăruiește <span class="text-transparent bg-gradient-to-r from-red-400 to-orange-400 bg-clip-text">experiențe</span><br>nu obiecte
            </h1>
            <p class="mb-8 text-lg leading-relaxed text-white/90">Oferă-le celor dragi libertatea de a alege orice eveniment doresc. Cardurile cadou Ambilet sunt valabile pentru concerte, festivaluri, teatru și multe altele.</p>
            <a href="#buy" class="inline-flex items-center gap-2 px-9 py-4 bg-gradient-to-r from-primary to-primary-dark rounded-2xl text-white font-semibold hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/40 transition-all">
                Cumpără un card cadou
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </section>

    <!-- Gift Card Preview -->
    <div class="relative z-10 px-6 -mt-16">
        <div class="max-w-4xl mx-auto flex flex-col md:flex-row justify-center items-center gap-4 md:gap-[-30px]">
            <div class="gift-card w-72 h-44 md:w-80 md:h-48 rounded-2xl p-6 relative overflow-hidden bg-gradient-to-br from-primary to-[#7f1627] shadow-2xl transition-all hover:rotate-0 hover:-translate-y-3 hover:scale-105 hover:z-10 md:-rotate-6 md:translate-y-5">
                <div class="mb-10 text-xl font-extrabold text-white">Ambilet</div>
                <div class="text-xs tracking-widest uppercase text-white/90">Card Cadou</div>
                <div class="text-3xl font-extrabold text-white">100 LEI</div>
                <div class="absolute w-12 h-10 rounded-md bottom-6 right-6 bg-gradient-to-br from-amber-500 to-amber-600"></div>
            </div>
            <div class="gift-card w-72 h-44 md:w-80 md:h-48 rounded-2xl p-6 relative overflow-hidden bg-gradient-to-br from-slate-800 to-slate-700 shadow-2xl transition-all hover:rotate-0 hover:-translate-y-3 hover:scale-105 z-[2]">
                <div class="mb-10 text-xl font-extrabold text-white">Ambilet</div>
                <div class="text-xs tracking-widest uppercase text-white/90">Card Cadou</div>
                <div class="text-3xl font-extrabold text-white">250 LEI</div>
                <div class="absolute w-12 h-10 rounded-md bottom-6 right-6 bg-gradient-to-br from-amber-500 to-amber-600"></div>
            </div>
            <div class="relative p-6 overflow-hidden transition-all shadow-2xl gift-card w-72 h-44 md:w-80 md:h-48 rounded-2xl bg-gradient-to-br from-purple-600 to-purple-800 hover:rotate-0 hover:-translate-y-3 hover:scale-105 hover:z-10 md:rotate-6 md:translate-y-5">
                <div class="mb-10 text-xl font-extrabold text-white">Ambilet</div>
                <div class="text-xs tracking-widest uppercase text-white/90">Card Cadou</div>
                <div class="text-3xl font-extrabold text-white">500 LEI</div>
                <div class="absolute w-12 h-10 rounded-md bottom-6 right-6 bg-gradient-to-br from-amber-500 to-amber-600"></div>
            </div>
        </div>
    </div>

    <main class="max-w-6xl px-6 py-20 mx-auto" id="buy">
        <h2 class="mb-4 text-3xl font-extrabold text-center text-secondary">Alege valoarea cardului</h2>
        <p class="mb-12 text-base text-center text-gray-500">Selectează una dintre valorile predefinite sau introdu o sumă personalizată</p>

        <!-- Amount Grid -->
        <div class="grid grid-cols-1 gap-5 mb-8 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($amounts as $index => $amount): ?>
            <div class="amount-card bg-white border-2 border-gray-200 rounded-2xl py-8 px-6 text-center cursor-pointer transition-all hover:border-gray-300 hover:-translate-y-0.5 <?= isset($amount['popular']) ? 'selected !border-primary !bg-red-50 relative' : '' ?>" data-amount="<?= $amount['value'] ?>">
                <?php if (isset($amount['popular'])): ?>
                <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 px-3 py-1 bg-gradient-to-r from-primary to-primary-dark rounded-full text-[11px] font-bold text-white">Popular</span>
                <?php endif; ?>
                <div class="text-3xl font-extrabold text-secondary mb-1 <?= isset($amount['popular']) ? '!text-primary' : '' ?>"><?= $amount['value'] ?> LEI</div>
                <div class="text-sm text-gray-500"><?= $amount['description'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Custom Amount -->
        <div class="flex flex-col items-center justify-center gap-4 p-6 mb-12 sm:flex-row bg-gray-50 rounded-2xl">
            <span class="text-sm font-semibold text-gray-600">Sau introdu o sumă personalizată:</span>
            <input type="text" id="customAmount" class="w-36 py-3.5 px-5 bg-white border-2 border-gray-200 rounded-xl text-lg font-bold text-secondary text-center outline-none focus:border-primary" placeholder="300">
            <span class="text-sm text-gray-400">Între 25 LEI și 2000 LEI</span>
        </div>

        <!-- Gift Form Section -->
        <div class="p-8 bg-white border border-gray-200 rounded-3xl md:p-12">
            <div class="grid grid-cols-1 gap-12 lg:grid-cols-2">
                <!-- Left Column -->
                <div>
                    <h3 class="flex items-center gap-2 mb-6 text-lg font-bold text-secondary">
                        <span class="flex items-center justify-center text-sm font-bold text-white rounded-lg w-7 h-7 bg-gradient-to-r from-primary to-primary-dark">1</span>
                        Personalizează cardul
                    </h3>

                    <!-- Design Selection -->
                    <div class="mb-5">
                        <label class="block mb-2 text-sm font-semibold text-gray-600">Alege designul</label>
                        <div class="grid grid-cols-3 gap-3">
                            <?php foreach ($designs as $index => $design): ?>
                            <div class="design-option relative h-20 rounded-xl border-2 border-transparent cursor-pointer transition-all <?= $design['gradient'] ?> <?= $index === 0 ? 'selected !border-primary' : '' ?>" data-design="<?= $design['slug'] ?>">
                                <?php if ($index === 0): ?>
                                <span class="absolute flex items-center justify-center w-5 h-5 text-xs bg-white rounded-full text-primary top-2 right-2">✓</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recipient Name -->
                    <div class="mb-5">
                        <label class="block mb-2 text-sm font-semibold text-gray-600">Numele destinatarului</label>
                        <input type="text" class="w-full py-3.5 px-5 bg-gray-50 border border-gray-200 rounded-xl text-base text-secondary outline-none transition-all focus:border-primary focus:bg-white" placeholder="Ex: Maria Popescu">
                    </div>

                    <!-- Personal Message -->
                    <div class="mb-5">
                        <label class="block mb-2 text-sm font-semibold text-gray-600">Mesaj personalizat (opțional)</label>
                        <textarea class="w-full py-3.5 px-5 bg-gray-50 border border-gray-200 rounded-xl text-base text-secondary outline-none transition-all focus:border-primary focus:bg-white resize-y min-h-[100px]" placeholder="Scrie un mesaj pentru persoana care va primi cardul..."></textarea>
                        <p class="text-xs text-gray-400 mt-1.5">Maximum 200 de caractere</p>
                    </div>

                    <!-- From -->
                    <div class="mb-5">
                        <label class="block mb-2 text-sm font-semibold text-gray-600">De la</label>
                        <input type="text" class="w-full py-3.5 px-5 bg-gray-50 border border-gray-200 rounded-xl text-base text-secondary outline-none transition-all focus:border-primary focus:bg-white" placeholder="Numele tău">
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <h3 class="flex items-center gap-2 mb-6 text-lg font-bold text-secondary">
                        <span class="flex items-center justify-center text-sm font-bold text-white rounded-lg w-7 h-7 bg-gradient-to-r from-primary to-primary-dark">2</span>
                        Livrare și plată
                    </h3>

                    <!-- Delivery Options -->
                    <div class="mb-5">
                        <label class="block mb-2 text-sm font-semibold text-gray-600">Cum dorești să trimiți cardul?</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="delivery-option flex-1 p-4 bg-gray-50 border-2 border-gray-200 rounded-xl cursor-pointer text-center transition-all selected !border-primary !bg-red-50" data-delivery="instant">
                                <div class="flex items-center justify-center w-10 h-10 mx-auto mb-2 text-white bg-primary rounded-xl">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                </div>
                                <div class="text-sm font-bold text-secondary">Email</div>
                                <div class="text-xs text-gray-500">Instant</div>
                            </div>
                            <div class="flex-1 p-4 text-center transition-all border-2 border-gray-200 cursor-pointer delivery-option bg-gray-50 rounded-xl hover:border-gray-300" data-delivery="scheduled">
                                <div class="flex items-center justify-center w-10 h-10 mx-auto mb-2 text-gray-500 bg-gray-200 rounded-xl">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                </div>
                                <div class="text-sm font-bold text-secondary">Programat</div>
                                <div class="text-xs text-gray-500">Alege data</div>
                            </div>
                        </div>
                    </div>

                    <!-- Scheduled Delivery Date/Time (hidden by default) -->
                    <div id="scheduledDelivery" class="hidden p-4 mb-5 border bg-amber-50 border-amber-200 rounded-xl">
                        <label class="block mb-3 text-sm font-semibold text-gray-700">Când să fie trimis cardul?</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block mb-1.5 text-xs text-gray-500">Data</label>
                                <input type="date" id="deliveryDate" class="w-full px-4 py-3 text-sm bg-white border border-gray-200 outline-none rounded-xl text-secondary focus:border-primary" min="">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-xs text-gray-500">Ora</label>
                                <input type="time" id="deliveryTime" value="09:00" class="w-full px-4 py-3 text-sm bg-white border border-gray-200 outline-none rounded-xl text-secondary focus:border-primary">
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-amber-700">Cardul va fi trimis automat la data și ora selectată.</p>
                    </div>

                    <!-- Recipient Email -->
                    <div class="mb-5">
                        <label class="block mb-2 text-sm font-semibold text-gray-600">Email destinatar</label>
                        <input type="email" class="w-full py-3.5 px-5 bg-gray-50 border border-gray-200 rounded-xl text-base text-secondary outline-none transition-all focus:border-primary focus:bg-white" placeholder="email@exemplu.com">
                        <p class="text-xs text-gray-400 mt-1.5">Aici va fi trimis cardul cadou</p>
                    </div>

                    <!-- Your Email -->
                    <div class="mb-5">
                        <label class="block mb-2 text-sm font-semibold text-gray-600">Email-ul tău (pentru confirmare)</label>
                        <input type="email" class="w-full py-3.5 px-5 bg-gray-50 border border-gray-200 rounded-xl text-base text-secondary outline-none transition-all focus:border-primary focus:bg-white" placeholder="email-ul@tau.com">
                    </div>

                    <!-- Order Summary -->
                    <div class="p-6 mt-6 bg-gray-50 rounded-2xl">
                        <h4 class="mb-4 text-base font-bold text-secondary">Sumar comandă</h4>
                        <div class="flex justify-between py-3 text-sm border-b border-gray-200">
                            <span class="text-gray-500">Card cadou Ambilet</span>
                            <span class="font-semibold text-secondary amount-display">250 LEI</span>
                        </div>
                        <div class="flex justify-between py-3 text-sm border-b border-gray-200">
                            <span class="text-gray-500">Livrare email</span>
                            <span class="font-semibold text-secondary">Gratuit</span>
                        </div>
                        <div class="flex justify-between py-3 text-lg font-bold">
                            <span class="text-gray-500">Total</span>
                            <span class="text-xl text-primary amount-display">250 LEI</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button class="w-full mt-5 py-4 bg-gradient-to-r from-primary to-primary-dark rounded-2xl text-white font-bold flex items-center justify-center gap-2 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/35 transition-all">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Plătește acum
                    </button>
                </div>
            </div>
        </div>

        <!-- How it Works -->
        <section class="mt-20">
            <h2 class="mb-4 text-3xl font-extrabold text-center text-secondary">Cum funcționează</h2>
            <p class="mb-12 text-base text-center text-gray-500">În doar 4 pași simpli poți oferi un cadou memorabil</p>
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($steps as $index => $step): ?>
                <div class="relative text-center">
                    <?php if ($index < count($steps) - 1): ?>
                    <div class="hidden lg:block absolute top-10 left-[calc(50%+50px)] w-[calc(100%-100px)] h-0.5 bg-gray-200"></div>
                    <?php endif; ?>
                    <div class="relative z-10 flex items-center justify-center w-20 h-20 mx-auto mb-5 text-white bg-gradient-to-r from-primary to-primary-dark rounded-2xl">
                        <?php if ($step['icon'] === 'clock'): ?>
                        <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                        </svg>
                        <?php elseif ($step['icon'] === 'edit'): ?>
                        <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        <?php elseif ($step['icon'] === 'activity'): ?>
                        <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                        <?php elseif ($step['icon'] === 'heart'): ?>
                        <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-secondary"><?= $step['title'] ?></h3>
                    <p class="text-sm leading-relaxed text-gray-500"><?= $step['description'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="mt-20">
            <h2 class="mb-4 text-3xl font-extrabold text-center text-secondary">Întrebări frecvente</h2>
            <p class="mb-12 text-base text-center text-gray-500">Tot ce trebuie să știi despre cardurile cadou</p>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <?php foreach ($faqs as $faq): ?>
                <div class="p-6 bg-white border border-gray-200 rounded-2xl">
                    <h3 class="flex items-start gap-2 mb-2 text-base font-bold text-secondary">
                        <svg class="w-5 h-5 text-primary flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <?= $faq['question'] ?>
                    </h3>
                    <p class="text-sm leading-relaxed text-gray-500 pl-7"><?= $faq['answer'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>

    <script>
        // Amount selection
        document.querySelectorAll('.amount-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.amount-card').forEach(c => {
                    c.classList.remove('selected', '!border-primary', '!bg-red-50');
                    const value = c.querySelector('.text-3xl');
                    if (value) value.classList.remove('!text-primary');
                });
                card.classList.add('selected', '!border-primary', '!bg-red-50');
                const value = card.querySelector('.text-3xl');
                if (value) value.classList.add('!text-primary');

                const amount = card.dataset.amount;
                updateAmount(amount);
            });
        });

        // Custom amount
        document.getElementById('customAmount').addEventListener('input', function() {
            const amount = parseInt(this.value) || 0;
            if (amount >= 25 && amount <= 2000) {
                document.querySelectorAll('.amount-card').forEach(c => {
                    c.classList.remove('selected', '!border-primary', '!bg-red-50');
                    const value = c.querySelector('.text-3xl');
                    if (value) value.classList.remove('!text-primary');
                });
                updateAmount(amount);
            }
        });

        function updateAmount(amount) {
            document.querySelectorAll('.amount-display').forEach(el => {
                el.textContent = amount + ' LEI';
            });
        }

        // Design selection
        document.querySelectorAll('.design-option').forEach(option => {
            option.addEventListener('click', () => {
                document.querySelectorAll('.design-option').forEach(o => {
                    o.classList.remove('selected', '!border-primary');
                    const checkmark = o.querySelector('span');
                    if (checkmark) checkmark.remove();
                });
                option.classList.add('selected', '!border-primary');
                const checkmark = document.createElement('span');
                checkmark.className = 'absolute top-2 right-2 w-5 h-5 bg-white rounded-full flex items-center justify-center text-xs text-primary';
                checkmark.textContent = '✓';
                option.appendChild(checkmark);
            });
        });

        // Delivery option selection
        const scheduledDelivery = document.getElementById('scheduledDelivery');
        const deliveryDateInput = document.getElementById('deliveryDate');

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        if (deliveryDateInput) deliveryDateInput.min = today;

        document.querySelectorAll('.delivery-option').forEach(option => {
            option.addEventListener('click', () => {
                document.querySelectorAll('.delivery-option').forEach(o => {
                    o.classList.remove('selected', '!border-primary', '!bg-red-50');
                    const icon = o.querySelector('.w-10');
                    if (icon) {
                        icon.classList.remove('bg-primary', 'text-white');
                        icon.classList.add('bg-gray-200', 'text-gray-500');
                    }
                });
                option.classList.add('selected', '!border-primary', '!bg-red-50');
                const icon = option.querySelector('.w-10');
                if (icon) {
                    icon.classList.remove('bg-gray-200', 'text-gray-500');
                    icon.classList.add('bg-primary', 'text-white');
                }

                // Show/hide scheduled delivery section
                const deliveryType = option.dataset.delivery;
                if (deliveryType === 'scheduled') {
                    scheduledDelivery.classList.remove('hidden');
                    // Set default date to tomorrow
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    deliveryDateInput.value = tomorrow.toISOString().split('T')[0];
                } else {
                    scheduledDelivery.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
