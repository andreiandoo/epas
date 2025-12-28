/**
 * AmBilet.ro Main Application
 */

document.addEventListener('DOMContentLoaded', async () => {
    // Load featured events on homepage
    if (document.getElementById('events-grid')) {
        await loadFeaturedEvents();
    }

    // Setup search
    setupSearch();
});

/**
 * Load featured events
 */
async function loadFeaturedEvents() {
    const eventsGrid = document.getElementById('events-grid');

    try {
        const response = await window.api.getEvents({
            per_page: 8,
            sort: 'starts_at',
            order: 'asc'
        });

        if (response.success && response.data.length > 0) {
            eventsGrid.innerHTML = response.data.map(event => createEventCard(event)).join('');
        } else {
            eventsGrid.innerHTML = '<p class="no-events">Nu exista evenimente disponibile momentan.</p>';
        }
    } catch (error) {
        console.error('Failed to load events:', error);
        eventsGrid.innerHTML = '<p class="error">Eroare la incarcarea evenimentelor. Va rugam reincercati.</p>';
    }
}

/**
 * Create event card HTML
 */
function createEventCard(event) {
    const date = new Date(event.starts_at);
    const formattedDate = date.toLocaleDateString('ro-RO', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
    const formattedTime = date.toLocaleTimeString('ro-RO', {
        hour: '2-digit',
        minute: '2-digit'
    });

    const minPrice = event.ticket_types && event.ticket_types.length > 0
        ? Math.min(...event.ticket_types.map(tt => tt.price))
        : null;

    return `
        <article class="event-card">
            <a href="/event/${event.id}">
                <div class="event-image">
                    <img src="${event.image_url || 'images/placeholder-event.jpg'}" alt="${event.name}">
                    <span class="event-category">${event.category || 'Eveniment'}</span>
                </div>
                <div class="event-content">
                    <h3 class="event-title">${event.name}</h3>
                    <div class="event-meta">
                        <span class="event-date">${formattedDate} - ${formattedTime}</span>
                        ${event.venue ? `<span class="event-venue">${event.venue.name}, ${event.venue.city}</span>` : ''}
                    </div>
                    ${minPrice !== null ? `<div class="event-price">de la ${minPrice.toFixed(2)} RON</div>` : ''}
                </div>
            </a>
        </article>
    `;
}

/**
 * Setup search functionality
 */
function setupSearch() {
    const searchInput = document.getElementById('search-input');
    const searchBtn = document.getElementById('search-btn');

    if (searchInput && searchBtn) {
        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `/events?search=${encodeURIComponent(query)}`;
            }
        });

        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchBtn.click();
            }
        });
    }
}

/**
 * Format price
 */
function formatPrice(price, currency = 'RON') {
    return `${parseFloat(price).toFixed(2)} ${currency}`;
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
