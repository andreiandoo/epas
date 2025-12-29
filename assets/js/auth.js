/**
 * Ambilet.ro - Authentication Manager
 * Handles customer and organizer authentication state
 */

const AmbiletAuth = {
    // Storage keys
    KEYS: {
        CUSTOMER_TOKEN: 'ambilet_customer_token',
        CUSTOMER_DATA: 'ambilet_customer_data',
        ORGANIZER_TOKEN: 'ambilet_organizer_token',
        ORGANIZER_DATA: 'ambilet_organizer_data',
        USER_TYPE: 'ambilet_user_type',
        REDIRECT_AFTER_LOGIN: 'ambilet_redirect_after_login'
    },

    /**
     * Get current auth token (customer or organizer)
     */
    getToken() {
        const userType = this.getUserType();
        if (userType === 'organizer') {
            return localStorage.getItem(this.KEYS.ORGANIZER_TOKEN);
        }
        return localStorage.getItem(this.KEYS.CUSTOMER_TOKEN);
    },

    /**
     * Get current user type
     */
    getUserType() {
        return localStorage.getItem(this.KEYS.USER_TYPE) || null;
    },

    /**
     * Check if user is logged in
     */
    isLoggedIn() {
        return !!this.getToken();
    },

    /**
     * Check if user is a customer
     */
    isCustomer() {
        return this.getUserType() === 'customer' && this.isLoggedIn();
    },

    /**
     * Check if user is an organizer
     */
    isOrganizer() {
        return this.getUserType() === 'organizer' && this.isLoggedIn();
    },

    // ==================== CUSTOMER AUTH ====================

    /**
     * Login customer
     */
    async loginCustomer(email, password) {
        try {
            const response = await AmbiletAPI.customer.login(email, password);

            if (response.success && response.data.token) {
                this.setCustomerSession(response.data.token, response.data.customer);
                return { success: true, customer: response.data.customer };
            }

            return { success: false, message: response.message || 'Login failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Register customer
     */
    async registerCustomer(data) {
        try {
            const response = await AmbiletAPI.customer.register(data);

            if (response.success) {
                // Auto-login if token is returned
                if (response.data.token) {
                    this.setCustomerSession(response.data.token, response.data.customer);
                }
                return { success: true, customer: response.data.customer, requiresVerification: !response.data.token };
            }

            return { success: false, message: response.message || 'Registration failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Set customer session
     */
    setCustomerSession(token, customerData) {
        localStorage.setItem(this.KEYS.CUSTOMER_TOKEN, token);
        localStorage.setItem(this.KEYS.CUSTOMER_DATA, JSON.stringify(customerData));
        localStorage.setItem(this.KEYS.USER_TYPE, 'customer');

        // Clear organizer session if exists
        localStorage.removeItem(this.KEYS.ORGANIZER_TOKEN);
        localStorage.removeItem(this.KEYS.ORGANIZER_DATA);

        // Dispatch event
        window.dispatchEvent(new CustomEvent('ambilet:auth:login', {
            detail: { type: 'customer', user: customerData }
        }));
    },

    /**
     * Get customer data
     */
    getCustomerData() {
        const data = localStorage.getItem(this.KEYS.CUSTOMER_DATA);
        return data ? JSON.parse(data) : null;
    },

    /**
     * Update customer data
     */
    updateCustomerData(data) {
        const current = this.getCustomerData() || {};
        const updated = { ...current, ...data };
        localStorage.setItem(this.KEYS.CUSTOMER_DATA, JSON.stringify(updated));

        window.dispatchEvent(new CustomEvent('ambilet:auth:update', {
            detail: { type: 'customer', user: updated }
        }));
    },

    /**
     * Logout customer
     */
    async logoutCustomer() {
        try {
            await AmbiletAPI.customer.logout();
        } catch (e) {
            // Ignore logout API errors
        }

        this.clearCustomerSession();
    },

    /**
     * Clear customer session
     */
    clearCustomerSession() {
        localStorage.removeItem(this.KEYS.CUSTOMER_TOKEN);
        localStorage.removeItem(this.KEYS.CUSTOMER_DATA);
        if (this.getUserType() === 'customer') {
            localStorage.removeItem(this.KEYS.USER_TYPE);
        }

        window.dispatchEvent(new CustomEvent('ambilet:auth:logout', {
            detail: { type: 'customer' }
        }));
    },

    // ==================== ORGANIZER AUTH ====================

    /**
     * Login organizer
     */
    async loginOrganizer(email, password) {
        try {
            const response = await AmbiletAPI.organizer.login(email, password);

            if (response.success && response.data.token) {
                this.setOrganizerSession(response.data.token, response.data.organizer);
                return { success: true, organizer: response.data.organizer };
            }

            return { success: false, message: response.message || 'Login failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Register organizer
     */
    async registerOrganizer(data) {
        try {
            const response = await AmbiletAPI.organizer.register(data);

            if (response.success) {
                // Auto-login if token is returned (depends on verification requirements)
                if (response.data.token) {
                    this.setOrganizerSession(response.data.token, response.data.organizer);
                }
                return { success: true, organizer: response.data.organizer, requiresVerification: !response.data.token };
            }

            return { success: false, message: response.message || 'Registration failed' };
        } catch (error) {
            return { success: false, message: error.message, errors: error.errors };
        }
    },

    /**
     * Set organizer session
     */
    setOrganizerSession(token, organizerData) {
        localStorage.setItem(this.KEYS.ORGANIZER_TOKEN, token);
        localStorage.setItem(this.KEYS.ORGANIZER_DATA, JSON.stringify(organizerData));
        localStorage.setItem(this.KEYS.USER_TYPE, 'organizer');

        // Clear customer session if exists
        localStorage.removeItem(this.KEYS.CUSTOMER_TOKEN);
        localStorage.removeItem(this.KEYS.CUSTOMER_DATA);

        // Dispatch event
        window.dispatchEvent(new CustomEvent('ambilet:auth:login', {
            detail: { type: 'organizer', user: organizerData }
        }));
    },

    /**
     * Get organizer data
     */
    getOrganizerData() {
        const data = localStorage.getItem(this.KEYS.ORGANIZER_DATA);
        return data ? JSON.parse(data) : null;
    },

    /**
     * Update organizer data
     */
    updateOrganizerData(data) {
        const current = this.getOrganizerData() || {};
        const updated = { ...current, ...data };
        localStorage.setItem(this.KEYS.ORGANIZER_DATA, JSON.stringify(updated));

        window.dispatchEvent(new CustomEvent('ambilet:auth:update', {
            detail: { type: 'organizer', user: updated }
        }));
    },

    /**
     * Logout organizer
     */
    async logoutOrganizer() {
        try {
            await AmbiletAPI.organizer.logout();
        } catch (e) {
            // Ignore logout API errors
        }

        this.clearOrganizerSession();
    },

    /**
     * Clear organizer session
     */
    clearOrganizerSession() {
        localStorage.removeItem(this.KEYS.ORGANIZER_TOKEN);
        localStorage.removeItem(this.KEYS.ORGANIZER_DATA);
        if (this.getUserType() === 'organizer') {
            localStorage.removeItem(this.KEYS.USER_TYPE);
        }

        window.dispatchEvent(new CustomEvent('ambilet:auth:logout', {
            detail: { type: 'organizer' }
        }));
    },

    // ==================== COMMON ====================

    /**
     * Generic login method (defaults to customer)
     * Alias for loginCustomer for convenience
     */
    async login(email, password) {
        return this.loginCustomer(email, password);
    },

    /**
     * Check if authenticated (customer or organizer)
     */
    isAuthenticated() {
        return this.isLoggedIn();
    },

    /**
     * Get user data (alias for getCurrentUser)
     */
    getUser() {
        return this.getCurrentUser();
    },

    /**
     * Get current user data (customer or organizer)
     */
    getCurrentUser() {
        const userType = this.getUserType();
        if (userType === 'customer') {
            return this.getCustomerData();
        }
        if (userType === 'organizer') {
            return this.getOrganizerData();
        }
        return null;
    },

    /**
     * Full logout (clear all sessions)
     */
    logout() {
        const userType = this.getUserType();
        if (userType === 'customer') {
            return this.logoutCustomer();
        }
        if (userType === 'organizer') {
            return this.logoutOrganizer();
        }

        // Clear all just in case
        this.clearCustomerSession();
        this.clearOrganizerSession();
    },

    /**
     * Set redirect URL after login
     */
    setRedirectAfterLogin(url) {
        sessionStorage.setItem(this.KEYS.REDIRECT_AFTER_LOGIN, url);
    },

    /**
     * Get and clear redirect URL after login
     */
    getRedirectAfterLogin() {
        const url = sessionStorage.getItem(this.KEYS.REDIRECT_AFTER_LOGIN);
        sessionStorage.removeItem(this.KEYS.REDIRECT_AFTER_LOGIN);
        return url;
    },

    /**
     * Require customer authentication
     * Redirects to login if not authenticated
     */
    requireCustomerAuth(redirectUrl = null) {
        if (!this.isCustomer()) {
            const currentUrl = redirectUrl || window.location.href;
            this.setRedirectAfterLogin(currentUrl);
            window.location.href = '/login';
            return false;
        }
        return true;
    },

    /**
     * Require organizer authentication
     * Redirects to organizer login if not authenticated
     */
    requireOrganizerAuth(redirectUrl = null) {
        if (!this.isOrganizer()) {
            const currentUrl = redirectUrl || window.location.href;
            this.setRedirectAfterLogin(currentUrl);
            window.location.href = '/organizer/login';
            return false;
        }
        return true;
    },

    /**
     * Refresh user data from server
     */
    async refreshCurrentUser() {
        try {
            const userType = this.getUserType();
            if (userType === 'customer') {
                const response = await AmbiletAPI.customer.getProfile();
                if (response.success) {
                    this.updateCustomerData(response.data);
                    return response.data;
                }
            } else if (userType === 'organizer') {
                const response = await AmbiletAPI.organizer.getProfile();
                if (response.success) {
                    this.updateOrganizerData(response.data);
                    return response.data;
                }
            }
        } catch (error) {
            // Token might be expired, clear session
            if (error.isAuthError && error.isAuthError()) {
                this.logout();
            }
            throw error;
        }
        return null;
    },

    /**
     * Initialize auth state (call on page load)
     */
    init() {
        // Check if token is still valid on page load
        if (this.isLoggedIn()) {
            // Optionally verify token validity
            // this.refreshCurrentUser().catch(() => {});
        }

        // Dispatch initial state
        window.dispatchEvent(new CustomEvent('ambilet:auth:init', {
            detail: {
                isLoggedIn: this.isLoggedIn(),
                userType: this.getUserType(),
                user: this.getCurrentUser()
            }
        }));
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    AmbiletAuth.init();
});
