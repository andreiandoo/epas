<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Favorite';
$currentPage = 'watchlist';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';
?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6 lg:py-8">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Evenimentele tale favorite</h1>
                <p class="text-muted text-sm mt-1">Tii evidenta evenimentelor care te intereseaza</p>
            </div>
            <div class="flex items-center gap-2">
                <button id="btn-notify-all" class="flex items-center gap-2 px-4 py-2 bg-surface text-secondary rounded-xl text-sm font-medium hover:bg-primary/10 hover:text-primary transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    Notifica-ma pentru toate
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-6">
            <button onclick="UserWatchlist.filter('all')" class="filter-btn active px-4 py-2 bg-primary text-white rounded-xl text-sm font-medium" data-filter="all">Toate (<span id="count-all">0</span>)</button>
            <button onclick="UserWatchlist.filter('upcoming')" class="filter-btn px-4 py-2 bg-surface text-muted rounded-xl text-sm font-medium" data-filter="upcoming">Viitoare (<span id="count-upcoming">0</span>)</button>
            <button onclick="UserWatchlist.filter('ending-soon')" class="filter-btn px-4 py-2 bg-surface text-muted rounded-xl text-sm font-medium" data-filter="ending-soon">Bilete pe terminate (<span id="count-ending">0</span>)</button>
        </div>

        <!-- Watchlist Grid -->
        <div id="watchlist-grid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
            <div class="text-center py-8 col-span-full">
                <div class="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full mx-auto"></div>
                <p class="text-muted mt-2">Se incarca favoritele...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden text-center py-16">
            <div class="w-20 h-20 bg-surface rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-secondary mb-2">Lista ta de favorite e goala</h3>
            <p class="text-muted mb-6">Adauga evenimente la favorite pentru a le gasi mai usor!</p>
            <a href="/" class="btn btn-primary inline-flex items-center gap-2 px-6 py-3 text-white font-semibold rounded-xl">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Descopera evenimente
            </a>
        </div>
    </main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const UserWatchlist = {
    events: [],
    currentFilter: 'all',

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/login?redirect=/user/watchlist';
            return;
        }
        this.loadUserInfo();
        await this.loadWatchlist();
    },

    loadUserInfo() {
        const user = AmbiletAuth.getUser();
        if (user) {
            const initials = user.name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'U';
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                headerAvatar.innerHTML = `<span class="text-sm font-bold text-white">${initials}</span>`;
            }
            const headerPoints = document.getElementById('header-user-points');
            if (headerPoints) {
                headerPoints.textContent = (user.points || 0).toLocaleString();
            }
        }
    },

    async loadWatchlist() {
        try {
            const response = await AmbiletAPI.get('/customer/watchlist');
            if (response.success) {
                this.events = response.data;
            }
        } catch (error) {
            console.log('Using demo data');
            // Demo data
            this.events = [
                {
                    id: 1,
                    title: 'Mos Craciun e Rocker',
                    slug: 'mos-craciun-rocker',
                    image: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=400',
                    date: '2024-12-27',
                    time: '19:00',
                    venue: 'Grand Gala, Baia Mare',
                    city: 'Baia Mare',
                    price_from: 80,
                    genre: 'Rock',
                    tickets_left: 45,
                    is_ending_soon: true,
                    notify_enabled: true
                },
                {
                    id: 2,
                    title: 'Cargo Live',
                    slug: 'cargo-live',
                    image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400',
                    date: '2025-01-15',
                    time: '20:00',
                    venue: 'Arenele Romane',
                    city: 'Bucuresti',
                    price_from: 120,
                    genre: 'Rock',
                    tickets_left: 200,
                    is_ending_soon: false,
                    notify_enabled: false
                },
                {
                    id: 3,
                    title: 'Trooper 30 Years',
                    slug: 'trooper-30-years',
                    image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400',
                    date: '2025-02-20',
                    time: '20:00',
                    venue: 'Sala Palatului',
                    city: 'Bucuresti',
                    price_from: 100,
                    genre: 'Rock',
                    tickets_left: 15,
                    is_ending_soon: true,
                    notify_enabled: true
                },
                {
                    id: 4,
                    title: 'Jazz in the Park',
                    slug: 'jazz-in-the-park',
                    image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400',
                    date: '2025-06-15',
                    time: '18:00',
                    venue: 'Parcul Central',
                    city: 'Cluj-Napoca',
                    price_from: 0,
                    genre: 'Jazz',
                    tickets_left: null,
                    is_ending_soon: false,
                    notify_enabled: false
                }
            ];
        }

        this.updateCounts();
        this.render();
    },

    updateCounts() {
        const now = new Date();
        const upcoming = this.events.filter(e => new Date(e.date) >= now);
        const ending = this.events.filter(e => e.is_ending_soon);

        document.getElementById('count-all').textContent = this.events.length;
        document.getElementById('count-upcoming').textContent = upcoming.length;
        document.getElementById('count-ending').textContent = ending.length;
    },

    filter(type) {
        this.currentFilter = type;

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-primary', 'text-white');
            btn.classList.add('bg-surface', 'text-muted');
        });
        document.querySelector(`[data-filter="${type}"]`).classList.add('active', 'bg-primary', 'text-white');
        document.querySelector(`[data-filter="${type}"]`).classList.remove('bg-surface', 'text-muted');

        this.render();
    },

    render() {
        const container = document.getElementById('watchlist-grid');
        const emptyState = document.getElementById('empty-state');

        let filtered = this.events;
        const now = new Date();

        if (this.currentFilter === 'upcoming') {
            filtered = this.events.filter(e => new Date(e.date) >= now);
        } else if (this.currentFilter === 'ending-soon') {
            filtered = this.events.filter(e => e.is_ending_soon);
        }

        if (filtered.length === 0) {
            container.classList.add('hidden');
            emptyState.classList.remove('hidden');
            return;
        }

        container.classList.remove('hidden');
        emptyState.classList.add('hidden');

        container.innerHTML = filtered.map(event => this.renderCard(event)).join('');
    },

    renderCard(event) {
        const date = new Date(event.date);
        const days = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sam'];
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dateStr = `${days[date.getDay()]}, ${date.getDate()} ${months[date.getMonth()]}`;

        return `
            <div class="event-card bg-white rounded-2xl border border-border overflow-hidden" data-id="${event.id}">
                <div class="relative">
                    <a href="/event/${event.slug}">
                        <img src="${event.image}" class="w-full h-48 object-cover" alt="${event.title}">
                    </a>
                    ${event.is_ending_soon ? '<span class="absolute top-3 left-3 px-2 py-1 bg-error text-white text-xs font-bold rounded">ULTIMELE BILETE</span>' : ''}
                    <button onclick="UserWatchlist.toggleFavorite(${event.id})" class="heart-btn active absolute top-3 right-3 w-10 h-10 bg-white/90 rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-5 h-5 text-error" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-semibold rounded">${event.genre}</span>
                        ${event.tickets_left && event.tickets_left < 50 ? `<span class="text-xs text-warning font-medium">${event.tickets_left} bilete ramase</span>` : ''}
                    </div>
                    <a href="/event/${event.slug}">
                        <h3 class="font-bold text-secondary text-lg mb-2 hover:text-primary transition-colors">${event.title}</h3>
                    </a>
                    <div class="space-y-1.5 text-sm text-muted mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>${dateStr}, ${event.time}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            <span>${event.venue}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-border">
                        <div>
                            <p class="text-xs text-muted">de la</p>
                            <p class="text-lg font-bold text-secondary">${event.price_from > 0 ? event.price_from + ' lei' : 'GRATUIT'}</p>
                        </div>
                        <a href="/event/${event.slug}" class="btn btn-primary px-4 py-2 text-white text-sm font-semibold rounded-xl">
                            Cumpara bilete
                        </a>
                    </div>
                </div>
                <div class="px-4 py-3 bg-surface border-t border-border flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" ${event.notify_enabled ? 'checked' : ''} onchange="UserWatchlist.toggleNotify(${event.id}, this.checked)" class="w-4 h-4 text-primary rounded">
                        <span class="text-xs text-muted">Notifica-ma cand se pune in vanzare</span>
                    </label>
                </div>
            </div>
        `;
    },

    async toggleFavorite(eventId) {
        try {
            await AmbiletAPI.delete(`/customer/watchlist/${eventId}`);
            this.events = this.events.filter(e => e.id !== eventId);
            this.updateCounts();
            this.render();
        } catch (error) {
            console.error('Error removing from watchlist:', error);
        }
    },

    async toggleNotify(eventId, enabled) {
        try {
            await AmbiletAPI.put(`/customer/watchlist/${eventId}/notify`, { enabled });
            const event = this.events.find(e => e.id === eventId);
            if (event) event.notify_enabled = enabled;
        } catch (error) {
            console.error('Error toggling notification:', error);
        }
    },

    logout() {
        AmbiletAuth.logout();
        window.location.href = '/login';
    }
};

document.addEventListener('DOMContentLoaded', () => UserWatchlist.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
