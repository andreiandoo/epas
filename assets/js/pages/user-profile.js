// TODO: API integration needed - fetch profile data from customer API
document.addEventListener('DOMContentLoaded', async function() {
    let customer = {};
    let tasteProfile = [];
    let topArtists = [];
    let citiesVisited = [];
    let insights = [];
    let badges = [];
    let activityData = [];

    try {
        const response = await AmbiletAPI.customer.getProfile();
        if (response.success && response.data) {
            customer = response.data.customer || response.data || {};
            tasteProfile = response.data.tasteProfile || [];
            topArtists = response.data.topArtists || [];
            citiesVisited = response.data.citiesVisited || [];
            insights = response.data.insights || [];
            badges = response.data.badges?.unlocked || response.data.badges || [];
            activityData = response.data.activityData || [];
        }
    } catch (error) {
        console.error('Failed to load profile data:', error);
    }

    // Update user info
    document.getElementById('user-initials').textContent = customer.initials || '--';
    document.getElementById('user-name').textContent = customer.name || 'User';
    document.getElementById('user-member-since').textContent = customer.member_since || '...';
    document.getElementById('user-level-badge').textContent = customer.level || 0;
    document.getElementById('user-type-badge').innerHTML = customer.type || 'Fan';
    document.getElementById('user-level-text').textContent = 'Nivel ' + (customer.level || 0);

    // Update stats
    const stats = customer.stats || {};
    document.getElementById('stat-events').textContent = stats.events || 0;
    document.getElementById('stat-spent').textContent = (stats.spent || 0).toLocaleString();
    document.getElementById('stat-cities').textContent = stats.cities || 0;
    document.getElementById('stat-artists').textContent = stats.artists || 0;
    document.getElementById('taste-events-count').textContent = stats.events || 0;

    // Update user type section
    document.getElementById('user-type-title').textContent = customer.type || 'Fan';
    document.getElementById('user-type-desc').textContent = customer.type_description || '';

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
