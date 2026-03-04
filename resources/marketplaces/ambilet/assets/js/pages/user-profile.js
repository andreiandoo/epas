document.addEventListener('DOMContentLoaded', async function() {
    let customer = {};
    let profileData = {};

    try {
        // Load customer data, profile data, and badges in parallel
        // Badges loaded from dedicated endpoint (same as rewards page) which also evaluates/awards new badges
        const [profileRes, dataRes, badgesRes] = await Promise.all([
            AmbiletAPI.customer.getProfile(),
            AmbiletAPI.customer.getProfileData(),
            AmbiletAPI.customer.getBadges().catch(() => ({ success: false }))
        ]);

        if (profileRes.success && profileRes.data) {
            customer = profileRes.data.customer || profileRes.data || {};
        }
        if (dataRes.success && dataRes.data) {
            profileData = dataRes.data;
        }

        // Merge badges: prefer dedicated endpoint (evaluates conditions), fallback to profileData
        if (badgesRes.success && badgesRes.data) {
            const earned = badgesRes.data.earned || badgesRes.data.badges || [];
            if (earned.length > 0) {
                // Normalize field names to match profile format
                profileData.badges = earned.map(b => ({
                    name: b.name,
                    description: b.description,
                    icon: b.icon_url || b.icon,
                    color: b.color,
                    rarity: b.rarity_level || b.rarity,
                    earned_at: b.earned_at,
                }));
            }
        }
    } catch (error) {
        console.error('Failed to load profile data:', error);
    }

    const tasteProfile = profileData.taste_profile || [];
    const topArtists = profileData.top_artists || [];
    const preferredGenres = profileData.preferred_genres || [];
    const citiesVisited = profileData.cities_visited || [];
    const insights = profileData.insights || [];
    const badges = profileData.badges || [];
    const activityData = profileData.activity_data || [];
    const stats = profileData.stats || {};
    const customerType = profileData.customer_type || 'Fan';
    const customerTypeEmoji = profileData.customer_type_emoji || '🌟';

    // User info
    const fi = (customer.first_name || '?')[0].toUpperCase();
    const li = (customer.last_name || '')[0]?.toUpperCase() || '';
    const initialsEl = document.getElementById('user-initials');
    if (customer.avatar) {
        initialsEl.parentElement.innerHTML = `<img src="${customer.avatar}" class="object-cover w-full h-full rounded-full" alt="Avatar">`;
    } else {
        initialsEl.textContent = fi + li;
    }

    document.getElementById('user-name').textContent = customer.full_name || customer.name || 'User';

    const createdAt = customer.created_at ? new Date(customer.created_at) : null;
    const memberSince = createdAt ? createdAt.toLocaleDateString('ro-RO', { month: 'long', year: 'numeric' }) : '...';
    document.getElementById('user-member-since').textContent = 'Membru din ' + memberSince;

    const levelBadge = document.getElementById('user-level-badge');
    if (levelBadge) levelBadge.textContent = customer.points || 0;

    const typeBadge = document.getElementById('user-type-badge');
    if (typeBadge) typeBadge.innerHTML = `${customerTypeEmoji} ${customerType}`;

    const levelText = document.getElementById('user-level-text');
    if (levelText) levelText.textContent = (customer.points || 0) + ' puncte';

    // Stats
    const statEvents = document.getElementById('stat-events');
    const statSpent = document.getElementById('stat-spent');
    const statCities = document.getElementById('stat-cities');
    const statArtists = document.getElementById('stat-artists');
    const tasteCount = document.getElementById('taste-events-count');

    if (statEvents) statEvents.textContent = stats.total_events || 0;
    if (statSpent) statSpent.textContent = (stats.total_spent || 0).toLocaleString('ro-RO');
    if (statCities) statCities.textContent = stats.total_cities || 0;
    if (statArtists) statArtists.textContent = stats.total_artists || 0;
    if (tasteCount) tasteCount.textContent = stats.total_events || 0;

    // User type
    const typeTitle = document.getElementById('user-type-title');
    const typeDesc = document.getElementById('user-type-desc');
    if (typeTitle) typeTitle.textContent = `${customerTypeEmoji} ${customerType}`;
    if (typeDesc) {
        const typeDescriptions = {
            'Meloman': 'Muzica e pasiunea ta! Majoritatea evenimentelor la care participi sunt concerte.',
            'Sportiv': 'Ești fan al sportului! Evenimentele sportive domină lista ta.',
            'Cultural': 'Cultura și arta te definesc! Teatrul și evenimentele culturale sunt preferatele tale.',
            'Festivalier': 'Festivalurile sunt locul tău preferat! Energie și distracție la maximum.',
            'Comedian Fan': 'Râsul e cea mai bună medicină! Stand-up comedy-ul e genul tău.',
            'Explorator': 'Ești curios și deschis! Explorezi diverse tipuri de evenimente.',
        };
        typeDesc.textContent = typeDescriptions[customerType] || 'Participi la diverse tipuri de evenimente.';
    }

    // Render sections
    renderTasteProfile(tasteProfile);
    renderTopArtists(topArtists);
    renderPreferredGenres(preferredGenres);
    renderCities(citiesVisited);
    renderInsights(insights);
    renderBadges(badges);
    initActivityChart(activityData);

    // Show/hide empty states
    toggleEmptyState('taste-profile-container', tasteProfile.length === 0);
    toggleEmptyState('top-artists-container', topArtists.length === 0);
    toggleEmptyState('preferred-genres-container', preferredGenres.length === 0);
    toggleEmptyState('cities-container', citiesVisited.length === 0);
    toggleEmptyState('insights-container', insights.length === 0);
});

