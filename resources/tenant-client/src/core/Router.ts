import { TixelloConfig } from './ConfigManager';
import { TemplateManager } from '../templates';
import { PageBuilderModule, PageLayout } from '../modules/PageBuilderModule';
import { PreviewMode } from './PreviewMode';

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
    price: number;          // Base price (original price)
    salePrice: number | null;  // Sale price (discounted price if any)
    finalPrice: number;     // Price including commission if applicable
    commissionAmount: number;  // Commission amount per ticket (calculated from base price)
    commissionRate: number;    // Commission rate percentage
    hasCommissionOnTop: boolean;  // Whether commission is added on top
    quantity: number;
    currency: string;
    bulkDiscounts: any[];
}

interface AppliedDiscount {
    code: string;
    name: string;
    type: 'percentage' | 'fixed';
    value: number;
    discountAmount: number;
}

class CartService {
    private static STORAGE_KEY = 'tixello_cart';
    private static DISCOUNT_KEY = 'tixello_discount';

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

    static getTotal(): { subtotal: number; discount: number; commission: number; total: number; currency: string; hasCommission: boolean } {
        const cart = this.getCart();
        let subtotal = 0;
        let totalDiscount = 0;
        let totalCommission = 0;
        let hasCommission = false;
        let currency = 'RON';

        for (const item of cart) {
            // Use salePrice if available, otherwise base price (NOT finalPrice which includes commission)
            const itemPrice = item.salePrice || item.price;
            const result = this.calculateBulkDiscount(item.quantity, itemPrice, item.bulkDiscounts);
            subtotal += result.total;  // This is already the discounted total
            totalDiscount += result.discount;
            currency = item.currency;

            // Calculate commission from BASE price × quantity (not affected by discounts)
            if (item.hasCommissionOnTop && item.commissionRate > 0) {
                const commission = item.quantity * item.price * (item.commissionRate / 100);
                totalCommission += commission;
                hasCommission = true;
            }
        }

        // Apply coupon discount if exists
        const couponDiscount = this.getDiscount();
        let couponDiscountAmount = 0;
        if (couponDiscount) {
            if (couponDiscount.type === 'percentage') {
                couponDiscountAmount = subtotal * (couponDiscount.value / 100);
            } else {
                couponDiscountAmount = couponDiscount.discountAmount;
            }
        }

        return {
            subtotal: subtotal + totalDiscount, // Original subtotal before discount
            discount: totalDiscount + couponDiscountAmount,
            commission: totalCommission,
            total: Math.max(0, subtotal + totalCommission - couponDiscountAmount),  // subtotal already has bulk discount applied, add commission, subtract coupon
            currency,
            hasCommission
        };
    }

    static setDiscount(discount: AppliedDiscount): void {
        localStorage.setItem(this.DISCOUNT_KEY, JSON.stringify(discount));
    }

    static getDiscount(): AppliedDiscount | null {
        const discountJson = localStorage.getItem(this.DISCOUNT_KEY);
        return discountJson ? JSON.parse(discountJson) : null;
    }

