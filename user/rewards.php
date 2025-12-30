<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Puncte & Recompense';
$currentPage = 'rewards';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';

// Demo data
$userPoints = 2450;
$userLevel = 12;
$levelName = 'Rock Star';
$nextLevelXP = 3000;

// Demo rewards
$rewards = [
    ['emoji' => '🎫', 'title' => '10 lei reducere', 'desc' => 'Aplicabil la orice comanda de minim 50 lei', 'points' => 500, 'status' => 'available', 'gradient' => 'from-accent/20 to-warning/20'],
    ['emoji' => '🎁', 'title' => '25 lei reducere', 'desc' => 'Aplicabil la orice comanda de minim 100 lei', 'points' => 1000, 'status' => 'available', 'gradient' => 'from-primary/20 to-accent/20'],
    ['emoji' => '⬆️', 'title' => 'Upgrade VIP', 'desc' => 'Transforma un bilet Standard in VIP', 'points' => 2000, 'status' => 'available', 'gradient' => 'from-purple-500/20 to-pink-500/20'],
    ['emoji' => '🎤', 'title' => 'Meet & Greet', 'desc' => 'Acces la meet & greet cu artistii', 'points' => 5000, 'status' => 'locked', 'lock_reason' => 'NIVEL 15+', 'gradient' => 'from-blue-500/20 to-cyan-500/20'],
    ['emoji' => '🎫', 'title' => 'Bilet gratuit', 'desc' => 'Un bilet Standard gratuit la orice eveniment', 'points' => 4000, 'status' => 'insufficient', 'missing' => 1550, 'gradient' => 'from-yellow-400/20 to-orange-500/20'],
    ['emoji' => '👑', 'title' => 'Gold Member', 'desc' => 'Status Gold pentru 1 an - acces prioritar', 'points' => 10000, 'status' => 'exclusive', 'missing' => 7550, 'gradient' => 'from-yellow-400 to-orange-500']
];

// Demo badges - unlocked
$unlockedBadges = [
    ['emoji' => '🎸', 'name' => 'Rock Veteran', 'desc' => '10+ concerte rock', 'xp' => 200, 'gradient' => 'from-yellow-400 to-orange-500'],
    ['emoji' => '🌟', 'name' => 'Early Bird', 'desc' => '5+ bilete early bird', 'xp' => 150, 'gradient' => 'from-purple-400 to-pink-500'],
    ['emoji' => '💎', 'name' => 'VIP Lover', 'desc' => '3+ bilete VIP', 'xp' => 300, 'gradient' => 'from-green-400 to-emerald-500'],
    ['emoji' => '🎪', 'name' => 'Festival Fan', 'desc' => '3+ festivaluri', 'xp' => 250, 'gradient' => 'from-blue-400 to-cyan-500'],
    ['emoji' => '❤️', 'name' => 'Loyal Fan', 'desc' => '1 an pe platforma', 'xp' => 500, 'gradient' => 'from-red-400 to-pink-500'],
    ['emoji' => '🎭', 'name' => 'Eclectic', 'desc' => '5+ genuri diferite', 'xp' => 200, 'gradient' => 'from-indigo-400 to-purple-500'],
    ['emoji' => '⭐', 'name' => 'First Timer', 'desc' => 'Primul bilet', 'xp' => 50, 'gradient' => 'from-amber-400 to-yellow-500']
];

// Demo badges - locked
$lockedBadges = [
    ['emoji' => '🏆', 'name' => 'Champion', 'desc' => '50+ evenimente', 'missing' => '27 lipsa'],
    ['emoji' => '🌍', 'name' => 'Explorer', 'desc' => '10+ orase diferite', 'missing' => '6 lipsa'],
    ['emoji' => '👥', 'name' => 'Social', 'desc' => 'Invita 5 prieteni', 'missing' => '5 lipsa']
];