function toggleEmptyState(containerId, isEmpty) {
    const container = document.getElementById(containerId);
    if (!container) return;
    if (isEmpty) {
        container.innerHTML = '<p class="py-4 text-sm text-center text-muted">Nu sunt suficiente date încă. Participă la mai multe evenimente!</p>';
    }
}

function renderTasteProfile(profile) {
    const container = document.getElementById('taste-profile-container');
    if (!container || !profile.length) return;

    container.innerHTML = profile.map(genre => {
        const gradientStyle = genre.gradient
            ? `background: linear-gradient(to right, ${genre.gradient[0]}, ${genre.gradient[1]})`
            : 'background: var(--primary)';
        return `
        <div>
            <div class="flex items-center justify-between mb-2 text-sm">
                <span class="flex items-center gap-2 font-medium text-secondary">
                    <span class="text-lg">${genre.emoji || '🎵'}</span> ${genre.name}
                </span>
                <span class="font-bold text-primary">${genre.percentage}%</span>
            </div>
            <div class="h-3 overflow-hidden rounded-full bg-surface">
                <div class="taste-bar h-full rounded-full" style="width: ${genre.percentage}%; ${gradientStyle}"></div>
            </div>
            <p class="mt-1 text-xs text-muted">${genre.count} eveniment${genre.count > 1 ? 'e' : ''}</p>
        </div>
    `;
    }).join('');
}

function renderTopArtists(artists) {
    const container = document.getElementById('top-artists-container');
    if (!container || !artists.length) return;

    container.innerHTML = artists.map(artist => {
        const imgHtml = artist.image
            ? `<img src="${artist.image}" class="object-cover w-full h-full" alt="${artist.name}" onerror="this.parentElement.innerHTML='<div class=\\'flex items-center justify-center w-full h-full text-xl font-bold text-white bg-primary\\'>${artist.name[0]}</div>'">`
            : `<div class="flex items-center justify-center w-full h-full text-xl font-bold text-white bg-primary">${artist.name[0]}</div>`;
        const favHeart = artist.is_favorite ? '<span class="ml-1 text-error" title="Favorit">&#9829;</span>' : '';
        const evtLabel = artist.events_count > 0
            ? `${artist.events_count} eveniment${artist.events_count > 1 ? 'e' : ''}`
            : (artist.is_favorite ? 'Favorit' : '');
        return `
        <div class="text-center artist-card">
            <div class="w-16 h-16 mx-auto mb-2 overflow-hidden rounded-full">
                ${imgHtml}
            </div>
            <p class="text-sm font-semibold text-secondary">${artist.name}${favHeart}</p>
            <p class="text-xs text-muted">${evtLabel}</p>
        </div>
    `;
    }).join('');
}

