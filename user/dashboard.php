<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Contul Meu';
$currentPage = 'dashboard';
$cssBundle = 'account';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .level-progress { background: linear-gradient(90deg, #A51C30 0%, #E67E22 100%); }
    .badge-glow { box-shadow: 0 0 10px rgba(230, 126, 34, 0.5); }
    .taste-bar { transition: width 1s ease-out; }
    .card-hover { transition: all 0.3s ease; }
    .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
</style>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        

    <!-- Welcome Section with Level -->
    <div class="relative p-5 mb-6 overflow-hidden text-white bg-gradient-to-r from-primary via-primary-dark to-secondary rounded-2xl lg:rounded-3xl lg:p-8">
        <div class="absolute top-0 right-0 w-64 h-64 translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5"></div>
        <div class="absolute bottom-0 w-48 h-48 translate-y-1/2 rounded-full left-1/2 bg-white/5"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <div class="flex items-center justify-center w-16 h-16 lg:w-20 lg:h-20 bg-white/20 rounded-2xl backdrop-blur">
                        <span id="welcome-initials" class="text-2xl font-bold lg:text-3xl">--</span>
                    </div>
                    <div class="absolute flex items-center justify-center rounded-lg -bottom-1 -right-1 w-7 h-7 bg-accent badge-glow">
                        <span id="user-level" class="text-xs font-bold">1</span>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-white/90">Bun venit inapoi,</p>
                    <h1 id="welcome-name" class="text-xl font-bold lg:text-2xl">--</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span id="user-badge" class="px-2 py-0.5 bg-accent/20 text-accent text-xs font-bold rounded">ROCK ENTHUSIAST</span>
                    </div>
                </div>
            </div>

            <!-- Level Progress -->
            <div class="lg:w-80">
                <div class="flex items-center justify-between mb-2">
                    <span id="level-title" class="text-sm text-white/90">Nivel 1 - Incepator</span>
                    <span id="level-xp" class="text-sm font-bold">0 / 500 XP</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-white/20">
                    <div id="level-progress" class="h-full rounded-full level-progress" style="width: 0%"></div>
                </div>
                <p id="level-remaining" class="mt-1 text-xs text-white/90">500 XP pana la nivelul 2</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-3 mb-6 lg:grid-cols-4 lg:gap-4">
        <div class="p-4 bg-white border rounded-xl lg:rounded-2xl lg:p-5 border-border">
            <div class="flex items-center justify-center gap-x-2">
                <p id="stat-events" class="text-3xl font-bold lg:text-3xl text-secondary">0</p>
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <p class="text-xs text-center lg:text-sm text-muted">Evenimente participat</p>
        </div>

        <div class="p-4 bg-white border rounded-xl lg:rounded-2xl lg:p-5 border-border">
            <div class="flex items-center justify-center gap-x-2">
                <p id="stat-points" class="text-3xl font-bold lg:text-3xl text-secondary">0</p>
                <svg class="w-6 h-6 text-accent" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>
            </div>
            <p class="text-xs text-center lg:text-sm text-muted">Puncte acumulate</p>
        </div>

        <div class="p-4 bg-white border rounded-xl lg:rounded-2xl lg:p-5 border-border">
            <div class="flex items-center justify-center gap-x-2">
                <p id="stat-badges" class="text-3xl font-bold lg:text-3xl text-secondary">0</p>
                <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </div>
            <p class="text-xs text-center lg:text-sm text-muted">Badge-uri obtinute</p>
        </div>

        <div class="p-4 bg-white border rounded-xl lg:rounded-2xl lg:p-5 border-border">
            <div class="flex items-center justify-center gap-x-2">
                <p id="stat-favorites" class="text-3xl font-bold lg:text-3xl text-secondary">0</p>
                <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </div>
            <p class="text-xs text-center lg:text-sm text-muted">Evenimente favorite</p>
        </div>
    </div>

    <div class="grid gap-6 mobile:grid-cols-1 lg:grid-cols-5">
        <!-- Left Column -->
        <div class="space-y-6 lg:col-span-3">
            <!-- Upcoming Events -->
            <div class="p-4 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-secondary">Evenimentele mele urmatoare</h2>
                    <a href="/cont/bilete" class="text-sm font-medium text-primary">Vezi toate</a>
                </div>
                <div id="upcoming-events" class="space-y-3">
                    <div class="flex gap-4 p-3 animate-pulse bg-surface rounded-xl">
                        <div class="w-20 h-20 bg-gray-200 rounded-xl"></div>
                        <div class="flex-1 space-y-2">
                            <div class="w-1/4 h-4 bg-gray-200 rounded"></div>
                            <div class="w-3/4 h-5 bg-gray-200 rounded"></div>
                            <div class="w-1/2 h-4 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="hidden p-4 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6 mobile:block">
                <h2 class="mb-4 font-bold text-secondary">Actiuni rapide</h2>
                <div class="space-y-2">
                    <a href="/cont/bilete" class="flex items-center gap-3 p-3 transition-colors bg-surface rounded-xl hover:bg-primary/10 group">
                        <div class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg bg-primary/10 group-hover:bg-primary">
                            <svg class="w-5 h-5 text-primary group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-secondary">Descarca biletele</span>
                    </a>
                    <a href="/evenimente" class="flex items-center gap-3 p-3 transition-colors bg-surface rounded-xl hover:bg-primary/10 group">
                        <div class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg bg-success/10 group-hover:bg-success">
                            <svg class="w-5 h-5 text-success group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-secondary">Descopera evenimente</span>
                    </a>
                    <a href="/cont/puncte" class="flex items-center gap-3 p-3 transition-colors bg-surface rounded-xl hover:bg-primary/10 group">
                        <div class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg bg-accent/10 group-hover:bg-accent">
                            <svg class="w-5 h-5 text-accent group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                        </div>
                        <span class="text-sm font-medium text-secondary">Foloseste punctele</span>
                    </a>
                </div>
            </div>

            <!-- Recommended Events -->
            <div class="p-4 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6 mobile:p-0 mobile:border-none mobile:rounded-none">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-secondary">Recomandate pentru tine</h2>
                    <a href="/evenimente" class="text-sm font-medium text-primary">Vezi toate</a>
                </div>
                <div id="recommended-events" class="grid grid-cols-2 gap-4 lg:grid-cols-3 md:gap-5">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="overflow-hidden bg-white border rounded-xl border-border">
                        <div class="h-40 skeleton mobile:h-64"></div>
                        <div class="p-3 space-y-2">
                            <div class="w-3/4 h-4 bg-gray-200 rounded skeleton"></div>
                            <div class="w-1/2 h-3 bg-gray-200 rounded skeleton"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6 lg:col-span-2 mobile:hidden">
            <!-- Quick Actions -->
            <div class="p-4 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                <h2 class="mb-4 font-bold text-secondary">Actiuni rapide</h2>
                <div class="space-y-2">
                    <a href="/cont/bilete" class="flex items-center gap-3 p-3 transition-colors bg-surface rounded-xl hover:bg-primary/10 group">
                        <div class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg bg-primary/10 group-hover:bg-primary">
                            <svg class="w-5 h-5 text-primary group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-secondary">Descarca biletele</span>
                    </a>
                    <a href="/evenimente" class="flex items-center gap-3 p-3 transition-colors bg-surface rounded-xl hover:bg-primary/10 group">
                        <div class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg bg-success/10 group-hover:bg-success">
                            <svg class="w-5 h-5 text-success group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-secondary">Descopera evenimente</span>
                    </a>
                    <a href="/cont/puncte" class="flex items-center gap-3 p-3 transition-colors bg-surface rounded-xl hover:bg-primary/10 group">
                        <div class="flex items-center justify-center w-10 h-10 transition-colors rounded-lg bg-accent/10 group-hover:bg-accent">
                            <svg class="w-5 h-5 text-accent group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                        </div>
                        <span class="text-sm font-medium text-secondary">Foloseste punctele</span>
                    </a>
                </div>
            </div>

            <!-- Recent Badges -->
            <div class="p-4 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-secondary">Badge-uri recente</h2>
                    <a href="/cont/puncte" class="text-sm font-medium text-primary">Toate</a>
                </div>
                <div id="recent-badges" class="space-y-3">
                    <div class="flex items-center gap-3 p-3 animate-pulse bg-surface rounded-xl">
                        <div class="w-12 h-12 bg-gray-200 rounded-xl"></div>
                        <div class="flex-1 space-y-2">
                            <div class="w-1/2 h-4 bg-gray-200 rounded"></div>
                            <div class="w-3/4 h-3 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Favorite Artists -->
            <div class="p-4 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-secondary">Artistii tai preferati</h2>
                    <a href="/cont/favorite" class="text-sm font-medium text-primary">Toti</a>
                </div>
                <div id="favorite-artists" class="flex flex-wrap gap-2">
                    <span class="px-3 py-1.5 bg-surface text-muted text-sm rounded-full animate-pulse">Se incarca...</span>
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
const UserDashboard = {
    user: null,
    stats: null,

    async init() {
        if (!AmbiletAuth.isLoggedIn()) {
            window.location.href = '/autentificare?redirect=/cont';
            return;
        }

        this.user = AmbiletAuth.getCurrentUser();
        this.renderUser();
        await this.loadDashboard();
    },

    async loadDashboard() {
        // Load all data in parallel using dedicated endpoints that work correctly
        await Promise.all([
            this.loadStats(),
            this.loadUpcomingEvents(),
            this.loadRecommendedEvents(),
            this.loadRecentBadges(),
            this.loadFavoriteArtists()
        ]);
    },

    async loadStats() {
        // Use dedicated endpoints (same as rewards.php which works correctly)
        // instead of /customer/stats which returns mismatched field names
        try {
            const [rewardsRes, badgesRes, watchlistRes, statsRes] = await Promise.all([
                AmbiletAPI.customer.getPoints(),
                AmbiletAPI.customer.getBadges(),
                AmbiletAPI.customer.getWatchlist({ limit: 1 }),
                AmbiletAPI.customer.getDashboardStats()
            ]);

            // Points: same parsing as rewards.php
            let points = 0, level = 1, levelName = 'Bronze', xp = 0, xpNext = 500, xpToNext = 500;
            if (rewardsRes.success && rewardsRes.data) {
                const pointsData = rewardsRes.data.points || rewardsRes.data;
                points = pointsData.balance || 0;
                const levelData = rewardsRes.data.level || {};
                xp = levelData.total_xp || rewardsRes.data.total_xp || 0;
                level = levelData.current || (typeof rewardsRes.data.level === 'number' ? rewardsRes.data.level : 1);
                levelName = levelData.name || rewardsRes.data.level_name || 'Bronze';
                xpToNext = levelData.xp_to_next || rewardsRes.data.xp_to_next_level || 500;
                xpNext = xp + xpToNext;
            }

            // Badges count: same parsing as rewards.php
            let badgesCount = 0;
            if (badgesRes.success && badgesRes.data) {
                const earned = badgesRes.data.earned || [];
                badgesCount = earned.length;
            }

            // Watchlist/favorites count
            let favoritesCount = 0;
            if (watchlistRes.success && watchlistRes.data) {
                // API may return total in meta/pagination or array length
                favoritesCount = watchlistRes.meta?.total || watchlistRes.data?.total || 0;
                if (!favoritesCount && Array.isArray(watchlistRes.data)) {
                    favoritesCount = watchlistRes.data.length;
                }
            }

            // Events attended from stats endpoint
            let eventsAttended = 0;
            if (statsRes.success && statsRes.data) {
                const d = statsRes.data;
                eventsAttended = d.events?.past || d.events_attended || 0;
            }

            this.stats = {
                events_attended: eventsAttended,
                points: points,
                badges: badgesCount,
                favorites: favoritesCount,
                level: level,
                level_title: levelName,
                xp: xp,
                xp_next: xpNext,
                badge_title: levelName
            };
        } catch (error) {
            console.warn('Failed to load stats:', error.message);
            this.stats = {
                events_attended: 0, points: 0, badges: 0, favorites: 0,
                level: 1, level_title: 'Bronze', xp: 0, xp_next: 500,
                badge_title: 'MEMBER'
            };
        }
        this.renderStats();
    },

    renderUser() {
        if (!this.user) return;
        const initials = this.getInitials(this.user.first_name, this.user.last_name);
        const fullName = `${this.user.first_name || ''} ${this.user.last_name || ''}`.trim() || this.user.name || 'Utilizator';

        document.getElementById('welcome-initials').textContent = initials;
        document.getElementById('welcome-name').textContent = fullName;
    },

    renderStats() {
        if (!this.stats) return;

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
            document.getElementById('user-badge').textContent = this.stats.badge_title.toUpperCase();
        }
    },

    async loadUpcomingEvents() {
        // Try dedicated upcoming events endpoint first
        try {
            const response = await AmbiletAPI.customer.getUpcomingEvents(5);
            if (response.success && response.data) {
                const events = response.data.upcoming_events || response.data;
                if (Array.isArray(events) && events.length) {
                    this.renderUpcomingEvents(events);
                    return;
                }
            }
        } catch (e) {
            console.warn('Upcoming events endpoint failed:', e.message);
        }

        // Fallback: load upcoming tickets and group by event
        try {
            const ticketsRes = await AmbiletAPI.customer.getAllTickets('upcoming');
            if (ticketsRes.success && ticketsRes.data) {
                const tickets = ticketsRes.data.tickets || ticketsRes.data || [];
                if (Array.isArray(tickets) && tickets.length) {
                    // Group tickets by event and count per event
                    const byEvent = {};
                    tickets.forEach(t => {
                        const eid = t.event?.id;
                        if (!eid) return;
                        if (!byEvent[eid]) {
                            byEvent[eid] = {
                                event: {
                                    id: t.event.id,
                                    name: t.event.name || '',
                                    slug: t.event.slug || '',
                                    date: t.event.date || '',
                                    date_formatted: t.event.date_formatted || '',
                                    time: t.event.time || '',
                                    venue: t.event.venue || '',
                                    city: t.event.city || '',
                                    image: t.event.image || '',
                                    days_until: t.event.days_until,
                                },
                                tickets_count: 0,
                            };
                        }
                        byEvent[eid].tickets_count++;
                    });
                    const events = Object.values(byEvent).slice(0, 5);
                    if (events.length) {
                        this.renderUpcomingEvents(events);
                        return;
                    }
                }
            }
        } catch (e2) {
            console.warn('Tickets fallback failed:', e2.message);
        }

        this.renderUpcomingEvents([]);
    },

    renderUpcomingEvents(events) {
        const container = document.getElementById('upcoming-events');
        if (!events.length) {
            container.innerHTML = '<div class="py-8 text-center"><p class="text-muted">Nu ai evenimente programate</p><a href="/evenimente" class="inline-block px-4 py-2 mt-3 text-sm font-medium text-white rounded-lg bg-primary hover:bg-primary-dark">Descopera evenimente</a></div>';
            return;
        }

        container.innerHTML = events.map(item => {
            // Support both nested {event, tickets_count} and flat event objects
            const e = item.event || item;
            const ticketsCount = item.tickets_count || item.ticket_count || 0;
            const imgSrc = e.image ? (typeof getStorageUrl === 'function' ? getStorageUrl(e.image) : e.image) : '/assets/images/default-event.png';
            const eventName = e.name || e.title || '';
            const eventSlug = e.slug || '';
            const venueName = e.venue?.name || e.venue || '';
            const cityName = e.city?.name || e.city || '';
            const dateStr = e.date_formatted || this.formatDate(e.start_date || e.date);
            const timeStr = e.start_time || e.time || '';

            // Calculate days until event
            let daysUntil = e.days_until;
            if (daysUntil === undefined && (e.start_date || e.date)) {
                const eventDate = new Date(e.start_date || e.date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                eventDate.setHours(0, 0, 0, 0);
                daysUntil = Math.max(0, Math.round((eventDate - today) / 86400000));
            }
            daysUntil = daysUntil || 0;
            const daysClass = daysUntil <= 7 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning';
            const daysText = daysUntil === 0 ? 'Azi' : daysUntil === 1 ? 'Maine' : 'In ' + daysUntil + ' zile';

            return '<a href="/bilete/' + eventSlug + '" class="flex gap-4 p-3 card-hover bg-surface rounded-xl">' +
                '<div class="flex-shrink-0 w-20 h-20 overflow-hidden rounded-xl">' +
                '<img src="' + imgSrc + '" class="object-cover w-full h-full" alt="" loading="lazy">' +
                '</div>' +
                '<div class="flex-1 min-w-0">' +
                '<span class="px-2 py-0.5 ' + daysClass + ' text-xs font-semibold rounded">' + daysText + '</span>' +
                '<h3 class="mt-1 font-semibold truncate text-secondary">' + this.escapeHtml(eventName) + '</h3>' +
                '<p class="mt-1 text-sm text-muted">' + dateStr + (timeStr ? ', ' + timeStr : '') + '</p>' +
                '<p class="mt-1 text-xs text-muted">' + this.escapeHtml(venueName) + (cityName ? ', ' + this.escapeHtml(cityName) : '') + '</p>' +
                (ticketsCount ? '<div class="flex items-center gap-2 mt-2"><span class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-semibold rounded">' + ticketsCount + ' bilete</span></div>' : '') +
                '</div>' +
                '</a>';
        }).join('');
    },

    async loadRecommendedEvents() {
        try {
            // Load promoted/recommended events, same as homepage
            const response = await AmbiletAPI.get('/events', { limit: 6, promoted: true });
            if (response.success) {
                const events = response.data?.data || response.data || [];
                if (Array.isArray(events) && events.length) {
                    this.renderRecommendedEvents(events);
                    return;
                }
            }
        } catch (e) {
            console.warn('Failed to load recommended events:', e.message);
        }

        // Fallback: load latest events
        try {
            const response = await AmbiletAPI.get('/events', { limit: 6 });
            if (response.success) {
                const events = response.data?.data || response.data || [];
                if (Array.isArray(events) && events.length) {
                    this.renderRecommendedEvents(events);
                    return;
                }
            }
        } catch (e) {}

        this.renderRecommendedEvents([]);
    },

    renderRecommendedEvents(events) {
        const container = document.getElementById('recommended-events');
        if (!events.length) {
            container.innerHTML = '<p class="py-8 text-center col-span-full text-muted">Descopera evenimente noi</p>';
            return;
        }

        // Use AmbiletEventCard component for consistent display (same as homepage)
        if (typeof AmbiletEventCard !== 'undefined') {
            container.innerHTML = AmbiletEventCard.renderMany(events.slice(0, 6), {
                urlPrefix: '/bilete/',
                showCategory: true,
                showPrice: true,
                showVenue: true
            });
        } else {
            container.innerHTML = '<p class="py-8 text-center col-span-full text-muted">Descopera evenimente noi</p>';
        }
    },

    async loadRecentBadges() {
        try {
            const response = await AmbiletAPI.customer.getBadges();
            if (response.success && response.data) {
                const earned = response.data.earned || [];
                this.renderRecentBadges(earned.slice(0, 3));
                return;
            }
        } catch (e) {
            console.warn('Failed to load badges:', e.message);
        }
        this.renderRecentBadges([]);
    },

    renderRecentBadges(badges) {
        const container = document.getElementById('recent-badges');
        if (!badges.length) {
            container.innerHTML = '<div class="py-4 text-center"><p class="text-sm text-muted">Nu ai badge-uri inca</p><a href="/cont/puncte" class="text-sm font-medium text-primary">Descopera cum obtii badge-uri</a></div>';
            return;
        }

        // Badge gradient colors for variety
        const gradients = [
            'from-yellow-400 to-orange-500',
            'from-purple-400 to-pink-500',
            'from-blue-400 to-cyan-500',
            'from-green-400 to-emerald-500',
            'from-red-400 to-rose-500'
        ];

        container.innerHTML = badges.map((badge, i) => {
            const icon = badge.icon || badge.emoji || '🏆';
            const name = badge.name || badge.title || 'Badge';
            const desc = badge.description || '';
            const gradient = gradients[i % gradients.length];

            return '<div class="flex items-center gap-3 p-3 bg-surface rounded-xl">' +
                '<div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-2xl bg-gradient-to-br ' + gradient + ' rounded-xl">' + this.escapeHtml(icon) + '</div>' +
                '<div class="min-w-0">' +
                '<p class="text-sm font-semibold truncate text-secondary">' + this.escapeHtml(name) + '</p>' +
                (desc ? '<p class="text-xs truncate text-muted">' + this.escapeHtml(desc) + '</p>' : '') +
                '</div>' +
                '</div>';
        }).join('');
    },

    async loadFavoriteArtists() {
        try {
            const response = await AmbiletAPI.getFavoriteArtists();
            if (response.success && response.data) {
                this.renderFavoriteArtists(response.data);
                return;
            }
        } catch (e) {
            console.warn('Failed to load favorite artists:', e.message);
        }
        this.renderFavoriteArtists([]);
    },

    renderFavoriteArtists(artists) {
        const container = document.getElementById('favorite-artists');
        if (!artists.length) {
            container.innerHTML = '<span class="px-3 py-1.5 bg-surface text-muted text-sm rounded-full">Nu ai artisti preferati</span>';
            return;
        }

        const maxDisplay = 5;
        const displayArtists = artists.slice(0, maxDisplay);
        const remaining = artists.length - maxDisplay;

        let html = displayArtists.map(artist =>
            '<a href="/artist/' + (artist.slug || artist.id) + '" class="px-3 py-1.5 bg-primary/10 text-primary text-sm font-medium rounded-full hover:bg-primary hover:text-white transition-colors">' +
            this.escapeHtml(artist.name) + '</a>'
        ).join('');

        if (remaining > 0) {
            html += '<a href="/cont/favorite" class="px-3 py-1.5 bg-surface text-muted text-sm font-medium rounded-full border border-border hover:bg-primary/10 hover:text-primary transition-colors">+' + remaining + ' altii</a>';
        }

        container.innerHTML = html;
    },

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    getInitials(firstName, lastName) {
        return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase() || '--';
    },

    formatNumber(num) {
        return new Intl.NumberFormat('ro-RO').format(num);
    },

    formatDate(dateStr) {
        if (!dateStr) return '';
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
