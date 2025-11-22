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
        // Listen for hash changes
        window.addEventListener('hashchange', () => this.handleRoute());

        // Handle initial route
        this.handleRoute();
    }

    private setupDefaultRoutes(): void {
        // Public routes
        this.addRoute('/', this.renderHome.bind(this));
        this.addRoute('/events', this.renderEvents.bind(this));
        this.addRoute('/events/:slug', this.renderEventDetail.bind(this));
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

        // Admin routes
        this.addRoute('/admin', this.renderAdminDashboard.bind(this));
        this.addRoute('/admin/events', this.renderAdminEvents.bind(this));
        this.addRoute('/admin/orders', this.renderAdminOrders.bind(this));
        this.addRoute('/admin/customers', this.renderAdminCustomers.bind(this));
        this.addRoute('/admin/users', this.renderAdminUsers.bind(this));
        this.addRoute('/admin/venues', this.renderAdminVenues.bind(this));
        this.addRoute('/admin/artists', this.renderAdminArtists.bind(this));
        this.addRoute('/admin/seating', this.renderAdminSeating.bind(this));
        this.addRoute('/admin/pricing', this.renderAdminPricing.bind(this));
        this.addRoute('/admin/templates', this.renderAdminTemplates.bind(this));
        this.addRoute('/admin/settings', this.renderAdminSettings.bind(this));
        this.addRoute('/admin/payments', this.renderAdminPayments.bind(this));
        this.addRoute('/admin/services', this.renderAdminServices.bind(this));
        this.addRoute('/admin/services/:id', this.renderAdminServiceConfig.bind(this));
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
        window.location.hash = path;
    }

    private handleRoute(): void {
        const hash = window.location.hash.slice(1) || '/';
        this.currentPath = hash;

        for (const route of this.routes) {
            const match = hash.match(route.pattern);
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
            <div class="tixello-home">
                <h1>Welcome to ${this.config.domain}</h1>
                <p>Browse our upcoming events</p>
                <a href="#/events" class="tixello-btn">View Events</a>
            </div>
        `;
    }

    private renderEvents(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-events">
                <h1>Events</h1>
                <div id="events-list">Loading events...</div>
            </div>
        `;

        // Events will be loaded via module
    }

    private renderEventDetail(params: Record<string, string>): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-event-detail">
                <div id="event-${params.slug}">Loading event...</div>
            </div>
        `;
    }

    private renderCart(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-cart">
                <h1>Shopping Cart</h1>
                <div id="cart-items">Loading cart...</div>
            </div>
        `;
    }

    private renderCheckout(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-checkout">
                <h1>Checkout</h1>
                <div id="checkout-form">Loading...</div>
            </div>
        `;
    }

    private renderLogin(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-auth">
                <h1>Login</h1>
                <form id="login-form">
                    <input type="email" placeholder="Email" required>
                    <input type="password" placeholder="Password" required>
                    <button type="submit" class="tixello-btn">Login</button>
                </form>
                <p>Don't have an account? <a href="#/register">Register</a></p>
            </div>
        `;
    }

    private renderRegister(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-auth">
                <h1>Register</h1>
                <form id="register-form">
                    <input type="text" placeholder="Name" required>
                    <input type="email" placeholder="Email" required>
                    <input type="password" placeholder="Password" required>
                    <button type="submit" class="tixello-btn">Register</button>
                </form>
                <p>Already have an account? <a href="#/login">Login</a></p>
            </div>
        `;
    }

    private renderAccount(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-account">
                <h1>My Account</h1>
                <nav>
                    <a href="#/account/orders">My Orders</a>
                    <a href="#/account/tickets">My Tickets</a>
                </nav>
            </div>
        `;
    }

    private renderOrders(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-orders">
                <h1>My Orders</h1>
                <div id="orders-list">Loading orders...</div>
            </div>
        `;
    }

    private renderTickets(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-tickets">
                <h1>My Tickets</h1>
                <div id="tickets-list">Loading tickets...</div>
            </div>
        `;
    }

    private renderOrderDetail(params: Record<string, string>): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-order-detail">
                <a href="#/account/orders" style="color: #6b7280; margin-bottom: 1rem; display: inline-block;">← Back to Orders</a>
                <div id="order-detail-${params.id}">Loading order details...</div>
            </div>
        `;
    }

    private renderMyEvents(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-my-events">
                <h1>My Events</h1>
                <p style="color: #6b7280; margin-bottom: 1.5rem;">Upcoming events you have tickets for</p>
                <div id="my-events-list">Loading events...</div>
            </div>
        `;
    }

    private renderProfile(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-profile">
                <h1>My Profile</h1>
                <div id="profile-form">Loading profile...</div>
            </div>
        `;
    }

    private renderThankYou(params: Record<string, string>): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-thank-you" style="text-align: center; padding: 3rem 1rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">✓</div>
                <h1 style="font-size: 2rem; margin-bottom: 1rem;">Thank You!</h1>
                <p style="color: #6b7280; margin-bottom: 2rem;">Your order has been confirmed.</p>
                <div id="order-confirmation-${params.orderNumber}">Loading order details...</div>
            </div>
        `;
    }

    // Admin routes
    private renderAdminDashboard(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin">
                <h1>Admin Dashboard</h1>
                <div id="admin-stats">Loading...</div>
            </div>
        `;
    }

    private renderAdminEvents(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-events">
                <h1>Manage Events</h1>
                <div id="admin-events-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminOrders(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-orders">
                <h1>Orders</h1>
                <div id="admin-orders-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminCustomers(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-customers">
                <h1>Customers</h1>
                <div id="admin-customers-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminSettings(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-settings">
                <h1>Settings</h1>
                <div id="admin-settings-form">Loading...</div>
            </div>
        `;
    }

    private renderAdminUsers(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-users">
                <h1>Users</h1>
                <div id="admin-users-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminVenues(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-venues">
                <h1>Venues</h1>
                <div id="admin-venues-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminArtists(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-artists">
                <h1>Artists</h1>
                <div id="admin-artists-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminSeating(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-seating">
                <h1>Seating & Price Tiers</h1>
                <div class="tabs" style="margin-bottom: 1rem;">
                    <button class="tab-btn active" data-tab="layouts">Seating Layouts</button>
                    <button class="tab-btn" data-tab="tiers">Price Tiers</button>
                </div>
                <div id="admin-seating-layouts">Loading...</div>
                <div id="admin-price-tiers" style="display: none;">Loading...</div>
            </div>
        `;
    }

    private renderAdminPricing(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-pricing">
                <h1>Dynamic Pricing</h1>
                <div id="admin-pricing-rules">Loading...</div>
            </div>
        `;
    }

    private renderAdminTemplates(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-templates">
                <h1>Site Templates</h1>
                <p style="color: #6b7280; margin-bottom: 1rem;">Choose a template for your public website</p>
                <div id="admin-templates-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminPayments(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-payments">
                <h1>Payment Processors</h1>
                <p style="color: #6b7280; margin-bottom: 1rem;">Configure your payment gateway API keys</p>
                <div id="admin-payments-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminServices(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-services">
                <h1>Services</h1>
                <p style="color: #6b7280; margin-bottom: 1rem;">Configure your subscribed microservices</p>
                <div id="admin-services-list">Loading...</div>
            </div>
        `;
    }

    private renderAdminServiceConfig(params: Record<string, string>): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-admin-service-config">
                <div id="admin-service-config-${params.id}">Loading...</div>
            </div>
        `;
    }

    private render404(): void {
        const content = this.getContentElement();
        if (!content) return;

        content.innerHTML = `
            <div class="tixello-404">
                <h1>Page Not Found</h1>
                <p>The page you're looking for doesn't exist.</p>
                <a href="#/" class="tixello-btn">Go Home</a>
            </div>
        `;
    }
}
