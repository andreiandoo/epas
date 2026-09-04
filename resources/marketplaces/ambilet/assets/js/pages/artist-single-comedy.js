/**
 * Ambilet.ro - Stand-up / Comedy Layout Overrides
 * Custom rendering for artists with type "Comedian".
 *
 * Comedians rarely have Spotify (no monthly listeners / popularity), so the
 * music-centric stats read as a demoralizing "0". This override foregrounds
 * what actually matters for a stand-up act: number of shows and social reach
 * (Instagram / Facebook / TikTok / YouTube). Zero-valued metrics are dropped
 * so the card always reads positively.
 *
 * Applied as a post-render DOM decorator, same pattern as TheaterLayout.
 * Dependencies: ArtistPage (artist-single.js)
 */

const ComedyLayout = {

    /**
     * Check if an artist should use the comedy layout.
     * @param {Array} types - Array of type objects from API [{id, name, slug}]
     * @returns {boolean}
     */
    isComedy(types) {
        if (!types || !Array.isArray(types)) return false;
        var comedySlugs = ['comedian', 'stand-up', 'standup', 'stand-up-comedy', 'comedie'];
        return types.some(function(t) {
            var slug = (t.slug || '').toLowerCase();
            var name = (t.name || '').toLowerCase();
            return comedySlugs.includes(slug) ||
                   name.includes('comedian') ||
                   name.includes('comedy') ||
                   name.includes('stand-up') ||
                   name.includes('standup') ||
                   name.includes('comedie') ||
                   name.includes('umor');
        });
    },

    /**
     * Apply comedy layout overrides after the standard render.
     * @param {Object} data - Transformed artist data
     * @param {Object} rawApi - Raw API response
     */
    apply(data, rawApi) {
        this.renderComedyStats(data, rawApi);
        this.renderComedyFacts(data, rawApi);
        this.adjustStatsLabels();
        // Spotify section: not force-hidden — a comedian with a real comedy
        // album/special keeps it; the default logic already hides it when there
        // is no Spotify data at all (the common stand-up case).
    },

    /**
     * Render comedy-specific stats (shows + social reach), dropping zeros.
     */
    renderComedyStats(data, rawApi) {
        var container = document.getElementById('statsContainer');
        if (!container) return;

        var s = rawApi.stats || {};
        var upcoming = s.upcoming_events || 0;
        var past = s.past_events || 0;
        var totalShows = upcoming + past;
        var followers = (s.instagram_followers || 0) +
                        (s.facebook_followers || 0) +
                        (s.tiktok_followers || 0) +
                        (s.youtube_subscribers || 0);
        var ytViews = s.youtube_total_views || 0;

        var candidates = [
            { label: 'Spectacole', value: totalShows, raw: true },
            { label: 'Spectacole viitoare', value: upcoming, raw: true },
            { label: 'Followers', value: followers },
            { label: 'Vizualizări YouTube', value: ytViews },
            { label: 'Followers Instagram', value: s.instagram_followers || 0 },
            { label: 'Followers TikTok', value: s.tiktok_followers || 0 },
            { label: 'Followers Facebook', value: s.facebook_followers || 0 },
        ];

        var items = candidates
            .filter(function(c) { return c.value > 0; })
            .slice(0, 4)
            .map(function(c) {
                return {
                    label: c.label,
                    value: c.raw ? String(c.value) : ArtistPage.formatNumber(c.value),
                };
            });

        if (items.length === 0) {
            items.push({ label: 'Pe AmBilet', value: 'Nou' });
        }

        var divider = '<div class="hidden w-px h-12 bg-gray-200 lg:block"></div>';
        container.innerHTML = items.map(function(item, i) {
            return (i > 0 ? divider : '') +
                '<div class="text-center flex-1 min-w-[100px]">' +
                    '<div class="text-[28px] font-extrabold text-gray-900">' + item.value + '</div>' +
                    '<div class="text-[13px] text-gray-500 mt-1">' + ArtistPage.escapeHtml(item.label) + '</div>' +
                '</div>';
        }).join('');
    },

    /**
     * Render comedy-specific quick facts (shows instead of music genre focus).
     */
    renderComedyFacts(data, rawApi) {
        var factsCard = document.getElementById('factsCard');
        if (!factsCard) return;

        var origin = [rawApi.city, rawApi.country].filter(Boolean).join(', ') || '-';
        var types = (rawApi.types || []).map(function(t) { return t.name || t; }).join(', ') || '-';
        var upcoming = rawApi.stats?.upcoming_events || 0;
        var past = rawApi.stats?.past_events || 0;

        var facts = [
            { label: 'Origine', value: origin },
            { label: 'Tip', value: types },
            { label: 'Spectacole viitoare', value: upcoming.toString() },
            { label: 'Spectacole anterioare', value: past.toString() },
        ];

        var listEl = factsCard.querySelector('[id="factsContent"], .divide-y');
        if (!listEl) {
            ArtistPage.renderFacts(facts);
            return;
        }

        listEl.innerHTML = facts.map(function(f) {
            return '<div class="flex items-center justify-between py-3">' +
                '<span class="text-[13px] text-gray-500">' + ArtistPage.escapeHtml(f.label) + '</span>' +
                '<span class="text-[13px] font-semibold text-gray-900">' + ArtistPage.escapeHtml(f.value) + '</span>' +
            '</div>';
        }).join('');
    },

    /**
     * Rename "Concerte" → "Spectacole" in the events heading for comedy context.
     */
    adjustStatsLabels() {
        var eventsHeading = document.querySelector('[data-section="events"] h2, .events-section h2');
        if (eventsHeading && eventsHeading.textContent.includes('Concert')) {
            eventsHeading.textContent = eventsHeading.textContent.replace('Concerte', 'Spectacole');
        }
    }
};

// Make available globally
window.ComedyLayout = ComedyLayout;
