import { TixelloConfig } from './ConfigManager';
import { TemplateManager } from '../templates';

type RouteHandler = (params: Record<string, string>) => void | Promise<void>;

interface Route {
    pattern: RegExp;
    handler: RouteHandler;
    paramNames: string[];
}


// Cart item interface
interface CartItem {
    eventId: number;
    eventTitle: string;
    eventSlug: string;
    eventDate: string;
    ticketTypeId: number;
    ticketTypeName: string;
    price: number;
    salePrice: number | null;
    quantity: number;
    currency: string;
    bulkDiscounts: any[];
}

class CartService {
    private static STORAGE_KEY = 'tixello_cart';

    static getCart(): CartItem[] {
        const cartJson = localStorage.getItem(this.STORAGE_KEY);
        return cartJson ? JSON.parse(cartJson) : [];
    }

    static addItem(item: CartItem): void {
        const cart = this.getCart();
        const existingIndex = cart.findIndex(
            i => i.eventId === item.eventId && i.ticketTypeId === item.ticketTypeId
        );

        if (existingIndex >= 0) {
            cart[existingIndex].quantity += item.quantity;
        } else {
            cart.push(item);
        }

        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
    }

    static updateQuantity(eventId: number, ticketTypeId: number, quantity: number): void {
        const cart = this.getCart();
        const item = cart.find(i => i.eventId === eventId && i.ticketTypeId === ticketTypeId);
        if (item) {
            item.quantity = quantity;
            if (item.quantity <= 0) {
                this.removeItem(eventId, ticketTypeId);
            } else {
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
            }
        }
    }

    static removeItem(eventId: number, ticketTypeId: number): void {
        let cart = this.getCart();
        cart = cart.filter(i => !(i.eventId === eventId && i.ticketTypeId === ticketTypeId));
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
    }

    static clearCart(): void {
        localStorage.removeItem(this.STORAGE_KEY);
    }

    static getItemCount(): number {
        return this.getCart().reduce((sum, item) => sum + item.quantity, 0);
    }

    static calculateBulkDiscount(qty: number, price: number, discounts: any[]): { total: number; discount: number } {
        let bestTotal = qty * price;
        let bestDiscount = 0;

        for (const discount of discounts) {
            let discountedTotal = qty * price;
            let discountAmount = 0;

            if (discount.rule_type === 'buy_x_get_y' && qty >= discount.buy_qty) {
                const sets = Math.floor(qty / discount.buy_qty);
                const freeTickets = sets * discount.get_qty;
                const paidTickets = qty - freeTickets;
                discountedTotal = paidTickets * price;
                discountAmount = freeTickets * price;
            } else if (discount.rule_type === 'amount_off_per_ticket' && qty >= discount.min_qty) {
                const amountOff = discount.amount_off / 100;
                discountAmount = qty * amountOff;
                discountedTotal = (qty * price) - discountAmount;
            } else if (discount.rule_type === 'percent_off' && qty >= discount.min_qty) {
                discountAmount = (qty * price) * (discount.percent_off / 100);
                discountedTotal = (qty * price) - discountAmount;
            }

            if (discountedTotal < bestTotal) {
                bestTotal = discountedTotal;
                bestDiscount = discountAmount;
            }
        }

        return { total: bestTotal, discount: bestDiscount };
    }

    static getTotal(): { subtotal: number; discount: number; total: number; currency: string } {
        const cart = this.getCart();
        let subtotal = 0;
        let totalDiscount = 0;
        let currency = 'EUR';

        for (const item of cart) {
            const itemPrice = item.salePrice || item.price;
            const result = this.calculateBulkDiscount(item.quantity, itemPrice, item.bulkDiscounts);
            subtotal += item.quantity * itemPrice;
            totalDiscount += result.discount;
            currency = item.currency;
        }

        return {
            subtotal,
            discount: totalDiscount,
            total: subtotal - totalDiscount,
            currency
        };
    }
}


class ToastNotification {
    static show(message: string, type: 'success' | 'error' | 'info' = 'success'): void {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50 transform transition-all duration-300 translate-y-0 opacity-100`;

        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-blue-600'
        };

        toast.classList.add(colors[type]);
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('translate-y-2', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

export class Router {
    private routes: Route[] = [];
    private config: TixelloConfig;
    private currentPath: string = '';
    private authToken: string | null = null;
    private currentUser: any = null;

    constructor(config: TixelloConfig) {
        this.config = config;
        TemplateManager.init(config);
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
        if (!token || !user) {
            console.error('Cannot save auth state: token or user is undefined', { token, user });
            return;
        }
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

    // Update cart badge in header
    private updateCartBadge(): void {
        const badge = document.getElementById('cart-badge');
        if (badge) {
            const count = CartService.getItemCount();
            badge.textContent = count.toString();
            if (count > 0) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    // Update header to show user name when logged in
    private updateHeaderForUser(): void {
        const accountLink = document.getElementById('account-link');
        if (accountLink && this.currentUser) {
            const firstName = this.currentUser.name?.split(' ')[0] || this.currentUser.email;
            accountLink.textContent = `Buna, ${firstName}`;
            accountLink.href = '/account';
        }
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

        // Use poster_url or hero_image_url from new API
        const imageUrl = event.poster_url || event.hero_image_url;

        return `
            <a href="/event/${event.slug}" class="block bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition group">
                <div class="aspect-[16/9] bg-gray-200 relative overflow-hidden">
                    ${imageUrl
                        ? `<img src="${imageUrl}" alt="${event.title}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">`
                        : `<div class="w-full h-full flex items-center justify-center text-gray-400">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                          </div>`
                    }
                    ${event.is_sold_out ? `<span class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">SOLD OUT</span>` : ''}
                    ${event.is_cancelled ? `<span class="absolute top-2 right-2 bg-gray-800 text-white text-xs font-bold px-2 py-1 rounded">ANULAT</span>` : ''}
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1 line-clamp-2">${event.title}</h3>
                    <p class="text-sm text-gray-500 mb-2">${date}</p>
                    ${event.venue ? `<p class="text-sm text-gray-600 mb-2">${event.venue.name}${event.venue.city ? `, ${event.venue.city}` : ''}</p>` : ''}
                    ${event.price_from ? `<p class="text-sm font-semibold text-primary">de la ${event.price_from} ${event.currency || '€'}</p>` : ''}
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
        this.addRoute('/order-success/:orderId', this.renderOrderSuccess.bind(this));
        this.addRoute('/thank-you/:orderNumber', this.renderThankYou.bind(this));
        this.addRoute('/login', this.renderLogin.bind(this));
        this.addRoute('/register', this.renderRegister.bind(this));
        this.addRoute('/account', this.renderAccount.bind(this));
        this.addRoute('/account/orders', this.renderOrders.bind(this));
        this.addRoute('/account/orders/:id', this.renderOrderDetail.bind(this));
        this.addRoute('/account/tickets', this.renderTickets.bind(this));
        this.addRoute('/account/events', this.renderMyEvents.bind(this));
        this.addRoute('/account/profile', this.renderProfile.bind(this));
        this.addRoute('/account/watchlist', this.renderWatchlist.bind(this));
        this.addRoute('/terms', this.renderTerms.bind(this));
        this.addRoute('/privacy', this.renderPrivacy.bind(this));
        this.addRoute('/page/:slug', this.renderPage.bind(this));
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
                    <a href="/events" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-primary hover:bg-primary-dark transition">
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
                const events = featuredData.data.events || featuredData.data || [];
                if (events.length === 0) {
                    featuredEl.innerHTML = `<p class="col-span-3 text-center text-gray-500">Nu există evenimente recomandate momentan.</p>`;
                } else {
                    featuredEl.innerHTML = events.map((event: any) => this.renderEventCard(event)).join('');
                }
            }

