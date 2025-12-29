<?php
/**
 * Events Calendar Page
 * URL: /calendar
 */
require_once 'includes/config.php';

$pageTitle = 'Calendar Evenimente — ' . SITE_NAME;
$pageDescription = 'Planifică-ți experiențele. Vezi toate evenimentele într-un singur loc.';
$transparentHeader = true;
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include 'includes/head.php'; ?>
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
</head>
<body class="min-h-screen font-body bg-surface text-secondary">
    <?php include 'includes/header.php'; ?>

    <!-- Page Hero -->
    <section class="relative px-6 py-12 overflow-hidden bg-gradient-to-br from-slate-800 to-slate-900">
        <div class="absolute -top-24 -right-24 w-[400px] h-[400px] bg-[radial-gradient(circle,rgba(165,28,48,0.3)_0%,transparent_70%)] rounded-full"></div>
        <div class="relative z-10 flex flex-col items-center justify-between gap-6 mx-auto max-w-7xl md:flex-row">
            <div>
                <h1 class="mb-2 text-3xl font-extrabold text-white md:text-4xl">Calendar Evenimente</h1>
                <p class="text-base text-white/70">Planifică-ți experiențele. Vezi toate evenimentele într-un singur loc.</p>
            </div>
            <div class="flex gap-2 bg-white/10 p-1.5 rounded-xl">
                <button class="flex items-center gap-2 px-5 py-2.5 bg-white text-secondary rounded-lg text-sm font-semibold transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Lună
                </button>
                <button class="flex items-center gap-2 px-5 py-2.5 bg-transparent text-white/60 rounded-lg text-sm font-semibold hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    Listă
                </button>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="px-6 py-8 mx-auto max-w-7xl">
        <!-- Calendar Controls -->
        <div class="flex flex-col items-center justify-between gap-4 mb-8 md:flex-row">
            <div class="flex items-center gap-4">
                <button class="flex items-center justify-center transition-all bg-white border border-gray-200 w-11 h-11 rounded-xl text-muted hover:border-primary hover:text-primary">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                </button>
                <h2 class="text-2xl font-bold text-secondary">Ianuarie 2025</h2>
                <button class="flex items-center justify-center transition-all bg-white border border-gray-200 w-11 h-11 rounded-xl text-muted hover:border-primary hover:text-primary">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
                <button class="px-5 py-2.5 bg-gray-100 rounded-xl text-gray-600 text-sm font-semibold hover:bg-gray-200 transition-all">Astăzi</button>
            </div>
            <div class="flex flex-col w-full gap-3 sm:flex-row md:w-auto">
                <select class="px-4 py-3 pr-10 bg-white border border-gray-200 rounded-xl text-sm font-medium text-secondary cursor-pointer appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_12px_center] focus:outline-none focus:border-primary">
                    <option value="">Toate categoriile</option>
                    <option value="concerts">Concerte</option>
                    <option value="festivals">Festivaluri</option>
                    <option value="theater">Teatru</option>
                    <option value="comedy">Stand-up</option>
                    <option value="sport">Sport</option>
                </select>
                <select class="px-4 py-3 pr-10 bg-white border border-gray-200 rounded-xl text-sm font-medium text-secondary cursor-pointer appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_12px_center] focus:outline-none focus:border-primary">
                    <option value="">Toate orașele</option>
                    <option value="bucuresti">București</option>
                    <option value="cluj">Cluj-Napoca</option>
                    <option value="timisoara">Timișoara</option>
                    <option value="iasi">Iași</option>
                </select>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="mb-12 overflow-hidden bg-white border border-gray-200 rounded-2xl">
            <!-- Header -->
            <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Luni</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Marți</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Miercuri</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Joi</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Vineri</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Sâmbătă</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Duminică</div>
            </div>
            <!-- Calendar Body -->
            <div class="grid grid-cols-7" id="calendarGrid">
                <!-- Calendar days will be populated by JavaScript -->
            </div>
            <!-- Legend -->
            <div class="flex flex-wrap items-center justify-center gap-6 p-5 border-t border-gray-200">
                <div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 bg-red-100 rounded"></div>Concerte</div>
                <div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 bg-blue-100 rounded"></div>Festivaluri</div>
                <div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 bg-purple-100 rounded"></div>Teatru & Operă</div>
                <div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 rounded bg-amber-100"></div>Stand-up</div>
                <div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 rounded bg-emerald-100"></div>Sport</div>
            </div>
        </div>

        <!-- Featured This Month -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-secondary">Recomandate luna aceasta</h2>
                <a href="/evenimente" class="flex items-center gap-1.5 text-primary text-sm font-semibold hover:underline">
                    Vezi toate
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4" id="featuredEvents">
                <!-- Events will be populated by JavaScript -->
            </div>
        </section>

        <!-- Subscribe Section -->
        <section class="bg-gradient-to-r from-primary to-[#7f1627] rounded-2xl p-10 flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden">
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-[radial-gradient(circle,rgba(255,255,255,0.1)_0%,transparent_70%)] rounded-full"></div>
            <div class="relative z-10 text-center md:text-left">
                <h2 class="mb-2 text-2xl font-bold text-white">Nu rata niciun eveniment!</h2>
                <p class="text-[15px] text-white/80">Primește notificări când apar evenimente noi în orașul tău.</p>
            </div>
            <form class="relative z-10 flex flex-col w-full gap-3 sm:flex-row md:w-auto">
                <input type="email" placeholder="Adresa ta de email" class="px-5 py-4 bg-white/15 border border-white/20 rounded-xl text-[15px] text-white placeholder-white/50 outline-none focus:border-white w-full sm:w-[300px]">
                <button type="submit" class="px-7 py-4 bg-white rounded-xl text-primary text-[15px] font-semibold hover:-translate-y-0.5 hover:shadow-lg transition-all">Abonează-te</button>
            </form>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>

    <script>
    // Demo calendar data
    const calendarEvents = {
        '2025-01-01': [{ name: 'Concert Revelion', type: 'concert' }],
        '2025-01-03': [{ name: 'Hamlet - TNB', type: 'theater' }],
        '2025-01-04': [{ name: 'Stand-up Micutzu', type: 'comedy' }, { name: "Carla's Dreams", type: 'concert' }],
        '2025-01-10': [{ name: 'Subcarpați Live', type: 'concert' }],
        '2025-01-17': [{ name: 'Winter Fest', type: 'festival' }],
        '2025-01-18': [{ name: 'Winter Fest', type: 'festival' }, { name: 'Irina Rimes', type: 'concert' }],
        '2025-01-25': [{ name: 'Alternosfera', type: 'concert' }]
    };

    const typeColors = {
        concert: 'bg-red-100 text-red-800',
        festival: 'bg-blue-100 text-blue-800',
        theater: 'bg-purple-100 text-purple-800',
        comedy: 'bg-amber-100 text-amber-800',
        sport: 'bg-emerald-100 text-emerald-800'
    };

    function renderCalendar(year, month) {
        const grid = document.getElementById('calendarGrid');
        if (!grid) return;

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDay = (firstDay.getDay() + 6) % 7;
        const today = new Date();

        let html = '';

        // Previous month days
        const prevMonthDays = new Date(year, month, 0).getDate();
        for (let i = startDay - 1; i >= 0; i--) {
            html += `<div class="min-h-[100px] md:min-h-[120px] p-2 border-r border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-center w-8 h-8 mb-1 text-sm font-semibold text-gray-300 rounded-lg">${prevMonthDays - i}</div>
            </div>`;
        }

        // Current month days
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
            const events = calendarEvents[dateStr] || [];

            const dayClass = isToday ? 'bg-red-50' : 'bg-white hover:bg-gray-50';
            const numberClass = isToday ? 'bg-gradient-to-br from-primary to-primary-light text-white' : '';

            html += `<div class="${dayClass} min-h-[100px] md:min-h-[120px] p-2 border-r border-b border-gray-200 cursor-pointer transition-colors">
                <div class="w-8 h-8 flex items-center justify-center text-sm font-semibold text-secondary rounded-lg mb-1.5 ${numberClass}">${day}</div>
                <div class="flex flex-col gap-1">`;

            events.slice(0, 2).forEach(event => {
                html += `<div class="px-2 py-1 rounded-md text-[11px] font-semibold whitespace-nowrap overflow-hidden text-ellipsis ${typeColors[event.type]}">${event.name}</div>`;
            });

            if (events.length > 2) {
                html += `<div class="text-[11px] font-semibold text-primary px-2 cursor-pointer hover:underline">+${events.length - 2} mai multe</div>`;
            }

            html += `</div></div>`;
        }

        // Next month days
        const remainingDays = 42 - (startDay + lastDay.getDate());
        for (let i = 1; i <= remainingDays; i++) {
            html += `<div class="min-h-[100px] md:min-h-[120px] p-2 border-r border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-center w-8 h-8 mb-1 text-sm font-semibold text-gray-300 rounded-lg">${i}</div>
            </div>`;
        }

        grid.innerHTML = html;
    }

    // Featured events demo
    const featuredEvents = [
        { title: 'Winter Fest 2025', category: 'Festival', location: 'Romexpo, București', date: '17 Ian', image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=200&fit=crop' },
        { title: 'Subcarpați Live', category: 'Concert', location: 'Arenele Romane', date: '10 Ian', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400&h=200&fit=crop' },
        { title: 'Hamlet - Premieră', category: 'Teatru', location: 'TNB, București', date: '3 Ian', image: 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=400&h=200&fit=crop' },
        { title: 'Micutzu - Show Nou', category: 'Stand-up', location: 'Sala Palatului', date: '4 Ian', image: 'https://images.unsplash.com/photo-1527224857830-43a7acc85260?w=400&h=200&fit=crop' }
    ];

    function renderFeaturedEvents() {
        const container = document.getElementById('featuredEvents');
        if (!container) return;

        container.innerHTML = featuredEvents.map(event => `
            <a href="/bilete/${event.title.toLowerCase().replace(/\s+/g, '-')}" class="overflow-hidden transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-lg group">
                <div class="relative bg-center bg-cover h-36" style="background-image: url('${event.image}')">
                    <div class="absolute px-3 py-2 text-center bg-white shadow-md top-3 left-3 rounded-xl">
                        <div class="text-xl font-extrabold leading-none text-primary">${event.date.split(' ')[0]}</div>
                        <div class="text-[10px] font-semibold text-muted uppercase">${event.date.split(' ')[1]}</div>
                    </div>
                </div>
                <div class="p-4">
                    <span class="inline-block px-2.5 py-1 bg-red-50 rounded-md text-[11px] font-semibold text-primary mb-2">${event.category}</span>
                    <h3 class="mb-2 text-base font-bold text-secondary line-clamp-2">${event.title}</h3>
                    <p class="flex items-center gap-1.5 text-[13px] text-muted">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        ${event.location}
                    </p>
                </div>
            </a>
        `).join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderCalendar(2025, 0);
        renderFeaturedEvents();
    });
    </script>
</body>
</html>
