import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

/**
 * SLEEK CLIENT MODULE
 * A premium, app-like customer portal experience
 * Designed to feel like high-end ticketing apps (Dice, Eventbrite, StubHub)
 */
export class SleekClientModule {
    name = 'sleek-client';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private clientToken: string | null = null;

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;
        this.clientToken = localStorage.getItem('client_token');

        // Auth routes
        this.eventBus.on('route:login', () => this.renderLogin());
        this.eventBus.on('route:register', () => this.renderRegister());

        // Cart & Checkout
        this.eventBus.on('route:cart', () => this.renderCart());
        this.eventBus.on('route:checkout', () => this.renderCheckout());
        this.eventBus.on('route:thank-you', (orderNumber: string) => this.renderOrderConfirmation(orderNumber));

        // Client dashboard
        this.eventBus.on('route:account', () => this.renderAccountDashboard());
        this.eventBus.on('route:orders', () => this.renderOrders());
        this.eventBus.on('route:order-detail', (id: string) => this.renderOrderDetail(id));
        this.eventBus.on('route:tickets', () => this.renderTickets());
        this.eventBus.on('route:my-events', () => this.renderMyEvents());
        this.eventBus.on('route:profile', () => this.renderProfile());

        // Shop account pages
        this.eventBus.on('route:shop-orders', () => this.renderShopOrders());
        this.eventBus.on('route:shop-order-detail', (id: string) => this.renderShopOrderDetail(id));
        this.eventBus.on('route:wishlist', () => this.renderWishlist());
        this.eventBus.on('route:stock-alerts', () => this.renderStockAlerts());
        this.eventBus.on('route:shop-cart', () => this.renderShopCart());
        this.eventBus.on('route:shop-checkout', () => this.renderShopCheckout());