    static removeDiscount(): void {
        localStorage.removeItem(this.DISCOUNT_KEY);
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

    // DELETE helper
    private async deleteApi(endpoint: string): Promise<any> {
        const url = new URL(`${this.config.apiEndpoint}${endpoint}`);
        url.searchParams.set('hostname', window.location.hostname);

        const headers: HeadersInit = {
            'Content-Type': 'application/json',
        };

        if (this.authToken) {
            (headers as Record<string, string>)['Authorization'] = `Bearer ${this.authToken}`;
        }

        const response = await fetch(url.toString(), {
            method: 'DELETE',
            headers,
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || result.error || 'Request failed');
        }

        return result;
    }

    // Event card HTML generator
    private renderEventCard(event: any): string {
        // Use postponed date if event is postponed
        const displayDate = event.is_postponed && event.postponed_date ? event.postponed_date : event.start_date;
        const date = displayDate ? new Date(displayDate).toLocaleDateString('ro-RO', {
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
                    ${event.is_postponed && !event.is_cancelled ? `<span class="absolute top-2 right-2 bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded">AMÂNAT</span>` : ''}
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1 line-clamp-2">${event.title}</h3>
                    <p class="text-sm text-gray-500 mb-2">${date}</p>
                    ${event.venue ? `<p class="text-sm text-gray-600 mb-2">${event.venue.name}${event.venue.city ? `, ${event.venue.city}` : ''}</p>` : ''}
                    ${event.price_from ? `<p class="text-sm font-semibold text-primary">de la ${event.price_from} ${event.currency || 'RON'}</p>` : ''}
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

        // Update cart badge on page load
        this.updateCartBadge();
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
        this.addRoute('/forgot-password', this.renderForgotPassword.bind(this));
        this.addRoute('/reset-password', this.renderResetPassword.bind(this));
        this.addRoute('/account', this.renderAccount.bind(this));
        this.addRoute('/account/orders', this.renderOrders.bind(this));
        this.addRoute('/account/orders/:id', this.renderOrderDetail.bind(this));
        this.addRoute('/account/tickets', this.renderTickets.bind(this));
        this.addRoute('/account/events', this.renderMyEvents.bind(this));
        this.addRoute('/account/profile', this.renderProfile.bind(this));
        this.addRoute('/account/watchlist', this.renderWatchlist.bind(this));
        this.addRoute('/terms', this.renderTerms.bind(this));
        this.addRoute('/privacy', this.renderPrivacy.bind(this));
        this.addRoute('/past-events', this.renderPastEvents.bind(this));
        this.addRoute('/page/:slug', this.renderPage.bind(this));
        this.addRoute('/blog', this.renderBlog.bind(this));
        this.addRoute('/blog/:slug', this.renderBlogArticle.bind(this));
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

        // Check if there's a page builder layout for the home page
        try {
            const pageData = await this.fetchApi('/pages/home');
            if (pageData.success && pageData.data?.page_type === 'builder' && pageData.data?.layout?.blocks) {
                // Use PageBuilder to render the home page
                content.innerHTML = `<div id="page-content"></div>`;
                PageBuilderModule.updateLayout(pageData.data.layout as PageLayout, 'page-content');

                // Register for preview mode updates
                if (PreviewMode.isActive()) {
                    PreviewMode.onLayoutUpdate((layout) => {
                        PageBuilderModule.updateLayout(layout as PageLayout, 'page-content');
                    });
                }
                return;
            }
        } catch {
            // Fall back to default home page
        }

        // Default home page (backwards compatible)
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

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Evenimente</h1>

                <!-- Calendar Timeline (Sticky) -->
                <div id="events-calendar" class="space-y-4 mb-8 sticky top-4 z-20 bg-gray-50 pt-4 px-2 pb-2 -mt-4 border-b border-gray-200">
                    <!-- Year/Month Navigator -->
                    <div id="month-navigator" class="flex items-center gap-x-8 flex-wrap">
                        <div class="animate-pulse bg-gray-200 h-8 w-full rounded"></div>
                    </div>

                    <!-- Days Strip (hidden scrollbar, drag to scroll) -->
                    <div class="relative">
                        <div id="days-scroller" class="days-scroller flex items-stretch gap-6 overflow-x-scroll pb-2 whitespace-nowrap cursor-grab active:cursor-grabbing" style="scrollbar-width: none; -ms-overflow-style: none;">
                            <div class="animate-pulse flex gap-2">
                                ${Array(14).fill(0).map(() => '<div class="w-14 h-16 bg-white/70 rounded-xl shrink-0"></div>').join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Events List -->
                <div id="events-container" class="min-h-[80px]">
                    <div class="animate-pulse space-y-3">
                        <div class="bg-gray-400 h-20 rounded-xl"></div>
                        <div class="bg-gray-300 h-20 rounded-xl"></div>
                        <div class="bg-gray-200 h-20 rounded-xl"></div>
                    </div>
                </div>
            </div>
        `;

        // Fetch events
        try {
            const eventsData = await this.fetchApi('/events');
            const events = eventsData.data?.events || eventsData.data || [];

            // Initialize calendar with events
            this.initEventsCalendar(events);
        } catch (error) {
            console.error('Failed to load events:', error);
            const container = document.getElementById('events-container');
            if (container) {
                container.innerHTML = `<p class="text-center text-red-500 py-4">Eroare la încărcarea evenimentelor.</p>`;
            }
        }
    }

    private initEventsCalendar(rawEvents: any[]): void {
        const MONTHS_AHEAD = 3;
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // State
        let selectedType: 'all' | 'day' | 'month' = 'all';
        let selectedDateStr: string | null = null;
        let selectedYear: number | null = null;
        let selectedMonthIndex: number | null = null;

        const monthNamesShort = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthNamesFull = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
        const weekdayNamesShort = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];

        // Helper functions
        const formatYmd = (date: Date): string => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const todayStr = formatYmd(today);

        // Parse event date
        const parseEventDate = (ev: any): Date => {
            const usePostponed = ev.is_postponed && ev.postponed_date;
            const dateRaw = usePostponed ? ev.postponed_date : ev.start_date;
            const timeRaw = usePostponed ? ev.postponed_start_time : ev.start_time;

            if (!dateRaw) return today;

            const datePart = String(dateRaw).split('T')[0];
            const parts = datePart.split('-').map(Number);
            if (parts.length !== 3) return today;

            const [y, m, d] = parts;
            let hh = 0, mm = 0;
            if (timeRaw) {
                const tParts = String(timeRaw).split(':');
                if (tParts.length >= 2) {
                    hh = parseInt(tParts[0]) || 0;
                    mm = parseInt(tParts[1]) || 0;
                }
            }

            return new Date(y, m - 1, d, hh, mm);
        };

        // Normalize events
        const eventsByDate: Record<string, any[]> = {};
        const allEvents = rawEvents.map((ev) => {
            const date = parseEventDate(ev);
            const dateStr = formatYmd(date);

            const prettyDate = date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
            const prettyTime = date.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });

            const normalized = {
                id: ev.id,
                title: ev.title || ev.name || 'Untitled',
                slug: ev.slug,
                venue: ev.venue?.name || '',
                city: ev.venue?.city || '',
                posterUrl: ev.poster_url || ev.hero_image_url,
                date,
                dateStr,
                dateLabel: prettyDate,
                timeLabel: prettyTime,
                isPostponed: !!ev.is_postponed,
                isCancelled: !!ev.is_cancelled,
                isSoldOut: !!ev.is_sold_out,
                doorSalesOnly: !!ev.door_sales_only,
                priceFrom: ev.price_from || null,
            };

            if (!eventsByDate[dateStr]) eventsByDate[dateStr] = [];
            eventsByDate[dateStr].push(normalized);

            return normalized;
        });

        // Build days range
        interface DayInfo {
            date: Date;
            dateStr: string;
            year: number;
            monthIndex: number;
            weekdayShort: string;
            day: number;
            isToday: boolean;
            hasEvents: boolean;
            hasPostponed: boolean;
            hasCancelled: boolean;
        }

        const days: DayInfo[] = [];
        const end = new Date(today);
        end.setMonth(end.getMonth() + MONTHS_AHEAD);

        for (let d = new Date(today); d <= end; d.setDate(d.getDate() + 1)) {
            const date = new Date(d);
            const dateStr = formatYmd(date);
            const eventsForDay = eventsByDate[dateStr] || [];

            days.push({
                date,
                dateStr,
                year: date.getFullYear(),
                monthIndex: date.getMonth(),
                weekdayShort: weekdayNamesShort[date.getDay()],
                day: date.getDate(),
                isToday: dateStr === todayStr,
                hasEvents: eventsForDay.length > 0,
                hasPostponed: eventsForDay.some(e => e.isPostponed),
                hasCancelled: eventsForDay.some(e => e.isCancelled),
            });
        }

        // Build year/month groups
        interface MonthInfo { index: number; label: string; eventCount: number; }
        interface YearGroup { year: number; months: MonthInfo[]; }

        const byYear: Record<number, Record<number, MonthInfo>> = {};
        days.forEach((day) => {
            if (!byYear[day.year]) byYear[day.year] = {};
            if (!byYear[day.year][day.monthIndex]) {
                byYear[day.year][day.monthIndex] = {
                    index: day.monthIndex,
                    label: monthNamesShort[day.monthIndex],
                    eventCount: 0,
                };
            }
            if (eventsByDate[day.dateStr]) {
                byYear[day.year][day.monthIndex].eventCount += eventsByDate[day.dateStr].length;
            }
        });

        const years: YearGroup[] = Object.keys(byYear).sort().map((yearStr) => {
            const yearNum = Number(yearStr);
            const months = Object.values(byYear[yearNum]).sort((a, b) => a.index - b.index);
            return { year: yearNum, months };
        });

        // Build month blocks for days strip
        interface MonthBlock { year: number; monthIndex: number; label: string; days: DayInfo[]; }
        const monthBlocks: MonthBlock[] = [];
        const blocksMap: Record<string, MonthBlock> = {};

        days.forEach((day) => {
            const key = `${day.year}-${day.monthIndex}`;
            if (!blocksMap[key]) {
                blocksMap[key] = {
                    year: day.year,
                    monthIndex: day.monthIndex,
                    label: monthNamesFull[day.monthIndex],
                    days: [],
                };
                monthBlocks.push(blocksMap[key]);
            }
            blocksMap[key].days.push(day);
        });

        // Render functions
        const renderMonthNavigator = () => {
            const nav = document.getElementById('month-navigator');
            if (!nav) return;

            nav.innerHTML = years.map(year => `
                <div class="flex items-center">
                    <div class="w-10 text-xs font-bold text-gray-800">${year.year}</div>
                    <div class="flex flex-wrap gap-2">
                        ${year.months.map(month => {
                            const isSelected = selectedType === 'month' && selectedYear === year.year && selectedMonthIndex === month.index;
                            return `
                                <button type="button" data-year="${year.year}" data-month="${month.index}"
                                    class="month-btn px-2 py-1 rounded-md text-[11px] uppercase font-semibold transition
                                    ${isSelected ? 'bg-gray-900 text-white border border-gray-900' : 'border bg-white/70 border-gray-200 text-gray-800 hover:border-gray-400 hover:bg-gray-50'}">
                                    <span>${month.label}</span>
                                    ${month.eventCount ? `<span class="ml-1 text-[11px] font-bold ${isSelected ? 'text-gray-400' : 'text-gray-800'}">${month.eventCount}</span>` : ''}
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            `).join('');

            // Add click handlers
            nav.querySelectorAll('.month-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const year = parseInt((btn as HTMLElement).dataset.year || '0');
                    const month = parseInt((btn as HTMLElement).dataset.month || '0');
                    selectMonth(year, month);
                });
            });
        };

        const getDayClasses = (day: DayInfo): string => {
            const isSelected = selectedType === 'day' && selectedDateStr === day.dateStr;
            if (isSelected) return 'border-gray-900 bg-gray-900 text-white';
            if (day.hasCancelled) return 'border-red-300 bg-red-50 text-red-900';
            if (day.hasPostponed) return 'border-amber-300 bg-amber-50 text-amber-900';
            if (day.hasEvents) return 'border-emerald-300 bg-emerald-50 text-gray-900';
            return 'border-gray-200 text-gray-700 hover:border-gray-400';
        };

        const getDotClass = (day: DayInfo): string => {
            if (day.hasCancelled) return 'bg-red-500';
            if (day.hasPostponed) return 'bg-amber-500';
            if (day.hasEvents) return 'bg-emerald-500';
            return 'bg-transparent';
        };

        const renderDaysStrip = () => {
            const scroller = document.getElementById('days-scroller');
            if (!scroller) return;

            scroller.innerHTML = monthBlocks.map(month => `
                <div class="flex flex-col items-start gap-1 shrink-0">
                    <div class="text-xs font-semibold text-gray-800 uppercase">${month.label}</div>
                    <div class="flex items-stretch gap-2">
                        ${month.days.map(day => {
                            const isSelected = selectedType === 'day' && selectedDateStr === day.dateStr;
                            return `
                                <button type="button" data-date="${day.dateStr}"
                                    class="day-btn flex flex-col items-center justify-between w-14 h-16 ${day.isToday ? 'py-1 px-3' : 'p-3'} rounded-xl border text-xs leading-tight shrink-0 transition ${getDayClasses(day)}">
                                    <span class="text-xl font-bold">${day.day}</span>
                                    <span class="text-[11px] capitalize">${day.weekdayShort}</span>
                                    ${day.isToday ? `<span class="mt-0.5 text-[9px] uppercase tracking-wide ${isSelected ? 'text-white' : 'text-blue-600'}">Azi</span>` : ''}
                                    <span class="mt-0.5 h-1.5 w-1.5 rounded-full ${getDotClass(day)}"></span>
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            `).join('');

            // Add click handlers
            scroller.querySelectorAll('.day-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const dateStr = (btn as HTMLElement).dataset.date || '';
                    selectDay(dateStr);
                });
            });
        };

        const renderEventsList = () => {
            const container = document.getElementById('events-container');
            if (!container) return;

            // Filter events
            let filtered = [...allEvents];

            if (selectedType === 'day' && selectedDateStr) {
                filtered = eventsByDate[selectedDateStr] || [];
            } else if (selectedType === 'month' && selectedYear !== null && selectedMonthIndex !== null) {
                filtered = allEvents.filter(ev =>
                    ev.date.getFullYear() === selectedYear && ev.date.getMonth() === selectedMonthIndex
                );
            }

            // Sort by date ascending
            filtered.sort((a, b) => a.date.getTime() - b.date.getTime());

            if (filtered.length === 0) {
                container.innerHTML = `<p class="text-sm text-gray-500 py-4">Nu există evenimente care să corespundă filtrelor selectate.</p>`;
                return;
            }

            container.innerHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    ${filtered.map(event => `
                        <a href="/event/${event.slug}" class="group block rounded-xl border border-gray-200 bg-white overflow-hidden hover:border-gray-400 hover:shadow-md transition">
                            ${event.posterUrl ? `
                                <div class="aspect-[3/4] w-full overflow-hidden bg-gray-100">
                                    <img src="${event.posterUrl}" alt="${event.title}" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                                </div>
                            ` : `
                                <div class="aspect-[3/4] w-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                            `}
                            <div class="p-3">
                                <p class="font-semibold text-gray-900 text-sm line-clamp-2 leading-tight">${event.title}</p>
                                <p class="mt-1 text-xs text-gray-500 truncate">${event.venue}${event.city ? ` · ${event.city}` : ''}</p>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="text-xs text-gray-600">
                                        <span class="font-medium">${event.dateLabel}</span>
                                        <span class="text-gray-400 ml-1">${event.timeLabel}</span>
                                    </div>
                                    ${event.priceFrom ? `<span class="text-xs font-semibold text-gray-900">${event.priceFrom} RON</span>` : ''}
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    ${event.isCancelled ? `<span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700">Anulat</span>` : ''}
                                    ${!event.isCancelled && event.isPostponed ? `<span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">Amânat</span>` : ''}
                                    ${event.isSoldOut ? `<span class="inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-800">Sold-out</span>` : ''}
                                    ${event.doorSalesOnly ? `<span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700">Doar la intrare</span>` : ''}
                                </div>
                            </div>
                        </a>
                    `).join('')}
                </div>
            `;
        };

        // Selection handlers
        const selectDay = (dateStr: string) => {
            const day = days.find(d => d.dateStr === dateStr);
            if (!day) return;

            selectedType = 'day';
            selectedDateStr = dateStr;
            selectedYear = day.year;
            selectedMonthIndex = day.monthIndex;

            renderMonthNavigator();
            renderDaysStrip();
            renderEventsList();
        };

        const selectMonth = (year: number, monthIndex: number) => {
            selectedType = 'month';
            selectedYear = year;
            selectedMonthIndex = monthIndex;
            selectedDateStr = null;

            renderMonthNavigator();
            renderDaysStrip();
            renderEventsList();

            // Scroll to first day of month
            const firstDay = days.find(d => d.year === year && d.monthIndex === monthIndex);
            if (firstDay) {
                const scroller = document.getElementById('days-scroller');
                const el = scroller?.querySelector(`[data-date="${firstDay.dateStr}"]`);
                el?.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
            }
        };

        const scrollToToday = () => {
            const scroller = document.getElementById('days-scroller');
            const todayEl = scroller?.querySelector(`[data-date="${todayStr}"]`);
            if (todayEl && scroller) {
                scroller.scrollLeft = (todayEl as HTMLElement).offsetLeft - 24;
            }
        };

        // Initial render
        renderMonthNavigator();
        renderDaysStrip();
        renderEventsList();

        // Scroll to today after render
        setTimeout(scrollToToday, 100);

        // Enable drag-to-scroll on days scroller
        const scroller = document.getElementById('days-scroller');
        if (scroller) {
            // Hide webkit scrollbar
            const styleId = 'days-scroller-style';
            if (!document.getElementById(styleId)) {
                const style = document.createElement('style');
                style.id = styleId;
                style.textContent = '.days-scroller::-webkit-scrollbar { display: none; }';
                document.head.appendChild(style);
            }
            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;

            scroller.addEventListener('mousedown', (e) => {
                isDown = true;
                scroller.classList.add('active');
                startX = e.pageX - scroller.offsetLeft;
                scrollLeft = scroller.scrollLeft;
            });

            scroller.addEventListener('mouseleave', () => {
                isDown = false;
                scroller.classList.remove('active');
            });

            scroller.addEventListener('mouseup', () => {
                isDown = false;
                scroller.classList.remove('active');
            });

            scroller.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - scroller.offsetLeft;
                const walk = (x - startX) * 2; // Scroll speed multiplier
                scroller.scrollLeft = scrollLeft - walk;
            });

            // Touch support for mobile
            scroller.addEventListener('touchstart', (e) => {
                startX = e.touches[0].pageX - scroller.offsetLeft;
                scrollLeft = scroller.scrollLeft;
            }, { passive: true });

            scroller.addEventListener('touchmove', (e) => {
                const x = e.touches[0].pageX - scroller.offsetLeft;
                const walk = (x - startX) * 2;
                scroller.scrollLeft = scrollLeft - walk;
            }, { passive: true });
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

            // Use postponed date/time if event is postponed
            const isPostponed = event.is_postponed === true;
            const displayDate = isPostponed && event.postponed_date ? event.postponed_date : event.start_date;
            const displayStartTime = isPostponed && event.postponed_start_time ? event.postponed_start_time : event.start_time;
            const displayDoorTime = isPostponed && event.postponed_door_time ? event.postponed_door_time : event.door_time;
            const displayEndTime = isPostponed && event.postponed_end_time ? event.postponed_end_time : event.end_time;

            const date = displayDate ? new Date(displayDate).toLocaleDateString('ro-RO', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }) : '';

            const time = displayStartTime || (displayDate ? new Date(displayDate).toLocaleTimeString('ro-RO', {
                hour: '2-digit',
                minute: '2-digit'
            }) : '');

            // Use poster_url or hero_image_url from new API
            const imageUrl = event.poster_url || event.hero_image_url || event.image;

            // Check if event is in the past
            const eventDateObj = displayDate ? new Date(displayDate) : null;
            const isPastEvent = eventDateObj ? eventDateObj < new Date() : false;

            const eventDetailEl = document.getElementById('event-detail');
            if (eventDetailEl) {
                eventDetailEl.innerHTML = `
                    <div class="${isPastEvent ? 'lg:col-span-3' : 'lg:col-span-2'}">
                        ${imageUrl
                            ? `<img src="${imageUrl}" alt="${event.title}" class="w-full h-96 object-cover rounded-lg mb-6">`
                            : `<div class="w-full h-96 bg-gray-200 rounded-lg mb-6 flex items-center justify-center">
                                <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                              </div>`
                        }

                        ${!isPastEvent ? `<div id="countdown-container" data-event-date="${displayDate || ''}" data-event-time="${displayStartTime || ''}" data-is-cancelled="${event.is_cancelled || false}" data-is-postponed="${event.is_postponed || false}"></div>` : ''}

                        ${event.is_cancelled ? `
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <strong>Eveniment anulat:</strong> ${event.cancel_reason || 'Acest eveniment a fost anulat.'}
                        </div>
                        ` : ''}

                        ${isPostponed ? `
                        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                            <strong>Eveniment amânat${event.postponed_date ? ` pentru ${date}${displayStartTime ? ` ora ${displayStartTime}` : ''}` : ''}:</strong> ${event.postponed_reason || 'Acest eveniment a fost amânat.'}
                        </div>
                        ` : ''}

                        ${isPastEvent && !event.is_cancelled ? `
                        <div class="bg-gray-100 border border-gray-300 text-gray-700 px-4 py-3 rounded mb-4">
                            <strong>Eveniment încheiat</strong> - Acest eveniment a avut loc în ${date}.
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

                        ${event.description ? `
                        <div class="mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Descriere</h2>
                            <div class="prose prose-gray max-w-none text-gray-700 [&>p]:mb-4 [&>ul]:mb-4 [&>ol]:mb-4 [&>p:last-child]:mb-0">${event.description}</div>
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
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Artiști</h2>
                            <div class="space-y-8">
                                ${event.artists.map((artist: any) => {
                                    const formatNumber = (num: number | null | undefined): string => {
                                        if (!num) return '';
                                        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
                                        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
                                        return num.toString();
                                    };

                                    const hasStats = artist.youtube_subscribers || artist.youtube_total_views ||
                                                     artist.spotify_followers || artist.facebook_followers ||
                                                     artist.instagram_followers || artist.tiktok_followers;

                                    const hasSocial = artist.facebook_url || artist.instagram_url || artist.youtube_url ||
                                                      artist.spotify_url || artist.tiktok_url || artist.website;

                                    const latestVideo = artist.youtube_videos && artist.youtube_videos.length > 0
                                        ? artist.youtube_videos[0] : null;

                                    return `
                                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl overflow-hidden shadow-sm">
                                        <div class="p-6">
                                            <!-- Header: Image, Name, Types/Genres -->
                                            <div class="flex flex-col md:flex-row gap-6">
                                                <a href="https://core.tixello.com/artist/${artist.slug}?locale=en" target="_blank" class="flex-shrink-0 group">
                                                    ${artist.image || artist.portrait
                                                        ? `<img src="${artist.portrait || artist.image}" alt="${artist.name}" class="w-32 h-32 md:w-40 md:h-40 rounded-xl object-cover shadow-md group-hover:shadow-lg transition">`
                                                        : `<div class="w-32 h-32 md:w-40 md:h-40 rounded-xl bg-gradient-to-br from-primary to-purple-600 flex items-center justify-center">
                                                            <span class="text-4xl text-white font-bold">${artist.name.charAt(0)}</span>
                                                           </div>`
                                                    }
                                                </a>
                                                <div class="flex-1">
                                                    <a href="https://core.tixello.com/artist/${artist.slug}?locale=en" target="_blank" class="hover:text-primary transition">
                                                        <h3 class="text-2xl font-bold text-gray-900 mb-2">${artist.name}</h3>
                                                    </a>
                                                    ${artist.city || artist.country ? `
                                                        <p class="text-gray-500 mb-3">📍 ${[artist.city, artist.country].filter(Boolean).join(', ')}</p>
                                                    ` : ''}

                                                    ${artist.artist_types && artist.artist_types.length > 0 ? `
                                                        <div class="flex flex-wrap gap-2 mb-2">
                                                            ${artist.artist_types.map((t: any) => `
                                                                <span class="px-3 py-1 bg-primary/10 text-primary text-sm font-medium rounded-full">${t.name}</span>
                                                            `).join('')}
                                                        </div>
                                                    ` : ''}

                                                    ${artist.artist_genres && artist.artist_genres.length > 0 ? `
                                                        <div class="flex flex-wrap gap-2">
                                                            ${artist.artist_genres.map((g: any) => `
                                                                <span class="px-3 py-1 bg-gray-200 text-gray-700 text-sm rounded-full">${g.name}</span>
                                                            `).join('')}
                                                        </div>
                                                    ` : ''}

                                                    ${hasSocial ? `
                                                        <div class="flex flex-wrap gap-3 mt-4">
                                                            ${artist.spotify_url ? `<a href="${artist.spotify_url}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-[#1DB954] text-white rounded-full hover:scale-110 transition" title="Spotify"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg></a>` : ''}
                                                            ${artist.youtube_url ? `<a href="${artist.youtube_url}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-[#FF0000] text-white rounded-full hover:scale-110 transition" title="YouTube"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>` : ''}
                                                            ${artist.instagram_url ? `<a href="${artist.instagram_url}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-gradient-to-br from-[#833AB4] via-[#FD1D1D] to-[#FCAF45] text-white rounded-full hover:scale-110 transition" title="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>` : ''}
                                                            ${artist.facebook_url ? `<a href="${artist.facebook_url}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-[#1877F2] text-white rounded-full hover:scale-110 transition" title="Facebook"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>` : ''}
                                                            ${artist.tiktok_url ? `<a href="${artist.tiktok_url}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-black text-white rounded-full hover:scale-110 transition" title="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>` : ''}
                                                            ${artist.website ? `<a href="${artist.website}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-gray-700 text-white rounded-full hover:scale-110 transition" title="Website"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg></a>` : ''}
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>

                                            ${hasStats ? `
                                            <!-- Social Stats -->
                                            <div class="mt-6 pt-6 border-t border-gray-200">
                                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                                                    ${artist.spotify_followers ? `
                                                        <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                                                            <div class="text-[#1DB954] mb-1"><svg class="w-6 h-6 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg></div>
                                                            <div class="text-xl font-bold text-gray-900">${formatNumber(artist.spotify_followers)}</div>
                                                            <div class="text-xs text-gray-500">Followers</div>
                                                        </div>
                                                    ` : ''}
                                                    ${artist.spotify_monthly_listeners ? `
                                                        <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                                                            <div class="text-[#1DB954] mb-1"><svg class="w-6 h-6 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg></div>
                                                            <div class="text-xl font-bold text-gray-900">${formatNumber(artist.spotify_monthly_listeners)}</div>
                                                            <div class="text-xs text-gray-500">Monthly</div>
                                                        </div>
                                                    ` : ''}
                                                    ${artist.youtube_subscribers ? `
                                                        <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                                                            <div class="text-[#FF0000] mb-1"><svg class="w-6 h-6 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></div>
                                                            <div class="text-xl font-bold text-gray-900">${formatNumber(artist.youtube_subscribers)}</div>
                                                            <div class="text-xs text-gray-500">Subscribers</div>
                                                        </div>
                                                    ` : ''}
                                                    ${artist.youtube_total_views ? `
                                                        <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                                                            <div class="text-[#FF0000] mb-1"><svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></div>
                                                            <div class="text-xl font-bold text-gray-900">${formatNumber(artist.youtube_total_views)}</div>
                                                            <div class="text-xs text-gray-500">Views</div>
                                                        </div>
                                                    ` : ''}
                                                    ${artist.instagram_followers ? `
                                                        <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                                                            <div class="text-[#E4405F] mb-1"><svg class="w-6 h-6 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></div>
                                                            <div class="text-xl font-bold text-gray-900">${formatNumber(artist.instagram_followers)}</div>
                                                            <div class="text-xs text-gray-500">Followers</div>
                                                        </div>
                                                    ` : ''}
                                                    ${artist.facebook_followers ? `
                                                        <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                                                            <div class="text-[#1877F2] mb-1"><svg class="w-6 h-6 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></div>
                                                            <div class="text-xl font-bold text-gray-900">${formatNumber(artist.facebook_followers)}</div>
                                                            <div class="text-xs text-gray-500">Followers</div>
                                                        </div>
                                                    ` : ''}
                                                    ${artist.tiktok_followers ? `
                                                        <div class="text-center p-3 bg-white rounded-xl shadow-sm">
                                                            <div class="text-black mb-1"><svg class="w-6 h-6 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></div>
                                                            <div class="text-xl font-bold text-gray-900">${formatNumber(artist.tiktok_followers)}</div>
                                                            <div class="text-xs text-gray-500">Followers</div>
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                            ` : ''}

                                            ${artist.bio ? `
                                            <!-- Bio -->
                                            <div class="mt-6 pt-6 border-t border-gray-200">
                                                <div class="prose prose-sm max-w-none text-gray-600">${artist.bio}</div>
                                            </div>
                                            ` : ''}

                                            ${latestVideo && latestVideo.video_id ? `
                                            <!-- YouTube Video Embed -->
                                            <div class="mt-6 pt-6 border-t border-gray-200">
                                                <h4 class="text-lg font-semibold text-gray-900 mb-4">Ultimul videoclip</h4>
                                                <div class="relative pb-[56.25%] h-0 rounded-xl overflow-hidden shadow-lg">
                                                    <iframe
                                                        class="absolute top-0 left-0 w-full h-full"
                                                        src="https://www.youtube.com/embed/${latestVideo.video_id}"
                                                        title="${latestVideo.title || 'YouTube video'}"
                                                        frameborder="0"
                                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                        allowfullscreen>
                                                    </iframe>
                                                </div>
                                                ${latestVideo.title ? `<p class="mt-2 text-sm text-gray-600">${latestVideo.title}</p>` : ''}
                                            </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                `}).join('')}
                            </div>
                        </div>
                        ` : ''}

                        ${event.venue ? `
                        <div class="mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Locație</h2>
                            <div class="bg-gray-50 rounded-lg overflow-hidden">
                                ${event.venue.image_url ? `
                                <img src="${event.venue.image_url}" alt="${event.venue.name}" class="w-full h-48 object-cover">
                                ` : ''}
                                <div class="p-4">
                                    <h3 class="font-semibold text-lg text-gray-900 mb-2">${event.venue.name}</h3>

                                    ${event.venue.address || event.venue.city || event.venue.state || event.venue.country ? `
                                    <div class="flex items-start mb-3">
                                        <svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <div class="text-gray-600">
                                            ${event.venue.address ? `<div>${event.venue.address}</div>` : ''}
                                            ${event.venue.city || event.venue.state || event.venue.country ? `
                                            <div>${[event.venue.city, event.venue.state, event.venue.country].filter(Boolean).join(', ')}</div>
                                            ` : ''}
                                        </div>
                                    </div>
                                    ` : ''}

                                    ${event.venue.phone || event.venue.phone2 ? `
                                    <div class="flex items-center mb-3">
                                        <svg class="w-5 h-5 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                        <div class="text-gray-600">
                                            ${event.venue.phone ? `<a href="tel:${event.venue.phone}" class="hover:text-primary">${event.venue.phone}</a>` : ''}
                                            ${event.venue.phone && event.venue.phone2 ? ' / ' : ''}
                                            ${event.venue.phone2 ? `<a href="tel:${event.venue.phone2}" class="hover:text-primary">${event.venue.phone2}</a>` : ''}
                                        </div>
                                    </div>
                                    ` : ''}

                                    ${event.venue.email || event.venue.email2 ? `
                                    <div class="flex items-center mb-3">
                                        <svg class="w-5 h-5 text-gray-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <div class="text-gray-600">
                                            ${event.venue.email ? `<a href="mailto:${event.venue.email}" class="hover:text-primary">${event.venue.email}</a>` : ''}
                                            ${event.venue.email && event.venue.email2 ? ' / ' : ''}
                                            ${event.venue.email2 ? `<a href="mailto:${event.venue.email2}" class="hover:text-primary">${event.venue.email2}</a>` : ''}
                                        </div>
                                    </div>
                                    ` : ''}

                                    <div class="flex flex-wrap gap-3 mt-4">
                                        ${event.venue.website_url ? `
                                        <a href="${event.venue.website_url}" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-gray-200 hover:bg-gray-300 rounded text-sm text-gray-700 transition">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                            </svg>
                                            Website
                                        </a>
                                        ` : ''}
                                        ${event.venue.google_maps_url ? `
                                        <a href="${event.venue.google_maps_url}" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 rounded text-sm text-blue-700 transition">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                            </svg>
                                            Google Maps
                                        </a>
                                        ` : event.venue.latitude && event.venue.longitude ? `
                                        <a href="https://www.google.com/maps?q=${event.venue.latitude},${event.venue.longitude}" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 rounded text-sm text-blue-700 transition">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                            </svg>
                                            Google Maps
                                        </a>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>

                    ${!isPastEvent ? `
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

                            ${event.ticket_types && event.ticket_types.length > 0 && !event.is_cancelled && !event.door_sales_only && !event.is_sold_out ? `
                                <div class="space-y-4 mb-6">
                                    ${event.ticket_types.map((ticket: any) => {
                                        const currency = ticket.currency || event.currency || 'RON';
                                        const available = ticket.available ?? 0;
                                        const maxQty = Math.min(10, available);
                                        const commissionInfo = event.commission;
                                        const hasCommissionOnTop = commissionInfo?.is_added_on_top && ticket.commission_amount > 0;
                                        return `
                                        <div class="border border-gray-200 rounded-lg p-4 ${ticket.status !== 'active' ? 'opacity-50' : ''}">
                                            <div class="flex justify-between items-start mb-2">
                                                <div class="flex items-center gap-2">
                                                    <h3 class="font-semibold text-gray-900">${ticket.name}</h3>
                                                    ${hasCommissionOnTop ? `
                                                    <div class="relative group">
                                                        <svg class="w-4 h-4 text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-800 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                                                            Prețul include comision Tixello de ${ticket.commission_amount} ${currency}
                                                        </div>
                                                    </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                            ${ticket.description ? `<p class="text-sm text-gray-500 mb-2">${ticket.description}</p>` : ''}
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
                                                    ${hasCommissionOnTop ? `
                                                        <div>
                                                            ${ticket.sale_price ? `
                                                                <span class="line-through text-gray-400 text-sm">${ticket.price} ${currency}</span>
                                                                <span class="font-bold text-primary block">${(parseFloat(ticket.sale_price) + parseFloat(ticket.commission_amount || 0)).toFixed(2)} ${currency}</span>
                                                                <span class="text-xs text-gray-500">(${ticket.sale_price} + ${ticket.commission_amount} comision)</span>
                                                            ` : `
                                                                <span class="font-bold text-primary">${(parseFloat(ticket.price) + parseFloat(ticket.commission_amount || 0)).toFixed(2)} ${currency}</span>
                                                                <span class="text-xs text-gray-500 block">(${ticket.price} + ${ticket.commission_amount} comision)</span>
                                                            `}
                                                        </div>
                                                    ` : ticket.sale_price ? `
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
                                                    <span class="ticket-qty-display w-12 text-center font-semibold" data-ticket-id="${ticket.id}" data-price="${ticket.sale_price || ticket.price}" data-base-price="${ticket.price}" data-currency="${currency}" data-bulk-discounts='${JSON.stringify(ticket.bulk_discounts || [])}' data-commission-rate="${commissionInfo?.rate || 0}" data-has-commission-on-top="${hasCommissionOnTop}">0</span>
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
                                        <span id="cart-total-price">0 ${event.currency || 'RON'}</span>
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
                                <p class="text-center text-xs text-gray-400 mt-4">
                                    Ticketing system powered by <a href="https://tixello.com" target="_blank" class="text-primary hover:underline">Tixello</a>
                                </p>
                            ` : `
                                <p class="text-gray-500 text-center py-4">Nu sunt bilete disponibile pentru achiziție online.</p>
                            `}
                        </div>
                    </div>
                    ` : ''}
                `;

                // Setup ticket quantity handlers
                if (!isPastEvent) {
                    this.setupTicketHandlers();
                }

                // Initialize countdown timer
                this.initCountdown();
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
            let totalCommission = 0;
            let hasSelection = false;
            let currency = 'RON';
            let discountInfos: string[] = [];
            let hasCommissionOnTop = false;

            qtyDisplays.forEach((display) => {
                const ticketId = (display as HTMLElement).dataset.ticketId || '';
                const qty = quantities[ticketId] || 0;
                const price = parseFloat((display as HTMLElement).dataset.price || '0');
                const basePrice = parseFloat((display as HTMLElement).dataset.basePrice || '0');
                const ticketCurrency = (display as HTMLElement).dataset.currency || 'RON';
                const commissionRate = parseFloat((display as HTMLElement).dataset.commissionRate || '0');
                const ticketHasCommission = (display as HTMLElement).dataset.hasCommissionOnTop === 'true';
                const discounts = ticketBulkDiscounts[ticketId] || [];

                if (qty > 0) {
                    const result = calculateBulkDiscount(qty, price, discounts);
                    total += result.total;
                    totalDiscount += result.discount;
                    if (result.info) discountInfos.push(result.info);
                    hasSelection = true;
                    currency = ticketCurrency;

                    // Commission is calculated from BASE price × quantity, not affected by discounts
                    if (ticketHasCommission && commissionRate > 0) {
                        const commission = qty * basePrice * (commissionRate / 100);
                        totalCommission += commission;
                        hasCommissionOnTop = true;
                    }
                }
            });

            // Add commission to total if applicable
            const finalTotal = total + totalCommission;

            if (totalEl) {
                if (totalDiscount > 0 && hasCommissionOnTop) {
                    // Has both discount and commission
                    const originalTotal = total + totalDiscount;
                    totalEl.innerHTML = `
                        <div class="text-sm text-gray-500 line-through">${originalTotal.toFixed(2)} ${currency}</div>
                        <div class="text-sm text-green-600">-${totalDiscount.toFixed(2)} ${currency} reducere</div>
                        <div class="text-sm text-gray-500">+${totalCommission.toFixed(2)} ${currency} comision</div>
                        <div class="text-lg font-bold text-primary">${finalTotal.toFixed(2)} ${currency}</div>
                    `;
                } else if (totalDiscount > 0) {
                    // Only discount, no commission
                    const originalTotal = total + totalDiscount;
                    totalEl.innerHTML = `
                        <div class="text-sm text-gray-500 line-through">${originalTotal.toFixed(2)} ${currency}</div>
                        <div class="text-lg font-bold text-green-600">${total.toFixed(2)} ${currency}</div>
                        <div class="text-xs text-green-600">Economisești ${totalDiscount.toFixed(2)} ${currency}</div>
                    `;
                } else if (hasCommissionOnTop) {
                    // Only commission, no discount
                    totalEl.innerHTML = `
                        <div class="text-sm text-gray-500">${total.toFixed(2)} + ${totalCommission.toFixed(2)} comision</div>
                        <div class="text-lg font-bold text-primary">${finalTotal.toFixed(2)} ${currency}</div>
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
                        const commissionInfo = eventData.commission;
                        const hasCommissionOnTop = commissionInfo?.is_added_on_top && ticketType.commission_amount > 0;
                        if (ticketType) {
                            CartService.addItem({
                                eventId: eventData.id,
                                eventTitle: eventData.title,
                                eventSlug: eventData.slug,
                                eventDate: eventData.start_date,
                                ticketTypeId: ticketType.id,
                                ticketTypeName: ticketType.name,
                                price: ticketType.price,  // Base price
                                salePrice: ticketType.sale_price,  // Discounted price
                                finalPrice: ticketType.final_price || ticketType.sale_price || ticketType.price,
                                commissionAmount: ticketType.commission_amount || 0,
                                commissionRate: commissionInfo?.rate || 0,
                                hasCommissionOnTop: hasCommissionOnTop,
                                quantity: qty,
                                currency: ticketType.currency || 'RON',
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
                    <a href="/events" class="px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition inline-block">
                        Vezi evenimente
                    </a>
                </div>
            `;
            return;
        }

        const cartItemsHtml = cart.map((item, index) => {
            // Use sale price if available, otherwise base price (same as getTotal)
            const ticketPrice = item.salePrice || item.price;
            const result = CartService.calculateBulkDiscount(item.quantity, ticketPrice, item.bulkDiscounts);
            let itemTotal = result.total;
            const itemDiscount = result.discount;
            let originalTotal = item.quantity * ticketPrice;

            // Add commission if applicable (from BASE price, not affected by discounts/sale price)
            let itemCommission = 0;
            if (item.hasCommissionOnTop && item.commissionRate > 0) {
                itemCommission = item.quantity * item.price * (item.commissionRate / 100);
                itemTotal += itemCommission;
                originalTotal += itemCommission;
            }
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
                                    <span>Subtotal bilete</span>
                                    <span>${totals.subtotal.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ${totals.discount > 0 ? `
                                <div class="flex justify-between text-green-600">
                                    <span>Discount bulk</span>
                                    <span>-${totals.discount.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ` : ''}
                                ${totals.hasCommission ? `
                                <div class="flex justify-between text-gray-600">
                                    <span>Comision Tixello</span>
                                    <span>+${totals.commission.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ` : ''}
                            </div>

                            <!-- Discount Code Section -->
                            <div class="mb-4 pb-4 border-b">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cod de reducere</label>
                                <div class="flex gap-2">
                                    <input type="text" id="discount-code-input" placeholder="Introdu codul"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-sm">
                                    <button id="apply-discount-btn" class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition">
                                        Aplică
                                    </button>
                                </div>
                                <div id="discount-code-message" class="mt-2 text-sm hidden"></div>
                                <div id="applied-discount-display" class="hidden mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="text-sm font-medium text-green-800" id="applied-discount-name"></span>
                                            <span class="text-sm text-green-600 ml-2" id="applied-discount-value"></span>
                                        </div>
                                        <button id="remove-discount-btn" class="text-red-600 hover:text-red-700 text-sm">Elimină</button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between items-center mb-6">
                                <span class="text-lg font-semibold">Total</span>
                                <span class="text-2xl font-bold text-primary" id="cart-total-amount">${totals.total.toFixed(2)} ${totals.currency}</span>
                            </div>

                            <button id="checkout-btn" class="w-full py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition">
                                Finalizează comanda
                            </button>

                            <a href="/events" class="w-full mt-3 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition block text-center">
                                Continuă cumpărăturile
                            </a>

                            <p class="text-center text-xs text-gray-400 mt-4">
                                Ticketing system powered by <a href="https://tixello.com" target="_blank" class="text-primary hover:underline">Tixello</a>
                            </p>
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

        // Discount code handlers
        this.setupDiscountCodeHandlers();
    }

    private setupDiscountCodeHandlers(): void {
        const applyBtn = document.getElementById('apply-discount-btn');
        const input = document.getElementById('discount-code-input') as HTMLInputElement;
        const messageDiv = document.getElementById('discount-code-message');
        const appliedDisplay = document.getElementById('applied-discount-display');
        const removeBtn = document.getElementById('remove-discount-btn');

        if (applyBtn && input) {
            applyBtn.addEventListener('click', async () => {
                const code = input.value.trim();
                if (!code) {
                    this.showDiscountMessage('Te rugăm să introduci un cod de reducere.', 'error');
                    return;
                }

                applyBtn.textContent = 'Se verifică...';
                (applyBtn as HTMLButtonElement).disabled = true;

                try {
                    const response = await this.fetchApi('/discount/validate', {
                        method: 'POST',
                        body: JSON.stringify({
                            code: code,
                            cart: CartService.getCart()
                        })
                    });

                    if (response.success && response.data?.valid) {
                        // Store discount in CartService
                        CartService.setDiscount({
                            code: code,
                            name: response.data.name || code,
                            type: response.data.discount_type,
                            value: response.data.discount_value,
                            discountAmount: response.data.discount_amount
                        });

                        // Show applied discount
                        const nameSpan = document.getElementById('applied-discount-name');
                        const valueSpan = document.getElementById('applied-discount-value');
                        if (nameSpan) nameSpan.textContent = response.data.name || code;
                        if (valueSpan) {
                            valueSpan.textContent = response.data.discount_type === 'percentage'
                                ? `(-${response.data.discount_value}%)`
                                : `(-${response.data.discount_amount} ${CartService.getTotal().currency})`;
                        }
                        if (appliedDisplay) appliedDisplay.classList.remove('hidden');
                        if (messageDiv) messageDiv.classList.add('hidden');
                        input.value = '';
                        input.disabled = true;

                        // Update total display
                        this.updateCartTotal();
                    } else {
                        this.showDiscountMessage(response.message || 'Cod invalid sau expirat.', 'error');
                    }
                } catch (error) {
                    console.error('Discount validation error:', error);
                    this.showDiscountMessage('A apărut o eroare. Te rugăm să încerci din nou.', 'error');
                } finally {
                    applyBtn.textContent = 'Aplică';
                    (applyBtn as HTMLButtonElement).disabled = false;
                }
            });

            // Allow Enter key to apply
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyBtn.click();
                }
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                CartService.removeDiscount();
                if (appliedDisplay) appliedDisplay.classList.add('hidden');
                if (input) {
                    input.disabled = false;
                    input.value = '';
                }
                this.updateCartTotal();
            });
        }

        // Check if there's already an applied discount
        const existingDiscount = CartService.getDiscount();
        if (existingDiscount && appliedDisplay) {
            const nameSpan = document.getElementById('applied-discount-name');
            const valueSpan = document.getElementById('applied-discount-value');
            if (nameSpan) nameSpan.textContent = existingDiscount.name;
            if (valueSpan) {
                valueSpan.textContent = existingDiscount.type === 'percentage'
                    ? `(-${existingDiscount.value}%)`
                    : `(-${existingDiscount.discountAmount} ${CartService.getTotal().currency})`;
            }
            appliedDisplay.classList.remove('hidden');
            if (input) input.disabled = true;
        }
    }

    private showDiscountMessage(message: string, type: 'success' | 'error'): void {
        const messageDiv = document.getElementById('discount-code-message');
        if (messageDiv) {
            messageDiv.textContent = message;
            messageDiv.className = `mt-2 text-sm ${type === 'error' ? 'text-red-600' : 'text-green-600'}`;
            messageDiv.classList.remove('hidden');
        }
    }

    private updateCartTotal(): void {
        const totals = CartService.getTotal();
        const totalEl = document.getElementById('cart-total-amount');
        if (totalEl) {
            totalEl.textContent = `${totals.total.toFixed(2)} ${totals.currency}`;
        }
    }

    private async renderCheckout(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        const cart = CartService.getCart();
        const totals = CartService.getTotal();

        if (cart.length === 0) {
            this.navigate('/cart');
            return;
        }

        // Fetch user profile if logged in
        let userData: any = null;
        if (this.authToken) {
            try {
                const profileResponse = await this.fetchApi('/account/profile');
                if (profileResponse.success) {
                    userData = profileResponse.data;
                }
            } catch (error) {
                console.log('Could not fetch user profile:', error);
            }
        }

        // Prepare pre-filled values
        const customerName = userData ? `${userData.first_name || ''} ${userData.last_name || ''}`.trim() : '';
        const customerEmail = userData?.email || '';
        const customerPhone = userData?.phone || '';

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
                                            value="${customerName}"
                                            value="${customerName}"
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
                                            value="${customerEmail}"
                                            value="${customerEmail}"
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
                                            value="${customerPhone}"
                                            value="${customerPhone}"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Beneficiari bilete</h2>
                                <p class="text-sm text-gray-600 mb-4">
                                    Implicit, toate biletele vor fi pe numele tău. Poți specifica beneficiari diferiți pentru fiecare bilet.
                                </p>
                                <div class="flex items-start mb-4">
                                    <input
                                        type="checkbox"
                                        id="different_beneficiaries"
                                        name="different_beneficiaries"
                                        class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    >
                                    <label for="different_beneficiaries" class="ml-2 text-sm text-gray-700">
                                        Doresc să specific beneficiari diferiți pentru fiecare bilet
                                    </label>
                                </div>
                                <div id="beneficiaries-section" class="hidden">
                                    <div class="border-t pt-4">
                                        <p class="text-sm text-gray-600 mb-4">
                                            Completează datele pentru fiecare bilet. Primul bilet va fi pre-completat cu datele tale.
                                        </p>
                                        <div id="beneficiaries-container" class="space-y-4">
                                            <!-- Beneficiary fields will be generated dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Acorduri</h2>
                                <div class="space-y-3 mb-6">
                                    <div class="flex items-start">
                                        <input
                                            type="checkbox"
                                            id="agree_terms"
                                            name="agree_terms"
                                            required
                                            class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        >
                                        <label for="agree_terms" class="ml-2 text-sm text-gray-700">
                                            Sunt de acord cu <a href="/terms" target="_blank" class="text-primary hover:underline">Termenii și Condițiile</a> *
                                        </label>
                                    </div>
                                    <div class="flex items-start">
                                        <input
                                            type="checkbox"
                                            id="agree_privacy"
                                            name="agree_privacy"
                                            required
                                            class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        >
                                        <label for="agree_privacy" class="ml-2 text-sm text-gray-700">
                                            Sunt de acord cu <a href="/privacy" target="_blank" class="text-primary hover:underline">Politica de Confidențialitate</a> a organizatorului, <a href="https://tixello.com/data-privacy-client" target="_blank" class="text-primary hover:underline">Politica Tixello</a> și procesarea datelor personale *
                                        </label>
                                    </div>
                                    ${!this.authToken ? `
                                    <div class="flex items-start">
                                        <input
                                            type="checkbox"
                                            id="create_account"
                                            name="create_account"
                                            class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        >
                                        <label for="create_account" class="ml-2 text-sm text-gray-700">
                                            Doresc să îmi creez un cont automat pentru comenzi viitoare
                                        </label>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>

                            <div class="bg-white rounded-lg shadow p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-4">Preferințe notificări</h2>
                                <p class="text-sm text-gray-600 mb-4">Alegeți cum doriți să primiți actualizări despre comandă:</p>
                                <div class="space-y-3">
                                    <div class="flex items-start">
                                        <input
                                            type="checkbox"
                                            id="notification_email"
                                            name="notification_email"
                                            checked
                                            class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        >
                                        <label for="notification_email" class="ml-2 text-sm text-gray-700">
                                            Primește notificări pe email (confirmare comandă, bilete, reamintiri)
                                        </label>
                                    </div>
                                    ${this.config?.modules?.includes('whatsapp-notifications') ? `
                                    <div class="flex items-start">
                                        <input
                                            type="checkbox"
                                            id="notification_whatsapp"
                                            name="notification_whatsapp"
                                            class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        >
                                        <label for="notification_whatsapp" class="ml-2 text-sm text-gray-700">
                                            Primește notificări pe WhatsApp (necesită număr de telefon valid)
                                        </label>
                                    </div>
                                    ` : ''}
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
                                <p class="text-center text-xs text-gray-400 mt-4">
                                    Ticketing system powered by <a href="https://tixello.com" target="_blank" class="text-primary hover:underline">Tixello</a>
                                </p>
                            </div>
                        </form>
                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Sumar comandă</h2>

                            <div class="space-y-3 mb-4 pb-4 border-b">
                                ${cart.map(item => {
                                    // Use sale price if available, otherwise base price (same as getTotal)
                                    const ticketPrice = item.salePrice || item.price;
                                    const result = CartService.calculateBulkDiscount(item.quantity, ticketPrice, item.bulkDiscounts);
                                    let itemTotal = result.total;
                                    // Add commission if applicable (from BASE price)
                                    if (item.hasCommissionOnTop && item.commissionRate > 0) {
                                        const commission = item.quantity * item.price * (item.commissionRate / 100);
                                        itemTotal += commission;
                                    }
                                    return `
                                    <div class="flex justify-between text-sm">
                                        <div>
                                            <div class="font-medium">${item.eventTitle}</div>
                                            <div class="text-gray-500">${item.ticketTypeName} × ${item.quantity}</div>
                                        </div>
                                        <div class="font-medium">${itemTotal.toFixed(2)} ${item.currency}</div>
                                    </div>
                                `}).join('')}
                            </div>

                            <div class="space-y-2 mb-4 pb-4 border-b">
                                <div class="flex justify-between text-gray-600">
                                    <span>Subtotal bilete</span>
                                    <span>${totals.subtotal.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ${totals.discount > 0 ? `
                                <div class="flex justify-between text-green-600">
                                    <span>Discount</span>
                                    <span>-${totals.discount.toFixed(2)} ${totals.currency}</span>
                                </div>
                                ` : ''}
                                ${totals.hasCommission ? `
                                <div class="flex justify-between text-gray-600">
                                    <span>Comision Tixello</span>
                                    <span>+${totals.commission.toFixed(2)} ${totals.currency}</span>
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

        // Handle beneficiaries checkbox toggle
        const beneficiariesCheckbox = document.getElementById('different_beneficiaries') as HTMLInputElement;
        const beneficiariesSection = document.getElementById('beneficiaries-section');
        const beneficiariesContainer = document.getElementById('beneficiaries-container');
        const cart = CartService.getCart();
        const totalTickets = cart.reduce((sum, item) => sum + item.quantity, 0);

        if (beneficiariesCheckbox && beneficiariesSection && beneficiariesContainer) {
            beneficiariesCheckbox.addEventListener('change', () => {
                if (beneficiariesCheckbox.checked) {
                    beneficiariesSection.classList.remove('hidden');

                    // Generate beneficiary fields
                    const customerName = (document.getElementById('customer_name') as HTMLInputElement)?.value || '';
                    const customerEmail = (document.getElementById('customer_email') as HTMLInputElement)?.value || '';
                    const customerPhone = (document.getElementById('customer_phone') as HTMLInputElement)?.value || '';

                    let html = '';
                    for (let i = 0; i < totalTickets; i++) {
                        const isFirst = i === 0;
                        html += `
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-900 mb-3">Bilet ${i + 1}</h4>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Nume complet *
                                        </label>
                                        <input
                                            type="text"
                                            name="beneficiary_${i}_name"
                                            required
                                            value="${isFirst ? customerName : ''}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                                            placeholder="Nume complet"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Email (optional)
                                        </label>
                                        <input
                                            type="email"
                                            name="beneficiary_${i}_email"
                                            value="${isFirst ? customerEmail : ''}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                                            placeholder="email@example.com"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Telefon (optional)
                                        </label>
                                        <input
                                            type="tel"
                                            name="beneficiary_${i}_phone"
                                            value="${isFirst ? customerPhone : ''}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm"
                                            placeholder="0722123456"
                                        >
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    beneficiariesContainer.innerHTML = html;
                } else {
                    beneficiariesSection.classList.add('hidden');
                    beneficiariesContainer.innerHTML = '';
                }
            });
        }

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Se procesează...';
                }

                const formData = new FormData(form);
                const cart = CartService.getCart();

                // Collect beneficiaries data if checkbox is checked
                const beneficiariesData: any[] = [];
                const beneficiariesCheckbox = document.getElementById('different_beneficiaries') as HTMLInputElement;
                if (beneficiariesCheckbox?.checked) {
                    const totalTickets = cart.reduce((sum, item) => sum + item.quantity, 0);
                    for (let i = 0; i < totalTickets; i++) {
                        beneficiariesData.push({
                            name: formData.get(`beneficiary_${i}_name`),
                            email: formData.get(`beneficiary_${i}_email`) || null,
                            phone: formData.get(`beneficiary_${i}_phone`) || null,
                        });
                    }
                }

                // Get checkbox values
                const agreeTerms = (document.getElementById('agree_terms') as HTMLInputElement)?.checked ?? false;
                const agreePrivacy = (document.getElementById('agree_privacy') as HTMLInputElement)?.checked ?? false;
                const createAccount = (document.getElementById('create_account') as HTMLInputElement)?.checked ?? false;
                const notificationEmail = (document.getElementById('notification_email') as HTMLInputElement)?.checked ?? true;
                const notificationWhatsapp = (document.getElementById('notification_whatsapp') as HTMLInputElement)?.checked ?? false;

                try {
                    const response = await this.postApi('/orders', {
                        customer_name: formData.get('customer_name'),
                        customer_email: formData.get('customer_email'),
                        customer_phone: formData.get('customer_phone'),
                        agree_terms: agreeTerms,
                        agree_privacy: agreePrivacy,
                        create_account: createAccount,
                        notification_email: notificationEmail,
                        notification_whatsapp: notificationWhatsapp,
                        cart: cart.map(item => ({
                            eventId: item.eventId,
                            ticketTypeId: item.ticketTypeId,
                            quantity: item.quantity,
                        })),
                        beneficiaries: beneficiariesData.length > 0 ? beneficiariesData : null,
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
                        Comanda ta <strong>#${orderId}</strong> a fost înregistrată.
                    </p>
                    <p class="text-gray-600">
                        Vei primi biletele pe email în câteva minute.
                    </p>
                </div>

                <div class="space-y-3">
                    <a href="/events" class="w-full max-w-xs mx-auto block px-6 py-3 bg-primary text-white font-semibold rounded-lg hover:bg-primary-dark transition text-center">
                        Înapoi la evenimente
                    </a>
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
                            <div class="flex justify-between items-center mb-1">
                                <label for="password" class="block text-sm font-medium text-gray-700">Parolă</label>
                                <a href="/forgot-password" class="text-xs text-primary hover:text-blue-700">Ai uitat parola?</a>
                            </div>
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
                            <!-- Password Strength Meter -->
                            <div id="password-strength" class="mt-2">
                                <div class="flex gap-1 mb-1">
                                    <div id="strength-bar-1" class="h-1 flex-1 rounded bg-gray-200"></div>
                                    <div id="strength-bar-2" class="h-1 flex-1 rounded bg-gray-200"></div>
                                    <div id="strength-bar-3" class="h-1 flex-1 rounded bg-gray-200"></div>
                                    <div id="strength-bar-4" class="h-1 flex-1 rounded bg-gray-200"></div>
                                </div>
                                <p id="strength-text" class="text-xs text-gray-500"></p>
                            </div>
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmă parola</label>
                            <input type="password" id="password_confirmation" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <p id="password-match" class="mt-1 text-xs hidden"></p>
                        </div>
                        <div class="border-t pt-4">
                            <p class="text-sm font-medium text-gray-700 mb-3">Preferințe notificări</p>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="reg_notification_email" checked
                                           class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                    <label for="reg_notification_email" class="ml-2 text-sm text-gray-700">
                                        Primește notificări pe email
                                    </label>
                                </div>
                                ${this.config?.modules?.includes('whatsapp-notifications') ? `
                                <div class="flex items-center">
                                    <input type="checkbox" id="reg_notification_whatsapp"
                                           class="h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary">
                                    <label for="reg_notification_whatsapp" class="ml-2 text-sm text-gray-700">
                                        Primește notificări pe WhatsApp
                                    </label>
                                </div>
                                ` : ''}
                            </div>
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

        // Password strength meter
        const passwordInput = document.getElementById('password') as HTMLInputElement;
        const confirmInput = document.getElementById('password_confirmation') as HTMLInputElement;
        const strengthText = document.getElementById('strength-text');
        const matchText = document.getElementById('password-match');
        const bars = [
            document.getElementById('strength-bar-1'),
            document.getElementById('strength-bar-2'),
            document.getElementById('strength-bar-3'),
            document.getElementById('strength-bar-4'),
        ];

        const checkPasswordStrength = (password: string): { score: number; text: string; color: string } => {
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            if (score <= 2) return { score: 1, text: 'Foarte slabă', color: 'bg-red-500' };
            if (score <= 3) return { score: 2, text: 'Slabă', color: 'bg-orange-500' };
            if (score <= 4) return { score: 3, text: 'Medie', color: 'bg-yellow-500' };
            if (score <= 5) return { score: 4, text: 'Puternică', color: 'bg-green-500' };
            return { score: 4, text: 'Foarte puternică', color: 'bg-green-600' };
        };

        const updateStrengthMeter = () => {
            const password = passwordInput?.value || '';
            if (!password) {
                bars.forEach(bar => bar?.classList.replace(bar.className.split(' ').find(c => c.startsWith('bg-')) || 'bg-gray-200', 'bg-gray-200'));
                if (strengthText) strengthText.textContent = '';
                return;
            }

            const { score, text, color } = checkPasswordStrength(password);
            bars.forEach((bar, i) => {
                if (bar) {
                    const currentBg = Array.from(bar.classList).find(c => c.startsWith('bg-'));
                    if (currentBg) bar.classList.remove(currentBg);
                    bar.classList.add(i < score ? color : 'bg-gray-200');
                }
            });
            if (strengthText) {
                strengthText.textContent = text;
                strengthText.className = `text-xs ${color.replace('bg-', 'text-')}`;
            }
        };

        const checkPasswordMatch = () => {
            const password = passwordInput?.value || '';
            const confirm = confirmInput?.value || '';
            if (!confirm) {
                matchText?.classList.add('hidden');
                confirmInput?.classList.remove('border-red-300', 'border-green-300');
                return;
            }

            matchText?.classList.remove('hidden');
            if (password === confirm) {
                if (matchText) {
                    matchText.textContent = '✓ Parolele coincid';
                    matchText.className = 'mt-1 text-xs text-green-600';
                }
                confirmInput?.classList.remove('border-red-300');
                confirmInput?.classList.add('border-green-300');
            } else {
                if (matchText) {
                    matchText.textContent = '✗ Parolele nu coincid';
                    matchText.className = 'mt-1 text-xs text-red-600';
                }
                confirmInput?.classList.remove('border-green-300');
                confirmInput?.classList.add('border-red-300');
            }
        };

        passwordInput?.addEventListener('input', () => {
            updateStrengthMeter();
            checkPasswordMatch();
        });
        confirmInput?.addEventListener('input', checkPasswordMatch);

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

            const notificationEmail = (document.getElementById('reg_notification_email') as HTMLInputElement)?.checked ?? true;
            const notificationWhatsapp = (document.getElementById('reg_notification_whatsapp') as HTMLInputElement)?.checked ?? false;

            try {
                const result = await this.postApi('/auth/register', {
                    first_name,
                    last_name,
                    email,
                    phone: phone || null,
                    password,
                    notification_email: notificationEmail,
                    notification_whatsapp: notificationWhatsapp,
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

    private renderForgotPassword(): void {
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
                        <h1 class="text-3xl font-bold text-gray-900">Resetează parola</h1>
                        <p class="mt-2 text-gray-600">Introdu adresa de email pentru a primi un link de resetare</p>
                    </div>
                    <div id="forgot-success" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg"></div>
                    <div id="forgot-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"></div>
                    <form id="forgot-form" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <button type="submit" id="forgot-btn" class="w-full px-4 py-2 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition">
                            Trimite link de resetare
                        </button>
                    </form>
                    <p class="text-center text-gray-600">
                        <a href="/login" class="text-primary hover:text-blue-700 font-medium">← Înapoi la conectare</a>
                    </p>
                </div>
            </div>
        `;

        const form = document.getElementById('forgot-form');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = (document.getElementById('email') as HTMLInputElement).value;
            const errorEl = document.getElementById('forgot-error');
            const successEl = document.getElementById('forgot-success');
            const btn = document.getElementById('forgot-btn') as HTMLButtonElement;

            btn.disabled = true;
            btn.textContent = 'Se trimite...';
            errorEl?.classList.add('hidden');
            successEl?.classList.add('hidden');

            try {
                const result = await this.postApi('/auth/forgot-password', { email });
                if (result.success) {
                    if (successEl) {
                        successEl.textContent = 'Dacă adresa de email există în sistem, vei primi un link de resetare.';
                        successEl.classList.remove('hidden');
                    }
                    (document.getElementById('email') as HTMLInputElement).value = '';
                } else {
                    throw new Error(result.message || 'Eroare la trimitere');
                }
            } catch (error: any) {
                console.error('Forgot password error:', error);
                if (errorEl) {
                    errorEl.textContent = error.message || 'Eroare la trimiterea emailului';
                    errorEl.classList.remove('hidden');
                }
            } finally {
                btn.disabled = false;
                btn.textContent = 'Trimite link de resetare';
            }
        });
    }

    private renderResetPassword(): void {
        // Redirect if already logged in
        if (this.isAuthenticated()) {
            this.navigate('/account');
            return;
        }

        const content = this.getContentElement();
        if (!content) return;

        // Get token from URL
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        const email = urlParams.get('email');

        if (!token || !email) {
            content.innerHTML = `
                <div class="min-h-[60vh] flex items-center justify-center px-4">
                    <div class="text-center">
                        <h1 class="text-2xl font-bold text-red-600 mb-4">Link invalid</h1>
                        <p class="text-gray-600 mb-4">Linkul de resetare este invalid sau a expirat.</p>
                        <a href="/forgot-password" class="text-primary hover:text-blue-700">Solicită un nou link</a>
                    </div>
                </div>
            `;
            return;
        }

        content.innerHTML = `
            <div class="min-h-[60vh] flex items-center justify-center px-4">
                <div class="max-w-md w-full space-y-8">
                    <div class="text-center">
                        <h1 class="text-3xl font-bold text-gray-900">Setează parola nouă</h1>
                        <p class="mt-2 text-gray-600">Introdu noua parolă pentru contul tău</p>
                    </div>
                    <div id="reset-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"></div>
                    <form id="reset-form" class="space-y-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Parolă nouă</label>
                            <input type="password" id="password" required minlength="8"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <div id="password-strength" class="mt-2">
                                <div class="flex gap-1 mb-1">
                                    <div id="strength-bar-1" class="h-1 flex-1 rounded bg-gray-200"></div>
                                    <div id="strength-bar-2" class="h-1 flex-1 rounded bg-gray-200"></div>
                                    <div id="strength-bar-3" class="h-1 flex-1 rounded bg-gray-200"></div>
                                    <div id="strength-bar-4" class="h-1 flex-1 rounded bg-gray-200"></div>
                                </div>
                                <p id="strength-text" class="text-xs text-gray-500"></p>
                            </div>
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmă parola</label>
                            <input type="password" id="password_confirmation" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                            <p id="password-match" class="mt-1 text-xs hidden"></p>
                        </div>
                        <button type="submit" id="reset-btn" class="w-full px-4 py-2 bg-primary text-white font-medium rounded-lg hover:bg-primary-dark transition">
                            Resetează parola
                        </button>
                    </form>
                </div>
            </div>
        `;

        // Password strength meter (reuse same logic)
        const passwordInput = document.getElementById('password') as HTMLInputElement;
        const confirmInput = document.getElementById('password_confirmation') as HTMLInputElement;
        const strengthText = document.getElementById('strength-text');
        const matchText = document.getElementById('password-match');
        const bars = [
            document.getElementById('strength-bar-1'),
            document.getElementById('strength-bar-2'),
            document.getElementById('strength-bar-3'),
            document.getElementById('strength-bar-4'),
        ];

        const checkPasswordStrength = (password: string): { score: number; text: string; color: string } => {
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            if (score <= 2) return { score: 1, text: 'Foarte slabă', color: 'bg-red-500' };
            if (score <= 3) return { score: 2, text: 'Slabă', color: 'bg-orange-500' };
            if (score <= 4) return { score: 3, text: 'Medie', color: 'bg-yellow-500' };
            if (score <= 5) return { score: 4, text: 'Puternică', color: 'bg-green-500' };
            return { score: 4, text: 'Foarte puternică', color: 'bg-green-600' };
        };

        const updateStrengthMeter = () => {
            const password = passwordInput?.value || '';
            if (!password) {
                bars.forEach(bar => {
                    const currentBg = Array.from(bar?.classList || []).find(c => c.startsWith('bg-'));
                    if (currentBg && bar) bar.classList.replace(currentBg, 'bg-gray-200');
                });
                if (strengthText) strengthText.textContent = '';
                return;
            }

            const { score, text, color } = checkPasswordStrength(password);
            bars.forEach((bar, i) => {
                if (bar) {
                    const currentBg = Array.from(bar.classList).find(c => c.startsWith('bg-'));
                    if (currentBg) bar.classList.remove(currentBg);
                    bar.classList.add(i < score ? color : 'bg-gray-200');
                }
            });
            if (strengthText) {
                strengthText.textContent = text;
                strengthText.className = `text-xs ${color.replace('bg-', 'text-')}`;
            }
        };

        const checkPasswordMatch = () => {
            const password = passwordInput?.value || '';
            const confirm = confirmInput?.value || '';
            if (!confirm) {
                matchText?.classList.add('hidden');
                confirmInput?.classList.remove('border-red-300', 'border-green-300');
                return;
            }

            matchText?.classList.remove('hidden');
            if (password === confirm) {
                if (matchText) {
                    matchText.textContent = '✓ Parolele coincid';
                    matchText.className = 'mt-1 text-xs text-green-600';
                }
                confirmInput?.classList.remove('border-red-300');
                confirmInput?.classList.add('border-green-300');
            } else {
                if (matchText) {
                    matchText.textContent = '✗ Parolele nu coincid';
                    matchText.className = 'mt-1 text-xs text-red-600';
                }
                confirmInput?.classList.remove('border-green-300');
                confirmInput?.classList.add('border-red-300');
            }
        };

        passwordInput?.addEventListener('input', () => {
            updateStrengthMeter();
            checkPasswordMatch();
        });
        confirmInput?.addEventListener('input', checkPasswordMatch);

        // Form submit handler
        const form = document.getElementById('reset-form');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = passwordInput.value;
            const password_confirmation = confirmInput.value;
            const errorEl = document.getElementById('reset-error');
            const btn = document.getElementById('reset-btn') as HTMLButtonElement;

            if (password !== password_confirmation) {
                if (errorEl) {
                    errorEl.textContent = 'Parolele nu coincid';
                    errorEl.classList.remove('hidden');
                }
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Se resetează...';
            errorEl?.classList.add('hidden');

            try {
                const result = await this.postApi('/auth/reset-password', {
                    token,
                    email,
                    password,
                    password_confirmation,
                });
                if (result.success) {
                    ToastNotification.show('✓ Parola a fost resetată cu succes!', 'success');
                    this.navigate('/login');
                } else {
                    throw new Error(result.message || 'Eroare la resetare');
                }
            } catch (error: any) {
                console.error('Reset password error:', error);
                if (errorEl) {
                    errorEl.textContent = error.message || 'Eroare la resetarea parolei';
                    errorEl.classList.remove('hidden');
                }
                btn.disabled = false;
                btn.textContent = 'Resetează parola';
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
                        <a href="/account/orders/${order.id}" class="block bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
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
                                        <p class="text-sm text-gray-700">• ${ticket.event_name} - ${ticket.ticket_type} ${ticket.quantity > 1 ? `(×${ticket.quantity})` : ''}</p>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t flex justify-end">
                                <span class="text-sm text-primary hover:text-primary-dark font-medium inline-flex items-center">
                                    Vezi detalii
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
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
                    ticketsListEl.innerHTML = `
                        <div class="bg-white rounded-lg shadow overflow-hidden col-span-2">
                            <div class="divide-y divide-gray-100">
                                ${tickets.map((ticket: any) => `
                                    <div class="p-4 hover:bg-gray-50 transition flex items-center gap-4">
                                        <img src="${ticket.qr_code}" alt="QR" class="w-16 h-16 border border-gray-200 rounded flex-shrink-0">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <h3 class="font-semibold text-gray-900 truncate">${ticket.event_name}</h3>
                                                <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full whitespace-nowrap flex-shrink-0 ${
                                                    ticket.status === 'valid' || ticket.status === 'pending' ? 'bg-green-100 text-green-700' :
                                                    ticket.status === 'used' ? 'bg-blue-100 text-blue-700' :
                                                    ticket.status === 'cancelled' ? 'bg-red-100 text-red-700' :
                                                    'bg-gray-100 text-gray-700'
                                                }">${ticket.status_label}</span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
                                                <span>${ticket.ticket_type}</span>
                                                ${ticket.date ? `<span>${new Date(ticket.date).toLocaleDateString('ro-RO', {day: 'numeric', month: 'short', year: 'numeric'})}</span>` : ''}
                                                ${ticket.venue ? `<span>📍 ${ticket.venue}</span>` : ''}
                                                ${ticket.seat_label ? `<span>💺 ${ticket.seat_label}</span>` : ''}
                                            </div>
                                            ${ticket.beneficiary?.name ? `
                                                <p class="text-sm text-gray-600 mt-1">👤 ${ticket.beneficiary.name}</p>
                                            ` : ''}
                                            <p class="text-xs text-gray-400 font-mono mt-1">${ticket.code}</p>
                                        </div>
                                        <button
                                            class="view-ticket-btn flex-shrink-0 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition text-sm font-medium"
                                            data-ticket-id="${ticket.id}"
                                            data-ticket-code="${ticket.code}"
                                            data-ticket-qr="${ticket.qr_code}"
                                            data-event-name="${ticket.event_name || ''}"
                                            data-ticket-type="${ticket.ticket_type || ''}"
                                            data-date="${ticket.date || ''}"
                                            data-venue="${ticket.venue || ''}"
                                            data-seat="${ticket.seat_label || ''}"
                                            data-status="${ticket.status || ''}"
                                            data-status-label="${ticket.status_label || ''}"
                                            data-beneficiary-name="${ticket.beneficiary?.name || ''}"
                                            data-beneficiary-email="${ticket.beneficiary?.email || ''}">
                                            Detalii
                                        </button>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;

                    // Attach event listeners to ticket detail buttons
                    document.querySelectorAll('.view-ticket-btn').forEach(button => {
                        button.addEventListener('click', (e) => {
                            const btn = e.currentTarget as HTMLElement;
                            const ticketData = {
                                id: btn.dataset.ticketId || '',
                                code: btn.dataset.ticketCode || '',
                                qr_code: btn.dataset.ticketQr || '',
                                event_name: btn.dataset.eventName || '',
                                ticket_type: btn.dataset.ticketType || '',
                                date: btn.dataset.date || '',
                                venue: btn.dataset.venue || '',
                                seat_label: btn.dataset.seat || '',
                                status: btn.dataset.status || '',
                                status_label: btn.dataset.statusLabel || '',
                                beneficiary: {
                                    name: btn.dataset.beneficiaryName || '',
                                    email: btn.dataset.beneficiaryEmail || ''
                                }
                            };
                            this.showTicketDetailModal(ticketData);
                        });
                    });
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

    private async renderOrderDetail(params: Record<string, string>): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/account/orders" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Înapoi la Comenzi
                </a>
                <div id="order-detail-container">
                    <div class="animate-pulse space-y-4">
                        <div class="bg-gray-200 h-8 w-1/3 rounded"></div>
                        <div class="bg-gray-200 h-48 rounded-lg"></div>
                    </div>
                </div>
            </div>
        `;

        try {
            const response = await this.fetchApi(`/account/orders/${params.id}`);
            const order = response.data;

            const containerEl = document.getElementById('order-detail-container');
            if (containerEl) {
                containerEl.innerHTML = `
                    <h1 class="text-3xl font-bold text-gray-900 mb-6">Comanda ${order.order_number}</h1>

                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <span class="inline-block px-3 py-1 text-xs font-medium rounded-full ${
                                    order.status === 'paid' ? 'bg-green-100 text-green-800' :
                                    order.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                    order.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                                    'bg-gray-100 text-gray-800'
                                }">
                                    ${order.status_label || order.status}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Data comenzii</p>
                                <p class="font-medium text-gray-900">${order.date}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Metodă plată</p>
                                <p class="font-medium text-gray-900">${order.meta?.payment_method || 'Card'}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total</p>
                                <p class="font-bold text-lg text-primary">${order.total} ${order.currency || 'RON'}</p>
                            </div>
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Bilete (${order.items_count})</h2>
                    <div class="space-y-6">
                        ${(order.events || []).map((eventGroup: any) => `
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <div class="bg-gray-50 p-4 border-b">
                                    <h3 class="font-semibold text-gray-900">${eventGroup.event?.title || 'Eveniment'}</h3>
                                    ${eventGroup.venue ? `
                                        <p class="text-sm text-gray-600">${eventGroup.venue.name}${eventGroup.venue.city ? `, ${eventGroup.venue.city}` : ''}</p>
                                    ` : ''}
                                    ${eventGroup.event?.date ? `
                                        <p class="text-sm text-gray-500">${new Date(eventGroup.event.date).toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' })}${eventGroup.event.time ? ` • ${eventGroup.event.time}` : ''}</p>
                                    ` : ''}
                                </div>
                                <div class="divide-y">
                                    ${(eventGroup.tickets || []).map((ticket: any) => `
                                        <div class="p-4 flex justify-between items-center">
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-900">${ticket.ticket_type}</p>
                                                ${ticket.seat_label ? `<p class="text-sm text-gray-600">💺 Loc: ${ticket.seat_label}</p>` : ''}
                                                <p class="text-xs text-gray-500 font-mono mt-1">${ticket.code}</p>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-block px-2 py-1 text-xs rounded-full ${
                                                    ticket.status === 'valid' ? 'bg-green-100 text-green-700' :
                                                    ticket.status === 'used' ? 'bg-gray-100 text-gray-700' :
                                                    'bg-red-100 text-red-700'
                                                }">${ticket.status_label || ticket.status}</span>
                                                ${ticket.price ? `<p class="font-medium mt-1">${ticket.price} ${ticket.currency || order.currency || 'RON'}</p>` : ''}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    ${order.meta ? `
                        <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-4">Informații client</h2>
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Nume</p>
                                    <p class="font-medium">${order.meta.customer_name || '-'}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Email</p>
                                    <p class="font-medium">${order.meta.customer_email || '-'}</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                `;
            }
        } catch (error) {
            console.error('Error loading order:', error);
            const containerEl = document.getElementById('order-detail-container');
            if (containerEl) {
                containerEl.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                        <p class="text-red-700">Eroare la încărcarea comenzii</p>
                    </div>
                `;
            }
        }
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

    private async renderPastEvents(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        // Set up the container for past events
        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div id="past-events-container">
                    <div class="animate-pulse space-y-6">
                        <div class="bg-gray-200 h-10 w-1/4 rounded"></div>
                        <div class="flex gap-4 mb-8">
                            <div class="bg-gray-200 h-10 w-32 rounded"></div>
                            <div class="bg-gray-200 h-10 w-40 rounded"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="bg-gray-200 h-64 rounded-lg"></div>
                            <div class="bg-gray-200 h-64 rounded-lg"></div>
                            <div class="bg-gray-200 h-64 rounded-lg"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Fetch and render past events
        try {
            const response = await this.fetchApi('/events/past');
            const events = response.data?.events || response.data || [];

            const container = document.getElementById('past-events-container');
            if (!container) return;

            if (events.length === 0) {
                container.innerHTML = `
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">Evenimente trecute</h1>
                    <p class="text-gray-500">Nu există evenimente trecute.</p>
                `;
                return;
            }

            // Group events by month
            const eventsByMonth = this.groupEventsByMonth(events);

            container.innerHTML = `
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Evenimente trecute</h1>
                ${this.renderPastEventsByMonth(eventsByMonth)}
            `;
        } catch (error) {
            console.error('Failed to load past events:', error);
            const container = document.getElementById('past-events-container');
            if (container) {
                container.innerHTML = `
                    <h1 class="text-3xl font-bold text-gray-900 mb-8">Evenimente trecute</h1>
                    <p class="text-red-500">Nu s-au putut încărca evenimentele trecute.</p>
                `;
            }
        }
    }

    private groupEventsByMonth(events: any[]): Map<string, any[]> {
        const grouped = new Map<string, any[]>();

        events.forEach(event => {
            const date = new Date(event.start_date || event.event_date);
            const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

            if (!grouped.has(monthKey)) {
                grouped.set(monthKey, []);
            }
            grouped.get(monthKey)!.push(event);
        });

        // Sort by month descending (most recent first)
        return new Map([...grouped.entries()].sort((a, b) => b[0].localeCompare(a[0])));
    }

    private renderPastEventsByMonth(eventsByMonth: Map<string, any[]>): string {
        const monthNames = [
            'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie',
            'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'
        ];

        let html = '';

        eventsByMonth.forEach((events, monthKey) => {
            const [year, month] = monthKey.split('-');
            const monthName = monthNames[parseInt(month) - 1];

            html += `
                <div class="mb-10">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4 pb-2 border-b-2 border-gray-200">
                        ${monthName} ${year}
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        ${events.map(event => this.renderPastEventCard(event)).join('')}
                    </div>
                </div>
            `;
        });

        return html;
    }

    private renderPastEventCard(event: any): string {
        const date = new Date(event.start_date || event.event_date);
        const formattedDate = date.toLocaleDateString('ro-RO', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });

        const imageUrl = event.poster_url || event.hero_image_url || event.image;
        const eventUrl = `/event/${event.slug}`;

        return `
            <a href="${eventUrl}" class="block bg-white rounded-lg shadow-sm border overflow-hidden opacity-90 hover:opacity-100 hover:shadow-md transition cursor-pointer">
                ${imageUrl
                    ? `<div class="relative">
                        <img src="${imageUrl}" alt="${event.title}" class="w-full h-40 object-cover" style="filter: grayscale(30%);">
                        <span class="absolute top-2 right-2 bg-gray-700 text-white text-xs px-2 py-1 rounded">Încheiat</span>
                       </div>`
                    : `<div class="relative h-40 bg-gray-200 flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="absolute top-2 right-2 bg-gray-700 text-white text-xs px-2 py-1 rounded">Încheiat</span>
                       </div>`
                }
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1 truncate">${event.title}</h3>
                    <p class="text-sm text-gray-500">${formattedDate}</p>
                    ${event.venue?.name ? `<p class="text-sm text-gray-400">${event.venue.name}</p>` : ''}
                </div>
            </a>
        `;
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

            // Check if page uses page builder
            if (data.data.page_type === 'builder' && data.data.layout?.blocks) {
                content.innerHTML = `<div id="page-content"></div>`;
                PageBuilderModule.updateLayout(data.data.layout as PageLayout, 'page-content');

                // Register for preview mode updates
                if (PreviewMode.isActive()) {
                    PreviewMode.onLayoutUpdate((layout) => {
                        PageBuilderModule.updateLayout(layout as PageLayout, 'page-content');
                    });
                }
                return;
            }

            // Standard content page
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

    private async renderBlog(): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-8">Blog</h1>
                <div id="blog-list">
                    <div class="animate-pulse space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            ${[1,2,3].map(() => `
                                <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden">
                                    <div class="bg-gray-200 dark:bg-gray-700 h-48"></div>
                                    <div class="p-5 space-y-3">
                                        <div class="bg-gray-200 dark:bg-gray-700 h-4 w-1/4 rounded"></div>
                                        <div class="bg-gray-200 dark:bg-gray-700 h-6 w-3/4 rounded"></div>
                                        <div class="bg-gray-200 dark:bg-gray-700 h-4 w-full rounded"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;

        try {
            const data = await this.fetchApi('/blog');
            const articles = data.data?.articles || [];
            const pagination = data.data?.pagination;

            // Also fetch categories
            let categories: any[] = [];
            try {
                const catData = await this.fetchApi('/blog/categories');
                categories = catData.data?.categories || [];
            } catch (e) {
                console.log('Could not load blog categories');
            }

            const blogList = document.getElementById('blog-list');
            if (!blogList) return;

            if (articles.length === 0) {
                blogList.innerHTML = `
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">No articles yet.</p>
                    </div>
                `;
                return;
            }

            blogList.innerHTML = `
                ${categories.length > 0 ? `
                    <div class="mb-8 flex flex-wrap gap-2">
                        <a href="/blog" class="px-4 py-2 rounded-full text-sm font-medium bg-primary-600 text-white">All</a>
                        ${categories.map((cat: any) => `
                            <a href="/blog?category=${cat.slug}" class="px-4 py-2 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700">
                                ${cat.name} (${cat.articles_count})
                            </a>
                        `).join('')}
                    </div>
                ` : ''}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    ${articles.map((article: any) => this.renderBlogCard(article)).join('')}
                </div>
            `;
        } catch (error: any) {
            const blogList = document.getElementById('blog-list');
            if (blogList) {
                if (error.response?.status === 403) {
                    blogList.innerHTML = `
                        <div class="text-center py-12">
                            <p class="text-gray-500 dark:text-gray-400">Blog is not available.</p>
                        </div>
                    `;
                } else {
                    blogList.innerHTML = `
                        <div class="text-center py-12">
                            <p class="text-red-500">Failed to load blog. Please try again.</p>
                        </div>
                    `;
                }
            }
            console.error('Failed to load blog:', error);
        }
    }

    private renderBlogCard(article: any): string {
        const publishedDate = article.published_at
            ? new Date(article.published_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            })
            : '';

        return `
            <article class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow border border-gray-100 dark:border-gray-700">
                <a href="/blog/${article.slug}" class="block">
                    ${article.featured_image
                        ? `<div class="aspect-[16/9] overflow-hidden">
                            <img src="${article.featured_image}"
                                 alt="${article.featured_image_alt || article.title}"
                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                           </div>`
                        : `<div class="aspect-[16/9] bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
                            <svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                            </svg>
                           </div>`
                    }
                </a>
                <div class="p-5">
                    ${article.category
                        ? `<span class="inline-block px-3 py-1 text-xs font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full mb-3">${article.category}</span>`
                        : ''
                    }
                    <a href="/blog/${article.slug}" class="block group">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2 mb-2">
                            ${article.title}
                        </h3>
                    </a>
                    ${article.excerpt
                        ? `<p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2 mb-4">${article.excerpt}</p>`
                        : ''
                    }
                    <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span>${publishedDate}</span>
                        <span>${article.reading_time || 1} min read</span>
                    </div>
                </div>
            </article>
        `;
    }

    private async renderBlogArticle(params: Record<string, string>): Promise<void> {
        const content = this.getContentElement();
        if (!content) return;

        const slug = params.slug;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="animate-pulse space-y-6">
                    <div class="bg-gray-200 dark:bg-gray-700 h-8 w-3/4 rounded"></div>
                    <div class="bg-gray-200 dark:bg-gray-700 h-4 w-1/2 rounded"></div>
                    <div class="bg-gray-200 dark:bg-gray-700 h-64 rounded-xl"></div>
                    <div class="space-y-3">
                        <div class="bg-gray-200 dark:bg-gray-700 h-4 w-full rounded"></div>
                        <div class="bg-gray-200 dark:bg-gray-700 h-4 w-full rounded"></div>
                        <div class="bg-gray-200 dark:bg-gray-700 h-4 w-2/3 rounded"></div>
                    </div>
                </div>
            </div>
        `;

        try {
            const data = await this.fetchApi(`/blog/${slug}`);
            const article = data.data?.article;
            const related = data.data?.related || [];

            if (!article) {
                this.render404();
                return;
            }

            const publishedDate = article.published_at
                ? new Date(article.published_at).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })
                : '';

            content.innerHTML = `
                <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <header class="mb-8">
                        ${article.category
                            ? `<a href="/blog?category=${article.category_slug}" class="inline-block px-3 py-1 text-sm font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full mb-4 hover:bg-primary-200 dark:hover:bg-primary-900/50 transition-colors">${article.category}</a>`
                            : ''
                        }
                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">${article.title}</h1>
                        ${article.subtitle
                            ? `<p class="text-xl text-gray-600 dark:text-gray-400 mb-6">${article.subtitle}</p>`
                            : ''
                        }
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                            ${article.author ? `<span>By ${article.author}</span>` : ''}
                            <span>${publishedDate}</span>
                            <span>${article.reading_time || 1} min read</span>
                            <span>${article.view_count || 0} views</span>
                        </div>
                    </header>

                    ${article.featured_image
                        ? `<figure class="mb-8">
                            <img src="${article.featured_image}"
                                 alt="${article.featured_image_alt || article.title}"
                                 class="w-full rounded-xl">
                           </figure>`
                        : ''
                    }

                    <div class="prose prose-lg dark:prose-invert max-w-none">
                        ${article.content_html || article.content || ''}
                    </div>

                    ${article.tags && article.tags.length > 0
                        ? `<div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex flex-wrap gap-2">
                                ${article.tags.map((tag: string) => `
                                    <span class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full">#${tag}</span>
                                `).join('')}
                            </div>
                           </div>`
                        : ''
                    }

                    ${related.length > 0 ? `
                        <section class="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Related Articles</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                ${related.map((rel: any) => `
                                    <a href="/blog/${rel.slug}" class="block group">
                                        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
                                            ${rel.featured_image
                                                ? `<img src="${rel.featured_image}" alt="${rel.title}" class="w-full h-32 object-cover group-hover:scale-105 transition-transform duration-300">`
                                                : `<div class="w-full h-32 bg-gradient-to-br from-primary-500 to-primary-700"></div>`
                                            }
                                        </div>
                                        <h3 class="mt-3 font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">${rel.title}</h3>
                                    </a>
                                `).join('')}
                            </div>
                        </section>
                    ` : ''}

                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="/blog" class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Back to Blog
                        </a>
                    </div>
                </article>
            `;

            // Update page title and meta tags
            this.updateArticleMetaTags(article);
        } catch (error) {
            this.render404();
            console.error('Failed to load article:', error);
        }
    }

    private updateArticleMetaTags(article: any): void {
        document.title = article.meta_title || article.title;

        // Helper to update or create meta tag
        const setMetaTag = (selector: string, content: string, attr: 'name' | 'property' = 'name', attrValue?: string) => {
            if (!content) return;
            let tag = document.querySelector(selector) as HTMLMetaElement;
            if (!tag) {
                tag = document.createElement('meta');
                tag.setAttribute(attr, attrValue || '');
                document.head.appendChild(tag);
            }
            tag.setAttribute('content', content);
        };

        // Update meta description
        setMetaTag('meta[name="description"]', article.meta_description || article.excerpt || '', 'name', 'description');

        // Update OG tags
        setMetaTag('meta[property="og:title"]', article.og_title || article.title, 'property', 'og:title');
        setMetaTag('meta[property="og:description"]', article.og_description || article.excerpt || '', 'property', 'og:description');
        setMetaTag('meta[property="og:type"]', 'article', 'property', 'og:type');
        setMetaTag('meta[property="og:url"]', window.location.href, 'property', 'og:url');

        if (article.og_image || article.featured_image) {
            setMetaTag('meta[property="og:image"]', article.og_image || article.featured_image || '', 'property', 'og:image');
        }

        // Update Twitter Card tags
        setMetaTag('meta[name="twitter:card"]', 'summary_large_image', 'name', 'twitter:card');
        setMetaTag('meta[name="twitter:title"]', article.og_title || article.title, 'name', 'twitter:title');
        setMetaTag('meta[name="twitter:description"]', article.og_description || article.excerpt || '', 'name', 'twitter:description');

        if (article.og_image || article.featured_image) {
            setMetaTag('meta[name="twitter:image"]', article.og_image || article.featured_image || '', 'name', 'twitter:image');
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

    private showTicketDetailModal(ticket: any): void {
        // Create modal backdrop
        const modalHTML = `
            <div id="ticket-modal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                    <!-- Modal Panel -->
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <!-- Close button -->
                            <button id="close-modal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            <!-- Modal content -->
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                                        Detalii Bilet
                                    </h3>

                                    <!-- Event Info -->
                                    <div class="mb-4">
                                        <h4 class="text-xl font-semibold text-gray-900 mb-1">${ticket.event_name}</h4>
                                        <p class="text-sm text-gray-600">${ticket.ticket_type}</p>
                                    </div>

                                    <!-- Status Badge -->
                                    <div class="mb-4">
                                        <span class="inline-block px-3 py-1 text-sm font-medium rounded-full ${
                                            ticket.status === 'valid' || ticket.status === 'pending' ? 'bg-green-100 text-green-800' :
                                            ticket.status === 'used' ? 'bg-blue-100 text-blue-800' :
                                            ticket.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                                            'bg-gray-100 text-gray-800'
                                        }">
                                            ${ticket.status_label}
                                        </span>
                                    </div>

                                    <!-- Event Details -->
                                    <div class="space-y-2 mb-4">
                                        ${ticket.date ? `<p class="text-sm text-gray-700"><strong>Dată:</strong> ${new Date(ticket.date).toLocaleDateString('ro-RO', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'})}</p>` : ''}
                                        ${ticket.venue ? `<p class="text-sm text-gray-700"><strong>Locație:</strong> ${ticket.venue}</p>` : ''}
                                        ${ticket.seat_label ? `<p class="text-sm text-gray-700"><strong>Loc:</strong> ${ticket.seat_label}</p>` : ''}
                                    </div>

                                    <!-- Beneficiary Info -->
                                    ${ticket.beneficiary && ticket.beneficiary.name ? `
                                        <div class="border-t pt-3 mb-4">
                                            <p class="text-xs text-gray-500 mb-1">Beneficiar:</p>
                                            <p class="text-sm font-medium text-gray-900">${ticket.beneficiary.name}</p>
                                            ${ticket.beneficiary.email ? `<p class="text-xs text-gray-600">${ticket.beneficiary.email}</p>` : ''}
                                        </div>
                                    ` : ''}

                                    <!-- QR Code -->
                                    <div class="border-t pt-4">
                                        <div class="flex flex-col items-center">
                                            <img src="${ticket.qr_code}" alt="QR Code" class="w-48 h-48 border-2 border-gray-300 rounded-lg mb-3">
                                            <div class="text-center">
                                                <p class="text-xs text-gray-500 mb-1">Cod bilet:</p>
                                                <p class="text-lg font-mono font-bold text-gray-900">${ticket.code}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Download/Print Buttons -->
                                    <div class="flex space-x-2 mt-6">
                                        <button id="download-ticket" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition font-medium">
                                            Descarcă Bilet
                                        </button>
                                        <button id="print-ticket" class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition font-medium">
                                            Printează
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Append modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Attach close handlers
        const modal = document.getElementById('ticket-modal');
        const closeBtn = document.getElementById('close-modal');
        const backdrop = modal?.querySelector('.bg-gray-500');

        const closeModal = () => {
            modal?.remove();
        };

        closeBtn?.addEventListener('click', closeModal);
        backdrop?.addEventListener('click', closeModal);

        // Download button handler
        document.getElementById('download-ticket')?.addEventListener('click', () => {
            const link = document.createElement('a');
            link.href = ticket.qr_code;
            link.download = `bilet-${ticket.code}.png`;
            link.click();
        });

        // Print button handler
        document.getElementById('print-ticket')?.addEventListener('click', () => {
            const printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Bilet ${ticket.code}</title>
                            <style>
                                body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                                img { max-width: 300px; margin: 20px 0; }
                                .code { font-size: 18px; font-weight: bold; margin: 10px 0; }
                            </style>
                        </head>
                        <body>
                            <h1>${ticket.event_name}</h1>
                            <p>${ticket.ticket_type}</p>
                            ${ticket.date ? `<p>${new Date(ticket.date).toLocaleDateString('ro-RO', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'})}</p>` : ''}
                            ${ticket.venue ? `<p>${ticket.venue}</p>` : ''}
                            ${ticket.seat_label ? `<p>Loc: ${ticket.seat_label}</p>` : ''}
                            <img src="${ticket.qr_code}" alt="QR Code">
                            <p class="code">Cod: ${ticket.code}</p>
                            ${ticket.beneficiary && ticket.beneficiary.name ? `<p>Beneficiar: ${ticket.beneficiary.name}</p>` : ''}
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        });
    }

    private initCountdown(): void {
        const container = document.getElementById('countdown-container');
        if (!container) return;

        const eventDate = container.dataset.eventDate;
        const eventTime = container.dataset.eventTime;
        const isCancelled = container.dataset.isCancelled === 'true';
        const isPostponed = container.dataset.isPostponed === 'true';

        // Don't show countdown for cancelled events or if no date available
        // For postponed events, we show countdown to the NEW date (already passed in eventDate)
        if (isCancelled || !eventDate) {
            container.style.display = 'none';
            return;
        }

        // Parse event datetime - handles ISO 8601 format (includes time) or date-only formats
        let eventDateTime: Date;
        try {
            // Try parsing eventDate directly first (it may already include time in ISO 8601 format)
            eventDateTime = new Date(eventDate);

            // Check if we got a valid date with a meaningful time (not midnight unless midnight is the actual event time)
            const hasTimeInDate = eventDate.includes('T') || eventDate.includes(' ');

            // If eventDate is date-only and we have a separate eventTime, combine them
            if (!hasTimeInDate && eventTime && eventTime.match(/^\d{2}:\d{2}/)) {
                // eventDate is like "2024-03-15", eventTime is like "19:00"
                const dateOnly = eventDate.split('T')[0]; // Handle any potential timezone suffix
                eventDateTime = new Date(`${dateOnly}T${eventTime}`);
            }

            if (isNaN(eventDateTime.getTime())) {
                console.warn('Countdown: Invalid date', { eventDate, eventTime });
                container.style.display = 'none';
                return;
            }
        } catch (e) {
            console.warn('Countdown: Date parsing error', e);
            container.style.display = 'none';
            return;
        }

        // Create countdown elements
        const wrapper = document.createElement('div');
        wrapper.className = 'p-6 mb-6 text-white rounded-lg shadow-lg bg-gradient-to-r from-blue-500 to-purple-600';

        const title = document.createElement('h3');
        title.className = 'mb-3 text-lg font-semibold text-center';
        title.textContent = isPostponed ? 'Noua dată - Începe în:' : 'Începe în:';

        const timeDisplay = document.createElement('div');
        timeDisplay.className = 'flex justify-center gap-4 text-center';
        timeDisplay.id = 'countdown-display';

        wrapper.appendChild(title);
        wrapper.appendChild(timeDisplay);
        container.appendChild(wrapper);

        // Update countdown
        const updateCountdown = () => {
            const now = new Date().getTime();
            const distance = eventDateTime.getTime() - now;

            if (distance < 0) {
                title.textContent = 'Evenimentul a început!';
                timeDisplay.innerHTML = '';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            timeDisplay.innerHTML = `
                <div class="flex flex-col">
                    <span class="text-3xl font-bold">${days}</span>
                    <span class="text-sm">zile</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-3xl font-bold">${hours}</span>
                    <span class="text-sm">ore</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-3xl font-bold">${minutes}</span>
                    <span class="text-sm">minute</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-3xl font-bold">${seconds}</span>
                    <span class="text-sm">secunde</span>
                </div>
            `;
        };

        // Initial update
        updateCountdown();

        // Update every second
        setInterval(updateCountdown, 1000);
    }
}