// Demo history
$pointsHistory = [
    ['type' => 'earned', 'icon' => 'plus', 'desc' => 'Achizitie bilet - Cargo Live', 'date' => '20 Dec 2024, 10:12', 'points' => 120],
    ['type' => 'badge', 'icon' => 'badge', 'desc' => 'Badge obtinut - Rock Veteran', 'date' => '18 Dec 2024, 15:30', 'points' => 200],
    ['type' => 'spent', 'icon' => 'minus', 'desc' => 'Reducere folosita - 10 lei', 'date' => '15 Dec 2024, 09:45', 'points' => -500],
    ['type' => 'earned', 'icon' => 'plus', 'desc' => 'Achizitie bilet - Halloween Rock Night', 'date' => '28 Oct 2024, 18:45', 'points' => 160],
    ['type' => 'checkin', 'icon' => 'check', 'desc' => 'Check-in efectuat - Halloween Rock Night', 'date' => '31 Oct 2024, 19:15', 'points' => 50]
];

// Levels
$levels = [
    ['range' => '1-5', 'name' => 'Newbie', 'emoji' => '🎵', 'xp' => '0 - 500', 'rewards' => '', 'status' => 'completed', 'gradient' => 'from-gray-300 to-gray-400'],
    ['range' => '6-10', 'name' => 'Music Lover', 'emoji' => '🎶', 'xp' => '500 - 1,500', 'rewards' => '10 lei reducere', 'status' => 'completed', 'gradient' => 'from-blue-400 to-cyan-500'],
    ['range' => '11-15', 'name' => 'Rock Star', 'emoji' => '🎸', 'xp' => '1,500 - 4,000', 'rewards' => 'Upgrade VIP, 25 lei reducere', 'status' => 'current', 'gradient' => 'from-primary to-accent'],
    ['range' => '16-20', 'name' => 'Legend', 'emoji' => '👑', 'xp' => '4,000 - 8,000', 'rewards' => 'Meet & Greet, Bilet gratuit', 'status' => 'locked', 'gradient' => 'from-purple-400 to-pink-500'],
    ['range' => '21+', 'name' => 'Hall of Fame', 'emoji' => '🏆', 'xp' => '8,000+', 'rewards' => 'Gold Member, Backstage Access', 'status' => 'locked', 'gradient' => 'from-yellow-400 to-orange-500']
];
?>

