<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Favorite';
$currentPage = 'watchlist';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/user-header.php';

// Demo events
$events = [
    ['id' => 1, 'title' => 'Trooper Unplugged', 'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400', 'date' => '22 Ian 2025', 'venue' => 'Hard Rock Cafe, Bucuresti', 'price' => 80, 'genre' => 'Rock', 'badge' => '85% Sold', 'badge_color' => 'bg-warning'],
    ['id' => 2, 'title' => 'Dirty Shirt - Tour 2025', 'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400', 'date' => '5 Feb 2025', 'venue' => 'Sala Palatului, Bucuresti', 'price' => 120, 'genre' => 'Metal', 'badge' => 'NOU', 'badge_color' => 'bg-success'],
    ['id' => 3, 'title' => 'Iris - Romantic Tour', 'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400', 'date' => '15 Feb 2025', 'venue' => 'Teatrul National, Cluj', 'price' => 95, 'genre' => 'Rock', 'badge' => null],
    ['id' => 4, 'title' => 'Rock la Mures 2025', 'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400', 'date' => 'Iulie 2025', 'venue' => 'Targu Mures', 'price' => null, 'genre' => 'Festival', 'badge' => 'IN CURAND', 'badge_color' => 'bg-blue-500'],
    ['id' => 5, 'title' => 'Phoenix - Turneu National', 'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=400', 'date' => '28 Feb 2025', 'venue' => 'Filarmonica, Timisoara', 'price' => 150, 'genre' => 'Rock', 'badge' => null],
    ['id' => 6, 'title' => 'Vita de Vie Acoustic', 'image' => 'https://images.unsplash.com/photo-1506157786151-b8491531f063?w=400', 'date' => '10 Ian 2025', 'venue' => 'Control Club, Bucuresti', 'price' => 100, 'genre' => 'Rock', 'badge' => 'SOLD OUT', 'badge_color' => 'bg-error', 'sold_out' => true]
];

// Demo artists
$artists = [
    ['name' => 'Dirty Shirt', 'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=200', 'genre' => 'Metal / Folk', 'events' => 3],
    ['name' => 'Trooper', 'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200', 'genre' => 'Rock', 'events' => 2],
    ['name' => 'Cargo', 'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=200', 'genre' => 'Rock', 'events' => 1]
];

