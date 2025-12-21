import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

/**
 * GAMIFICATION MODULE
 * Handles loyalty points, referrals, and points redemption
 */
export class GamificationModule {
    name = 'gamification';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private clientToken: string | null = null;
    private config: GamificationConfig | null = null;

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;
        this.clientToken = localStorage.getItem('client_token');

        // Load gamification config
        await this.loadConfig();

        // Points pages
        this.eventBus.on('route:points', () => this.renderPointsPage());
        this.eventBus.on('route:points-history', () => this.renderPointsHistory());
        this.eventBus.on('route:referral', () => this.renderReferralPage());

        // Integration events
        this.eventBus.on('gamification:get-balance', (callback: (balance: PointsBalance | null) => void) => {
            this.getBalance().then(callback);
        });

        this.eventBus.on('gamification:check-redemption', (data: { points: number, orderTotal: number }, callback: (result: RedemptionCheck | null) => void) => {
            this.checkRedemption(data.points, data.orderTotal).then(callback);
        });

        this.eventBus.on('gamification:get-config', (callback: (config: GamificationConfig | null) => void) => {
            callback(this.config);
        });

        console.log('Gamification module initialized');
    }

    private isLoggedIn(): boolean {
        return !!this.clientToken;
    }

    private setAuthHeaders(): void {
        if (this.clientToken && this.apiClient) {
            this.apiClient.setHeader('Authorization', `Bearer ${this.clientToken}`);
        }
    }

    private async loadConfig(): Promise<void> {
        if (!this.apiClient) return;

        try {
            const response = await this.apiClient.get('/gamification/config');
            if (response.data.success) {
                this.config = response.data.data;
            }
        } catch (error) {
            console.log('Gamification not enabled for this tenant');
        }
    }

    public isEnabled(): boolean {
        return this.config !== null && this.config.is_active;
    }

    public getConfig(): GamificationConfig | null {
        return this.config;
    }

    private async getBalance(): Promise<PointsBalance | null> {
        if (!this.apiClient || !this.isLoggedIn()) return null;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/gamification/balance');
            if (response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.error('Failed to get points balance:', error);
        }
        return null;
    }

    private async checkRedemption(points: number, orderTotal: number): Promise<RedemptionCheck | null> {
        if (!this.apiClient || !this.isLoggedIn()) return null;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.post('/gamification/check-redemption', {
                points_to_redeem: points,
                order_total_cents: orderTotal
            });
            if (response.data.success) {
                return response.data.data;
            }
        } catch (error) {
            console.error('Failed to check redemption:', error);
        }
        return null;
    }

    private formatCurrency(amount: number, currency: string = 'RON'): string {
        return new Intl.NumberFormat('ro-RO', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    private formatDate(dateStr: string): string {
        const date = new Date(dateStr);
        return date.toLocaleDateString('ro-RO', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // ========================================
    // POINTS PAGE (Account -> My Points)
    // ========================================
    async renderPointsPage(): Promise<void> {
        // Auth is already checked by Router before emitting route:points
        const container = document.getElementById('points-container');
        if (!container) return;

        container.innerHTML = `
            <div class="gamification-page">
                <div class="gamification-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadPointsPageContent(container);
    }

    private async loadPointsPageContent(container: HTMLElement): Promise<void> {
        if (!this.apiClient) return;

        try {
            this.setAuthHeaders();
            const [balanceRes, historyRes, configRes] = await Promise.all([
                this.apiClient.get('/gamification/balance'),
                this.apiClient.get('/gamification/history?per_page=10'),
                this.apiClient.get('/gamification/config')
            ]);

            const balance = balanceRes.data.data;
            const history = historyRes.data.data;
            const config = configRes.data.data;

            const pointsName = config.points_name || 'puncte';
            const pointsNameSingular = config.points_name_singular || 'punct';
            const pointValue = config.point_value_cents / 100;
            const currency = config.currency || 'RON';

            container.innerHTML = `
                <style>
                    .gamification-page {
                        max-width: 800px;
                        margin: 0 auto;
                        padding: 2rem 1rem;
                    }

                    .points-header {
                        text-align: center;
                        margin-bottom: 2rem;
                    }

                    .points-balance-card {
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                        border-radius: var(--sleek-radius);
                        padding: 2rem;
                        text-align: center;
                        color: white;
                        margin-bottom: 2rem;
                        box-shadow: 0 8px 32px var(--sleek-glow);
                    }

                    .points-balance-label {
                        font-size: 0.9rem;
                        opacity: 0.9;
                        margin-bottom: 0.5rem;
                    }

                    .points-balance-value {
                        font-size: 3rem;
                        font-weight: 800;
                        margin-bottom: 0.5rem;
                    }

                    .points-balance-worth {
                        font-size: 1rem;
                        opacity: 0.8;
                    }

                    .points-stats-grid {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 1rem;
                        margin-bottom: 2rem;
                    }

                    .points-stat {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.25rem;
                        text-align: center;
                    }

                    .points-stat-value {
                        font-size: 1.5rem;
                        font-weight: 700;
                        color: var(--sleek-text);
                    }

                    .points-stat-label {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                        margin-top: 0.25rem;
                    }

                    .points-section {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                        margin-bottom: 1.5rem;
                    }

                    .points-section-title {
                        font-size: 1.1rem;
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 1rem;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                    }

                    .points-history-item {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 1rem 0;
                        border-bottom: 1px solid var(--sleek-border);
                    }

                    .points-history-item:last-child {
                        border-bottom: none;
                    }

                    .points-history-info {
                        flex: 1;
                    }

                    .points-history-type {
                        font-weight: 500;
                        color: var(--sleek-text);
                        margin-bottom: 0.25rem;
                    }

                    .points-history-date {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                    }

                    .points-history-amount {
                        font-weight: 700;
                        font-size: 1.1rem;
                    }

                    .points-history-amount.earned {
                        color: var(--sleek-success);
                    }

                    .points-history-amount.spent {
                        color: var(--sleek-error);
                    }

                    .tier-badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        padding: 0.5rem 1rem;
                        background: var(--sleek-surface-elevated);
                        border-radius: 9999px;
                        font-size: 0.9rem;
                        font-weight: 600;
                        margin-top: 1rem;
                    }

                    .view-all-link {
                        display: block;
                        text-align: center;
                        color: var(--sleek-gradient-start);
                        font-weight: 500;
                        padding-top: 1rem;
                        text-decoration: none;
                    }

                    .view-all-link:hover {
                        text-decoration: underline;
                    }

                    .referral-box {
                        background: var(--sleek-surface-elevated);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                        text-align: center;
                    }

                    .referral-link-input {
                        display: flex;
                        gap: 0.5rem;
                        margin-top: 1rem;
                    }

                    .referral-link-input input {
                        flex: 1;
                        padding: 0.75rem 1rem;
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius-sm);
                        color: var(--sleek-text);
                        font-size: 0.9rem;
                    }

                    .referral-link-input button {
                        padding: 0.75rem 1.5rem;
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                        color: white;
                        border: none;
                        border-radius: var(--sleek-radius-sm);
                        font-weight: 600;
                        cursor: pointer;
                        transition: var(--sleek-transition);
                    }

                    .referral-link-input button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px var(--sleek-glow);
                    }

                    @media (max-width: 640px) {
                        .points-stats-grid {
                            grid-template-columns: 1fr;
                        }
                    }
                </style>

                <div class="gamification-page">
                    <div class="points-header">
                        <h1 class="sleek-page-title">${pointsName.charAt(0).toUpperCase() + pointsName.slice(1)}le mele</h1>
                    </div>

                    <div class="points-balance-card">
                        <div class="points-balance-label">Balanta curenta</div>
                        <div class="points-balance-value">${balance.current_balance.toLocaleString()}</div>
                        <div class="points-balance-worth">
                            Valoare: ${this.formatCurrency(balance.current_balance * pointValue, currency)}
                        </div>
                        ${balance.current_tier ? `
                            <div class="tier-badge" style="background: ${balance.current_tier.color || '#6366f1'}20; color: ${balance.current_tier.color || '#6366f1'}">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                ${balance.current_tier.name}
                            </div>
                        ` : ''}
                    </div>

                    <div class="points-stats-grid">
                        <div class="points-stat">
                            <div class="points-stat-value">${balance.total_earned.toLocaleString()}</div>
                            <div class="points-stat-label">Total castigate</div>
                        </div>
                        <div class="points-stat">
                            <div class="points-stat-value">${balance.total_spent.toLocaleString()}</div>
                            <div class="points-stat-label">Total folosite</div>
                        </div>
                        <div class="points-stat">
                            <div class="points-stat-value">${balance.referral_count || 0}</div>
                            <div class="points-stat-label">Prieteni invitati</div>
                        </div>
                    </div>

                    ${balance.referral_code ? `
                    <div class="points-section">
                        <div class="points-section-title">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Invita prieteni
                        </div>
                        <div class="referral-box">
                            <p style="color: var(--sleek-text-muted); margin-bottom: 0.5rem;">
                                Primesti <strong>${config.referral_bonus_points || 0} ${pointsName}</strong> pentru fiecare prieten care face o comanda!
                            </p>
                            <div class="referral-link-input">
                                <input type="text" id="referral-link" value="${balance.referral_link || ''}" readonly>
                                <button onclick="navigator.clipboard.writeText(document.getElementById('referral-link').value).then(() => alert('Link copiat!'))">
                                    Copiaza
                                </button>
                            </div>
                        </div>
                    </div>
                    ` : ''}

                    <div class="points-section">
                        <div class="points-section-title">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Istoric recent
                        </div>
                        ${history.data && history.data.length > 0 ? `
                            ${history.data.map((tx: PointsTransaction) => `
                                <div class="points-history-item">
                                    <div class="points-history-info">
                                        <div class="points-history-type">${tx.display_type || tx.type}</div>
                                        <div class="points-history-date">${this.formatDate(tx.created_at)}</div>
                                    </div>
                                    <div class="points-history-amount ${tx.points > 0 ? 'earned' : 'spent'}">
                                        ${tx.points > 0 ? '+' : ''}${tx.points.toLocaleString()} ${tx.points === 1 || tx.points === -1 ? pointsNameSingular : pointsName}
                                    </div>
                                </div>
                            `).join('')}
                            <a href="#/account/points-history" class="view-all-link">Vezi tot istoricul</a>
                        ` : `
                            <p style="color: var(--sleek-text-muted); text-align: center; padding: 2rem;">
                                Nu ai inca tranzactii. Plaseaza o comanda pentru a castiga ${pointsName}!
                            </p>
                        `}
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Failed to load points page:', error);
            container.innerHTML = `
                <div class="gamification-page" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--sleek-text-muted);">Nu am putut incarca informatiile despre puncte.</p>
                </div>
            `;
        }
    }

    // ========================================
    // POINTS HISTORY PAGE
    // ========================================
    async renderPointsHistory(): Promise<void> {
        // Auth is already checked by Router before emitting route:points-history
        const container = document.getElementById('points-history-container');
        if (!container) return;

        container.innerHTML = `
            <div class="gamification-page">
                <div class="gamification-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadPointsHistoryContent(container);
    }

    private async loadPointsHistoryContent(container: HTMLElement): Promise<void> {
        if (!this.apiClient) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/gamification/history?per_page=50');
            const history = response.data.data;
            const config = this.config;

            const pointsName = config?.points_name || 'puncte';
            const pointsNameSingular = config?.points_name_singular || 'punct';

            container.innerHTML = `
                <style>
                    .gamification-page {
                        max-width: 800px;
                        margin: 0 auto;
                        padding: 2rem 1rem;
                    }

                    .history-header {
                        display: flex;
                        align-items: center;
                        gap: 1rem;
                        margin-bottom: 2rem;
                    }

                    .back-btn {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 40px;
                        height: 40px;
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius-sm);
                        color: var(--sleek-text);
                        text-decoration: none;
                        transition: var(--sleek-transition);
                    }

                    .back-btn:hover {
                        background: var(--sleek-surface-elevated);
                    }

                    .history-list {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        overflow: hidden;
                    }

                    .history-item {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 1rem 1.5rem;
                        border-bottom: 1px solid var(--sleek-border);
                    }

                    .history-item:last-child {
                        border-bottom: none;
                    }

                    .history-info {
                        flex: 1;
                    }

                    .history-type {
                        font-weight: 500;
                        color: var(--sleek-text);
                        margin-bottom: 0.25rem;
                    }

                    .history-date {
                        font-size: 0.8rem;
                        color: var(--sleek-text-muted);
                    }

                    .history-amount {
                        font-weight: 700;
                        font-size: 1.1rem;
                    }

                    .history-amount.earned {
                        color: var(--sleek-success);
                    }

                    .history-amount.spent {
                        color: var(--sleek-error);
                    }
                </style>

                <div class="gamification-page">
                    <div class="history-header">
                        <a href="#/account/points" class="back-btn">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <h1 class="sleek-page-title">Istoric ${pointsName}</h1>
                    </div>

                    <div class="history-list">
                        ${history.data && history.data.length > 0 ? history.data.map((tx: PointsTransaction) => `
                            <div class="history-item">
                                <div class="history-info">
                                    <div class="history-type">${tx.display_type || tx.type}</div>
                                    <div class="history-date">${this.formatDate(tx.created_at)}</div>
                                </div>
                                <div class="history-amount ${tx.points > 0 ? 'earned' : 'spent'}">
                                    ${tx.points > 0 ? '+' : ''}${tx.points.toLocaleString()} ${Math.abs(tx.points) === 1 ? pointsNameSingular : pointsName}
                                </div>
                            </div>
                        `).join('') : `
                            <div style="padding: 3rem; text-align: center; color: var(--sleek-text-muted);">
                                Nu ai inca tranzactii.
                            </div>
                        `}
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Failed to load points history:', error);
            container.innerHTML = `
                <div class="gamification-page" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--sleek-text-muted);">Nu am putut incarca istoricul.</p>
                </div>
            `;
        }
    }

    // ========================================
    // REFERRAL PAGE
    // ========================================
    async renderReferralPage(): Promise<void> {
        // Auth is already checked by Router before emitting route:referral
        const container = document.getElementById('referral-container');
        if (!container) return;

        container.innerHTML = `
            <div class="gamification-page">
                <div class="gamification-loading">
                    <div class="sleek-spinner"></div>
                </div>
            </div>
        `;

        await this.loadReferralPageContent(container);
    }

    private async loadReferralPageContent(container: HTMLElement): Promise<void> {
        if (!this.apiClient) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/gamification/referral');
            const referral = response.data.data;
            const config = this.config;

            const pointsName = config?.points_name || 'puncte';

            container.innerHTML = `
                <style>
                    .gamification-page {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 2rem 1rem;
                    }

                    .referral-hero {
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                        border-radius: var(--sleek-radius);
                        padding: 2rem;
                        text-align: center;
                        color: white;
                        margin-bottom: 2rem;
                    }

                    .referral-hero h2 {
                        font-size: 1.5rem;
                        font-weight: 700;
                        margin-bottom: 0.5rem;
                    }

                    .referral-hero p {
                        opacity: 0.9;
                    }

                    .referral-stats {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 1rem;
                        margin-bottom: 2rem;
                    }

                    .referral-stat {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                        text-align: center;
                    }

                    .referral-stat-value {
                        font-size: 2rem;
                        font-weight: 700;
                        color: var(--sleek-text);
                    }

                    .referral-stat-label {
                        font-size: 0.9rem;
                        color: var(--sleek-text-muted);
                    }

                    .share-section {
                        background: var(--sleek-surface);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius);
                        padding: 1.5rem;
                    }

                    .share-title {
                        font-weight: 600;
                        color: var(--sleek-text);
                        margin-bottom: 1rem;
                    }

                    .share-link-box {
                        display: flex;
                        gap: 0.5rem;
                    }

                    .share-link-box input {
                        flex: 1;
                        padding: 0.875rem 1rem;
                        background: var(--sleek-surface-elevated);
                        border: 1px solid var(--sleek-border);
                        border-radius: var(--sleek-radius-sm);
                        color: var(--sleek-text);
                        font-size: 0.9rem;
                    }

                    .share-link-box button {
                        padding: 0.875rem 1.5rem;
                        background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                        color: white;
                        border: none;
                        border-radius: var(--sleek-radius-sm);
                        font-weight: 600;
                        cursor: pointer;
                        transition: var(--sleek-transition);
                    }

                    .share-link-box button:hover {
                        transform: translateY(-2px);
                    }

                    .share-buttons {
                        display: flex;
                        gap: 0.75rem;
                        margin-top: 1rem;
                        justify-content: center;
                    }

                    .share-btn {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 44px;
                        height: 44px;
                        border-radius: 50%;
                        color: white;
                        transition: var(--sleek-transition);
                    }

                    .share-btn:hover {
                        transform: scale(1.1);
                    }

                    .share-btn.facebook { background: #1877F2; }
                    .share-btn.whatsapp { background: #25D366; }
                    .share-btn.twitter { background: #1DA1F2; }
                </style>

                <div class="gamification-page">
                    <div class="referral-hero">
                        <h2>Invita prieteni, castiga ${pointsName}!</h2>
                        <p>Primesti <strong>${config?.referral_bonus_points || 0} ${pointsName}</strong> pentru fiecare prieten care face o comanda.</p>
                    </div>

                    <div class="referral-stats">
                        <div class="referral-stat">
                            <div class="referral-stat-value">${referral.referral_count || 0}</div>
                            <div class="referral-stat-label">Prieteni invitati</div>
                        </div>
                        <div class="referral-stat">
                            <div class="referral-stat-value">${referral.referral_points_earned || 0}</div>
                            <div class="referral-stat-label">${pointsName.charAt(0).toUpperCase() + pointsName.slice(1)} castigate</div>
                        </div>
                    </div>

                    <div class="share-section">
                        <div class="share-title">Linkul tau de referral</div>
                        <div class="share-link-box">
                            <input type="text" id="referral-link-share" value="${referral.referral_link || ''}" readonly>
                            <button onclick="navigator.clipboard.writeText(document.getElementById('referral-link-share').value).then(() => alert('Link copiat!'))">
                                Copiaza
                            </button>
                        </div>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referral.referral_link || '')}" target="_blank" class="share-btn facebook">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                            <a href="https://wa.me/?text=${encodeURIComponent('Cumpara bilete aici: ' + (referral.referral_link || ''))}" target="_blank" class="share-btn whatsapp">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </a>
                            <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent('Cumpara bilete aici: ' + (referral.referral_link || ''))}" target="_blank" class="share-btn twitter">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Failed to load referral page:', error);
            container.innerHTML = `
                <div class="gamification-page" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--sleek-text-muted);">Nu am putut incarca informatiile despre referral.</p>
                </div>
            `;
        }
    }

    // ========================================
    // HELPER: Get points widget HTML for account dashboard
    // ========================================
    public async getAccountPointsWidget(): Promise<string> {
        if (!this.isEnabled() || !this.isLoggedIn()) return '';

        try {
            const balance = await this.getBalance();
            if (!balance) return '';

            const config = this.config;
            const pointsName = config?.points_name || 'puncte';
            const pointValue = (config?.point_value_cents || 0) / 100;
            const currency = config?.currency || 'RON';

            return `
                <a href="#/account/points" class="sleek-menu-item">
                    <div class="sleek-menu-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="sleek-menu-content">
                        <span class="sleek-menu-title">${pointsName.charAt(0).toUpperCase() + pointsName.slice(1)}le mele</span>
                        <span class="sleek-menu-badge">${balance.current_balance.toLocaleString()}</span>
                    </div>
                    <svg class="sleek-menu-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            `;
        } catch (error) {
            return '';
        }
    }

    // ========================================
    // HELPER: Get points redemption widget for cart
    // ========================================
    public async getCartPointsWidget(orderTotalCents: number): Promise<string> {
        if (!this.isEnabled() || !this.isLoggedIn()) return '';

        try {
            const balance = await this.getBalance();
            if (!balance || balance.current_balance <= 0) return '';

            const config = this.config;
            const pointsName = config?.points_name || 'puncte';
            const pointValue = (config?.point_value_cents || 1) / 100;
            const currency = config?.currency || 'RON';
            const minRedeem = config?.min_redeem_points || 0;
            const maxRedeemPercent = config?.max_redeem_percentage || 100;

            // Calculate max redeemable
            const maxFromBalance = balance.current_balance;
            const maxFromOrderPercent = Math.floor((orderTotalCents * maxRedeemPercent / 100) / (config?.point_value_cents || 1));
            const maxRedeemable = Math.min(maxFromBalance, maxFromOrderPercent);

            if (maxRedeemable < minRedeem) return '';

            const maxDiscount = maxRedeemable * pointValue;

            return `
                <div class="points-redemption-widget" style="background: var(--sleek-surface); border: 1px solid var(--sleek-border); border-radius: var(--sleek-radius); padding: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <svg class="w-5 h-5" style="color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span style="font-weight: 600; color: var(--sleek-text);">Ai ${balance.current_balance.toLocaleString()} ${pointsName}</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="number" id="points-to-redeem" min="0" max="${maxRedeemable}" value="0"
                            style="flex: 1; padding: 0.625rem; background: var(--sleek-surface-elevated); border: 1px solid var(--sleek-border); border-radius: var(--sleek-radius-sm); color: var(--sleek-text);">
                        <button onclick="window.applyPoints()" style="padding: 0.625rem 1rem; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; border-radius: var(--sleek-radius-sm); font-weight: 600; cursor: pointer;">
                            Aplica
                        </button>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--sleek-text-muted); margin-top: 0.5rem;">
                        Poti folosi maxim ${maxRedeemable.toLocaleString()} ${pointsName} (discount ${this.formatCurrency(maxDiscount, currency)})
                    </p>
                </div>
            `;
        } catch (error) {
            return '';
        }
    }

    // ========================================
    // HELPER: Calculate points earned for order
    // ========================================
    public calculatePointsEarned(orderTotalCents: number): number {
        if (!this.config || !this.config.is_active) return 0;

        const earnPercentage = this.config.earn_percentage || 0;
        const minOrderCents = this.config.min_order_cents_for_earning || 0;

        if (orderTotalCents < minOrderCents) return 0;

        const baseAmount = this.config.earn_on_subtotal ? orderTotalCents : orderTotalCents;
        return Math.floor((baseAmount * earnPercentage) / 100);
    }
}

// Types
interface GamificationConfig {
    is_active: boolean;
    point_value_cents: number;
    currency: string;
    earn_percentage: number;
    earn_on_subtotal: boolean;
    min_order_cents_for_earning: number;
    min_redeem_points: number;
    max_redeem_percentage: number;
    max_redeem_points_per_order: number;
    referral_bonus_points: number;
    referred_bonus_points: number;
    signup_bonus_points: number;
    birthday_bonus_points: number;
    points_name: string;
    points_name_singular: string;
    icon: string;
    tiers: GamificationTier[];
}

interface GamificationTier {
    name: string;
    min_points: number;
    multiplier: number;
    color: string;
}

interface PointsBalance {
    current_balance: number;
    total_earned: number;
    total_spent: number;
    total_expired: number;
    pending_points: number;
    current_tier: GamificationTier | null;
    referral_code: string;
    referral_link: string;
    referral_count: number;
    referral_points_earned: number;
}

interface PointsTransaction {
    id: number;
    type: string;
    display_type: string;
    points: number;
    balance_after: number;
    created_at: string;
}

interface RedemptionCheck {
    can_redeem: boolean;
    max_redeemable: number;
    discount_cents: number;
    message: string;
}

export default GamificationModule;
