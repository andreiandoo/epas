/**
 * Ambilet.ro - Theater Troupe Layout Overrides
 * Custom rendering for artists with type "Theater Troupe"
 *
 * This file overrides specific sections of ArtistPage when the artist
 * is a theater troupe. It replaces music-focused stats (Spotify, YouTube)
 * with theater-relevant metrics.
 *
 * Dependencies: ArtistPage (artist-single.js)
 */

const TheaterLayout = {

    /**
     * Check if an artist should use the theater layout
     * @param {Array} types - Array of type objects from API [{id, name, slug}]
     * @returns {boolean}
     */
    isTheater(types) {
        if (!types || !Array.isArray(types)) return false;
        var theaterSlugs = ['theater-troupe', 'theatre-troupe', 'trupa-de-teatru'];
        return types.some(function(t) {
            var slug = (t.slug || '').toLowerCase();
            var name = (t.name || '').toLowerCase();
            return theaterSlugs.includes(slug) ||
                   name.includes('theater') ||
                   name.includes('theatre') ||
                   name.includes('teatru');
        });
    },

    /**
     * Apply theater layout overrides after standard render
     * @param {Object} data - Transformed artist data
     * @param {Object} rawApi - Raw API response
     */
    apply(data, rawApi) {
        this.renderTheaterStats(data, rawApi);
        this.renderTheaterFacts(data, rawApi);
        this.hideSpotifySection();
        this.adjustStatsLabels();
    },

    /**
     * Render theater-specific stats (replaces music stats)
     */
    renderTheaterStats(data, rawApi) {
        var container = document.getElementById('statsContainer');
        if (!container) return;

        var upcomingEvents = rawApi.stats?.upcoming_events || 0;
        var pastEvents = rawApi.stats?.past_events || 0;
        var totalEvents = upcomingEvents + pastEvents;

        // Calculate total followers across social platforms
        var totalFollowers = (rawApi.stats?.instagram_followers || 0) +
                            (rawApi.stats?.facebook_followers || 0) +
                            (rawApi.stats?.youtube_subscribers || 0) +
                            (rawApi.stats?.tiktok_followers || 0);

        var divider = '<div class="hidden w-px h-12 bg-gray-200 lg:block"></div>';

        container.innerHTML =
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + totalEvents + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Spectacole</div>' +
            '</div>' + divider +
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + upcomingEvents + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Viitoare</div>' +
            '</div>' + divider +
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + pastEvents + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Anterioare</div>' +
            '</div>' + divider +
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + ArtistPage.formatNumber(totalFollowers) + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Followers</div>' +
            '</div>';
    },

    /**
     * Render theater-specific facts (replaces music genre with theater genre)
     */
    renderTheaterFacts(data, rawApi) {
        var factsCard = document.getElementById('factsCard');
        if (!factsCard) return;

        var origin = [rawApi.city, rawApi.country].filter(Boolean).join(', ') || '-';
        var genres = (rawApi.genres || []).map(function(g) { return g.name || g; }).join(', ') || '-';
        var types = (rawApi.types || []).map(function(t) { return t.name || t; }).join(', ') || '-';
        var upcomingEvents = rawApi.stats?.upcoming_events || 0;
        var pastEvents = rawApi.stats?.past_events || 0;

        var facts = [
            { label: 'Origine', value: origin },
            { label: 'Gen', value: genres },
            { label: 'Tip', value: types },
            { label: 'Spectacole viitoare', value: upcomingEvents.toString() },
            { label: 'Spectacole anterioare', value: pastEvents.toString() }
        ];

        var factsContent = factsCard.querySelector('.space-y-0, .divide-y') || factsCard;

        // Find or create the facts list container
        var listEl = factsCard.querySelector('[id="factsContent"], .divide-y');
        if (!listEl) {
            // Fallback: render via ArtistPage.renderFacts
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
     * Hide Spotify section for theater troupes (not relevant)
     */
    hideSpotifySection() {
        var spotifySection = document.getElementById('spotifySection');
        if (spotifySection) {
            spotifySection.style.display = 'none';
        }
    },

    /**
     * Adjust hero/section labels for theater context
     */
    adjustStatsLabels() {
        // Update "Concerte viitoare" heading to "Spectacole viitoare" if present
        var eventsHeading = document.querySelector('[data-section="events"] h2, .events-section h2');
        if (eventsHeading && eventsHeading.textContent.includes('Concert')) {
            eventsHeading.textContent = eventsHeading.textContent.replace('Concerte', 'Spectacole');
        }
    }
};

// Make available globally
window.TheaterLayout = TheaterLayout;