            // Render categories
            const categoriesEl = document.getElementById('categories');
            if (categoriesEl && categoriesData.data) {
                const categories = categoriesData.data.categories || categoriesData.data || [];
                if (categories.length === 0) {
                    categoriesEl.innerHTML = `<p class="col-span-4 text-center text-gray-500">Nu există categorii disponibile.</p>`;
                } else {
                    categoriesEl.innerHTML = categories.map((cat: any) => `
                        <a href="/events?category=${cat.slug}" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition text-center">
                            <div class="text-primary mb-2">
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
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <select id="event-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
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
                const categories = categoriesData.data.categories || categoriesData.data || [];
                categories.forEach((cat: any) => {
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
                const events = eventsData.data.events || eventsData.data || [];
                if (events.length === 0) {
                    eventsEl.innerHTML = `
                        <div class="col-span-3 text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-gray-500">Nu au fost găsite evenimente.</p>
                        </div>
                    `;
                } else {
                    eventsEl.innerHTML = events.map((event: any) => this.renderEventCard(event)).join('');
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

            // Store event data globally for cart functionality
            (window as any).currentEventData = event;

            const date = event.start_date ? new Date(event.start_date).toLocaleDateString('ro-RO', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }) : '';

            const time = event.start_time || (event.start_date ? new Date(event.start_date).toLocaleTimeString('ro-RO', {
                hour: '2-digit',
                minute: '2-digit'
            }) : '');

            // Use poster_url or hero_image_url from new API
            const imageUrl = event.poster_url || event.hero_image_url || event.image;

            const eventDetailEl = document.getElementById('event-detail');
            if (eventDetailEl) {
                eventDetailEl.innerHTML = `
                    <div class="lg:col-span-2">
                        ${imageUrl
                            ? `<img src="${imageUrl}" alt="${event.title}" class="w-full h-96 object-cover rounded-lg mb-6">`
                            : `<div class="w-full h-96 bg-gray-200 rounded-lg mb-6 flex items-center justify-center">
                                <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                              </div>`
                        }

                        ${event.is_cancelled ? `
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <strong>Eveniment anulat:</strong> ${event.cancel_reason || 'Acest eveniment a fost anulat.'}
                        </div>
                        ` : ''}

                        ${event.is_postponed ? `
                        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                            <strong>Eveniment amânat:</strong> ${event.postponed_reason || 'Acest eveniment a fost amânat.'}
                        </div>
                        ` : ''}

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
                            <div class="flex items-center flex-wrap gap-2">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <a href="https://core.tixello.com/venue/${event.venue.slug}?locale=en" target="_blank" class="hover:underline text-blue-600">${event.venue.name}</a>
                                    ${event.venue.city ? `, ${event.venue.city}` : ''}
                                </div>
                                ${event.venue.latitude && event.venue.longitude ? `
                                <a href="https://www.google.com/maps?q=${event.venue.latitude},${event.venue.longitude}" target="_blank" class="text-sm text-blue-600 hover:underline">
                                    Vezi pe Google Maps
                                </a>
                                ` : event.venue.address ? `
                                <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(event.venue.address)}" target="_blank" class="text-sm text-blue-600 hover:underline">
                                    Vezi pe Google Maps
                                </a>
                                ` : ''}
                            </div>
                            ` : ''}
                        </div>

                        ${event.short_description ? `
                        <div class="mb-4">
                            <p class="text-gray-700 text-lg">${event.short_description}</p>
                        </div>
                        ` : ''}

                        ${event.description ? `
                        <div class="prose max-w-none mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Descriere</h2>
                            <div class="text-gray-700">${event.description}</div>
                        </div>
                        ` : ''}

                        ${event.ticket_terms ? `
                        <div class="prose max-w-none mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Termeni și condiții bilete</h2>
                            <div class="text-gray-700 text-sm">${event.ticket_terms}</div>
                        </div>
                        ` : ''}

                        ${event.event_website_url || event.facebook_url ? `
                        <div class="mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Link-uri</h2>
                            <div class="flex flex-wrap gap-4">
                                ${event.event_website_url ? `<a href="${event.event_website_url}" target="_blank" class="text-blue-600 hover:underline">Website eveniment</a>` : ''}
                                ${event.facebook_url ? `<a href="${event.facebook_url}" target="_blank" class="text-blue-600 hover:underline">Facebook</a>` : ''}
                                ${event.website_url ? `<a href="${event.website_url}" target="_blank" class="text-blue-600 hover:underline">Website</a>` : ''}
                            </div>
                        </div>
                        ` : ''}

                        ${event.artists && event.artists.length > 0 ? `
                        <div class="mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Artiști</h2>
                            <div class="flex flex-wrap gap-4">
                                ${event.artists.map((artist: any) => `
                                    <a href="https://core.tixello.com/artist/${artist.slug}?locale=en" target="_blank" class="flex items-center bg-gray-100 rounded-lg p-3 hover:bg-gray-200 transition">
                                        ${artist.image
                                            ? `<img src="${artist.image}" alt="${artist.name}" class="w-10 h-10 rounded-full object-cover mr-3">`
                                            : `<div class="w-10 h-10 rounded-full bg-gray-300 mr-3"></div>`
                                        }
                                        <span class="font-medium">${artist.name}</span>
                                    </a>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow-lg p-6 sticky top-24">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Bilete</h2>

                            ${event.is_sold_out || event.door_sales_only || event.is_cancelled ? `
                                <div class="mb-4 p-4 rounded ${event.is_cancelled ? 'bg-red-50 border border-red-200' : 'bg-yellow-50 border border-yellow-200'}">
                                    <p class="text-sm ${event.is_cancelled ? 'text-red-700' : 'text-yellow-700'}">
                                        ${event.is_cancelled ? 'Eveniment anulat' : event.is_sold_out ? 'Bilete epuizate' : 'Bilete disponibile doar la intrare'}
                                    </p>
                                </div>
                            ` : ''}

                            ${event.ticket_types && event.ticket_types.length > 0 && !event.is_cancelled && !event.door_sales_only ? `
                                <div class="space-y-4 mb-6">
                                    ${event.ticket_types.map((ticket: any) => {
                                        const currency = ticket.currency || event.currency || 'EUR';
                                        const available = ticket.available ?? 0;
                                        const maxQty = Math.min(10, available);
                                        return `
                                        <div class="border border-gray-200 rounded-lg p-4 ${ticket.status !== 'active' ? 'opacity-50' : ''}">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h3 class="font-semibold text-gray-900">${ticket.name}</h3>
                                                    ${ticket.description ? `<p class="text-sm text-gray-500">${ticket.description}</p>` : ''}
                                                </div>
                                            </div>
                                            ${ticket.bulk_discounts && ticket.bulk_discounts.length > 0 ? `
                                            <div class="mt-2 space-y-1">
                                                ${ticket.bulk_discounts.map((discount: any) => {
                                                    if (discount.rule_type === 'buy_x_get_y') {
                                                        return `<div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                                            🎁 Cumpără ${discount.buy_qty}, primești ${discount.get_qty} GRATUIT
                                                        </div>`;
                                                    } else if (discount.rule_type === 'amount_off_per_ticket') {
                                                        return `<div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                                            💰 ${discount.amount_off / 100} ${currency} reducere/bilet pentru ${discount.min_qty}+ bilete
                                                        </div>`;
                                                    } else if (discount.rule_type === 'percent_off') {
                                                        return `<div class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                                            📊 ${discount.percent_off}% reducere pentru ${discount.min_qty}+ bilete
                                                        </div>`;
                                                    }
                                                    return '';
                                                }).join('')}
                                            </div>
                                            ` : ''}
                                            <div class="flex justify-between items-start">
                                                <div></div>
                                                <div class="text-right">
                                                    ${ticket.sale_price ? `
                                                        <div>
                                                            <span class="line-through text-gray-400 text-sm">${ticket.price} ${currency}</span>
                                                            <span class="font-bold text-red-600 block">${ticket.sale_price} ${currency}</span>
                                                            ${ticket.discount_percent ? `<span class="text-xs text-red-600">-${ticket.discount_percent}%</span>` : ''}
                                                        </div>
                                                    ` : `
                                                        <span class="font-bold text-primary">${ticket.price} ${currency}</span>
                                                    `}
                                                </div>
                                            </div>
                                            ${ticket.status === 'active' && available > 0 ? `
                                            <div class="flex items-center justify-between mt-3">
                                                <div class="flex items-center gap-2">
                                                    <button class="ticket-minus w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100" data-ticket-id="${ticket.id}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                                        </svg>
                                                    </button>
                                                    <span class="ticket-qty-display w-12 text-center font-semibold" data-ticket-id="${ticket.id}" data-price="${ticket.sale_price || ticket.price}" data-currency="${currency}" data-bulk-discounts='${JSON.stringify(ticket.bulk_discounts || [])}'>0</span>
                                                    <button class="ticket-plus w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100" data-ticket-id="${ticket.id}" data-max="${maxQty}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <span class="text-sm text-gray-500">${available} disponibile</span>
                                            </div>
                                            ` : `
                                                <p class="text-sm text-gray-500 mt-2">${ticket.status !== 'active' ? 'Indisponibil' : 'Stoc epuizat'}</p>
                                            `}
                                        </div>
                                    `}).join('')}
                                </div>

                                <div class="border-t pt-4 mb-4">
                                    <div class="flex justify-between items-center text-lg font-bold">
                                        <span>Total</span>
                                        <span id="cart-total-price">0 ${event.currency || 'EUR'}</span>
                                    </div>
                                </div>

                                <button id="add-to-cart-btn" class="w-full py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition disabled:bg-gray-300 disabled:cursor-not-allowed" disabled>
                                    Adaugă în coș
                                </button>
                                <button id="watchlist-btn" class="w-full mt-3 py-3 border-2 border-primary text-primary font-semibold rounded-lg hover:bg-primary hover:text-white transition flex items-center justify-center gap-2" data-event-id="${event.id}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                    <span id="watchlist-btn-text">Adaugă la favorite</span>
                                </button>
                            ` : `
                                <p class="text-gray-500 text-center py-4">Nu sunt bilete disponibile pentru achiziție online.</p>
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
        const qtyDisplays = document.querySelectorAll('.ticket-qty-display');
        const totalEl = document.getElementById('cart-total-price');
        const addBtn = document.getElementById('add-to-cart-btn');

        // Store quantities in memory
        const quantities: { [key: string]: number } = {};

        // Store bulk discounts per ticket
        const ticketBulkDiscounts: { [key: string]: any[] } = {};
        document.querySelectorAll('.ticket-qty-display').forEach((display) => {
            const ticketId = (display as HTMLElement).dataset.ticketId || '';
            const discountsAttr = (display as HTMLElement).dataset.bulkDiscounts;
            if (discountsAttr) {
                try {
                    ticketBulkDiscounts[ticketId] = JSON.parse(discountsAttr);
                } catch (e) {
                    ticketBulkDiscounts[ticketId] = [];
                }
            } else {
                ticketBulkDiscounts[ticketId] = [];
            }
        });

        const calculateBulkDiscount = (qty: number, price: number, discounts: any[]): { total: number; discount: number; info: string } => {
            let bestTotal = qty * price;
            let bestDiscount = 0;
            let bestInfo = '';

            for (const discount of discounts) {
                let discountedTotal = qty * price;
                let discountAmount = 0;
                let info = '';

                if (discount.rule_type === 'buy_x_get_y' && qty >= discount.buy_qty) {
                    // Calculate how many free tickets
                    const sets = Math.floor(qty / discount.buy_qty);
                    const freeTickets = sets * discount.get_qty;
                    const paidTickets = qty - freeTickets;
                    discountedTotal = paidTickets * price;
                    discountAmount = freeTickets * price;
                    info = `Buy ${discount.buy_qty} get ${discount.get_qty} free`;
                } else if (discount.rule_type === 'amount_off_per_ticket' && qty >= discount.min_qty) {
                    const amountOff = discount.amount_off / 100; // Convert cents to currency
                    discountAmount = qty * amountOff;
                    discountedTotal = (qty * price) - discountAmount;
                    info = `${amountOff} off per ticket`;
                } else if (discount.rule_type === 'percent_off' && qty >= discount.min_qty) {
                    discountAmount = (qty * price) * (discount.percent_off / 100);
                    discountedTotal = (qty * price) - discountAmount;
                    info = `${discount.percent_off}% off`;
                }

                if (discountedTotal < bestTotal) {
                    bestTotal = discountedTotal;
                    bestDiscount = discountAmount;
                    bestInfo = info;
                }
            }

            return { total: bestTotal, discount: bestDiscount, info: bestInfo };
        };

        const updateTotal = () => {
            let total = 0;
            let totalDiscount = 0;
            let hasSelection = false;
            let currency = 'EUR';
            let discountInfos: string[] = [];

            qtyDisplays.forEach((display) => {
                const ticketId = (display as HTMLElement).dataset.ticketId || '';
                const qty = quantities[ticketId] || 0;
                const price = parseFloat((display as HTMLElement).dataset.price || '0');
                const ticketCurrency = (display as HTMLElement).dataset.currency || 'EUR';
                const discounts = ticketBulkDiscounts[ticketId] || [];

                if (qty > 0) {
                    const result = calculateBulkDiscount(qty, price, discounts);
                    total += result.total;
                    totalDiscount += result.discount;
                    if (result.info) discountInfos.push(result.info);
                    hasSelection = true;
                    currency = ticketCurrency;
                }
            });

            if (totalEl) {
                if (totalDiscount > 0) {
                    const originalTotal = total + totalDiscount;
                    totalEl.innerHTML = `
                        <div class="text-sm text-gray-500 line-through">${originalTotal.toFixed(2)} ${currency}</div>
                        <div class="text-lg font-bold text-green-600">${total.toFixed(2)} ${currency}</div>
                        <div class="text-xs text-green-600">Economisești ${totalDiscount.toFixed(2)} ${currency}</div>
                    `;
                } else {
                    totalEl.textContent = `${total.toFixed(2)} ${currency}`;
                }
            }
            if (addBtn) (addBtn as HTMLButtonElement).disabled = !hasSelection;
        };

        // Setup + buttons
        document.querySelectorAll('.ticket-plus').forEach((btn) => {
            btn.addEventListener('click', () => {
                const ticketId = (btn as HTMLElement).dataset.ticketId || '';
                const max = parseInt((btn as HTMLElement).dataset.max || '10');
                const current = quantities[ticketId] || 0;

                if (current < max) {
                    quantities[ticketId] = current + 1;
                    const display = document.querySelector(`.ticket-qty-display[data-ticket-id="${ticketId}"]`);
                    if (display) display.textContent = quantities[ticketId].toString();
                    updateTotal();
                }
            });
        });

        // Setup - buttons
        document.querySelectorAll('.ticket-minus').forEach((btn) => {
            btn.addEventListener('click', () => {
                const ticketId = (btn as HTMLElement).dataset.ticketId || '';
                const current = quantities[ticketId] || 0;

                if (current > 0) {
                    quantities[ticketId] = current - 1;
                    const display = document.querySelector(`.ticket-qty-display[data-ticket-id="${ticketId}"]`);
                    if (display) display.textContent = quantities[ticketId].toString();
                    updateTotal();
                }
            });
        });

        if (addBtn) {
            addBtn.addEventListener('click', () => {
                // Get current event data
                const eventTitle = document.querySelector('#event-detail h1')?.textContent || '';
                const eventData = (window as any).currentEventData; // Store event data globally

                // Collect selected tickets
                let hasItems = false;
                qtyDisplays.forEach((display) => {
                    const ticketId = parseInt((display as HTMLElement).dataset.ticketId || '0');
                    const qty = quantities[ticketId] || 0;

                    if (qty > 0 && eventData) {
                        const ticketType = eventData.ticket_types.find((t: any) => t.id === ticketId);
                        if (ticketType) {
                            CartService.addItem({
                                eventId: eventData.id,
                                eventTitle: eventData.title,
                                eventSlug: eventData.slug,
                                eventDate: eventData.start_date,
                                ticketTypeId: ticketType.id,
                                ticketTypeName: ticketType.name,
                                price: ticketType.price,
                                salePrice: ticketType.sale_price,
                                quantity: qty,
                                currency: ticketType.currency || 'EUR',
                                bulkDiscounts: ticketType.bulk_discounts || []
                            });
                            hasItems = true;
                        }
                    }
                });

                if (hasItems) {
                    ToastNotification.show('✓ Biletele au fost adăugate în coș!', 'success');
                    this.updateCartBadge();
                    this.navigate('/cart');
                } else {
                    ToastNotification.show('Te rog selectează cel puțin un bilet.', 'error');
                }
            });
        }

        // Setup watchlist button handler
        const watchlistBtn = document.getElementById('watchlist-btn');
        if (watchlistBtn) {
            // Check if event is in watchlist on page load
            const checkWatchlist = async () => {
                if (!this.isAuthenticated()) return;

                try {
                    const eventId = watchlistBtn.dataset.eventId;
                    const data = await this.fetchApi(`/account/watchlist/${eventId}/check`);
                    const btnText = document.getElementById('watchlist-btn-text');

                    if (data.in_watchlist) {
                        if (btnText) btnText.textContent = 'În watchlist';
                        watchlistBtn.classList.add('bg-primary', 'text-white');
                        watchlistBtn.classList.remove('text-primary');
                    } else {
                        if (btnText) btnText.textContent = 'Adaugă la favorite';
                        watchlistBtn.classList.remove('bg-primary', 'text-white');
                        watchlistBtn.classList.add('text-primary');
                    }
                } catch (error) {
                    // Silent fail if not authenticated
                }
            };

            checkWatchlist();

            watchlistBtn.addEventListener('click', async () => {
                if (!this.isAuthenticated()) {
                    this.navigate('/login');
                    return;
                }

                const eventId = watchlistBtn.dataset.eventId;
                const btnText = document.getElementById('watchlist-btn-text');

                try {
                    // Check current status
                    const checkData = await this.fetchApi(`/account/watchlist/${eventId}/check`);

                    if (checkData.in_watchlist) {
                        // Remove from watchlist
                        await this.deleteApi(`/account/watchlist/${eventId}`);
                        if (btnText) btnText.textContent = 'Adaugă la favorite';
                        watchlistBtn.classList.remove('bg-primary', 'text-white');
                        watchlistBtn.classList.add('text-primary');
                        ToastNotification.show('✓ Eveniment șters din watchlist', 'success');
                    } else {
                        // Add to watchlist
                        await this.postApi(`/account/watchlist/${eventId}`, {});
                        if (btnText) btnText.textContent = 'În watchlist';
                        watchlistBtn.classList.add('bg-primary', 'text-white');
                        watchlistBtn.classList.remove('text-primary');
                        ToastNotification.show('✓ Eveniment adăugat la watchlist', 'success');
                    }
                } catch (error: any) {
                    if (error.message?.includes('deja în watchlist')) {
                        ToastNotification.show('Evenimentul este deja în watchlist', 'info');
                    } else {
                        ToastNotification.show('Eroare la actualizarea watchlist-ului', 'error');
                    }
                }
            });
        }
    }

    private renderCart(): void {
        const content = this.getContentElement();
        if (!content) return;

        const cart = CartService.getCart();
        const totals = CartService.getTotal();

        if (cart.length === 0) {
            content.innerHTML = `
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
                    <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <h1 class="text-2xl font-bold text-gray-900 mb-4">Coșul tău este gol</h1>
                    <p class="text-gray-600 mb-8">Explorează evenimentele noastre și adaugă bilete în coș.</p>
                    <button class="px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition" onclick="window.tixelloRouter.navigate('/events')">
                        Vezi evenimente
                    </button>
                </div>
            `;
            return;
        }

        const cartItemsHtml = cart.map((item, index) => {
            const effectivePrice = item.salePrice || item.price;
            const result = CartService.calculateBulkDiscount(item.quantity, effectivePrice, item.bulkDiscounts);
            const itemTotal = result.total;
            const itemDiscount = result.discount;
            const originalTotal = item.quantity * effectivePrice;
            const dateFormatted = new Date(item.eventDate).toLocaleDateString('ro-RO', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });

            return `
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-gray-900 mb-1">${item.eventTitle}</h3>
                        <p class="text-sm text-gray-600">${item.ticketTypeName}</p>
                        <p class="text-sm text-gray-500">${dateFormatted}</p>
                    </div>
                    <button class="remove-item-btn text-red-600 hover:text-red-700" data-event-id="${item.eventId}" data-ticket-id="${item.ticketTypeId}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <button class="cart-qty-minus w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100" data-event-id="${item.eventId}" data-ticket-id="${item.ticketTypeId}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                            </svg>
                        </button>
                        <span class="cart-qty-display w-12 text-center font-semibold">${item.quantity}</span>
                        <button class="cart-qty-plus w-8 h-8 flex items-center justify-center border border-gray-300 rounded hover:bg-gray-100" data-event-id="${item.eventId}" data-ticket-id="${item.ticketTypeId}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                    <div class="text-right">
                        ${itemDiscount > 0 ? `
                            <div class="text-sm text-gray-500 line-through">${originalTotal.toFixed(2)} ${item.currency}</div>
                            <div class="font-bold text-green-600">${itemTotal.toFixed(2)} ${item.currency}</div>
                            <div class="text-xs text-green-600">-${itemDiscount.toFixed(2)} ${item.currency}</div>
                        ` : `
                            <div class="font-bold text-gray-900">${itemTotal.toFixed(2)} ${item.currency}</div>
                        `}
                    </div>
                </div>
            </div>
        `}).join('');

        content.innerHTML = `
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Coșul meu</h1>
                    <button id="clear-cart-btn" class="text-sm text-red-600 hover:text-red-700">Golește coșul</button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-4">
                        ${cartItemsHtml}
                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Sumar comandă</h2>

                            <div class="space-y-2 mb-4 pb-4 border-b">
                                <div class="flex justify-between text-gray-600">
                                    <span>Subtotal</span>
                                    <span>${totals.subtotal.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ${totals.discount > 0 ? `
                                <div class="flex justify-between text-green-600">
                                    <span>Discount bulk</span>
                                    <span>-${totals.discount.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ` : ''}
                            </div>

                            <div class="flex justify-between items-center mb-6">
                                <span class="text-lg font-semibold">Total</span>
                                <span class="text-2xl font-bold text-primary">${totals.total.toFixed(2)} ${totals.currency}</span>
                            </div>

                            <button id="checkout-btn" class="w-full py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition">
                                Finalizează comanda
                            </button>

                            <button onclick="window.tixelloRouter.navigate('/events')" class="w-full mt-3 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition">
                                Continuă cumpărăturile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.setupCartHandlers();
    }

    private setupCartHandlers(): void {
        document.querySelectorAll('.remove-item-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLElement;
                const eventId = parseInt(target.dataset.eventId || '0');
                const ticketId = parseInt(target.dataset.ticketId || '0');
                CartService.removeItem(eventId, ticketId);
                this.updateCartBadge();
                this.renderCart();
            });
        });

        document.querySelectorAll('.cart-qty-plus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLElement;
                const eventId = parseInt(target.dataset.eventId || '0');
                const ticketId = parseInt(target.dataset.ticketId || '0');
                const cart = CartService.getCart();
                const item = cart.find(i => i.eventId === eventId && i.ticketTypeId === ticketId);
                if (item) {
                    CartService.updateQuantity(eventId, ticketId, item.quantity + 1);
                    this.updateCartBadge();
                    this.renderCart();
                }
            });
        });

        document.querySelectorAll('.cart-qty-minus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLElement;
                const eventId = parseInt(target.dataset.eventId || '0');
                const ticketId = parseInt(target.dataset.ticketId || '0');
                const cart = CartService.getCart();
                const item = cart.find(i => i.eventId === eventId && i.ticketTypeId === ticketId);
                if (item && item.quantity > 1) {
                    CartService.updateQuantity(eventId, ticketId, item.quantity - 1);
                    this.updateCartBadge();
                    this.renderCart();
                }
            });
        });

        const clearBtn = document.getElementById('clear-cart-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (confirm('Sigur vrei să golești coșul?')) {
                    CartService.clearCart();
                    this.updateCartBadge();
                    this.renderCart();
                }
            });
        }

        const checkoutBtn = document.getElementById('checkout-btn');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', () => {
                this.navigate('/checkout');
            });
        }
    }

        private renderCheckout(): void {
        const content = this.getContentElement();
        if (!content) return;

        const cart = CartService.getCart();
        const totals = CartService.getTotal();

        if (cart.length === 0) {
            this.navigate('/cart');
            return;
        }

        content.innerHTML = `
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Finalizare comandă</h1>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <form id="checkout-form" class="space-y-6">
                            <div class="bg-white rounded-lg shadow p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Date personale</h2>
                                <div class="space-y-4">
                                    <div>
                                        <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                                            Nume complet *
                                        </label>
                                        <input
                                            type="text"
                                            id="customer_name"
                                            name="customer_name"
                                            required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                            placeholder="Ion Popescu"
                                        >
                                    </div>
                                    <div>
                                        <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">
                                            Email *
                                        </label>
                                        <input
                                            type="email"
                                            id="customer_email"
                                            name="customer_email"
                                            required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                            placeholder="ion@example.com"
                                        >
                                    </div>
                                    <div>
                                        <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">
                                            Telefon
                                        </label>
                                        <input
                                            type="tel"
                                            id="customer_phone"
                                            name="customer_phone"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                            placeholder="0722123456"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Plată</h2>
                                <p class="text-gray-600 mb-4">
                                    Vei primi biletele pe email imediat după finalizarea comenzii.
                                </p>
                                <button
                                    type="submit"
                                    id="submit-order-btn"
                                    class="w-full py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition disabled:bg-gray-300 disabled:cursor-not-allowed"
                                >
                                    Plasează comanda
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Sumar comandă</h2>

                            <div class="space-y-3 mb-4 pb-4 border-b">
                                ${cart.map(item => {
                                    const effectivePrice = item.salePrice || item.price;
                                    const result = CartService.calculateBulkDiscount(item.quantity, effectivePrice, item.bulkDiscounts);
                                    return `
                                    <div class="flex justify-between text-sm">
                                        <div>
                                            <div class="font-medium">${item.eventTitle}</div>
                                            <div class="text-gray-500">${item.ticketTypeName} × ${item.quantity}</div>
                                        </div>
                                        <div class="font-medium">${result.total.toFixed(2)} ${item.currency}</div>
                                    </div>
                                `}).join('')}
                            </div>

                            <div class="space-y-2 mb-4 pb-4 border-b">
                                <div class="flex justify-between text-gray-600">
                                    <span>Subtotal</span>
                                    <span>${totals.subtotal.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ${totals.discount > 0 ? `
                                <div class="flex justify-between text-green-600">
                                    <span>Discount</span>
                                    <span>-${totals.discount.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ` : ''}
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-lg font-semibold">Total</span>
                                <span class="text-2xl font-bold text-primary">${totals.total.toFixed(2)} ${totals.currency}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.setupCheckoutHandlers();
    }

    private setupCheckoutHandlers(): void {
        const form = document.getElementById('checkout-form') as HTMLFormElement;
        const submitBtn = document.getElementById('submit-order-btn') as HTMLButtonElement;

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Se procesează...';
                }

                const formData = new FormData(form);
                const cart = CartService.getCart();

                try {
                    const response = await this.postApi('/orders', {
                        customer_name: formData.get('customer_name'),
                        customer_email: formData.get('customer_email'),
                        customer_phone: formData.get('customer_phone'),
                        cart: cart.map(item => ({
                            eventId: item.eventId,
                            ticketTypeId: item.ticketTypeId,
                            quantity: item.quantity,
                        })),
                    });

                    if (response.success) {
                        CartService.clearCart();
                        this.updateCartBadge();
                        ToastNotification.show('✓ Comanda a fost plasată cu succes!', 'success');
                        this.navigate(`/order-success/${response.data.order_id}`);
                    } else {
                        throw new Error(response.error || 'Eroare la plasarea comenzii');
                    }
                } catch (error: any) {
                    ToastNotification.show(error.message || 'Eroare la plasarea comenzii', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Plasează comanda';
                    }
                }
            });
        }
    }



    private async renderOrderSuccess(params: Record<string, string>): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        const orderId = params.orderId;

        content.innerHTML = `
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
                <div class="mb-8">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Comanda plasată cu succes!</h1>
                    <p class="text-gray-600 mb-4">
                        Comanda ta # a fost înregistrată.
                    </p>
                    <p class="text-gray-600">
                        Vei primi biletele pe email în câteva minute.
                    </p>
                </div>

                <div class="space-y-3">
                    <button onclick="window.tixelloRouter.navigate('/events')" class="w-full max-w-xs mx-auto block px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition">
                        Înapoi la evenimente
                    </button>
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
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Parolă</label>
                            <input type="password" id="password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <button type="submit" id="login-btn" class="w-full px-4 py-2 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition">
                            Conectare
                        </button>
                    </form>
                    <p class="text-center text-gray-600">
                        Nu ai cont? <a href="/register" class="text-primary hover:text-blue-700 font-medium">Înregistrează-te</a>
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
                console.log('Login response:', result);
                if (result.success && result.data) {
                    this.saveAuthState(result.data.token, result.data.user);
                    this.navigate('/account');
                } else {
                    throw new Error(result.message || 'Invalid response from server');
                }
            } catch (error: any) {
                console.error('Login error:', error);
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
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Nume</label>
                                <input type="text" id="last_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Telefon (opțional)</label>
                            <input type="tel" id="phone"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Parolă</label>
                            <input type="password" id="password" required minlength="8"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmă parola</label>
                            <input type="password" id="password_confirmation" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                        </div>
                        <button type="submit" id="register-btn" class="w-full px-4 py-2 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition">
                            Creează cont
                        </button>
                    </form>
                    <p class="text-center text-gray-600">
                        Ai deja cont? <a href="/login" class="text-primary hover:text-blue-700 font-medium">Conectează-te</a>
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
                });
                console.log('Register response:', result);
                if (result.success && result.data) {
                    this.saveAuthState(result.data.token, result.data.user);
                    ToastNotification.show('✓ Cont creat cu succes!', 'success');
                    this.navigate('/account');
                } else {
                    throw new Error(result.message || 'Invalid response from server');
                }
            } catch (error: any) {
                console.error('Register error:', error);
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
                        <p class="text-gray-600">Bun venit, ${userName?.split(' ')[0] || userName}!</p>
                    </div>
                    <button id="logout-btn" class="px-4 py-2 text-red-600 hover:text-red-700 font-medium">
                        Deconectare
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="/account/orders" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-primary mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Comenzile mele</h3>
                        <p class="text-gray-600 text-sm">Vezi istoricul comenzilor</p>
                    </a>
                    <a href="/account/tickets" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-primary mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Biletele mele</h3>
                        <p class="text-gray-600 text-sm">Accesează biletele tale</p>
                    </a>
                    <a href="/account/watchlist" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-primary mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Evenimente favorite</h3>
                        <p class="text-gray-600 text-sm">Vezi watchlist-ul tău</p>
                    </a>
                    <a href="/account/profile" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-primary mb-3">
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

    private async renderOrders(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la Cont
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Comenzile Mele</h1>
                <div id="orders-list" class="space-y-4">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                </div>
            </div>
        `;

        try {
            const response = await this.fetchApi('/account/orders');
            const orders = response.data;

            const ordersListEl = document.getElementById('orders-list');
            if (ordersListEl) {
                if (orders && orders.length > 0) {
                    ordersListEl.innerHTML = orders.map((order: any) => `
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">${order.order_number}</h3>
                                    <p class="text-sm text-gray-600">${order.date}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-gray-900">${order.total} ${order.currency}</p>
                                    <span class="inline-block px-3 py-1 text-xs font-medium rounded-full ${
                                        order.status === 'paid' ? 'bg-green-100 text-green-800' :
                                        order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                        order.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                                        'bg-gray-100 text-gray-800'
                                    }">
                                        ${order.status_label}
                                    </span>
                                </div>
                            </div>
                            <div class="border-t pt-4">
                                <p class="text-sm text-gray-600 mb-2">${order.items_count} bilet${order.items_count > 1 ? 'e' : ''}</p>
                                <div class="space-y-1">
                                    ${order.tickets.map((ticket: any) => `
                                        <p class="text-sm text-gray-700">• ${ticket.event_name} - ${ticket.ticket_type}</p>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    ordersListEl.innerHTML = `
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-gray-600">Nu ai comenzi încă</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            const ordersListEl = document.getElementById('orders-list');
            if (ordersListEl) {
                ordersListEl.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p class="text-red-700">Eroare la încărcarea comenzilor</p>
                    </div>
                `;
            }
        }
    }

    private async renderTickets(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la Cont
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Biletele Mele</h1>
                <div id="tickets-list" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                    <div class="animate-pulse bg-gray-200 rounded-lg h-48"></div>
                </div>
            </div>
        `;

        try {
            const response = await this.fetchApi('/account/tickets');
            const tickets = response.data;

            const ticketsListEl = document.getElementById('tickets-list');
            if (ticketsListEl) {
                if (tickets && tickets.length > 0) {
                    ticketsListEl.innerHTML = tickets.map((ticket: any) => `
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">${ticket.event_name}</h3>
                                    <p class="text-sm text-gray-600">${ticket.ticket_type}</p>
                                </div>
                                <span class="inline-block px-3 py-1 text-xs font-medium rounded-full ${
                                    ticket.status === 'valid' ? 'bg-green-100 text-green-800' :
                                    ticket.status === 'used' ? 'bg-gray-100 text-gray-800' :
                                    'bg-red-100 text-red-800'
                                }">
                                    ${ticket.status_label}
                                </span>
                            </div>
                            ${ticket.date ? `<p class="text-sm text-gray-600 mb-2">📅 ${new Date(ticket.date).toLocaleDateString('ro-RO')}</p>` : ''}
                            ${ticket.venue ? `<p class="text-sm text-gray-600 mb-2">📍 ${ticket.venue}</p>` : ''}
                            ${ticket.seat_label ? `<p class="text-sm text-gray-600 mb-4">💺 ${ticket.seat_label}</p>` : '<div class="mb-4"></div>'}
                            <div class="border-t pt-4 text-center">
                                <img src="${ticket.qr_code}" alt="QR Code" class="w-32 h-32 mx-auto mb-2">
                                <p class="text-xs text-gray-500">${ticket.code}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    ticketsListEl.innerHTML = `
                        <div class="col-span-2 bg-white rounded-lg shadow p-8 text-center">
                            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                            <p class="text-gray-600">Nu ai bilete încă</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading tickets:', error);
            const ticketsListEl = document.getElementById('tickets-list');
            if (ticketsListEl) {
                ticketsListEl.innerHTML = `
                    <div class="col-span-2 bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p class="text-red-700">Eroare la încărcarea biletelor</p>
                    </div>
                `;
            }
        }
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

    
    private async renderWatchlist(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        try {
            const data = await this.fetchApi('/account/watchlist');

            const eventsHtml = data.data.length > 0 ? data.data.map((event: any) => `
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="md:flex">
                        <div class="md:w-48 h-48 md:h-auto">
                            ${event.poster_url
                                ? `<img src="${event.poster_url}" alt="${event.title}" class="w-full h-full object-cover">`
                                : `<div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>`
                            }
                        </div>
                        <div class="p-6 flex-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-2">${event.title}</h3>
                                    <div class="space-y-1 text-sm text-gray-600">
                                        <p>
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            ${new Date(event.start_date).toLocaleDateString('ro-RO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                                            ${event.start_time ? ` • ${event.start_time}` : ''}
                                        </p>
                                        ${event.venue ? `
                                            <p>
                                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                ${event.venue.name}${event.venue.city ? `, ${event.venue.city}` : ''}
                                            </p>
                                        ` : ''}
                                        <p class="text-primary font-semibold">
                                            ${event.is_sold_out ? 'Sold Out' : `De la ${event.price_from} ${event.currency}`}
                                        </p>
                                    </div>
                                </div>
                                <button class="remove-watchlist-btn text-red-600 hover:text-red-700 p-2" data-event-id="${event.id}">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="mt-4 flex gap-3">
                                <a href="/event/${event.slug}" class="inline-block px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition">
                                    Vezi detalii
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('') : `
                <div class="text-center py-16">
                    <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Niciun eveniment în watchlist</h2>
                    <p class="text-gray-600 mb-6">Adaugă evenimente favorite pentru a le urmări</p>
                    <a href="/events" class="inline-block px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark transition">
                        Explorează evenimente
                    </a>
                </div>
            `;

            content.innerHTML = `
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Evenimente favorite</h1>
                            <p class="text-gray-600">Evenimente pe care le urmărești</p>
                        </div>
                        <a href="/account" class="text-primary hover:text-primary-dark font-medium">← Înapoi la cont</a>
                    </div>

                    <div class="space-y-4">
                        ${eventsHtml}
                    </div>
                </div>
            `;

            // Add event listeners for remove buttons
            document.querySelectorAll('.remove-watchlist-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const target = e.currentTarget as HTMLElement;
                    const eventId = target.dataset.eventId;

                    if (confirm('Sigur vrei să ștergi acest eveniment din watchlist?')) {
                        try {
                            await this.deleteApi(`/account/watchlist/${eventId}`);
                            ToastNotification.show('✓ Eveniment șters din watchlist', 'success');
                            this.renderWatchlist();
                        } catch (error) {
                            ToastNotification.show('Eroare la ștergerea evenimentului', 'error');
                        }
                    }
                });
            });
        } catch (error) {
            content.innerHTML = `
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
                    <p class="text-red-600">Eroare la încărcarea watchlist-ului</p>
                </div>
            `;
        }
    }
