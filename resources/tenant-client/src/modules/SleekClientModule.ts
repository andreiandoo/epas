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
}
