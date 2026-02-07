#!/usr/bin/env node
/**
 * Load Test Results Analyzer for bilete.online
 *
 * Reads k6 JSON output and produces a structured performance report.
 *
 * Usage:
 *   node analyze.js reports/pages_normal_20260207_120000.json
 *   node analyze.js reports/  (analyzes all JSON files in directory)
 */

const fs = require('fs');
const path = require('path');

// ── SLO Thresholds ───────────────────────────────────────────────────
const SLO = {
    PAGE_P95: 2000,
    PAGE_P99: 4000,
    API_P95: 500,
    API_P99: 1500,
    SEATING_P95: 300,
    SEATING_P99: 800,
    TTFB_P95: 1500,
    ERROR_RATE: 0.01,
    SUCCESS_RATE: 0.99,
};

// ── Main ─────────────────────────────────────────────────────────────
function main() {
    const input = process.argv[2];
    if (!input) {
        console.error('Usage: node analyze.js <json-file-or-directory>');
        process.exit(1);
    }

    const stat = fs.statSync(input);
    const files = stat.isDirectory()
        ? fs.readdirSync(input)
            .filter(f => f.endsWith('.json'))
            .map(f => path.join(input, f))
        : [input];

    if (files.length === 0) {
        console.error('No JSON files found.');
        process.exit(1);
    }

    for (const file of files) {
        console.log(`\n${'═'.repeat(70)}`);
        console.log(`  ANALYSIS: ${path.basename(file)}`);
        console.log(`${'═'.repeat(70)}\n`);
        analyzeFile(file);
    }
}

