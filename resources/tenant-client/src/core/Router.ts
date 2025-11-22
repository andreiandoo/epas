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

    constructor(config: TixelloConfig) {
        this.config = config;
        this.setupDefaultRoutes();
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
    private renderHome(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <!-- Hero Section -->
                <div class="text-center mb-16">
                    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                        Discover Amazing Events
                    </h1>
                    <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                        Find and book tickets for the best concerts, shows, and experiences
                    </p>
                    <a href="/events" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition">
                        Browse Events
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>

                <!-- Featured Events -->
                <div class="mb-16">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Featured Events</h2>
                    <div id="featured-events" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-64"></div>
                    </div>
                </div>

                <!-- Categories -->
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Browse by Category</h2>
                    <div id="categories" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                        <div class="animate-pulse bg-gray-200 rounded-lg h-24"></div>
                    </div>
                </div>
            </div>
        `;
    }

    private renderEvents(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Events</h1>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <input type="search" id="event-search" placeholder="Search events..."
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <select id="event-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>
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
    }

    private renderEventDetail(params: Record<string, string>): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <a href="/events" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Events
                </a>
                <div id="event-detail-${params.slug}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="animate-pulse bg-gray-200 rounded-lg h-96 mb-6"></div>
                        <div class="animate-pulse bg-gray-200 h-8 w-3/4 mb-4 rounded"></div>
                        <div class="animate-pulse bg-gray-200 h-4 w-full mb-2 rounded"></div>
                        <div class="animate-pulse bg-gray-200 h-4 w-full mb-2 rounded"></div>
                        <div class="animate-pulse bg-gray-200 h-4 w-2/3 rounded"></div>
                    </div>
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow-lg p-6 sticky top-4">
                            <div class="animate-pulse bg-gray-200 h-6 w-1/2 mb-4 rounded"></div>
                            <div class="animate-pulse bg-gray-200 h-10 w-full mb-4 rounded"></div>
                            <div class="animate-pulse bg-gray-200 h-12 w-full rounded"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
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
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="min-h-[60vh] flex items-center justify-center px-4">
                <div class="max-w-md w-full space-y-8">
                    <div class="text-center">
                        <h1 class="text-3xl font-bold text-gray-900">Welcome back</h1>
                        <p class="mt-2 text-gray-600">Sign in to your account</p>
                    </div>
                    <form id="login-form" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                            Sign In
                        </button>
                    </form>
                    <p class="text-center text-gray-600">
                        Don't have an account? <a href="/register" class="text-blue-600 hover:text-blue-700 font-medium">Register</a>
                    </p>
                </div>
            </div>
        `;
    }

    private renderRegister(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="min-h-[60vh] flex items-center justify-center px-4">
                <div class="max-w-md w-full space-y-8">
                    <div class="text-center">
                        <h1 class="text-3xl font-bold text-gray-900">Create account</h1>
                        <p class="mt-2 text-gray-600">Join us to book amazing events</p>
                    </div>
                    <form id="register-form" class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" id="first_name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
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
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                            Create Account
                        </button>
                    </form>
                    <p class="text-center text-gray-600">
                        Already have an account? <a href="/login" class="text-blue-600 hover:text-blue-700 font-medium">Sign in</a>
                    </p>
                </div>
            </div>
        `;
    }

    private renderAccount(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">My Account</h1>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="/account/orders" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-blue-600 mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">My Orders</h3>
                        <p class="text-gray-600 text-sm">View order history</p>
                    </a>
                    <a href="/account/tickets" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-blue-600 mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">My Tickets</h3>
                        <p class="text-gray-600 text-sm">Access your tickets</p>
                    </a>
                    <a href="/account/profile" class="block p-6 bg-white rounded-lg shadow hover:shadow-md transition">
                        <div class="text-blue-600 mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Profile</h3>
                        <p class="text-gray-600 text-sm">Edit your details</p>
                    </a>
                </div>
            </div>
        `;
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