function renderPreferredGenres(genres) {
    const container = document.getElementById('preferred-genres-container');
    if (!container || !genres.length) return;

    container.innerHTML = genres.map(genre => `
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-secondary">${genre.name}</span>
            <div class="flex items-center gap-2">
                <div class="w-24 h-2 overflow-hidden rounded-full bg-surface">
                    <div class="h-full rounded-full bg-accent" style="width: ${genre.percentage}%"></div>
                </div>
                <span class="w-12 text-xs text-right text-muted">${genre.percentage}%</span>
            </div>
        </div>
    `).join('');
}

function renderCities(cities) {
    const container = document.getElementById('cities-container');
    if (!container || !cities.length) return;

    container.innerHTML = cities.map(city => `
        <div class="flex items-center justify-between">
            <span class="text-sm text-secondary">${city.name}</span>
            <div class="flex items-center gap-2">
                <div class="w-24 h-2 overflow-hidden rounded-full bg-surface">
                    <div class="h-full rounded-full bg-primary" style="width: ${city.percentage}%"></div>
                </div>
                <span class="w-8 text-xs text-muted">${city.count}</span>
            </div>
        </div>
    `).join('');
}

function renderInsights(insights) {
    const container = document.getElementById('insights-container');
    if (!container || !insights.length) return;

    const bgColors = ['bg-red-50', 'bg-blue-50', 'bg-green-50', 'bg-amber-50', 'bg-purple-50'];
    container.innerHTML = insights.map((insight, i) => `
        <div class="p-4 bg-surface rounded-xl">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 ${bgColors[i % bgColors.length]} rounded-lg flex items-center justify-center">
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
    if (!container) return;

    if (!badges.length) {
        container.innerHTML = '<p class="py-4 text-sm text-center text-muted col-span-full">Niciun badge câștigat încă. Continuă să participi la evenimente!</p>';
        return;
    }

    const badgeGradients = ['from-red-400 to-pink-500', 'from-blue-400 to-indigo-500', 'from-green-400 to-emerald-500', 'from-amber-400 to-orange-500', 'from-purple-400 to-violet-500'];
    const badgesHtml = badges.slice(0, 5).map((badge, i) => {
        const emoji = badge.icon ? `<img src="${badge.icon}" class="w-8 h-8" alt="${badge.name}">` : '🏆';
        return `
        <div class="w-14 h-14 bg-gradient-to-br ${badgeGradients[i % badgeGradients.length]} rounded-xl flex items-center justify-center text-2xl" title="${badge.name}${badge.rarity ? ' (' + badge.rarity + ')' : ''}">${emoji}</div>
    `;
    }).join('');

    const remaining = Math.max(0, badges.length - 5);
    const moreHtml = remaining > 0 ? `
        <div class="flex items-center justify-center border w-14 h-14 bg-surface rounded-xl text-muted border-border" title="Mai multe badge-uri">+${remaining}</div>
    ` : '';

    container.innerHTML = badgesHtml + moreHtml;
}

function initActivityChart(data) {
    const canvas = document.getElementById('activityChart');
    if (!canvas) return;

    const labels = data.map(d => d.month || '');
    const values = data.map(d => d.count || 0);

    // If all values are 0, show empty state
    if (values.every(v => v === 0)) {
        canvas.parentElement.innerHTML = '<p class="flex items-center justify-center h-full text-sm text-muted">Nu sunt date de activitate pentru anul curent.</p>';
        return;
    }

    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Evenimente',
                data: values,
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
