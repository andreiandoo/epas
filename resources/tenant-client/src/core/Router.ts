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
        this.addRoute('/login', this.renderLogin.bind(this));
        this.addRoute('/register', this.renderRegister.bind(this));
        this.addRoute('/account', this.renderAccount.bind(this));
        this.addRoute('/account/orders', this.renderOrders.bind(this));
        this.addRoute('/account/tickets', this.renderTickets.bind(this));

        // Admin routes
        this.addRoute('/admin', this.renderAdminDashboard.bind(this));
        this.addRoute('/admin/events', this.renderAdminEvents.bind(this));
        this.addRoute('/admin/orders', this.renderAdminOrders.bind(this));
        this.addRoute('/admin/customers', this.renderAdminCustomers.bind(this));
        this.addRoute('/admin/settings', this.renderAdminSettings.bind(this));
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
