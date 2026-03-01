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
        REDIRECT_AFTER_LOGIN: 'ambilet_redirect_after_login',
        REFERRAL_CODE: 'ambilet_referral_code',
        REFERRAL_INFO: 'ambilet_referral_info'
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
            // Include referral code if present
            const referralCode = this.getReferralCode();
            if (referralCode && !data.referral_code) {
                data.referral_code = referralCode;
            }

            const response = await AmbiletAPI.customer.register(data);

            if (response.success) {
                // Auto-login if token is returned
                if (response.data.token) {
                    this.setCustomerSession(response.data.token, response.data.customer);
                }

                // Clear stored referral code after successful registration
                this.clearReferralCode();

                // Show referral message if user was referred
                if (response.data.referral && response.data.referral.message) {
                    setTimeout(() => {
                        if (typeof AmbiletNotifications !== 'undefined') {
                            AmbiletNotifications.success(response.data.referral.message, 6000);
                        }
                    }, 500);
                }

                return {
                    success: true,
                    customer: response.data.customer,
                    requiresVerification: !response.data.token,
                    referral: response.data.referral
                };
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
     * Generic register method (defaults to customer)
     * Alias for registerCustomer for convenience
     */
    async register(data) {
        return this.registerCustomer(data);
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
            window.location.href = '/organizator/login';
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

    // ==================== REFERRAL HANDLING ====================

    /**
     * Get stored referral code
     */
    getReferralCode() {
        return localStorage.getItem(this.KEYS.REFERRAL_CODE);
    },

    /**
     * Store referral code
     */
    setReferralCode(code) {
        localStorage.setItem(this.KEYS.REFERRAL_CODE, code);
    },

    /**
     * Get stored referral info
     */
    getReferralInfo() {
        const info = localStorage.getItem(this.KEYS.REFERRAL_INFO);
        return info ? JSON.parse(info) : null;
    },

    /**
     * Store referral info
     */
    setReferralInfo(info) {
        localStorage.setItem(this.KEYS.REFERRAL_INFO, JSON.stringify(info));
    },

    /**
     * Clear referral data
     */
    clearReferralCode() {
        localStorage.removeItem(this.KEYS.REFERRAL_CODE);
        localStorage.removeItem(this.KEYS.REFERRAL_INFO);
    },

    /**
     * Check for referral code in URL and validate it
     */
    async checkReferralCode() {
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');

        if (!refCode) return;

        // Don't process if user is already logged in
        if (this.isLoggedIn()) return;

        // Don't process if we already have this code stored
        const storedCode = this.getReferralCode();
        if (storedCode === refCode) {
            // Show stored notification if available
            this.showReferralBanner();
            return;
        }

        try {
            const response = await AmbiletAPI.customer.validateReferralCode(refCode);
            if (response.success && response.data.valid) {
                // Store the code and info
                this.setReferralCode(refCode);
                this.setReferralInfo(response.data);

                // Show notification banner
                this.showReferralBanner(response.data);

                // Clean URL without page reload
                const url = new URL(window.location);
                url.searchParams.delete('ref');
                window.history.replaceState({}, '', url);
            }
        } catch (error) {
            console.error('Error validating referral code:', error);
        }
    },

    /**
     * Show referral notification banner
     */
    showReferralBanner(info = null) {
        const referralInfo = info || this.getReferralInfo();
        if (!referralInfo) return;

        // Don't show if already logged in
        if (this.isLoggedIn()) return;

        // Create banner if it doesn't exist
        let banner = document.getElementById('referral-banner');
        if (banner) return; // Already showing

        banner = document.createElement('div');
        banner.id = 'referral-banner';
        banner.className = 'fixed top-0 left-0 right-0 z-50 bg-gradient-to-r from-purple-600 to-pink-600 text-white py-3 px-4 text-center shadow-lg';
        banner.innerHTML = `
            <div class="container mx-auto flex items-center justify-center gap-3 flex-wrap">
                <span class="text-lg">🎉</span>
                <span class="font-medium">${referralInfo.message || ('Ai fost invitat! Primesti ' + referralInfo.referred_reward + ' puncte bonus la inregistrare.')}</span>
                <a href="/register" class="bg-white text-purple-600 px-4 py-1 rounded-full font-semibold hover:bg-gray-100 transition-colors text-sm">
                    Inregistreaza-te acum
                </a>
                <button onclick="this.closest('#referral-banner').remove()" aria-label="Închide" class="ml-2 text-white/80 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

        // Add some top padding to body to account for fixed banner
        document.body.style.paddingTop = '56px';

        document.body.insertBefore(banner, document.body.firstChild);
    },

    /**
     * Initialize auth state (call on page load)
     */
    init() {
        // Check if token is still valid on page load
        if (this.isLoggedIn()) {
            // Optionally verify token validity
            // this.refreshCurrentUser().catch(() => {});
        } else {
            // Check for referral code in URL
            this.checkReferralCode();

            // Show existing referral banner if code is stored
            if (this.getReferralCode()) {
                this.showReferralBanner();
            }
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