// Demo venues
$venues = [
    ['name' => 'Hard Rock Cafe', 'image' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=400', 'city' => 'Bucuresti', 'events' => 5],
    ['name' => 'Sala Palatului', 'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400', 'city' => 'Bucuresti', 'events' => 3],
    ['name' => 'Arenele Romane', 'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400', 'city' => 'Bucuresti', 'events' => 2]
];
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

<!-- Main Content -->
<main class="px-4 py-6 mx-auto max-w-7xl lg:py-8">
    <!-- Page Header -->
    <div class="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-secondary">Favorite</h1>
            <p class="mt-1 text-sm text-muted">Evenimente pe care le urmaresti</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 p-1 mb-6 bg-surface rounded-xl w-fit">
        <button onclick="showTab('events')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn active">
            Evenimente (<?= count($events) ?>)
        </button>
        <button onclick="showTab('artists')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted">
            Artisti (<?= count($artists) ?>)
        </button>
        <button onclick="showTab('venues')" class="px-4 py-2 text-sm font-medium rounded-lg tab-btn text-muted">
            Locatii (<?= count($venues) ?>)
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
                    <p class="font-semibold text-secondary">Notificari active pentru 8 evenimente</p>
                    <p class="text-sm text-muted">Vei fi notificat cand biletele devin disponibile sau se apropie de sold out.</p>
                </div>
                <a href="/user/settings" class="text-sm font-medium text-primary hover:underline whitespace-nowrap">Gestioneaza →</a>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
            <?php foreach ($events as $event): ?>
            <?php $isSoldOut = isset($event['sold_out']) && $event['sold_out']; ?>
            <div class="event-card bg-white rounded-xl lg:rounded-2xl border border-border overflow-hidden <?= $isSoldOut ? 'opacity-75' : '' ?>">
                <div class="relative">
                    <img src="<?= $event['image'] ?>" class="w-full h-40 object-cover <?= $isSoldOut ? 'grayscale' : '' ?>" alt="<?= $event['title'] ?>">
                    <?php if ($isSoldOut): ?>
                    <div class="absolute inset-0 flex items-center justify-center bg-black/50">
                        <span class="px-4 py-2 text-sm font-bold text-white rounded-lg bg-error">SOLD OUT</span>
                    </div>
                    <?php elseif ($event['badge']): ?>
                    <div class="absolute top-3 left-3">
                        <span class="notification-badge px-2 py-1 <?= $event['badge_color'] ?? 'bg-primary' ?> text-white text-xs font-bold rounded-lg flex items-center gap-1">
                            <?php if ($event['badge'] === '85% Sold'): ?>
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <?php endif; ?>
                            <?= $event['badge'] ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <button class="absolute flex items-center justify-center rounded-full shadow-lg heart-btn active top-3 right-3 w-9 h-9 bg-white/90 backdrop-blur">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2 py-0.5 <?= $isSoldOut ? 'bg-muted/20 text-muted' : 'bg-primary/10 text-primary' ?> text-xs font-semibold rounded"><?= $event['genre'] ?></span>
                        <span class="text-xs text-muted"><?= $event['date'] ?></span>
                    </div>
                    <h3 class="mb-1 font-bold text-secondary"><?= $event['title'] ?></h3>
                    <p class="mb-3 text-sm text-muted"><?= $event['venue'] ?></p>
                    <div class="flex items-center justify-between">
                        <?php if ($isSoldOut): ?>
                        <div><span class="text-sm line-through text-muted"><?= $event['price'] ?> lei</span></div>
                        <button class="flex items-center gap-1 px-4 py-2 text-sm font-semibold rounded-lg bg-surface text-muted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            Alerta resale
                        </button>
                        <?php elseif ($event['price']): ?>
                        <div>
                            <span class="text-lg font-bold text-primary"><?= $event['price'] ?> lei</span>
                            <span class="ml-1 text-xs text-muted">de la</span>
                        </div>
                        <a href="/event" class="px-4 py-2 text-sm font-semibold text-white rounded-lg btn-primary">Cumpara</a>
                        <?php else: ?>
                        <div><span class="text-sm text-muted">Bilete in curand</span></div>
                        <button class="flex items-center gap-1 px-4 py-2 text-sm font-semibold rounded-lg bg-surface text-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            Notifica-ma
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Artists Tab -->
    <div id="tab-artists" class="hidden">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
            <?php foreach ($artists as $artist): ?>
            <div class="p-5 text-center bg-white border event-card rounded-xl lg:rounded-2xl border-border">
                <div class="relative inline-block mb-4">
                    <div class="w-24 h-24 mx-auto overflow-hidden rounded-full">
                        <img src="<?= $artist['image'] ?>" class="object-cover w-full h-full" alt="<?= $artist['name'] ?>">
                    </div>
                    <button class="absolute flex items-center justify-center w-8 h-8 bg-white border rounded-full shadow-lg heart-btn active -bottom-1 -right-1 border-border">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    </button>
                </div>
                <h3 class="mb-1 font-bold text-secondary"><?= $artist['name'] ?></h3>
                <p class="mb-3 text-sm text-muted"><?= $artist['genre'] ?></p>
                <div class="flex items-center justify-center gap-2 text-sm">
                    <span class="px-2 py-1 font-medium rounded-lg bg-success/10 text-success"><?= $artist['events'] ?> eveniment<?= $artist['events'] > 1 ? 'e' : '' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Venues Tab -->
    <div id="tab-venues" class="hidden">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
            <?php foreach ($venues as $venue): ?>
            <div class="overflow-hidden bg-white border event-card rounded-xl lg:rounded-2xl border-border">
                <div class="relative h-32">
                    <img src="<?= $venue['image'] ?>" class="object-cover w-full h-full" alt="<?= $venue['name'] ?>">
                    <button class="absolute flex items-center justify-center w-8 h-8 rounded-full shadow-lg heart-btn active top-3 right-3 bg-white/90 backdrop-blur">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <h3 class="mb-1 font-bold text-secondary"><?= $venue['name'] ?></h3>
                    <p class="mb-2 text-sm text-muted"><?= $venue['city'] ?></p>
                    <span class="px-2 py-1 text-xs font-medium rounded-lg bg-primary/10 text-primary"><?= $venue['events'] ?> evenimente</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/user-footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
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
    event.target.classList.add('active');
    event.target.classList.remove('text-muted');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
