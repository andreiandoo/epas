import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';

export class ClientModule {
    name = 'client';
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private clientToken: string | null = null;

    async init(apiClient: ApiClient, eventBus: EventBus): Promise<void> {
        this.apiClient = apiClient;
        this.eventBus = eventBus;
        this.clientToken = localStorage.getItem('client_token');

        // Auth routes
        this.eventBus.on('route:login', () => this.setupLoginForm());
        this.eventBus.on('route:register', () => this.setupRegisterForm());

        // Cart & Checkout
        this.eventBus.on('route:cart', () => this.loadCart());
        this.eventBus.on('route:checkout', () => this.loadCheckout());
        this.eventBus.on('route:thank-you', (orderNumber: string) => this.loadOrderConfirmation(orderNumber));

        // Client dashboard
        this.eventBus.on('route:account', () => this.loadAccountDashboard());
        this.eventBus.on('route:orders', () => this.loadOrders());
        this.eventBus.on('route:order-detail', (id: string) => this.loadOrderDetail(id));
        this.eventBus.on('route:tickets', () => this.loadTickets());
        this.eventBus.on('route:my-events', () => this.loadMyEvents());
        this.eventBus.on('route:profile', () => this.loadProfile());

        console.log('Client module initialized');
    }

    private getCartId(): string {
        let cartId = localStorage.getItem('cart_id');
        if (!cartId) {
            cartId = 'cart-' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('cart_id', cartId);
        }
        return cartId;
    }

    private isLoggedIn(): boolean {
        return !!this.clientToken;
    }

    private setAuthHeaders(): void {
        if (this.clientToken && this.apiClient) {
            this.apiClient.setHeader('Authorization', `Bearer ${this.clientToken}`);
        }
    }

