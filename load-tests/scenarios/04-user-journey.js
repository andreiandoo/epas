/**
 * Scenario 4: Full User Journey – Browse → Select → Checkout
 *
 * Simulates a realistic end-to-end user flow on bilete.online:
 *   1. Land on homepage
 *   2. Browse events
 *   3. View event detail
 *   4. Select tickets (or seats)
 *   5. Add to cart
 *   6. Go to checkout
 *
 * This is the most realistic scenario – it mixes page loads and API calls
 * with realistic think times.
 *
 * Usage:
 *   k6 run --env BASE_URL=https://bilete.online --env PROFILE=normal scenarios/04-user-journey.js
 */

import http from 'k6/http';
import { group, check } from 'k6';
import { Trend } from 'k6/metrics';
import { CONFIG, getProfile, pageUrl, proxyUrl, randomItem } from '../config.js';
import {
    htmlHeaders,
    apiHeaders,
    checkPage,
    checkApi,
    thinkTime,
    quickPause,
    errorRate,
} from '../lib/helpers.js';

const profile = getProfile(__ENV.PROFILE);

const journeyDuration = new Trend('journey_total_ms', true);

export const options = {
    ...profile,
    thresholds: {
        'custom_page_load_ms': [`p(95)<${CONFIG.THRESHOLDS.PAGE_P95}`],
        'custom_api_response_ms': [`p(95)<${CONFIG.THRESHOLDS.API_P95}`],
        'custom_error_rate': [`rate<${CONFIG.THRESHOLDS.MAX_ERROR_RATE}`],
        'journey_total_ms': ['p(95)<25000'], // Full journey < 25s at p95
    },
    tags: { scenario: 'user-journey' },
};

export default function () {
    const journeyStart = Date.now();

    // ── Step 1: Homepage ─────────────────────────────────────────────
    group('Journey: Homepage', () => {
        // Load HTML page
        const page = http.get(pageUrl('/'), htmlHeaders());
        checkPage(page, 'Homepage HTML');

        // Parallel API calls the homepage makes
        const responses = http.batch([
            ['GET', proxyUrl('events.featured'), null, apiHeaders()],
            ['GET', proxyUrl('categories'), null, apiHeaders()],
            ['GET', proxyUrl('config'), null, apiHeaders()],
        ]);

        responses.forEach((r, i) => {
            const names = ['Featured events', 'Categories', 'Config'];
            checkApi(r, `Homepage API: ${names[i]}`);
        });

        thinkTime(3, 7); // User reads the homepage
    });

    // ── Step 2: Browse events ────────────────────────────────────────
    group('Journey: Browse Events', () => {
        // User picks a category or browses all
        const usesCategory = Math.random() > 0.5;
        let res;

        if (usesCategory) {
            const cat = randomItem(CONFIG.SAMPLE_CATEGORY_SLUGS);
            const page = http.get(pageUrl(`/view.php?category=${cat}`), htmlHeaders());
            checkPage(page, 'Category page HTML');
            res = http.get(proxyUrl('events', { category: cat }), apiHeaders());
            checkApi(res, 'Events by category API');
        } else {
            const page = http.get(pageUrl('/view.php'), htmlHeaders());
            checkPage(page, 'Events page HTML');
            res = http.get(proxyUrl('events'), apiHeaders());
            checkApi(res, 'All events API');
        }

        thinkTime(2, 5); // User scrolls through events
    });

    // ── Step 3: Quick search (50% of users) ──────────────────────────
    if (Math.random() > 0.5) {
        group('Journey: Search', () => {
            const term = randomItem(CONFIG.SAMPLE_SEARCH_TERMS);
            const res = http.get(proxyUrl('search', { q: term }), apiHeaders());
            checkApi(res, 'Search API');
            thinkTime(1, 3);
        });
    }

    // ── Step 4: Event detail page ────────────────────────────────────
    let eventData = null;
    group('Journey: Event Detail', () => {
        const slug = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);

        // Load page HTML
        const page = http.get(pageUrl(`/view.php?slug=${slug}`), htmlHeaders());
        checkPage(page, 'Event detail HTML');

        // API call for event data
        const res = http.get(proxyUrl('event', { slug }), apiHeaders());
        checkApi(res, 'Event detail API');

        if (res.status === 200) {
            try {
                eventData = JSON.parse(res.body);
            } catch (e) { /* ignore */ }
        }

        thinkTime(5, 12); // User reads description, checks dates, looks at photos
    });

    // ── Step 5: Select tickets ───────────────────────────────────────
    group('Journey: Select Tickets', () => {
        // Simulate scrolling to ticket section and selecting quantity
        // This is client-side only (no API call), but we simulate the delay
        thinkTime(3, 8); // User picks ticket type and quantity
    });

    // ── Step 6: Add to cart ──────────────────────────────────────────
    group('Journey: Add to Cart', () => {
        // Cart is localStorage-based on bilete.online, no server call
        // But users may interact with promo code validation
        if (Math.random() > 0.7) {
            // 30% try a promo code
            const res = http.get(
                proxyUrl('coupon.validate', { code: 'TESTCODE2026' }),
                apiHeaders()
            );
            // Don't fail on 404 – invalid codes are expected
            check(res, {
                'Promo code - no server error': (r) => r.status < 500,
            });
            errorRate.add(res.status >= 500);
        }
        thinkTime(1, 3);
    });

    // ── Step 7: Checkout page ────────────────────────────────────────
    group('Journey: Checkout', () => {
        // Load checkout page
        const page = http.get(pageUrl('/thank-you.php'), htmlHeaders());
        checkPage(page, 'Checkout/Thank-you page');

        // In a real flow, this would POST order data
        // We simulate the page load only (no actual payment)
        thinkTime(2, 5);
    });

    // Record full journey time
    journeyDuration.add(Date.now() - journeyStart);
}
