/**
 * TICS.ro - API Client
 * Handles all API calls to Tixello Core
 */

const TicsAPI = {
    baseUrl: '/api/proxy.php',

    /**
     * Make a GET request
     * @param {string} endpoint - API endpoint (e.g., '/events')
     * @returns {Promise<Object>}
     */
    async get(endpoint) {
        try {
            const response = await fetch(`${this.baseUrl}?endpoint=${encodeURIComponent(endpoint)}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API GET error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Make a POST request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @returns {Promise<Object>}
     */
    async post(endpoint, data = {}) {
        try {
            const response = await fetch(`${this.baseUrl}?endpoint=${encodeURIComponent(endpoint)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API POST error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Fetch events with filters
     * @param {Object} params - Query parameters
     * @returns {Promise<Object>}
     */
    async getEvents(params = {}) {
        try {
            // Build URL with endpoint and filter params as separate query params
            const queryParams = new URLSearchParams({ endpoint: 'events', ...params });
            const response = await fetch(`${this.baseUrl}?${queryParams.toString()}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API getEvents error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Fetch single event by slug
     * @param {string} slug - Event slug
     * @returns {Promise<Object>}
     */
    async getEvent(slug) {
        try {
            const queryParams = new URLSearchParams({ endpoint: 'event', slug: slug });
            const response = await fetch(`${this.baseUrl}?${queryParams.toString()}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API getEvent error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Search events, artists, locations
     * @param {string} query - Search query
     * @param {number} limit - Results limit
     * @returns {Promise<Object>}
     */
    async search(query, limit = 5) {
        try {
            const queryParams = new URLSearchParams({ endpoint: 'search', q: query, limit: limit });
            const response = await fetch(`${this.baseUrl}?${queryParams.toString()}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API search error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get categories
     * @returns {Promise<Object>}
     */
    async getCategories() {
        try {
            const response = await fetch(`${this.baseUrl}?endpoint=categories`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API getCategories error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get cities
     * @returns {Promise<Object>}
     */
    async getCities() {
        try {
            const response = await fetch(`${this.baseUrl}?endpoint=cities`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API getCities error:', error);
            return { success: false, error: error.message };
        }
    },

    /**
     * Get genres
     * @returns {Promise<Object>}
     */
    async getGenres() {
        try {
            const response = await fetch(`${this.baseUrl}?endpoint=genres`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API getGenres error:', error);
            return { success: false, error: error.message };
        }
    }
};

// Make available globally
window.TicsAPI = TicsAPI;
