/**
 * AmBilet.ro API Client
 * Communicates with the Core platform through a secure server-side proxy.
 *
 * SECURITY: This client calls a local PHP proxy which holds the API key
 * securely on the server side. The API key is NEVER exposed to the browser.
 */

class AmBiletAPI {
    constructor(config = {}) {
        // Local proxy URL - this calls our server-side proxy.php
        this.proxyUrl = config.proxyUrl || '/api/proxy.php';
    }

    /**
     * Make API request through the secure proxy
     */
    async request(endpoint, options = {}) {
        const url = `${this.proxyUrl}?endpoint=${encodeURIComponent(endpoint)}`;

        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers
        };

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'API request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        // Add query params to the endpoint
        const queryString = new URLSearchParams(params).toString();
        const fullEndpoint = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(fullEndpoint, { method: 'GET' });
    }

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    // ==========================================
    // Configuration
    // ==========================================

    /**
     * Get marketplace client configuration
     */
    async getConfig() {
        return this.get('config');
    }

    /**
     * Get list of tenants we can sell tickets for
     */
    async getTenants() {
        return this.get('tenants');
    }

    // ==========================================
    // Events
    // ==========================================

    /**
     * Get all events
     * @param {Object} filters - Optional filters
     */
    async getEvents(filters = {}) {
        return this.get('events', filters);
    }

    /**
     * Get single event details
     * @param {number} eventId - Event ID
     */
    async getEvent(eventId) {
        return this.get(`events/${eventId}`);
    }

    /**
     * Get event ticket availability
     * @param {number} eventId - Event ID
     */
    async getEventAvailability(eventId) {
        return this.get(`events/${eventId}/availability`);
    }

    // ==========================================
    // Orders
    // ==========================================

    /**
     * Get all orders
     * @param {Object} filters - Optional filters
     */
    async getOrders(filters = {}) {
        return this.get('orders', filters);
    }

    /**
     * Get single order details
     * @param {number} orderId - Order ID
     */
    async getOrder(orderId) {
        return this.get(`orders/${orderId}`);
    }

    /**
     * Create a new order
     * @param {Object} orderData - Order data
     */
    async createOrder(orderData) {
        return this.post('orders', orderData);
    }

    /**
     * Cancel an order
     * @param {number} orderId - Order ID
     */
    async cancelOrder(orderId) {
        return this.post(`orders/${orderId}/cancel`);
    }
}

// Create global instance
window.AmBiletAPI = AmBiletAPI;

// Initialize - no API key needed! It's handled by the server-side proxy
window.api = new AmBiletAPI();
