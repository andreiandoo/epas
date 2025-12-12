import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

interface Event {
    id: number;
    title: string;
    slug: string;
    description: string;
    short_description?: string;
    venue: string | Venue;
    event_date: string;
    start_date: string;
    end_date?: string;
    event_time: string;
    start_time: string;
    poster_url: string;
    hero_image_url: string;
    // Legacy field for backwards compatibility
    image_url?: string;
    min_price: number;
    max_price: number;
    price_from: number;
    currency: string;
    status: string;
    ticket_types: TicketType[];
    artists?: Artist[];
    gallery?: string[];
}

interface TicketType {
    id: number;
    name: string;
    price: number;
    sale_price?: number;
    display_price?: number; // Computed: sale_price ?? price
    discount_percent?: number;
    available: number;
    available_quantity?: number;
    description: string;
    status?: string;
}

interface Artist {
    id: number;
    name: string;
    image?: string;
}

interface Venue {
    id: number;
    name: string;
    address?: string;
    city?: string;
    latitude?: number;
    longitude?: number;
}

interface PastEventsFilters {
    year?: number;
    month?: number;
}

export class EventsModule {
    name = 'events';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private events: Event[] = [];
    private pastEvents: Event[] = [];
    private pastEventsFilters: PastEventsFilters = {};

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;

        // Listen for route changes
        this.eventBus.on('route:events', () => this.loadEvents());
        this.eventBus.on('route:event-detail', (slug: string) => this.loadEventDetail(slug));
        this.eventBus.on('route:past-events', () => this.loadPastEvents());

