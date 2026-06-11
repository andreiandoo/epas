<?php
/**
 * Public Order Detail Page
 * Shows order details for a completed purchase
 */
require_once 'includes/config.php';

// Demo data - would come from database
$order = [
    'id' => 'AMB-2024-78543',
    'status' => 'completed',
    'created_at' => '20 Decembrie 2024 la 14:32'
];
$event = [
    'name' => 'Smiley — Acasă Tour 2025',
    'category' => 'Concert',
    'date' => 'Sâmbătă, 15 Ianuarie 2025',
    'time' => '20:00',
    'venue' => 'Sala Palatului, București',
    'slug' => 'smiley-acasa-tour-2025'
];
$tickets = [
    ['type' => 'General Admission', 'beneficiary' => 'Alexandru Mihai', 'email' => 'alexandru.m@email.com', 'price' => 120, 'status' => 'valid', 'id' => 'AMB-T-001'],
    ['type' => 'General Admission', 'beneficiary' => 'Maria Popescu', 'email' => 'maria.p@email.com', 'price' => 120, 'status' => 'valid', 'id' => 'AMB-T-002'],
    ['type' => 'VIP Package', 'beneficiary' => 'Alexandru Mihai', 'email' => 'alexandru.m@email.com', 'price' => 350, 'status' => 'valid', 'id' => 'AMB-T-003']
];
$billing = [
    'name' => 'Alexandru Mihai',
    'email' => 'alexandru.m@email.com',
    'phone' => '+40 722 123 456',
    'address' => 'Str. Victoriei 123, București'
];
$summary = [
    'items' => [
        ['name' => 'General Admission × 2', 'price' => '240 RON'],
        ['name' => 'VIP Package × 1', 'price' => '350 RON'],
        ['name' => 'Taxă serviciu', 'price' => '29,50 RON']
    ],
    'total' => '619,50 RON'
];
$payment = [
    'type' => 'Visa •••• 4242',
    'expires' => 'Expiră 12/26'
];
$timeline = [
    ['event' => 'Bilete trimise pe email', 'date' => '20 Dec 2024, 14:35'],
    ['event' => 'Plată confirmată', 'date' => '20 Dec 2024, 14:33'],
    ['event' => 'Comandă plasată', 'date' => '20 Dec 2024, 14:32']
];

$pageTitle = 'Comandă #' . $order['id'] . ' — AmBilet.ro';

$cssBundle = 'checkout';
require_once 'includes/head.php';
require_once 'includes/header.php';
?>

