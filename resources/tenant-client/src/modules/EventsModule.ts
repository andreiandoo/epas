import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

interface Event {
    id: number;
    title: string;
    slug: string;
    description: string;
    venue: string | { id: number; name: string; city: string };
    event_date: string;
    start_date: string;
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
}

interface TicketType {
    id: number;
    name: string;
    price: number;
    available: number;
    description: string;
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

        // Use price_from or min_price
        const price = event.price_from ?? event.min_price ?? 0;

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

        return `
            <div class="event-detail">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
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
                    </div>
                    <div class="tixello-card">
                        <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem;">Selectează bilete</h2>
                        <div id="ticket-types">
                            ${(event.ticket_types || []).map(tt => this.renderTicketType(tt)).join('')}
                        </div>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span style="font-weight: 600;">Total:</span>
                                <span id="total-price" style="font-size: 1.5rem; font-weight: 700;">0 RON</span>
                            </div>
                            <button id="add-to-cart-btn" class="tixello-btn" style="width: 100%;" disabled>
                                Adaugă în coș
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    private renderTicketType(ticketType: TicketType): string {
        return `
            <div class="ticket-type" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #e5e7eb;">
                <div>
                    <h4 style="font-weight: 600;">${ticketType.name}</h4>
                    <p style="font-size: 0.875rem; color: #6b7280;">${ticketType.description || ''}</p>
                    <p style="font-weight: 600; color: #059669;">RON ${ticketType.price}</p>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button class="qty-btn qty-minus" data-ticket-id="${ticketType.id}" style="width: 32px; height: 32px; border: 1px solid #d1d5db; border-radius: 0.25rem; cursor: pointer;">-</button>
                    <input type="number" class="qty-input" data-ticket-id="${ticketType.id}" data-price="${ticketType.price}" value="0" min="0" max="${ticketType.available}" style="width: 50px; text-align: center; border: 1px solid #d1d5db; border-radius: 0.25rem; padding: 0.25rem;">
                    <button class="qty-btn qty-plus" data-ticket-id="${ticketType.id}" style="width: 32px; height: 32px; border: 1px solid #d1d5db; border-radius: 0.25rem; cursor: pointer;">+</button>
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
        const updateTotal = () => {
            let total = 0;
            let hasTickets = false;

            document.querySelectorAll('.qty-input').forEach((input: Element) => {
                const htmlInput = input as HTMLInputElement;
                const qty = parseInt(htmlInput.value) || 0;
                const price = parseFloat(htmlInput.getAttribute('data-price') || '0');
                total += qty * price;
                if (qty > 0) hasTickets = true;
            });

            const totalEl = document.getElementById('total-price');
            if (totalEl) {
                totalEl.textContent = `${total.toFixed(2)} RON`;
            }

            const addBtn = document.getElementById('add-to-cart-btn') as HTMLButtonElement;
            if (addBtn) {
                addBtn.disabled = !hasTickets;
            }
        };

        document.querySelectorAll('.qty-minus').forEach(btn => {
            btn.addEventListener('click', () => {
                const ticketId = btn.getAttribute('data-ticket-id');
                const input = document.querySelector(`.qty-input[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
                if (input && parseInt(input.value) > 0) {
                    input.value = String(parseInt(input.value) - 1);
                    updateTotal();
                }
            });
        });

        document.querySelectorAll('.qty-plus').forEach(btn => {
            btn.addEventListener('click', () => {
                const ticketId = btn.getAttribute('data-ticket-id');
                const input = document.querySelector(`.qty-input[data-ticket-id="${ticketId}"]`) as HTMLInputElement;
                if (input) {
                    const max = parseInt(input.getAttribute('max') || '10');
                    if (parseInt(input.value) < max) {
                        input.value = String(parseInt(input.value) + 1);
                        updateTotal();
                    }
                }
            });
        });

        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('change', updateTotal);
        });

        const addToCartBtn = document.getElementById('add-to-cart-btn');
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', () => {
                const items: any[] = [];
                document.querySelectorAll('.qty-input').forEach((input: Element) => {
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
                    window.location.hash = '/cart';
                }
            });
        }
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
