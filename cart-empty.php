<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Cos de cumparaturi';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body class="flex flex-col min-h-screen font-['Plus_Jakarta_Sans'] bg-surface text-secondary">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Main Container -->
    <main class="flex-1">
        <div class="max-w-[1200px] mx-auto px-4 md:px-8 py-8">
            <!-- Page Title -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-secondary">Cos de cumparaturi</h1>
            </div>

            <!-- Empty Cart State -->
            <div class="bg-white rounded-3xl border border-border p-8 md:p-16 text-center mb-12">
                <div class="w-48 h-48 md:w-52 md:h-52 mx-auto mb-8">
                    <svg viewBox="0 0 200 200" fill="none" class="w-full h-full">
                        <!-- Cart body -->
                        <path d="M40 60L50 140H150L165 60H40Z" fill="#F1F5F9" stroke="#E2E8F0" stroke-width="2"/>
                        <!-- Cart handle -->
                        <path d="M25 50H40L50 140" stroke="#CBD5E1" stroke-width="4" stroke-linecap="round"/>
                        <!-- Wheels -->
                        <circle cx="70" cy="155" r="12" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
                        <circle cx="130" cy="155" r="12" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
                        <!-- Empty indicator - sad ticket -->
                        <rect x="70" y="80" width="60" height="40" rx="4" fill="#FEE2E2" stroke="#FCA5A5" stroke-width="2"/>
                        <circle cx="85" cy="95" r="4" fill="#EF4444"/>
                        <circle cx="115" cy="95" r="4" fill="#EF4444"/>
                        <path d="M90 108C90 108 95 103 100 103C105 103 110 108 110 108" stroke="#EF4444" stroke-width="2" stroke-linecap="round"/>
                        <!-- Dotted lines indicating empty -->
                        <path d="M75 130H125" stroke="#CBD5E1" stroke-width="2" stroke-dasharray="6 4"/>
                        <!-- Decorative elements -->
                        <circle cx="170" cy="40" r="8" fill="#FEE2E2"/>
                        <circle cx="30" cy="90" r="6" fill="#DBEAFE"/>
                        <circle cx="175" cy="120" r="5" fill="#D1FAE5"/>
                    </svg>
                </div>
                <h2 class="text-xl md:text-2xl font-bold text-secondary mb-3">Cosul tau este gol</h2>
                <p class="text-base text-muted max-w-md mx-auto mb-8">Nu ai adaugat inca niciun bilet in cos. Descopera evenimentele disponibile si gaseste experiente memorabile!</p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/evenimente" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-primary to-red-700 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Descopera evenimente
                    </a>
                    <a href="/cont/favorite" class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-secondary bg-white border border-border hover:bg-surface transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        Vezi favorite
                    </a>
                </div>

                <div class="flex flex-col md:flex-row justify-center gap-4 md:gap-12 mt-12 pt-8 border-t border-border">
                    <div class="flex items-center justify-center gap-3 text-muted text-sm">
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Plati securizate 100%
                    </div>
                    <div class="flex items-center justify-center gap-3 text-muted text-sm">
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Bilete instant pe email
                    </div>
                    <div class="flex items-center justify-center gap-3 text-muted text-sm">
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        Suport 24/7
                    </div>
                </div>
            </div>

            <!-- Suggestions Section -->
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-secondary">Evenimente populare</h2>
                    <a href="/evenimente/populare" class="text-primary font-semibold text-sm flex items-center gap-1">
                        Vezi toate
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" id="popular-events-grid">
                    <!-- Events loaded dynamically -->
                </div>
            </section>

            <!-- Categories Section -->
            <section>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-secondary">Exploreaza categorii</h2>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <a href="/evenimente/muzica" class="bg-white rounded-2xl border border-border p-6 text-center hover:-translate-y-0.5 hover:shadow-md hover:border-primary transition-all">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-red-100 text-red-600 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-secondary">Muzica</div>
                    </a>
                    <a href="/evenimente/teatru" class="bg-white rounded-2xl border border-border p-6 text-center hover:-translate-y-0.5 hover:shadow-md hover:border-primary transition-all">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-secondary">Teatru</div>
                    </a>
                    <a href="/evenimente/sport" class="bg-white rounded-2xl border border-border p-6 text-center hover:-translate-y-0.5 hover:shadow-md hover:border-primary transition-all">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-green-100 text-green-600 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-secondary">Sport</div>
                    </a>
                    <a href="/evenimente/stand-up" class="bg-white rounded-2xl border border-border p-6 text-center hover:-translate-y-0.5 hover:shadow-md hover:border-primary transition-all">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-secondary">Stand-up</div>
                    </a>
                    <a href="/evenimente/familie" class="bg-white rounded-2xl border border-border p-6 text-center hover:-translate-y-0.5 hover:shadow-md hover:border-primary transition-all">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-secondary">Familie</div>
                    </a>
                    <a href="/evenimente/festivaluri" class="bg-white rounded-2xl border border-border p-6 text-center hover:-translate-y-0.5 hover:shadow-md hover:border-primary transition-all">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-secondary">Festivaluri</div>
                    </a>
                </div>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <?php include __DIR__ . '/includes/scripts.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load popular events
        loadPopularEvents();
    });

    async function loadPopularEvents() {
        const grid = document.getElementById('popular-events-grid');

        try {
            const response = await AmbiletAPI.get('/events/popular?limit=4');
            if (response.success && response.data.length > 0) {
                grid.innerHTML = response.data.map(event => createEventCard(event)).join('');
            } else {
                grid.innerHTML = createPlaceholderEvents();
            }
        } catch (error) {
            grid.innerHTML = createPlaceholderEvents();
        }
    }

    function createEventCard(event) {
        const gradients = [
            'from-indigo-500 to-purple-600',
            'from-pink-500 to-rose-500',
            'from-emerald-500 to-teal-500',
            'from-amber-500 to-orange-500'
        ];
        const randomGradient = gradients[Math.floor(Math.random() * gradients.length)];

        return `
            <div class="bg-white rounded-2xl border border-border overflow-hidden hover:-translate-y-1 hover:shadow-xl transition-all">
                <div class="h-40 relative overflow-hidden">
                    <div class="w-full h-full bg-gradient-to-br ${randomGradient} flex items-center justify-center">
                        <svg class="w-12 h-12 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                    </div>
                    ${event.badge ? `<span class="absolute top-3 left-3 px-2.5 py-1 bg-primary text-white rounded-md text-xs font-bold uppercase tracking-wide">${event.badge}</span>` : ''}
                    <button class="absolute top-3 right-3 w-8 h-8 bg-white rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <div class="text-xs font-semibold text-primary uppercase tracking-wide mb-1">${event.date || 'Data TBA'}</div>
                    <h3 class="font-bold text-secondary mb-1 line-clamp-2"><a href="/eveniment/${event.slug}" class="hover:text-primary">${event.title}</a></h3>
                    <div class="text-sm text-muted flex items-center gap-1 mb-3">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        ${event.venue || 'Locatie TBA'}
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <div class="font-bold text-secondary">${event.price || 'N/A'} <span class="text-xs font-normal text-muted">RON</span></div>
                        <a href="/eveniment/${event.slug}" class="px-3.5 py-2 bg-gradient-to-r from-primary to-red-700 text-white rounded-lg text-sm font-semibold hover:-translate-y-0.5 hover:shadow-md hover:shadow-primary/30 transition-all">Cumpara</a>
                    </div>
                </div>
            </div>
        `;
    }

    function createPlaceholderEvents() {
        const events = [
            { title: 'Coldplay - Music of the Spheres', date: '15 Iunie 2025', venue: 'Arena Nationala, Bucuresti', price: '350', gradient: 'from-indigo-500 to-purple-600', badge: 'Hot' },
            { title: 'UNTOLD Festival 2025', date: '7-10 August 2025', venue: 'Cluj Arena, Cluj-Napoca', price: '499', gradient: 'from-pink-500 to-rose-500', badge: 'Sold Out Soon' },
            { title: 'Stand-up Comedy cu Micutzu', date: '22 Martie 2025', venue: 'Sala Palatului, Bucuresti', price: '89', gradient: 'from-emerald-500 to-teal-500', badge: 'Nou' },
            { title: 'Cirque du Soleil - Alegria', date: '5 Aprilie 2025', venue: 'Romexpo, Bucuresti', price: '199', gradient: 'from-amber-500 to-orange-500', badge: '' }
        ];

        return events.map(event => `
            <div class="bg-white rounded-2xl border border-border overflow-hidden hover:-translate-y-1 hover:shadow-xl transition-all">
                <div class="h-40 relative overflow-hidden">
                    <div class="w-full h-full bg-gradient-to-br ${event.gradient} flex items-center justify-center">
                        <svg class="w-12 h-12 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                    </div>
                    ${event.badge ? `<span class="absolute top-3 left-3 px-2.5 py-1 ${event.badge === 'Hot' ? 'bg-amber-500' : event.badge === 'Nou' ? 'bg-green-500' : 'bg-primary'} text-white rounded-md text-xs font-bold uppercase tracking-wide">${event.badge}</span>` : ''}
                    <button class="absolute top-3 right-3 w-8 h-8 bg-white rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <div class="text-xs font-semibold text-primary uppercase tracking-wide mb-1">${event.date}</div>
                    <h3 class="font-bold text-secondary mb-1 line-clamp-2"><a href="#" class="hover:text-primary">${event.title}</a></h3>
                    <div class="text-sm text-muted flex items-center gap-1 mb-3">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        ${event.venue}
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <div class="font-bold text-secondary">${event.price} <span class="text-xs font-normal text-muted">RON de la</span></div>
                        <button class="px-3.5 py-2 bg-gradient-to-r from-primary to-red-700 text-white rounded-lg text-sm font-semibold hover:-translate-y-0.5 hover:shadow-md hover:shadow-primary/30 transition-all">Cumpara</button>
                    </div>
                </div>
            </div>
        `).join('');
    }
    </script>
</body>
</html>
