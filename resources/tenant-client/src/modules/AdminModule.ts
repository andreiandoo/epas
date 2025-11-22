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
        this.eventBus.on('route:admin-venues', () => this.loadVenues());
        this.eventBus.on('route:admin-artists', () => this.loadArtists());
        this.eventBus.on('route:admin-seating', () => this.loadSeating());
        this.eventBus.on('route:admin-pricing', () => this.loadPricing());
        this.eventBus.on('route:admin-templates', () => this.loadTemplates());
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

    async loadVenues(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-venues-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/venues');
            const venues = response.data.data.venues || [];

            container.innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <button id="create-venue-btn" class="tixello-btn">+ Add Venue</button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left;">Name</th>
                            <th style="padding: 0.75rem; text-align: left;">Address</th>
                            <th style="padding: 0.75rem; text-align: left;">City</th>
                            <th style="padding: 0.75rem; text-align: left;">Capacity</th>
                            <th style="padding: 0.75rem; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${venues.map((venue: any) => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">${venue.name}</td>
                                <td style="padding: 0.75rem;">${venue.address || '-'}</td>
                                <td style="padding: 0.75rem;">${venue.city || '-'}</td>
                                <td style="padding: 0.75rem;">${venue.capacity || '-'}</td>
                                <td style="padding: 0.75rem;">
                                    <button class="edit-venue-btn" data-id="${venue.id}" style="color: #3b82f6; cursor: pointer; background: none; border: none;">Edit</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load venues.</p>';
        }
    }

    async loadArtists(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-artists-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/artists');
            const artists = response.data.data.artists || [];

            container.innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <button id="create-artist-btn" class="tixello-btn">+ Add Artist</button>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
                    ${artists.map((artist: any) => `
                        <div class="tixello-card">
                            <h3 style="font-weight: 600;">${artist.name}</h3>
                            <p style="font-size: 0.875rem; color: #6b7280;">${artist.genre || 'No genre'}</p>
                            <p style="font-size: 0.75rem; margin-top: 0.5rem;">${artist.events_count} events</p>
                            <button class="edit-artist-btn" data-id="${artist.id}" style="margin-top: 0.5rem; color: #3b82f6; cursor: pointer; background: none; border: none; padding: 0;">Edit</button>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load artists.</p>';
        }
    }

    async loadSeating(): Promise<void> {
        if (!this.apiClient) return;

        const layoutsContainer = document.getElementById('admin-seating-layouts');
        const tiersContainer = document.getElementById('admin-price-tiers');
        if (!layoutsContainer) return;

        try {
            const [layoutsRes, tiersRes] = await Promise.all([
                this.apiClient.get('/admin/seating/layouts'),
                this.apiClient.get('/admin/pricing/tiers')
            ]);

            const layouts = layoutsRes.data.data.layouts || [];
            const tiers = tiersRes.data.data.tiers || [];

            layoutsContainer.innerHTML = `
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left;">Name</th>
                            <th style="padding: 0.75rem; text-align: left;">Venue</th>
                            <th style="padding: 0.75rem; text-align: left;">Total Seats</th>
                            <th style="padding: 0.75rem; text-align: left;">Sections</th>
                            <th style="padding: 0.75rem; text-align: left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${layouts.map((layout: any) => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">${layout.name}</td>
                                <td style="padding: 0.75rem;">${layout.venue_name || '-'}</td>
                                <td style="padding: 0.75rem;">${layout.total_seats}</td>
                                <td style="padding: 0.75rem;">${layout.sections_count}</td>
                                <td style="padding: 0.75rem;">
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; background: ${layout.is_active ? '#d1fae5' : '#fee2e2'};">
                                        ${layout.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;

            if (tiersContainer) {
                tiersContainer.innerHTML = `
                    <div style="margin-bottom: 1rem;">
                        <button id="create-tier-btn" class="tixello-btn">+ Add Price Tier</button>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                        ${tiers.map((tier: any) => `
                            <div class="tixello-card" style="border-left: 4px solid ${tier.color};">
                                <h4 style="font-weight: 600;">${tier.name}</h4>
                                <p style="font-size: 1.25rem; font-weight: 700; color: #059669;">${this.formatCurrency(tier.base_price)}</p>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
        } catch (error) {
            layoutsContainer.innerHTML = '<p class="text-red-500">Failed to load seating data.</p>';
        }
    }

    async loadPricing(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-pricing-rules');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/pricing/rules');
            const rules = response.data.data.rules || [];

            container.innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <button id="create-rule-btn" class="tixello-btn">+ Add Pricing Rule</button>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 0.75rem; text-align: left;">Name</th>
                            <th style="padding: 0.75rem; text-align: left;">Type</th>
                            <th style="padding: 0.75rem; text-align: left;">Adjustment</th>
                            <th style="padding: 0.75rem; text-align: left;">Priority</th>
                            <th style="padding: 0.75rem; text-align: left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rules.map((rule: any) => `
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem;">${rule.name}</td>
                                <td style="padding: 0.75rem;">${rule.type.replace('_', ' ')}</td>
                                <td style="padding: 0.75rem;">
                                    ${rule.adjustment_type === 'percentage' ? `${rule.adjustment_value}%` : this.formatCurrency(rule.adjustment_value)}
                                </td>
                                <td style="padding: 0.75rem;">${rule.priority}</td>
                                <td style="padding: 0.75rem;">
                                    <span style="padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; background: ${rule.is_active ? '#d1fae5' : '#fee2e2'};">
                                        ${rule.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load pricing rules.</p>';
        }
    }

    async loadTemplates(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('admin-templates-list');
        if (!container) return;

        try {
            const response = await this.apiClient.get('/admin/templates');
            const { templates, current_template } = response.data.data;

            container.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                    ${templates.map((template: any) => `
                        <div class="tixello-card template-card ${template.id === current_template ? 'selected' : ''}"
                             style="cursor: pointer; ${template.id === current_template ? 'border: 2px solid #3b82f6;' : ''}"
                             data-template-id="${template.id}">
                            <div style="height: 150px; background: linear-gradient(135deg, ${template.colors.primary}, ${template.colors.secondary}); border-radius: 0.375rem; margin-bottom: 1rem;"></div>
                            <h3 style="font-weight: 600; margin-bottom: 0.5rem;">${template.name}</h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">${template.description}</p>
                            ${template.id === current_template ?
                                '<span style="color: #3b82f6; font-weight: 600;">✓ Current Template</span>' :
                                '<button class="select-template-btn tixello-btn" data-id="' + template.id + '">Select Template</button>'
                            }
                        </div>
                    `).join('')}
                </div>
            `;

            // Bind template selection
            document.querySelectorAll('.select-template-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const templateId = (e.target as HTMLElement).getAttribute('data-id');
                    if (templateId && this.apiClient) {
                        try {
                            await this.apiClient.post('/admin/templates/select', { template_id: templateId });
                            this.loadTemplates(); // Reload to show updated selection
                        } catch (error) {
                            alert('Failed to select template');
                        }
                    }
                });
            });
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load templates.</p>';
        }
    }

    private formatCurrency(amount: number): string {
        return new Intl.NumberFormat('ro-RO', {
            style: 'currency',
            currency: 'RON'
        }).format(amount);
    }
}
