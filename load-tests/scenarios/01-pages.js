/**
 * Scenario 1: Page Load Performance
 *
 * Tests all critical marketplace pages for response time and availability.
 * Simulates real user browsing patterns on bilete.online.
 *
 * Usage:
 *   k6 run --env BASE_URL=https://bilete.online --env PROFILE=normal scenarios/01-pages.js
 */

import http from 'k6/http';
import { group } from 'k6';
import { CONFIG, getProfile, pageUrl, randomItem } from '../config.js';
import {
    htmlHeaders,
    checkPage,
    thinkTime,
    quickPause,
    errorRate,
} from '../lib/helpers.js';

const profile = getProfile(__ENV.PROFILE);

export const options = {
    ...profile,
    thresholds: {
        'custom_page_load_ms': [
            `p(95)<${CONFIG.THRESHOLDS.PAGE_P95}`,
            `p(99)<${CONFIG.THRESHOLDS.PAGE_P99}`,
        ],
        'custom_error_rate': [`rate<${CONFIG.THRESHOLDS.MAX_ERROR_RATE}`],
        'custom_ttfb_ms': ['p(95)<1500'],
        'http_req_failed': [`rate<${CONFIG.THRESHOLDS.MAX_ERROR_RATE}`],
    },
    tags: { scenario: 'pages' },
};

export default function () {
    // ── Homepage ─────────────────────────────────────────────────────
    group('Homepage', () => {
        const res = http.get(pageUrl('/'), htmlHeaders());
        checkPage(res, 'Homepage');
        thinkTime(2, 5);
    });

    // ── Event listing (main browse page) ─────────────────────────────
    group('Events Listing', () => {
        const res = http.get(pageUrl('/view.php'), htmlHeaders());
        checkPage(res, 'Events listing');
        thinkTime(1, 3);
    });

    // ── Category page ────────────────────────────────────────────────
    group('Category Page', () => {
        // Simulated as query-param or slug-based depending on routing
        const cat = randomItem(CONFIG.SAMPLE_CATEGORY_SLUGS);
        const res = http.get(pageUrl(`/view.php?category=${cat}`), htmlHeaders());
        checkPage(res, `Category: ${cat}`);
        thinkTime(1, 3);
    });

    // ── Event detail page ────────────────────────────────────────────
    group('Event Detail', () => {
        const slug = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);
        const res = http.get(pageUrl(`/view.php?slug=${slug}`), htmlHeaders());
        checkPage(res, `Event: ${slug}`);
        thinkTime(3, 8); // Users spend more time reading event details
    });

    // ── Artists page ─────────────────────────────────────────────────
    group('Artists Page', () => {
        const res = http.get(pageUrl('/artists.php'), htmlHeaders());
        checkPage(res, 'Artists listing');
        thinkTime(1, 3);
    });

    // ── Venues page ──────────────────────────────────────────────────
    group('Venues Page', () => {
        const res = http.get(pageUrl('/venues.php'), htmlHeaders());
        checkPage(res, 'Venues listing');
        thinkTime(1, 3);
    });

    // ── Region / City page ───────────────────────────────────────────
    group('Region Page', () => {
        const city = randomItem(CONFIG.SAMPLE_CITY_SLUGS);
        const res = http.get(pageUrl(`/region.php?slug=${city}`), htmlHeaders());
        checkPage(res, `Region: ${city}`);
        thinkTime(1, 2);
    });

    // ── Static pages ─────────────────────────────────────────────────
    group('Static Pages', () => {
        const pages = ['/about.php', '/terms.php', '/privacy.php', '/partners.php'];
        const page = randomItem(pages);
        const res = http.get(pageUrl(page), htmlHeaders());
        checkPage(res, `Static: ${page}`);
        quickPause();
    });

    // ── Past events ──────────────────────────────────────────────────
    group('Past Events', () => {
        const res = http.get(pageUrl('/past-events.php'), htmlHeaders());
        checkPage(res, 'Past events');
        quickPause();
    });

    // ── Login page ───────────────────────────────────────────────────
    group('Login Page', () => {
        const res = http.get(pageUrl('/login.php'), htmlHeaders());
        checkPage(res, 'Login page');
        quickPause();
    });
}
