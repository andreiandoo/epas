import { TixelloConfig } from './ConfigManager';

type RouteHandler = (params: Record<string, string>) => void | Promise<void>;

interface Route {
    pattern: RegExp;
    handler: RouteHandler;
    paramNames: string[];
}

export class Router {
    private routes: Route[] = [];
    private config: TixelloConfig;
    private currentPath: string = '';
    private authToken: string | null = null;
    private currentUser: any = null;

    constructor(config: TixelloConfig) {
        this.config = config;
        this.loadAuthState();
        this.setupDefaultRoutes();
    }

    // Auth state management
    private loadAuthState(): void {
        this.authToken = localStorage.getItem('tixello_auth_token');
        const userJson = localStorage.getItem('tixello_user');
        if (userJson) {
            try {
                this.currentUser = JSON.parse(userJson);
            } catch {
                this.currentUser = null;
            }
        }
    }

    private saveAuthState(token: string, user: any): void {
        this.authToken = token;
        this.currentUser = user;
        localStorage.setItem('tixello_auth_token', token);
        localStorage.setItem('tixello_user', JSON.stringify(user));
    }

    private clearAuthState(): void {
        this.authToken = null;
        this.currentUser = null;
        localStorage.removeItem('tixello_auth_token');
        localStorage.removeItem('tixello_user');
    }

    public isAuthenticated(): boolean {
        return !!this.authToken;
    }

    public getUser(): any {
        return this.currentUser;
    }