// ── Analyze a single k6 JSON output file ─────────────────────────────
function analyzeFile(filePath) {
    const raw = fs.readFileSync(filePath, 'utf-8');
    const lines = raw.trim().split('\n');

    const metrics = {};
    const points = {};
    const checks = { passes: 0, fails: 0 };
    let httpReqs = 0;
    let httpFails = 0;
    let maxVUs = 0;

    for (const line of lines) {
        let obj;
        try {
            obj = JSON.parse(line);
        } catch {
            continue;
        }

        if (obj.type === 'Point' && obj.metric && obj.data) {
            const name = obj.metric;
            const value = obj.data.value;

            if (!points[name]) points[name] = [];
            points[name].push(value);

            // Track specific counters
            if (name === 'http_reqs') httpReqs++;
            if (name === 'http_req_failed' && value === 1) httpFails++;
            if (name === 'vus' && value > maxVUs) maxVUs = value;
        }

        if (obj.type === 'Metric') {
            metrics[obj.metric] = obj.data;
        }
    }

    // ── Compute percentiles from raw data points ─────────────────────
    const computed = {};
    for (const [name, values] of Object.entries(points)) {
        if (values.length === 0) continue;
        const sorted = [...values].sort((a, b) => a - b);
        computed[name] = {
            count: sorted.length,
            min: sorted[0],
            max: sorted[sorted.length - 1],
            avg: sorted.reduce((a, b) => a + b, 0) / sorted.length,
            med: percentile(sorted, 50),
            p90: percentile(sorted, 90),
            p95: percentile(sorted, 95),
            p99: percentile(sorted, 99),
        };
    }

    // ── Print report ─────────────────────────────────────────────────

    console.log('── TRAFFIC SUMMARY ──────────────────────────────────────');
    console.log(`  Total HTTP requests:  ${httpReqs}`);
    console.log(`  Failed requests:      ${httpFails}`);
    console.log(`  Error rate:           ${httpReqs > 0 ? ((httpFails / httpReqs) * 100).toFixed(2) : 0}%`);
    console.log(`  Peak VUs:             ${maxVUs}`);
    console.log('');

    // Page load
    printMetricSection('── PAGE LOAD TIMES ─────────────────────────────────────', computed, [
        { key: 'custom_page_load_ms', label: 'Page load', slo95: SLO.PAGE_P95, slo99: SLO.PAGE_P99 },
        { key: 'custom_ttfb_ms', label: 'TTFB', slo95: SLO.TTFB_P95 },
    ]);

    // API
    printMetricSection('── API RESPONSE TIMES ──────────────────────────────────', computed, [
        { key: 'custom_api_response_ms', label: 'API proxy', slo95: SLO.API_P95, slo99: SLO.API_P99 },
        { key: 'search_latency_ms', label: 'Search' },
        { key: 'autocomplete_latency_ms', label: 'Autocomplete' },
    ]);

    // Seating
    printMetricSection('── SEATING SYSTEM ──────────────────────────────────────', computed, [
        { key: 'custom_seating_response_ms', label: 'Seating API', slo95: SLO.SEATING_P95, slo99: SLO.SEATING_P99 },
        { key: 'seat_hold_latency_ms', label: 'Seat hold' },
        { key: 'seat_release_latency_ms', label: 'Seat release' },
    ]);

    // On-sale specific
    printMetricSection('── ON-SALE METRICS ─────────────────────────────────────', computed, [
        { key: 'onsale_page_latency_ms', label: 'On-sale page' },
        { key: 'onsale_api_latency_ms', label: 'On-sale API' },
    ]);

    // Journey
    if (computed['journey_total_ms']) {
        printMetricSection('── USER JOURNEY ────────────────────────────────────────', computed, [
            { key: 'journey_total_ms', label: 'Full journey' },
        ]);
    }

    // Counters
    const counterKeys = [
        'seat_hold_success', 'seat_hold_conflict',
        'onsale_page_loads', 'onsale_seat_attempts', 'onsale_seat_success',
        'onsale_seat_conflict', 'onsale_f5_refreshes',
    ];
    const hasCounters = counterKeys.some(k => computed[k]);
    if (hasCounters) {
        console.log('── COUNTERS ────────────────────────────────────────────');
        for (const k of counterKeys) {
            if (computed[k]) {
                console.log(`  ${k.padEnd(30)} ${computed[k].count}`);
            }
        }
        console.log('');
    }

    // ── SLO Check ────────────────────────────────────────────────────
    console.log('── SLO COMPLIANCE ──────────────────────────────────────');
    const sloResults = [];

    checkSLO(sloResults, computed, 'custom_page_load_ms', 'p95', SLO.PAGE_P95, 'Page p95 < 2s');
    checkSLO(sloResults, computed, 'custom_page_load_ms', 'p99', SLO.PAGE_P99, 'Page p99 < 4s');
    checkSLO(sloResults, computed, 'custom_api_response_ms', 'p95', SLO.API_P95, 'API p95 < 500ms');
    checkSLO(sloResults, computed, 'custom_api_response_ms', 'p99', SLO.API_P99, 'API p99 < 1.5s');
    checkSLO(sloResults, computed, 'custom_seating_response_ms', 'p95', SLO.SEATING_P95, 'Seating p95 < 300ms');
    checkSLO(sloResults, computed, 'custom_ttfb_ms', 'p95', SLO.TTFB_P95, 'TTFB p95 < 1.5s');

    const errorRate = httpReqs > 0 ? httpFails / httpReqs : 0;
    const errorSLO = errorRate <= SLO.ERROR_RATE;
    sloResults.push({ name: `Error rate < ${SLO.ERROR_RATE * 100}%`, pass: errorSLO, value: `${(errorRate * 100).toFixed(2)}%` });
    console.log(`  ${errorSLO ? '✅' : '❌'} Error rate < ${SLO.ERROR_RATE * 100}%: ${(errorRate * 100).toFixed(2)}%`);

    console.log('');

    const passed = sloResults.filter(r => r.pass).length;
    const total = sloResults.length;
    const allPassed = passed === total;

    console.log(`  Result: ${passed}/${total} SLOs passed`);
    console.log(`  Status: ${allPassed ? '✅ ALL SLOs MET' : '⚠️  SLO VIOLATIONS DETECTED'}`);
    console.log('');

    // ── Recommendations ──────────────────────────────────────────────
    console.log('── RECOMMENDATIONS ─────────────────────────────────────');
    generateRecommendations(computed, errorRate);
    console.log('');
}

// ── Helpers ──────────────────────────────────────────────────────────

function percentile(sorted, pct) {
    if (sorted.length === 0) return 0;
    const idx = Math.ceil((pct / 100) * sorted.length) - 1;
    return sorted[Math.max(0, idx)];
}

function printMetricSection(header, computed, metrics) {
    const hasAny = metrics.some(m => computed[m.key]);
    if (!hasAny) return;

    console.log(header);
    for (const m of metrics) {
        const c = computed[m.key];
        if (!c) continue;

        const slo95str = m.slo95 ? ` (SLO: ${m.slo95}ms)` : '';
        console.log(`  ${m.label}:`);
        console.log(`    min: ${c.min.toFixed(0)}ms  avg: ${c.avg.toFixed(0)}ms  med: ${c.med.toFixed(0)}ms`);
        console.log(`    p90: ${c.p90.toFixed(0)}ms  p95: ${c.p95.toFixed(0)}ms${slo95str}  p99: ${c.p99.toFixed(0)}ms`);
        console.log(`    max: ${c.max.toFixed(0)}ms  samples: ${c.count}`);

        if (m.slo95 && c.p95 > m.slo95) {
            console.log(`    ⚠️  p95 exceeds SLO by ${(c.p95 - m.slo95).toFixed(0)}ms`);
        }
        if (m.slo99 && c.p99 > m.slo99) {
            console.log(`    ⚠️  p99 exceeds SLO by ${(c.p99 - m.slo99).toFixed(0)}ms`);
        }
    }
    console.log('');
}

