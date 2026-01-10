<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Profilul meu';
$currentPage = 'profile';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .stat-card { transition: all 0.3s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
    .taste-bar { transition: width 1s ease-out; }
    .artist-card { transition: all 0.3s ease; }
    .artist-card:hover { transform: scale(1.05); }
</style>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
            <!-- Profile Header -->
            <div class="mb-6 overflow-hidden bg-white border rounded-2xl lg:rounded-3xl border-border">
                <!-- Cover -->
                <div class="relative h-32 lg:h-48 bg-gradient-to-r from-primary via-primary-dark to-secondary">
                    <div class="absolute inset-0 bg-black/20"></div>
                    <div class="absolute top-0 right-0 w-64 h-64 translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5"></div>
                </div>

                <!-- Profile Info -->
                <div class="px-5 pb-6 lg:px-8">
                    <div class="flex flex-col gap-4 -mt-12 lg:flex-row lg:items-end lg:justify-between lg:-mt-16">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                            <div class="relative">
                                <div class="flex items-center justify-center w-24 h-24 border-4 border-white shadow-lg lg:w-32 lg:h-32 bg-gradient-to-br from-primary to-accent rounded-2xl lg:rounded-3xl">
                                    <span class="text-3xl font-bold text-white lg:text-4xl" id="user-initials">--</span>
                                </div>
                                <div class="absolute flex items-center justify-center w-8 h-8 border-2 border-white rounded-lg -bottom-1 -right-1 bg-accent">
                                    <span class="text-xs font-bold text-white" id="user-level-badge">0</span>
                                </div>
                            </div>
                            <div class="lg:pb-2">
                                <h1 class="text-2xl font-bold lg:text-3xl text-secondary" id="user-name">Loading...</h1>
                                <p class="text-muted">Membru din <span id="user-member-since">...</span></p>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="px-3 py-1 text-sm font-bold rounded-full bg-accent/10 text-accent" id="user-type-badge">...</span>
                                    <span class="px-3 py-1 text-sm font-bold rounded-full bg-success/10 text-success" id="user-level-text">Nivel 0</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="/cont/setari" class="flex items-center gap-2 px-4 py-2.5 bg-surface text-secondary rounded-xl text-sm font-medium hover:bg-primary/10 hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Editeaza profilul
                            </a>
                            <button class="flex items-center gap-2 px-4 py-2.5 bg-surface text-secondary rounded-xl text-sm font-medium hover:bg-primary/10 hover:text-primary transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                Partajeaza
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 gap-3 mb-6 lg:grid-cols-4 lg:gap-4">
                <div class="p-4 text-center bg-white border stat-card rounded-xl lg:rounded-2xl lg:p-5 border-border">
                    <p class="text-3xl font-bold lg:text-4xl text-secondary" id="stat-events">0</p>
                    <p class="mt-1 text-sm text-muted">Evenimente</p>
                </div>
                <div class="p-4 text-center bg-white border stat-card rounded-xl lg:rounded-2xl lg:p-5 border-border">
                    <p class="text-3xl font-bold lg:text-4xl text-secondary" id="stat-spent">0</p>
                    <p class="mt-1 text-sm text-muted">Lei cheltuiti</p>
                </div>
                <div class="p-4 text-center bg-white border stat-card rounded-xl lg:rounded-2xl lg:p-5 border-border">
                    <p class="text-3xl font-bold lg:text-4xl text-secondary" id="stat-cities">0</p>
                    <p class="mt-1 text-sm text-muted">Orase vizitate</p>
                </div>
                <div class="p-4 text-center bg-white border stat-card rounded-xl lg:rounded-2xl lg:p-5 border-border">
                    <p class="text-3xl font-bold lg:text-4xl text-secondary" id="stat-artists">0</p>
                    <p class="mt-1 text-sm text-muted">Artisti vazuti</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Taste Profile -->
                    <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-lg font-bold text-secondary">Profilul tau muzical</h2>
                            <span class="text-xs text-muted">Bazat pe <span id="taste-events-count">0</span> evenimente</span>
                        </div>

                        <!-- User Type Card -->
                        <div class="p-5 mb-6 border bg-gradient-to-br from-primary/5 to-accent/5 rounded-xl border-primary/10">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center justify-center w-16 h-16 bg-gradient-to-br from-primary to-accent rounded-xl">
                                    <span class="text-3xl">🎸</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-secondary" id="user-type-title">...</h3>
                                    <p class="mt-1 text-sm text-muted" id="user-type-desc">...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Genre Breakdown -->
                        <h3 class="mb-4 font-semibold text-secondary">Genuri preferate</h3>
                        <div class="space-y-4" id="taste-profile-container">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Activity Chart -->
                    <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Activitatea ta in 2024</h2>
                        <div class="h-64">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>

                    <!-- Top Artists -->
                    <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Artistii tai preferati</h2>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4" id="top-artists-container">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Your Insights -->
                    <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Insights</h2>
                        <div class="space-y-4" id="insights-container">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Cities Map -->
                    <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                        <h2 class="mb-4 text-lg font-bold text-secondary">Orase vizitate</h2>
                        <div class="space-y-3" id="cities-container">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Recent Badges -->
                    <div class="p-5 bg-white border rounded-xl lg:rounded-2xl border-border lg:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-secondary">Badge-uri</h2>
                            <a href="/cont/puncte" class="text-sm font-medium text-primary">Vezi toate →</a>
                        </div>
                        <div class="flex flex-wrap gap-3" id="badges-container">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Share Profile -->
                    <div class="p-5 border bg-gradient-to-br from-primary/5 to-accent/5 rounded-xl lg:rounded-2xl border-primary/20">
                        <h3 class="mb-2 font-bold text-secondary">Partajeaza profilul</h3>
                        <p class="mb-4 text-sm text-muted">Arata-le prietenilor ce concerte ai vazut!</p>
                        <div class="flex gap-2">
                            <button class="flex-1 py-2.5 bg-[#1877F2] text-white rounded-lg text-sm font-medium flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </button>
                            <button class="flex-1 py-2.5 bg-[#1DA1F2] text-white rounded-lg text-sm font-medium flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                            </button>
                            <button class="flex-1 py-2.5 bg-gradient-to-r from-[#833AB4] via-[#FD1D1D] to-[#F77737] text-white rounded-lg text-sm font-medium flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            </button>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Render profile from centralized demo data
document.addEventListener('DOMContentLoaded', function() {
    if (typeof DEMO_DATA === 'undefined') {
        console.error('DEMO_DATA not loaded');
        return;
    }

    const customer = DEMO_DATA.customer || {};
    const tasteProfile = DEMO_DATA.tasteProfile || [];
    const topArtists = DEMO_DATA.topArtists || [];
    const citiesVisited = DEMO_DATA.citiesVisited || [];
    const insights = DEMO_DATA.insights || [];
    const badges = DEMO_DATA.badges?.unlocked || [];
    const activityData = DEMO_DATA.activityData || [];

    // Update user info
    document.getElementById('user-initials').textContent = customer.initials || '--';
    document.getElementById('user-name').textContent = customer.name || 'User';
    document.getElementById('user-member-since').textContent = customer.member_since || '...';
    document.getElementById('user-level-badge').textContent = customer.level || 0;
    document.getElementById('user-type-badge').innerHTML = `🎸 ${customer.type || 'Fan'}`;
    document.getElementById('user-level-text').textContent = `Nivel ${customer.level || 0}`;

    // Update stats
    const stats = customer.stats || {};
    document.getElementById('stat-events').textContent = stats.events || 0;
    document.getElementById('stat-spent').textContent = (stats.spent || 0).toLocaleString();
    document.getElementById('stat-cities').textContent = stats.cities || 0;
    document.getElementById('stat-artists').textContent = stats.artists || 0;
    document.getElementById('taste-events-count').textContent = stats.events || 0;

    // Update user type section
    document.getElementById('user-type-title').textContent = customer.type || 'Music Fan';
    document.getElementById('user-type-desc').textContent = 'Esti pasionat de concerte rock si metal. Preferi evenimentele live cu energie mare si nu ratezi niciodata o trupa buna din Romania.';

    // Render taste profile
    renderTasteProfile(tasteProfile);
    renderTopArtists(topArtists);
    renderCities(citiesVisited);
    renderInsights(insights);
    renderBadges(badges);
    initActivityChart(activityData);
});

function renderTasteProfile(profile) {
    const container = document.getElementById('taste-profile-container');
    container.innerHTML = profile.map(genre => `
        <div>
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="flex items-center gap-2 font-medium text-secondary">
                    <span class="text-lg">${genre.emoji}</span> ${genre.name}
                </span>
                <span class="font-bold text-primary">${genre.percent}%</span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-surface">
                <div class="taste-bar h-full bg-gradient-to-r ${genre.gradient} rounded-full" style="width: ${genre.percent}%"></div>
            </div>
            ${genre.artists ? `
                <p class="mt-1 text-xs text-muted">${genre.events} evenimente • ${genre.artists}</p>
            ` : `
                <p class="mt-1 text-xs text-muted">${genre.events} eveniment${genre.events > 1 ? 'e' : ''}</p>
            `}
        </div>
    `).join('');
}

function renderTopArtists(artists) {
    const container = document.getElementById('top-artists-container');
    container.innerHTML = artists.map(artist => `
        <div class="text-center artist-card">
            <div class="w-16 h-16 mx-auto mb-2 overflow-hidden rounded-full">
                <img src="${artist.image}" class="object-cover w-full h-full" alt="${artist.name}">
            </div>
            <p class="text-sm font-semibold text-secondary">${artist.name}</p>
            <p class="text-xs text-muted">${artist.concerts} concerte</p>
        </div>
    `).join('');
}

function renderCities(cities) {
    const container = document.getElementById('cities-container');
    container.innerHTML = cities.map(city => `
        <div class="flex items-center justify-between">
            <span class="text-sm text-secondary">${city.name}</span>
            <div class="flex items-center gap-2">
                <div class="w-24 h-2 overflow-hidden rounded-full bg-surface">
                    <div class="h-full rounded-full bg-primary" style="width: ${city.percent}%"></div>
                </div>
                <span class="w-8 text-xs text-muted">${city.count}</span>
            </div>
        </div>
    `).join('');
}

function renderInsights(insights) {
    const container = document.getElementById('insights-container');
    container.innerHTML = insights.map(insight => `
        <div class="p-4 bg-surface rounded-xl">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 ${insight.bg} rounded-lg flex items-center justify-center">
                    <span class="text-lg">${insight.icon}</span>
                </div>
                <div>
                    <p class="font-semibold text-secondary">${insight.label}</p>
                    <p class="text-sm text-muted">${insight.value}</p>
                </div>
            </div>
        </div>
    `).join('');
}

function renderBadges(badges) {
    const container = document.getElementById('badges-container');
    const badgesHtml = badges.slice(0, 5).map(badge => `
        <div class="w-14 h-14 bg-gradient-to-br ${badge.gradient} rounded-xl flex items-center justify-center text-2xl" title="${badge.name}">${badge.emoji}</div>
    `).join('');

    const remaining = Math.max(0, badges.length - 5);
    const moreHtml = remaining > 0 ? `
        <div class="flex items-center justify-center border w-14 h-14 bg-surface rounded-xl text-muted border-border" title="Mai multe badge-uri">+${remaining}</div>
    ` : '';

    container.innerHTML = badgesHtml + moreHtml;
}

function initActivityChart(data) {
    const ctx = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Evenimente',
                data: data,
                backgroundColor: 'rgba(165, 28, 48, 0.8)',
                borderColor: '#A51C30',
                borderWidth: 0,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: '#E2E8F0' }
                }
            }
        }
    });
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