        console.log('Sleek Client module initialized');
    }

    private isLoggedIn(): boolean {
        return !!this.clientToken;
    }

    private setAuthHeaders(): void {
        if (this.clientToken && this.apiClient) {
            this.apiClient.setHeader('Authorization', `Bearer ${this.clientToken}`);
        }
    }

    private formatCurrency(amount: number, currency: string = 'RON'): string {
        return new Intl.NumberFormat('ro-RO', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    private formatDate(dateStr: string, includeTime: boolean = false): string {
        const date = new Date(dateStr);
        const options: Intl.DateTimeFormatOptions = {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        };
        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        return date.toLocaleDateString('ro-RO', options);
    }

    // ========================================
    // LOGIN PAGE
    // ========================================
    renderLogin(): void {
        const container = document.getElementById('login-form');
        if (!container) return;

        container.innerHTML = `
            <style>
                .sleek-auth-container {
                    min-height: calc(100vh - 140px);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 2rem 1rem;
                }

                .sleek-auth-card {
                    width: 100%;
                    max-width: 420px;
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    padding: 2.5rem;
                }

                .sleek-auth-header {
                    text-align: center;
                    margin-bottom: 2rem;
                }

                .sleek-auth-icon {
                    width: 64px;
                    height: 64px;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1rem;
                    box-shadow: 0 8px 24px var(--sleek-glow);
                }

                .sleek-auth-icon svg {
                    width: 28px;
                    height: 28px;
                    color: white;
                }

                .sleek-auth-title {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: var(--sleek-text);
                    margin-bottom: 0.5rem;
                }

                .sleek-auth-subtitle {
                    color: var(--sleek-text-muted);
                    font-size: 0.9rem;
                }

                .sleek-form-group {
                    margin-bottom: 1.25rem;
                }

                .sleek-form-label {
                    display: block;
                    font-weight: 500;
                    color: var(--sleek-text);
                    margin-bottom: 0.5rem;
                    font-size: 0.9rem;
                }

                .sleek-form-input {
                    width: 100%;
                    padding: 0.875rem 1rem;
                    background: var(--sleek-surface-elevated);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius-sm);
                    color: var(--sleek-text);
                    font-size: 1rem;
                    transition: var(--sleek-transition);
                }

                .sleek-form-input:focus {
                    outline: none;
                    border-color: var(--sleek-gradient-start);
                    box-shadow: 0 0 0 3px var(--sleek-glow);
                }

                .sleek-form-input::placeholder {
                    color: var(--sleek-text-subtle);
                }

                .sleek-auth-submit {
                    width: 100%;
                    padding: 1rem;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    color: white;
                    font-weight: 600;
                    font-size: 1rem;
                    border: none;
                    border-radius: var(--sleek-radius-sm);
                    cursor: pointer;
                    transition: var(--sleek-transition);
                    box-shadow: 0 4px 15px var(--sleek-glow);
                }

                .sleek-auth-submit:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px var(--sleek-glow);
                }

                .sleek-auth-submit:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                    transform: none;
                }

                .sleek-auth-footer {
                    text-align: center;
                    margin-top: 1.5rem;
                    padding-top: 1.5rem;
                    border-top: 1px solid var(--sleek-border);
                }

                .sleek-auth-link {
                    color: var(--sleek-gradient-start);
                    text-decoration: none;
                    font-weight: 500;
                    transition: var(--sleek-transition);
                }

                .sleek-auth-link:hover {
                    color: var(--sleek-gradient-end);
                }

                .sleek-error-msg {
                    background: rgba(239, 68, 68, 0.1);
                    border: 1px solid rgba(239, 68, 68, 0.3);
                    color: #ef4444;
                    padding: 0.75rem 1rem;
                    border-radius: var(--sleek-radius-xs);
                    font-size: 0.875rem;
                    margin-bottom: 1rem;
                    display: none;
                }
            </style>

            <div class="sleek-auth-container">
                <div class="sleek-auth-card">
                    <div class="sleek-auth-header">
                        <div class="sleek-auth-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h1 class="sleek-auth-title">Bine ai venit!</h1>
                        <p class="sleek-auth-subtitle">Conecteaza-te pentru a-ti accesa contul</p>
                    </div>

                    <div id="login-error" class="sleek-error-msg"></div>

                    <form id="sleek-login-form">
                        <div class="sleek-form-group">
                            <label class="sleek-form-label">Email</label>
                            <input type="email" name="email" class="sleek-form-input" placeholder="email@exemplu.com" required>
                        </div>

                        <div class="sleek-form-group">
                            <label class="sleek-form-label">Parola</label>
                            <input type="password" name="password" class="sleek-form-input" placeholder="Introdu parola" required>
                        </div>

                        <button type="submit" class="sleek-auth-submit" id="login-btn">
                            <span id="login-text">Conecteaza-te</span>
                            <span id="login-loading" style="display:none;">Se conecteaza...</span>
                        </button>
                    </form>

                    <div class="sleek-auth-footer">
                        <p style="color: var(--sleek-text-muted); margin-bottom: 0.5rem;">Nu ai cont?</p>
                        <a href="/register" class="sleek-auth-link">Creeaza un cont nou</a>
                    </div>
                </div>
            </div>
        `;

        this.setupLoginHandler();
    }

    private setupLoginHandler(): void {
        const form = document.getElementById('sleek-login-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = (form.querySelector('input[name="email"]') as HTMLInputElement).value;
            const password = (form.querySelector('input[name="password"]') as HTMLInputElement).value;
            const btn = document.getElementById('login-btn') as HTMLButtonElement;
            const errorEl = document.getElementById('login-error');

            btn.disabled = true;
            document.getElementById('login-text')!.style.display = 'none';
            document.getElementById('login-loading')!.style.display = 'inline';

            if (this.apiClient) {
                try {
                    const response = await this.apiClient.post('/client/login', { email, password });
                    const { token, client } = response.data.data;
                    localStorage.setItem('client_token', token);
                    localStorage.setItem('client_name', client.name);
                    this.clientToken = token;
                    window.location.hash = '/account';
                } catch (error: any) {
                    if (errorEl) {
                        errorEl.textContent = error.response?.data?.message || 'Autentificare esuata. Verifica datele introduse.';
                        errorEl.style.display = 'block';
                    }
                    btn.disabled = false;
                    document.getElementById('login-text')!.style.display = 'inline';
                    document.getElementById('login-loading')!.style.display = 'none';
                }
            }
        });
    }

    // ========================================
    // REGISTER PAGE
    // ========================================
    renderRegister(): void {
        const container = document.getElementById('register-form');
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-auth-container">
                <div class="sleek-auth-card">
                    <div class="sleek-auth-header">
                        <div class="sleek-auth-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <h1 class="sleek-auth-title">Creeaza cont</h1>
                        <p class="sleek-auth-subtitle">Inregistreaza-te pentru a cumpara bilete</p>
                    </div>

                    <div id="register-error" class="sleek-error-msg"></div>

                    <form id="sleek-register-form">
                        <div class="sleek-form-group">
                            <label class="sleek-form-label">Nume complet</label>
                            <input type="text" name="name" class="sleek-form-input" placeholder="Ion Popescu" required>
                        </div>

                        <div class="sleek-form-group">
                            <label class="sleek-form-label">Email</label>
                            <input type="email" name="email" class="sleek-form-input" placeholder="email@exemplu.com" required>
                        </div>

                        <div class="sleek-form-group">
                            <label class="sleek-form-label">Parola</label>
                            <input type="password" name="password" class="sleek-form-input" placeholder="Minim 8 caractere" required>
                        </div>

                        <button type="submit" class="sleek-auth-submit" id="register-btn">
                            <span id="register-text">Creeaza cont</span>
                            <span id="register-loading" style="display:none;">Se creeaza...</span>
                        </button>
                    </form>

                    <div class="sleek-auth-footer">
                        <p style="color: var(--sleek-text-muted); margin-bottom: 0.5rem;">Ai deja cont?</p>
                        <a href="/login" class="sleek-auth-link">Conecteaza-te</a>
                    </div>
                </div>
            </div>
        `;

        this.setupRegisterHandler();
    }

    private setupRegisterHandler(): void {
        const form = document.getElementById('sleek-register-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = (form.querySelector('input[name="name"]') as HTMLInputElement).value;
            const email = (form.querySelector('input[name="email"]') as HTMLInputElement).value;
            const password = (form.querySelector('input[name="password"]') as HTMLInputElement).value;
            const btn = document.getElementById('register-btn') as HTMLButtonElement;
            const errorEl = document.getElementById('register-error');

            btn.disabled = true;
            document.getElementById('register-text')!.style.display = 'none';
            document.getElementById('register-loading')!.style.display = 'inline';

            if (this.apiClient) {
                try {
                    const response = await this.apiClient.post('/client/register', {
                        name, email, password, password_confirmation: password
                    });
                    const { token, client } = response.data.data;
                    localStorage.setItem('client_token', token);
                    localStorage.setItem('client_name', client.name);
                    this.clientToken = token;
                    window.location.hash = '/account';
                } catch (error: any) {
                    if (errorEl) {
                        errorEl.textContent = error.response?.data?.message || 'Inregistrare esuata. Incearca din nou.';
                        errorEl.style.display = 'block';
                    }
                    btn.disabled = false;
                    document.getElementById('register-text')!.style.display = 'inline';
                    document.getElementById('register-loading')!.style.display = 'none';
                }
            }
        });
    }

    // ========================================
    // ACCOUNT DASHBOARD - App-like Design
    // ========================================
    async renderAccountDashboard(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.querySelector('.tixello-account');
        if (!container) return;

        const clientName = localStorage.getItem('client_name') || 'User';
        const firstName = clientName.split(' ')[0];

        container.innerHTML = `
            <style>
                .sleek-dashboard {
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 1.5rem 1rem;
                }

                @media (min-width: 768px) {
                    .sleek-dashboard {
                        padding: 3rem 2rem;
                    }
                }

                .sleek-dashboard-header {
                    text-align: center;
                    margin-bottom: 2rem;
                }

                .sleek-avatar {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1rem;
                    font-size: 2rem;
                    font-weight: 700;
                    color: white;
                    box-shadow: 0 8px 24px var(--sleek-glow);
                }

                .sleek-greeting {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: var(--sleek-text);
                    margin-bottom: 0.25rem;
                }

                .sleek-greeting-sub {
                    color: var(--sleek-text-muted);
                    font-size: 0.9rem;
                }

                .sleek-quick-stats {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                    margin-bottom: 2rem;
                }

                @media (min-width: 768px) {
                    .sleek-quick-stats {
                        grid-template-columns: repeat(4, 1fr);
                    }
                }

                .sleek-stat-card {
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    padding: 1.25rem;
                    text-align: center;
                    transition: var(--sleek-transition);
                }

                .sleek-stat-card:hover {
                    border-color: var(--sleek-border-light);
                }

                .sleek-stat-value {
                    font-size: 1.75rem;
                    font-weight: 700;
                    color: var(--sleek-text);
                    display: block;
                }

                .sleek-stat-label {
                    font-size: 0.75rem;
                    color: var(--sleek-text-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-top: 0.25rem;
                }

                .sleek-menu-section {
                    margin-bottom: 2rem;
                }

                .sleek-menu-title {
                    font-size: 0.8rem;
                    font-weight: 600;
                    color: var(--sleek-text-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.1em;
                    margin-bottom: 0.75rem;
                    padding-left: 0.5rem;
                }

                .sleek-menu-list {
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    overflow: hidden;
                }

                .sleek-menu-item {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 1rem 1.25rem;
                    color: var(--sleek-text);
                    text-decoration: none;
                    border-bottom: 1px solid var(--sleek-border);
                    transition: var(--sleek-transition);
                }

                .sleek-menu-item:last-child {
                    border-bottom: none;
                }

                .sleek-menu-item:hover {
                    background: rgba(255,255,255,0.02);
                }

                .sleek-menu-item:active {
                    background: rgba(255,255,255,0.05);
                }

                .sleek-menu-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: var(--sleek-radius-sm);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }

                .sleek-menu-icon svg {
                    width: 20px;
                    height: 20px;
                }

                .sleek-menu-icon.tickets {
                    background: rgba(99, 102, 241, 0.15);
                    color: #6366f1;
                }

                .sleek-menu-icon.orders {
                    background: rgba(16, 185, 129, 0.15);
                    color: #10b981;
                }

                .sleek-menu-icon.events {
                    background: rgba(245, 158, 11, 0.15);
                    color: #f59e0b;
                }

                .sleek-menu-icon.profile {
                    background: rgba(139, 92, 246, 0.15);
                    color: #8b5cf6;
                }

                .sleek-menu-icon.logout {
                    background: rgba(239, 68, 68, 0.15);
                    color: #ef4444;
                }

                .sleek-menu-icon.shop {
                    background: rgba(16, 185, 129, 0.15);
                    color: #10b981;
                }

                .sleek-menu-icon.wishlist {
                    background: rgba(239, 68, 68, 0.15);
                    color: #ef4444;
                }

                .sleek-menu-icon.alerts {
                    background: rgba(59, 130, 246, 0.15);
                    color: #3b82f6;
                }

                .sleek-menu-content {
                    flex: 1;
                    min-width: 0;
                }

                .sleek-menu-content h3 {
                    font-weight: 600;
                    font-size: 0.95rem;
                    color: var(--sleek-text);
                    margin: 0;
                }

                .sleek-menu-content p {
                    font-size: 0.8rem;
                    color: var(--sleek-text-muted);
                    margin: 0.125rem 0 0 0;
                }

                .sleek-menu-arrow {
                    color: var(--sleek-text-subtle);
                }

                .sleek-menu-badge {
                    padding: 0.25rem 0.625rem;
                    background: var(--sleek-glow);
                    color: var(--sleek-gradient-start);
                    font-size: 0.75rem;
                    font-weight: 600;
                    border-radius: 50px;
                }
            </style>

            <div class="sleek-dashboard sleek-animate-in">
                <div class="sleek-dashboard-header">
                    <div class="sleek-avatar">${firstName.charAt(0).toUpperCase()}</div>
                    <h1 class="sleek-greeting">Salut, ${firstName}!</h1>
                    <p class="sleek-greeting-sub">Gestioneaza-ti biletele si comenzile</p>
                </div>

                <div class="sleek-quick-stats" id="quick-stats">
                    <div class="sleek-stat-card">
                        <span class="sleek-stat-value" id="stat-tickets">-</span>
                        <span class="sleek-stat-label">Bilete</span>
                    </div>
                    <div class="sleek-stat-card">
                        <span class="sleek-stat-value" id="stat-orders">-</span>
                        <span class="sleek-stat-label">Comenzi</span>
                    </div>
                    <div class="sleek-stat-card">
                        <span class="sleek-stat-value" id="stat-events">-</span>
                        <span class="sleek-stat-label">Evenimente</span>
                    </div>
                    <div class="sleek-stat-card">
                        <span class="sleek-stat-value" id="stat-spent">-</span>
                        <span class="sleek-stat-label">Cheltuit</span>
                    </div>
                </div>

                <div class="sleek-menu-section">
                    <h2 class="sleek-menu-title">Bilete & Comenzi</h2>
                    <div class="sleek-menu-list">
                        <a href="/account/tickets" class="sleek-menu-item">
                            <div class="sleek-menu-icon tickets">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3>Biletele mele</h3>
                                <p>Vizualizeaza si descarca biletele</p>
                            </div>
                            <svg class="sleek-menu-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="/account/orders" class="sleek-menu-item">
                            <div class="sleek-menu-icon orders">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3>Comenzile mele</h3>
                                <p>Istoricul comenzilor</p>
                            </div>
                            <svg class="sleek-menu-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="/account/events" class="sleek-menu-item">
                            <div class="sleek-menu-icon events">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3>Evenimentele mele</h3>
                                <p>Evenimente la care vei participa</p>
                            </div>
                            <svg class="sleek-menu-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="sleek-menu-section">
                    <h2 class="sleek-menu-title">Magazin</h2>
                    <div class="sleek-menu-list">
                        <a href="/account/shop-orders" class="sleek-menu-item">
                            <div class="sleek-menu-icon shop">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3>Comenzi magazin</h3>
                                <p>Istoricul comenzilor din magazin</p>
                            </div>
                            <svg class="sleek-menu-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="/account/wishlist" class="sleek-menu-item">
                            <div class="sleek-menu-icon wishlist">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3>Lista de dorinte</h3>
                                <p>Produsele salvate</p>
                            </div>
                            <svg class="sleek-menu-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="/account/stock-alerts" class="sleek-menu-item">
                            <div class="sleek-menu-icon alerts">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3>Alerte stoc</h3>
                                <p>Notificari pentru produse</p>
                            </div>
                            <svg class="sleek-menu-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="sleek-menu-section">
                    <h2 class="sleek-menu-title">Setari</h2>
                    <div class="sleek-menu-list">
                        <a href="/account/profile" class="sleek-menu-item">
                            <div class="sleek-menu-icon profile">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3>Profil</h3>
                                <p>Editeaza datele personale</p>
                            </div>
                            <svg class="sleek-menu-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <button id="logout-btn" class="sleek-menu-item" style="width: 100%; text-align: left; background: none; border: none; cursor: pointer;">
                            <div class="sleek-menu-icon logout">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                            </div>
                            <div class="sleek-menu-content">
                                <h3 style="color: #ef4444;">Deconecteaza-te</h3>
                                <p>Iesi din cont</p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Setup logout handler
        document.getElementById('logout-btn')?.addEventListener('click', () => {
            localStorage.removeItem('client_token');
            localStorage.removeItem('client_name');
            this.clientToken = null;
            window.location.hash = '/login';
        });

        // Load stats
        this.loadDashboardStats();
    }

    private async loadDashboardStats(): Promise<void> {
        if (!this.apiClient) return;

        try {
            this.setAuthHeaders();
            const [ordersRes, ticketsRes] = await Promise.all([
                this.apiClient.get('/client/orders').catch(() => ({ data: { data: { orders: [] } } })),
                this.apiClient.get('/client/tickets').catch(() => ({ data: { data: { tickets: [] } } }))
            ]);

            const orders = ordersRes.data.data?.orders || [];
            const tickets = ticketsRes.data.data?.tickets || [];

            const totalSpent = orders.reduce((sum: number, o: any) => sum + (o.total || 0), 0);
            const uniqueEvents = new Set(tickets.map((t: any) => t.event?.id)).size;

            document.getElementById('stat-tickets')!.textContent = tickets.length.toString();
            document.getElementById('stat-orders')!.textContent = orders.length.toString();
            document.getElementById('stat-events')!.textContent = uniqueEvents.toString();
            document.getElementById('stat-spent')!.textContent = this.formatCurrency(totalSpent).replace(/\s/g, '');
        } catch (e) {
            console.error('Failed to load stats', e);
        }
    }

    // ========================================
    // TICKETS PAGE - App-like Ticket List
    // ========================================
    async renderTickets(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById('tickets-list');
        if (!container) return;

        container.innerHTML = `
            <style>
                .sleek-page {
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 1.5rem 1rem;
                }

                @media (min-width: 768px) {
                    .sleek-page {
                        padding: 2rem;
                    }
                }

                .sleek-page-header {
                    margin-bottom: 1.5rem;
                }

                .sleek-page-title {
                    font-size: 1.75rem;
                    font-weight: 700;
                    color: var(--sleek-text);
                    margin: 0;
                }

                .sleek-page-subtitle {
                    color: var(--sleek-text-muted);
                    font-size: 0.9rem;
                    margin-top: 0.25rem;
                }

                .sleek-ticket-card {
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    overflow: hidden;
                    margin-bottom: 1rem;
                    transition: var(--sleek-transition);
                }

                .sleek-ticket-card:hover {
                    border-color: var(--sleek-border-light);
                }

                .sleek-ticket-header {
                    display: flex;
                    gap: 1rem;
                    padding: 1.25rem;
                }

                .sleek-ticket-image {
                    width: 80px;
                    height: 80px;
                    border-radius: var(--sleek-radius-sm);
                    object-fit: cover;
                    background: var(--sleek-surface-elevated);
                    flex-shrink: 0;
                }

                @media (min-width: 768px) {
                    .sleek-ticket-image {
                        width: 100px;
                        height: 100px;
                    }
                }

                .sleek-ticket-info {
                    flex: 1;
                    min-width: 0;
                }

                .sleek-ticket-event {
                    font-weight: 600;
                    font-size: 1rem;
                    color: var(--sleek-text);
                    margin-bottom: 0.375rem;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }

                .sleek-ticket-meta {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                    font-size: 0.8rem;
                    color: var(--sleek-text-muted);
                }

                .sleek-ticket-meta span {
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                }

                .sleek-ticket-meta svg {
                    width: 14px;
                    height: 14px;
                }

                .sleek-ticket-footer {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0.875rem 1.25rem;
                    background: var(--sleek-surface-elevated);
                    border-top: 1px solid var(--sleek-border);
                }

                .sleek-ticket-type {
                    font-size: 0.8rem;
                    color: var(--sleek-text-muted);
                }

                .sleek-ticket-type strong {
                    color: var(--sleek-text);
                    font-weight: 600;
                }

                .sleek-ticket-status {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.375rem;
                    padding: 0.375rem 0.75rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                    border-radius: 50px;
                    text-transform: uppercase;
                }

                .sleek-ticket-status.valid {
                    background: rgba(16, 185, 129, 0.15);
                    color: #10b981;
                }

                .sleek-ticket-status.used {
                    background: rgba(100, 116, 139, 0.15);
                    color: #64748b;
                }

                .sleek-empty-state {
                    text-align: center;
                    padding: 4rem 2rem;
                }

                .sleek-empty-icon {
                    width: 80px;
                    height: 80px;
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1.5rem;
                }

                .sleek-empty-icon svg {
                    width: 36px;
                    height: 36px;
                    color: var(--sleek-text-subtle);
                }

                .sleek-empty-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: var(--sleek-text);
                    margin-bottom: 0.5rem;
                }

                .sleek-empty-desc {
                    color: var(--sleek-text-muted);
                    margin-bottom: 1.5rem;
                }

                .sleek-loading {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 4rem;
                }

                .sleek-spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid var(--sleek-border);
                    border-top-color: var(--sleek-gradient-start);
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }

                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>

            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Biletele mele</h1>
                    <p class="sleek-page-subtitle">Toate biletele tale intr-un singur loc</p>
                </div>
                <div id="tickets-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadTicketsContent();
    }

    private async loadTicketsContent(): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('tickets-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/tickets');
            const tickets = response.data.data?.tickets || [];

            if (tickets.length === 0) {
                contentEl.innerHTML = `
                    <div class="sleek-empty-state">
                        <div class="sleek-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <h2 class="sleek-empty-title">Niciun bilet inca</h2>
                        <p class="sleek-empty-desc">Cumpara primul tau bilet pentru a-l vedea aici</p>
                        <a href="/events" class="sleek-btn-primary">Descopera evenimente</a>
                    </div>
                `;
                return;
            }

            contentEl.innerHTML = tickets.map((ticket: any) => `
                <div class="sleek-ticket-card">
                    <div class="sleek-ticket-header">
                        ${ticket.event?.image ? `<img src="${ticket.event.image}" alt="" class="sleek-ticket-image">` : `<div class="sleek-ticket-image"></div>`}
                        <div class="sleek-ticket-info">
                            <h3 class="sleek-ticket-event">${ticket.event?.name || 'Event'}</h3>
                            <div class="sleek-ticket-meta">
                                <span>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    ${this.formatDate(ticket.event?.date || '')}
                                </span>
                                <span>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    </svg>
                                    ${ticket.event?.venue || 'TBA'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="sleek-ticket-footer">
                        <div class="sleek-ticket-type">
                            <strong>${ticket.ticket_type}</strong> &bull; #${ticket.ticket_number}
                        </div>
                        <span class="sleek-ticket-status ${ticket.status}">${ticket.status === 'valid' ? 'Valid' : 'Folosit'}</span>
                    </div>
                </div>
            `).join('');
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea biletelor.</p>';
        }
    }

    // ========================================
    // ORDERS PAGE
    // ========================================
    async renderOrders(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById('orders-list');
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Comenzile mele</h1>
                    <p class="sleek-page-subtitle">Istoricul achizitiilor tale</p>
                </div>
                <div id="orders-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadOrdersContent();
    }

    private async loadOrdersContent(): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('orders-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/orders');
            const orders = response.data.data?.orders || [];

            if (orders.length === 0) {
                contentEl.innerHTML = `
                    <div class="sleek-empty-state">
                        <div class="sleek-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <h2 class="sleek-empty-title">Nicio comanda inca</h2>
                        <p class="sleek-empty-desc">Achizitioneaza bilete pentru a vedea comenzile aici</p>
                        <a href="/events" class="sleek-btn-primary">Descopera evenimente</a>
                    </div>
                `;
                return;
            }

            contentEl.innerHTML = `
                <style>
                    .sleek-order-card {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.25rem;
                        margin-bottom: 1rem;
                        display: block;
                        text-decoration: none;
                        color: inherit;
                        transition: var(--sleek-transition);
                    }

                    .sleek-order-card:hover {
                        border-color: var(--sleek-border-light);
                        background: var(--sleek-surface-elevated);
                    }

                    .sleek-order-top {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin-bottom: 0.75rem;
                    }

                    .sleek-order-number {
                        font-weight: 600;
                        color: var(--sleek-text);
                    }

                    .sleek-order-date {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                    }

                    .sleek-order-status {
                        padding: 0.25rem 0.625rem;
                        font-size: 0.7rem;
                        font-weight: 600;
                        border-radius: 50px;
                        text-transform: uppercase;
                    }

                    .sleek-order-status.completed {
                        background: rgba(16, 185, 129, 0.15);
                        color: #10b981;
                    }

                    .sleek-order-status.pending {
                        background: rgba(245, 158, 11, 0.15);
                        color: #f59e0b;
                    }

                    .sleek-order-events {
                        font-size: 0.85rem;
                        color: var(--sleek-text-muted);
                        margin-bottom: 0.75rem;
                    }

                    .sleek-order-bottom {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding-top: 0.75rem;
                        border-top: 1px solid var(--sleek-border);
                    }

                    .sleek-order-tickets {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                    }

                    .sleek-order-total {
                        font-weight: 700;
                        color: var(--sleek-text);
                    }
                </style>
                ${orders.map((order: any) => `
                    <a href="/account/orders/${order.id}" class="sleek-order-card">
                        <div class="sleek-order-top">
                            <div>
                                <div class="sleek-order-number">#${order.order_number}</div>
                                <div class="sleek-order-date">${this.formatDate(order.created_at)}</div>
                            </div>
                            <span class="sleek-order-status ${order.status}">${order.status}</span>
                        </div>
                        <div class="sleek-order-events">${order.events?.join(', ') || 'Multiple events'}</div>
                        <div class="sleek-order-bottom">
                            <span class="sleek-order-tickets">${order.tickets_count || 0} bilet(e)</span>
                            <span class="sleek-order-total">${this.formatCurrency(order.total, order.currency)}</span>
                        </div>
                    </a>
                `).join('')}
            `;
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea comenzilor.</p>';
        }
    }

    // ========================================
    // MY EVENTS PAGE
    // ========================================
    async renderMyEvents(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById('my-events-list');
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Evenimentele mele</h1>
                    <p class="sleek-page-subtitle">Evenimente la care vei participa</p>
                </div>
                <div id="events-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadMyEventsContent();
    }

    private async loadMyEventsContent(): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('events-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/upcoming-events');
            const events = response.data.data?.events || [];

            if (events.length === 0) {
                contentEl.innerHTML = `
                    <div class="sleek-empty-state">
                        <div class="sleek-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h2 class="sleek-empty-title">Niciun eveniment viitor</h2>
                        <p class="sleek-empty-desc">Cumpara bilete pentru a vedea evenimentele aici</p>
                        <a href="/events" class="sleek-btn-primary">Descopera evenimente</a>
                    </div>
                `;
                return;
            }

            contentEl.innerHTML = `
                <style>
                    .sleek-event-card {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        overflow: hidden;
                        margin-bottom: 1rem;
                    }

                    .sleek-event-banner {
                        position: relative;
                        height: 140px;
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    }

                    .sleek-event-banner img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }

                    .sleek-event-date-badge {
                        position: absolute;
                        top: 1rem;
                        left: 1rem;
                        background: rgba(0,0,0,0.7);
                        backdrop-filter: blur(10px);
                        padding: 0.5rem 0.75rem;
                        border-radius: var(--sleek-radius-xs);
                        text-align: center;
                    }

                    .sleek-event-date-day {
                        font-size: 1.5rem;
                        font-weight: 700;
                        color: white;
                        line-height: 1;
                    }

                    .sleek-event-date-month {
                        font-size: 0.7rem;
                        color: rgba(255,255,255,0.8);
                        text-transform: uppercase;
                    }

                    .sleek-event-body {
                        padding: 1.25rem;
                    }

                    .sleek-event-title {
                        font-size: 1.1rem;
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 0.5rem;
                    }

                    .sleek-event-venue {
                        font-size: 0.85rem;
                        color: var(--sleek-text-muted);
                        display: flex;
                        align-items: center;
                        gap: 0.375rem;
                    }

                    .sleek-event-venue svg {
                        width: 14px;
                        height: 14px;
                    }

                    .sleek-event-tickets-badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 0.375rem;
                        margin-top: 0.75rem;
                        padding: 0.375rem 0.75rem;
                        background: var(--sleek-glow);
                        color: var(--sleek-gradient-start);
                        font-size: 0.8rem;
                        font-weight: 600;
                        border-radius: 50px;
                    }
                </style>
                ${events.map((event: any) => {
                    const date = new Date(event.date);
                    return `
                        <div class="sleek-event-card">
                            <div class="sleek-event-banner">
                                ${event.image ? `<img src="${event.image}" alt="">` : ''}
                                <div class="sleek-event-date-badge">
                                    <div class="sleek-event-date-day">${date.getDate()}</div>
                                    <div class="sleek-event-date-month">${date.toLocaleDateString('ro-RO', { month: 'short' })}</div>
                                </div>
                            </div>
                            <div class="sleek-event-body">
                                <h3 class="sleek-event-title">${event.name}</h3>
                                <div class="sleek-event-venue">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    </svg>
                                    ${event.venue}${event.address ? `, ${event.address}` : ''}
                                </div>
                                <span class="sleek-event-tickets-badge">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                    </svg>
                                    ${event.tickets_count} bilet(e)
                                </span>
                            </div>
                        </div>
                    `;
                }).join('')}
            `;
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea evenimentelor.</p>';
        }
    }

    // ========================================
    // PROFILE PAGE
    // ========================================
    async renderProfile(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById('profile-form');
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Profil</h1>
                    <p class="sleek-page-subtitle">Gestioneaza datele tale personale</p>
                </div>
                <div id="profile-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadProfileContent();
    }

    private async loadProfileContent(): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('profile-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/profile');
            const profile = response.data.data;

            contentEl.innerHTML = `
                <style>
                    .sleek-profile-form {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                    }

                    .sleek-form-section {
                        margin-bottom: 2rem;
                    }

                    .sleek-form-section:last-child {
                        margin-bottom: 0;
                    }

                    .sleek-form-section-title {
                        font-size: 0.8rem;
                        font-weight: 600;
                        color: var(--sleek-text-muted);
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                        margin-bottom: 1rem;
                        padding-bottom: 0.5rem;
                        border-bottom: 1px solid var(--sleek-border);
                    }

                    .sleek-form-row {
                        display: grid;
                        gap: 1rem;
                        margin-bottom: 1rem;
                    }

                    @media (min-width: 768px) {
                        .sleek-form-row.two-col {
                            grid-template-columns: 1fr 1fr;
                        }
                    }

                    .sleek-success-msg {
                        background: rgba(16, 185, 129, 0.1);
                        border: 1px solid rgba(16, 185, 129, 0.3);
                        color: #10b981;
                        padding: 0.75rem 1rem;
                        border-radius: var(--sleek-radius-xs);
                        font-size: 0.875rem;
                        margin-bottom: 1rem;
                        display: none;
                    }
                </style>

                <div class="sleek-profile-form">
                    <div id="profile-success" class="sleek-success-msg">Profil actualizat cu succes!</div>
                    <div id="profile-error" class="sleek-error-msg"></div>

                    <form id="sleek-profile-form">
                        <div class="sleek-form-section">
                            <h3 class="sleek-form-section-title">Informatii personale</h3>
                            <div class="sleek-form-group">
                                <label class="sleek-form-label">Nume complet</label>
                                <input type="text" name="name" value="${profile.name || ''}" class="sleek-form-input" required>
                            </div>
                            <div class="sleek-form-row two-col">
                                <div class="sleek-form-group">
                                    <label class="sleek-form-label">Email</label>
                                    <input type="email" name="email" value="${profile.email || ''}" class="sleek-form-input" required>
                                </div>
                                <div class="sleek-form-group">
                                    <label class="sleek-form-label">Telefon</label>
                                    <input type="tel" name="phone" value="${profile.phone || ''}" class="sleek-form-input">
                                </div>
                            </div>
                        </div>

                        <div class="sleek-form-section">
                            <h3 class="sleek-form-section-title">Schimba parola</h3>
                            <div class="sleek-form-group">
                                <label class="sleek-form-label">Parola curenta</label>
                                <input type="password" name="current_password" class="sleek-form-input" placeholder="Lasa gol pentru a pastra parola actuala">
                            </div>
                            <div class="sleek-form-row two-col">
                                <div class="sleek-form-group">
                                    <label class="sleek-form-label">Parola noua</label>
                                    <input type="password" name="new_password" class="sleek-form-input" placeholder="Minim 8 caractere">
                                </div>
                                <div class="sleek-form-group">
                                    <label class="sleek-form-label">Confirma parola</label>
                                    <input type="password" name="new_password_confirmation" class="sleek-form-input" placeholder="Repeta parola noua">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="sleek-btn-primary" style="width: 100%;">
                            Salveaza modificarile
                        </button>
                    </form>
                </div>
            `;

            // Setup form handler
            document.getElementById('sleek-profile-form')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target as HTMLFormElement;
                const formData = new FormData(form);
                const successEl = document.getElementById('profile-success');
                const errorEl = document.getElementById('profile-error');

                if (this.apiClient) {
                    try {
                        this.setAuthHeaders();
                        await this.apiClient.put('/client/profile', {
                            name: formData.get('name'),
                            email: formData.get('email'),
                            phone: formData.get('phone'),
                            current_password: formData.get('current_password'),
                            new_password: formData.get('new_password'),
                            new_password_confirmation: formData.get('new_password_confirmation'),
                        });

                        localStorage.setItem('client_name', formData.get('name') as string);
                        if (successEl) {
                            successEl.style.display = 'block';
                            setTimeout(() => { successEl.style.display = 'none'; }, 3000);
                        }
                        if (errorEl) errorEl.style.display = 'none';
                    } catch (error: any) {
                        if (errorEl) {
                            errorEl.textContent = error.response?.data?.message || 'Eroare la actualizarea profilului.';
                            errorEl.style.display = 'block';
                        }
                        if (successEl) successEl.style.display = 'none';
                    }
                }
            });
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea profilului.</p>';
        }
    }

    // Placeholder methods for cart/checkout/order (keeping original functionality)
    renderCart(): void {}
    renderCheckout(): void {}
    renderOrderConfirmation(orderNumber: string): void {}
    renderOrderDetail(id: string): void {}

    // ========================================
    // SHOP ORDERS PAGE
    // ========================================
    async renderShopOrders(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById('shop-orders-list');
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Comenzi magazin</h1>
                    <p class="sleek-page-subtitle">Istoricul comenzilor tale din magazin</p>
                </div>
                <div id="shop-orders-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadShopOrdersContent();
    }

    private async loadShopOrdersContent(): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('shop-orders-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/account/shop-orders');
            const orders = response.data.data?.orders || [];

            if (orders.length === 0) {
                contentEl.innerHTML = `
                    <div class="sleek-empty-state">
                        <div class="sleek-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                        <h2 class="sleek-empty-title">Nicio comanda din magazin</h2>
                        <p class="sleek-empty-desc">Cumpara produse pentru a le vedea aici</p>
                        <a href="/shop" class="sleek-btn-primary">Descopera produse</a>
                    </div>
                `;
                return;
            }

            contentEl.innerHTML = `
                <style>
                    .shop-order-card {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.25rem;
                        margin-bottom: 1rem;
                        display: block;
                        text-decoration: none;
                        color: inherit;
                        transition: var(--sleek-transition);
                    }
                    .shop-order-card:hover {
                        border-color: var(--sleek-border-light);
                        background: var(--sleek-surface-elevated);
                    }
                    .shop-order-top {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin-bottom: 0.75rem;
                    }
                    .shop-order-number {
                        font-weight: 600;
                        color: var(--sleek-text);
                    }
                    .shop-order-date {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                    }
                    .shop-order-status {
                        padding: 0.25rem 0.625rem;
                        font-size: 0.7rem;
                        font-weight: 600;
                        border-radius: 50px;
                        text-transform: uppercase;
                    }
                    .shop-order-status.paid, .shop-order-status.shipped, .shop-order-status.delivered {
                        background: rgba(16, 185, 129, 0.15);
                        color: #10b981;
                    }
                    .shop-order-status.pending, .shop-order-status.processing {
                        background: rgba(245, 158, 11, 0.15);
                        color: #f59e0b;
                    }
                    .shop-order-status.cancelled, .shop-order-status.refunded {
                        background: rgba(239, 68, 68, 0.15);
                        color: #ef4444;
                    }
                    .shop-order-items {
                        display: flex;
                        gap: 0.5rem;
                        margin-bottom: 0.75rem;
                        overflow: hidden;
                    }
                    .shop-order-item-thumb {
                        width: 50px;
                        height: 50px;
                        border-radius: 0.375rem;
                        object-fit: cover;
                        background: #f3f4f6;
                    }
                    .shop-order-bottom {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding-top: 0.75rem;
                        border-top: 1px solid var(--sleek-border);
                    }
                    .shop-order-items-count {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                    }
                    .shop-order-total {
                        font-weight: 700;
                        color: var(--sleek-text);
                    }
                </style>
                ${orders.map((order: any) => `
                    <a href="/account/shop-orders/${order.id}" class="shop-order-card">
                        <div class="shop-order-top">
                            <div>
                                <div class="shop-order-number">${order.order_number}</div>
                                <div class="shop-order-date">${this.formatDate(order.created_at)}</div>
                            </div>
                            <span class="shop-order-status ${order.status}">${this.getShopOrderStatusLabel(order.status)}</span>
                        </div>
                        <div class="shop-order-items">
                            ${(order.items || []).slice(0, 4).map((item: any) => `
                                <img src="${item.image_url || '/placeholder.jpg'}" alt="${item.title}" class="shop-order-item-thumb">
                            `).join('')}
                            ${(order.items || []).length > 4 ? `<div class="shop-order-item-thumb" style="display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: #6b7280;">+${order.items.length - 4}</div>` : ''}
                        </div>
                        <div class="shop-order-bottom">
                            <span class="shop-order-items-count">${order.items_count || order.items?.length || 0} produs(e)</span>
                            <span class="shop-order-total">${this.formatCurrency(order.total_cents / 100, order.currency)}</span>
                        </div>
                    </a>
                `).join('')}
            `;
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea comenzilor.</p>';
        }
    }

    private getShopOrderStatusLabel(status: string): string {
        const labels: Record<string, string> = {
            'pending': 'In asteptare',
            'paid': 'Platita',
            'processing': 'In procesare',
            'shipped': 'Expediata',
            'delivered': 'Livrata',
            'cancelled': 'Anulata',
            'refunded': 'Rambursata',
        };
        return labels[status] || status;
    }

    // ========================================
    // SHOP ORDER DETAIL PAGE
    // ========================================
    async renderShopOrderDetail(orderId: string): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById(`shop-order-detail-${orderId}`);
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <a href="/account/shop-orders" style="color: var(--sleek-text-muted); text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Inapoi la comenzi
                    </a>
                    <h1 class="sleek-page-title">Detalii comanda</h1>
                </div>
                <div id="shop-order-detail-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadShopOrderDetailContent(orderId);
    }

    private async loadShopOrderDetailContent(orderId: string): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('shop-order-detail-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get(`/account/shop-orders/${orderId}`);
            const order = response.data.data;

            contentEl.innerHTML = `
                <style>
                    .order-detail-grid {
                        display: grid;
                        gap: 1.5rem;
                    }
                    @media (min-width: 768px) {
                        .order-detail-grid {
                            grid-template-columns: 2fr 1fr;
                        }
                    }
                    .order-detail-section {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.25rem;
                    }
                    .order-detail-title {
                        font-weight: 600;
                        font-size: 1rem;
                        color: var(--sleek-text);
                        margin-bottom: 1rem;
                        padding-bottom: 0.75rem;
                        border-bottom: 1px solid var(--sleek-border);
                    }
                    .order-item {
                        display: flex;
                        gap: 1rem;
                        padding: 1rem 0;
                        border-bottom: 1px solid var(--sleek-border);
                    }
                    .order-item:last-child {
                        border-bottom: none;
                    }
                    .order-item-image {
                        width: 80px;
                        height: 80px;
                        border-radius: 0.5rem;
                        object-fit: cover;
                        background: #f3f4f6;
                    }
                    .order-item-info {
                        flex: 1;
                    }
                    .order-item-title {
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 0.25rem;
                    }
                    .order-item-variant {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                        margin-bottom: 0.25rem;
                    }
                    .order-item-qty {
                        font-size: 0.85rem;
                        color: var(--sleek-text-muted);
                    }
                    .order-item-price {
                        font-weight: 600;
                        color: var(--sleek-text);
                        text-align: right;
                    }
                    .order-summary-row {
                        display: flex;
                        justify-content: space-between;
                        padding: 0.5rem 0;
                        font-size: 0.9rem;
                    }
                    .order-summary-row.total {
                        font-weight: 700;
                        font-size: 1.1rem;
                        border-top: 1px solid var(--sleek-border);
                        padding-top: 1rem;
                        margin-top: 0.5rem;
                    }
                    .order-info-row {
                        display: flex;
                        justify-content: space-between;
                        padding: 0.5rem 0;
                        font-size: 0.9rem;
                        border-bottom: 1px solid var(--sleek-border);
                    }
                    .order-info-row:last-child {
                        border-bottom: none;
                    }
                    .order-info-label {
                        color: var(--sleek-text-muted);
                    }
                    .order-info-value {
                        font-weight: 500;
                        color: var(--sleek-text);
                    }
                    .tracking-link {
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        margin-top: 1rem;
                        padding: 0.75rem 1rem;
                        background: var(--sleek-glow);
                        color: var(--sleek-gradient-start);
                        border-radius: 0.5rem;
                        text-decoration: none;
                        font-weight: 500;
                        font-size: 0.9rem;
                    }
                </style>

                <div class="order-detail-grid">
                    <div>
                        <div class="order-detail-section">
                            <h3 class="order-detail-title">Produse comandate</h3>
                            ${(order.items || []).map((item: any) => `
                                <div class="order-item">
                                    <img src="${item.image_url || '/placeholder.jpg'}" alt="${item.title}" class="order-item-image">
                                    <div class="order-item-info">
                                        <div class="order-item-title">${item.title}</div>
                                        ${item.variant_name ? `<div class="order-item-variant">${item.variant_name}</div>` : ''}
                                        <div class="order-item-qty">Cantitate: ${item.quantity}</div>
                                    </div>
                                    <div class="order-item-price">${this.formatCurrency(item.total_cents / 100, order.currency)}</div>
                                </div>
                            `).join('')}
                        </div>

                        ${order.shipping_address ? `
                            <div class="order-detail-section" style="margin-top: 1rem;">
                                <h3 class="order-detail-title">Adresa de livrare</h3>
                                <p style="color: var(--sleek-text); line-height: 1.6;">
                                    ${order.shipping_address.name}<br>
                                    ${order.shipping_address.address}<br>
                                    ${order.shipping_address.city}, ${order.shipping_address.postal_code}<br>
                                    ${order.shipping_address.country}
                                </p>
                                ${order.tracking_number ? `
                                    <a href="${order.tracking_url || '#'}" target="_blank" class="tracking-link">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        Urmareste coletul: ${order.tracking_number}
                                    </a>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>

                    <div>
                        <div class="order-detail-section">
                            <h3 class="order-detail-title">Detalii comanda</h3>
                            <div class="order-info-row">
                                <span class="order-info-label">Numar comanda</span>
                                <span class="order-info-value">${order.order_number}</span>
                            </div>
                            <div class="order-info-row">
                                <span class="order-info-label">Data</span>
                                <span class="order-info-value">${this.formatDate(order.created_at, true)}</span>
                            </div>
                            <div class="order-info-row">
                                <span class="order-info-label">Status</span>
                                <span class="shop-order-status ${order.status}" style="padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 50px;">${this.getShopOrderStatusLabel(order.status)}</span>
                            </div>
                            <div class="order-info-row">
                                <span class="order-info-label">Metoda plata</span>
                                <span class="order-info-value">${order.payment_method || 'Card'}</span>
                            </div>
                        </div>

                        <div class="order-detail-section" style="margin-top: 1rem;">
                            <h3 class="order-detail-title">Sumar</h3>
                            <div class="order-summary-row">
                                <span>Subtotal</span>
                                <span>${this.formatCurrency(order.subtotal_cents / 100, order.currency)}</span>
                            </div>
                            ${order.discount_cents > 0 ? `
                                <div class="order-summary-row" style="color: #10b981;">
                                    <span>Reducere</span>
                                    <span>-${this.formatCurrency(order.discount_cents / 100, order.currency)}</span>
                                </div>
                            ` : ''}
                            ${order.shipping_cents > 0 ? `
                                <div class="order-summary-row">
                                    <span>Livrare</span>
                                    <span>${this.formatCurrency(order.shipping_cents / 100, order.currency)}</span>
                                </div>
                            ` : ''}
                            ${order.tax_cents > 0 ? `
                                <div class="order-summary-row">
                                    <span>TVA</span>
                                    <span>${this.formatCurrency(order.tax_cents / 100, order.currency)}</span>
                                </div>
                            ` : ''}
                            <div class="order-summary-row total">
                                <span>Total</span>
                                <span>${this.formatCurrency(order.total_cents / 100, order.currency)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea detaliilor comenzii.</p>';
        }
    }

    // ========================================
    // WISHLIST PAGE
    // ========================================
    async renderWishlist(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById('wishlist-container');
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Lista de dorinte</h1>
                    <p class="sleek-page-subtitle">Produsele tale salvate</p>
                </div>
                <div id="wishlist-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadWishlistContent();
    }

    private async loadWishlistContent(): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('wishlist-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/shop/wishlist');
            const items = response.data.data?.items || [];

            if (items.length === 0) {
                contentEl.innerHTML = `
                    <div class="sleek-empty-state">
                        <div class="sleek-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <h2 class="sleek-empty-title">Lista de dorinte este goala</h2>
                        <p class="sleek-empty-desc">Salveaza produsele preferate pentru mai tarziu</p>
                        <a href="/shop" class="sleek-btn-primary">Descopera produse</a>
                    </div>
                `;
                return;
            }

            contentEl.innerHTML = `
                <style>
                    .wishlist-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                        gap: 1.5rem;
                    }
                    .wishlist-item {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        overflow: hidden;
                        transition: var(--sleek-transition);
                    }
                    .wishlist-item:hover {
                        border-color: var(--sleek-border-light);
                    }
                    .wishlist-item-image {
                        position: relative;
                        aspect-ratio: 1;
                        background: #f3f4f6;
                    }
                    .wishlist-item-image img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }
                    .wishlist-remove-btn {
                        position: absolute;
                        top: 0.5rem;
                        right: 0.5rem;
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        background: white;
                        border: none;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    }
                    .wishlist-remove-btn:hover {
                        background: #fef2f2;
                        color: #ef4444;
                    }
                    .wishlist-item-body {
                        padding: 1rem;
                    }
                    .wishlist-item-title {
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 0.5rem;
                        display: -webkit-box;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                    }
                    .wishlist-item-price {
                        font-weight: 700;
                        color: var(--sleek-text);
                        margin-bottom: 0.75rem;
                    }
                    .wishlist-item-stock {
                        font-size: 0.8rem;
                        margin-bottom: 0.75rem;
                    }
                    .wishlist-item-stock.in-stock {
                        color: #10b981;
                    }
                    .wishlist-item-stock.out-of-stock {
                        color: #ef4444;
                    }
                    .wishlist-add-cart-btn {
                        width: 100%;
                        padding: 0.75rem;
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                        color: white;
                        border: none;
                        border-radius: 0.375rem;
                        font-weight: 600;
                        cursor: pointer;
                        transition: var(--sleek-transition);
                    }
                    .wishlist-add-cart-btn:hover {
                        transform: translateY(-1px);
                    }
                    .wishlist-add-cart-btn:disabled {
                        opacity: 0.5;
                        cursor: not-allowed;
                        transform: none;
                    }
                </style>
                <div class="wishlist-grid">
                    ${items.map((item: any) => `
                        <div class="wishlist-item" data-item-id="${item.id}">
                            <div class="wishlist-item-image">
                                <a href="/shop/product/${item.product?.slug}">
                                    <img src="${item.product?.image_url || '/placeholder.jpg'}" alt="${item.product?.title}">
                                </a>
                                <button class="wishlist-remove-btn" data-item-id="${item.id}">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="wishlist-item-body">
                                <a href="/shop/product/${item.product?.slug}" style="text-decoration: none; color: inherit;">
                                    <h3 class="wishlist-item-title">${item.product?.title}</h3>
                                </a>
                                <div class="wishlist-item-price">${this.formatCurrency((item.product?.display_price || item.product?.price_cents) / 100, item.product?.currency)}</div>
                                <div class="wishlist-item-stock ${item.product?.is_in_stock ? 'in-stock' : 'out-of-stock'}">
                                    ${item.product?.is_in_stock ? 'In stoc' : 'Stoc epuizat'}
                                </div>
                                <button class="wishlist-add-cart-btn" data-product-id="${item.product_id}" ${!item.product?.is_in_stock ? 'disabled' : ''}>
                                    ${item.product?.is_in_stock ? 'Adauga in cos' : 'Indisponibil'}
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;

            // Bind remove buttons
            contentEl.querySelectorAll('.wishlist-remove-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const itemId = btn.getAttribute('data-item-id');
                    if (itemId && this.apiClient) {
                        try {
                            this.setAuthHeaders();
                            await this.apiClient.delete(`/shop/wishlist/items/${itemId}`);
                            const itemEl = contentEl.querySelector(`[data-item-id="${itemId}"]`);
                            itemEl?.remove();

                            // Check if list is empty
                            if (contentEl.querySelectorAll('.wishlist-item').length === 0) {
                                this.loadWishlistContent();
                            }
                        } catch (e) {
                            console.error('Failed to remove wishlist item:', e);
                        }
                    }
                });
            });

            // Bind add to cart buttons
            contentEl.querySelectorAll('.wishlist-add-cart-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const productId = btn.getAttribute('data-product-id');
                    if (productId && this.apiClient) {
                        try {
                            const sessionId = localStorage.getItem('shop_session_id') || 'shop-' + Math.random().toString(36).substr(2, 16);
                            localStorage.setItem('shop_session_id', sessionId);

                            await this.apiClient.post('/shop/cart/items', {
                                product_id: productId,
                                quantity: 1
                            }, {
                                headers: { 'X-Session-ID': sessionId }
                            });

                            btn.textContent = 'Adaugat!';
                            btn.style.background = '#10b981';
                            setTimeout(() => {
                                btn.textContent = 'Adauga in cos';
                                btn.style.background = '';
                            }, 2000);
                        } catch (e) {
                            console.error('Failed to add to cart:', e);
                        }
                    }
                });
            });
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea listei de dorinte.</p>';
        }
    }

    // ========================================
    // STOCK ALERTS PAGE
    // ========================================
    async renderStockAlerts(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.getElementById('stock-alerts-container');
        if (!container) return;

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Alerte stoc</h1>
                    <p class="sleek-page-subtitle">Vei fi notificat cand produsele revin in stoc</p>
                </div>
                <div id="stock-alerts-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadStockAlertsContent();
    }

    private async loadStockAlertsContent(): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('stock-alerts-content');
        if (!contentEl) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/shop/stock-alerts/my-alerts');
            const alerts = response.data.data?.alerts || [];

            if (alerts.length === 0) {
                contentEl.innerHTML = `
                    <div class="sleek-empty-state">
                        <div class="sleek-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <h2 class="sleek-empty-title">Nicio alerta activa</h2>
                        <p class="sleek-empty-desc">Aboneaza-te la produsele indisponibile pentru a fi notificat</p>
                        <a href="/shop" class="sleek-btn-primary">Descopera produse</a>
                    </div>
                `;
                return;
            }

            contentEl.innerHTML = `
                <style>
                    .alerts-list {
                        display: flex;
                        flex-direction: column;
                        gap: 1rem;
                    }
                    .alert-item {
                        display: flex;
                        gap: 1rem;
                        padding: 1rem;
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        align-items: center;
                    }
                    .alert-item-image {
                        width: 60px;
                        height: 60px;
                        border-radius: 0.375rem;
                        object-fit: cover;
                        background: #f3f4f6;
                    }
                    .alert-item-info {
                        flex: 1;
                    }
                    .alert-item-title {
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 0.25rem;
                    }
                    .alert-item-date {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                    }
                    .alert-remove-btn {
                        padding: 0.5rem 1rem;
                        border: 1px solid var(--sleek-border);
                        border-radius: 0.375rem;
                        background: white;
                        color: var(--sleek-text-muted);
                        cursor: pointer;
                        font-size: 0.8rem;
                    }
                    .alert-remove-btn:hover {
                        border-color: #ef4444;
                        color: #ef4444;
                    }
                </style>
                <div class="alerts-list">
                    ${alerts.map((alert: any) => `
                        <div class="alert-item" data-alert-id="${alert.id}">
                            <img src="${alert.product?.image_url || '/placeholder.jpg'}" alt="${alert.product?.title}" class="alert-item-image">
                            <div class="alert-item-info">
                                <div class="alert-item-title">${alert.product?.title}</div>
                                <div class="alert-item-date">Abonat la ${this.formatDate(alert.created_at)}</div>
                            </div>
                            <button class="alert-remove-btn" data-alert-id="${alert.id}">Anuleaza</button>
                        </div>
                    `).join('')}
                </div>
            `;

            // Bind remove buttons
            contentEl.querySelectorAll('.alert-remove-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const alertId = btn.getAttribute('data-alert-id');
                    if (alertId && this.apiClient) {
                        try {
                            this.setAuthHeaders();
                            await this.apiClient.delete(`/shop/stock-alerts/${alertId}`);
                            const alertEl = contentEl.querySelector(`[data-alert-id="${alertId}"]`);
                            alertEl?.remove();

                            // Check if list is empty
                            if (contentEl.querySelectorAll('.alert-item').length === 0) {
                                this.loadStockAlertsContent();
                            }
                        } catch (e) {
                            console.error('Failed to remove stock alert:', e);
                        }
                    }
                });
            });
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea alertelor.</p>';
        }
    }

    // ========================================
    // SHOP CART PAGE
    // ========================================
    async renderShopCart(): Promise<void> {
        const container = document.getElementById('shop-cart-container');
        if (!container) return;

        const sessionId = localStorage.getItem('shop_session_id') || 'shop-' + Math.random().toString(36).substr(2, 16);
        localStorage.setItem('shop_session_id', sessionId);

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Cosul tau</h1>
                </div>
                <div id="shop-cart-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadShopCartContent(sessionId);
    }

    private async loadShopCartContent(sessionId: string): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('shop-cart-content');
        if (!contentEl) return;

        try {
            const response = await this.apiClient.get('/shop/cart', {
                headers: { 'X-Session-ID': sessionId }
            });
            const cart = response.data;

            if (!cart || !cart.items || cart.items.length === 0) {
                contentEl.innerHTML = `
                    <div class="sleek-empty-state">
                        <div class="sleek-empty-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h2 class="sleek-empty-title">Cosul este gol</h2>
                        <p class="sleek-empty-desc">Adauga produse in cos pentru a continua</p>
                        <a href="/shop" class="sleek-btn-primary">Descopera produse</a>
                    </div>
                `;
                return;
            }

            contentEl.innerHTML = `
                <style>
                    .cart-layout {
                        display: grid;
                        gap: 2rem;
                    }
                    @media (min-width: 1024px) {
                        .cart-layout {
                            grid-template-columns: 1fr 380px;
                        }
                    }
                    .cart-items {
                        display: flex;
                        flex-direction: column;
                        gap: 1rem;
                    }
                    .cart-item {
                        display: flex;
                        gap: 1rem;
                        padding: 1rem;
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                    }
                    .cart-item-image {
                        width: 100px;
                        height: 100px;
                        border-radius: 0.5rem;
                        object-fit: cover;
                        background: #f3f4f6;
                    }
                    .cart-item-info {
                        flex: 1;
                    }
                    .cart-item-title {
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 0.25rem;
                    }
                    .cart-item-variant {
                        font-size: 0.85rem;
                        color: var(--sleek-text-muted);
                        margin-bottom: 0.5rem;
                    }
                    .cart-item-price {
                        font-weight: 600;
                        color: var(--sleek-text);
                    }
                    .cart-item-qty {
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        margin-top: 0.75rem;
                    }
                    .cart-qty-btn {
                        width: 32px;
                        height: 32px;
                        border: 1px solid var(--sleek-border);
                        border-radius: 0.375rem;
                        background: white;
                        cursor: pointer;
                        font-size: 1rem;
                    }
                    .cart-qty-btn:hover {
                        border-color: var(--sleek-gradient-start);
                    }
                    .cart-qty-input {
                        width: 50px;
                        text-align: center;
                        border: 1px solid var(--sleek-border);
                        border-radius: 0.375rem;
                        padding: 0.375rem;
                    }
                    .cart-item-remove {
                        color: var(--sleek-text-muted);
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 0.5rem;
                    }
                    .cart-item-remove:hover {
                        color: #ef4444;
                    }
                    .cart-summary {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                        height: fit-content;
                        position: sticky;
                        top: 1rem;
                    }
                    .cart-summary-title {
                        font-weight: 600;
                        font-size: 1.1rem;
                        color: var(--sleek-text);
                        margin-bottom: 1rem;
                        padding-bottom: 1rem;
                        border-bottom: 1px solid var(--sleek-border);
                    }
                    .cart-summary-row {
                        display: flex;
                        justify-content: space-between;
                        padding: 0.5rem 0;
                        font-size: 0.9rem;
                    }
                    .cart-summary-row.total {
                        font-weight: 700;
                        font-size: 1.25rem;
                        border-top: 1px solid var(--sleek-border);
                        padding-top: 1rem;
                        margin-top: 0.5rem;
                    }
                    .cart-checkout-btn {
                        width: 100%;
                        padding: 1rem;
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                        color: white;
                        border: none;
                        border-radius: 0.5rem;
                        font-weight: 600;
                        font-size: 1rem;
                        cursor: pointer;
                        margin-top: 1.5rem;
                        transition: var(--sleek-transition);
                    }
                    .cart-checkout-btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px var(--sleek-glow);
                    }
                </style>
                <div class="cart-layout">
                    <div class="cart-items">
                        ${cart.items.map((item: any) => `
                            <div class="cart-item" data-item-id="${item.id}">
                                <img src="${item.image_url || '/placeholder.jpg'}" alt="${item.title}" class="cart-item-image">
                                <div class="cart-item-info">
                                    <div class="cart-item-title">${item.title}</div>
                                    ${item.variant_name ? `<div class="cart-item-variant">${item.variant_name}</div>` : ''}
                                    <div class="cart-item-price">${this.formatCurrency(item.unit_price, cart.currency)}</div>
                                    <div class="cart-item-qty">
                                        <button class="cart-qty-btn cart-qty-minus" data-item-id="${item.id}">-</button>
                                        <input type="number" class="cart-qty-input" data-item-id="${item.id}" value="${item.quantity}" min="1">
                                        <button class="cart-qty-btn cart-qty-plus" data-item-id="${item.id}">+</button>
                                    </div>
                                </div>
                                <button class="cart-item-remove" data-item-id="${item.id}">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <div class="cart-summary">
                        <h3 class="cart-summary-title">Sumar comanda</h3>
                        <div class="cart-summary-row">
                            <span>Subtotal</span>
                            <span>${this.formatCurrency(cart.subtotal, cart.currency)}</span>
                        </div>
                        ${cart.discount > 0 ? `
                            <div class="cart-summary-row" style="color: #10b981;">
                                <span>Reducere</span>
                                <span>-${this.formatCurrency(cart.discount, cart.currency)}</span>
                            </div>
                        ` : ''}
                        <div class="cart-summary-row total">
                            <span>Total</span>
                            <span>${this.formatCurrency(cart.total, cart.currency)}</span>
                        </div>
                        <button class="cart-checkout-btn" onclick="window.location.hash='/shop/checkout'">
                            Continua spre plata
                        </button>
                    </div>
                </div>
            `;

            // Bind quantity buttons
            contentEl.querySelectorAll('.cart-qty-minus').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const itemId = btn.getAttribute('data-item-id');
                    const input = contentEl.querySelector(`.cart-qty-input[data-item-id="${itemId}"]`) as HTMLInputElement;
                    if (input && parseInt(input.value) > 1) {
                        await this.updateCartItemQuantity(sessionId, itemId!, parseInt(input.value) - 1);
                    }
                });
            });

            contentEl.querySelectorAll('.cart-qty-plus').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const itemId = btn.getAttribute('data-item-id');
                    const input = contentEl.querySelector(`.cart-qty-input[data-item-id="${itemId}"]`) as HTMLInputElement;
                    if (input) {
                        await this.updateCartItemQuantity(sessionId, itemId!, parseInt(input.value) + 1);
                    }
                });
            });

            // Bind remove buttons
            contentEl.querySelectorAll('.cart-item-remove').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const itemId = btn.getAttribute('data-item-id');
                    if (itemId && this.apiClient) {
                        try {
                            await this.apiClient.delete(`/shop/cart/items/${itemId}`, {
                                headers: { 'X-Session-ID': sessionId }
                            });
                            this.loadShopCartContent(sessionId);
                        } catch (e) {
                            console.error('Failed to remove item:', e);
                        }
                    }
                });
            });
        } catch (e) {
            contentEl.innerHTML = '<p style="color: var(--sleek-error); text-align: center;">Eroare la incarcarea cosului.</p>';
        }
    }

    private async updateCartItemQuantity(sessionId: string, itemId: string, quantity: number): Promise<void> {
        if (!this.apiClient) return;

        try {
            await this.apiClient.put(`/shop/cart/items/${itemId}`, { quantity }, {
                headers: { 'X-Session-ID': sessionId }
            });
            this.loadShopCartContent(sessionId);
        } catch (e) {
            console.error('Failed to update quantity:', e);
        }
    }

    // ========================================
    // SHOP CHECKOUT PAGE
    // ========================================
    async renderShopCheckout(): Promise<void> {
        const container = document.getElementById('shop-checkout-container');
        if (!container) return;

        const sessionId = localStorage.getItem('shop_session_id');
        if (!sessionId) {
            window.location.hash = '/shop/cart';
            return;
        }

        container.innerHTML = `
            <div class="sleek-page sleek-animate-in">
                <div class="sleek-page-header">
                    <h1 class="sleek-page-title">Finalizare comanda</h1>
                </div>
                <div id="shop-checkout-content" class="sleek-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadShopCheckoutContent(sessionId);
    }

    private async loadShopCheckoutContent(sessionId: string): Promise<void> {
        if (!this.apiClient) return;

        const contentEl = document.getElementById('shop-checkout-content');
        if (!contentEl) return;

        try {
            // Initialize checkout session
            const initResponse = await this.apiClient.get(`/shop/checkout/initialize?session_id=${sessionId}`);

            const { cart, totals, requires_shipping, currency } = initResponse.data.data;

            if (!cart || !cart.items || cart.items.length === 0) {
                window.location.hash = '/shop/cart';
                return;
            }

            contentEl.innerHTML = `
                <style>
                    .checkout-layout {
                        display: grid;
                        gap: 2rem;
                    }
                    @media (min-width: 1024px) {
                        .checkout-layout {
                            grid-template-columns: 1fr 400px;
                        }
                    }
                    .checkout-section {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                        margin-bottom: 1.5rem;
                    }
                    .checkout-section-title {
                        font-size: 1.125rem;
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 1rem;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                    .checkout-form-row {
                        display: grid;
                        gap: 1rem;
                        margin-bottom: 1rem;
                    }
                    @media (min-width: 640px) {
                        .checkout-form-row.two-cols {
                            grid-template-columns: 1fr 1fr;
                        }
                    }
                    .checkout-form-group label {
                        display: block;
                        font-weight: 500;
                        color: var(--sleek-text);
                        margin-bottom: 0.5rem;
                        font-size: 0.875rem;
                    }
                    .checkout-form-group input,
                    .checkout-form-group select {
                        width: 100%;
                        padding: 0.75rem;
                        border: 1px solid var(--sleek-border);
                        border-radius: 0.5rem;
                        background: var(--sleek-bg);
                        color: var(--sleek-text);
                        font-size: 1rem;
                        transition: border-color 0.2s;
                    }
                    .checkout-form-group input:focus,
                    .checkout-form-group select:focus {
                        outline: none;
                        border-color: var(--sleek-primary);
                    }
                    .shipping-methods {
                        display: flex;
                        flex-direction: column;
                        gap: 0.75rem;
                    }
                    .shipping-method {
                        display: flex;
                        align-items: flex-start;
                        gap: 1rem;
                        padding: 1rem;
                        border: 2px solid var(--sleek-border);
                        border-radius: 0.75rem;
                        cursor: pointer;
                        transition: all 0.2s;
                    }
                    .shipping-method:hover {
                        border-color: var(--sleek-primary);
                    }
                    .shipping-method.selected {
                        border-color: var(--sleek-primary);
                        background: rgba(var(--sleek-primary-rgb), 0.05);
                    }
                    .shipping-method input {
                        margin-top: 0.25rem;
                    }
                    .shipping-method-info {
                        flex: 1;
                    }
                    .shipping-method-name {
                        font-weight: 600;
                        color: var(--sleek-text);
                    }
                    .shipping-method-estimate {
                        font-size: 0.875rem;
                        color: var(--sleek-primary);
                        margin-top: 0.25rem;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }
                    .shipping-method-estimate svg {
                        width: 16px;
                        height: 16px;
                    }
                    .shipping-method-description {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                        margin-top: 0.25rem;
                    }
                    .shipping-method-price {
                        font-weight: 600;
                        color: var(--sleek-text);
                        white-space: nowrap;
                    }
                    .shipping-method-price.free {
                        color: #10b981;
                    }
                    .checkout-summary {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                        position: sticky;
                        top: 1rem;
                    }
                    .checkout-summary-title {
                        font-size: 1.125rem;
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 1rem;
                        padding-bottom: 0.75rem;
                        border-bottom: 1px solid var(--sleek-border);
                    }
                    .checkout-summary-items {
                        max-height: 200px;
                        overflow-y: auto;
                        margin-bottom: 1rem;
                        padding-bottom: 1rem;
                        border-bottom: 1px solid var(--sleek-border);
                    }
                    .checkout-summary-item {
                        display: flex;
                        gap: 0.75rem;
                        margin-bottom: 0.75rem;
                    }
                    .checkout-summary-item:last-child {
                        margin-bottom: 0;
                    }
                    .checkout-summary-item-image {
                        width: 50px;
                        height: 50px;
                        border-radius: 0.5rem;
                        object-fit: cover;
                        background: #f3f4f6;
                    }
                    .checkout-summary-item-info {
                        flex: 1;
                    }
                    .checkout-summary-item-name {
                        font-size: 0.875rem;
                        font-weight: 500;
                        color: var(--sleek-text);
                    }
                    .checkout-summary-item-variant {
                        font-size: 0.75rem;
                        color: var(--sleek-text-muted);
                    }
                    .checkout-summary-item-qty {
                        font-size: 0.75rem;
                        color: var(--sleek-text-muted);
                    }
                    .checkout-summary-item-price {
                        font-size: 0.875rem;
                        font-weight: 500;
                        color: var(--sleek-text);
                        white-space: nowrap;
                    }
                    .checkout-summary-row {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 0.5rem;
                        font-size: 0.875rem;
                    }
                    .checkout-summary-row.total {
                        font-size: 1.125rem;
                        font-weight: 700;
                        color: var(--sleek-text);
                        margin-top: 0.75rem;
                        padding-top: 0.75rem;
                        border-top: 1px solid var(--sleek-border);
                    }
                    .checkout-submit-btn {
                        width: 100%;
                        padding: 1rem;
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                        color: white;
                        border: none;
                        border-radius: 0.75rem;
                        font-size: 1rem;
                        font-weight: 600;
                        cursor: pointer;
                        margin-top: 1rem;
                        transition: opacity 0.2s, transform 0.2s;
                    }
                    .checkout-submit-btn:hover:not(:disabled) {
                        opacity: 0.9;
                        transform: translateY(-1px);
                    }
                    .checkout-submit-btn:disabled {
                        opacity: 0.5;
                        cursor: not-allowed;
                    }
                    .checkout-secure-note {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 0.5rem;
                        margin-top: 1rem;
                        font-size: 0.75rem;
                        color: var(--sleek-text-muted);
                    }
                    .checkout-secure-note svg {
                        width: 14px;
                        height: 14px;
                    }
                    #shipping-methods-loading {
                        padding: 2rem;
                        text-align: center;
                        color: var(--sleek-text-muted);
                    }
                    #stripe-payment-element {
                        padding: 1rem;
                        border: 1px solid var(--sleek-border);
                        border-radius: 0.5rem;
                        background: var(--sleek-bg);
                        min-height: 100px;
                    }
                    #stripe-payment-errors {
                        color: #ef4444;
                        font-size: 0.875rem;
                        margin-top: 0.5rem;
                        display: none;
                    }
                </style>

                <form id="shop-checkout-form">
                    <div class="checkout-layout">
                        <div class="checkout-form-section">
                            <!-- Contact Info -->
                            <div class="checkout-section">
                                <h2 class="checkout-section-title">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Informații contact
                                </h2>
                                <div class="checkout-form-row two-cols">
                                    <div class="checkout-form-group">
                                        <label for="customer_name">Nume complet *</label>
                                        <input type="text" id="customer_name" name="customer_name" required>
                                    </div>
                                    <div class="checkout-form-group">
                                        <label for="customer_email">Email *</label>
                                        <input type="email" id="customer_email" name="customer_email" required>
                                    </div>
                                </div>
                                <div class="checkout-form-row">
                                    <div class="checkout-form-group">
                                        <label for="customer_phone">Telefon</label>
                                        <input type="tel" id="customer_phone" name="customer_phone">
                                    </div>
                                </div>
                            </div>

                            ${requires_shipping ? `
                            <!-- Shipping Address -->
                            <div class="checkout-section">
                                <h2 class="checkout-section-title">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Adresa de livrare
                                </h2>
                                <div class="checkout-form-row">
                                    <div class="checkout-form-group">
                                        <label for="shipping_name">Nume destinatar *</label>
                                        <input type="text" id="shipping_name" name="shipping_name" required>
                                    </div>
                                </div>
                                <div class="checkout-form-row">
                                    <div class="checkout-form-group">
                                        <label for="shipping_line1">Adresa (strada, numar) *</label>
                                        <input type="text" id="shipping_line1" name="shipping_line1" required>
                                    </div>
                                </div>
                                <div class="checkout-form-row">
                                    <div class="checkout-form-group">
                                        <label for="shipping_line2">Detalii suplimentare (apartament, bloc, etc.)</label>
                                        <input type="text" id="shipping_line2" name="shipping_line2">
                                    </div>
                                </div>
                                <div class="checkout-form-row two-cols">
                                    <div class="checkout-form-group">
                                        <label for="shipping_city">Oras *</label>
                                        <input type="text" id="shipping_city" name="shipping_city" required>
                                    </div>
                                    <div class="checkout-form-group">
                                        <label for="shipping_region">Judet/Regiune</label>
                                        <input type="text" id="shipping_region" name="shipping_region">
                                    </div>
                                </div>
                                <div class="checkout-form-row two-cols">
                                    <div class="checkout-form-group">
                                        <label for="shipping_postal_code">Cod postal *</label>
                                        <input type="text" id="shipping_postal_code" name="shipping_postal_code" required>
                                    </div>
                                    <div class="checkout-form-group">
                                        <label for="shipping_country">Tara *</label>
                                        <select id="shipping_country" name="shipping_country" required>
                                            <option value="RO" selected>Romania</option>
                                            <option value="MD">Moldova</option>
                                            <option value="BG">Bulgaria</option>
                                            <option value="HU">Ungaria</option>
                                            <option value="DE">Germania</option>
                                            <option value="AT">Austria</option>
                                            <option value="IT">Italia</option>
                                            <option value="ES">Spania</option>
                                            <option value="FR">Franta</option>
                                            <option value="GB">Marea Britanie</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Methods -->
                            <div class="checkout-section" id="shipping-methods-section">
                                <h2 class="checkout-section-title">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                    </svg>
                                    Metoda de livrare
                                </h2>
                                <div id="shipping-methods-container">
                                    <div id="shipping-methods-loading">
                                        <p style="color: var(--sleek-text-muted);">Completeaza adresa pentru a vedea optiunile de livrare</p>
                                    </div>
                                </div>
                            </div>
                            ` : ''}

                            <!-- Payment -->
                            <div class="checkout-section">
                                <h2 class="checkout-section-title">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                    </svg>
                                    Plata
                                </h2>
                                <div id="stripe-payment-element">
                                    <p style="color: var(--sleek-text-muted); text-align: center;">Se incarca optiunile de plata...</p>
                                </div>
                                <div id="stripe-payment-errors"></div>
                            </div>

                            <!-- Order Notes -->
                            <div class="checkout-section">
                                <h2 class="checkout-section-title">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Notite comanda (optional)
                                </h2>
                                <div class="checkout-form-group">
                                    <textarea id="order_notes" name="notes" rows="3" placeholder="Instructiuni speciale pentru livrare sau comanda..."
                                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--sleek-border); border-radius: 0.5rem; background: var(--sleek-bg); color: var(--sleek-text); resize: vertical;"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Order Summary Sidebar -->
                        <div>
                            <div class="checkout-summary">
                                <h3 class="checkout-summary-title">Sumar comanda</h3>
                                <div class="checkout-summary-items">
                                    ${cart.items.map((item: any) => `
                                        <div class="checkout-summary-item">
                                            <img src="${item.product?.image_url || '/placeholder.jpg'}" alt="${item.product?.title}" class="checkout-summary-item-image">
                                            <div class="checkout-summary-item-info">
                                                <div class="checkout-summary-item-name">${item.product?.title}</div>
                                                ${item.variant ? `<div class="checkout-summary-item-variant">${item.variant.name}</div>` : ''}
                                                <div class="checkout-summary-item-qty">x ${item.quantity}</div>
                                            </div>
                                            <div class="checkout-summary-item-price">${this.formatCurrency(item.line_total_cents / 100, currency)}</div>
                                        </div>
                                    `).join('')}
                                </div>
                                <div class="checkout-summary-row">
                                    <span>Subtotal</span>
                                    <span>${this.formatCurrency(totals.subtotal_cents / 100, currency)}</span>
                                </div>
                                ${totals.discount_cents > 0 ? `
                                    <div class="checkout-summary-row" style="color: #10b981;">
                                        <span>Reducere</span>
                                        <span>-${this.formatCurrency(totals.discount_cents / 100, currency)}</span>
                                    </div>
                                ` : ''}
                                ${totals.tax_cents > 0 ? `
                                    <div class="checkout-summary-row">
                                        <span>TVA</span>
                                        <span>${this.formatCurrency(totals.tax_cents / 100, currency)}</span>
                                    </div>
                                ` : ''}
                                <div class="checkout-summary-row" id="shipping-cost-row" style="${requires_shipping ? '' : 'display: none;'}">
                                    <span>Livrare</span>
                                    <span id="shipping-cost-display">-</span>
                                </div>
                                <div class="checkout-summary-row" id="delivery-estimate-row" style="display: none; color: var(--sleek-primary);">
                                    <span>
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline; vertical-align: middle; margin-right: 4px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Livrare estimata
                                    </span>
                                    <span id="delivery-estimate-display"></span>
                                </div>
                                <div class="checkout-summary-row total">
                                    <span>Total</span>
                                    <span id="checkout-total-display">${this.formatCurrency(totals.total_cents / 100, currency)}</span>
                                </div>
                                <input type="hidden" id="selected_shipping_method" name="shipping_method_id" value="">
                                <input type="hidden" id="checkout_session_id" value="${sessionId}">
                                <input type="hidden" id="checkout_currency" value="${currency}">
                                <input type="hidden" id="checkout_total_cents" value="${totals.total_cents}">
                                <button type="submit" class="checkout-submit-btn" id="checkout-submit-btn" disabled>
                                    <span id="btn-text">Plateste ${this.formatCurrency(totals.total_cents / 100, currency)}</span>
                                    <span id="btn-spinner" style="display: none;">Procesare...</span>
                                </button>
                                <div class="checkout-secure-note">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Plata securizata prin Stripe
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            `;

            // Initialize Stripe payment
            await this.initShopStripeCheckout(totals.total_cents, currency);

            // Bind shipping address change listeners if shipping required
            if (requires_shipping) {
                this.bindShippingAddressListeners(sessionId, currency);
            }

            // Bind form submission
            this.bindShopCheckoutForm(sessionId, requires_shipping);

        } catch (error: any) {
            console.error('Checkout init error:', error);
            contentEl.innerHTML = `
                <div class="sleek-empty-state">
                    <p style="color: var(--sleek-error);">Eroare la initializarea checkout-ului: ${error.response?.data?.message || error.message}</p>
                    <a href="/shop/cart" class="sleek-btn-primary" style="margin-top: 1rem;">Inapoi la cos</a>
                </div>
            `;
        }
    }

    private bindShippingAddressListeners(sessionId: string, currency: string): void {
        const addressFields = ['shipping_country', 'shipping_city', 'shipping_postal_code'];
        let debounceTimer: number | null = null;

        const fetchShippingMethods = async () => {
            const country = (document.getElementById('shipping_country') as HTMLSelectElement)?.value;
            const city = (document.getElementById('shipping_city') as HTMLInputElement)?.value;
            const postalCode = (document.getElementById('shipping_postal_code') as HTMLInputElement)?.value;
            const region = (document.getElementById('shipping_region') as HTMLInputElement)?.value;

            if (!country || !city || !postalCode) {
                return;
            }

            const container = document.getElementById('shipping-methods-container');
            if (!container) return;

            container.innerHTML = `
                <div id="shipping-methods-loading">
                    <div class="sleek-spinner" style="width: 24px; height: 24px; margin: 0 auto 0.5rem;"></div>
                    <p>Se incarca optiunile de livrare...</p>
                </div>
            `;

            try {
                const response = await this.apiClient!.post('/shop/checkout/shipping-methods', {
                    session_id: sessionId,
                    country,
                    region,
                    city,
                    postal_code: postalCode
                });

                const { shipping_methods } = response.data.data;

                if (!shipping_methods || shipping_methods.length === 0) {
                    container.innerHTML = `
                        <p style="color: var(--sleek-text-muted); text-align: center; padding: 1rem;">
                            Nu sunt disponibile metode de livrare pentru aceasta adresa.
                        </p>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div class="shipping-methods">
                        ${shipping_methods.map((method: any, index: number) => `
                            <label class="shipping-method ${index === 0 ? 'selected' : ''}" data-method-id="${method.id}" data-cost="${method.cost_cents}" data-estimate="${method.estimated_delivery || ''}">
                                <input type="radio" name="shipping_method" value="${method.id}" ${index === 0 ? 'checked' : ''}>
                                <div class="shipping-method-info">
                                    <div class="shipping-method-name">${method.name}</div>
                                    ${method.estimated_delivery ? `
                                        <div class="shipping-method-estimate">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            ${method.estimated_delivery}
                                        </div>
                                    ` : ''}
                                    ${method.description ? `<div class="shipping-method-description">${method.description}</div>` : ''}
                                </div>
                                <div class="shipping-method-price ${method.is_free ? 'free' : ''}">
                                    ${method.is_free ? 'GRATUIT' : this.formatCurrency(method.cost_cents / 100, currency)}
                                </div>
                            </label>
                        `).join('')}
                    </div>
                `;

                // Bind shipping method selection
                container.querySelectorAll('.shipping-method').forEach(method => {
                    method.addEventListener('click', () => {
                        container.querySelectorAll('.shipping-method').forEach(m => m.classList.remove('selected'));
                        method.classList.add('selected');
                        const radio = method.querySelector('input[type="radio"]') as HTMLInputElement;
                        if (radio) radio.checked = true;

                        // Update totals
                        this.updateShopCheckoutTotals(sessionId, currency);
                    });
                });

                // Select first method by default and update totals
                if (shipping_methods.length > 0) {
                    const hiddenInput = document.getElementById('selected_shipping_method') as HTMLInputElement;
                    if (hiddenInput) {
                        hiddenInput.value = shipping_methods[0].id;
                    }
                    this.updateShopCheckoutTotals(sessionId, currency);
                }

            } catch (error: any) {
                console.error('Failed to fetch shipping methods:', error);
                container.innerHTML = `
                    <p style="color: var(--sleek-error); text-align: center; padding: 1rem;">
                        Eroare la incarcarea metodelor de livrare.
                    </p>
                `;
            }
        };

        addressFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('change', () => {
                    if (debounceTimer) clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(fetchShippingMethods, 500);
                });
                field.addEventListener('blur', () => {
                    if (debounceTimer) clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(fetchShippingMethods, 300);
                });
            }
        });
    }

    private async updateShopCheckoutTotals(sessionId: string, currency: string): Promise<void> {
        if (!this.apiClient) return;

        const selectedMethod = document.querySelector('.shipping-method.selected') as HTMLElement;
        const shippingMethodId = selectedMethod?.dataset.methodId;
        const shippingCost = parseInt(selectedMethod?.dataset.cost || '0');
        const deliveryEstimate = selectedMethod?.dataset.estimate || '';

        // Update hidden input
        const hiddenInput = document.getElementById('selected_shipping_method') as HTMLInputElement;
        if (hiddenInput && shippingMethodId) {
            hiddenInput.value = shippingMethodId;
        }

        // Update shipping cost display
        const shippingCostDisplay = document.getElementById('shipping-cost-display');
        if (shippingCostDisplay) {
            shippingCostDisplay.textContent = shippingCost === 0 ? 'GRATUIT' : this.formatCurrency(shippingCost / 100, currency);
        }

        // Update delivery estimate display
        const estimateRow = document.getElementById('delivery-estimate-row');
        const estimateDisplay = document.getElementById('delivery-estimate-display');
        if (estimateRow && estimateDisplay) {
            if (deliveryEstimate) {
                estimateRow.style.display = 'flex';
                estimateDisplay.textContent = deliveryEstimate;
            } else {
                estimateRow.style.display = 'none';
            }
        }

        // Calculate new total
        try {
            const response = await this.apiClient.post('/shop/checkout/calculate', {
                session_id: sessionId,
                shipping_method_id: shippingMethodId
            });

            const { total_cents } = response.data.data;

            // Update total display
            const totalDisplay = document.getElementById('checkout-total-display');
            if (totalDisplay) {
                totalDisplay.textContent = this.formatCurrency(total_cents / 100, currency);
            }

            // Update hidden total and button
            const totalInput = document.getElementById('checkout_total_cents') as HTMLInputElement;
            if (totalInput) {
                totalInput.value = total_cents.toString();
            }

            const btnText = document.getElementById('btn-text');
            if (btnText) {
                btnText.textContent = `Plateste ${this.formatCurrency(total_cents / 100, currency)}`;
            }

        } catch (error) {
            console.error('Failed to calculate totals:', error);
        }
    }

    private async initShopStripeCheckout(totalCents: number, currency: string): Promise<void> {
        if (!this.apiClient) return;

        const paymentElement = document.getElementById('stripe-payment-element');
        const errorsElement = document.getElementById('stripe-payment-errors');
        const submitBtn = document.getElementById('checkout-submit-btn') as HTMLButtonElement;

        if (!paymentElement) return;

        try {
            // Load Stripe.js dynamically
            if (!(window as any).Stripe) {
                await this.loadStripeJs();
            }

            // Get payment config
            const configResponse = await this.apiClient.get('/client/payment/config');
            const { publishable_key, configured, processor } = configResponse.data.data;

            if (!configured || processor !== 'stripe') {
                paymentElement.innerHTML = '<p style="color: var(--sleek-error);">Platile nu sunt configurate pentru acest magazin.</p>';
                return;
            }

            // Create Payment Intent
            const intentResponse = await this.apiClient.post('/client/payment/create-intent', {
                amount: totalCents / 100,
                currency: currency.toLowerCase(),
            });

            const { client_secret } = intentResponse.data.data;

            // Initialize Stripe
            const stripe = (window as any).Stripe(publishable_key);
            const elements = stripe.elements({
                clientSecret: client_secret,
                appearance: {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '#6366f1',
                        colorBackground: '#ffffff',
                        colorText: '#1f2937',
                        fontFamily: 'system-ui, sans-serif',
                        borderRadius: '0.5rem',
                    },
                },
            });

            // Store stripe and elements for form submission
            (window as any).__shopStripe = stripe;
            (window as any).__shopElements = elements;

            // Create Payment Element
            const paymentElementInstance = elements.create('payment', {
                layout: 'tabs',
            });
            paymentElement.innerHTML = '';
            paymentElementInstance.mount(paymentElement);

            // Enable submit button when payment element is ready
            paymentElementInstance.on('ready', () => {
                submitBtn.disabled = false;
            });

            // Show errors
            paymentElementInstance.on('change', (event: any) => {
                if (event.error && errorsElement) {
                    errorsElement.textContent = event.error.message;
                    errorsElement.style.display = 'block';
                } else if (errorsElement) {
                    errorsElement.style.display = 'none';
                }
            });

        } catch (error: any) {
            console.error('Stripe init error:', error);
            paymentElement.innerHTML = `<p style="color: var(--sleek-error);">Eroare la incarcarea platii: ${error.response?.data?.message || error.message}</p>`;
        }
    }

    private loadStripeJs(): Promise<void> {
        return new Promise((resolve, reject) => {
            if ((window as any).Stripe) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://js.stripe.com/v3/';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load Stripe.js'));
            document.head.appendChild(script);
        });
    }

    private bindShopCheckoutForm(sessionId: string, requiresShipping: boolean): void {
        const form = document.getElementById('shop-checkout-form') as HTMLFormElement;
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('checkout-submit-btn') as HTMLButtonElement;
            const btnText = document.getElementById('btn-text');
            const btnSpinner = document.getElementById('btn-spinner');
            const errorsElement = document.getElementById('stripe-payment-errors');

            if (!submitBtn || !this.apiClient) return;

            // Validate shipping method if required
            if (requiresShipping) {
                const shippingMethodId = (document.getElementById('selected_shipping_method') as HTMLInputElement)?.value;
                if (!shippingMethodId) {
                    if (errorsElement) {
                        errorsElement.textContent = 'Te rugam sa selectezi o metoda de livrare.';
                        errorsElement.style.display = 'block';
                    }
                    return;
                }
            }

            submitBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnSpinner) btnSpinner.style.display = 'inline';

            try {
                const formData = new FormData(form);
                const currency = (document.getElementById('checkout_currency') as HTMLInputElement)?.value || 'RON';

                // Build shipping address if required
                let shippingAddress = null;
                if (requiresShipping) {
                    shippingAddress = {
                        name: formData.get('shipping_name'),
                        line1: formData.get('shipping_line1'),
                        line2: formData.get('shipping_line2') || null,
                        city: formData.get('shipping_city'),
                        region: formData.get('shipping_region') || null,
                        postal_code: formData.get('shipping_postal_code'),
                        country: formData.get('shipping_country'),
                    };
                }

                // Create order first
                const orderResponse = await this.apiClient.post('/shop/checkout/create-order', {
                    session_id: sessionId,
                    customer_email: formData.get('customer_email'),
                    customer_phone: formData.get('customer_phone') || null,
                    customer_name: formData.get('customer_name'),
                    shipping_address: shippingAddress,
                    shipping_method_id: formData.get('shipping_method_id') || null,
                    notes: formData.get('notes') || null,
                });

                if (!orderResponse.data.success) {
                    throw new Error(orderResponse.data.message || 'Eroare la crearea comenzii');
                }

                const { order_number, requires_payment } = orderResponse.data.data;

                // If payment required, process with Stripe
                if (requires_payment) {
                    const stripe = (window as any).__shopStripe;
                    const elements = (window as any).__shopElements;

                    if (!stripe || !elements) {
                        throw new Error('Sistemul de plata nu este initializat');
                    }

                    const returnUrl = `${window.location.origin}${window.location.pathname}#/shop/thank-you/${order_number}`;

                    const { error } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: returnUrl,
                            receipt_email: formData.get('customer_email') as string,
                            payment_method_data: {
                                billing_details: {
                                    name: formData.get('customer_name') as string,
                                    email: formData.get('customer_email') as string,
                                    phone: formData.get('customer_phone') as string || undefined,
                                },
                            },
                        },
                    });

                    if (error) {
                        if (errorsElement) {
                            errorsElement.textContent = error.message || 'A aparut o eroare la procesarea platii.';
                            errorsElement.style.display = 'block';
                        }
                        submitBtn.disabled = false;
                        if (btnText) btnText.style.display = 'inline';
                        if (btnSpinner) btnSpinner.style.display = 'none';
                        return;
                    }
                    // If no error, Stripe will redirect to return_url
                } else {
                    // No payment needed, redirect to thank you page
                    window.location.hash = `/shop/thank-you/${order_number}`;
                }

            } catch (error: any) {
                console.error('Checkout error:', error);
                if (errorsElement) {
                    errorsElement.textContent = error.response?.data?.message || error.message || 'A aparut o eroare.';
                    errorsElement.style.display = 'block';
                }
                submitBtn.disabled = false;
                if (btnText) btnText.style.display = 'inline';
                if (btnSpinner) btnSpinner.style.display = 'none';
            }
        });
    }
}