function checkSLO(results, computed, metric, pctKey, threshold, name) {
    const c = computed[metric];
    if (!c) return;

    const value = c[pctKey];
    const pass = value <= threshold;
    results.push({ name, pass, value: `${value.toFixed(0)}ms` });
    console.log(`  ${pass ? '✅' : '❌'} ${name}: ${value.toFixed(0)}ms`);
}

function generateRecommendations(computed, errorRate) {
    const recs = [];

    // Page load
    const page = computed['custom_page_load_ms'];
    if (page) {
        if (page.p95 > 3000) {
            recs.push('🔴 Page load p95 > 3s: Consider CDN, page caching, or server-side rendering optimization.');
        } else if (page.p95 > 2000) {
            recs.push('🟡 Page load p95 > 2s: Consider enabling OPcache, Redis page cache, or Varnish.');
        }
        if (page.max > 10000) {
            recs.push('🔴 Page load max > 10s: Investigate slow outliers – possible DB query bottleneck.');
        }
    }

    // TTFB
    const ttfb = computed['custom_ttfb_ms'];
    if (ttfb && ttfb.p95 > 1500) {
        recs.push('🔴 TTFB p95 > 1.5s: Server processing too slow. Check DB queries, PHP-FPM pool size, and Redis connection.');
    }

    // API
    const api = computed['custom_api_response_ms'];
    if (api) {
        if (api.p95 > 1000) {
            recs.push('🔴 API p95 > 1s: Proxy is too slow. Optimize core API queries, add Redis caching in proxy.php.');
        } else if (api.p95 > 500) {
            recs.push('🟡 API p95 > 500ms: Consider extending ApiCache TTL or adding Cloudflare caching rules.');
        }
    }

    // Seating
    const seating = computed['custom_seating_response_ms'];
    if (seating) {
        if (seating.p95 > 500) {
            recs.push('🔴 Seating p95 > 500ms: Redis may be overloaded. Consider Redis Cluster or dedicated seating Redis instance.');
        } else if (seating.p95 > 300) {
            recs.push('🟡 Seating p95 > 300ms: Acceptable but tight. Pre-warm Redis keys before on-sale events.');
        }
    }

    // Error rate
    if (errorRate > 0.05) {
        recs.push('🔴 Error rate > 5%: Critical – investigate 5xx errors. Check PHP error logs and OOM kills.');
    } else if (errorRate > 0.01) {
        recs.push('🟡 Error rate > 1%: Some requests failing. Check rate limiting config and PHP-FPM worker count.');
    }

    // Search
    const search = computed['search_latency_ms'];
    if (search && search.p95 > 800) {
        recs.push('🟡 Search p95 > 800ms: Consider Elasticsearch/Meilisearch for instant search instead of DB queries.');
    }

    // Seat conflicts
    const conflicts = computed['seat_hold_conflict'];
    if (conflicts && conflicts.count > 100) {
        recs.push('🟡 High seat conflict count: Consider implementing a virtual queue for high-demand events.');
    }

    // On-sale
    const onsalePage = computed['onsale_page_latency_ms'];
    if (onsalePage && onsalePage.p95 > 5000) {
        recs.push('🔴 On-sale page p95 > 5s: Add a waiting room / queue system for high-demand events.');
    }

    if (recs.length === 0) {
        recs.push('✅ All metrics within acceptable ranges. System looks healthy.');
    }

    recs.forEach(r => console.log(`  ${r}`));
}

// ── Infrastructure recommendations (always printed) ──────────────────
function printInfraRecommendations() {
    console.log('── INFRASTRUCTURE CHECKLIST ─────────────────────────────');
    console.log('  [ ] PHP OPcache enabled with JIT');
    console.log('  [ ] Redis used for session, cache, and seating');
    console.log('  [ ] PHP-FPM pool: min 50 workers for peak traffic');
    console.log('  [ ] Cloudflare CDN with page rules for static assets');
    console.log('  [ ] MySQL query cache / slow query log enabled');
    console.log('  [ ] Gzip/Brotli compression on Nginx');
    console.log('  [ ] HTTP/2 or HTTP/3 enabled');
    console.log('  [ ] Connection pooling for DB (PgBouncer or ProxySQL)');
    console.log('  [ ] Auto-scaling rules for VPS / container orchestration');
    console.log('  [ ] Rate limiting at Nginx level (not just Laravel)');
    console.log('');
}

main();
