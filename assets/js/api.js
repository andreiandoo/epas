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

        // Handle unknown endpoints
        if (!action) {
            throw new APIError(`Unknown endpoint: ${endpoint}`, 400);
        }

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
        // Customer auth endpoints
        if (endpoint === '/customer/register') return 'customer.register';
        if (endpoint === '/customer/login') return 'customer.login';
        if (endpoint === '/customer/logout') return 'customer.logout';
        if (endpoint === '/customer/me') return 'customer.me';
        if (endpoint === '/customer/profile') return 'customer.profile';
        if (endpoint === '/customer/password') return 'customer.password';
        if (endpoint === '/customer/settings') return 'customer.settings';
        if (endpoint === '/customer/forgot-password') return 'customer.forgot-password';
        if (endpoint === '/customer/reset-password') return 'customer.reset-password';
        if (endpoint === '/customer/verify-email') return 'customer.verify-email';
        if (endpoint === '/customer/resend-verification') return 'customer.resend-verification';

        // Customer orders
        if (endpoint.match(/\/customer\/orders\/\d+$/)) return 'customer.order';
        if (endpoint.includes('/customer/orders')) return 'customer.orders';

        // Customer tickets
        if (endpoint.includes('/customer/tickets/all')) return 'customer.tickets.all';
        if (endpoint.match(/\/customer\/tickets\/\d+$/)) return 'customer.ticket';
        if (endpoint.includes('/customer/tickets')) return 'customer.tickets';

        // Customer stats & dashboard
        if (endpoint.includes('/customer/stats/upcoming-events')) return 'customer.upcoming-events';
        if (endpoint.includes('/customer/stats')) return 'customer.stats.dashboard';

        // Customer reviews
        if (endpoint.includes('/customer/reviews/events-to-review')) return 'customer.reviews.to-write';
        if (endpoint.match(/\/customer\/reviews\/\d+$/) && endpoint.includes('DELETE')) return 'customer.review.delete';
        if (endpoint.match(/\/customer\/reviews\/\d+$/)) return 'customer.review.show';
        if (endpoint === '/customer/reviews' || endpoint.includes('/customer/reviews?')) return 'customer.reviews';

        // Customer watchlist
        if (endpoint.includes('/customer/watchlist/check')) return 'customer.watchlist.check';
        if (endpoint.match(/\/customer\/watchlist\/\d+$/)) return 'customer.watchlist.remove';
        if (endpoint === '/customer/watchlist' || endpoint.includes('/customer/watchlist?')) return 'customer.watchlist';

        // Customer rewards
        if (endpoint.includes('/customer/rewards/history')) return 'customer.rewards.history';
        if (endpoint.includes('/customer/rewards/badges')) return 'customer.badges';
        if (endpoint.includes('/customer/rewards/available')) return 'customer.rewards.available';
        if (endpoint.includes('/customer/rewards/redeem')) return 'customer.rewards.redeem';
        if (endpoint.includes('/customer/rewards/redemptions')) return 'customer.rewards.redemptions';
        if (endpoint === '/customer/rewards' || endpoint.includes('/customer/rewards?')) return 'customer.rewards';

        // Customer notifications
        if (endpoint.includes('/customer/notifications/unread-count')) return 'customer.notifications.unread-count';
        if (endpoint.includes('/customer/notifications/mark-read')) return 'customer.notifications.read';
        if (endpoint.includes('/customer/notifications/settings')) return 'customer.notifications.settings';
        if (endpoint.match(/\/customer\/notifications\/\d+$/)) return 'customer.notification.delete';
        if (endpoint === '/customer/notifications' || endpoint.includes('/customer/notifications?')) return 'customer.notifications';

        // Customer referrals
        if (endpoint.includes('/customer/referrals/regenerate-code')) return 'customer.referrals.regenerate';
        if (endpoint.includes('/customer/referrals/track-click')) return 'customer.referrals.track-click';
        if (endpoint.includes('/customer/referrals/leaderboard')) return 'customer.referrals.leaderboard';
        if (endpoint.includes('/customer/referrals/claim-rewards')) return 'customer.referrals.claim-rewards';
        if (endpoint === '/customer/referrals' || endpoint.includes('/customer/referrals?')) return 'customer.referrals';

        // Public endpoints
        if (endpoint.includes('/search')) return 'search';
        if (endpoint.includes('/marketplace-events/categories')) return 'categories';
        if (endpoint.includes('/marketplace-events/cities')) return 'cities';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+$/i)) return 'event';
        if (endpoint.includes('/marketplace-events')) return 'events';

        // Venues endpoints - specific patterns first, then fallback
        if (endpoint.includes('/venues/featured')) return 'venues.featured';
        if (endpoint.match(/\/venues\/[a-z0-9-]+\/toggle-favorite$/i)) return 'venue.toggle-favorite';
        if (endpoint.match(/\/venues\/[a-z0-9-]+\/check-favorite$/i)) return 'venue.check-favorite';
        if (endpoint.match(/\/venues\/[a-z0-9-]+$/i)) return 'venue';
        if (endpoint.includes('/venues')) return 'venues';

        // Customer favorites endpoints (must be before artists/venues)
        if (endpoint === '/customer/favorites/artists') return 'customer.favorites.artists';
        if (endpoint === '/customer/favorites/venues') return 'customer.favorites.venues';
        if (endpoint === '/customer/favorites/summary') return 'customer.favorites.summary';

        // Artists endpoints - specific patterns first, then fallback
        if (endpoint.includes('/artists/featured')) return 'artists.featured';
        if (endpoint.includes('/artists/trending')) return 'artists.trending';
        if (endpoint.includes('/artists/genre-counts')) return 'artists.genre-counts';
        if (endpoint.includes('/artists/alphabet')) return 'artists.alphabet';
        if (endpoint.match(/\/artists\/[a-z0-9-]+\/toggle-favorite$/i)) return 'artist.toggle-favorite';
        if (endpoint.match(/\/artists\/[a-z0-9-]+\/check-favorite$/i)) return 'artist.check-favorite';
        if (endpoint.match(/\/artists\/[a-z0-9-]+\/events/i)) return 'artist.events';
        if (endpoint.match(/\/artists\/[a-z0-9-]+$/i)) return 'artist';
        if (endpoint.includes('/artists')) return 'artists';

        // Locations endpoints
        if (endpoint.includes('/locations/stats')) return 'locations.stats';
        if (endpoint.includes('/locations/cities/featured')) return 'locations.cities.featured';
        if (endpoint.includes('/locations/cities/alphabet')) return 'locations.cities.alphabet';
        if (endpoint.match(/\/locations\/cities\/[a-z0-9-]+$/i)) return 'locations.city';
        if (endpoint.includes('/locations/cities')) return 'locations.cities';
        if (endpoint.match(/\/locations\/regions\/[a-z0-9-]+$/i)) return 'locations.region';
        if (endpoint.includes('/locations/regions')) return 'locations.regions';

        if (endpoint.includes('/cart')) return 'cart';
        if (endpoint.includes('/checkout')) return 'checkout';

        // Event tracking endpoints (must be before general /events routes)
        if (endpoint.match(/\/events\/[a-z0-9-]+\/track-view$/i)) return 'event.track-view';
        if (endpoint.match(/\/events\/[a-z0-9-]+\/toggle-interest$/i)) return 'event.toggle-interest';
        if (endpoint.match(/\/events\/[a-z0-9-]+\/check-interest$/i)) return 'event.check-interest';

        // Events endpoints (match /events but not /marketplace-events which is handled above)
        if (endpoint.match(/^\/events\/[a-z0-9-]+$/i)) return 'event';
        if (endpoint.startsWith('/events')) return 'events';

        // Event categories endpoint
        if (endpoint.includes('event-categories')) return 'event-categories';

        // Genres - not implemented, will return error
        if (endpoint.includes('/genres')) return 'genres';

        // Cities endpoint
        if (endpoint.startsWith('/cities')) return 'cities';

        return null; // unknown endpoint - will cause error
    },

    /**
     * Extract params from endpoint for proxy
     */
    getProxyParams(endpoint) {
        // Extract order ID from /customer/orders/{id}
        const orderMatch = endpoint.match(/\/customer\/orders\/(\d+)/);
        if (orderMatch) {
            return `id=${encodeURIComponent(orderMatch[1])}`;
        }

        // Extract ticket ID from /customer/tickets/{id}
        const ticketMatch = endpoint.match(/\/customer\/tickets\/(\d+)/);
        if (ticketMatch) {
            return `id=${encodeURIComponent(ticketMatch[1])}`;
        }

        // Extract review ID from /customer/reviews/{id}
        const reviewMatch = endpoint.match(/\/customer\/reviews\/(\d+)/);
        if (reviewMatch) {
            return `id=${encodeURIComponent(reviewMatch[1])}`;
        }

        // Extract watchlist ID from /customer/watchlist/{id}
        const watchlistMatch = endpoint.match(/\/customer\/watchlist\/(\d+)/);
        if (watchlistMatch) {
            return `id=${encodeURIComponent(watchlistMatch[1])}`;
        }

        // Extract notification ID from /customer/notifications/{id}
        const notificationMatch = endpoint.match(/\/customer\/notifications\/(\d+)/);
        if (notificationMatch) {
            return `id=${encodeURIComponent(notificationMatch[1])}`;
        }

        // Extract slug from endpoints like /marketplace-events/event-slug
        const eventMatch = endpoint.match(/\/marketplace-events\/([a-z0-9-]+)$/i);
        if (eventMatch) {
            return `slug=${encodeURIComponent(eventMatch[1])}`;
        }

        // Extract slug from event tracking endpoints: /events/{slug}/track-view, /events/{slug}/toggle-interest, /events/{slug}/check-interest
        const eventTrackingMatch = endpoint.match(/\/events\/([a-z0-9-]+)\/(track-view|toggle-interest|check-interest)$/i);
        if (eventTrackingMatch) {
            return `slug=${encodeURIComponent(eventTrackingMatch[1])}`;
        }

        // Venue favorite endpoints - extract slug before /toggle-favorite or /check-favorite
        const venueFavoriteMatch = endpoint.match(/\/venues\/([a-z0-9-]+)\/(toggle-favorite|check-favorite)/i);
        if (venueFavoriteMatch) {
            return `slug=${encodeURIComponent(venueFavoriteMatch[1])}`;
        }

        const venueMatch = endpoint.match(/\/venues\/([a-z0-9-]+)$/i);
        if (venueMatch) {
            return `slug=${encodeURIComponent(venueMatch[1])}`;
        }

        // Artist favorite endpoints - extract slug before /toggle-favorite or /check-favorite
        const artistFavoriteMatch = endpoint.match(/\/artists\/([a-z0-9-]+)\/(toggle-favorite|check-favorite)/i);
        if (artistFavoriteMatch) {
            return `slug=${encodeURIComponent(artistFavoriteMatch[1])}`;
        }

        // Artist events endpoint - extract slug before /events
        const artistEventsMatch = endpoint.match(/\/artists\/([a-z0-9-]+)\/events/i);
        if (artistEventsMatch) {
            const slug = artistEventsMatch[1];
            const queryStart = endpoint.indexOf('?');
            if (queryStart !== -1) {
                return `slug=${encodeURIComponent(slug)}&${endpoint.substring(queryStart + 1)}`;
            }
            return `slug=${encodeURIComponent(slug)}`;
        }

        const artistMatch = endpoint.match(/\/artists\/([a-z0-9-]+)$/i);
        if (artistMatch) {
            return `slug=${encodeURIComponent(artistMatch[1])}`;
        }

        // Location city endpoint - extract slug
        const cityMatch = endpoint.match(/\/locations\/cities\/([a-z0-9-]+)$/i);
        if (cityMatch) {
            return `slug=${encodeURIComponent(cityMatch[1])}`;
        }

        // Location region endpoint - extract slug
        const regionMatch = endpoint.match(/\/locations\/regions\/([a-z0-9-]+)$/i);
        if (regionMatch) {
            return `slug=${encodeURIComponent(regionMatch[1])}`;
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
     * Track event page view
     */
    async trackEventView(slug) {
        return this.post(`/events/${slug}/track-view`);
    },

    /**
     * Toggle interest for an event
     */
    async toggleEventInterest(slug) {
        return this.post(`/events/${slug}/toggle-interest`);
    },

    /**
     * Check if user is interested in an event
     */
    async checkEventInterest(slug) {
        return this.get(`/events/${slug}/check-interest`);
    },

    /**
     * Toggle favorite for an artist
     */
    async toggleArtistFavorite(slug) {
        return this.post(`/artists/${slug}/toggle-favorite`);
    },

    /**
     * Check if user has favorited an artist
     */
    async checkArtistFavorite(slug) {
        return this.get(`/artists/${slug}/check-favorite`);
    },

    /**
     * Toggle favorite for a venue
     */
    async toggleVenueFavorite(slug) {
        return this.post(`/venues/${slug}/toggle-favorite`);
    },

    /**
     * Check if user has favorited a venue
     */
    async checkVenueFavorite(slug) {
        return this.get(`/venues/${slug}/check-favorite`);
    },

    /**
     * Get list of all favorite artists
     */
    async getFavoriteArtists() {
        return this.get('/customer/favorites/artists');
    },

    /**
     * Get list of all favorite venues
     */
    async getFavoriteVenues() {
        return this.get('/customer/favorites/venues');
    },

    /**
     * Get favorites summary (counts)
     */
    async getFavoritesSummary() {
        return this.get('/customer/favorites/summary');
    },

    /**
     * Get single venue details
     */
    async getVenue(slug) {
        return this.get(`/venues/${slug}`);
    },

    /**
     * Get list of venues
     */
    async getVenues(params = {}) {
        return this.get('/venues', params);
    },

    /**
     * Get featured venues
     */
    async getFeaturedVenues(limit = 6) {
        return this.get('/venues/featured', { limit });
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
         * Get all tickets with filter (upcoming, past, all)
         */
        async getAllTickets(filter = 'all', params = {}) {
            return AmbiletAPI.get('/customer/tickets/all', { filter, ...params });
        },

        /**
         * Get single ticket with QR data
         */
        async getTicket(ticketId) {
            return AmbiletAPI.get(`/customer/tickets/${ticketId}`);
        },

        // ==================== DASHBOARD & STATS ====================

        /**
         * Get dashboard stats (orders, tickets, events, rewards)
         */
        async getDashboardStats() {
            return AmbiletAPI.get('/customer/stats');
        },

        /**
         * Get upcoming events for dashboard
         */
        async getUpcomingEvents(limit = 5) {
            return AmbiletAPI.get('/customer/stats/upcoming-events', { limit });
        },

        // ==================== REVIEWS ====================

        /**
         * Get customer reviews
         */
        async getReviews(params = {}) {
            return AmbiletAPI.get('/customer/reviews', params);
        },

        /**
         * Get events available to review
         */
        async getEventsToReview(params = {}) {
            return AmbiletAPI.get('/customer/reviews/events-to-review', params);
        },

        /**
         * Submit a review
         */
        async submitReview(data) {
            return AmbiletAPI.post('/customer/reviews', data);
        },

        /**
         * Get single review
         */
        async getReview(reviewId) {
            return AmbiletAPI.get(`/customer/reviews/${reviewId}`);
        },

        /**
         * Update a review
         */
        async updateReview(reviewId, data) {
            return AmbiletAPI.put(`/customer/reviews/${reviewId}`, data);
        },

        /**
         * Delete a review
         */
        async deleteReview(reviewId) {
            return AmbiletAPI.delete(`/customer/reviews/${reviewId}`);
        },

        // ==================== WATCHLIST ====================

        /**
         * Get watchlist
         */
        async getWatchlist(params = {}) {
            return AmbiletAPI.get('/customer/watchlist', params);
        },

        /**
         * Add event to watchlist
         */
        async addToWatchlist(eventId, options = {}) {
            return AmbiletAPI.post('/customer/watchlist', {
                event_id: eventId,
                notify_on_sale: options.notifyOnSale !== false,
                notify_on_changes: options.notifyOnChanges !== false
            });
        },

        /**
         * Update watchlist item preferences
         */
        async updateWatchlistItem(watchlistId, data) {
            return AmbiletAPI.put(`/customer/watchlist/${watchlistId}`, data);
        },

        /**
         * Remove from watchlist/favorites
         * @param {string} type - 'event', 'artist', or 'venue'
         * @param {number} id - The item ID
         */
        async removeFromWatchlist(type, id) {
            if (type === 'event') {
                // Remove event from watchlist
                return AmbiletAPI.delete(`/customer/watchlist/${id}`);
            } else if (type === 'artist') {
                // Toggle artist favorite off
                return AmbiletAPI.post(`/artists/${id}/toggle-favorite`);
            } else if (type === 'venue') {
                // Toggle venue favorite off
                return AmbiletAPI.post(`/venues/${id}/toggle-favorite`);
            }
            return { success: false, message: 'Unknown type' };
        },

        /**
         * Check if event is in watchlist
         */
        async checkWatchlist(eventId) {
            return AmbiletAPI.get('/customer/watchlist/check', { event_id: eventId });
        },

        // ==================== REWARDS & GAMIFICATION ====================

        /**
         * Get rewards overview (points, XP, badges count)
         */
        async getRewardsOverview() {
            return AmbiletAPI.get('/customer/rewards');
        },

        /**
         * Get points history
         */
        async getPointsHistory(params = {}) {
            return AmbiletAPI.get('/customer/rewards/history', params);
        },

        /**
         * Get badges (earned and available)
         */
        async getBadges() {
            return AmbiletAPI.get('/customer/rewards/badges');
        },

        /**
         * Get available rewards to redeem
         */
        async getAvailableRewards(params = {}) {
            return AmbiletAPI.get('/customer/rewards/available', params);
        },

        /**
         * Redeem a reward
         */
        async redeemReward(rewardId) {
            return AmbiletAPI.post('/customer/rewards/redeem', { reward_id: rewardId });
        },

        /**
         * Get redemption history
         */
        async getRedemptions(params = {}) {
            return AmbiletAPI.get('/customer/rewards/redemptions', params);
        },

        // ==================== NOTIFICATIONS ====================

        /**
         * Get notifications
         */
        async getNotifications(params = {}) {
            return AmbiletAPI.get('/customer/notifications', params);
        },

        /**
         * Get unread notifications count
         */
        async getUnreadNotificationsCount() {
            return AmbiletAPI.get('/customer/notifications/unread-count');
        },

        /**
         * Mark notifications as read
         */
        async markNotificationsRead(notificationIds = null) {
            return AmbiletAPI.post('/customer/notifications/mark-read', {
                notification_ids: notificationIds // null = mark all
            });
        },

        /**
         * Delete notification
         */
        async deleteNotification(notificationId) {
            return AmbiletAPI.delete(`/customer/notifications/${notificationId}`);
        },

        /**
         * Get notification settings
         */
        async getNotificationSettings() {
            return AmbiletAPI.get('/customer/notifications/settings');
        },

        /**
         * Update notification settings
         */
        async updateNotificationSettings(settings) {
            return AmbiletAPI.put('/customer/notifications/settings', settings);
        },

        // ==================== REFERRALS ====================

        /**
         * Get referral program info and stats
         */
        async getReferrals() {
            return AmbiletAPI.get('/customer/referrals');
        },

        /**
         * Regenerate referral code
         */
        async regenerateReferralCode() {
            return AmbiletAPI.post('/customer/referrals/regenerate-code');
        },

        /**
         * Track referral link click
         */
        async trackReferralClick(code, source = null) {
            return AmbiletAPI.post('/customer/referrals/track-click', { code, source });
        },

        /**
         * Get referral leaderboard
         */
        async getReferralLeaderboard(period = 'month', limit = 10) {
            return AmbiletAPI.get('/customer/referrals/leaderboard', { period, limit });
        },

        /**
         * Claim pending referral rewards
         */
        async claimReferralRewards() {
            return AmbiletAPI.post('/customer/referrals/claim-rewards');
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
