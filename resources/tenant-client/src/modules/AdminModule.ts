import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

export class AdminModule {
    name = 'admin';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;

        this.eventBus.on('route:admin-dashboard', () => this.loadDashboard());
        this.eventBus.on('route:admin-events', () => this.loadEvents());
        this.eventBus.on('route:admin-orders', () => this.loadOrders());
        this.eventBus.on('route:admin-customers', () => this.loadCustomers());
        this.eventBus.on('route:admin-users', () => this.loadUsers());
        this.eventBus.on('route:admin-settings', () => this.loadSettings());

        console.log('Admin module initialized');
    }

    async loadDashboard(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-stats');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/dashboard');
            const stats = response.data.data.stats;

            container.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    ${this.renderStatCard('Total Events', stats.total_events, 'heroicon-calendar')}
                    ${this.renderStatCard('Active Events', stats.active_events, 'heroicon-sparkles')}
                    ${this.renderStatCard('Total Orders', stats.total_orders, 'heroicon-shopping-cart')}
                    ${this.renderStatCard('Tickets Sold', stats.tickets_sold, 'heroicon-ticket')}
                    ${this.renderStatCard('Customers', stats.customers, 'heroicon-users')}
                    ${this.renderStatCard('Revenue', this.formatCurrency(stats.total_revenue), 'heroicon-currency-dollar')}
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="tixello-card">
                        <h3 style="font-weight: 600; margin-bottom: 1rem;">Recent Orders</h3>
                        <div id="recent-orders">Loading...</div>
                    </div>
                    <div class="tixello-card">
                        <h3 style="font-weight: 600; margin-bottom: 1rem;">Upcoming Events</h3>
                        <div id="upcoming-events">Loading...</div>
                    </div>
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load dashboard. Please try again.</p>';
        }
    }

    async loadEvents(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-events-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/events');
            const events = response.data.data.events || [];

            container.innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <button id="create-event-btn" class="tixello-btn">+ Create Event</button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left;">Title</th>
                            <th style="padding: 0.75rem; text-align: left;">Date</th>
                            <th style="padding: 0.75rem; text-align: left;">Venue</th>
                            <th style="padding: 0.75rem; text-align: left;">Status</th>
                            <th style="padding: 0.75rem; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${events.map((event: any) => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">${event.title}</td>
                                <td style="padding: 0.75rem;">${new Date(event.start_date).toLocaleDateString()}</td>
                                <td style="padding: 0.75rem;">${event.venue}</td>
                                <td style="padding: 0.75rem;">
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; background: ${event.status === 'published' ? '#d1fae5' : '#fef3c7'}; color: ${event.status === 'published' ? '#065f46' : '#92400e'};">
                                        ${event.status}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem;">
                                    <button class="edit-event-btn" data-id="${event.id}" style="color: #3b82f6; cursor: pointer; background: none; border: none;">Edit</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load events.</p>';
        }
    }

    async loadOrders(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-orders-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/orders');
            const orders = response.data.data.orders || [];

            container.innerHTML = `
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left;">Order #</th>
                            <th style="padding: 0.75rem; text-align: left;">Customer</th>
                            <th style="padding: 0.75rem; text-align: left;">Event</th>
                            <th style="padding: 0.75rem; text-align: left;">Amount</th>
                            <th style="padding: 0.75rem; text-align: left;">Status</th>
                            <th style="padding: 0.75rem; text-align: left;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${orders.map((order: any) => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">#${order.id}</td>
                                <td style="padding: 0.75rem;">${order.customer_name || order.customer_email}</td>
                                <td style="padding: 0.75rem;">${order.event_title || 'N/A'}</td>
                                <td style="padding: 0.75rem;">${this.formatCurrency(order.total_cents / 100)}</td>
                                <td style="padding: 0.75rem;">
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; background: ${order.status === 'completed' ? '#d1fae5' : '#fee2e2'};">
                                        ${order.status}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem;">${new Date(order.created_at).toLocaleDateString()}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load orders.</p>';
        }
    }

    async loadCustomers(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-customers-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/customers');
            const customers = response.data.data.customers || [];

            container.innerHTML = `
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left;">Name</th>
                            <th style="padding: 0.75rem; text-align: left;">Email</th>
                            <th style="padding: 0.75rem; text-align: left;">Orders</th>
                            <th style="padding: 0.75rem; text-align: left;">Total Spent</th>
                            <th style="padding: 0.75rem; text-align: left;">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${customers.map((customer: any) => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">${customer.name}</td>
                                <td style="padding: 0.75rem;">${customer.email}</td>
                                <td style="padding: 0.75rem;">${customer.orders_count || 0}</td>
                                <td style="padding: 0.75rem;">${this.formatCurrency(customer.total_spent || 0)}</td>
                                <td style="padding: 0.75rem;">${new Date(customer.created_at).toLocaleDateString()}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load customers.</p>';
        }
    }

    async loadUsers(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-users-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/users');
            const users = response.data.data.users || [];

            container.innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <button id="create-user-btn" class="tixello-btn">+ Add User</button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left;">Name</th>
                            <th style="padding: 0.75rem; text-align: left;">Email</th>
                            <th style="padding: 0.75rem; text-align: left;">Role</th>
                            <th style="padding: 0.75rem; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${users.map((user: any) => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">${user.name}</td>
                                <td style="padding: 0.75rem;">${user.email}</td>
                                <td style="padding: 0.75rem;">${user.role}</td>
                                <td style="padding: 0.75rem;">
                                    <button class="edit-user-btn" data-id="${user.id}" style="color: #3b82f6; cursor: pointer; background: none; border: none;">Edit</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load users.</p>';
        }
    }

    async loadSettings(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-settings-form');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/settings');
            const settings = response.data.data;

            container.innerHTML = `
                <form id="settings-form" style="max-width: 600px;">
                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="font-weight: 600; margin-bottom: 1rem;">General</h3>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Company Name</label>
                            <input type="text" name="company_name" value="${settings.general?.name || ''}" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Contact Email</label>
                            <input type="email" name="email" value="${settings.general?.email || ''}" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                        </div>
                    </div>
                    <button type="submit" class="tixello-btn">Save Settings</button>
                </form>
            `;

            document.getElementById('settings-form')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                // Handle form submission
            });
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load settings.</p>';
        }
    }

    private renderStatCard(label: string, value: string | number, icon: string): string {
        return `
            <div class="tixello-card" style="text-align: center;">
                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">${label}</p>
                <p style="font-size: 1.5rem; font-weight: 700;">${value}</p>
            </div>
        `;
    }

    private formatCurrency(amount: number): string {
        return new Intl.NumberFormat('ro-RO', {
            style: 'currency',
            currency: 'RON'
        }).format(amount);
    }
}
