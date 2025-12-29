/**
 * Ambilet.ro - API Client
 * Wrapper for fetch with automatic authentication and error handling
 *
 * Supports DEMO_MODE for testing without API connection
 */

const AmbiletAPI = {
    /**
     * Check if demo mode is enabled
     */
    isDemoMode() {
        // Check both old and new config structures for backwards compatibility
        return window.AMBILET?.demoMode === true || window.AMBILET_CONFIG?.DEMO_MODE === true;
    },

    /**
     * Get API base URL (uses proxy for security)
     */
    getApiUrl() {
        return window.AMBILET?.apiUrl || '/api/proxy.php';
    },

    /**
     * Handle demo mode requests
     */
    handleDemoRequest(endpoint, options = {}) {
        const method = options.method || 'GET';
        const body = options.body ? JSON.parse(options.body) : {};

        // Simulate network delay
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                try {
                    const result = this.getDemoResponse(endpoint, method, body);
                    resolve(result);
                } catch (error) {
                    reject(error);
                }
            }, 200 + Math.random() * 300);
        });
    },

    /**
     * Get demo response based on endpoint
     */
    getDemoResponse(endpoint, method, body) {
        // Public endpoints
        if (endpoint.includes('/marketplace-events/featured')) {
            const events = DEMO_DATA.events.filter(e => e.is_featured).map(e => ({ ...e, date: e.start_date }));
            return { success: true, data: events };
        }
        if (endpoint.includes('/marketplace-events/categories')) {
            return { success: true, data: DEMO_DATA.categories };
        }
        if (endpoint.includes('/marketplace-events/cities')) {
            return { success: true, data: DEMO_DATA.cities };
        }
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+$/i)) {
            const slug = endpoint.split('/').pop();
            const event = DEMO_DATA.events.find(e => e.slug === slug);
            if (event) {
                const eventWithTickets = { ...event };
                eventWithTickets.ticket_types = DEMO_DATA.ticketTypes[event.id] || [];
                eventWithTickets.date = event.start_date;
                return { success: true, data: eventWithTickets };
            }
            return { success: false, message: 'Eveniment negasit' };
        }
        if (endpoint.includes('/marketplace-events')) {
            const events = DEMO_DATA.events.map(e => ({ ...e, date: e.start_date }));
            return { success: true, data: events };
        }

        // Customer endpoints
        if (endpoint === '/customer/login' && method === 'POST') {
            if (body.email === DEMO_DATA.customer.email && body.password === DEMO_DATA.customer.password) {
                localStorage.setItem('demo_customer_logged_in', 'true');
                return {
                    success: true,
                    data: {
                        token: 'demo_customer_token_' + Date.now(),
                        customer: DEMO_DATA.customer
                    }
                };
            }
            return { success: false, message: 'Email sau parola incorecta' };
        }
        if (endpoint === '/customer/me') {
            if (localStorage.getItem('demo_customer_logged_in') === 'true') {
                return { success: true, data: DEMO_DATA.customer };
            }
            throw new APIError('Nu esti autentificat', 401);
        }
        if (endpoint === '/customer/stats' || endpoint.startsWith('/customer/stats?')) {
            return {
                success: true,
                data: {
                    active_tickets: DEMO_DATA.customerTickets.filter(t => t.status === 'valid').length,
                    attended_events: 5,
                    points: DEMO_DATA.customer.points || 0,
                    favorites: DEMO_DATA.customerWatchlist?.length || 0
                }
            };
        }
        if (endpoint === '/customer/orders' || endpoint.startsWith('/customer/orders?')) {
            // Normalize order data for dashboard compatibility
            const orders = DEMO_DATA.customerOrders.map(order => ({
                ...order,
                reference: order.id, // Add reference alias for id
                total: order.grand_total || order.total,
                status: order.status === 'confirmed' ? 'completed' : order.status
            }));
            return { success: true, data: orders };
        }
        if (endpoint === '/customer/tickets' || endpoint.startsWith('/customer/tickets?')) {
            // Normalize ticket data for dashboard compatibility
            const tickets = DEMO_DATA.customerTickets.map(ticket => ({
                ...ticket,
                ticket_type: { name: ticket.ticket_type },
                event: {
                    ...ticket.event,
                    date: ticket.event.date,
                    image: ticket.event.image
                }
            }));
            return { success: true, data: tickets };
        }
        if (endpoint === '/customer/logout' && method === 'POST') {
            localStorage.removeItem('demo_customer_logged_in');
            return { success: true };
        }
        if (endpoint === '/customer/register' && method === 'POST') {
            localStorage.setItem('demo_customer_logged_in', 'true');
            return {
                success: true,
                data: {
                    token: 'demo_customer_token_' + Date.now(),
                    customer: { ...DEMO_DATA.customer, name: body.name || body.first_name, email: body.email }
                }
            };
        }

        // Organizer endpoints
        if (endpoint === '/organizer/login' && method === 'POST') {
            if (body.email === DEMO_DATA.organizer.email && body.password === DEMO_DATA.organizer.password) {
                localStorage.setItem('demo_organizer_logged_in', 'true');
                return {
                    success: true,
                    data: {
                        token: 'demo_organizer_token_' + Date.now(),
                        organizer: DEMO_DATA.organizer
                    }
                };
            }
            return { success: false, message: 'Email sau parola incorecta' };
        }
        if (endpoint === '/organizer/me') {
            if (localStorage.getItem('demo_organizer_logged_in') === 'true') {
                return { success: true, data: DEMO_DATA.organizer };
            }
            throw new APIError('Nu esti autentificat', 401);
        }
        if (endpoint === '/organizer/logout' && method === 'POST') {
            localStorage.removeItem('demo_organizer_logged_in');
            return { success: true };
        }
        if (endpoint === '/organizer/register' && method === 'POST') {
            localStorage.setItem('demo_organizer_logged_in', 'true');
            return {
                success: true,
                data: {
                    token: 'demo_organizer_token_' + Date.now(),
                    organizer: { ...DEMO_DATA.organizer, name: body.company_name, email: body.email }
                }
            };
        }
        if (endpoint === '/organizer/dashboard') {
            return { success: true, data: DEMO_DATA.organizerSales };
        }
        if (endpoint === '/organizer/events') {
            if (method === 'POST') {
                return { success: true, data: { id: 999, ...body }, message: 'Eveniment creat cu succes' };
            }
            return { success: true, data: DEMO_DATA.organizerEvents };
        }
        if (endpoint === '/organizer/balance') {
            return { success: true, data: DEMO_DATA.organizerFinance };
        }
        if (endpoint === '/organizer/transactions') {
            return { success: true, data: DEMO_DATA.organizerFinance.transactions };
        }
        if (endpoint === '/organizer/promo-codes') {
            if (method === 'POST') {
                return { success: true, data: { id: 999, ...body }, message: 'Cod promotional creat' };
            }
            return { success: true, data: DEMO_DATA.organizerPromoCodes };
        }
        if (endpoint.includes('/organizer/events') && endpoint.includes('/participants')) {
            return { success: true, data: DEMO_DATA.organizerParticipants };
        }

        // Default success response for other endpoints
        return { success: true, data: null, message: 'Demo mode - endpoint not implemented' };
    },

    /**
     * Make an API request via proxy
     * @param {string} endpoint - API endpoint (without base URL)
     * @param {Object} options - Fetch options
     * @returns {Promise<Object>} - API response data
     */
    async request(endpoint, options = {}) {
        // Check for demo mode
        if (this.isDemoMode()) {
            return this.handleDemoRequest(endpoint, options);
        }

        // Build proxy URL - the API key is handled server-side
        const baseUrl = this.getApiUrl();
        const action = this.getProxyAction(endpoint);
        const params = this.getProxyParams(endpoint);

        let url = `${baseUrl}?action=${action}`;
        if (params) {
            url += '&' + params;
        }

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        // Add auth token if available
        const authToken = typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getToken() : null;
        if (authToken) {
            headers['Authorization'] = `Bearer ${authToken}`;
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const data = await response.json();

            if (!response.ok) {
                throw new APIError(data.message || data.error || 'An error occurred', response.status, data.errors);
            }

            return data;
        } catch (error) {
            if (error instanceof APIError) {
                throw error;
            }
            throw new APIError('Network error. Please check your connection.', 0);
        }
    },

    /**
     * Convert endpoint to proxy action
     */
    getProxyAction(endpoint) {
        if (endpoint.includes('/search')) return 'search';
        if (endpoint.includes('/marketplace-events/categories')) return 'categories';
        if (endpoint.includes('/marketplace-events/cities')) return 'cities';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+$/i)) return 'event';
        if (endpoint.includes('/marketplace-events')) return 'events';
        if (endpoint.match(/\/venues\/[a-z0-9-]+$/i)) return 'venue';
        if (endpoint.includes('/venues')) return 'venues';
        if (endpoint.match(/\/artists\/[a-z0-9-]+$/i)) return 'artist';
        if (endpoint.includes('/artists')) return 'artists';
        if (endpoint.includes('/cart')) return 'cart';
        if (endpoint.includes('/checkout')) return 'checkout';
        return 'events'; // default
    },

    /**
     * Extract params from endpoint for proxy
     */
    getProxyParams(endpoint) {
        // Extract slug from endpoints like /marketplace-events/event-slug
        const eventMatch = endpoint.match(/\/marketplace-events\/([a-z0-9-]+)$/i);
        if (eventMatch) {
            return `slug=${encodeURIComponent(eventMatch[1])}`;
        }

        const venueMatch = endpoint.match(/\/venues\/([a-z0-9-]+)$/i);
        if (venueMatch) {
            return `slug=${encodeURIComponent(venueMatch[1])}`;
        }

        const artistMatch = endpoint.match(/\/artists\/([a-z0-9-]+)$/i);
        if (artistMatch) {
            return `slug=${encodeURIComponent(artistMatch[1])}`;
        }

        // Pass through query params
        const queryStart = endpoint.indexOf('?');
        if (queryStart !== -1) {
            return endpoint.substring(queryStart + 1);
        }

        return '';
    },

    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * PATCH request
     */
    async patch(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },

    // ==================== PUBLIC ENDPOINTS ====================

    /**
     * Get marketplace configuration
     */
    async getConfig() {
        return this.get('/config');
    },

    /**
     * Get list of events
     */
    async getEvents(params = {}) {
        return this.get('/marketplace-events', params);
    },

    /**
     * Get featured events
     */
    async getFeaturedEvents(limit = 6) {
        return this.get('/marketplace-events/featured', { limit });
    },

    /**
     * Get event categories
     */
    async getCategories() {
        return this.get('/marketplace-events/categories');
    },

    /**
     * Get event cities
     */
    async getCities() {
        return this.get('/marketplace-events/cities');
    },

    /**
     * Get single event details
     */
    async getEvent(identifier) {
        return this.get(`/marketplace-events/${identifier}`);
    },

    /**
     * Get event availability
     */
    async getEventAvailability(eventId) {
        return this.get(`/marketplace-events/${eventId}/availability`);
    },

    /**
     * Validate promo code
     */
    async validatePromoCode(code, eventId, cartTotal, ticketCount, customerEmail = null) {
        return this.post('/promo-codes/validate', {
            code,
            event_id: eventId,
            cart_total: cartTotal,
            ticket_count: ticketCount,
            customer_email: customerEmail
        });
    },

    // ==================== CUSTOMER ENDPOINTS ====================

    customer: {
        /**
         * Register new customer
         */
        async register(data) {
            return AmbiletAPI.post('/customer/register', data);
        },

        /**
         * Login customer
         */
        async login(email, password) {
            return AmbiletAPI.post('/customer/login', { email, password });
        },

        /**
         * Logout customer
         */
        async logout() {
            return AmbiletAPI.post('/customer/logout');
        },

        /**
         * Get current customer profile
         */
        async getProfile() {
            return AmbiletAPI.get('/customer/me');
        },

        /**
         * Update customer profile
         */
        async updateProfile(data) {
            return AmbiletAPI.put('/customer/profile', data);
        },

        /**
         * Change password
         */
        async changePassword(currentPassword, newPassword, confirmPassword) {
            return AmbiletAPI.put('/customer/password', {
                current_password: currentPassword,
                password: newPassword,
                password_confirmation: confirmPassword
            });
        },

        /**
         * Request password reset
         */
        async forgotPassword(email) {
            return AmbiletAPI.post('/customer/forgot-password', { email });
        },

        /**
         * Reset password
         */
        async resetPassword(token, email, password, confirmPassword) {
            return AmbiletAPI.post('/customer/reset-password', {
                token,
                email,
                password,
                password_confirmation: confirmPassword
            });
        },

        /**
         * Verify email
         */
        async verifyEmail(token) {
            return AmbiletAPI.post('/customer/verify-email', { token });
        },

        /**
         * Get customer orders
         */
        async getOrders(params = {}) {
            return AmbiletAPI.get('/customer/orders', params);
        },

        /**
         * Get single order
         */
        async getOrder(orderId) {
            return AmbiletAPI.get(`/customer/orders/${orderId}`);
        },

        /**
         * Get customer tickets
         */
        async getTickets(params = {}) {
            return AmbiletAPI.get('/customer/tickets', params);
        },

        /**
         * Get single ticket
         */
        async getTicket(ticketId) {
            return AmbiletAPI.get(`/customer/tickets/${ticketId}`);
        },

        /**
         * Get cart
         */
        async getCart() {
            return AmbiletAPI.get('/customer/cart');
        },

        /**
         * Add item to cart
         */
        async addToCart(eventId, ticketTypeId, quantity) {
            return AmbiletAPI.post('/customer/cart/items', {
                event_id: eventId,
                ticket_type_id: ticketTypeId,
                quantity
            });
        },

        /**
         * Update cart item
         */
        async updateCartItem(itemKey, quantity) {
            return AmbiletAPI.put(`/customer/cart/items/${itemKey}`, { quantity });
        },

        /**
         * Remove cart item
         */
        async removeCartItem(itemKey) {
            return AmbiletAPI.delete(`/customer/cart/items/${itemKey}`);
        },

        /**
         * Clear cart
         */
        async clearCart() {
            return AmbiletAPI.delete('/customer/cart');
        },

        /**
         * Apply promo code to cart
         */
        async applyPromoCode(code) {
            return AmbiletAPI.post('/customer/cart/promo-code', { code });
        },

        /**
         * Remove promo code from cart
         */
        async removePromoCode() {
            return AmbiletAPI.delete('/customer/cart/promo-code');
        },

        /**
         * Get checkout summary
         */
        async getCheckoutSummary() {
            return AmbiletAPI.get('/customer/checkout/summary');
        },

        /**
         * Process checkout
         */
        async checkout(data) {
            return AmbiletAPI.post('/customer/checkout', data);
        }
    },

    // ==================== ORGANIZER ENDPOINTS ====================

    organizer: {
        /**
         * Register new organizer
         */
        async register(data) {
            return AmbiletAPI.post('/organizer/register', data);
        },

        /**
         * Login organizer
         */
        async login(email, password) {
            return AmbiletAPI.post('/organizer/login', { email, password });
        },

        /**
         * Logout organizer
         */
        async logout() {
            return AmbiletAPI.post('/organizer/logout');
        },

        /**
         * Get current organizer profile
         */
        async getProfile() {
            return AmbiletAPI.get('/organizer/me');
        },

        /**
         * Update organizer profile
         */
        async updateProfile(data) {
            return AmbiletAPI.put('/organizer/profile', data);
        },

        /**
         * Get dashboard data
         */
        async getDashboard() {
            return AmbiletAPI.get('/organizer/dashboard');
        },

        /**
         * Get dashboard timeline
         */
        async getDashboardTimeline(params = {}) {
            return AmbiletAPI.get('/organizer/dashboard/timeline', params);
        },

        /**
         * Get organizer events
         */
        async getEvents(params = {}) {
            return AmbiletAPI.get('/organizer/events', params);
        },

        /**
         * Get single event
         */
        async getEvent(eventId) {
            return AmbiletAPI.get(`/organizer/events/${eventId}`);
        },

        /**
         * Create event
         */
        async createEvent(data) {
            return AmbiletAPI.post('/organizer/events', data);
        },

        /**
         * Update event
         */
        async updateEvent(eventId, data) {
            return AmbiletAPI.put(`/organizer/events/${eventId}`, data);
        },

        /**
         * Submit event for review
         */
        async submitEvent(eventId) {
            return AmbiletAPI.post(`/organizer/events/${eventId}/submit`);
        },

        /**
         * Cancel event
         */
        async cancelEvent(eventId) {
            return AmbiletAPI.post(`/organizer/events/${eventId}/cancel`);
        },

        /**
         * Get event participants
         */
        async getParticipants(eventId, params = {}) {
            return AmbiletAPI.get(`/organizer/events/${eventId}/participants`, params);
        },

        /**
         * Export participants
         */
        async exportParticipants(eventId) {
            return AmbiletAPI.get(`/organizer/events/${eventId}/participants/export`);
        },

        /**
         * Check in ticket
         */
        async checkIn(eventId, barcode) {
            return AmbiletAPI.post(`/organizer/events/${eventId}/check-in/${barcode}`);
        },

        /**
         * Undo check in
         */
        async undoCheckIn(eventId, barcode) {
            return AmbiletAPI.delete(`/organizer/events/${eventId}/check-in/${barcode}`);
        },

        /**
         * Get organizer orders
         */
        async getOrders(params = {}) {
            return AmbiletAPI.get('/organizer/orders', params);
        },

        /**
         * Get balance
         */
        async getBalance() {
            return AmbiletAPI.get('/organizer/balance');
        },

        /**
         * Get transactions
         */
        async getTransactions(params = {}) {
            return AmbiletAPI.get('/organizer/transactions', params);
        },

        /**
         * Get payouts
         */
        async getPayouts(params = {}) {
            return AmbiletAPI.get('/organizer/payouts', params);
        },

        /**
         * Request payout
         */
        async requestPayout(data) {
            return AmbiletAPI.post('/organizer/payouts', data);
        },

        /**
         * Cancel payout
         */
        async cancelPayout(payoutId) {
            return AmbiletAPI.delete(`/organizer/payouts/${payoutId}`);
        },

        /**
         * Get promo codes
         */
        async getPromoCodes(params = {}) {
            return AmbiletAPI.get('/organizer/promo-codes', params);
        },

        /**
         * Create promo code
         */
        async createPromoCode(data) {
            return AmbiletAPI.post('/organizer/promo-codes', data);
        },

        /**
         * Update promo code
         */
        async updatePromoCode(codeId, data) {
            return AmbiletAPI.put(`/organizer/promo-codes/${codeId}`, data);
        },

        /**
         * Delete promo code
         */
        async deletePromoCode(codeId) {
            return AmbiletAPI.delete(`/organizer/promo-codes/${codeId}`);
        },

        /**
         * Update payout details
         */
        async updatePayoutDetails(data) {
            return AmbiletAPI.put('/organizer/payout-details', data);
        }
    }
};

/**
 * Custom API Error class
 */
class APIError extends Error {
    constructor(message, status, errors = null) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.errors = errors;
    }

    /**
     * Check if error is authentication related
     */
    isAuthError() {
        return this.status === 401 || this.status === 403;
    }

    /**
     * Check if error is validation related
     */
    isValidationError() {
        return this.status === 422;
    }

    /**
     * Get first error message for a field
     */
    getFieldError(field) {
        if (this.errors && this.errors[field]) {
            return Array.isArray(this.errors[field])
                ? this.errors[field][0]
                : this.errors[field];
        }
        return null;
    }

    /**
     * Get all field errors
     */
    getAllFieldErrors() {
        const result = {};
        if (this.errors) {
            for (const [field, messages] of Object.entries(this.errors)) {
                result[field] = Array.isArray(messages) ? messages[0] : messages;
            }
        }
        return result;
    }
}

// Make APIError available globally
window.APIError = APIError;