private async renderProfile(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la Cont
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Profilul Meu</h1>
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

        try {
            const response = await this.fetchApi('/account/profile');
            const profile = response.data;

            const profileFormEl = document.getElementById('profile-form');
            if (profileFormEl) {
                profileFormEl.innerHTML = `
                    <form id="update-profile-form" class="space-y-6">
                        <div id="profile-message"></div>

                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">Prenume</label>
                            <input type="text" id="first_name" name="first_name" value="${profile.first_name || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Nume</label>
                            <input type="text" id="last_name" name="last_name" value="${profile.last_name || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" value="${profile.email || ''}" disabled
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Telefon</label>
                            <input type="tel" id="phone" name="phone" value="${profile.phone || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-2">Oraș</label>
                            <input type="text" id="city" name="city" value="${profile.city || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700 mb-2">Țară</label>
                            <input type="text" id="country" name="country" value="${profile.country || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">Data Nașterii</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="${profile.date_of_birth || ''}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div class="border-t pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Schimbă Parola</h3>

                            <div class="space-y-4">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Parola Curentă</label>
                                    <input type="password" id="current_password" name="current_password"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>

                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Parola Nouă</label>
                                    <input type="password" id="new_password" name="new_password"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>

                                <div>
                                    <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirmă Parola Nouă</label>
                                    <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 transition">
                            Salvează Modificările
                        </button>

                        <div class="border-t pt-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Zona Periculoasă</h3>
                            <p class="text-sm text-gray-600 mb-4">Odată ce îți ștergi contul, nu mai există cale de întoarcere. Te rog fii sigur.</p>
                            <button type="button" id="delete-account-btn" class="w-full bg-red-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-red-700 transition">
                                Șterge Contul
                            </button>
                        </div>
                    </form>
                `;

                // Handle form submission
                const form = document.getElementById('update-profile-form') as HTMLFormElement;
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const messageEl = document.getElementById('profile-message');

                        try {
                            const formData = new FormData(form);
                            const data: any = {};
                            formData.forEach((value, key) => {
                                if (value) data[key] = value;
                            });

                            const response = await this.fetchApi('/account/profile', {}, {
                                method: 'PUT',
                                body: JSON.stringify(data)
                            });

                            if (messageEl) {
                                messageEl.innerHTML = `
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                        <p class="text-green-700">${response.message || 'Profil actualizat cu succes!'}</p>
                                    </div>
                                `;
                            }

                            // Clear password fields
                            (document.getElementById('current_password') as HTMLInputElement).value = '';
                            (document.getElementById('new_password') as HTMLInputElement).value = '';
                            (document.getElementById('new_password_confirmation') as HTMLInputElement).value = '';
                        } catch (error: any) {
                            if (messageEl) {
                                messageEl.innerHTML = `
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                        <p class="text-red-700">${error.message || 'Eroare la actualizarea profilului'}</p>
                                    </div>
                                `;
                            }
                        }
                    });
                }

                // Handle delete account
                const deleteBtn = document.getElementById('delete-account-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', async () => {
                        const confirmation1 = confirm('Ești absolut sigur că vrei să îți ștergi contul? Această acțiune NU poate fi anulată!');
                        if (!confirmation1) return;

                        const confirmation2 = confirm('Confirmă din nou: Toate datele tale, comenzile și biletele vor fi șterse permanent. Vrei să continui?');
                        if (!confirmation2) return;

                        try {
                            await this.deleteApi('/account/delete');
                            alert('Contul tău a fost șters cu succes.');
                            this.clearAuthState();
                            this.navigate('/');
                        } catch (error: any) {
                            alert(error.message || 'Eroare la ștergerea contului');
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Error loading profile:', error);
            const profileFormEl = document.getElementById('profile-form');
            if (profileFormEl) {
                profileFormEl.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p class="text-red-700">Eroare la încărcarea profilului</p>
                    </div>
                `;
            }
        }
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
                <a href="/account/tickets" class="inline-flex items-center px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition">
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

    private async renderPage(params: Record<string, string>): Promise<void> {
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
            const data = await this.fetchApi(`/pages/${params.slug}`);
            content.innerHTML = `
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">${data.data.title}</h1>
                    <div class="prose max-w-none">
                        ${data.data.content}
                    </div>
                </div>
            `;
        } catch (error) {
            this.render404();
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
                    <a href="/" class="inline-flex items-center px-6 py-3 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition">
                        Go Home
                    </a>
                </div>
            </div>
        `;
    }
}