<main class="max-w-5xl mx-auto px-6 md:px-12 py-8">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 mb-6 text-sm">
        <a href="/cont" class="text-slate-500 no-underline hover:text-primary">Contul meu</a>
        <svg class="w-4 h-4 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <a href="/cont/comenzi" class="text-slate-500 no-underline hover:text-primary">Comenzi</a>
        <svg class="w-4 h-4 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="text-slate-800 font-semibold">#<?= htmlspecialchars($order['id']) ?></span>
    </nav>

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-[28px] font-extrabold text-slate-800 mb-2 flex flex-wrap items-center gap-3">
                Comandă #<?= htmlspecialchars($order['id']) ?>
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[13px] font-semibold bg-green-100 text-green-600">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Completă
                </span>
            </h1>
            <p class="text-sm text-slate-500">Plasată pe <?= htmlspecialchars($order['created_at']) ?></p>
        </div>
        <div class="flex gap-3 w-full md:w-auto">
            <button class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descarcă factura
            </button>
            <button class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold transition-all bg-gradient-to-br from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/30">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/></svg>
                Vezi biletele
            </button>
        </div>
    </div>

    <div class="grid lg:grid-cols-[2fr_1fr] gap-8">
        <!-- Main Content -->
        <div>
            <!-- Event Card -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-6">
                <div class="flex flex-col md:flex-row gap-5 p-6">
                    <div class="w-full md:w-[140px] h-40 md:h-[100px] rounded-xl bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center flex-shrink-0">
                        <svg class="w-10 h-10 text-white opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                    </div>
                    <div class="flex-1">
                        <div class="text-xs font-semibold text-primary uppercase tracking-wide mb-1"><?= htmlspecialchars($event['category']) ?></div>
                        <h2 class="text-xl font-bold text-slate-800 mb-3"><?= htmlspecialchars($event['name']) ?></h2>
                        <div class="flex flex-wrap gap-5">
                            <div class="flex items-center gap-2 text-sm text-slate-500">
                                <svg class="w-[18px] h-[18px] text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?= htmlspecialchars($event['date']) ?>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-500">
                                <svg class="w-[18px] h-[18px] text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?= htmlspecialchars($event['time']) ?>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-500">
                                <svg class="w-[18px] h-[18px] text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?= htmlspecialchars($event['venue']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-wrap gap-3">
                    <a href="/bilete/<?= htmlspecialchars($event['slug']) ?>" class="flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 no-underline transition-all hover:bg-slate-50 hover:border-primary hover:text-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Vezi evenimentul
                    </a>
                    <a href="#" class="flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 no-underline transition-all hover:bg-slate-50 hover:border-primary hover:text-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Adaugă în calendar
                    </a>
                    <a href="#" class="flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-[13px] font-semibold text-slate-700 no-underline transition-all hover:bg-slate-50 hover:border-primary hover:text-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Direcții
                    </a>
                </div>
            </div>

            <!-- Tickets Section -->
            <div class="bg-white rounded-2xl border border-slate-200 mb-6">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/></svg>
                        Bilete comandate
                    </h3>
                    <span class="px-2.5 py-1 bg-slate-100 rounded-md text-xs font-semibold text-slate-500"><?= count($tickets) ?> bilete</span>
                </div>
                <?php foreach ($tickets as $ticket): ?>
                <div class="p-5 border-b border-slate-100 last:border-b-0 flex flex-wrap items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center text-primary">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/></svg>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <div class="text-[15px] font-semibold text-slate-800 mb-1"><?= htmlspecialchars($ticket['type']) ?></div>
                        <div class="text-[13px] text-slate-500"><?= htmlspecialchars($ticket['beneficiary']) ?> • <?= htmlspecialchars($ticket['email']) ?></div>
                    </div>
                    <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-green-100 text-green-600">Valid</span>
                    <div class="text-right">
                        <div class="text-base font-bold text-slate-800"><?= $ticket['price'] ?> RON</div>
                    </div>
                    <a href="/cont/bilete/<?= $ticket['id'] ?>" class="px-3.5 py-2 bg-slate-100 rounded-lg text-[13px] font-semibold text-slate-700 no-underline transition-all hover:bg-slate-200">Vezi bilet</a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Billing Info -->
            <div class="bg-white rounded-2xl border border-slate-200">
                <div class="p-5 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Informații facturare
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Nume complet</div>
                        <div class="text-[15px] font-semibold text-slate-800"><?= htmlspecialchars($billing['name']) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Email</div>
                        <div class="text-[15px] font-semibold text-slate-800"><?= htmlspecialchars($billing['email']) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Telefon</div>
                        <div class="text-[15px] font-semibold text-slate-800"><?= htmlspecialchars($billing['phone']) ?></div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Adresă</div>
                        <div class="text-[15px] font-semibold text-slate-800"><?= htmlspecialchars($billing['address']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Summary Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
                <h3 class="text-base font-bold text-slate-800 mb-5">Sumar comandă</h3>
                <?php foreach ($summary['items'] as $item): ?>
                <div class="flex justify-between py-3 border-b border-slate-100 last:border-b-0">
                    <span class="text-sm text-slate-500"><?= htmlspecialchars($item['name']) ?></span>
                    <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($item['price']) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between pt-4 mt-3 border-t-2 border-slate-200">
                    <span class="text-base font-bold text-slate-800">Total plătit</span>
                    <span class="text-xl font-extrabold text-primary"><?= htmlspecialchars($summary['total']) ?></span>
                </div>
            </div>

            <!-- Payment Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
                <h3 class="text-base font-bold text-slate-800 mb-4">Metodă de plată</h3>
                <div class="flex items-center gap-3.5 p-4 bg-slate-50 rounded-xl">
                    <div class="w-12 h-8 bg-gradient-to-br from-slate-800 to-slate-600 rounded-md flex items-center justify-center text-white text-[10px] font-bold">VISA</div>
                    <div>
                        <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($payment['type']) ?></div>
                        <div class="text-[13px] text-slate-500"><?= htmlspecialchars($payment['expires']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Timeline Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h3 class="text-base font-bold text-slate-800 mb-5">Istoric comandă</h3>
                <div class="space-y-0">
                    <?php foreach ($timeline as $index => $item): ?>
                    <div class="flex gap-4 pb-5 relative <?= $index === count($timeline) - 1 ? '' : 'before:content-[\'\'] before:absolute before:left-[11px] before:top-7 before:bottom-0 before:w-0.5 before:bg-slate-200' ?>">
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0 z-10">
                            <svg class="w-3.5 h-3.5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800 mb-0.5"><?= htmlspecialchars($item['event']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($item['date']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Help Box -->
            <div class="bg-gradient-to-br from-slate-800 to-slate-600 rounded-2xl p-6 mt-6">
                <h4 class="text-[15px] font-bold text-white mb-2">Ai nevoie de ajutor?</h4>
                <p class="text-[13px] text-white/90 mb-4">Contactează-ne pentru orice întrebare despre această comandă.</p>
                <a href="/contact" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-red-400 no-underline">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contactează suportul
                </a>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
