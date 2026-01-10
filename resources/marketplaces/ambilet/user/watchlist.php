<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Favorite';
$currentPage = 'watchlist';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .event-card { transition: all 0.3s ease; }
    .event-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
    .heart-btn { transition: all 0.2s ease; }
    .heart-btn:hover { transform: scale(1.1); }
    .heart-btn.active { color: #EF4444; }
    .notification-badge { animation: pulse 2s infinite; }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    .tab-btn.active { background: white; color: #A51C30; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
</style>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
            <!-- Page Header -->
            <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Favorite</h1>
                    <p class="mt-1 text-sm text-muted">Evenimente pe care le urmaresti</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 p-1 mb-6 bg-surface rounded-xl w-fit">
                <button onclick="showTab('events')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn active" id="tab-btn-events">
                    Evenimente (<span id="events-count">0</span>)
                </button>
                <button onclick="showTab('artists')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted" id="tab-btn-artists">
                    Artisti (<span id="artists-count">0</span>)
                </button>
                <button onclick="showTab('venues')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted" id="tab-btn-venues">
                    Locatii (<span id="venues-count">0</span>)
                </button>
            </div>

            <!-- Events Tab -->
            <div id="tab-events">
                <!-- Notification Alert -->
                <div class="p-4 mb-6 border bg-gradient-to-r from-primary/10 to-accent/10 border-primary/20 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-lg bg-primary/20">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-secondary">Notificari active pentru <span id="notification-count">0</span> evenimente</p>
                            <p class="text-sm text-muted">Vei fi notificat cand biletele devin disponibile sau se apropie de sold out.</p>
                        </div>
                        <a href="/cont/setari" class="text-sm font-medium text-primary whitespace-nowrap">Gestioneaza →</a>
                    </div>
                </div>

                <!-- Events Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6" id="events-grid">
                    <!-- Populated by JavaScript -->
                </div>
            </div>

            <!-- Artists Tab -->
            <div id="tab-artists" class="hidden">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6" id="artists-grid">
                    <!-- Populated by JavaScript -->
                </div>
            </div>

            <!-- Venues Tab -->
            <div id="tab-venues" class="hidden">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6" id="venues-grid">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const WatchlistPage = {
    events: [],
    artists: [],
    venues: [],

    async init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/favorite';
            return;
        }

        await this.loadWatchlist();
    },

    async loadWatchlist() {
        try {
            // Load events from watchlist API
            const eventsResponse = await AmbiletAPI.customer.getWatchlist();
            console.log('[WatchlistPage] Events response:', eventsResponse);
            if (eventsResponse.success && eventsResponse.data) {
                // API returns array directly in data, not data.events
                this.events = Array.isArray(eventsResponse.data) ? eventsResponse.data : [];
                // Transform event data to expected format
                this.events = this.events.map(item => ({
                    id: item.event?.id || item.id,
                    title: item.event?.name || item.title || 'Eveniment',
                    slug: item.event?.slug || item.slug,
                    image: item.event?.image || item.image,
                    date: item.event?.date_formatted || item.date,
                    venue: item.event?.venue || item.venue,
                    city: item.event?.city || item.city,
                    category: item.event?.category || item.category,
                    genre: item.event?.genre || item.genre,
                    price: item.event?.min_price || item.price,
                    sold_out: item.event?.is_sold_out || item.sold_out
                }));
            }

            // Load favorite artists
            try {
                const artistsResponse = await AmbiletAPI.getFavoriteArtists();
                if (artistsResponse.success && artistsResponse.data) {
                    this.artists = Array.isArray(artistsResponse.data) ? artistsResponse.data : [];
                }
            } catch (e) {
                console.log('[WatchlistPage] Artists load error:', e);
                this.artists = [];
            }

            // Load favorite venues
            try {
                const venuesResponse = await AmbiletAPI.getFavoriteVenues();
                console.log('[WatchlistPage] Venues response:', venuesResponse);
                if (venuesResponse.success && venuesResponse.data) {
                    this.venues = Array.isArray(venuesResponse.data) ? venuesResponse.data : [];
                    console.log('[WatchlistPage] Venues loaded:', this.venues.length, 'venues');
                }
            } catch (e) {
                console.log('[WatchlistPage] Venues load error:', e);
                this.venues = [];
            }
        } catch (error) {
            console.log('Watchlist API error:', error);
            this.loadDemoData();
        }

        this.render();
    },

    loadDemoData() {
        if (typeof DEMO_DATA !== 'undefined') {
            this.events = DEMO_DATA.watchlistEvents || [];
            this.artists = DEMO_DATA.watchlistArtists || [];
            this.venues = DEMO_DATA.watchlistVenues || [];
        }
    },

    render() {
        // Update counts
        document.getElementById('events-count').textContent = this.events.length;
        document.getElementById('artists-count').textContent = this.artists.length;
        document.getElementById('venues-count').textContent = this.venues.length;
        document.getElementById('notification-count').textContent = this.events.length;

        // Render all sections
        this.renderEvents();
        this.renderArtists();
        this.renderVenues();
    },

    async removeFromWatchlist(type, id) {
        try {
            const response = await AmbiletAPI.customer.removeFromWatchlist(type, id);
            if (response.success) {
                AmbiletNotifications.success('Eliminat din favorite!');
                // Remove from local array
                if (type === 'event') {
                    this.events = this.events.filter(e => e.id !== id);
                } else if (type === 'artist') {
                    this.artists = this.artists.filter(a => a.id !== id);
                } else if (type === 'venue') {
                    this.venues = this.venues.filter(v => v.id !== id);
                }
                this.render();
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la eliminare.');
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la eliminarea din favorite.');
        }
    },

    renderEvents() {
        const grid = document.getElementById('events-grid');
        if (this.events.length === 0) {
            grid.innerHTML = '<p class="col-span-full text-center py-8 text-muted">Nu ai evenimente in favorite.</p>';
            return;
        }

        grid.innerHTML = this.events.map(event => {
            const isSoldOut = event.sold_out === true;
            const eventId = event.id || 0;
            const eventUrl = '/bilete/' + (event.slug || event.id);
            const hasPrice = event.price && event.price > 0;
            return '<div class="event-card bg-white rounded-xl lg:rounded-2xl border border-border overflow-hidden ' + (isSoldOut ? 'opacity-75' : '') + '">' +
                '<a href="' + eventUrl + '" class="block relative">' +
                    '<img src="' + (event.image || '/assets/images/default-event.jpg') + '" class="w-full h-40 object-cover ' + (isSoldOut ? 'grayscale' : '') + '" alt="' + (event.title || '') + '">' +
                    (isSoldOut ?
                        '<div class="absolute inset-0 flex items-center justify-center bg-black/50">' +
                            '<span class="px-4 py-2 text-sm font-bold text-white rounded-lg bg-error">SOLD OUT</span>' +
                        '</div>'
                    : (event.badge ?
                        '<div class="absolute top-3 left-3">' +
                            '<span class="notification-badge px-2 py-1 ' + (event.badge_color || 'bg-primary') + ' text-white text-xs font-bold rounded-lg">' + event.badge + '</span>' +
                        '</div>'
                    : '')) +
                '</a>' +
                '<button onclick="event.stopPropagation(); WatchlistPage.removeFromWatchlist(\'event\', ' + eventId + ')" class="absolute flex items-center justify-center rounded-full shadow-lg heart-btn active top-3 right-3 w-9 h-9 bg-white/90 backdrop-blur z-10">' +
                    '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' +
                '</button>' +
                '<a href="' + eventUrl + '" class="block p-4">' +
                    '<div class="flex items-center gap-2 mb-2">' +
                        '<span class="px-2 py-0.5 ' + (isSoldOut ? 'bg-muted/20 text-muted' : 'bg-primary/10 text-primary') + ' text-xs font-semibold rounded">' + (event.genre || event.category || 'Eveniment') + '</span>' +
                        '<span class="text-xs text-muted">' + (event.date || '') + '</span>' +
                    '</div>' +
                    '<h3 class="mb-1 font-bold text-secondary">' + (event.title || '') + '</h3>' +
                    '<p class="mb-3 text-sm text-muted">' + (event.venue || '') + '</p>' +
                    '<div class="flex items-center justify-between">' +
                        (isSoldOut ?
                            '<div><span class="text-sm line-through text-muted">' + (event.price || 0) + ' lei</span></div>' +
                            '<span class="px-4 py-2 text-sm font-semibold rounded-lg bg-surface text-muted">Sold out</span>'
                        : (hasPrice ?
                            '<div>' +
                                '<span class="text-lg font-bold text-primary">' + event.price + ' lei</span>' +
                                '<span class="ml-1 text-xs text-muted">de la</span>' +
                            '</div>' +
                            '<span class="px-4 py-2 text-sm font-semibold text-white rounded-lg btn-primary">Cumpara</span>'
                        :
                            '<div><span class="text-sm text-muted">Bilete in curand</span></div>' +
                            '<span class="px-4 py-2 text-sm font-semibold rounded-lg bg-surface text-secondary">Vezi detalii</span>'
                        )) +
                    '</div>' +
                '</a>' +
            '</div>';
        }).join('');
    },

    renderArtists() {
        const grid = document.getElementById('artists-grid');
        if (this.artists.length === 0) {
            grid.innerHTML = '<p class="col-span-full text-center py-8 text-muted">Nu ai artisti in favorite.</p>';
            return;
        }

        grid.innerHTML = this.artists.map(artist => {
            const artistId = artist.id || 0;
            const artistUrl = '/artist/' + (artist.slug || artist.id);
            return '<div class="relative p-5 text-center bg-white border event-card rounded-xl lg:rounded-2xl border-border">' +
                '<button onclick="event.stopPropagation(); WatchlistPage.removeFromWatchlist(\'artist\', ' + artistId + ')" class="absolute flex items-center justify-center w-8 h-8 bg-white border rounded-full shadow-lg heart-btn active top-3 right-3 border-border z-10">' +
                    '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' +
                '</button>' +
                '<a href="' + artistUrl + '" class="block mb-4">' +
                    '<div class="w-24 h-24 mx-auto overflow-hidden rounded-full">' +
                        '<img src="' + (artist.image || '/assets/images/default-artist.jpg') + '" class="object-cover w-full h-full" alt="' + (artist.name || '') + '">' +
                    '</div>' +
                '</a>' +
                '<a href="' + artistUrl + '">' +
                    '<h3 class="mb-1 font-bold text-secondary hover:text-primary">' + (artist.name || '') + '</h3>' +
                '</a>' +
                '<p class="mb-3 text-sm text-muted">' + (artist.genre || '') + '</p>' +
                '<div class="flex items-center justify-center gap-2 text-sm">' +
                    '<a href="' + artistUrl + '" class="px-2 py-1 font-medium rounded-lg bg-success/10 text-success hover:bg-success/20">' + (artist.events || 0) + ' eveniment' + ((artist.events || 0) > 1 ? 'e' : '') + '</a>' +
                '</div>' +
            '</div>';
        }).join('');
    },

    renderVenues() {
        const grid = document.getElementById('venues-grid');
        if (this.venues.length === 0) {
            grid.innerHTML = '<p class="col-span-full text-center py-8 text-muted">Nu ai locatii in favorite.</p>';
            return;
        }

        grid.innerHTML = this.venues.map(venue => {
            const venueId = venue.id || 0;
            const venueUrl = '/locatie/' + (venue.slug || venue.id);
            return '<div class="relative overflow-hidden bg-white border event-card rounded-xl lg:rounded-2xl border-border">' +
                '<a href="' + venueUrl + '" class="block h-32">' +
                    '<img src="' + (venue.image || '/assets/images/default-venue.jpg') + '" class="object-cover w-full h-full" alt="' + (venue.name || '') + '">' +
                '</a>' +
                '<button onclick="event.stopPropagation(); WatchlistPage.removeFromWatchlist(\'venue\', ' + venueId + ')" class="absolute flex items-center justify-center w-8 h-8 rounded-full shadow-lg heart-btn active top-3 right-3 bg-white/90 backdrop-blur z-10">' +
                    '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' +
                '</button>' +
                '<a href="' + venueUrl + '" class="block p-4">' +
                    '<h3 class="mb-1 font-bold text-secondary hover:text-primary">' + (venue.name || '') + '</h3>' +
                    '<p class="mb-2 text-sm text-muted">' + (venue.city || '') + '</p>' +
                    '<span class="px-2 py-1 text-xs font-medium rounded-lg bg-primary/10 text-primary">' + (venue.events || 0) + ' evenimente</span>' +
                '</a>' +
            '</div>';
        }).join('');
    }
};

// Initialize page
document.addEventListener('DOMContentLoaded', () => WatchlistPage.init());

function showTab(tabName) {
    // Hide all tabs
    document.getElementById('tab-events').classList.add('hidden');
    document.getElementById('tab-artists').classList.add('hidden');
    document.getElementById('tab-venues').classList.add('hidden');

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
