import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

interface Event {
    id: number;
    title: string;
    slug: string;
    description: string;
    venue: string;
    event_date: string;
    event_time: string;
    image_url: string;
    min_price: number;
    max_price: number;
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

export class EventsModule {
    name = 'events';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private events: Event[] = [];

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;

        // Listen for route changes
        this.eventBus.on('route:events', () => this.loadEvents());
        this.eventBus.on('route:event-detail', (slug: string) => this.loadEventDetail(slug));

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
        const formattedDate = new Date(event.event_date).toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        });

        return `
            <div class="tixello-card event-card" data-event-slug="${event.slug}">
                ${event.image_url ? `<img src="${event.image_url}" alt="${event.title}" style="width: 100%; height: 200px; object-fit: cover; border-radius: 0.5rem 0.5rem 0 0;">` : ''}
                <div style="padding: 1rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">${event.title}</h3>
                    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                        <span>${formattedDate}</span> • <span>${event.venue}</span>
                    </p>
                    <p style="font-weight: 600; color: #059669; margin-bottom: 1rem;">
                        From ${event.currency} ${event.min_price}
                    </p>
                    <a href="#/events/${event.slug}" class="tixello-btn" style="display: inline-block; text-decoration: none;">
                        View Details
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
        const formattedDate = new Date(event.event_date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        return `
            <div class="event-detail">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        ${event.image_url ? `<img src="${event.image_url}" alt="${event.title}" style="width: 100%; border-radius: 0.5rem;">` : ''}
                        <h1 style="font-size: 2rem; font-weight: 700; margin: 1rem 0;">${event.title}</h1>
                        <p style="color: #6b7280; margin-bottom: 1rem;">
                            ${formattedDate} at ${event.event_time}<br>
                            ${event.venue}
                        </p>
                        <div style="margin-top: 1rem;">
                            ${event.description}
                        </div>
                    </div>
                    <div class="tixello-card">
                        <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem;">Select Tickets</h2>
                        <div id="ticket-types">
                            ${event.ticket_types.map(tt => this.renderTicketType(tt)).join('')}
                        </div>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span style="font-weight: 600;">Total:</span>
                                <span id="total-price" style="font-size: 1.5rem; font-weight: 700;">0 ${event.ticket_types[0]?.price ? 'RON' : ''}</span>
                            </div>
                            <button id="add-to-cart-btn" class="tixello-btn" style="width: 100%;" disabled>
                                Add to Cart
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
}
