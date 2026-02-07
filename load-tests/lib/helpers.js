/**
 * Shared helpers for k6 load test scenarios
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ── Custom metrics ───────────────────────────────────────────────────
export const errorRate = new Rate('custom_error_rate');
export const pageLoadTime = new Trend('custom_page_load_ms', true);
export const apiResponseTime = new Trend('custom_api_response_ms', true);
export const seatingResponseTime = new Trend('custom_seating_response_ms', true);
export const ttfb = new Trend('custom_ttfb_ms', true);
export const requestCount = new Counter('custom_request_count');

// ── HTTP helpers ─────────────────────────────────────────────────────

/**
 * Standard headers for HTML page requests
 */
export function htmlHeaders() {
    return {
        headers: {
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language': 'ro-RO,ro;q=0.9,en;q=0.8',
            'Accept-Encoding': 'gzip, deflate, br',
            'User-Agent': 'k6-load-test/1.0 (Tixello Performance Audit)',
            'Cache-Control': 'no-cache',
        },
        tags: { type: 'page' },
    };
}

/**
 * Standard headers for API (JSON) requests
 */
export function apiHeaders(authToken) {
    const h = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'User-Agent': 'k6-load-test/1.0 (Tixello Performance Audit)',
    };
    if (authToken) {
        h['Authorization'] = `Bearer ${authToken}`;
    }
    return { headers: h, tags: { type: 'api' } };
}

/**
 * Standard headers for seating API
 */
export function seatingHeaders(sessionId) {
    return {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Session-ID': sessionId || `k6-session-${__VU}-${Date.now()}`,
            'User-Agent': 'k6-load-test/1.0 (Tixello Performance Audit)',
        },
        tags: { type: 'seating' },
    };
}

// ── Check helpers ────────────────────────────────────────────────────

/**
 * Check page response – status 200, non-empty body
 */
export function checkPage(res, name) {
    const passed = check(res, {
        [`${name} - status 200`]: (r) => r.status === 200,
        [`${name} - body not empty`]: (r) => r.body && r.body.length > 0,
        [`${name} - no server error`]: (r) => r.status < 500,
    });

    errorRate.add(!passed);
    pageLoadTime.add(res.timings.duration);
    ttfb.add(res.timings.waiting);
    requestCount.add(1);

    return passed;
}

/**
 * Check API response – status 200, valid JSON
 */
export function checkApi(res, name) {
    const passed = check(res, {
        [`${name} - status 200`]: (r) => r.status === 200,
        [`${name} - valid JSON`]: (r) => {
            try { JSON.parse(r.body); return true; } catch (e) { return false; }
        },
        [`${name} - no server error`]: (r) => r.status < 500,
    });

    errorRate.add(!passed);
    apiResponseTime.add(res.timings.duration);
    requestCount.add(1);

    return passed;
}

/**
 * Check seating API response
 */
export function checkSeating(res, name) {
    const passed = check(res, {
        [`${name} - status OK`]: (r) => r.status >= 200 && r.status < 400,
        [`${name} - valid JSON`]: (r) => {
            try { JSON.parse(r.body); return true; } catch (e) { return false; }
        },
        [`${name} - no server error`]: (r) => r.status < 500,
    });

    errorRate.add(!passed);
    seatingResponseTime.add(res.timings.duration);
    requestCount.add(1);

    return passed;
}

// ── User behaviour simulation ────────────────────────────────────────

/**
 * Simulate think time (user reading the page)
 */
export function thinkTime(minSeconds, maxSeconds) {
    const min = minSeconds || 1;
    const max = maxSeconds || 3;
    sleep(min + Math.random() * (max - min));
}

/**
 * Simulate fast interaction (clicking next immediately)
 */
export function quickPause() {
    sleep(0.3 + Math.random() * 0.7);
}

/**
 * Generate a unique session ID for seating tests
 */
export function generateSessionId() {
    return `k6-vu${__VU}-${Date.now()}-${Math.random().toString(36).substring(2, 8)}`;
}