        console.log('Events module initialized');
    }

    async loadEvents(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('events-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/events');
            this.events = response.data.events || [];

            if (this.events.length === 0) {
                container.innerHTML = '<p class="text-gray-500">No upcoming events.</p>';
                return;
            }

            container.innerHTML = this.renderEventsList(this.events);
            this.bindEventListeners();
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load events. Please try again.</p>';
            console.error('Failed to load events:', error);
        }
    }

    private renderEventsList(events: Event[]): string {
        return `
            <div class="events-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                ${events.map(event => this.renderEventCard(event)).join('')}
            </div>
        `;
    }

    private renderEventCard(event: Event): string {
        const eventDate = event.start_date || event.event_date;
        const formattedDate = eventDate ? new Date(eventDate).toLocaleDateString('ro-RO', {
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        }) : '';

        // Use poster_url for card images (vertical), fallback to hero_image_url or image_url
        const cardImage = event.poster_url || event.hero_image_url || event.image_url;

        // Get venue name (can be string or object)
        const venueName = typeof event.venue === 'string'
            ? event.venue
            : (event.venue?.name || '');

        // Calculate minimum price from ticket_types using sale_price (discounted) if available
        let price = 0;
        if (event.ticket_types && event.ticket_types.length > 0) {
            price = event.ticket_types.reduce((min, tt) => {
                const ticketPrice = tt.sale_price ?? tt.price ?? 0;
                return ticketPrice > 0 && (min === 0 || ticketPrice < min) ? ticketPrice : min;
            }, 0);
        }
        // Fallback to event-level price if no ticket_types
        if (price === 0) {
            price = event.price_from ?? event.min_price ?? 0;
        }

        return `
            <div class="tixello-card event-card" data-event-slug="${event.slug}">
                ${cardImage ? `<img src="${cardImage}" alt="${event.title}" style="width: 100%; height: 200px; object-fit: cover; border-radius: 0.5rem 0.5rem 0 0;">` : ''}
                <div style="padding: 1rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">${event.title}</h3>
                    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                        <span>${formattedDate}</span>${venueName ? ` • <span>${venueName}</span>` : ''}
                    </p>
                    <p style="font-weight: 600; color: #059669; margin-bottom: 1rem;">
                        De la ${price} ${event.currency || 'RON'}
                    </p>
                    <a href="#/events/${event.slug}" class="tixello-btn" style="display: inline-block; text-decoration: none;">
                        Detalii
                    </a>
                </div>
            </div>
        `;
    }

    async loadEventDetail(slug: string): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById(`event-${slug}`);
        if (!container) return;

        try {
            const response = await this.apiClient.get(`/events/${slug}`);
            const event = response.data.event;

            container.innerHTML = this.renderEventDetail(event);
            this.bindTicketSelectors(event);
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load event. Please try again.</p>';
            console.error('Failed to load event:', error);
        }
    }

    private renderEventDetail(event: Event): string {
        const eventDate = event.start_date || event.event_date;
        const formattedDate = eventDate ? new Date(eventDate).toLocaleDateString('ro-RO', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : '';

        // Use hero_image_url for single event pages (horizontal), fallback to poster_url or image_url
        const heroImage = event.hero_image_url || event.poster_url || event.image_url;

        // Get venue name (can be string or object)
        const venueName = typeof event.venue === 'string'
            ? event.venue
            : (event.venue?.name || '');

        const eventTime = event.start_time || event.event_time || '';

        // Calculate minimum price for mobile button (use sale_price if available)
        const minPrice = (event.ticket_types || []).reduce((min, tt) => {
            const price = tt.sale_price ?? tt.price ?? 0;
            return price > 0 && (min === 0 || price < min) ? price : min;
        }, 0);
        const currency = event.currency || 'RON';

        // Get venue details for collapsible section
        const venueObj = typeof event.venue === 'object' ? event.venue : null;
        const hasVenueDetails = venueObj && (venueObj.address || venueObj.city);
        const hasArtists = event.artists && event.artists.length > 0;

        return `
            <style>
                .event-detail-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 2rem;
                }
                @media (min-width: 768px) {
                    .event-detail-grid {
                        grid-template-columns: 1fr 1fr;
                    }
                }
                .tickets-sidebar {
                    display: none;
                }
                @media (min-width: 768px) {
                    .tickets-sidebar {
                        display: block;
                    }
                }
                .mobile-tickets-btn {
                    display: flex;
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    z-index: 40;
                    background: linear-gradient(135deg, var(--tixello-primary, #6366f1) 0%, var(--tixello-primary-dark, #4f46e5) 100%);
                    color: white;
                    padding: 1rem 1.5rem;
                    justify-content: space-between;
                    align-items: center;
                    cursor: pointer;
                    box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
                }
                @media (min-width: 768px) {
                    .mobile-tickets-btn {
                        display: none;
                    }
                }
                .mobile-tickets-panel {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    z-index: 50;
                    background: white;
                    border-radius: 1.5rem 1.5rem 0 0;
                    box-shadow: 0 -8px 30px rgba(0,0,0,0.2);
                    transform: translateY(100%);
                    transition: transform 0.3s ease-out;
                    max-height: 85vh;
                    overflow-y: auto;
                }
                .mobile-tickets-panel.open {
                    transform: translateY(0);
                }
                .mobile-tickets-overlay {
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.5);
                    z-index: 45;
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity 0.3s ease;
                }
                .mobile-tickets-overlay.open {
                    opacity: 1;
                    pointer-events: auto;
                }
                @media (min-width: 768px) {
                    .mobile-tickets-panel, .mobile-tickets-overlay {
                        display: none !important;
                    }
                }
                /* Add bottom padding on mobile for the sticky button */
                @media (max-width: 767px) {
                    .event-detail {
                        padding-bottom: 80px;
                    }
                }
                /* Collapsible sections for mobile */
                .collapsible-section {
                    margin-top: 1.5rem;
                    border: 1px solid #e5e7eb;
                    border-radius: 0.75rem;
                    overflow: hidden;
                }
                .collapsible-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem;
                    background: #f9fafb;
                    cursor: pointer;
                    user-select: none;
                }
                .collapsible-header h3 {
                    margin: 0;
                    font-size: 1rem;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .collapsible-icon {
                    width: 20px;
                    height: 20px;
                    transition: transform 0.2s ease;
                }
                .collapsible-section.open .collapsible-icon {
                    transform: rotate(180deg);
                }
                .collapsible-content {
                    max-height: 0;
                    overflow: hidden;
                    transition: max-height 0.3s ease;
                }
                .collapsible-section.open .collapsible-content {
                    max-height: 500px;
                }
                .collapsible-content-inner {
                    padding: 1rem;
                }
                /* On desktop, sections are always expanded */
                @media (min-width: 768px) {
                    .collapsible-section {
                        border: none;
                        margin-top: 2rem;
                    }
                    .collapsible-header {
                        background: transparent;
                        cursor: default;
                        padding: 0 0 0.75rem 0;
                        border-bottom: 2px solid #e5e7eb;
                    }
                    .collapsible-icon {
                        display: none;
                    }
                    .collapsible-content {
                        max-height: none !important;
                    }
                    .collapsible-content-inner {
                        padding: 1rem 0;
                    }
                }
                .artist-card {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 0.75rem 0;
                    border-bottom: 1px solid #f3f4f6;
                }
                .artist-card:last-child {
                    border-bottom: none;
                }
                .artist-image {
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    object-fit: cover;
                    background: #e5e7eb;
                }
                .artist-name {
                    font-weight: 600;
                    font-size: 0.95rem;
                }
            </style>

            <div class="event-detail">
                <div class="event-detail-grid">
                    <div>
                        ${heroImage ? `<img src="${heroImage}" alt="${event.title}" style="width: 100%; border-radius: 0.5rem;">` : ''}
                        <h1 style="font-size: 2rem; font-weight: 700; margin: 1rem 0;">${event.title}</h1>
                        <p style="color: #6b7280; margin-bottom: 1rem;">
                            ${formattedDate}${eventTime ? ` la ${eventTime}` : ''}<br>
                            ${venueName}
                        </p>
                        <div style="margin-top: 1rem;">
                            ${event.description || ''}
                        </div>

                        ${hasArtists ? `
                        <!-- Artists Section - Collapsible on mobile -->
                        <div class="collapsible-section" id="artists-section">
                            <div class="collapsible-header" onclick="this.parentElement.classList.toggle('open')">
                                <h3>
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Artiști
                                </h3>
                                <svg class="collapsible-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                            <div class="collapsible-content">
                                <div class="collapsible-content-inner">
                                    ${event.artists!.map(artist => `
                                        <div class="artist-card">
                                            ${artist.image
                                                ? `<img src="${artist.image}" alt="${artist.name}" class="artist-image">`
                                                : `<div class="artist-image" style="display: flex; align-items: center; justify-content: center;">
                                                    <svg style="width: 24px; height: 24px; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                    </svg>
                                                   </div>`
                                            }
                                            <span class="artist-name">${artist.name}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        ${hasVenueDetails ? `
                        <!-- Location Section - Collapsible on mobile -->
                        <div class="collapsible-section" id="location-section">
                            <div class="collapsible-header" onclick="this.parentElement.classList.toggle('open')">
                                <h3>
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Locație
                                </h3>
                                <svg class="collapsible-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                            <div class="collapsible-content">
                                <div class="collapsible-content-inner">
                                    <p style="font-weight: 600; margin: 0 0 0.5rem 0;">${venueObj!.name}</p>
                                    ${venueObj!.address ? `<p style="color: #6b7280; margin: 0 0 0.25rem 0;">${venueObj!.address}</p>` : ''}
                                    ${venueObj!.city ? `<p style="color: #6b7280; margin: 0;">${venueObj!.city}</p>` : ''}
                                    ${venueObj!.latitude && venueObj!.longitude ? `
                                        <a href="https://www.google.com/maps?q=${venueObj!.latitude},${venueObj!.longitude}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           style="display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1rem; color: var(--tixello-primary, #6366f1); text-decoration: none; font-weight: 500;">
                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                            Deschide în Google Maps
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    <!-- Desktop: Tickets sidebar (no title) -->
                    <div class="tixello-card tickets-sidebar">
                        <div id="ticket-types">
                            ${(event.ticket_types || []).map(tt => this.renderTicketType(tt)).join('')}
                        </div>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span style="font-weight: 600;">Total:</span>
                                <span id="total-price" style="font-size: 1.5rem; font-weight: 700;">0 ${currency}</span>
                            </div>
                            <button id="add-to-cart-btn" class="tixello-btn" style="width: 100%;" disabled>
                                Adaugă în coș
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile: Sticky bottom button -->
            <div class="mobile-tickets-btn" id="mobile-tickets-btn">
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem;">Bilete</div>
                    <div style="font-size: 0.875rem; opacity: 0.9;">Începând de la ${minPrice} ${currency}</div>
                </div>
                <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                </svg>
            </div>

            <!-- Mobile: Tickets panel overlay -->
            <div class="mobile-tickets-overlay" id="mobile-tickets-overlay"></div>

            <!-- Mobile: Tickets panel -->
            <div class="mobile-tickets-panel" id="mobile-tickets-panel">
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 10; border-radius: 1.5rem 1.5rem 0 0;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Selectează bilete</h2>
                    <button id="mobile-tickets-close" style="padding: 0.5rem; border-radius: 0.5rem; border: none; background: #f3f4f6; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
                <div style="padding: 1rem 1.5rem;">
                    <div id="ticket-types-mobile">
                        ${(event.ticket_types || []).map(tt => this.renderTicketType(tt, true)).join('')}
                    </div>
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span style="font-weight: 600;">Total:</span>
                            <span id="total-price-mobile" style="font-size: 1.5rem; font-weight: 700;">0 ${currency}</span>
                        </div>
                        <button id="add-to-cart-btn-mobile" class="tixello-btn" style="width: 100%;" disabled>
                            Adaugă în coș
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    private renderTicketType(ticketType: TicketType, isMobile: boolean = false): string {
        const mobileClass = isMobile ? ' qty-mobile' : '';
        const price = ticketType.sale_price ?? ticketType.price ?? 0;
        return `
            <div class="ticket-type" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div style="flex: 1; min-width: 0;">
                    <h4 style="font-weight: 600; font-size: 0.95rem; margin: 0 0 0.25rem 0;">${ticketType.name}</h4>
                    ${ticketType.description ? `<p style="font-size: 0.8rem; color: #6b7280; margin: 0 0 0.25rem 0;">${ticketType.description}</p>` : ''}
                    <p style="font-weight: 600; color: #059669; margin: 0; font-size: 0.95rem;">${price} RON</p>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                    <button class="qty-btn qty-minus${mobileClass}" data-ticket-id="${ticketType.id}" style="width: 32px; height: 32px; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer; background: white; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;">−</button>
                    <input type="number" class="qty-input${mobileClass}" data-ticket-id="${ticketType.id}" data-price="${price}" value="0" min="0" max="${ticketType.available_quantity ?? 10}" style="width: 44px; text-align: center; border: 1px solid #d1d5db; border-radius: 0.375rem; padding: 0.375rem; font-size: 0.95rem;">
                    <button class="qty-btn qty-plus${mobileClass}" data-ticket-id="${ticketType.id}" style="width: 32px; height: 32px; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer; background: white; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;">+</button>
                </div>
            </div>
        `;
    }

    private bindEventListeners(): void {
        document.querySelectorAll('.event-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const target = e.target as HTMLElement;
                if (target.tagName !== 'A') {
                    const slug = card.getAttribute('data-event-slug');
                    if (slug) {
                        window.location.hash = `/events/${slug}`;
                    }
                }
            });
        });
    }

    private bindTicketSelectors(event: Event): void {
        const currency = event.currency || 'RON';

        // Bind mobile tickets panel open/close
        const mobileBtn = document.getElementById('mobile-tickets-btn');
        const mobileClose = document.getElementById('mobile-tickets-close');
        const mobileOverlay = document.getElementById('mobile-tickets-overlay');
        const mobilePanel = document.getElementById('mobile-tickets-panel');

        const openMobilePanel = () => {
            if (mobilePanel && mobileOverlay) {
                mobileOverlay.classList.add('open');
                mobilePanel.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        };

        const closeMobilePanel = () => {
            if (mobilePanel && mobileOverlay) {
                mobileOverlay.classList.remove('open');
                mobilePanel.classList.remove('open');
                document.body.style.overflow = '';
            }
        };

        if (mobileBtn) mobileBtn.addEventListener('click', openMobilePanel);
        if (mobileClose) mobileClose.addEventListener('click', closeMobilePanel);
        if (mobileOverlay) mobileOverlay.addEventListener('click', closeMobilePanel);

        // Update totals for both desktop and mobile
        const updateTotals = () => {
            let total = 0;
            let hasTickets = false;

            // Calculate from desktop inputs (they are the source of truth)
            document.querySelectorAll('.qty-input:not(.qty-mobile)').forEach((input: Element) => {
                const htmlInput = input as HTMLInputElement;
                const qty = parseInt(htmlInput.value) || 0;
                const price = parseFloat(htmlInput.getAttribute('data-price') || '0');
                total += qty * price;
                if (qty > 0) hasTickets = true;
            });

            // Update desktop total
            const totalEl = document.getElementById('total-price');
            if (totalEl) {
                totalEl.textContent = `${total.toFixed(2)} ${currency}`;
            }

            // Update mobile total
            const totalElMobile = document.getElementById('total-price-mobile');
            if (totalElMobile) {
                totalElMobile.textContent = `${total.toFixed(2)} ${currency}`;
            }

            // Enable/disable both buttons
            const addBtn = document.getElementById('add-to-cart-btn') as HTMLButtonElement;
            if (addBtn) addBtn.disabled = !hasTickets;

            const addBtnMobile = document.getElementById('add-to-cart-btn-mobile') as HTMLButtonElement;
            if (addBtnMobile) addBtnMobile.disabled = !hasTickets;
        };

        // Sync mobile input to desktop
        const syncToDesktop = (ticketId: string, value: string) => {
            const desktopInput = document.querySelector(`.qty-input:not(.qty-mobile)[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
            if (desktopInput) {
                desktopInput.value = value;
            }
        };

        // Sync desktop input to mobile
        const syncToMobile = (ticketId: string, value: string) => {
            const mobileInput = document.querySelector(`.qty-input.qty-mobile[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
            if (mobileInput) {
                mobileInput.value = value;
            }
        };

        // Desktop quantity buttons (no .qty-mobile class)
        document.querySelectorAll('.qty-minus:not(.qty-mobile)').forEach(btn => {
            btn.addEventListener('click', () => {
                const ticketId = btn.getAttribute('data-ticket-id');
                const input = document.querySelector(`.qty-input:not(.qty-mobile)[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
                if (input && parseInt(input.value) > 0) {
                    input.value = String(parseInt(input.value) - 1);
                    if (ticketId) syncToMobile(ticketId, input.value);
                    updateTotals();
                }
            });
        });

        document.querySelectorAll('.qty-plus:not(.qty-mobile)').forEach(btn => {
            btn.addEventListener('click', () => {
                const ticketId = btn.getAttribute('data-ticket-id');
                const input = document.querySelector(`.qty-input:not(.qty-mobile)[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
                if (input) {
                    const max = parseInt(input.getAttribute('max') || '10');
                    if (parseInt(input.value) < max) {
                        input.value = String(parseInt(input.value) + 1);
                        if (ticketId) syncToMobile(ticketId, input.value);
                        updateTotals();
                    }
                }
            });
        });

        // Mobile quantity buttons (with .qty-mobile class)
        document.querySelectorAll('.qty-minus.qty-mobile').forEach(btn => {
            btn.addEventListener('click', () => {
                const ticketId = btn.getAttribute('data-ticket-id');
                const input = document.querySelector(`.qty-input.qty-mobile[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
                if (input && parseInt(input.value) > 0) {
                    input.value = String(parseInt(input.value) - 1);
                    if (ticketId) syncToDesktop(ticketId, input.value);
                    updateTotals();
                }
            });
        });

        document.querySelectorAll('.qty-plus.qty-mobile').forEach(btn => {
            btn.addEventListener('click', () => {
                const ticketId = btn.getAttribute('data-ticket-id');
                const input = document.querySelector(`.qty-input.qty-mobile[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
                if (input) {
                    const max = parseInt(input.getAttribute('max') || '10');
                    if (parseInt(input.value) < max) {
                        input.value = String(parseInt(input.value) + 1);
                        if (ticketId) syncToDesktop(ticketId, input.value);
                        updateTotals();
                    }
                }
            });
        });

        // Input change handlers
        document.querySelectorAll('.qty-input:not(.qty-mobile)').forEach(input => {
            input.addEventListener('change', () => {
                const ticketId = (input as HTMLInputElement).getAttribute('data-ticket-id');
                if (ticketId) syncToMobile(ticketId, (input as HTMLInputElement).value);
                updateTotals();
            });
        });

        document.querySelectorAll('.qty-input.qty-mobile').forEach(input => {
            input.addEventListener('change', () => {
                const ticketId = (input as HTMLInputElement).getAttribute('data-ticket-id');
                if (ticketId) syncToDesktop(ticketId, (input as HTMLInputElement).value);
                updateTotals();
            });
        });

        // Add to cart handlers (both desktop and mobile)
        const handleAddToCart = () => {
            const items: any[] = [];
            document.querySelectorAll('.qty-input:not(.qty-mobile)').forEach((input: Element) => {
                const htmlInput = input as HTMLInputElement;
                const qty = parseInt(htmlInput.value) || 0;
                if (qty > 0) {
                    items.push({
                        ticket_type_id: htmlInput.getAttribute('data-ticket-id'),
                        quantity: qty,
                        price: parseFloat(htmlInput.getAttribute('data-price') || '0')
                    });
                }
            });

            if (items.length > 0 && this.eventBus) {
                this.eventBus.emit('cart:add', { event, items });
                closeMobilePanel();
                window.location.hash = '/cart';
            }
        };

        const addToCartBtn = document.getElementById('add-to-cart-btn');
        if (addToCartBtn) addToCartBtn.addEventListener('click', handleAddToCart);

        const addToCartBtnMobile = document.getElementById('add-to-cart-btn-mobile');
        if (addToCartBtnMobile) addToCartBtnMobile.addEventListener('click', handleAddToCart);
    }

    // ==========================================
    // PAST EVENTS SECTION
    // ==========================================

    async loadPastEvents(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('past-events-container');
        if (!container) return;

        try {
            // Build query params from filters
            const params: Record<string, string> = {};
            if (this.pastEventsFilters.year) {
                params.year = String(this.pastEventsFilters.year);
            }
            if (this.pastEventsFilters.month) {
                params.month = String(this.pastEventsFilters.month);
            }

            const queryString = new URLSearchParams(params).toString();
            const url = `/events/past${queryString ? `?${queryString}` : ''}`;

            const response = await this.apiClient.get(url);
            this.pastEvents = response.data.events || [];

            container.innerHTML = this.renderPastEventsPage();
            this.bindPastEventsFilters();
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Nu s-au putut încărca evenimentele trecute.</p>';
            console.error('Failed to load past events:', error);
        }
    }

    private renderPastEventsPage(): string {
        const currentYear = new Date().getFullYear();
        const years = [];
        for (let y = currentYear; y >= currentYear - 10; y--) {
            years.push(y);
        }

        const months = [
            { value: 1, name: 'Ianuarie' },
            { value: 2, name: 'Februarie' },
            { value: 3, name: 'Martie' },
            { value: 4, name: 'Aprilie' },
            { value: 5, name: 'Mai' },
            { value: 6, name: 'Iunie' },
            { value: 7, name: 'Iulie' },
            { value: 8, name: 'August' },
            { value: 9, name: 'Septembrie' },
            { value: 10, name: 'Octombrie' },
            { value: 11, name: 'Noiembrie' },
            { value: 12, name: 'Decembrie' },
        ];

        // Group events by month
        const eventsByMonth = this.groupEventsByMonth(this.pastEvents);

        return `
            <div class="past-events-page">
                <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 1.5rem;">Evenimente trecute</h1>

                <!-- Filters -->
                <div class="past-events-filters" style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;">
                    <select id="filter-year" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; background: white; min-width: 120px;">
                        <option value="">Toți anii</option>
                        ${years.map(y => `<option value="${y}" ${this.pastEventsFilters.year === y ? 'selected' : ''}>${y}</option>`).join('')}
                    </select>
                    <select id="filter-month" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; background: white; min-width: 150px;">
                        <option value="">Toate lunile</option>
                        ${months.map(m => `<option value="${m.value}" ${this.pastEventsFilters.month === m.value ? 'selected' : ''}>${m.name}</option>`).join('')}
                    </select>
                    <button id="clear-filters" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; background: #f3f4f6; cursor: pointer;">
                        Resetează filtrele
                    </button>
                </div>

                <!-- Events grouped by month -->
                <div class="past-events-list">
                    ${this.pastEvents.length === 0
                        ? '<p style="color: #6b7280;">Nu există evenimente pentru perioada selectată.</p>'
                        : this.renderEventsByMonth(eventsByMonth)
                    }
                </div>
            </div>
        `;
    }

    private groupEventsByMonth(events: Event[]): Map<string, Event[]> {
        const grouped = new Map<string, Event[]>();

        events.forEach(event => {
            const date = new Date(event.event_date);
            const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

            if (!grouped.has(monthKey)) {
                grouped.set(monthKey, []);
            }
            grouped.get(monthKey)!.push(event);
        });

        // Sort by month descending (most recent first)
        return new Map([...grouped.entries()].sort((a, b) => b[0].localeCompare(a[0])));
    }

    private renderEventsByMonth(eventsByMonth: Map<string, Event[]>): string {
        const monthNames = [
            'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie',
            'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'
        ];

        let html = '';

        eventsByMonth.forEach((events, monthKey) => {
            const [year, month] = monthKey.split('-');
            const monthName = monthNames[parseInt(month) - 1];

            html += `
                <div class="month-section" style="margin-bottom: 2.5rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; color: #374151; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e5e7eb;">
                        ${monthName} ${year}
                    </h2>
                    <div class="events-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        ${events.map(event => this.renderPastEventCard(event)).join('')}
                    </div>
                </div>
            `;
        });

        return html;
    }

    private renderPastEventCard(event: Event): string {
        const date = new Date(event.event_date);
        const formattedDate = date.toLocaleDateString('ro-RO', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });

        // Use poster_url for card images (vertical), fallback to hero_image_url or image_url
        const cardImage = event.poster_url || event.hero_image_url || event.image_url;

        return `
            <div class="tixello-card past-event-card" style="opacity: 0.9;">
                ${cardImage
                    ? `<div style="position: relative;">
                        <img src="${cardImage}" alt="${event.title}" style="width: 100%; height: 160px; object-fit: cover; border-radius: 0.5rem 0.5rem 0 0; filter: grayscale(30%);">
                        <span style="position: absolute; top: 0.5rem; right: 0.5rem; background: #374151; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem;">Încheiat</span>
                       </div>`
                    : ''
                }
                <div style="padding: 1rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; color: #1f2937;">${event.title}</h3>
                    <p style="color: #6b7280; font-size: 0.875rem;">
                        ${formattedDate}
                    </p>
                    ${event.venue ? `<p style="color: #9ca3af; font-size: 0.875rem;">${event.venue}</p>` : ''}
                </div>
            </div>
        `;
    }

    private bindPastEventsFilters(): void {
        const yearSelect = document.getElementById('filter-year') as HTMLSelectElement;
        const monthSelect = document.getElementById('filter-month') as HTMLSelectElement;
        const clearBtn = document.getElementById('clear-filters');

        if (yearSelect) {
            yearSelect.addEventListener('change', () => {
                this.pastEventsFilters.year = yearSelect.value ? parseInt(yearSelect.value) : undefined;
                this.loadPastEvents();
            });
        }

        if (monthSelect) {
            monthSelect.addEventListener('change', () => {
                this.pastEventsFilters.month = monthSelect.value ? parseInt(monthSelect.value) : undefined;
                this.loadPastEvents();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.pastEventsFilters = {};
                this.loadPastEvents();
            });
        }
    }
}
