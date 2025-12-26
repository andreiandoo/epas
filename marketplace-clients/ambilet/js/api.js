/**
 * AmBilet.ro API Client
 * Communicates with the Core Tixello platform
 */

class AmBiletAPI {
    constructor(config = {}) {
        this.baseUrl = config.baseUrl || 'https://core.tixello.com/api/marketplace-client';
        this.apiKey = config.apiKey || '';
    }

    /**
     * Set API key
     */
    setApiKey(key) {
        this.apiKey = key;
    }

    /**
     * Make API request
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            'X-API-Key': this.apiKey,
            ...options.headers
        };

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'API request failed');
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
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
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
        return this.get('/config');
    }

    /**
     * Get list of tenants we can sell tickets for
     */
    async getTenants() {
        return this.get('/tenants');
    }

    // ==========================================
    // Events
    // ==========================================

    /**
     * Get all events
     * @param {Object} filters - Optional filters
     */
    async getEvents(filters = {}) {
        return this.get('/events', filters);
    }

    /**
     * Get single event details
     * @param {number} eventId - Event ID
     */
    async getEvent(eventId) {
        return this.get(`/events/${eventId}`);
    }

    /**
     * Get event ticket availability
     * @param {number} eventId - Event ID
     */
    async getEventAvailability(eventId) {
        return this.get(`/events/${eventId}/availability`);
    }

    // ==========================================
    // Orders
    // ==========================================

    /**
     * Get all orders
     * @param {Object} filters - Optional filters
     */
    async getOrders(filters = {}) {
        return this.get('/orders', filters);
    }

    /**
     * Get single order details
     * @param {number} orderId - Order ID
     */
    async getOrder(orderId) {
        return this.get(`/orders/${orderId}`);
    }

    /**
     * Create a new order
     * @param {Object} orderData - Order data
     */
    async createOrder(orderData) {
        return this.post('/orders', orderData);
    }

    /**
     * Cancel an order
     * @param {number} orderId - Order ID
     */
    async cancelOrder(orderId) {
        return this.post(`/orders/${orderId}/cancel`);
    }
}

// Create global instance
window.AmBiletAPI = AmBiletAPI;

// Initialize with config
window.api = new AmBiletAPI({
    // TODO: Replace with actual API key
    apiKey: 'mpc_YOUR_API_KEY_HERE'
});
