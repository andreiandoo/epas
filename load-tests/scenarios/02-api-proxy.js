/**
 * Scenario 2: API Proxy Stress Test
 *
 * Hammers the /api/proxy.php endpoint with all action types.
 * This is the backbone of bilete.online – every page relies on it.
 *
 * Usage:
 *   k6 run --env BASE_URL=https://bilete.online --env PROFILE=peak scenarios/02-api-proxy.js
 */

import http from 'k6/http';
import { group } from 'k6';
import { CONFIG, getProfile, proxyUrl, randomItem } from '../config.js';
import {
    apiHeaders,
    checkApi,
    thinkTime,
    quickPause,
} from '../lib/helpers.js';

const profile = getProfile(__ENV.PROFILE);

export const options = {
    ...profile,
    thresholds: {
        'custom_api_response_ms': [
            `p(95)<${CONFIG.THRESHOLDS.API_P95}`,
            `p(99)<${CONFIG.THRESHOLDS.API_P99}`,
        ],
        'custom_error_rate': [`rate<${CONFIG.THRESHOLDS.MAX_ERROR_RATE}`],
        'http_req_failed': [`rate<${CONFIG.THRESHOLDS.MAX_ERROR_RATE}`],
    },
    tags: { scenario: 'api-proxy' },
};

export default function () {

    // ── Bootstrap / Config ───────────────────────────────────────────
    group('API: Bootstrap Config', () => {
        const res = http.get(proxyUrl('config'), apiHeaders());
        checkApi(res, 'Bootstrap config');
        quickPause();
    });

    // ── Events listing ───────────────────────────────────────────────
    group('API: Events List', () => {
        const res = http.get(proxyUrl('events'), apiHeaders());
        checkApi(res, 'Events list');
        quickPause();
    });

    // ── Featured events ──────────────────────────────────────────────
    group('API: Featured Events', () => {
        const res = http.get(proxyUrl('events.featured'), apiHeaders());
        checkApi(res, 'Featured events');
        quickPause();
    });

    // ── Past events ──────────────────────────────────────────────────
    group('API: Past Events', () => {
        const res = http.get(proxyUrl('events.past'), apiHeaders());
        checkApi(res, 'Past events');
        quickPause();
    });

    // ── Single event by slug ─────────────────────────────────────────
    group('API: Event Detail', () => {
        const slug = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);
        const res = http.get(proxyUrl('event', { slug }), apiHeaders());
        checkApi(res, `Event detail: ${slug}`);
        thinkTime(1, 2);
    });

    // ── Categories ───────────────────────────────────────────────────
    group('API: Categories', () => {
        const res = http.get(proxyUrl('categories'), apiHeaders());
        checkApi(res, 'Categories');
        quickPause();
    });

    // ── Events by category ───────────────────────────────────────────
    group('API: Events by Category', () => {
        const cat = randomItem(CONFIG.SAMPLE_CATEGORY_SLUGS);
        const res = http.get(proxyUrl('events', { category: cat }), apiHeaders());
        checkApi(res, `Events by category: ${cat}`);
        quickPause();
    });

    // ── Venues list ──────────────────────────────────────────────────
    group('API: Venues', () => {
        const res = http.get(proxyUrl('venues'), apiHeaders());
        checkApi(res, 'Venues list');
        quickPause();
    });

    // ── Artists list ─────────────────────────────────────────────────
    group('API: Artists', () => {
        const res = http.get(proxyUrl('artists'), apiHeaders());
        checkApi(res, 'Artists list');
        quickPause();
    });

    // ── Search ───────────────────────────────────────────────────────
    group('API: Search', () => {
        const term = randomItem(CONFIG.SAMPLE_SEARCH_TERMS);
        const res = http.get(proxyUrl('search', { q: term }), apiHeaders());
        checkApi(res, `Search: ${term}`);
        quickPause();
    });

    // ── Theme ────────────────────────────────────────────────────────
    group('API: Theme', () => {
        const res = http.get(proxyUrl('theme'), apiHeaders());
        checkApi(res, 'Theme config');
        quickPause();
    });

    // ── Pages / Terms / Privacy ──────────────────────────────────────
    group('API: Static Content', () => {
        const actions = ['pages.terms', 'pages.privacy'];
        const action = randomItem(actions);
        const res = http.get(proxyUrl(action), apiHeaders());
        checkApi(res, `Static: ${action}`);
        quickPause();
    });

    // ── Navigation counts ────────────────────────────────────────────
    group('API: Navigation Counts', () => {
        const res = http.get(
            `${CONFIG.BASE_URL}/api/navigation-counts.php`,
            apiHeaders()
        );
        checkApi(res, 'Navigation counts');
        quickPause();
    });
}
