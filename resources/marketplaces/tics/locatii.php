<?php
/**
 * TICS.ro – Cities listing page (/locatii)
 *
 * Shows all cities for the selected country, grouped by:
 *   1. Orașe populare (is_featured = true)
 *   2. Orașe cu evenimente (events_count > 0)
 *   3. Alte orașe
 *
 * Country is read from localStorage (tics_country) and can be switched
 * via the tab buttons. Cities are loaded via /api/cities.php proxy.
 */

require_once 'includes/config.php';

$pageTitle       = 'Locații – Orașe cu evenimente';
$pageDescription = 'Descoperă toate orașele cu evenimente: concerte, festivaluri, teatru și mai mult în România, Moldova, Ungaria și Bulgaria.';
$canonicalUrl    = SITE_URL . '/locatii';

require_once 'includes/head.php';
require_once 'includes/header.php';
?>

<main class="min-h-screen bg-gray-50">

    <!-- ── Hero ───────────────────────────────────────────────── -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 lg:px-8 py-10">

            <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-1">
                Orașe din <span id="heroCountryName" class="gradient-text">România</span>
            </h1>
            <p class="text-gray-500 mt-1">Selectează un oraș pentru a vedea evenimentele disponibile</p>

            <!-- Country tabs -->
            <div class="flex flex-wrap items-center gap-2 mt-6">
                <button type="button" onclick="locatiiSetCountry('RO')" data-code="RO"
                        class="locatii-tab flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all">
                    <span class="fi fi-ro" style="border-radius:2px;"></span> România
                </button>
                <button type="button" onclick="locatiiSetCountry('MD')" data-code="MD"
                        class="locatii-tab flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all">
                    <span class="fi fi-md" style="border-radius:2px;"></span> Moldova
                </button>
                <button type="button" onclick="locatiiSetCountry('HU')" data-code="HU"
                        class="locatii-tab flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all">
                    <span class="fi fi-hu" style="border-radius:2px;"></span> Ungaria
                </button>
                <button type="button" onclick="locatiiSetCountry('BG')" data-code="BG"
                        class="locatii-tab flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all">
                    <span class="fi fi-bg" style="border-radius:2px;"></span> Bulgaria
                </button>
            </div>

        </div>
    </section>

    <!-- ── Cities content ─────────────────────────────────────── -->
    <section class="max-w-6xl mx-auto px-4 lg:px-8 py-10">
        <div id="locatiiContent">
            <div class="flex items-center justify-center py-24">
                <div class="w-8 h-8 border-2 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>
            </div>
        </div>
    </section>

</main>

<?php require_once 'includes/footer.php'; ?>

