/**
 * Ambilet.ro - API Client
 * Wrapper for fetch with automatic authentication and error handling
 */

const AmbiletAPI = {
    /**
     * Get API base URL (uses proxy for security)
     */
    getApiUrl() {
        return window.AMBILET?.apiUrl || '/api/proxy.php';
    },

    /**
     * Make an API request via proxy
     * @param {string} endpoint - API endpoint (without base URL)
     * @param {Object} options - Fetch options
     * @returns {Promise<Object>} - API response data
     */
    async request(endpoint, options = {}) {
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
        if (endpoint === '/customer/account') return 'customer.account';
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
        if (endpoint.includes('/customer/referrals/validate')) return 'customer.referrals.validate';
        if (endpoint === '/customer/referrals' || endpoint.includes('/customer/referrals?')) return 'customer.referrals';

        // Organizer event categories, genres, venues, artists (MUST be before public patterns that use .includes())
        if (endpoint === '/organizer/event-categories') return 'organizer.event-categories';
        if (endpoint === '/organizer/event-genres' || endpoint.includes('/organizer/event-genres?')) return 'organizer.event-genres';
        if (endpoint === '/organizer/venues' || endpoint.includes('/organizer/venues?')) return 'organizer.venues';
        if (endpoint === '/organizer/artists' || endpoint.includes('/organizer/artists?')) return 'organizer.artists';

        // Public endpoints
        if (endpoint.includes('/search')) return 'search';
        if (endpoint.includes('/marketplace-events/categories')) return 'categories';
        if (endpoint.includes('/marketplace-events/cities')) return 'cities';
        if (endpoint.match(/\/marketplace-events\/[a-z0-9-]+$/i)) return 'event';
        if (endpoint.includes('/marketplace-events')) return 'events';

        // Customer favorites endpoints (MUST be before venues/artists to avoid false matches)
        if (endpoint === '/customer/favorites/artists') return 'customer.favorites.artists';
        if (endpoint === '/customer/favorites/venues') return 'customer.favorites.venues';
        if (endpoint === '/customer/favorites/summary') return 'customer.favorites.summary';

        // Venues endpoints - specific patterns first, then fallback
        if (endpoint.includes('/venues/featured')) return 'venues.featured';
        if (endpoint.match(/\/venues\/[a-z0-9-]+\/toggle-favorite$/i)) return 'venue.toggle-favorite';
        if (endpoint.match(/\/venues\/[a-z0-9-]+\/check-favorite$/i)) return 'venue.check-favorite';
        if (endpoint.match(/\/venues\/[a-z0-9-]+$/i)) return 'venue';
        if (endpoint.includes('/venues')) return 'venues';

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

        // Orders endpoints (payment)
        if (endpoint.match(/\/orders\/\d+\/pay$/)) return 'orders.pay';
        if (endpoint.match(/\/orders\/\d+\/payment-status$/)) return 'orders.status';

        // Event tracking endpoints (must be before general /events routes)
        if (endpoint.match(/\/events\/[a-z0-9-]+\/track-view$/i)) return 'event.track-view';
        if (endpoint.match(/\/events\/[a-z0-9-]+\/toggle-interest$/i)) return 'event.toggle-interest';
        if (endpoint.match(/\/events\/[a-z0-9-]+\/check-interest$/i)) return 'event.check-interest';

        // Events featured endpoint (must be before general /events)
        if (endpoint.includes('/events/featured')) return 'events.featured';
        // Events cities endpoint
        if (endpoint.includes('/events/cities')) return 'events.cities';

        // Events endpoints (match /events but not /marketplace-events which is handled above)
        if (endpoint.match(/^\/events\/[a-z0-9-]+$/i)) return 'event';
        if (endpoint.startsWith('/events')) return 'events';

        // Event categories endpoint
        if (endpoint.includes('event-categories')) return 'event-categories';

        // Event genres endpoint
        if (endpoint.includes('/event-genres')) return 'event-genres';

        // Subgenres endpoint
        if (endpoint.startsWith('/subgenres')) return 'subgenres';

        // Cities endpoint
        if (endpoint.startsWith('/cities')) return 'cities';

        // Organizer auth endpoints
        if (endpoint === '/organizer/register') return 'organizer.register';
        if (endpoint === '/organizer/login') return 'organizer.login';
        if (endpoint === '/organizer/logout') return 'organizer.logout';
        if (endpoint === '/organizer/me') return 'organizer.me';
        if (endpoint === '/organizer/settings') return 'organizer.me';
        if (endpoint === '/organizer/settings/profile') return 'organizer.profile';
        if (endpoint === '/organizer/settings/company') return 'organizer.profile';
        if (endpoint === '/organizer/settings/verify-cui') return 'organizer.verify-cui';
        if (endpoint === '/organizer/contract') return 'organizer.contract';
        if (endpoint === '/organizer/profile') return 'organizer.profile';
        if (endpoint === '/organizer/password') return 'organizer.password';
        if (endpoint === '/organizer/forgot-password') return 'organizer.forgot-password';
        if (endpoint === '/organizer/reset-password') return 'organizer.reset-password';
        if (endpoint === '/organizer/verify-email') return 'organizer.verify-email';
        if (endpoint === '/organizer/resend-verification') return 'organizer.resend-verification';
        if (endpoint === '/organizer/payout-details') return 'organizer.payout-details';

        // Organizer bank accounts
        if (endpoint === '/organizer/bank-accounts') return 'organizer.bank-accounts';
        if (endpoint.match(/\/organizer\/bank-accounts\/\d+$/)) return 'organizer.bank-account.delete';
        if (endpoint.match(/\/organizer\/bank-accounts\/\d+\/primary$/)) return 'organizer.bank-account.primary';

        // Organizer dashboard
        if (endpoint === '/organizer/dashboard') return 'organizer.dashboard';
        if (endpoint === '/organizer/dashboard/timeline') return 'organizer.dashboard.timeline';

        // Organizer events
        if (endpoint.match(/\/organizer\/events\/\d+\/participants\/export$/)) return 'organizer.event.participants.export';
        if (endpoint.match(/\/organizer\/events\/\d+\/participants$/)) return 'organizer.event.participants';
        if (endpoint.match(/\/organizer\/events\/\d+\/check-in\//)) return 'organizer.event.checkin';
        if (endpoint.match(/\/organizer\/events\/\d+\/submit$/)) return 'organizer.event.submit';
        if (endpoint.match(/\/organizer\/events\/\d+\/cancel$/)) return 'organizer.event.cancel';
        if (endpoint.match(/\/organizer\/events\/\d+$/)) return 'organizer.event';
        if (endpoint === '/organizer/events' || endpoint.includes('/organizer/events?')) return 'organizer.events';

        // Organizer orders
        if (endpoint === '/organizer/orders' || endpoint.includes('/organizer/orders?')) return 'organizer.orders';

        // Organizer finance
        if (endpoint === '/organizer/balance') return 'organizer.balance';
        if (endpoint === '/organizer/finance') return 'organizer.finance';
        if (endpoint === '/organizer/transactions' || endpoint.includes('/organizer/transactions?')) return 'organizer.transactions';
        if (endpoint.match(/\/organizer\/payouts\/\d+$/)) return 'organizer.payout';
        if (endpoint === '/organizer/payouts' || endpoint.includes('/organizer/payouts?')) return 'organizer.payouts';

        // Organizer promo codes
        if (endpoint.match(/\/organizer\/promo-codes\/\d+$/)) return 'organizer.promo-code';
        if (endpoint === '/organizer/promo-codes' || endpoint.includes('/organizer/promo-codes?')) return 'organizer.promo-codes';

        // Organizer team
        if (endpoint === '/organizer/team') return 'organizer.team';
        if (endpoint === '/organizer/team/invite') return 'organizer.team.invite';
        if (endpoint === '/organizer/team/update') return 'organizer.team.update';
        if (endpoint === '/organizer/team/remove') return 'organizer.team.remove';
        if (endpoint === '/organizer/team/resend-invite') return 'organizer.team.resend-invite';
        if (endpoint === '/organizer/team/resend-all-invites') return 'organizer.team.resend-all-invites';

        // Organizer billing/invoices
        if (endpoint.match(/\/organizer\/invoices\/\d+$/)) return 'organizer.invoice';
        if (endpoint === '/organizer/invoices' || endpoint.includes('/organizer/invoices?')) return 'organizer.invoices';
        if (endpoint === '/organizer/billing-info') return 'organizer.billing-info';
        if (endpoint === '/organizer/payment-methods') return 'organizer.payment-methods';

        // Organizer participants (all events)
        if (endpoint === '/organizer/participants' || endpoint.includes('/organizer/participants?')) return 'organizer.participants';
        if (endpoint === '/organizer/participants/checkin') return 'organizer.participants.checkin';

        // Organizer API settings
        if (endpoint === '/organizer/api-key') return 'organizer.api-key';
        if (endpoint === '/organizer/api-key/regenerate') return 'organizer.api-key.regenerate';
        if (endpoint === '/organizer/webhook') return 'organizer.webhook';

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

        // Extract order ID from /orders/{id}/pay or /orders/{id}/payment-status
        const ordersPayMatch = endpoint.match(/\/orders\/(\d+)\/(pay|payment-status)$/);
        if (ordersPayMatch) {
            return `id=${encodeURIComponent(ordersPayMatch[1])}`;
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

        // Organizer event endpoints - extract event ID
        const organizerEventParticipantsMatch = endpoint.match(/\/organizer\/events\/(\d+)\/participants/);
        if (organizerEventParticipantsMatch) {
            const eventId = organizerEventParticipantsMatch[1];
            const queryStart = endpoint.indexOf('?');
            if (queryStart !== -1) {
                return `event_id=${encodeURIComponent(eventId)}&${endpoint.substring(queryStart + 1)}`;
            }
            return `event_id=${encodeURIComponent(eventId)}`;
        }

        const organizerEventCheckinMatch = endpoint.match(/\/organizer\/events\/(\d+)\/check-in\/(.+)/);
        if (organizerEventCheckinMatch) {
            return `event_id=${encodeURIComponent(organizerEventCheckinMatch[1])}&barcode=${encodeURIComponent(organizerEventCheckinMatch[2])}`;
        }

        const organizerEventActionMatch = endpoint.match(/\/organizer\/events\/(\d+)\/(submit|cancel)$/);
        if (organizerEventActionMatch) {
            return `event_id=${encodeURIComponent(organizerEventActionMatch[1])}`;
        }

        const organizerEventMatch = endpoint.match(/\/organizer\/events\/(\d+)$/);
        if (organizerEventMatch) {
            return `event_id=${encodeURIComponent(organizerEventMatch[1])}`;
        }

        // Organizer payout endpoint - extract payout ID
        const organizerPayoutMatch = endpoint.match(/\/organizer\/payouts\/(\d+)$/);
        if (organizerPayoutMatch) {
            return `payout_id=${encodeURIComponent(organizerPayoutMatch[1])}`;
        }

        // Organizer bank account endpoint - extract account ID
        const organizerBankAccountPrimaryMatch = endpoint.match(/\/organizer\/bank-accounts\/(\d+)\/primary$/);
        if (organizerBankAccountPrimaryMatch) {
            return `account_id=${encodeURIComponent(organizerBankAccountPrimaryMatch[1])}`;
        }
        const organizerBankAccountMatch = endpoint.match(/\/organizer\/bank-accounts\/(\d+)$/);
        if (organizerBankAccountMatch) {
            return `account_id=${encodeURIComponent(organizerBankAccountMatch[1])}`;
        }

        // Organizer promo code endpoint - extract code ID
        const organizerPromoCodeMatch = endpoint.match(/\/organizer\/promo-codes\/(\d+)$/);
        if (organizerPromoCodeMatch) {
            return `code_id=${encodeURIComponent(organizerPromoCodeMatch[1])}`;
        }

        // Organizer invoice endpoint - extract invoice ID
        const organizerInvoiceMatch = endpoint.match(/\/organizer\/invoices\/(\d+)$/);
        if (organizerInvoiceMatch) {
            return `invoice_id=${encodeURIComponent(organizerInvoiceMatch[1])}`;
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
     * Extract UTM and ad click parameters from current URL
     */
    getTrackingParams() {
        const params = new URLSearchParams(window.location.search);
        const trackingParams = {};

        // UTM parameters
        const utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        utmFields.forEach(field => {
            const value = params.get(field);
            if (value) trackingParams[field] = value;
        });

        // Ad click IDs
        const clickIds = ['gclid', 'fbclid', 'ttclid', 'li_fat_id'];
        clickIds.forEach(field => {
            const value = params.get(field);
            if (value) trackingParams[field] = value;
        });

        // Facebook browser cookies (if available)
        const fbFields = ['fbc', 'fbp'];
        fbFields.forEach(field => {
            const value = params.get(field);
            if (value) trackingParams[field] = value;
        });

        // Also try to get fbc/fbp from cookies (Facebook Pixel convention)
        try {
            const cookies = document.cookie.split(';').reduce((acc, cookie) => {
                const [key, value] = cookie.trim().split('=');
                acc[key] = value;
                return acc;
            }, {});
            if (cookies['_fbc'] && !trackingParams.fbc) trackingParams.fbc = cookies['_fbc'];
            if (cookies['_fbp'] && !trackingParams.fbp) trackingParams.fbp = cookies['_fbp'];
        } catch (e) {
            // Ignore cookie access errors
        }

        // Document referrer (if not same origin)
        try {
            const referrer = document.referrer;
            if (referrer) {
                const referrerUrl = new URL(referrer);
                const currentUrl = new URL(window.location.href);
                // Only include referrer if it's from a different domain
                if (referrerUrl.hostname !== currentUrl.hostname) {
                    trackingParams.referrer = referrer;
                }
            }
        } catch (e) {
            // Ignore URL parsing errors
        }

        return trackingParams;
    },

    /**
     * Track event page view
     */
    async trackEventView(slug) {
        const trackingParams = this.getTrackingParams();
        return this.post(`/events/${slug}/track-view`, trackingParams);
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
         * Delete account
         */
        async deleteAccount(password, reason = null) {
            return AmbiletAPI.delete('/customer/account', {
                password,
                reason
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

        /**
         * Get points summary (alias for getRewardsOverview)
         */
        async getPoints() {
            return AmbiletAPI.get('/customer/rewards');
        },

        /**
         * Get XP/Level summary (alias for getRewardsOverview, XP data included)
         */
        async getXP() {
            return AmbiletAPI.get('/customer/rewards');
        },

        /**
         * Get available rewards (alias for getAvailableRewards)
         */
        async getRewards(params = {}) {
            return AmbiletAPI.get('/customer/rewards/available', params);
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
         * Validate a referral code (public, no auth required)
         */
        async validateReferralCode(code) {
            return AmbiletAPI.get('/customer/referrals/validate', { code });
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
         * Get event categories for the marketplace
         */
        async getEventCategories() {
            return AmbiletAPI.get('/organizer/event-categories');
        },

        /**
         * Get event genres filtered by event type IDs
         */
        async getEventGenres(typeIds = []) {
            return AmbiletAPI.get('/organizer/event-genres', { type_ids: typeIds });
        },

        /**
         * Search venues
         */
        async searchVenues(search = '') {
            return AmbiletAPI.get('/organizer/venues', { search });
        },

        /**
         * Search artists
         */
        async searchArtists(search = '') {
            return AmbiletAPI.get('/organizer/artists', { search });
        },

        /**
         * Create a new artist
         */
        async createArtist(name) {
            return AmbiletAPI.post('/organizer/artists', { name });
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
