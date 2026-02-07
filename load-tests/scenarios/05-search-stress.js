/**
 * Scenario 5: Search & Autocomplete Stress Test
 *
 * Simulates rapid search-as-you-type behavior where every keystroke
 * triggers an API call. This tests the proxy + core API search performance.
 *
 * Usage:
 *   k6 run --env BASE_URL=https://bilete.online --env PROFILE=spike scenarios/05-search-stress.js
 */

import http from 'k6/http';
import { group, check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';
import { CONFIG, getProfile, proxyUrl } from '../config.js';
import { apiHeaders, checkApi, errorRate } from '../lib/helpers.js';

const profile = getProfile(__ENV.PROFILE);

const searchLatency = new Trend('search_latency_ms', true);
const autocompleteLatency = new Trend('autocomplete_latency_ms', true);
const searchErrorRate = new Rate('search_error_rate');

export const options = {
    ...profile,
    thresholds: {
        'search_latency_ms': ['p(95)<800', 'p(99)<2000'],
        'autocomplete_latency_ms': ['p(95)<300', 'p(99)<600'],
        'search_error_rate': ['rate<0.02'],
        'http_req_failed': ['rate<0.02'],
    },
    tags: { scenario: 'search-stress' },
};

// Realistic search queries of increasing length (simulating typing)
const SEARCH_SEQUENCES = [
    ['c', 'co', 'col', 'cold', 'coldp', 'coldplay'],
    ['u', 'un', 'unt', 'unto', 'untol', 'untold'],
    ['c', 'co', 'con', 'conc', 'conce', 'concert'],
    ['b', 'bu', 'buc', 'bucu', 'bucur', 'bucure', 'bucuresti'],
    ['f', 'fe', 'fes', 'fest', 'festi', 'festiv', 'festival'],
    ['t', 'te', 'tea', 'teat', 'teatr', 'teatru'],
    ['s', 'st', 'sta', 'stan', 'stand', 'stand-', 'stand-up'],
    ['a', 'ar', 'are', 'aren', 'arena'],
    ['m', 'mu', 'muz', 'muzi', 'muzic', 'muzica'],
    ['i', 'ia', 'ias', 'iasi'],
];

export default function () {
    // Pick a random search sequence
    const sequence = SEARCH_SEQUENCES[Math.floor(Math.random() * SEARCH_SEQUENCES.length)];

    // ── Simulate typing with autocomplete ────────────────────────────
    group('Search: Autocomplete Typing', () => {
        for (let i = 0; i < sequence.length; i++) {
            const query = sequence[i];

            // Skip queries < 2 chars (search requires min 2)
            if (query.length < 2) {
                sleep(0.1 + Math.random() * 0.15); // typing delay
                continue;
            }

            const res = http.get(
                `${CONFIG.BASE_URL}/api/search.php?q=${encodeURIComponent(query)}&limit=5`,
                apiHeaders()
            );

            autocompleteLatency.add(res.timings.duration);

            const ok = check(res, {
                [`Autocomplete "${query}" - status OK`]: (r) => r.status === 200 || r.status === 400,
                [`Autocomplete "${query}" - fast response`]: (r) => r.timings.duration < 600,
            });

            searchErrorRate.add(res.status >= 500);

            // Simulate typing speed: 100-250ms between keystrokes
            sleep(0.1 + Math.random() * 0.15);
        }
    });

    // ── Full search (user presses Enter) ─────────────────────────────
    group('Search: Full Query', () => {
        const fullQuery = sequence[sequence.length - 1];
        const res = http.get(
            proxyUrl('search', { q: fullQuery }),
            apiHeaders()
        );

        searchLatency.add(res.timings.duration);
        checkApi(res, `Full search: ${fullQuery}`);

        // Simulate reading results
        sleep(1 + Math.random() * 3);
    });

    // ── Concurrent search burst (multiple users searching at once) ───
    group('Search: Concurrent Burst', () => {
        const terms = CONFIG.SAMPLE_SEARCH_TERMS;
        const batchRequests = terms.map(term => [
            'GET',
            proxyUrl('search', { q: term }),
            null,
            apiHeaders(),
        ]);

        const responses = http.batch(batchRequests);
        responses.forEach((res, i) => {
            searchLatency.add(res.timings.duration);
            checkApi(res, `Burst search: ${terms[i]}`);
        });

        sleep(0.5);
    });
}