<style>
/* Locatii tab active state */
.locatii-tab          { background: #fff;     color: #374151; border-color: #e5e7eb; }
.locatii-tab:hover    { border-color: #9ca3af; }
.locatii-tab.active   { background: #111827;  color: #fff;    border-color: #111827; }

/* City card – featured */
.city-card-featured {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 16px;
    transition: border-color .15s, background .15s;
    text-decoration: none;
}
.city-card-featured:hover { border-color: #c4b5fd; background: #faf5ff; }

/* City link – regular */
.city-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
    transition: border-color .15s, background .15s;
    text-decoration: none;
}
.city-link:hover { border-color: #c4b5fd; background: #faf5ff; }
</style>

<script>
(function () {
    'use strict';

    var COUNTRIES = {
        RO: { name: 'România' },
        MD: { name: 'Moldova' },
        HU: { name: 'Ungaria' },
        BG: { name: 'Bulgaria' },
    };

    var cache       = {};
    var currentCode = localStorage.getItem('tics_country') || 'RO';

    /* ---- Public (called from onclick) ---- */
    window.locatiiSetCountry = function (code) {
        if (!COUNTRIES[code] || code === currentCode) return;
        currentCode = code;
        localStorage.setItem('tics_country', code);

        // Sync the header country selector if present on the same page
        var headerBtn = document.querySelector('.country-option[data-code="' + code + '"]');
        if (headerBtn && typeof window.ticsSelectCountry === 'function') {
            window.ticsSelectCountry(headerBtn);
        }

        applyCountry(code);
    };

    /* ---- UI ---- */
    function applyCountry(code) {
        updateTabs(code);
        updateHero(code);
        loadCities(code);
    }

    function updateTabs(code) {
        document.querySelectorAll('.locatii-tab').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.code === code);
        });
    }

    function updateHero(code) {
        var el = document.getElementById('heroCountryName');
        if (el) el.textContent = (COUNTRIES[code] || COUNTRIES.RO).name;
    }

    /* ---- Cities API ---- */
    function loadCities(code) {
        if (cache[code]) { renderCities(code, cache[code]); return; }

        var content = document.getElementById('locatiiContent');
        if (content) {
            content.innerHTML =
                '<div class="flex items-center justify-center py-24">' +
                '<div class="w-8 h-8 border-2 border-purple-200 border-t-purple-600 rounded-full animate-spin"></div>' +
                '</div>';
        }

        fetch('/api/cities.php?country=' + encodeURIComponent(code) + '&per_page=200&sort=events', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success && Array.isArray(data.data)) {
                cache[code] = data.data;
                renderCities(code, data.data);
            } else {
                showError();
            }
        })
        .catch(showError);
    }

    /* Strip country prefix (ro-, md-, hu-, bg-) to build clean city event URL */
    function cityUrl(slug) {
        return '/evenimente-' + slug.replace(/^[a-z]{2}-/, '');
    }

    function renderCities(code, cities) {
        var content = document.getElementById('locatiiContent');
        if (!content) return;

        if (!cities.length) {
            content.innerHTML =
                '<div class="text-center py-24 text-gray-400 text-sm">Nu există orașe disponibile pentru această țară.</div>';
            return;
        }

        var featured   = cities.filter(function (c) { return c.is_featured; });
        var withEvents = cities.filter(function (c) { return !c.is_featured && c.events_count > 0; });
        var noEvents   = cities.filter(function (c) { return !c.is_featured && c.events_count === 0; });

        var html = '';

        /* ─── Featured cities ─── */
        if (featured.length) {
            html += '<div class="mb-10">';
            html += '<h2 class="text-base font-semibold text-gray-900 uppercase tracking-wide mb-4">Orașe populare</h2>';
            html += '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">';
            featured.forEach(function (city) {
                html += '<a href="' + cityUrl(city.slug) + '" class="city-card-featured">';
                html += '<span class="font-semibold text-gray-900">' + esc(city.name) + '</span>';
                if (city.events_count > 0) {
                    html += '<span class="flex-shrink-0 ml-2 px-2 py-0.5 rounded-full text-xs font-bold bg-purple-600 text-white">'
                          + city.events_count + '</span>';
                }
                html += '</a>';
            });
            html += '</div></div>';
        }

        /* ─── Cities with events ─── */
        if (withEvents.length) {
            html += '<div class="mb-10">';
            html += '<h2 class="text-base font-semibold text-gray-900 uppercase tracking-wide mb-4">Orașe cu evenimente</h2>';
            html += '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">';
            withEvents.forEach(function (city) {
                html += '<a href="' + cityUrl(city.slug) + '" class="city-link">';
                html += '<span class="text-gray-800 font-medium">' + esc(city.name) + '</span>';
                html += '<span class="flex-shrink-0 ml-1 text-xs font-semibold text-purple-600">'
                      + city.events_count + '</span>';
                html += '</a>';
            });
            html += '</div></div>';
        }

        /* ─── Other cities ─── */
        if (noEvents.length) {
            html += '<div>';
            html += '<h2 class="text-base font-semibold text-gray-400 uppercase tracking-wide mb-4">Alte orașe</h2>';
            html += '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2">';
            noEvents.forEach(function (city) {
                html += '<a href="' + cityUrl(city.slug) + '" class="py-1.5 px-2 text-sm text-gray-500 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition-colors">'
                      + esc(city.name) + '</a>';
            });
            html += '</div></div>';
        }

        content.innerHTML = html;
    }

    function showError() {
        var content = document.getElementById('locatiiContent');
        if (content) {
            content.innerHTML =
                '<div class="text-center py-24 text-sm text-red-400">Nu s-au putut încărca orașele. Încearcă din nou.</div>';
        }
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ---- Init ---- */
    function init() {
        updateTabs(currentCode);
        updateHero(currentCode);
        loadCities(currentCode);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
</script>