    // API helper method
    private async fetchApi(endpoint: string, params: Record<string, string> = {}, options: RequestInit = {}): Promise<any> {
        const url = new URL(`${this.config.apiEndpoint}${endpoint}`);
        url.searchParams.set('hostname', window.location.hostname);

        Object.entries(params).forEach(([key, value]) => {
            url.searchParams.set(key, value);
        });

        const headers: HeadersInit = {
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        };

        if (this.authToken) {
            (headers as Record<string, string>)['Authorization'] = `Bearer ${this.authToken}`;
        }

        const response = await fetch(url.toString(), {
            ...options,
            headers,
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: 'Request failed' }));
            throw new Error(error.message || `API error: ${response.status}`);
        }
        return response.json();
    }

    // POST helper
    private async postApi(endpoint: string, data: any): Promise<any> {
        const url = new URL(`${this.config.apiEndpoint}${endpoint}`);
        url.searchParams.set('hostname', window.location.hostname);

        const headers: HeadersInit = {
            'Content-Type': 'application/json',
        };

        if (this.authToken) {
            (headers as Record<string, string>)['Authorization'] = `Bearer ${this.authToken}`;
        }

        const response = await fetch(url.toString(), {
            method: 'POST',
            headers,
            body: JSON.stringify(data),
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || result.error || 'Request failed');
        }

        return result;
    }

    // Event card HTML generator
    private renderEventCard(event: any): string {
        const date = event.start_date ? new Date(event.start_date).toLocaleDateString('ro-RO', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }) : '';

        return `
            <a href="/event/${event.slug}" class="block bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition group">
                <div class="aspect-[16/9] bg-gray-200 relative overflow-hidden">
                    ${event.image
                        ? `<img src="${event.image}" alt="${event.title}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">`
                        : `<div class="w-full h-full flex items-center justify-center text-gray-400">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                          </div>`
                    }
                    ${event.is_sold_out ? `<span class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">SOLD OUT</span>` : ''}
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1 line-clamp-2">${event.title}</h3>
                    <p class="text-sm text-gray-500 mb-2">${date}</p>
                    ${event.venue ? `<p class="text-sm text-gray-600 mb-2">${event.venue.name}${event.venue.city ? `, ${event.venue.city}` : ''}</p>` : ''}
                    ${event.price_from ? `<p class="text-sm font-semibold text-blue-600">de la ${event.price_from} €</p>` : ''}
                </div>
            </a>
        `;
    }

    init(): void {
        // Listen for popstate (browser back/forward)
        window.addEventListener('popstate', () => this.handleRoute());

        // Intercept all link clicks for SPA navigation
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            const anchor = target.closest('a');
            if (anchor && anchor.href && anchor.href.startsWith(window.location.origin)) {
                const url = new URL(anchor.href);
                if (!anchor.hasAttribute('data-external')) {
                    e.preventDefault();
                    this.navigate(url.pathname);
                }
            }
        });

        // Handle initial route
        this.handleRoute();
    }

    private setupDefaultRoutes(): void {
        // Public routes
        this.addRoute('/', this.renderHome.bind(this));
        this.addRoute('/events', this.renderEvents.bind(this));
        this.addRoute('/event/:slug', this.renderEventDetail.bind(this));
        this.addRoute('/cart', this.renderCart.bind(this));
        this.addRoute('/checkout', this.renderCheckout.bind(this));
        this.addRoute('/thank-you/:orderNumber', this.renderThankYou.bind(this));
        this.addRoute('/login', this.renderLogin.bind(this));
        this.addRoute('/register', this.renderRegister.bind(this));
        this.addRoute('/account', this.renderAccount.bind(this));
        this.addRoute('/account/orders', this.renderOrders.bind(this));
        this.addRoute('/account/orders/:id', this.renderOrderDetail.bind(this));
        this.addRoute('/account/tickets', this.renderTickets.bind(this));
        this.addRoute('/account/events', this.renderMyEvents.bind(this));
        this.addRoute('/account/profile', this.renderProfile.bind(this));
        this.addRoute('/terms', this.renderTerms.bind(this));
        this.addRoute('/privacy', this.renderPrivacy.bind(this));
    }

    addRoute(path: string, handler: RouteHandler): void {
        const paramNames: string[] = [];
        const pattern = path.replace(/:([^/]+)/g, (_, paramName) => {
            paramNames.push(paramName);
            return '([^/]+)';
        });

        this.routes.push({
            pattern: new RegExp(`^${pattern}$`),
            handler,
            paramNames,
        });
    }

    navigate(path: string): void {
        window.history.pushState({}, '', path);
        this.handleRoute();
    }

    private handleRoute(): void {
        const path = window.location.pathname || '/';
        this.currentPath = path;

        for (const route of this.routes) {
            const match = path.match(route.pattern);
            if (match) {
                const params: Record<string, string> = {};
                route.paramNames.forEach((name, index) => {
                    params[name] = match[index + 1];
                });

                route.handler(params);
                return;
            }
        }

        // 404
        this.render404();
    }

    private getContentElement(): HTMLElement | null {
        return document.getElementById('tixello-content');
    }

    // Route handlers
    private async renderHome(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <!-- Hero Section -->
                <div class="text-center mb-16">
                    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                        Descoperă evenimente unice
                    </h1>
                    <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                        Găsește și cumpără bilete pentru cele mai bune concerte, spectacole și experiențe
                    </p>
                    <a href="/events" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition">
                        Vezi toate evenimentele
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>

                <!-- Featured Events -->
                <div class="mb-16">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Evenimente recomandate</h2>
                    <div id="featured-events" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                    </div>
                </div>

                <!-- Categories -->
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Explorează pe categorii</h2>
                    <div id="categories" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    </div>
                </div>
            </div>
        `;

        // Fetch and render featured events
        try {
            const [featuredData, categoriesData] = await Promise.all([
                this.fetchApi('/events/featured'),
                this.fetchApi('/categories')
            ]);

            // Render featured events
            const featuredEl = document.getElementById('featured-events');
            if (featuredEl && featuredData.data) {
                if (featuredData.data.length === 0) {
                    featuredEl.innerHTML = `<p class="col-span-3 text-center text-gray-500">Nu există evenimente recomandate momentan.</p>`;
                } else {
                    featuredEl.innerHTML = featuredData.data.map((event: any) => this.renderEventCard(event)).join('');
                }
            }

            // Render categories
            const categoriesEl = document.getElementById('categories');
            if (categoriesEl && categoriesData.data) {
                if (categoriesData.data.length === 0) {
                    categoriesEl.innerHTML = `<p class="col-span-4 text-center text-gray-500">Nu există categorii disponibile.</p>`;
                } else {
                    categoriesEl.innerHTML = categoriesData.data.map((cat: any) => `
                        <a href="/events?category=${cat.slug}" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition text-center">
                            <div class="text-blue-600 mb-2">
                                <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <span class="font-medium text-gray-900">${cat.name}</span>
                        </a>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Failed to load home data:', error);
        }
    }

    private async renderEvents(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        // Get query params
        const urlParams = new URLSearchParams(window.location.search);
        const currentCategory = urlParams.get('category') || '';
        const currentSearch = urlParams.get('search') || '';

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Evenimente</h1>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <input type="search" id="event-search" placeholder="Caută evenimente..."
                               value="${currentSearch}"
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <select id="event-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Toate categoriile</option>
                        </select>
                    </div>
                </div>
                <div id="events-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-80"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-80"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-80"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-80"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-80"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-80"></div>
                </div>
            </div>
        `;

        // Fetch events and categories
        try {
            const params: Record<string, string> = {};
            if (currentCategory) params.category = currentCategory;
            if (currentSearch) params.search = currentSearch;

            const [eventsData, categoriesData] = await Promise.all([
                this.fetchApi('/events', params),
                this.fetchApi('/categories')
            ]);

            // Populate category filter
            const filterEl = document.getElementById('event-filter') as HTMLSelectElement;
            if (filterEl && categoriesData.data) {
                categoriesData.data.forEach((cat: any) => {
                    const option = document.createElement('option');
                    option.value = cat.slug;
                    option.textContent = cat.name;
                    if (cat.slug === currentCategory) option.selected = true;
                    filterEl.appendChild(option);
                });

                // Category change handler
                filterEl.addEventListener('change', () => {
                    const params = new URLSearchParams();
                    if (filterEl.value) params.set('category', filterEl.value);
                    const searchInput = document.getElementById('event-search') as HTMLInputElement;
                    if (searchInput?.value) params.set('search', searchInput.value);
                    this.navigate('/events' + (params.toString() ? '?' + params.toString() : ''));
                });
            }

            // Search handler
            const searchEl = document.getElementById('event-search') as HTMLInputElement;
            if (searchEl) {
                let timeout: number;
                searchEl.addEventListener('input', () => {
                    clearTimeout(timeout);
                    timeout = window.setTimeout(() => {
                        const params = new URLSearchParams();
                        if (searchEl.value) params.set('search', searchEl.value);
                        if (filterEl?.value) params.set('category', filterEl.value);
                        this.navigate('/events' + (params.toString() ? '?' + params.toString() : ''));
                    }, 500);
                });
            }

            // Render events
            const eventsEl = document.getElementById('events-list');
            if (eventsEl && eventsData.data) {
                if (eventsData.data.length === 0) {
                    eventsEl.innerHTML = `
                        <div class="col-span-3 text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-gray-500">Nu au fost găsite evenimente.</p>
                        </div>
                    `;
                } else {
                    eventsEl.innerHTML = eventsData.data.map((event: any) => this.renderEventCard(event)).join('');
                }
            }
        } catch (error) {
            console.error('Failed to load events:', error);
            const eventsEl = document.getElementById('events-list');
            if (eventsEl) {
                eventsEl.innerHTML = `<p class="col-span-3 text-center text-red-500">Eroare la încărcarea evenimentelor.</p>`;
            }
        }
    }

    private async renderEventDetail(params: Record<string, string>): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/events" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la evenimente
                </a>
                <div id="event-detail" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="animate-pulse bg-gray-200 rounded-lg h-96 mb-6"></div>
                        <div class="animate-pulse bg-gray-200 h-8 w-3/4 mb-4 rounded"></div>
                        <div class="animate-pulse bg-gray-200 h-4 w-full mb-2 rounded"></div>
                        <div class="animate-pulse bg-gray-200 h-4 w-full mb-2 rounded"></div>
                        <div class="animate-pulse bg-gray-200 h-4 w-2/3 rounded"></div>
                    </div>
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow-lg p-6 sticky top-24">
                            <div class="animate-pulse bg-gray-200 h-6 w-1/2 mb-4 rounded"></div>
                            <div class="animate-pulse bg-gray-200 h-10 w-full mb-4 rounded"></div>
                            <div class="animate-pulse bg-gray-200 h-12 w-full rounded"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Fetch event details
        try {
            const eventData = await this.fetchApi(`/events/${params.slug}`);
            const event = eventData.data;

            if (!event) {
                this.render404();
                return;
            }

            const date = event.start_date ? new Date(event.start_date).toLocaleDateString('ro-RO', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }) : '';

            const time = event.start_date ? new Date(event.start_date).toLocaleTimeString('ro-RO', {
                hour: '2-digit',
                minute: '2-digit'
            }) : '';

            const eventDetailEl = document.getElementById('event-detail');
            if (eventDetailEl) {
                eventDetailEl.innerHTML = `
                    <div class="lg:col-span-2">
                        ${event.image
                            ? `<img src="${event.image}" alt="${event.title}" class="w-full h-96 object-cover rounded-lg mb-6">`
                            : `<div class="w-full h-96 bg-gray-200 rounded-lg mb-6 flex items-center justify-center">
                                <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                              </div>`
                        }

                        <h1 class="text-3xl font-bold text-gray-900 mb-4">${event.title}</h1>

                        <div class="flex flex-wrap gap-4 mb-6 text-gray-600">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                ${date}
                            </div>
                            ${time ? `
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                ${time}
                            </div>
                            ` : ''}
                            ${event.venue ? `
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                ${event.venue.name}${event.venue.city ? `, ${event.venue.city}` : ''}
                            </div>
                            ` : ''}
                        </div>

                        ${event.description ? `
                        <div class="prose max-w-none mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Descriere</h2>
                            <div class="text-gray-700">${event.description}</div>
                        </div>
                        ` : ''}

                        ${event.artists && event.artists.length > 0 ? `
                        <div class="mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Artiști</h2>
                            <div class="flex flex-wrap gap-4">
                                ${event.artists.map((artist: any) => `
                                    <div class="flex items-center bg-gray-100 rounded-lg p-3">
                                        ${artist.image
                                            ? `<img src="${artist.image}" alt="${artist.name}" class="w-10 h-10 rounded-full object-cover mr-3">`
                                            : `<div class="w-10 h-10 rounded-full bg-gray-300 mr-3"></div>`
                                        }
                                        <span class="font-medium">${artist.name}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow-lg p-6 sticky top-24">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Bilete</h2>

                            ${event.ticket_types && event.ticket_types.length > 0 ? `
                                <div class="space-y-4 mb-6">
                                    ${event.ticket_types.map((ticket: any) => `
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h3 class="font-semibold text-gray-900">${ticket.name}</h3>
                                                    ${ticket.description ? `<p class="text-sm text-gray-500">${ticket.description}</p>` : ''}
                                                </div>
                                                <span class="font-bold text-blue-600">${ticket.price} ${ticket.currency}</span>
                                            </div>
                                            <div class="flex items-center justify-between mt-3">
                                                <select class="ticket-qty px-3 py-1 border border-gray-300 rounded text-sm" data-ticket-id="${ticket.id}" data-price="${ticket.price}">
                                                    ${Array.from({length: Math.min(ticket.max_per_order || 10, ticket.available || 10) + 1}, (_, i) =>
                                                        `<option value="${i}">${i}</option>`
                                                    ).join('')}
                                                </select>
                                                <span class="text-sm text-gray-500">${ticket.available || 0} disponibile</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>

                                <div class="border-t pt-4 mb-4">
                                    <div class="flex justify-between items-center text-lg font-bold">
                                        <span>Total</span>
                                        <span id="cart-total-price">0 €</span>
                                    </div>
                                </div>

                                <button id="add-to-cart-btn" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition disabled:bg-gray-300 disabled:cursor-not-allowed" disabled>
                                    Adaugă în coș
                                </button>
                            ` : `
                                <p class="text-gray-500 text-center py-4">Nu sunt bilete disponibile.</p>
                            `}
                        </div>
                    </div>
                `;

                // Setup ticket quantity handlers
                this.setupTicketHandlers();
            }
        } catch (error) {
            console.error('Failed to load event:', error);
            this.render404();
        }
    }

    private setupTicketHandlers(): void {
        const qtySelects = document.querySelectorAll('.ticket-qty');
        const totalEl = document.getElementById('cart-total-price');
        const addBtn = document.getElementById('add-to-cart-btn');

        const updateTotal = () => {
            let total = 0;
            let hasSelection = false;

            qtySelects.forEach((select) => {
                const qty = parseInt((select as HTMLSelectElement).value);
                const price = parseFloat((select as HTMLSelectElement).dataset.price || '0');
                if (qty > 0) {
                    total += qty * price;
                    hasSelection = true;
                }
            });

            if (totalEl) totalEl.textContent = `${total.toFixed(2)} €`;
            if (addBtn) (addBtn as HTMLButtonElement).disabled = !hasSelection;
        };

        qtySelects.forEach((select) => {
            select.addEventListener('change', updateTotal);
        });

        if (addBtn) {
            addBtn.addEventListener('click', () => {
                // TODO: Add to cart functionality
                alert('Funcționalitate în dezvoltare. Biletele vor fi adăugate în coș.');
                this.navigate('/cart');
            });
        }
    }

    private renderCart(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>
                <div id="cart-items" class="space-y-4 mb-8">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                </div>
                <div id="cart-summary" class="bg-gray-50 rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-lg font-medium text-gray-900">Total</span>
                        <span class="text-2xl font-bold text-gray-900" id="cart-total">$0.00</span>
                    </div>
                    <a href="/checkout" class="block w-full text-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        `;
    }

    private renderCheckout(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div id="checkout-form" class="space-y-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold mb-4">Contact Information</h2>
                            <div class="space-y-4">
                                <input type="email" placeholder="Email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <input type="tel" placeholder="Phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-semibold mb-4">Payment</h2>
                            <div id="payment-element" class="animate-pulse bg-gray-200 h-32 rounded"></div>
                        </div>
                    </div>
                    <div id="checkout-summary" class="bg-gray-50 rounded-lg p-6 h-fit sticky top-4">
                        <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                        <div class="animate-pulse space-y-2">
                            <div class="bg-gray-200 h-4 rounded"></div>
                            <div class="bg-gray-200 h-4 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    private renderLogin(): void {
        // Redirect if already logged in
        if (this.isAuthenticated()) {
            this.navigate('/account');
            return;
        }

        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="min-h-[60vh] flex items-center justify-center px-4">
                <div class="max-w-md w-full space-y-8">
                    <div class="text-center">
                        <h1 class="text-3xl font-bold text-gray-900">Bine ai revenit</h1>
                        <p class="mt-2 text-gray-600">Conectează-te la contul tău</p>
                    </div>
                    <div id="login-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"></div>
                    <form id="login-form" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Parolă</label>
                            <input type="password" id="password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <button type="submit" id="login-btn" class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                            Conectare
                        </button>
                    </form>
                    <p class="text-center text-gray-600">
                        Nu ai cont? <a href="/register" class="text-blue-600 hover:text-blue-700 font-medium">Înregistrează-te</a>
                    </p>
                </div>
            </div>
        `;

        // Setup form handler
        const form = document.getElementById('login-form');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = (document.getElementById('email') as HTMLInputElement).value;
            const password = (document.getElementById('password') as HTMLInputElement).value;
            const errorEl = document.getElementById('login-error');
            const btn = document.getElementById('login-btn') as HTMLButtonElement;

            btn.disabled = true;
            btn.textContent = 'Se conectează...';
            errorEl?.classList.add('hidden');

            try {
                const result = await this.postApi('/auth/login', { email, password });
                this.saveAuthState(result.token, result.user);
                this.navigate('/account');
            } catch (error: any) {
                if (errorEl) {
                    errorEl.textContent = error.message || 'Eroare la autentificare';
                    errorEl.classList.remove('hidden');
                }
                btn.disabled = false;
                btn.textContent = 'Conectare';
            }
        });
    }

    private renderRegister(): void {
        // Redirect if already logged in
        if (this.isAuthenticated()) {
            this.navigate('/account');
            return;
        }

        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="min-h-[60vh] flex items-center justify-center px-4">
                <div class="max-w-md w-full space-y-8">
                    <div class="text-center">
                        <h1 class="text-3xl font-bold text-gray-900">Creează cont</h1>
                        <p class="mt-2 text-gray-600">Înregistrează-te pentru a cumpăra bilete</p>
                    </div>
                    <div id="register-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"></div>
                    <form id="register-form" class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">Prenume</label>
                                <input type="text" id="first_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Nume</label>
                                <input type="text" id="last_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefon (opțional)</label>
                            <input type="tel" id="phone"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Parolă</label>
                            <input type="password" id="password" required minlength="8"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmă parola</label>
                            <input type="password" id="password_confirmation" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button type="submit" id="register-btn" class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                            Creează cont
                        </button>
                    </form>
                    <p class="text-center text-gray-600">
                        Ai deja cont? <a href="/login" class="text-blue-600 hover:text-blue-700 font-medium">Conectează-te</a>
                    </p>
                </div>
            </div>
        `;

        // Setup form handler
        const form = document.getElementById('register-form');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const first_name = (document.getElementById('first_name') as HTMLInputElement).value;
            const last_name = (document.getElementById('last_name') as HTMLInputElement).value;
            const email = (document.getElementById('email') as HTMLInputElement).value;
            const phone = (document.getElementById('phone') as HTMLInputElement).value;
            const password = (document.getElementById('password') as HTMLInputElement).value;
            const password_confirmation = (document.getElementById('password_confirmation') as HTMLInputElement).value;
            const errorEl = document.getElementById('register-error');
            const btn = document.getElementById('register-btn') as HTMLButtonElement;

            if (password !== password_confirmation) {
                if (errorEl) {
                    errorEl.textContent = 'Parolele nu coincid';
                    errorEl.classList.remove('hidden');
                }
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Se creează contul...';
            errorEl?.classList.add('hidden');

            try {
                const result = await this.postApi('/auth/register', {
                    first_name,
                    last_name,
                    email,
                    phone: phone || null,
                    password,
                    password_confirmation,
                });
                this.saveAuthState(result.token, result.user);
                this.navigate('/account');
            } catch (error: any) {
                if (errorEl) {
                    errorEl.textContent = error.message || 'Eroare la înregistrare';
                    errorEl.classList.remove('hidden');
                }
                btn.disabled = false;
                btn.textContent = 'Creează cont';
            }
        });
    }

    private renderAccount(): void {
        // Redirect if not logged in
        if (!this.isAuthenticated()) {
            this.navigate('/login');
            return;
        }

        const content = this.getContentElement();
        if (!content) return;

        const user = this.getUser();
        const userName = user?.full_name || user?.first_name || 'Utilizator';

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Contul meu</h1>
                        <p class="text-gray-600">Bun venit, ${userName}!</p>
                    </div>
                    <button id="logout-btn" class="px-4 py-2 text-red-600 hover:text-red-700 font-medium">
                        Deconectare
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="/account/orders" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-blue-600 mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Comenzile mele</h3>
                        <p class="text-gray-600 text-sm">Vezi istoricul comenzilor</p>
                    </a>
                    <a href="/account/tickets" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-blue-600 mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Biletele mele</h3>
                        <p class="text-gray-600 text-sm">Accesează biletele tale</p>
                    </a>
                    <a href="/account/profile" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-blue-600 mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Profil</h3>
                        <p class="text-gray-600 text-sm">Editează datele tale</p>
                    </a>
                </div>
            </div>
        `;

        // Setup logout handler
        const logoutBtn = document.getElementById('logout-btn');
        logoutBtn?.addEventListener('click', async () => {
            try {
                await this.postApi('/auth/logout', {});
            } catch {
                // Ignore errors
            }
            this.clearAuthState();
            this.navigate('/');
        });
    }

    private renderOrders(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Account
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">My Orders</h1>
                <div id="orders-list" class="space-y-4">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                </div>
            </div>
        `;
    }

    private renderTickets(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Account
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">My Tickets</h1>
                <div id="tickets-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                </div>
            </div>
        `;
    }

    private renderOrderDetail(params: Record<string, string>): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account/orders" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Orders
                </a>
                <div id="order-detail-${params.id}">
                    <div class="animate-pulse space-y-4">
                        <div class="bg-gray-200 h-8 w-1/3 rounded"></div>
                        <div class="bg-gray-200 h-48 rounded-lg"></div>
                    </div>
                </div>
            </div>
        `;
    }

    private renderMyEvents(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Account
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">My Events</h1>
                <p class="text-gray-600 mb-8">Upcoming events you have tickets for</p>
                <div id="my-events-list" class="space-y-4">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-32"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-32"></div>
                </div>
            </div>
        `;
    }

    private renderProfile(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Account
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">My Profile</h1>
                <div id="profile-form" class="bg-white rounded-lg shadow p-6">
                    <div class="animate-pulse space-y-4">
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 rounded"></div>
                        <div class="bg-gray-200 h-10 w-1/3 rounded"></div>
                    </div>
                </div>
            </div>
        `;
    }

    private renderThankYou(params: Record<string, string>): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Thank You!</h1>
                <p class="text-gray-600 mb-8">Your order has been confirmed. Check your email for tickets.</p>
                <div id="order-confirmation-${params.orderNumber}" class="bg-gray-50 rounded-lg p-6 text-left mb-8">
                    <div class="animate-pulse space-y-3">
                        <div class="bg-gray-200 h-4 w-1/2 rounded"></div>
                        <div class="bg-gray-200 h-4 w-3/4 rounded"></div>
                    </div>
                </div>
                <a href="/account/tickets" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    View My Tickets
                </a>
            </div>
        `;
    }

    private async renderTerms(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="animate-pulse space-y-4">
                    <div class="bg-gray-200 h-8 w-1/3 rounded"></div>
                    <div class="bg-gray-200 h-4 w-full rounded"></div>
                    <div class="bg-gray-200 h-4 w-full rounded"></div>
                    <div class="bg-gray-200 h-4 w-2/3 rounded"></div>
                </div>
            </div>
        `;

        try {
            const data = await this.fetchApi('/pages/terms');
            content.innerHTML = `
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">${data.data.title}</h1>
                    <div class="prose max-w-none">
                        ${data.data.content}
                    </div>
                </div>
            `;
        } catch (error) {
            content.innerHTML = `
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">Terms & Conditions</h1>
                    <p class="text-gray-600">Terms page not available.</p>
                </div>
            `;
        }
    }

    private async renderPrivacy(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="animate-pulse space-y-4">
                    <div class="bg-gray-200 h-8 w-1/3 rounded"></div>
                    <div class="bg-gray-200 h-4 w-full rounded"></div>
                    <div class="bg-gray-200 h-4 w-full rounded"></div>
                    <div class="bg-gray-200 h-4 w-2/3 rounded"></div>
                </div>
            </div>
        `;

        try {
            const data = await this.fetchApi('/pages/privacy');
            content.innerHTML = `
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">${data.data.title}</h1>
                    <div class="prose max-w-none">
                        ${data.data.content}
                    </div>
                </div>
            `;
        } catch (error) {
            content.innerHTML = `
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">Privacy Policy</h1>
                    <p class="text-gray-600">Privacy page not available.</p>
                </div>
            `;
        }
    }

    private render404(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="min-h-[60vh] flex items-center justify-center px-4">
                <div class="text-center">
                    <h1 class="text-6xl font-bold text-gray-300 mb-4">404</h1>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-2">Page Not Found</h2>
                    <p class="text-gray-600 mb-8">The page you're looking for doesn't exist.</p>
                    <a href="/" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                        Go Home
                    </a>
                </div>
            </div>
        `;
    }
}
