/**
 * Scenario 3: Seating System Stress Test
 *
 * Tests the Redis-backed seating system under high concurrency.
 * This is the most latency-critical flow: seat hold → confirm/release.
 *
 * Simulates the "on-sale" moment when hundreds of users try to
 * grab seats simultaneously for a popular event.
 *
 * Usage:
 *   k6 run --env BASE_URL=https://bilete.online \
 *           --env CORE_API=https://core.tixello.com \
 *           --env SEATING_EVENT_ID=42 \
 *           --env PROFILE=peak \
 *           scenarios/03-seating.js
 */

import http from 'k6/http';
import { group, check } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { CONFIG, getProfile } from '../config.js';
import {
    seatingHeaders,
    checkSeating,
    thinkTime,
    quickPause,
    generateSessionId,
    errorRate,
} from '../lib/helpers.js';

const profile = getProfile(__ENV.PROFILE);

// Extra metrics specific to seating
const seatHoldSuccess = new Counter('seat_hold_success');
const seatHoldConflict = new Counter('seat_hold_conflict');
const seatHoldLatency = new Trend('seat_hold_latency_ms', true);
const seatReleaseLatency = new Trend('seat_release_latency_ms', true);

const EVENT_ID = CONFIG.SAMPLE_EVENT_ID_WITH_SEATING;
const CORE = CONFIG.CORE_API;

export const options = {
    ...profile,
    thresholds: {
        'custom_seating_response_ms': [
            `p(95)<${CONFIG.THRESHOLDS.SEATING_P95}`,
            `p(99)<${CONFIG.THRESHOLDS.SEATING_P99}`,
        ],
        'seat_hold_latency_ms': ['p(95)<500', 'p(99)<1000'],
        'custom_error_rate': [`rate<0.05`], // Allow up to 5% for seat conflicts
        'http_req_failed': ['rate<0.05'],
    },
    tags: { scenario: 'seating' },
};

export default function () {
    const sessionId = generateSessionId();
    const headers = seatingHeaders(sessionId);

    // ── 1. Load seating layout ───────────────────────────────────────
    let layout = null;
    group('Seating: Get Layout', () => {
        const res = http.get(
            `${CORE}/api/public/events/${EVENT_ID}/seating`,
            headers
        );
        checkSeating(res, 'Get seating layout');

        if (res.status === 200) {
            try {
                layout = JSON.parse(res.body);
            } catch (e) { /* ignore parse errors in load test */ }
        }
        quickPause();
    });

    // ── 2. Load seat availability ────────────────────────────────────
    let availableSeats = [];
    group('Seating: Get Available Seats', () => {
        const res = http.get(
            `${CORE}/api/public/events/${EVENT_ID}/seats`,
            headers
        );
        checkSeating(res, 'Get seat availability');

        if (res.status === 200) {
            try {
                const data = JSON.parse(res.body);
                // Collect available seat IDs
                if (data.seats) {
                    availableSeats = data.seats
                        .filter(s => s.status === 'available')
                        .map(s => s.id);
                } else if (Array.isArray(data)) {
                    availableSeats = data
                        .filter(s => s.status === 'available')
                        .map(s => s.id);
                }
            } catch (e) { /* ignore */ }
        }
        thinkTime(1, 3); // User is looking at the seating map
    });

    // ── 3. Hold seats (1-4 random seats) ─────────────────────────────
    let heldSeats = [];
    group('Seating: Hold Seats', () => {
        // Pick 1-4 random available seats
        const numSeats = Math.min(
            1 + Math.floor(Math.random() * 4),
            availableSeats.length || CONFIG.SAMPLE_SEAT_IDS.length
        );

        const seatsToHold = availableSeats.length > 0
            ? shuffle(availableSeats).slice(0, numSeats)
            : CONFIG.SAMPLE_SEAT_IDS.slice(0, numSeats);

        const payload = JSON.stringify({
            event_id: EVENT_ID,
            seat_ids: seatsToHold,
        });

        const res = http.post(
            `${CORE}/api/public/seats/hold`,
            payload,
            headers
        );

        seatHoldLatency.add(res.timings.duration);

        const ok = check(res, {
            'Hold - status 200 or 409': (r) => r.status === 200 || r.status === 409,
            'Hold - valid JSON': (r) => {
                try { JSON.parse(r.body); return true; } catch (e) { return false; }
            },
        });

        if (res.status === 200) {
            seatHoldSuccess.add(1);
            heldSeats = seatsToHold;
        } else if (res.status === 409) {
            seatHoldConflict.add(1);
        }

        errorRate.add(res.status >= 500);
        thinkTime(2, 6); // User reviews their seat selection
    });

    // ── 4. Check session holds ───────────────────────────────────────
    group('Seating: Check Holds', () => {
        const res = http.get(
            `${CORE}/api/public/seats/holds`,
            headers
        );
        checkSeating(res, 'Get session holds');
        quickPause();
    });

    // ── 5. Release seats (simulate ~30% of users abandoning) ─────────
    if (heldSeats.length > 0 && Math.random() < 0.3) {
        group('Seating: Release Seats', () => {
            const payload = JSON.stringify({
                event_id: EVENT_ID,
                seat_ids: heldSeats,
            });

            const res = http.del(
                `${CORE}/api/public/seats/hold`,
                payload,
                headers
            );

            seatReleaseLatency.add(res.timings.duration);
            checkSeating(res, 'Release seats');
        });
    }
}

// ── Utility ──────────────────────────────────────────────────────────
function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}
