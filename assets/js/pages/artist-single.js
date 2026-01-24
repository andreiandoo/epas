/**
 * Ambilet.ro - Artist Single Page Controller
 * Handles artist detail page with events, stats, social links, videos, and similar artists
 *
 * Dependencies: AmbiletAPI
 */

const ArtistPage = {
    // Configuration
    artistSlug: '',
    artistData: null,
    isFollowing: false,

    // Month names for date formatting
    monthNames: ['IAN', 'FEB', 'MAR', 'APR', 'MAI', 'IUN', 'IUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],

    // DOM element IDs
    elements: {
        artistHero: 'artistHero',
        heroImage: 'heroImage',
        artistName: 'artistName',
        genreTags: 'genreTags',
        statsContainer: 'statsContainer',
        spotifyListenersCountDiv: 'spotifyListenersCount',
        eventsList: 'eventsList',
        viewAllEventsLink: 'viewAllEventsLink',
        aboutCard: 'aboutCard',
        factsCard: 'factsCard',
        bookingAgencyCard: 'bookingAgencyCard',
        bookingAgencyContent: 'bookingAgencyContent',
        youtubeVideosSection: 'youtubeVideosSection',
        youtubeVideosGrid: 'youtubeVideosGrid',
        galleryGrid: 'galleryGrid',
        spotifySection: 'spotifySection',
        similarArtists: 'similarArtists',
        socialFacebook: 'socialFacebook',
        socialInstagram: 'socialInstagram',
        socialYoutube: 'socialYoutube',
        socialTiktok: 'socialTiktok',
        socialSpotify: 'socialSpotify',
        followBtn: 'follow-btn',
        followIcon: 'follow-icon',
        followText: 'follow-text'
    },

    /**
     * Initialize the page
     */
    init() {
        // Get slug from window variable (set by PHP)
        this.artistSlug = window.ARTIST_SLUG || '';
        this.loadArtistData();
        this.loadFollowStatus();
    },

    /**
     * Load artist data from API
     */
    async loadArtistData() {
        if (!this.artistSlug) {
            console.error('No artist slug provided');
            this.showNotFound();
            return;
        }

        try {
            var response = await AmbiletAPI.get('/artists/' + this.artistSlug);
            if (response.success && response.data) {
                this.artistData = response.data;
                this.render(this.transformApiData(response.data));
            } else {
                console.error('Artist not found');
                this.showNotFound();
            }
        } catch (e) {
            console.error('Failed to load artist:', e);
            this.showNotFound();
        }
    },

    /**
     * Show not found error
     */
    showNotFound() {
        var heroSection = document.getElementById(this.elements.artistHero);
        if (heroSection) {
            heroSection.innerHTML =
                '<div class="flex flex-col items-center justify-center h-full bg-gradient-to-br from-gray-100 to-gray-200">' +
                    '<svg class="w-24 h-24 mb-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>' +
                    '</svg>' +
                    '<h1 class="mb-3 text-3xl font-bold text-gray-700">Artist negasit</h1>' +
                    '<p class="mb-8 text-gray-500">Ne pare rau, nu am putut gasi artistul cautat.</p>' +
                    '<a href="/artisti" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-all rounded-xl bg-primary hover:bg-primary-dark hover:shadow-lg">' +
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>' +
                        '</svg>' +
                        'Inapoi la artisti' +
                    '</a>' +
                '</div>';
        }

        // Hide other sections
        var profileSection = document.querySelector('.max-w-7xl.px-6.-mt-20');
        if (profileSection) {
            profileSection.style.display = 'none';
        }

        // Update page title
        document.title = 'Artist negasit - ' + (window.AMBILET_CONFIG?.SITE_NAME || 'Ambilet');
    },

    /**
     * Transform API response to page format
     */
    transformApiData(api) {
        var self = this;

        // Calculate total followers (sum of all social platforms)
        var totalFollowers = (api.stats?.spotify_listeners || 0) +
                            (api.stats?.instagram_followers || 0) +
                            (api.stats?.facebook_followers || 0) +
                            (api.stats?.youtube_subscribers || 0) +
                            (api.stats?.tiktok_followers || 0);

        // Transform events
        var events = (api.upcoming_events || []).map(function(event) {
            var date = new Date(event.event_date || event.starts_at);
            return {
                slug: event.slug,
                day: date.getDate().toString().padStart(2, '0'),
                month: self.monthNames[date.getMonth()],
                title: event.title || event.name,
                venue: event.venue ? event.venue.name + ', ' + event.venue.city : '-',
                time: event.start_time ? event.start_time.substring(0, 5) : '-',
                price: event.min_price || event.price_from,
                currency: event.currency || 'RON',
                soldOut: event.is_sold_out || false,
                image: event.image || event.image_url
            };
        });

        return {
            name: api.name,
            slug: api.slug,
            image: api.image || api.main_image_url || api.portrait_url || 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1920&h=800&fit=crop',
            verified: api.is_verified,
            genres: (api.genres || []).map(function(g) { return g.name || g; }),
            stats: {
                spotifyListeners: this.formatNumber(api.stats?.spotify_listeners || 0),
                totalFollowers: this.formatNumber(totalFollowers),
                spotifyPopularity: api.stats?.spotify_popularity || 0,
                youtubeViews: this.formatNumber(api.stats?.youtube_total_views || 0),
                upcomingEvents: api.stats?.upcoming_events || events.length
            },
            about: api.biography ? [api.biography] : ['Informatii despre acest artist vor fi adaugate in curand.'],
            facts: [
                { label: 'Origine', value: [api.city, api.country].filter(Boolean).join(', ') || '-' },
                { label: 'Gen muzical', value: (api.genres || []).map(function(g) { return g.name || g; }).join(', ') || '-' },
                { label: 'Tip', value: (api.types || []).map(function(t) { return t.name || t; }).join(', ') || '-' },
                { label: 'Concerte viitoare', value: (api.stats?.upcoming_events || events.length).toString() },
                //{ label: 'Concerte anterioare', value: (api.stats?.past_events || 0).toString() }
            ],
            spotifyId: api.external_ids?.spotify_id || api.spotify_id || null,
            events: events,
            // Store raw events for AmbiletEventCard which handles commission
            rawEvents: api.upcoming_events || [],
            gallery: api.youtube_videos?.length > 0 ?
                api.youtube_videos.map(function(v) { return { url: v.thumbnail, isVideo: true }; }) :
                [],
            similarArtists: (api.similar_artists || []).map(function(a) {
                return {
                    slug: a.slug,
                    name: a.name,
                    genre: a.genres?.map(function(g) { return g.name || g; }).join(', ') || 'Artist',
                    image: a.image || a.portrait_url || 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=300&h=300&fit=crop'
                };
            }),
            social: api.social || {},
            youtubeVideos: api.youtube_videos || [],
            bookingAgency: api.booking_agency || null
        };
    },

    /**
     * Format number with K/M suffix
     */
    formatNumber(num) {
        if (!num || num === 0) return '0';
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        }
        return num.toString();
    },

    /**
     * Extract YouTube video ID from URL
     */
    extractYoutubeId(url) {
        if (!url) return null;
        var patterns = [
            /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\s?]+)/,
            /youtube\.com\/watch\?.*v=([^&\s]+)/
        ];
        for (var i = 0; i < patterns.length; i++) {
            var match = url.match(patterns[i]);
            if (match && match[1]) {
                return match[1];
            }
        }
        return null;
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Render all artist content
     */
    render(data) {
        // Update page title
        document.title = data.name + ' - ' + (window.AMBILET_CONFIG?.SITE_NAME || 'Ambilet');

        // Update "Vezi toate" link to filter by artist
        var viewAllLink = document.getElementById(this.elements.viewAllEventsLink);
        if (viewAllLink) {
            viewAllLink.href = '/evenimente?artist=' + encodeURIComponent(data.slug || data.name);
        }

        // Hero image
        var heroImage = document.getElementById(this.elements.heroImage);
        if (heroImage) {
            heroImage.src = data.image;
            heroImage.alt = data.name;
            heroImage.onload = function() {
                heroImage.classList.remove('hidden');
                var skeleton = document.querySelector('.skeleton-hero');
                if (skeleton) skeleton.classList.add('hidden');
            };
        }

        // Artist name
        var nameEl = document.getElementById(this.elements.artistName);
        if (nameEl) nameEl.textContent = data.name;

        // Genre tags
        this.renderGenreTags(data.genres);

        // Stats
        this.renderStats(data.stats);

        // Social links
        this.updateSocialLinks(data.social);

        // Spotify embed
        this.updateSpotifyEmbed(data.spotifyId, data.social?.spotify, data.stats?.spotifyListeners);

        // Events - use rawEvents for AmbiletEventCard which handles commission
        this.renderEvents(data.rawEvents && data.rawEvents.length > 0 ? data.rawEvents : data.events);

        // About
        this.renderAbout(data.about);

        // Facts
        this.renderFacts(data.facts);

        // Booking Agency
        this.renderBookingAgency(data.bookingAgency);

        // YouTube Videos
        this.renderYoutubeVideos(data.youtubeVideos);

        // Gallery
        this.renderGallery(data.gallery);

        // Similar Artists
        this.renderSimilarArtists(data.similarArtists);
    },

    /**
     * Render genre tags
     */
    renderGenreTags(genres) {
        var container = document.getElementById(this.elements.genreTags);
        if (!container || !genres || genres.length === 0) return;

        var self = this;
        container.innerHTML = genres.map(function(genre) {
            return '<span class="px-4 py-2 bg-white/20 rounded-full text-[13px] font-medium text-white">' +
                self.escapeHtml(genre) + '</span>';
        }).join('');
    },

    /**
     * Render stats section
     */
    renderStats(stats) {
        var container = document.getElementById(this.elements.statsContainer);
        var spotifyListenersCount = document.getElementById(this.elements.spotifyListenersCountDiv);
        spotifyListenersCount.innerText = stats.spotifyListeners;

        if (!container) return;

        var divider = '<div class="hidden w-px h-12 bg-gray-200 lg:block"></div>';

        container.innerHTML =
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + stats.spotifyListeners + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Ascultatori lunari</div>' +
            '</div>' + divider +
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + stats.totalFollowers + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Total Followers</div>' +
            '</div>' + divider +
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + stats.spotifyPopularity + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Spotify Popularity</div>' +
            '</div>' + divider +
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + stats.youtubeViews + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">YouTube Views</div>' +
            '</div>';
    },

    /**
     * Update social links visibility
     */
    updateSocialLinks(social) {
        var socialMap = {
            facebook: { id: this.elements.socialFacebook, url: social?.facebook },
            instagram: { id: this.elements.socialInstagram, url: social?.instagram },
            youtube: { id: this.elements.socialYoutube, url: social?.youtube },
            tiktok: { id: this.elements.socialTiktok, url: social?.tiktok },
            spotify: { id: this.elements.socialSpotify, url: social?.spotify }
        };

        for (var key in socialMap) {
            var link = document.getElementById(socialMap[key].id);
            if (link) {
                if (socialMap[key].url) {
                    link.href = socialMap[key].url;
                    link.classList.remove('hidden');
                    link.classList.add('flex');
                } else {
                    link.classList.add('hidden');
                    link.classList.remove('flex');
                }
            }
        }
    },

    /**
     * Update Spotify embed section
     */
    updateSpotifyEmbed(spotifyId, spotifyUrl, listeners) {
        var spotifySection = document.getElementById(this.elements.spotifySection);
        if (!spotifySection) return;

        // Update Spotify link
        var spotifyLink = spotifySection.querySelector('a[target="_blank"]');
        if (spotifyLink && spotifyUrl) {
            spotifyLink.href = spotifyUrl;
        }

        // Update listeners text
        var listenersText = spotifySection.querySelector('p');
        if (listenersText && listeners) {
            listenersText.innerHTML = 'Descopera toate albumele, single-urile si colaborarile. <br/>Peste ' + listeners + ' ascultatori lunari!';
        }

        // Add Spotify embed player if spotifyId is available
        var embedContainer = spotifySection.querySelector('.bg-gray-50.rounded-xl');
        if (embedContainer) {
            if (spotifyId) {
                embedContainer.innerHTML = '<iframe style="border-radius:12px" src="https://open.spotify.com/embed/artist/' +
                    spotifyId + '?utm_source=generator&theme=0" width="100%" height="152" frameBorder="0" ' +
                    'allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>';
                embedContainer.classList.remove('flex', 'items-center', 'justify-center', 'text-gray-400', 'text-sm');
            } else if (!spotifyUrl) {
                // Hide entire Spotify section if no spotify data
                spotifySection.style.display = 'none';
            }
        }
    },

    /**
     * Render events list using AmbiletEventCard for commission support
     */
    renderEvents(events) {
        var container = document.getElementById(this.elements.eventsList);
        if (!container) return;

        if (events && events.length > 0) {
            // Use AmbiletEventCard for consistent rendering with commission support
            container.innerHTML = AmbiletEventCard.renderManyHorizontal(events, {
                urlPrefix: '/bilete/',
                showBuyButton: true
            });
        } else {
            container.innerHTML =
                '<div class="py-12 text-center bg-white border border-gray-200 rounded-2xl">' +
                    '<svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>' +
                        '<line x1="16" y1="2" x2="16" y2="6"/>' +
                        '<line x1="8" y1="2" x2="8" y2="6"/>' +
                        '<line x1="3" y1="10" x2="21" y2="10"/>' +
                    '</svg>' +
                    '<h3 class="mb-2 text-lg font-semibold text-gray-700">Niciun concert programat</h3>' +
                    '<p class="text-sm text-gray-500">Urmareste artistul pentru a fi notificat cand apar concerte noi.</p>' +
                '</div>';
        }
    },

    /**
     * Render about section
     * Note: Biography content comes from trusted admin panel and may contain HTML formatting
     */
    renderAbout(about) {
        var container = document.getElementById(this.elements.aboutCard);
        if (!container) return;

        container.innerHTML = about.map(function(text) {
            // Render HTML content directly - content is from trusted source (admin panel)
            return '<div class="text-base leading-[1.8] text-gray-600 mb-4 last:mb-0 prose prose-sm max-w-none">' + text + '</div>';
        }).join('');
    },

    /**
     * Render quick facts
     */
    renderFacts(facts) {
        var container = document.getElementById(this.elements.factsCard);
        if (!container) return;

        var self = this;
        var html = '<h3 class="flex items-center gap-2 mb-5 text-base font-bold text-gray-900">Quick Facts</h3>';

        facts.forEach(function(fact) {
            html += '<div class="flex justify-between py-3.5 border-b border-gray-100 last:border-0">' +
                '<span class="text-sm text-gray-500">' + self.escapeHtml(fact.label) + '</span>' +
                '<span class="text-sm font-semibold text-gray-900">' + self.escapeHtml(fact.value) + '</span>' +
            '</div>';
        });

        container.innerHTML = html;
    },

    /**
     * Render booking agency section
     */
    renderBookingAgency(agency) {
        var card = document.getElementById(this.elements.bookingAgencyCard);
        var content = document.getElementById(this.elements.bookingAgencyContent);

        if (!card || !content || !agency) return;

        if (!agency.name && !agency.email && !agency.phone && !agency.website) return;

        card.classList.remove('hidden');

        var html = '';

        if (agency.name) {
            html += '<div class="flex items-start gap-3 py-3">' +
                '<svg class="w-5 h-5 mt-0.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>' +
                '</svg>' +
                '<span class="text-sm font-semibold text-gray-900">' + this.escapeHtml(agency.name) + '</span>' +
            '</div>';
        }

        if (agency.email) {
            html += '<a href="mailto:' + this.escapeHtml(agency.email) + '" class="flex items-center gap-3 py-3 text-gray-600 transition-colors hover:text-primary">' +
                '<svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>' +
                '</svg>' +
                '<span class="text-sm">' + this.escapeHtml(agency.email) + '</span>' +
            '</a>';
        }

        if (agency.phone) {
            html += '<a href="tel:' + this.escapeHtml(agency.phone) + '" class="flex items-center gap-3 py-3 text-gray-600 transition-colors hover:text-primary">' +
                '<svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>' +
                '</svg>' +
                '<span class="text-sm">' + this.escapeHtml(agency.phone) + '</span>' +
            '</a>';
        }

        if (agency.website) {
            var websiteDisplay = agency.website.replace(/^https?:\/\//, '');
            html += '<a href="' + this.escapeHtml(agency.website) + '" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 py-3 text-gray-600 transition-colors hover:text-primary">' +
                '<svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>' +
                '</svg>' +
                '<span class="text-sm">' + this.escapeHtml(websiteDisplay) + '</span>' +
            '</a>';
        }

        content.innerHTML = html;
    },

    /**
     * Render YouTube videos section
     */
    renderYoutubeVideos(videos) {
        var section = document.getElementById(this.elements.youtubeVideosSection);
        var grid = document.getElementById(this.elements.youtubeVideosGrid);

        if (!section || !grid || !videos || videos.length === 0) return;

        section.classList.remove('hidden');

        var self = this;
        grid.innerHTML = videos.map(function(video) {
            var videoId = self.extractYoutubeId(video.url);
            if (!videoId) return '';

            return '<div class="overflow-hidden bg-white border border-gray-200 rounded-xl">' +
                '<div class="relative aspect-video">' +
                    '<iframe class="w-full h-full" src="https://www.youtube.com/embed/' + videoId + '" ' +
                        'title="' + self.escapeHtml(video.title || 'Video') + '" frameborder="0" ' +
                        'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ' +
                        'allowfullscreen loading="lazy"></iframe>' +
                '</div>' +
                (video.title ? '<div class="p-4"><h3 class="font-semibold text-gray-900 text-[15px] line-clamp-2">' + self.escapeHtml(video.title) + '</h3></div>' : '') +
            '</div>';
        }).filter(function(html) { return html !== ''; }).join('');
    },

    /**
     * Render gallery section
     */
    renderGallery(gallery) {
        var container = document.getElementById(this.elements.galleryGrid);
        if (!container) return;

        var self = this;
        container.innerHTML = gallery.map(function(item, index) {
            var spanClass = index === 0 ? 'col-span-2 row-span-2' : '';
            var videoIcon = item.isVideo ?
                '<div class="w-[60px] h-[60px] bg-primary/90 rounded-full flex items-center justify-center opacity-0 scale-75 group-hover:opacity-100 group-hover:scale-100 transition-all">' +
                    '<svg class="w-6 h-6 ml-1 text-white" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
                '</div>' : '';

            return '<div class="relative overflow-hidden cursor-pointer rounded-xl group ' + spanClass + ' aspect-square">' +
                '<img src="' + self.escapeHtml(item.url) + '" alt="Gallery" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105">' +
                '<div class="absolute inset-0 flex items-center justify-center transition-colors bg-black/0 group-hover:bg-black/30">' + videoIcon + '</div>' +
            '</div>';
        }).join('');
    },

    /**
     * Render similar artists section
     */
    renderSimilarArtists(artists) {
        var container = document.getElementById(this.elements.similarArtists);
        if (!container) return;

        if (artists.length === 0) {
            container.parentElement.style.display = 'none';
            return;
        }

        var self = this;
        container.innerHTML = artists.map(function(artist) {
            return '<a href="/artist/' + self.escapeHtml(artist.slug) + '" class="text-center transition-transform group hover:-translate-y-1">' +
                '<div class="w-full aspect-square rounded-full overflow-hidden mb-3 border-[3px] border-gray-200 group-hover:border-primary transition-colors">' +
                    '<img src="' + self.escapeHtml(artist.image) + '" alt="' + self.escapeHtml(artist.name) + '" class="object-cover w-full h-full">' +
                '</div>' +
                '<h3 class="text-[15px] font-bold text-gray-900 mb-0.5">' + self.escapeHtml(artist.name) + '</h3>' +
                '<p class="text-[13px] text-gray-500">' + self.escapeHtml(artist.genre) + '</p>' +
            '</a>';
        }).join('');
    },

    /**
     * Load follow status for artist
     */
    async loadFollowStatus() {
        if (!this.artistSlug) return;

        try {
            var response = await AmbiletAPI.checkArtistFavorite(this.artistSlug);
            if (response.success && response.data) {
                this.isFollowing = response.data.is_favorite;
                this.updateFollowButton();
            }
        } catch (e) {
            // Silently ignore - user not logged in or error
            console.log('[ArtistPage] Follow status check skipped');
        }
    },

    /**
     * Toggle follow status for artist
     */
    async toggleFollow() {
        if (!this.artistSlug) return;

        // Check if user is logged in
        if (!AmbiletAuth.isLoggedIn()) {
            window.location.href = '/cont/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        try {
            var response = await AmbiletAPI.toggleArtistFavorite(this.artistSlug);
            console.log('[ArtistPage] Toggle follow response:', response);
            if (response.success && response.data) {
                this.isFollowing = response.data.is_favorite;
                this.updateFollowButton();
            }
        } catch (e) {
            console.error('[ArtistPage] Toggle follow failed:', e);
            if (e.status === 401) {
                window.location.href = '/cont/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            }
        }
    },

    /**
     * Update follow button visual state
     */
    updateFollowButton() {
        var btn = document.getElementById(this.elements.followBtn);
        var text = document.getElementById(this.elements.followText);
        if (!btn || !text) return;

        if (this.isFollowing) {
            btn.classList.remove('from-primary', 'to-primary-light');
            btn.classList.add('from-gray-600', 'to-gray-700');
            text.textContent = 'Urmărești';
        } else {
            btn.classList.remove('from-gray-600', 'to-gray-700');
            btn.classList.add('from-primary', 'to-primary-light');
            text.textContent = 'Urmărește';
        }
    }
};

// Make available globally
window.ArtistPage = ArtistPage;