    setupLoginForm(): void {
        const form = document.getElementById('login-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form as HTMLFormElement);
            const email = form.querySelector('input[type="email"]') as HTMLInputElement;
            const password = form.querySelector('input[type="password"]') as HTMLInputElement;

            if (this.apiClient) {
                try {
                    const response = await this.apiClient.post('/client/login', {
                        email: email.value,
                        password: password.value,
                    });

                    const { token, client } = response.data.data;
                    localStorage.setItem('client_token', token);
                    localStorage.setItem('client_name', client.name);
                    this.clientToken = token;

                    window.location.hash = '/account';
                } catch (error: any) {
                    alert(error.response?.data?.message || 'Login failed');
                }
            }
        });
    }

    setupRegisterForm(): void {
        const form = document.getElementById('register-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const inputs = form.querySelectorAll('input');
            const name = inputs[0] as HTMLInputElement;
            const email = inputs[1] as HTMLInputElement;
            const password = inputs[2] as HTMLInputElement;

            if (this.apiClient) {
                try {
                    const response = await this.apiClient.post('/client/register', {
                        name: name.value,
                        email: email.value,
                        password: password.value,
                        password_confirmation: password.value,
                    });

                    const { token, client } = response.data.data;
                    localStorage.setItem('client_token', token);
                    localStorage.setItem('client_name', client.name);
                    this.clientToken = token;

                    window.location.hash = '/account';
                } catch (error: any) {
                    alert(error.response?.data?.message || 'Registration failed');
                }
            }
        });
    }

    async loadCart(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('cart-items');
        if (!container) return;

        try {
            const cartId = this.getCartId();
            const response = await this.apiClient.get('/client/cart', {
                headers: { 'X-Cart-Id': cartId }
            });

            const { items, subtotal, fees, total, currency } = response.data.data;

            if (items.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 3rem;">
                        <p style="color: #6b7280; margin-bottom: 1rem;">Your cart is empty</p>
                        <a href="#/events" class="tixello-btn">Browse Events</a>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div style="display: grid; gap: 1rem; margin-bottom: 1.5rem;">
                    ${items.map((item: any) => `
                        <div class="tixello-card" style="display: flex; gap: 1rem; align-items: center;">
                            ${item.event.image ? `<img src="${item.event.image}" alt="${item.event.name}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 0.375rem;">` : ''}
                            <div style="flex: 1;">
                                <h3 style="font-weight: 600;">${item.event.name}</h3>
                                <p style="font-size: 0.875rem; color: #6b7280;">${item.ticket_type.name}</p>
                                <p style="font-size: 0.875rem;">${this.formatCurrency(item.price, currency)} × ${item.quantity}</p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <button class="quantity-btn" data-id="${item.id}" data-action="decrease">-</button>
                                <span>${item.quantity}</span>
                                <button class="quantity-btn" data-id="${item.id}" data-action="increase">+</button>
                                <button class="remove-btn" data-id="${item.id}" style="color: #ef4444; margin-left: 1rem;">×</button>
                            </div>
                            <div style="font-weight: 600; min-width: 80px; text-align: right;">
                                ${this.formatCurrency(item.total, currency)}
                            </div>
                        </div>
                    `).join('')}
                </div>

                <div class="tixello-card" style="max-width: 400px; margin-left: auto;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Subtotal</span>
                        <span>${this.formatCurrency(subtotal, currency)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #6b7280;">
                        <span>Service Fee</span>
                        <span>${this.formatCurrency(fees, currency)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.125rem; padding-top: 0.5rem; border-top: 1px solid #e5e7eb;">
                        <span>Total</span>
                        <span>${this.formatCurrency(total, currency)}</span>
                    </div>
                    <a href="#/checkout" class="tixello-btn" style="display: block; text-align: center; margin-top: 1rem;">Proceed to Checkout</a>
                </div>
            `;

            // Bind quantity buttons
            container.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const itemId = (e.target as HTMLElement).getAttribute('data-id');
                    const action = (e.target as HTMLElement).getAttribute('data-action');
                    const item = items.find((i: any) => i.id == itemId);
                    const newQty = action === 'increase' ? item.quantity + 1 : item.quantity - 1;

                    if (this.apiClient) {
                        await this.apiClient.put(`/client/cart/${itemId}`, {
                            quantity: newQty,
                            cart_id: cartId
                        });
                        this.loadCart();
                    }
                });
            });

            // Bind remove buttons
            container.querySelectorAll('.remove-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const itemId = (e.target as HTMLElement).getAttribute('data-id');
                    if (this.apiClient) {
                        await this.apiClient.delete(`/client/cart/${itemId}?cart_id=${cartId}`);
                        this.loadCart();
                    }
                });
            });
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load cart.</p>';
        }
    }

    async loadCheckout(): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById('checkout-form');
        if (!container) return;

        try {
            const cartId = this.getCartId();
            const response = await this.apiClient.get('/client/cart', {
                headers: { 'X-Cart-Id': cartId }
            });

            const { items, subtotal, fees, total, currency } = response.data.data;

            if (items.length === 0) {
                window.location.hash = '/cart';
                return;
            }

            container.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem;">
                    <div>
                        <h2 style="font-weight: 600; margin-bottom: 1rem;">Billing Information</h2>
                        <form id="checkout-submit-form">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Full Name</label>
                                <input type="text" name="billing_name" required
                                    style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Email</label>
                                <input type="email" name="billing_email" required
                                    style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Phone</label>
                                <input type="tel" name="billing_phone"
                                    style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                            </div>

                            <h2 style="font-weight: 600; margin: 1.5rem 0 1rem;">Payment Method</h2>
                            <div style="display: grid; gap: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer;">
                                    <input type="radio" name="payment_method" value="stripe" checked>
                                    <span>Credit Card (Stripe)</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer;">
                                    <input type="radio" name="payment_method" value="netopia">
                                    <span>Netopia</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer;">
                                    <input type="radio" name="payment_method" value="euplatesc">
                                    <span>EuPlatesc</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer;">
                                    <input type="radio" name="payment_method" value="payu">
                                    <span>PayU</span>
                                </label>
                            </div>

                            <button type="submit" class="tixello-btn" style="width: 100%; margin-top: 1.5rem;">
                                Complete Purchase
                            </button>
                        </form>
                    </div>

                    <div>
                        <div class="tixello-card">
                            <h2 style="font-weight: 600; margin-bottom: 1rem;">Order Summary</h2>
                            ${items.map((item: any) => `
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
                                    <span>${item.event.name} × ${item.quantity}</span>
                                    <span>${this.formatCurrency(item.total, currency)}</span>
                                </div>
                            `).join('')}
                            <div style="border-top: 1px solid #e5e7eb; margin-top: 1rem; padding-top: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Subtotal</span>
                                    <span>${this.formatCurrency(subtotal, currency)}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #6b7280;">
                                    <span>Service Fee</span>
                                    <span>${this.formatCurrency(fees, currency)}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.125rem;">
                                    <span>Total</span>
                                    <span>${this.formatCurrency(total, currency)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Handle form submission
            document.getElementById('checkout-submit-form')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target as HTMLFormElement;
                const formData = new FormData(form);

                if (this.apiClient) {
                    try {
                        this.setAuthHeaders();
                        const response = await this.apiClient.post('/client/checkout', {
                            cart_id: cartId,
                            billing_name: formData.get('billing_name'),
                            billing_email: formData.get('billing_email'),
                            billing_phone: formData.get('billing_phone'),
                            payment_method: formData.get('payment_method'),
                        });

                        const { order_number, payment_url } = response.data.data;

                        // In production, redirect to payment_url
                        // For now, simulate successful payment
                        await this.apiClient.post(`/client/payment/${response.data.data.order_id}/callback`, {
                            cart_id: cartId
                        });

                        localStorage.removeItem('cart_id');
                        window.location.hash = `/thank-you/${order_number}`;
                    } catch (error: any) {
                        alert(error.response?.data?.message || 'Checkout failed');
                    }
                }
            });
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load checkout.</p>';
        }
    }

    async loadOrderConfirmation(orderNumber: string): Promise<void> {
        if (!this.apiClient) return;

        const container = document.getElementById(`order-confirmation-${orderNumber}`);
        if (!container) return;

        try {
            const response = await this.apiClient.get(`/client/order-confirmation/${orderNumber}`);
            const order = response.data.data;

            container.innerHTML = `
                <div class="tixello-card" style="max-width: 500px; margin: 0 auto; text-align: left;">
                    <div style="margin-bottom: 1rem;">
                        <strong>Order Number:</strong> ${order.order_number}
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Total:</strong> ${this.formatCurrency(order.total, order.currency)}
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <strong>Confirmation sent to:</strong> ${order.billing_email}
                    </div>

                    <h3 style="font-weight: 600; margin: 1.5rem 0 1rem;">Your Tickets</h3>
                    ${order.tickets.map((ticket: any) => `
                        <div style="padding: 0.75rem; background: #f9fafb; border-radius: 0.375rem; margin-bottom: 0.5rem;">
                            <div style="font-weight: 500;">${ticket.event_name}</div>
                            <div style="font-size: 0.875rem; color: #6b7280;">
                                ${ticket.ticket_type} • ${ticket.ticket_number}
                            </div>
                        </div>
                    `).join('')}

                    <div style="margin-top: 1.5rem; text-align: center;">
                        ${this.isLoggedIn() ?
                            '<a href="#/account/tickets" class="tixello-btn">View My Tickets</a>' :
                            '<a href="#/events" class="tixello-btn">Browse More Events</a>'
                        }
                    </div>
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load order confirmation.</p>';
        }
    }

    async loadAccountDashboard(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        const container = document.querySelector('.tixello-account');
        if (!container) return;

        const clientName = localStorage.getItem('client_name') || 'User';

        container.innerHTML = `
            <h1>Welcome, ${clientName}</h1>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
                <a href="#/account/orders" class="tixello-card" style="text-decoration: none; color: inherit;">
                    <h3 style="font-weight: 600;">My Orders</h3>
                    <p style="color: #6b7280; font-size: 0.875rem;">View your order history</p>
                </a>
                <a href="#/account/tickets" class="tixello-card" style="text-decoration: none; color: inherit;">
                    <h3 style="font-weight: 600;">My Tickets</h3>
                    <p style="color: #6b7280; font-size: 0.875rem;">View and download tickets</p>
                </a>
                <a href="#/account/events" class="tixello-card" style="text-decoration: none; color: inherit;">
                    <h3 style="font-weight: 600;">Upcoming Events</h3>
                    <p style="color: #6b7280; font-size: 0.875rem;">Events you're attending</p>
                </a>
                <a href="#/account/profile" class="tixello-card" style="text-decoration: none; color: inherit;">
                    <h3 style="font-weight: 600;">Profile</h3>
                    <p style="color: #6b7280; font-size: 0.875rem;">Manage your details</p>
                </a>
            </div>
            <button id="logout-btn" style="margin-top: 2rem; color: #ef4444; background: none; border: none; cursor: pointer;">
                Logout
            </button>
        `;

        document.getElementById('logout-btn')?.addEventListener('click', () => {
            localStorage.removeItem('client_token');
            localStorage.removeItem('client_name');
            this.clientToken = null;
            window.location.hash = '/login';
        });
    }

    async loadOrders(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        if (!this.apiClient) return;

        const container = document.getElementById('orders-list');
        if (!container) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/orders');
            const { orders } = response.data.data;

            if (orders.length === 0) {
                container.innerHTML = '<p style="color: #6b7280;">No orders yet.</p>';
                return;
            }

            container.innerHTML = `
                <div style="display: grid; gap: 1rem;">
                    ${orders.map((order: any) => `
                        <a href="#/account/orders/${order.id}" class="tixello-card" style="text-decoration: none; color: inherit;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <div style="font-weight: 600;">${order.order_number}</div>
                                    <div style="font-size: 0.875rem; color: #6b7280;">
                                        ${order.tickets_count} ticket(s) • ${new Date(order.created_at).toLocaleDateString()}
                                    </div>
                                    <div style="font-size: 0.875rem; margin-top: 0.25rem;">
                                        ${order.events.join(', ')}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600;">${this.formatCurrency(order.total, order.currency)}</div>
                                    <span style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: ${order.status === 'completed' ? '#d1fae5' : '#fef3c7'};">
                                        ${order.status}
                                    </span>
                                </div>
                            </div>
                        </a>
                    `).join('')}
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load orders.</p>';
        }
    }

    async loadOrderDetail(orderId: string): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        if (!this.apiClient) return;

        const container = document.getElementById(`order-detail-${orderId}`);
        if (!container) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get(`/client/orders/${orderId}`);
            const order = response.data.data;

            container.innerHTML = `
                <h1 style="margin-bottom: 1rem;">Order ${order.order_number}</h1>
                <div class="tixello-card" style="margin-bottom: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Status</div>
                            <div style="font-weight: 500;">${order.status}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Date</div>
                            <div style="font-weight: 500;">${new Date(order.created_at).toLocaleString()}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Payment Method</div>
                            <div style="font-weight: 500;">${order.payment_method}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Total</div>
                            <div style="font-weight: 600;">${this.formatCurrency(order.total, order.currency)}</div>
                        </div>
                    </div>
                </div>

                <h2 style="font-weight: 600; margin-bottom: 1rem;">Tickets</h2>
                <div style="display: grid; gap: 1rem;">
                    ${order.tickets.map((ticket: any) => `
                        <div class="tixello-card">
                            <div style="display: flex; justify-content: space-between;">
                                <div>
                                    <div style="font-weight: 600;">${ticket.event.name}</div>
                                    <div style="font-size: 0.875rem; color: #6b7280;">
                                        ${ticket.ticket_type} • ${ticket.ticket_number}
                                    </div>
                                    <div style="font-size: 0.875rem;">
                                        ${new Date(ticket.event.date).toLocaleDateString()} • ${ticket.event.venue}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div>${this.formatCurrency(ticket.price, order.currency)}</div>
                                    <span style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: ${ticket.status === 'valid' ? '#d1fae5' : '#fee2e2'};">
                                        ${ticket.status}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load order details.</p>';
        }
    }

    async loadTickets(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        if (!this.apiClient) return;

        const container = document.getElementById('tickets-list');
        if (!container) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/tickets');
            const { tickets } = response.data.data;

            if (tickets.length === 0) {
                container.innerHTML = '<p style="color: #6b7280;">No tickets yet.</p>';
                return;
            }

            container.innerHTML = `
                <div style="display: grid; gap: 1rem;">
                    ${tickets.map((ticket: any) => `
                        <div class="tixello-card">
                            <div style="display: flex; gap: 1rem;">
                                ${ticket.event.image ? `<img src="${ticket.event.image}" alt="${ticket.event.name}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 0.375rem;">` : ''}
                                <div style="flex: 1;">
                                    <div style="font-weight: 600;">${ticket.event.name}</div>
                                    <div style="font-size: 0.875rem; color: #6b7280;">
                                        ${new Date(ticket.event.date).toLocaleDateString()} • ${ticket.event.venue}
                                    </div>
                                    <div style="font-size: 0.875rem; margin-top: 0.5rem;">
                                        ${ticket.ticket_type} • ${ticket.ticket_number}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: ${ticket.status === 'valid' ? '#d1fae5' : '#fee2e2'};">
                                        ${ticket.status}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load tickets.</p>';
        }
    }

    async loadMyEvents(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        if (!this.apiClient) return;

        const container = document.getElementById('my-events-list');
        if (!container) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/upcoming-events');
            const { events } = response.data.data;

            if (events.length === 0) {
                container.innerHTML = '<p style="color: #6b7280;">No upcoming events.</p>';
                return;
            }

            container.innerHTML = `
                <div style="display: grid; gap: 1rem;">
                    ${events.map((event: any) => `
                        <div class="tixello-card">
                            <div style="display: flex; gap: 1rem;">
                                ${event.image ? `<img src="${event.image}" alt="${event.name}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 0.375rem;">` : ''}
                                <div>
                                    <div style="font-weight: 600; font-size: 1.125rem;">${event.name}</div>
                                    <div style="color: #6b7280; margin: 0.5rem 0;">
                                        ${new Date(event.date).toLocaleDateString()} at ${new Date(event.date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                    </div>
                                    <div style="font-size: 0.875rem;">${event.venue}</div>
                                    <div style="font-size: 0.875rem; color: #6b7280;">${event.address}</div>
                                    <div style="margin-top: 0.5rem; font-size: 0.875rem; color: #3b82f6;">
                                        ${event.tickets_count} ticket(s)
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load events.</p>';
        }
    }

    async loadProfile(): Promise<void> {
        if (!this.isLoggedIn()) {
            window.location.hash = '/login';
            return;
        }

        if (!this.apiClient) return;

        const container = document.getElementById('profile-form');
        if (!container) return;

        try {
            this.setAuthHeaders();
            const response = await this.apiClient.get('/client/profile');
            const profile = response.data.data;

            container.innerHTML = `
                <form id="profile-update-form" style="max-width: 500px;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Name</label>
                        <input type="text" name="name" value="${profile.name || ''}" required
                            style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Email</label>
                        <input type="email" name="email" value="${profile.email || ''}" required
                            style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Phone</label>
                        <input type="tel" name="phone" value="${profile.phone || ''}"
                            style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>

                    <h3 style="font-weight: 600; margin-bottom: 1rem;">Change Password</h3>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Current Password</label>
                        <input type="password" name="current_password"
                            style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">New Password</label>
                        <input type="password" name="new_password"
                            style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Confirm New Password</label>
                        <input type="password" name="new_password_confirmation"
                            style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                    </div>

                    <button type="submit" class="tixello-btn">Update Profile</button>
                </form>
            `;

            document.getElementById('profile-update-form')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target as HTMLFormElement;
                const formData = new FormData(form);

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
                        alert('Profile updated successfully');
                    } catch (error: any) {
                        alert(error.response?.data?.message || 'Failed to update profile');
                    }
                }
            });
        } catch (error) {
            container.innerHTML = '<p class="text-red-500">Failed to load profile.</p>';
        }
    }

    private formatCurrency(amount: number, currency: string = 'RON'): string {
        return new Intl.NumberFormat('ro-RO', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }
}
