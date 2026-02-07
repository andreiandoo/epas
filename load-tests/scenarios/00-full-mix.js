/**
 * Scenario 0: Full Traffic Mix
 *
 * Combines all scenarios with realistic traffic distribution.
 * This is the "master" test that simulates a normal day on bilete.online
 * with all types of users active simultaneously.
 *
 * Traffic distribution (based on typical marketplace analytics):
 *   - 40% Browsing (pages only)
 *   - 25% API-heavy browsing (SPA-like behavior)
 *   - 15% Full purchase journey
 *   - 10% Search-heavy users
 *   - 10% Seating-based purchases
 *
 * Usage:
 *   k6 run --env BASE_URL=https://bilete.online --env PROFILE=normal scenarios/00-full-mix.js
 */

import http from 'k6/http';
import { group, sleep } from 'k6';
import { CONFIG, getProfile, pageUrl, proxyUrl, randomItem } from '../config.js';
import {
    htmlHeaders,
    apiHeaders,
    seatingHeaders,
    checkPage,
    checkApi,
    checkSeating,
    thinkTime,
    quickPause,
    generateSessionId,
    errorRate,
} from '../lib/helpers.js';

const profile = getProfile(__ENV.PROFILE);

export const options = {
    scenarios: {
        browsers: {
            executor: 'ramping-vus',
            stages: profile.stages || [{ duration: '1m', target: profile.vus || 10 }],
            exec: 'browsingUser',
            startVUs: 0,
            tags: { user_type: 'browser' },
        },
        api_users: {
            executor: 'ramping-vus',
            stages: scaleStages(profile.stages || [{ duration: '1m', target: profile.vus || 10 }], 0.6),
            exec: 'apiUser',
            startVUs: 0,
            tags: { user_type: 'api' },
        },
        buyers: {
            executor: 'ramping-vus',
            stages: scaleStages(profile.stages || [{ duration: '1m', target: profile.vus || 10 }], 0.35),
            exec: 'buyerJourney',
            startVUs: 0,
            tags: { user_type: 'buyer' },
        },
        searchers: {
            executor: 'ramping-vus',
            stages: scaleStages(profile.stages || [{ duration: '1m', target: profile.vus || 10 }], 0.25),
            exec: 'searchUser',
            startVUs: 0,
            tags: { user_type: 'searcher' },
        },
        seating_users: {
            executor: 'ramping-vus',
            stages: scaleStages(profile.stages || [{ duration: '1m', target: profile.vus || 10 }], 0.25),
            exec: 'seatingUser',
            startVUs: 0,
            tags: { user_type: 'seating' },
        },
    },
    thresholds: {
        'custom_page_load_ms': [`p(95)<${CONFIG.THRESHOLDS.PAGE_P95}`],
        'custom_api_response_ms': [`p(95)<${CONFIG.THRESHOLDS.API_P95}`],
        'custom_seating_response_ms': [`p(95)<${CONFIG.THRESHOLDS.SEATING_P95}`],
        'custom_error_rate': [`rate<${CONFIG.THRESHOLDS.MAX_ERROR_RATE}`],
        'http_req_failed': [`rate<${CONFIG.THRESHOLDS.MAX_ERROR_RATE}`],
    },
};

// ── Scenario: Browsing User ──────────────────────────────────────────
export function browsingUser() {
    group('Browse: Homepage', () => {
        const res = http.get(pageUrl('/'), htmlHeaders());
        checkPage(res, 'Homepage');
        thinkTime(3, 6);
    });

    group('Browse: Events', () => {
        const res = http.get(pageUrl('/view.php'), htmlHeaders());
        checkPage(res, 'Events page');
        thinkTime(2, 5);
    });

    group('Browse: Event Detail', () => {
        const slug = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);
        const res = http.get(pageUrl(`/view.php?slug=${slug}`), htmlHeaders());
        checkPage(res, 'Event detail');
        thinkTime(5, 10);
    });

    // Some users browse more pages
    if (Math.random() > 0.5) {
        const pages = ['/artists.php', '/venues.php', '/past-events.php'];
        const page = randomItem(pages);
        group('Browse: Extra Page', () => {
            const res = http.get(pageUrl(page), htmlHeaders());
            checkPage(res, `Extra: ${page}`);
            thinkTime(2, 4);
        });
    }
}

