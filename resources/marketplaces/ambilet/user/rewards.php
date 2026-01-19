<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Puncte & Recompense';
$currentPage = 'rewards';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
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

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
            <!-- Points Overview Hero -->
            <div class="relative p-6 mb-6 overflow-hidden text-white bg-gradient-to-r from-primary via-primary-dark to-secondary rounded-2xl lg:rounded-3xl lg:p-8">
                <div class="absolute top-0 right-0 w-64 h-64 translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5"></div>
                <div class="absolute bottom-0 w-32 h-32 translate-y-1/2 rounded-full left-1/4 bg-white/5"></div>

                <div class="relative">
                    <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                        <!-- Points Balance -->
                        <div class="lg:col-span-1">
                            <p class="mb-1 text-sm text-white/70">Punctele tale</p>
                            <div class="flex items-baseline gap-2">
                                <span class="text-4xl font-extrabold lg:text-5xl" id="user-points">0</span>
                                <span class="text-white/70">puncte</span>
                            </div>
                            <p class="mt-2 text-sm text-white/60" id="points-value">≈ 0 lei reducere</p>
                        </div>

                        <!-- Level Progress -->
                        <div class="lg:col-span-2">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-xl">
                                        <span class="text-2xl">🎸</span>
                                    </div>
                                    <div>
                                        <p class="text-lg font-bold" id="level-info">Nivel 0 - Loading...</p>
                                        <p class="text-sm text-white/70" id="level-remaining">... XP pana la nivelul urmator</p>
                                    </div>
                                </div>
                                <div class="hidden text-right sm:block">
                                    <p class="text-2xl font-bold" id="xp-progress">0 / 0</p>
                                    <p class="text-xs text-white/70">XP</p>
                                </div>
                            </div>
                            <div class="h-4 overflow-hidden rounded-full bg-white/20">
                                <div class="h-full transition-all duration-1000 rounded-full level-progress" style="width: 0%" id="level-bar"></div>
                            </div>
                            <div class="flex justify-between mt-2 text-xs text-white/60">
                                <span id="level-current">Nivel 0</span>
                                <span id="level-next">Nivel 1 - Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 p-1 mb-6 overflow-x-auto bg-surface rounded-xl w-fit">
                <button onclick="showTab('rewards')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn active whitespace-nowrap" id="tab-btn-rewards">
                    Recompense
                </button>
                <button onclick="showTab('badges')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted whitespace-nowrap" id="tab-btn-badges">
                    Badge-uri (<span id="badges-count">0/0</span>)
                </button>
                <button onclick="showTab('history')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted whitespace-nowrap" id="tab-btn-history">
                    Istoric puncte
                </button>
                <button onclick="showTab('levels')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted whitespace-nowrap" id="tab-btn-levels">
                    Niveluri
                </button>
            </div>

            <!-- Rewards Tab -->
            <div id="tab-rewards">
                <div class="mb-6">
                    <h2 class="mb-2 text-xl font-bold text-secondary">Foloseste-ti punctele</h2>
                    <p class="text-muted">Schimba punctele acumulate pentru reduceri si beneficii exclusive.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6" id="rewards-container">
                    <!-- Populated by JavaScript -->
                </div>
            </div>

            <!-- Badges Tab -->
            <div id="tab-badges" class="hidden">
                <div class="mb-6">
                    <h2 class="mb-2 text-xl font-bold text-secondary">Colectia ta de badge-uri</h2>
                    <p class="text-muted" id="badges-desc">Loading...</p>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5" id="badges-container">
                    <!-- Populated by JavaScript -->
                </div>
            </div>

            <!-- History Tab -->
            <div id="tab-history" class="hidden">
                <div class="overflow-hidden bg-white border rounded-xl lg:rounded-2xl border-border">
                    <div class="p-4 border-b lg:p-5 border-border">
                        <h2 class="font-bold text-secondary">Istoric puncte</h2>
                    </div>
                    <div class="divide-y divide-border" id="history-container">
                        <!-- Populated by JavaScript -->
                    </div>
                    <div class="p-4 text-center border-t border-border">
                        <button class="text-sm font-medium text-primary">Incarca mai mult</button>
                    </div>
                </div>
            </div>

            <!-- Levels Tab -->
            <div id="tab-levels" class="hidden">
                <div class="mb-6">
                    <h2 class="mb-2 text-xl font-bold text-secondary">Sistemul de niveluri</h2>
                    <p class="text-muted">Acumuleaza XP pentru a avansa in nivel si a debloca recompense exclusive.</p>
                </div>

                <div class="space-y-4" id="levels-container">
                    <!-- Populated by JavaScript -->
                </div>

                <!-- How to earn XP -->
                <div class="p-5 mt-8 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                    <h3 class="mb-4 font-bold text-secondary">Cum castigi XP?</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="p-4 text-center bg-surface rounded-xl">
                            <div class="mb-2 text-2xl">🎫</div>
                            <p class="font-semibold text-secondary">Cumpara bilete</p>
                            <p class="text-sm font-medium text-success">+2 XP / leu</p>
                        </div>
                        <div class="p-4 text-center bg-surface rounded-xl">
                            <div class="mb-2 text-2xl">✅</div>
                            <p class="font-semibold text-secondary">Check-in</p>
                            <p class="text-sm font-medium text-success">+50 XP</p>
                        </div>
                        <div class="p-4 text-center bg-surface rounded-xl">
                            <div class="mb-2 text-2xl">⭐</div>
                            <p class="font-semibold text-secondary">Lasa o recenzie</p>
                            <p class="text-sm font-medium text-success">+30 XP</p>
                        </div>
                        <div class="p-4 text-center bg-surface rounded-xl">
                            <div class="mb-2 text-2xl">👥</div>
                            <p class="font-semibold text-secondary">Invita prieteni</p>
                            <p class="text-sm font-medium text-success">+100 XP</p>
                        </div>
                    </div>
                </div>
            </div>
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const RewardsPage = {
    points: 0,
    xp: 0,
    level: 1,
    levelName: 'Newbie',
    nextLevelXP: 1000,
    rewards: [],
    badges: { earned: [], available: [] },
    pointsHistory: [],
    levels: [],

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/puncte';
            return;
        }

        // Load all data in parallel
        await Promise.all([
            this.loadPoints(),
            this.loadXP(),
            this.loadBadges(),
            this.loadRewards(),
            this.loadHistory()
        ]);

        this.render();
    },

    async loadPoints() {
        try {
            const response = await AmbiletAPI.customer.getPoints();
            if (response.success && response.data) {
                // API returns nested structure: data.points.balance
                const pointsData = response.data.points || response.data;
                this.points = pointsData.balance || 0;
                if (response.data.history) {
                    this.pointsHistory = response.data.history;
                }

                // Update header and sidebar points display
                const headerPoints = document.getElementById('header-user-points');
                const sidebarPoints = document.getElementById('sidebar-user-points');
                if (headerPoints) headerPoints.textContent = this.points.toLocaleString();
                if (sidebarPoints) sidebarPoints.textContent = this.points.toLocaleString();

                // Update stored user data
                if (typeof AmbiletAuth !== 'undefined') {
                    AmbiletAuth.updateCustomerData({ points: this.points });
                }
            }
        } catch (error) {
            console.log('Points API error:', error);
            this.loadDemoPoints();
        }
    },

    async loadXP() {
        try {
            const response = await AmbiletAPI.customer.getXP();
            if (response.success && response.data) {
                // API returns nested structure: data.level.current, data.level.name, etc.
                const levelData = response.data.level || {};
                this.xp = levelData.total_xp || response.data.total_xp || 0;
                this.level = levelData.current || (typeof response.data.level === 'number' ? response.data.level : 1);
                this.levelName = levelData.name || response.data.level_name || 'Newbie';
                this.nextLevelXP = levelData.xp_to_next || response.data.next_level_xp || 1000;
                if (response.data.levels) {
                    this.levels = response.data.levels;
                }
            }
        } catch (error) {
            console.log('XP API error:', error);
            this.loadDemoXP();
        }
    },

    async loadBadges() {
        try {
            const response = await AmbiletAPI.customer.getBadges();
            if (response.success && response.data) {
                this.badges = {
                    earned: response.data.earned || [],
                    available: response.data.available || []
                };
            }
        } catch (error) {
            console.log('Badges API error:', error);
            this.loadDemoBadges();
        }
    },

    async loadRewards() {
        try {
            const response = await AmbiletAPI.customer.getRewards();
            if (response.success && response.data) {
                this.rewards = response.data.rewards || response.data || [];
            }
        } catch (error) {
            console.log('Rewards API error:', error);
            this.loadDemoRewards();
        }
    },

    async loadHistory() {
        try {
            const response = await AmbiletAPI.customer.getPointsHistory();
            if (response.success && response.data) {
                this.pointsHistory = response.data.history || response.data || [];
            }
        } catch (error) {
            console.log('History API error:', error);
        }
    },

    loadDemoPoints() {
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.customer) {
            this.points = DEMO_DATA.customer.points || 0;
        }
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.pointsHistory) {
            this.pointsHistory = DEMO_DATA.pointsHistory;
        }
    },

    loadDemoXP() {
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.customer) {
            this.xp = DEMO_DATA.customer.xp || DEMO_DATA.customer.points || 0;
            this.level = DEMO_DATA.customer.level || 1;
            this.levelName = DEMO_DATA.customer.level_name || 'Newbie';
            this.nextLevelXP = DEMO_DATA.customer.next_level_xp || 1000;
        }
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.levels) {
            this.levels = DEMO_DATA.levels;
        }
    },

    loadDemoBadges() {
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.badges) {
            this.badges = {
                earned: DEMO_DATA.badges.unlocked || [],
                available: DEMO_DATA.badges.locked || []
            };
        }
    },

    loadDemoRewards() {
        if (typeof DEMO_DATA !== 'undefined' && DEMO_DATA.rewards) {
            this.rewards = DEMO_DATA.rewards;
        }
    },

    render() {
        // Update hero section with points
        document.getElementById('user-points').textContent = this.points.toLocaleString();
        document.getElementById('points-value').textContent = '≈ ' + (this.points / 100).toFixed(2) + ' lei reducere';

        // Update XP/Level info
        document.getElementById('level-info').textContent = 'Nivel ' + this.level + ' - ' + this.levelName;
        const xpRemaining = Math.max(0, this.nextLevelXP - this.xp);
        document.getElementById('level-remaining').textContent = xpRemaining.toLocaleString() + ' XP pana la nivelul urmator';
        document.getElementById('xp-progress').textContent = this.xp.toLocaleString() + ' / ' + this.nextLevelXP.toLocaleString();
        const progressPercent = Math.min(100, Math.round((this.xp / this.nextLevelXP) * 100));
        document.getElementById('level-bar').style.width = progressPercent + '%';
        document.getElementById('level-current').textContent = 'Nivel ' + this.level;
        document.getElementById('level-next').textContent = 'Nivel ' + (this.level + 1);

        // Update badges count
        const earnedCount = this.badges.earned?.length || 0;
        const availableCount = this.badges.available?.length || 0;
        const totalBadges = earnedCount + availableCount;
        document.getElementById('badges-count').textContent = earnedCount + '/' + totalBadges;
        document.getElementById('badges-desc').textContent = 'Ai obtinut ' + earnedCount + ' din ' + totalBadges + ' badge-uri disponibile. Continua sa participi la evenimente!';

        // Render all sections
        this.renderRewards();
        this.renderBadges();
        this.renderHistory();
        this.renderLevels();
    },

    renderRewards() {
        const container = document.getElementById('rewards-container');
        if (!this.rewards || this.rewards.length === 0) {
            container.innerHTML = '<p class="col-span-full text-center py-8 text-muted">Nu sunt recompense disponibile momentan.</p>';
            return;
        }

        container.innerHTML = this.rewards.map(reward => {
            const isLocked = reward.status === 'locked';
            const isInsufficient = reward.status === 'insufficient' || (reward.points_cost > this.points);
            const isExclusive = reward.status === 'exclusive';
            const canRedeem = !isLocked && !isInsufficient && !isExclusive;

            let cardClass = (isLocked || isInsufficient) ? 'opacity-60' : '';
            if (isExclusive) {
                cardClass = 'shine bg-gradient-to-br from-yellow-50 to-orange-50 border-2 border-accent/30';
            } else {
                cardClass += ' bg-white border border-border';
            }

            let statusBadge = '';
            if (canRedeem) {
                statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-success/10 text-success">DISPONIBIL</span>';
            } else if (isLocked) {
                statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-muted/20 text-muted">' + (reward.lock_reason || 'BLOCAT') + '</span>';
            } else if (isInsufficient) {
                const missing = (reward.points_cost || 0) - this.points;
                statusBadge = '<span class="px-3 py-1 text-xs font-bold rounded-full bg-warning/10 text-warning">' + missing.toLocaleString() + ' LIPSA</span>';
            } else if (isExclusive) {
                statusBadge = '<span class="px-3 py-1 text-xs font-bold text-white rounded-full bg-accent">EXCLUSIV</span>';
            }

            let actionBtn = '';
            if (canRedeem) {
                actionBtn = '<button onclick="RewardsPage.redeemReward(' + reward.id + ')" class="px-4 py-2 text-sm font-semibold text-white rounded-lg btn-primary">Revendica</button>';
            } else {
                actionBtn = '<button class="px-4 py-2 text-sm font-semibold rounded-lg cursor-not-allowed bg-surface text-muted" disabled>Indisponibil</button>';
            }

            const iconColor = (isLocked || isInsufficient || isExclusive) ? 'text-muted' : 'text-accent';
            const pointsColor = (isLocked || isInsufficient || isExclusive) ? 'text-muted' : 'text-secondary';
            const pointsCost = reward.points_cost || reward.points || 0;
            const gradient = reward.gradient || 'from-primary/20 to-accent/20';
            const emoji = reward.emoji || reward.icon || '🎁';

            return '<div class="reward-card rounded-xl lg:rounded-2xl p-5 ' + cardClass + '">' +
                '<div class="flex items-center justify-between mb-4">' +
                    '<div class="w-14 h-14 bg-gradient-to-br ' + gradient + ' rounded-xl flex items-center justify-center">' +
                        '<span class="text-3xl">' + emoji + '</span>' +
                    '</div>' +
                    statusBadge +
                '</div>' +
                '<h3 class="mb-1 font-bold text-secondary">' + (reward.name || reward.title) + '</h3>' +
                '<p class="mb-4 text-sm text-muted">' + (reward.description || reward.desc || '') + '</p>' +
                '<div class="flex items-center justify-between">' +
                    '<div class="flex items-center gap-1">' +
                        '<svg class="w-5 h-5 ' + iconColor + '" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>' +
                        '<span class="font-bold ' + pointsColor + '">' + pointsCost.toLocaleString() + '</span>' +
                    '</div>' +
                    actionBtn +
                '</div>' +
            '</div>';
        }).join('');
    },

    async redeemReward(rewardId) {
        try {
            const response = await AmbiletAPI.customer.redeemReward(rewardId);
            if (response.success) {
                AmbiletNotifications.success('Recompensa a fost revendicata cu succes!');
                await this.loadPoints();
                await this.loadRewards();
                this.render();
            } else {
                AmbiletNotifications.error(response.message || 'Nu s-a putut revendica recompensa.');
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la revendicarea recompensei.');
        }
    },

    renderBadges() {
        const container = document.getElementById('badges-container');
        const badges = this.badges;

        // Combine earned and available badges for display
        const earnedHtml = (badges.earned || []).map(badge => {
            const gradient = badge.gradient || badge.color || 'from-yellow-400 to-orange-500';
            const emoji = badge.emoji || badge.icon || '🏆';
            const xpReward = badge.xp_reward || badge.xp || 0;
            return '<div class="p-4 text-center bg-white border badge-card rounded-xl border-border">' +
                '<div class="w-16 h-16 mx-auto mb-3 bg-gradient-to-br ' + gradient + ' rounded-2xl flex items-center justify-center text-3xl">' +
                    emoji +
                '</div>' +
                '<h4 class="text-sm font-bold text-secondary">' + (badge.name || badge.title) + '</h4>' +
                '<p class="mt-1 text-xs text-muted">' + (badge.description || badge.desc || '') + '</p>' +
                '<span class="inline-block mt-2 px-2 py-0.5 bg-success/10 text-success text-xs font-semibold rounded">+' + xpReward + ' XP</span>' +
            '</div>';
        }).join('');

        const availableHtml = (badges.available || []).map(badge => {
            const emoji = badge.emoji || badge.icon || '🔒';
            const progress = badge.progress || badge.missing || 'Blocat';
            return '<div class="p-4 text-center bg-white border badge-card badge-locked rounded-xl border-border">' +
                '<div class="flex items-center justify-center w-16 h-16 mx-auto mb-3 text-3xl bg-gradient-to-br from-gray-300 to-gray-400 rounded-2xl">' +
                    emoji +
                '</div>' +
                '<h4 class="text-sm font-bold text-secondary">' + (badge.name || badge.title) + '</h4>' +
                '<p class="mt-1 text-xs text-muted">' + (badge.description || badge.desc || '') + '</p>' +
                '<span class="inline-block mt-2 px-2 py-0.5 bg-muted/20 text-muted text-xs font-semibold rounded">' + progress + '</span>' +
            '</div>';
        }).join('');

        container.innerHTML = earnedHtml + availableHtml;
    },

    renderHistory() {
        const container = document.getElementById('history-container');
        if (!this.pointsHistory || this.pointsHistory.length === 0) {
            container.innerHTML = '<p class="p-8 text-center text-muted">Nu ai tranzactii in istoric.</p>';
            return;
        }

        container.innerHTML = this.pointsHistory.map(item => {
            const points = item.points || item.amount || 0;
            const iconBg = points > 0 ? 'bg-success/10' : 'bg-primary/10';
            const iconColor = points > 0 ? 'text-success' : 'text-primary';
            const pointsColor = points > 0 ? 'text-success' : 'text-primary';
            const pointsPrefix = points > 0 ? '+' : '';

            let iconSvg = '';
            const iconType = item.icon || (points > 0 ? 'plus' : 'minus');
            if (iconType === 'plus' || points > 0) {
                iconSvg = '<svg class="w-5 h-5 ' + iconColor + '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>';
            } else if (iconType === 'minus' || points < 0) {
                iconSvg = '<svg class="w-5 h-5 ' + iconColor + '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>';
            } else if (iconType === 'badge') {
                iconSvg = '<svg class="w-5 h-5 ' + iconColor + '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>';
            } else {
                iconSvg = '<svg class="w-5 h-5 ' + iconColor + '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            }

            const description = item.description || item.desc || item.reason || '';
            const date = item.date || item.created_at || '';

            return '<div class="flex items-center justify-between p-4 lg:p-5">' +
                '<div class="flex items-center gap-4">' +
                    '<div class="w-10 h-10 ' + iconBg + ' rounded-lg flex items-center justify-center">' +
                        iconSvg +
                    '</div>' +
                    '<div>' +
                        '<p class="font-medium text-secondary">' + description + '</p>' +
                        '<p class="text-sm text-muted">' + date + '</p>' +
                    '</div>' +
                '</div>' +
                '<span class="text-lg font-bold ' + pointsColor + '">' + pointsPrefix + points + '</span>' +
            '</div>';
        }).join('');
    },

    renderLevels() {
        const container = document.getElementById('levels-container');
        if (!this.levels || this.levels.length === 0) {
            // Use default levels if none from API
            this.levels = [
                { range: '1-5', name: 'Newbie', emoji: '🎵', gradient: 'from-gray-400 to-gray-500', xp: '0 - 500', status: this.level <= 5 ? 'current' : 'completed' },
                { range: '6-10', name: 'Fan', emoji: '🎸', gradient: 'from-blue-400 to-blue-500', xp: '500 - 2,000', status: this.level > 5 && this.level <= 10 ? 'current' : (this.level > 10 ? 'completed' : 'locked') },
                { range: '11-15', name: 'Enthusiast', emoji: '🎤', gradient: 'from-purple-400 to-purple-500', xp: '2,000 - 5,000', status: this.level > 10 && this.level <= 15 ? 'current' : (this.level > 15 ? 'completed' : 'locked') },
                { range: '16+', name: 'Legend', emoji: '👑', gradient: 'from-yellow-400 to-orange-500', xp: '5,000+', status: this.level > 15 ? 'current' : 'locked' }
            ];
        }

        container.innerHTML = this.levels.map(level => {
            const isCompleted = level.status === 'completed';
            const isCurrent = level.status === 'current';
            const isLocked = level.status === 'locked';

            let cardClass = isCompleted ? 'opacity-50' : '';
            if (isCurrent) {
                cardClass = 'bg-gradient-to-r from-primary/5 to-accent/5 border-2 border-primary';
            } else {
                cardClass += ' bg-white border border-border';
            }

            let statusText = '';
            if (isCompleted) {
                statusText = '<span class="text-sm font-medium text-success">✓ Completat</span>';
            } else if (isCurrent) {
                statusText = '<span class="px-2 py-0.5 bg-primary text-white text-xs font-bold rounded">ACTUAL</span>';
            } else {
                statusText = '<span class="text-sm text-muted">Blocat</span>';
            }

            const gradient = level.gradient || 'from-gray-400 to-gray-500';
            const emoji = level.emoji || '⭐';
            const xpRange = level.xp || '';
            const rewards = level.rewards || '';
            const levelRange = level.range || '';
            const levelName = level.name || '';

            const currentProgress = isCurrent ?
                '<div class="h-2 mt-2 overflow-hidden rounded-full bg-border">' +
                    '<div class="h-full rounded-full bg-primary" style="width: ' + Math.min(100, Math.round((this.xp / this.nextLevelXP) * 100)) + '%"></div>' +
                '</div>' +
                '<p class="mt-1 text-xs text-muted">' + this.xp.toLocaleString() + ' / ' + this.nextLevelXP.toLocaleString() + ' XP (Nivel ' + this.level + ')</p>'
                : '';

            return '<div class="rounded-xl p-4 lg:p-5 ' + cardClass + '">' +
                '<div class="flex items-center gap-4">' +
                    '<div class="w-14 h-14 bg-gradient-to-br ' + gradient + ' rounded-xl flex items-center justify-center ' + (isLocked ? 'opacity-50' : '') + '">' +
                        '<span class="text-2xl">' + emoji + '</span>' +
                    '</div>' +
                    '<div class="flex-1">' +
                        '<div class="flex items-center justify-between mb-1">' +
                            '<h3 class="font-bold text-secondary">Niveluri ' + levelRange + ': ' + levelName + '</h3>' +
                            statusText +
                        '</div>' +
                        '<p class="text-sm text-muted">' + xpRange + ' XP' + (rewards ? ' • Deblocheaza: ' + rewards : '') + '</p>' +
                        currentProgress +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }
};

// Initialize page
document.addEventListener('DOMContentLoaded', () => RewardsPage.init());

function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('[id^="tab-"]:not([id^="tab-btn-"])').forEach(tab => tab.classList.add('hidden'));

    // Reset all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('text-muted');
    });

    // Show selected tab
    document.getElementById('tab-' + tabName).classList.remove('hidden');
    document.getElementById('tab-btn-' + tabName).classList.add('active');
    document.getElementById('tab-btn-' + tabName).classList.remove('text-muted');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