<style>
    .level-progress { background: linear-gradient(90deg, #A51C30 0%, #E67E22 100%); }
    .badge-card { transition: all 0.3s ease; }
    .badge-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
    .badge-locked { filter: grayscale(100%); opacity: 0.5; }
    .reward-card { transition: all 0.3s ease; }
    .reward-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
    .shine { position: relative; overflow: hidden; }
    .shine::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(to right, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
        transform: rotate(30deg);
        animation: shine 3s infinite;
    }
    @keyframes shine {
        0% { transform: translateX(-100%) rotate(30deg); }
        100% { transform: translateX(100%) rotate(30deg); }
    }
    .tab-btn.active { background: white; color: #A51C30; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
</style>

<!-- Main Content -->
<main class="max-w-7xl mx-auto px-4 py-6 lg:py-8">
    <!-- Points Overview Hero -->
    <div class="bg-gradient-to-r from-primary via-primary-dark to-secondary rounded-2xl lg:rounded-3xl p-6 lg:p-8 mb-6 text-white relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-1/4 w-32 h-32 bg-white/5 rounded-full translate-y-1/2"></div>

        <div class="relative">
            <div class="grid lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Points Balance -->
                <div class="lg:col-span-1">
                    <p class="text-white/70 text-sm mb-1">Punctele tale</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl lg:text-5xl font-extrabold"><?= number_format($userPoints) ?></span>
                        <span class="text-white/70">puncte</span>
                    </div>
                    <p class="text-sm text-white/60 mt-2">≈ <?= number_format($userPoints / 100, 2) ?> lei reducere</p>
                </div>

                <!-- Level Progress -->
                <div class="lg:col-span-2">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                <span class="text-2xl">🎸</span>
                            </div>
                            <div>
                                <p class="font-bold text-lg">Nivel <?= $userLevel ?> - <?= $levelName ?></p>
                                <p class="text-sm text-white/70"><?= $nextLevelXP - $userPoints ?> XP pana la nivelul urmator</p>
                            </div>
                        </div>
                        <div class="text-right hidden sm:block">
                            <p class="text-2xl font-bold"><?= number_format($userPoints) ?> / <?= number_format($nextLevelXP) ?></p>
                            <p class="text-xs text-white/70">XP</p>
                        </div>
                    </div>
                    <div class="h-4 bg-white/20 rounded-full overflow-hidden">
                        <div class="level-progress h-full rounded-full transition-all duration-1000" style="width: <?= round(($userPoints / $nextLevelXP) * 100) ?>%"></div>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-white/60">
                        <span>Nivel <?= $userLevel ?></span>
                        <span>Nivel <?= $userLevel + 1 ?> - Legend</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 p-1 bg-surface rounded-xl mb-6 w-fit overflow-x-auto">
        <button onclick="showTab('rewards')" class="tab-btn active px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap">
            Recompense
        </button>
        <button onclick="showTab('badges')" class="tab-btn px-4 py-2 rounded-lg text-sm font-medium text-muted whitespace-nowrap">
            Badge-uri (<?= count($unlockedBadges) ?>/<?= count($unlockedBadges) + count($lockedBadges) ?>)
        </button>
        <button onclick="showTab('history')" class="tab-btn px-4 py-2 rounded-lg text-sm font-medium text-muted whitespace-nowrap">
            Istoric puncte
        </button>
        <button onclick="showTab('levels')" class="tab-btn px-4 py-2 rounded-lg text-sm font-medium text-muted whitespace-nowrap">
            Niveluri
        </button>
    </div>

    <!-- Rewards Tab -->
    <div id="tab-rewards">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-secondary mb-2">Foloseste-ti punctele</h2>
            <p class="text-muted">Schimba punctele acumulate pentru reduceri si beneficii exclusive.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
            <?php foreach ($rewards as $reward): ?>
            <?php
                $isLocked = $reward['status'] === 'locked';
                $isInsufficient = $reward['status'] === 'insufficient';
                $isExclusive = $reward['status'] === 'exclusive';
                $cardClass = ($isLocked || $isInsufficient) ? 'opacity-60' : '';
                if ($isExclusive) $cardClass = 'shine bg-gradient-to-br from-yellow-50 to-orange-50 border-2 border-accent/30';
                else $cardClass .= ' bg-white border border-border';
            ?>
            <div class="reward-card rounded-xl lg:rounded-2xl p-5 <?= $cardClass ?>">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br <?= $reward['gradient'] ?> rounded-xl flex items-center justify-center">
                        <span class="text-3xl"><?= $reward['emoji'] ?></span>
                    </div>
                    <?php if ($reward['status'] === 'available'): ?>
                    <span class="px-3 py-1 bg-success/10 text-success text-xs font-bold rounded-full">DISPONIBIL</span>
                    <?php elseif ($isLocked): ?>
                    <span class="px-3 py-1 bg-muted/20 text-muted text-xs font-bold rounded-full"><?= $reward['lock_reason'] ?></span>
                    <?php elseif ($isInsufficient): ?>
                    <span class="px-3 py-1 bg-warning/10 text-warning text-xs font-bold rounded-full"><?= number_format($reward['missing']) ?> LIPSA</span>
                    <?php elseif ($isExclusive): ?>
                    <span class="px-3 py-1 bg-accent text-white text-xs font-bold rounded-full">EXCLUSIV</span>
                    <?php endif; ?>
                </div>
                <h3 class="font-bold text-secondary mb-1"><?= $reward['title'] ?></h3>
                <p class="text-sm text-muted mb-4"><?= $reward['desc'] ?></p>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <svg class="w-5 h-5 <?= ($isLocked || $isInsufficient || $isExclusive) ? 'text-muted' : 'text-accent' ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
                        <span class="font-bold <?= ($isLocked || $isInsufficient || $isExclusive) ? 'text-muted' : 'text-secondary' ?>"><?= number_format($reward['points']) ?></span>
                    </div>
                    <?php if ($reward['status'] === 'available'): ?>
                    <button class="btn-primary px-4 py-2 text-white text-sm font-semibold rounded-lg">Revendica</button>
                    <?php elseif ($isLocked): ?>
                    <button class="px-4 py-2 bg-surface text-muted text-sm font-semibold rounded-lg cursor-not-allowed">Blocat</button>
                    <?php elseif ($isInsufficient): ?>
                    <button class="px-4 py-2 bg-surface text-muted text-sm font-semibold rounded-lg cursor-not-allowed">Insuficient</button>
                    <?php elseif ($isExclusive): ?>
                    <button class="px-4 py-2 bg-surface text-muted text-sm font-semibold rounded-lg cursor-not-allowed"><?= number_format($reward['missing']) ?> lipsa</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Badges Tab -->
    <div id="tab-badges" class="hidden">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-secondary mb-2">Colectia ta de badge-uri</h2>
            <p class="text-muted">Ai obtinut <?= count($unlockedBadges) ?> din <?= count($unlockedBadges) + count($lockedBadges) ?> badge-uri disponibile. Continua sa participi la evenimente!</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <?php foreach ($unlockedBadges as $badge): ?>
            <div class="badge-card bg-white rounded-xl border border-border p-4 text-center">
                <div class="w-16 h-16 mx-auto mb-3 bg-gradient-to-br <?= $badge['gradient'] ?> rounded-2xl flex items-center justify-center text-3xl">
                    <?= $badge['emoji'] ?>
                </div>
                <h4 class="font-bold text-secondary text-sm"><?= $badge['name'] ?></h4>
                <p class="text-xs text-muted mt-1"><?= $badge['desc'] ?></p>
                <span class="inline-block mt-2 px-2 py-0.5 bg-success/10 text-success text-xs font-semibold rounded">+<?= $badge['xp'] ?> XP</span>
            </div>
            <?php endforeach; ?>

            <?php foreach ($lockedBadges as $badge): ?>
            <div class="badge-card badge-locked bg-white rounded-xl border border-border p-4 text-center">
                <div class="w-16 h-16 mx-auto mb-3 bg-gradient-to-br from-gray-300 to-gray-400 rounded-2xl flex items-center justify-center text-3xl">
                    <?= $badge['emoji'] ?>
                </div>
                <h4 class="font-bold text-secondary text-sm"><?= $badge['name'] ?></h4>
                <p class="text-xs text-muted mt-1"><?= $badge['desc'] ?></p>
                <span class="inline-block mt-2 px-2 py-0.5 bg-muted/20 text-muted text-xs font-semibold rounded"><?= $badge['missing'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- History Tab -->
    <div id="tab-history" class="hidden">
        <div class="bg-white rounded-xl lg:rounded-2xl border border-border overflow-hidden">
            <div class="p-4 lg:p-5 border-b border-border">
                <h2 class="font-bold text-secondary">Istoric puncte</h2>
            </div>
            <div class="divide-y divide-border">
                <?php foreach ($pointsHistory as $item): ?>
                <?php
                    $iconBg = $item['points'] > 0 ? 'bg-success/10' : 'bg-primary/10';
                    $iconColor = $item['points'] > 0 ? 'text-success' : 'text-primary';
                    $pointsColor = $item['points'] > 0 ? 'text-success' : 'text-primary';
                    $pointsPrefix = $item['points'] > 0 ? '+' : '';
                ?>
                <div class="p-4 lg:p-5 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 <?= $iconBg ?> rounded-lg flex items-center justify-center">
                            <?php if ($item['icon'] === 'plus'): ?>
                            <svg class="w-5 h-5 <?= $iconColor ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <?php elseif ($item['icon'] === 'minus'): ?>
                            <svg class="w-5 h-5 <?= $iconColor ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                            <?php elseif ($item['icon'] === 'badge'): ?>
                            <svg class="w-5 h-5 <?= $iconColor ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                            <?php elseif ($item['icon'] === 'check'): ?>
                            <svg class="w-5 h-5 <?= $iconColor ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="font-medium text-secondary"><?= $item['desc'] ?></p>
                            <p class="text-sm text-muted"><?= $item['date'] ?></p>
                        </div>
                    </div>
                    <span class="text-lg font-bold <?= $pointsColor ?>"><?= $pointsPrefix ?><?= $item['points'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="p-4 text-center border-t border-border">
                <button class="text-primary font-medium text-sm hover:underline">Incarca mai mult</button>
            </div>
        </div>
    </div>

    <!-- Levels Tab -->
    <div id="tab-levels" class="hidden">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-secondary mb-2">Sistemul de niveluri</h2>
            <p class="text-muted">Acumuleaza XP pentru a avansa in nivel si a debloca recompense exclusive.</p>
        </div>

        <div class="space-y-4">
            <?php foreach ($levels as $level): ?>
            <?php
                $isCompleted = $level['status'] === 'completed';
                $isCurrent = $level['status'] === 'current';
                $isLocked = $level['status'] === 'locked';
                $cardClass = $isCompleted ? 'opacity-50' : '';
                if ($isCurrent) $cardClass = 'bg-gradient-to-r from-primary/5 to-accent/5 border-2 border-primary';
                else $cardClass .= ' bg-white border border-border';
            ?>
            <div class="rounded-xl p-4 lg:p-5 <?= $cardClass ?>">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-gradient-to-br <?= $level['gradient'] ?> rounded-xl flex items-center justify-center <?= $isLocked ? 'opacity-50' : '' ?>">
                        <span class="text-2xl"><?= $level['emoji'] ?></span>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="font-bold text-secondary">Niveluri <?= $level['range'] ?>: <?= $level['name'] ?></h3>
                            <?php if ($isCompleted): ?>
                            <span class="text-sm text-success font-medium">✓ Completat</span>
                            <?php elseif ($isCurrent): ?>
                            <span class="px-2 py-0.5 bg-primary text-white text-xs font-bold rounded">ACTUAL</span>
                            <?php else: ?>
                            <span class="text-sm text-muted">Blocat</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-muted"><?= $level['xp'] ?> XP<?= $level['rewards'] ? ' • Deblocheaza: ' . $level['rewards'] : '' ?></p>
                        <?php if ($isCurrent): ?>
                        <div class="h-2 bg-border rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-primary rounded-full" style="width: 38%"></div>
                        </div>
                        <p class="text-xs text-muted mt-1"><?= number_format($userPoints) ?> / 4,000 XP (Nivel <?= $userLevel ?>)</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- How to earn XP -->
        <div class="mt-8 bg-white rounded-xl lg:rounded-2xl border border-border p-5 lg:p-6">
            <h3 class="font-bold text-secondary mb-4">Cum castigi XP?</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-surface rounded-xl">
                    <div class="text-2xl mb-2">🎫</div>
                    <p class="font-semibold text-secondary">Cumpara bilete</p>
                    <p class="text-sm text-success font-medium">+2 XP / leu</p>
                </div>
                <div class="text-center p-4 bg-surface rounded-xl">
                    <div class="text-2xl mb-2">✅</div>
                    <p class="font-semibold text-secondary">Check-in</p>
                    <p class="text-sm text-success font-medium">+50 XP</p>
                </div>
                <div class="text-center p-4 bg-surface rounded-xl">
                    <div class="text-2xl mb-2">⭐</div>
                    <p class="font-semibold text-secondary">Lasa o recenzie</p>
                    <p class="text-sm text-success font-medium">+30 XP</p>
                </div>
                <div class="text-center p-4 bg-surface rounded-xl">
                    <div class="text-2xl mb-2">👥</div>
                    <p class="font-semibold text-secondary">Invita prieteni</p>
                    <p class="text-sm text-success font-medium">+100 XP</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('[id^="tab-"]').forEach(tab => tab.classList.add('hidden'));

    // Reset all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('text-muted');
    });

    // Show selected tab
    document.getElementById('tab-' + tabName).classList.remove('hidden');
    event.target.classList.add('active');
    event.target.classList.remove('text-muted');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
