<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Contul Meu';
$currentPage = 'dashboard';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';
?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6 lg:py-8">
        <!-- Welcome Section with Level -->
        <div class="bg-gradient-to-r from-primary via-primary-dark to-secondary rounded-2xl lg:rounded-3xl p-5 lg:p-8 mb-6 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-1/2 w-48 h-48 bg-white/5 rounded-full translate-y-1/2"></div>

            <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <div class="w-16 h-16 lg:w-20 lg:h-20 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur">
                            <span id="welcome-initials" class="text-2xl lg:text-3xl font-bold">--</span>
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-7 h-7 bg-accent rounded-lg flex items-center justify-center badge-glow">
                            <span id="user-level" class="text-xs font-bold">1</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-white/70 text-sm">Bun venit inapoi,</p>
                        <h1 id="welcome-name" class="text-xl lg:text-2xl font-bold">--</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <span id="user-badge" class="px-2 py-0.5 bg-accent/20 text-accent text-xs font-bold rounded">🎸 ROCK ENTHUSIAST</span>
                        </div>
                    </div>
                </div>

                <!-- Level Progress -->
                <div class="lg:w-80">
                    <div class="flex items-center justify-between mb-2">
                        <span id="level-title" class="text-sm text-white/70">Nivel 1 - Incepator</span>
                        <span id="level-xp" class="text-sm font-bold">0 / 500 XP</span>
                    </div>
                    <div class="h-3 bg-white/20 rounded-full overflow-hidden">
                        <div id="level-progress" class="level-progress h-full rounded-full" style="width: 0%"></div>
                    </div>
                    <p id="level-remaining" class="text-xs text-white/50 mt-1">500 XP pana la nivelul 2</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-6">
            <div class="bg-white rounded-xl lg:rounded-2xl p-4 lg:p-5 border border-border">
                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
                <p id="stat-events" class="text-2xl lg:text-3xl font-bold text-secondary">0</p>
                <p class="text-xs lg:text-sm text-muted">Evenimente participat</p>
            </div>

            <div class="bg-white rounded-xl lg:rounded-2xl p-4 lg:p-5 border border-border">
                <div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-accent" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z"/></svg>
                </div>
                <p id="stat-points" class="text-2xl lg:text-3xl font-bold text-secondary">0</p>
                <p class="text-xs lg:text-sm text-muted">Puncte acumulate</p>
            </div>

            <div class="bg-white rounded-xl lg:rounded-2xl p-4 lg:p-5 border border-border">
                <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                </div>
                <p id="stat-badges" class="text-2xl lg:text-3xl font-bold text-secondary">0</p>
                <p class="text-xs lg:text-sm text-muted">Badge-uri obtinute</p>
            </div>

            <div class="bg-white rounded-xl lg:rounded-2xl p-4 lg:p-5 border border-border">
                <div class="w-10 h-10 bg-error/10 rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <p id="stat-favorites" class="text-2xl lg:text-3xl font-bold text-secondary">0</p>
                <p class="text-xs lg:text-sm text-muted">Evenimente favorite</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Upcoming Events -->
                <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-secondary">Evenimentele mele urmatoare</h2>
                        <a href="/user/tickets" class="text-sm text-primary font-medium hover:underline">Vezi toate &rarr;</a>
                    </div>
                    <div id="upcoming-events" class="space-y-3">
                        <div class="animate-pulse flex gap-4 p-3 bg-surface rounded-xl">
                            <div class="w-20 h-20 bg-gray-200 rounded-xl"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                                <div class="h-5 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Taste Profile -->
                <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-secondary">Profilul tau muzical</h2>
                        <span id="taste-events-count" class="text-xs text-muted">Bazat pe 0 evenimente</span>
                    </div>

                    <!-- Genre Breakdown -->
                    <div id="genre-breakdown" class="space-y-3 mb-6">
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-secondary">🎸 Rock / Metal</span>
                                <span class="text-muted">65%</span>
                            </div>
                            <div class="h-2.5 bg-surface rounded-full overflow-hidden">
                                <div class="taste-bar h-full bg-gradient-to-r from-primary to-primary-dark rounded-full" style="width: 65%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-secondary">🎤 Pop / Dance</span>
                                <span class="text-muted">20%</span>
                            </div>
                            <div class="h-2.5 bg-surface rounded-full overflow-hidden">
                                <div class="taste-bar h-full bg-gradient-to-r from-accent to-warning rounded-full" style="width: 20%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-secondary">🎭 Teatru / Stand-up</span>
                                <span class="text-muted">10%</span>
                            </div>
                            <div class="h-2.5 bg-surface rounded-full overflow-hidden">
                                <div class="taste-bar h-full bg-gradient-to-r from-success to-teal-500 rounded-full" style="width: 10%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-secondary">🎻 Clasic / Jazz</span>
                                <span class="text-muted">5%</span>
                            </div>
                            <div class="h-2.5 bg-surface rounded-full overflow-hidden">
                                <div class="taste-bar h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full" style="width: 5%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- User Type -->
                    <div class="bg-gradient-to-br from-primary/5 to-accent/5 rounded-xl p-4 border border-primary/10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                                <span class="text-2xl">🎸</span>
                            </div>
                            <div>
                                <p id="fan-type" class="font-bold text-secondary">Rock Enthusiast</p>
                                <p class="text-xs text-muted">Tipul tau de fan</p>
                            </div>
                        </div>
                        <p id="fan-description" class="text-sm text-muted">Esti pasionat de concerte rock si metal. Preferi evenimentele live cu energie mare si nu ratezi niciodata o trupa buna.</p>
                    </div>
                </div>

                <!-- Recommended Events -->
                <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-secondary">Recomandate pentru tine</h2>
                        <a href="/" class="text-sm text-primary font-medium hover:underline">Vezi toate &rarr;</a>
                    </div>
                    <div id="recommended-events" class="grid sm:grid-cols-2 gap-4">
                        <div class="animate-pulse rounded-xl overflow-hidden border border-border">
                            <div class="h-32 bg-gray-200"></div>
                            <div class="p-3 space-y-2">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </div>
                        <div class="animate-pulse rounded-xl overflow-hidden border border-border">
                            <div class="h-32 bg-gray-200"></div>
                            <div class="p-3 space-y-2">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Recent Badges -->
                <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-secondary">Badge-uri recente</h2>
                        <a href="/user/rewards" class="text-sm text-primary font-medium hover:underline">Toate &rarr;</a>
                    </div>
                    <div id="recent-badges" class="space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-surface rounded-xl">
                            <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl flex items-center justify-center text-2xl">🎸</div>
                            <div>
                                <p class="font-semibold text-secondary text-sm">Rock Veteran</p>
                                <p class="text-xs text-muted">10+ concerte rock</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-surface rounded-xl">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center text-2xl">🌟</div>
                            <div>
                                <p class="font-semibold text-secondary text-sm">Early Bird</p>
                                <p class="text-xs text-muted">5+ bilete early bird</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-surface rounded-xl">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-xl flex items-center justify-center text-2xl">💎</div>
                            <div>
                                <p class="font-semibold text-secondary text-sm">VIP Lover</p>
                                <p class="text-xs text-muted">3+ bilete VIP achizitionate</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Points Activity -->
                <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-bold text-secondary">Activitate puncte</h2>
                        <a href="/user/rewards" class="text-sm text-primary font-medium hover:underline">Istoric &rarr;</a>
                    </div>
                    <div id="points-activity" class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-border">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-success/10 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-secondary">Bilet Cargo Live</p>
                                    <p class="text-xs text-muted">Acum 2 zile</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-success">+120</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-border">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-success/10 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-secondary">Badge Rock Veteran</p>
                                    <p class="text-xs text-muted">Acum 5 zile</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-success">+200</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-secondary">Reducere folosita</p>
                                    <p class="text-xs text-muted">Acum 1 saptamana</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-primary">-500</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6">
                    <h2 class="font-bold text-secondary mb-4">Actiuni rapide</h2>
                    <div class="space-y-2">
                        <a href="/user/tickets" class="flex items-center gap-3 p-3 bg-surface rounded-xl hover:bg-primary/10 transition-colors group">
                            <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center group-hover:bg-primary transition-colors">
                                <svg class="w-5 h-5 text-primary group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </div>
                            <span class="font-medium text-secondary text-sm">Descarca biletele</span>
                        </a>
                        <a href="/" class="flex items-center gap-3 p-3 bg-surface rounded-xl hover:bg-primary/10 transition-colors group">
                            <div class="w-10 h-10 bg-success/10 rounded-lg flex items-center justify-center group-hover:bg-success transition-colors">
                                <svg class="w-5 h-5 text-success group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <span class="font-medium text-secondary text-sm">Descopera evenimente</span>
                        </a>
                        <a href="/user/rewards" class="flex items-center gap-3 p-3 bg-surface rounded-xl hover:bg-primary/10 transition-colors group">
                            <div class="w-10 h-10 bg-accent/10 rounded-lg flex items-center justify-center group-hover:bg-accent transition-colors">
                                <svg class="w-5 h-5 text-accent group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                            </div>
                            <span class="font-medium text-secondary text-sm">Foloseste punctele</span>
                        </a>
                    </div>
                </div>

                <!-- Favorite Artists -->
                <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6">
                    <h2 class="font-bold text-secondary mb-4">Artistii tai preferati</h2>
                    <div id="favorite-artists" class="flex flex-wrap gap-2">
                        <span class="px-3 py-1.5 bg-primary/10 text-primary text-sm font-medium rounded-full">Dirty Shirt</span>
                        <span class="px-3 py-1.5 bg-primary/10 text-primary text-sm font-medium rounded-full">Cargo</span>
                        <span class="px-3 py-1.5 bg-primary/10 text-primary text-sm font-medium rounded-full">Trooper</span>
                        <span class="px-3 py-1.5 bg-primary/10 text-primary text-sm font-medium rounded-full">Iris</span>
                        <span class="px-3 py-1.5 bg-surface text-muted text-sm font-medium rounded-full border border-border">+5 altii</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const UserDashboard = {
    user: null,
    stats: null,

    async init() {
        if (!AmbiletAuth.isLoggedIn()) {
            window.location.href = '/login?redirect=/user/dashboard';
            return;
        }

        this.user = AmbiletAuth.getCurrentUser();
        await this.loadDashboard();
        this.renderUser();
    },

    async loadDashboard() {
        try {
            const response = await AmbiletAPI.get('/customer/stats');
            if (response.success && response.data) {
                this.stats = response.data;
                this.renderStats();
            }
        } catch (error) {
            console.error('Failed to load dashboard:', error);
            this.stats = {
                events_attended: 23,
                points: 2450,
                badges: 7,
                favorites: 12,
                level: 12,
                level_title: 'Rock Star',
                xp: 2450,
                xp_next: 3000,
                badge_title: '🎸 ROCK ENTHUSIAST'
            };
            this.renderStats();
        }

        await this.loadUpcomingEvents();
        await this.loadRecommendedEvents();
    },

    renderUser() {
        if (!this.user) return;

        const initials = this.getInitials(this.user.first_name, this.user.last_name);
        const fullName = `${this.user.first_name} ${this.user.last_name}`;

        // Update header elements (from user-header.php)
        const headerAvatar = document.getElementById('header-user-avatar');
        if (headerAvatar) {
            headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
        }

        document.getElementById('welcome-initials').textContent = initials;
        document.getElementById('welcome-name').textContent = fullName;
    },

    renderStats() {
        if (!this.stats) return;

        // Update header points (from user-header.php)
        const headerPoints = document.getElementById('header-user-points');
        if (headerPoints) {
            headerPoints.textContent = this.formatNumber(this.stats.points);
        }

        document.getElementById('stat-events').textContent = this.stats.events_attended || 0;
        document.getElementById('stat-points').textContent = this.formatNumber(this.stats.points || 0);
        document.getElementById('stat-badges').textContent = this.stats.badges || 0;
        document.getElementById('stat-favorites').textContent = this.stats.favorites || 0;

        const level = this.stats.level || 1;
        const xp = this.stats.xp || 0;
        const xpNext = this.stats.xp_next || 500;
        const levelTitle = this.stats.level_title || 'Incepator';
        const progress = Math.min(100, (xp / xpNext) * 100);

        document.getElementById('user-level').textContent = level;
        document.getElementById('level-title').textContent = `Nivel ${level} - ${levelTitle}`;
        document.getElementById('level-xp').textContent = `${this.formatNumber(xp)} / ${this.formatNumber(xpNext)} XP`;
        document.getElementById('level-progress').style.width = `${progress}%`;
        document.getElementById('level-remaining').textContent = `${this.formatNumber(xpNext - xp)} XP pana la nivelul ${level + 1}`;

        if (this.stats.badge_title) {
            document.getElementById('user-badge').textContent = this.stats.badge_title;
        }

        document.getElementById('taste-events-count').textContent = `Bazat pe ${this.stats.events_attended || 0} evenimente`;
    },

    async loadUpcomingEvents() {
        try {
            const response = await AmbiletAPI.get('/customer/tickets', { upcoming: true, limit: 2 });
            if (response.success && response.data?.length) {
                this.renderUpcomingEvents(response.data);
                return;
            }
        } catch (e) {}

        this.renderUpcomingEvents([
            {
                event: { title: 'Mos Craciun e Rocker', image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=200', start_date: '2024-12-27', start_time: '19:00', venue: { name: 'Grand Gala', city: 'Baia Mare' } },
                ticket_type: 'VIP', quantity: 2, days_until: 3
            },
            {
                event: { title: 'Cargo Live', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200', start_date: '2025-01-15', start_time: '20:00', venue: { name: 'Arenele Romane', city: 'Bucuresti' } },
                ticket_type: 'Standard', quantity: 1, days_until: 22
            }
        ]);
    },

    renderUpcomingEvents(tickets) {
        const container = document.getElementById('upcoming-events');
        if (!tickets.length) {
            container.innerHTML = '<p class="text-center text-muted py-8">Nu ai evenimente programate</p>';
            return;
        }

        container.innerHTML = tickets.map(t => {
            const event = t.event;
            const daysClass = t.days_until <= 7 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning';
            const typeClass = t.ticket_type.toLowerCase().includes('vip') ? 'bg-primary/10 text-primary' : 'bg-surface text-secondary border border-border';

            return `
                <a href="/event/${event.slug || ''}" class="card-hover flex gap-4 p-3 bg-surface rounded-xl">
                    <div class="w-20 h-20 rounded-xl overflow-hidden flex-shrink-0">
                        <img src="${event.image || '/assets/images/placeholder-event.jpg'}" class="w-full h-full object-cover" alt="">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="px-2 py-0.5 ${daysClass} text-xs font-semibold rounded">In ${t.days_until} zile</span>
                                <h3 class="font-semibold text-secondary mt-1">${event.title}</h3>
                            </div>
                        </div>
                        <p class="text-sm text-muted mt-1">${this.formatDate(event.start_date)}, ${event.start_time || '20:00'}</p>
                        <p class="text-sm text-muted">${event.venue?.name || ''}, ${event.venue?.city || ''}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="px-2 py-0.5 ${typeClass} text-xs font-semibold rounded">${t.quantity}x ${t.ticket_type}</span>
                        </div>
                    </div>
                </a>
            `;
        }).join('');
    },

    async loadRecommendedEvents() {
        try {
            const response = await AmbiletAPI.get('/events', { limit: 2, recommended: true });
            if (response.success && response.data?.length) {
                this.renderRecommendedEvents(response.data);
                return;
            }
        } catch (e) {}

        this.renderRecommendedEvents([
            { slug: 'trooper-unplugged', title: 'Trooper Unplugged', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400', start_date: '2025-01-22', venue: { name: 'Hard Rock Cafe' }, min_price: 80, category: 'Rock', match: 95 },
            { slug: 'dirty-shirt-tour', title: 'Dirty Shirt - Tour 2025', image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400', start_date: '2025-02-05', venue: { name: 'Sala Palatului' }, min_price: 120, category: 'Metal', match: 88 }
        ]);
    },

    renderRecommendedEvents(events) {
        const container = document.getElementById('recommended-events');
        container.innerHTML = events.map(e => `
            <a href="/event/${e.slug}" class="card-hover group rounded-xl overflow-hidden border border-border">
                <div class="relative h-32">
                    <img src="${e.image || '/assets/images/placeholder-event.jpg'}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" alt="">
                    <div class="absolute top-2 left-2 px-2 py-1 bg-primary text-white text-xs font-bold rounded">${e.match || 90}% MATCH</div>
                </div>
                <div class="p-3">
                    <h3 class="font-semibold text-secondary text-sm">${e.title}</h3>
                    <p class="text-xs text-muted mt-1">${this.formatDate(e.start_date)} • ${e.venue?.name || ''}</p>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-sm font-bold text-primary">de la ${e.min_price || 50} lei</span>
                        <span class="px-2 py-0.5 bg-accent/10 text-accent text-xs font-semibold rounded">${e.category || 'Concert'}</span>
                    </div>
                </div>
            </a>
        `).join('');
    },

    logout() {
        AmbiletAuth.logout();
        window.location.href = '/';
    },

    getInitials(firstName, lastName) {
        return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase() || '--';
    },

    formatNumber(num) {
        return new Intl.NumberFormat('ro-RO').format(num);
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
    }
};

document.addEventListener('DOMContentLoaded', () => UserDashboard.init());
</script>
JS;

require_once dirname(__DIR__) . '/includes/scripts.php';
?>
