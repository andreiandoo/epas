/**
 * Load Test Configuration for bilete.online (Tixello Marketplace)
 *
 * Adjust BASE_URL and API keys before running against production.
 * Default: targets a local/staging environment.
 */

export const CONFIG = {
    // ── Target URLs ──────────────────────────────────────────────────
    BASE_URL: __ENV.BASE_URL || 'https://bilete.online',
    API_PROXY: __ENV.API_PROXY || '/api/proxy.php',
    CORE_API: __ENV.CORE_API || 'https://core.tixello.com',

    // API key for core Tixello API (v1/public endpoints)
    API_KEY: __ENV.API_KEY || '',

    // ── Tenant / Marketplace identifiers ─────────────────────────────
    TENANT_SLUG: __ENV.TENANT_SLUG || 'ambilet',

    // ── Sample data for tests (override via env or keep defaults) ────
    SAMPLE_EVENT_SLUGS: [
        'coldplay-music-of-the-spheres',
        'untold-festival-2026',
        'electric-castle-2026',
    ],
    SAMPLE_CATEGORY_SLUGS: ['concerte', 'festivaluri', 'teatru', 'stand-up'],
    SAMPLE_CITY_SLUGS: ['bucuresti', 'cluj', 'timisoara', 'iasi'],
    SAMPLE_SEARCH_TERMS: ['coldplay', 'untold', 'concert bucuresti', 'teatru', 'festival'],

    // Seating test data
    SAMPLE_EVENT_ID_WITH_SEATING: __ENV.SEATING_EVENT_ID || '1',
    SAMPLE_SEAT_IDS: ['A1', 'A2', 'A3', 'B1', 'B2'],

    // ── Thresholds (SLOs) ────────────────────────────────────────────
    THRESHOLDS: {
        // Page load (HTML response)
        PAGE_P95: 2000,   // 95th percentile < 2s
        PAGE_P99: 4000,   // 99th percentile < 4s

        // API responses
        API_P95: 500,     // 95th percentile < 500ms
        API_P99: 1500,    // 99th percentile < 1.5s

        // Seating (latency-critical)
        SEATING_P95: 300, // 95th percentile < 300ms
        SEATING_P99: 800, // 99th percentile < 800ms

        // Error rate
        MAX_ERROR_RATE: 0.01,  // < 1% errors

        // Availability
        MIN_SUCCESS_RATE: 0.99, // 99% success
    },

    // ── Load profiles ────────────────────────────────────────────────
    PROFILES: {
        // Smoke test – sanity check
        smoke: {
            vus: 1,
            duration: '30s',
        },

        // Normal traffic – average day
        normal: {
            stages: [
                { duration: '1m', target: 20 },   // ramp up
                { duration: '3m', target: 50 },    // steady state
                { duration: '1m', target: 100 },   // light peak
                { duration: '3m', target: 50 },    // return to normal
                { duration: '1m', target: 0 },     // ramp down
            ],
        },

        // Peak traffic – concert on-sale moment
        peak: {
            stages: [
                { duration: '30s', target: 50 },   // warm up
                { duration: '1m', target: 200 },    // ramp to peak
                { duration: '2m', target: 500 },    // sustained peak
                { duration: '3m', target: 500 },    // hold peak
                { duration: '1m', target: 200 },    // gradual decline
                { duration: '30s', target: 0 },     // cool down
            ],
        },

        // Spike – flash sale / viral moment
        spike: {
            stages: [
                { duration: '10s', target: 50 },   // baseline
                { duration: '10s', target: 1000 },  // sudden spike
                { duration: '1m', target: 1000 },   // hold spike
                { duration: '10s', target: 50 },    // drop back
                { duration: '1m', target: 50 },     // recovery
                { duration: '10s', target: 0 },     // end
            ],
        },

        // Soak test – long-running stability
        soak: {
            stages: [
                { duration: '2m', target: 100 },
                { duration: '30m', target: 100 },   // 30 min sustained
                { duration: '2m', target: 0 },
            ],
        },

        // Stress test – find breaking point
        stress: {
            stages: [
                { duration: '1m', target: 100 },
                { duration: '2m', target: 300 },
                { duration: '2m', target: 600 },
                { duration: '2m', target: 1000 },
                { duration: '2m', target: 1500 },
                { duration: '2m', target: 2000 },
                { duration: '2m', target: 0 },
            ],
        },
    },
};

/**
 * Select load profile from env or default to 'normal'
 */
export function getProfile(name) {
    const profileName = name || __ENV.PROFILE || 'normal';
    return CONFIG.PROFILES[profileName] || CONFIG.PROFILES.normal;
}

/**
 * Build full URL for marketplace pages
 */
export function pageUrl(path) {
    return `${CONFIG.BASE_URL}${path}`;
}

/**
 * Build proxy API URL
 */
export function proxyUrl(action, params) {
    let url = `${CONFIG.BASE_URL}${CONFIG.API_PROXY}?action=${action}`;
    if (params) {
        const qs = Object.entries(params)
            .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
            .join('&');
        url += `&${qs}`;
    }
    return url;
}

/**
 * Build core API URL (direct, needs API key)
 */
export function coreApiUrl(path) {
    return `${CONFIG.CORE_API}/api${path}`;
}

/**
 * Random item from array
 */
export function randomItem(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}
