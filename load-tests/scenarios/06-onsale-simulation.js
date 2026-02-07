/**
 * Scenario 6: On-Sale Moment Simulation
 *
 * Simulates the extreme traffic pattern when tickets for a highly
 * anticipated event (e.g. Coldplay, Untold) go on sale.
 *
 * Pattern:
 *   - 500-2000 users hit the event page simultaneously
 *   - Everyone loads seating map at once
 *   - Race condition on seat holds
 *   - Mix of successful purchases and "sold out" responses
 *   - High rate of page refreshes (F5 storm)
 *
 * Usage:
 *   k6 run --env BASE_URL=https://bilete.online \
 *           --env CORE_API=https://core.tixello.com \
 *           --env SEATING_EVENT_ID=42 \
 *           --env PROFILE=spike \
 *           scenarios/06-onsale-simulation.js
 */

import http from 'k6/http';
import { group, check, sleep } from 'k6';
import { Counter, Trend, Rate } from 'k6/metrics';
import { CONFIG, getProfile, pageUrl, proxyUrl, randomItem } from '../config.js';
import {
    htmlHeaders,
    apiHeaders,
    seatingHeaders,
    checkPage,
    checkApi,
    checkSeating,
    generateSessionId,
} from '../lib/helpers.js';

const profile = getProfile(__ENV.PROFILE);

// On-sale specific metrics
const onsalePageLoads = new Counter('onsale_page_loads');
const onsaleApiCalls = new Counter('onsale_api_calls');
const onsaleSeatAttempts = new Counter('onsale_seat_attempts');
const onsaleSeatSuccess = new Counter('onsale_seat_success');
const onsaleSeatConflict = new Counter('onsale_seat_conflict');
const onsaleRefreshes = new Counter('onsale_f5_refreshes');
const onsaleErrorRate = new Rate('onsale_error_rate');
const onsalePageLatency = new Trend('onsale_page_latency_ms', true);
const onsaleApiLatency = new Trend('onsale_api_latency_ms', true);

const EVENT_ID = CONFIG.SAMPLE_EVENT_ID_WITH_SEATING;
const CORE = CONFIG.CORE_API;
const EVENT_SLUG = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);

export const options = {
    ...profile,
    thresholds: {
        'onsale_page_latency_ms': ['p(95)<5000', 'p(99)<10000'],
        'onsale_api_latency_ms': ['p(95)<2000', 'p(99)<5000'],
        'onsale_error_rate': ['rate<0.10'], // Up to 10% errors acceptable in extreme spike
        'http_req_failed': ['rate<0.15'],
    },
    tags: { scenario: 'onsale-simulation' },
};

export default function () {
    const sessionId = generateSessionId();

    // ── Phase 1: Waiting room / Page load storm ──────────────────────
    group('OnSale: Initial Page Load', () => {
        const res = http.get(pageUrl(`/view.php?slug=${EVENT_SLUG}`), htmlHeaders());

        onsalePageLoads.add(1);
        onsalePageLatency.add(res.timings.duration);

        check(res, {
            'Event page loaded': (r) => r.status === 200,
            'No server error': (r) => r.status < 500,
        });

        onsaleErrorRate.add(res.status >= 500);
    });

    // ── Phase 2: API data burst ──────────────────────────────────────
    group('OnSale: API Data Burst', () => {
        const responses = http.batch([
            ['GET', proxyUrl('event', { slug: EVENT_SLUG }), null, apiHeaders()],
            ['GET', proxyUrl('config'), null, apiHeaders()],
            ['GET', proxyUrl('categories'), null, apiHeaders()],
        ]);

        responses.forEach((r) => {
            onsaleApiCalls.add(1);
            onsaleApiLatency.add(r.timings.duration);
            onsaleErrorRate.add(r.status >= 500);
        });

        // Very short think time – users are anxious
        sleep(0.5 + Math.random() * 1.5);
    });

    // ── Phase 3: Seating map load ────────────────────────────────────
    group('OnSale: Load Seating', () => {
        const headers = seatingHeaders(sessionId);

        const responses = http.batch([
            ['GET', `${CORE}/api/public/events/${EVENT_ID}/seating`, null, headers],
            ['GET', `${CORE}/api/public/events/${EVENT_ID}/seats`, null, headers],
        ]);

        responses.forEach((r) => {
            onsaleApiCalls.add(1);
            onsaleApiLatency.add(r.timings.duration);
            onsaleErrorRate.add(r.status >= 500);
        });

        // Quick scan of the map
        sleep(1 + Math.random() * 2);
    });

    // ── Phase 4: Race to hold seats ──────────────────────────────────
    group('OnSale: Seat Hold Race', () => {
        const headers = seatingHeaders(sessionId);
        onsaleSeatAttempts.add(1);

        // Try to grab 2-4 seats
        const numSeats = 2 + Math.floor(Math.random() * 3);
        const seatIds = [];
        for (let i = 0; i < numSeats; i++) {
            // Generate semi-random seat IDs (simulating popular sections)
            const sections = ['A', 'B', 'C', 'D', 'E'];
            const section = sections[Math.floor(Math.random() * sections.length)];
            const row = 1 + Math.floor(Math.random() * 20);
            seatIds.push(`${section}${row}`);
        }

        const payload = JSON.stringify({
            event_id: EVENT_ID,
            seat_ids: seatIds,
        });

        const res = http.post(
            `${CORE}/api/public/seats/hold`,
            payload,
            headers
        );

        onsaleApiLatency.add(res.timings.duration);

        if (res.status === 200) {
            onsaleSeatSuccess.add(1);
        } else if (res.status === 409) {
            onsaleSeatConflict.add(1);
        }

        onsaleErrorRate.add(res.status >= 500);

        // Decide: proceed to checkout or refresh (panic)
        sleep(1 + Math.random() * 3);
    });

    // ── Phase 5: F5 Storm (frustrated users refreshing) ──────────────
    if (Math.random() < 0.4) {
        // 40% of users will refresh the page 1-3 times
        group('OnSale: F5 Refresh Storm', () => {
            const refreshes = 1 + Math.floor(Math.random() * 3);
            for (let i = 0; i < refreshes; i++) {
                const res = http.get(
                    pageUrl(`/view.php?slug=${EVENT_SLUG}`),
                    htmlHeaders()
                );
                onsaleRefreshes.add(1);
                onsalePageLatency.add(res.timings.duration);
                onsaleErrorRate.add(res.status >= 500);

                sleep(0.3 + Math.random() * 1); // Fast refresh
            }
        });
    }

    // ── Phase 6: Cart/Checkout attempt ───────────────────────────────
    if (Math.random() < 0.6) {
        group('OnSale: Checkout Attempt', () => {
            // Simulate navigating to checkout
            const res = http.get(pageUrl('/thank-you.php'), htmlHeaders());
            onsalePageLatency.add(res.timings.duration);
            onsaleErrorRate.add(res.status >= 500);

            sleep(2 + Math.random() * 4);
        });
    }
}