// ── Scenario: API-Heavy User ─────────────────────────────────────────
export function apiUser() {
    group('API: Config + Events', () => {
        const responses = http.batch([
            ['GET', proxyUrl('config'), null, apiHeaders()],
            ['GET', proxyUrl('events'), null, apiHeaders()],
            ['GET', proxyUrl('events.featured'), null, apiHeaders()],
            ['GET', proxyUrl('categories'), null, apiHeaders()],
        ]);
        responses.forEach((r, i) => {
            checkApi(r, `API batch ${i}`);
        });
        thinkTime(1, 3);
    });

    group('API: Event + Venues', () => {
        const slug = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);
        const responses = http.batch([
            ['GET', proxyUrl('event', { slug }), null, apiHeaders()],
            ['GET', proxyUrl('venues'), null, apiHeaders()],
            ['GET', proxyUrl('artists'), null, apiHeaders()],
        ]);
        responses.forEach((r, i) => {
            checkApi(r, `API detail batch ${i}`);
        });
        thinkTime(2, 5);
    });
}

// ── Scenario: Buyer Journey ──────────────────────────────────────────
export function buyerJourney() {
    // Homepage
    group('Buy: Homepage', () => {
        http.get(pageUrl('/'), htmlHeaders());
        http.batch([
            ['GET', proxyUrl('events.featured'), null, apiHeaders()],
            ['GET', proxyUrl('config'), null, apiHeaders()],
        ]);
        thinkTime(2, 4);
    });

    // Event detail
    group('Buy: Event Detail', () => {
        const slug = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);
        http.get(pageUrl(`/view.php?slug=${slug}`), htmlHeaders());
        http.get(proxyUrl('event', { slug }), apiHeaders());
        thinkTime(5, 10);
    });

    // Select tickets + checkout
    group('Buy: Checkout', () => {
        thinkTime(3, 6); // Selecting tickets (client-side)
        http.get(pageUrl('/thank-you.php'), htmlHeaders());
        thinkTime(2, 4);
    });
}

// ── Scenario: Search User ────────────────────────────────────────────
export function searchUser() {
    const terms = ['co', 'col', 'coldplay'];

    group('Search: Autocomplete', () => {
        for (const term of terms) {
            if (term.length >= 2) {
                http.get(
                    `${CONFIG.BASE_URL}/api/search.php?q=${encodeURIComponent(term)}&limit=5`,
                    apiHeaders()
                );
            }
            sleep(0.1 + Math.random() * 0.15);
        }
    });

    group('Search: Full', () => {
        const q = randomItem(CONFIG.SAMPLE_SEARCH_TERMS);
        const res = http.get(proxyUrl('search', { q }), apiHeaders());
        checkApi(res, 'Full search');
        thinkTime(2, 4);
    });

    // Click a result
    group('Search: Click Result', () => {
        const slug = randomItem(CONFIG.SAMPLE_EVENT_SLUGS);
        const res = http.get(pageUrl(`/view.php?slug=${slug}`), htmlHeaders());
        checkPage(res, 'Search result click');
        thinkTime(3, 7);
    });
}

// ── Scenario: Seating User ───────────────────────────────────────────
export function seatingUser() {
    const sessionId = generateSessionId();
    const headers = seatingHeaders(sessionId);
    const CORE = CONFIG.CORE_API;
    const EVENT_ID = CONFIG.SAMPLE_EVENT_ID_WITH_SEATING;

    group('Seat: Load Layout', () => {
        http.batch([
            ['GET', `${CORE}/api/public/events/${EVENT_ID}/seating`, null, headers],
            ['GET', `${CORE}/api/public/events/${EVENT_ID}/seats`, null, headers],
        ]);
        thinkTime(2, 5);
    });

    group('Seat: Hold', () => {
        const payload = JSON.stringify({
            event_id: EVENT_ID,
            seat_ids: ['A' + (1 + Math.floor(Math.random() * 20))],
        });
        const res = http.post(`${CORE}/api/public/seats/hold`, payload, headers);
        checkSeating(res, 'Seat hold');
        thinkTime(3, 6);
    });

    // 30% abandon
    if (Math.random() < 0.3) {
        group('Seat: Release', () => {
            const payload = JSON.stringify({
                event_id: EVENT_ID,
                seat_ids: ['A1'],
            });
            http.del(`${CORE}/api/public/seats/hold`, payload, headers);
        });
    }
}

// ── Helper: Scale stage targets ──────────────────────────────────────
function scaleStages(stages, factor) {
    if (!stages) return undefined;
    return stages.map(s => ({
        duration: s.duration,
        target: Math.max(1, Math.round(s.target * factor)),
    }));
}
