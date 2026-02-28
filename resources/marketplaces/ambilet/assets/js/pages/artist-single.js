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

        // Close video modal on Escape key
        var self = this;
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') self.closeVideoModal();
        });
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
                // Check if this is a Core-only artist (coming soon)
                if (response.data.is_coming_soon) {
                    this.showComingSoon(response.data);
                    return;
                }
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
                    '<h1 class="mb-3 text-3xl font-bold text-gray-700">Artist negăsit</h1>' +
                    '<p class="mb-8 text-gray-500">Ne pare rău, nu am putut găsi artistul căutat.</p>' +
                    '<a href="/artisti" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-all rounded-xl bg-primary hover:bg-primary-dark hover:shadow-lg">' +
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>' +
                        '</svg>' +
                        'Înapoi la artiști' +
                    '</a>' +
                '</div>';
        }

        // Hide profile section
        var profileSection = document.getElementById('profile-section');
        if (profileSection) {
            profileSection.style.display = 'none';
        }

        // Hide Spotify section
        var spotifySection = document.getElementById('spotifySection');
        if (spotifySection) {
            spotifySection.closest('.px-6')?.style && (spotifySection.closest('.px-6').style.display = 'none');
        }

        // Hide Similar Artists section
        var similarArtists = document.getElementById('similarArtists');
        if (similarArtists) {
            var similarSection = similarArtists.closest('section');
            if (similarSection && similarSection.closest('.px-6')) {
                similarSection.closest('.px-6').style.display = 'none';
            }
        }

        // Update page title
        document.title = 'Artist negăsit - ' + (window.AMBILET_CONFIG?.SITE_NAME || 'Ambilet');
    },

    /**
     * Show coming soon page for Core-only artists
     */
    showComingSoon(data) {
        var heroSection = document.getElementById(this.elements.artistHero);
        if (heroSection) {
            var imageHtml = data.image || data.portrait || data.logo
                ? '<img src="' + this.escapeHtml(data.image || data.portrait || data.logo) + '" alt="' + this.escapeHtml(data.name) + '" class="object-cover w-32 h-32 mb-6 rounded-full shadow-lg">'
                : '<svg class="w-32 h-32 mb-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>' +
                  '</svg>';

            heroSection.innerHTML =
                '<div class="flex flex-col items-center justify-center h-full bg-gradient-to-br from-primary/5 to-primary/10">' +
                    imageHtml +
                    '<h1 class="mb-3 text-3xl font-bold text-gray-800">' + this.escapeHtml(data.name) + '</h1>' +
                    '<p class="mb-8 text-gray-600">Detalii despre artist vor fi disponibile în curând.</p>' +
                    '<a href="/artisti" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-all rounded-xl bg-primary hover:bg-primary-dark hover:shadow-lg">' +
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>' +
                        '</svg>' +
                        'Înapoi la artiști' +
                    '</a>' +
                '</div>';
        }

        // Hide profile section
        var profileSection = document.getElementById('profile-section');
        if (profileSection) {
            profileSection.style.display = 'none';
        }

        // Hide Spotify section
        var spotifySection = document.getElementById('spotifySection');
        if (spotifySection) {
            spotifySection.closest('.px-6')?.style && (spotifySection.closest('.px-6').style.display = 'none');
        }

        // Hide Similar Artists section
        var similarArtists = document.getElementById('similarArtists');
        if (similarArtists) {
            var similarSection = similarArtists.closest('section');
            if (similarSection && similarSection.closest('.px-6')) {
                similarSection.closest('.px-6').style.display = 'none';
            }
        }

        // Update page title
        document.title = data.name + ' - ' + (window.AMBILET_CONFIG?.SITE_NAME || 'Ambilet');
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
                upcomingEvents: api.stats?.upcoming_events || events.length,
                spotifyListenersRaw: api.stats?.spotify_listeners || 0  
            },
            about: api.biography ? [api.biography] : ['Informații despre acest artist vor fi adăugate în curând.'],
            aboutTranslations: api.biography_translations || {},
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
        this.renderAbout(data.about, data.aboutTranslations);

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
        spotifyListenersCount.innerText = stats.spotifyListenersRaw;

        if (!container) return;

        var divider = '<div class="hidden w-px h-12 bg-gray-200 lg:block"></div>';

        container.innerHTML =
            '<div class="text-center flex-1 min-w-[100px]">' +
                '<div class="text-[28px] font-extrabold text-gray-900">' + stats.spotifyListeners + '</div>' +
                '<div class="text-[13px] text-gray-500 mt-1">Ascultători lunari</div>' +
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
            listenersText.innerHTML = 'Descoperă toate albumele, single-urile și colaborările. <br/>Peste ' + listeners + ' ascultători lunari!';
        }

        // Add Spotify embed player if spotifyId is available
        var embedContainer = spotifySection.querySelector('.bg-gray-50.rounded-xl');
        if (embedContainer) {
            if (spotifyId) {
                var embedHeight = window.innerWidth < 768 ? 400 : 152;
                embedContainer.innerHTML = '<iframe style="border-radius:12px" src="https://open.spotify.com/embed/artist/' +
                    spotifyId + '?utm_source=generator&theme=0" width="100%" height="' + embedHeight + '" frameBorder="0" ' +
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
                    '<p class="text-sm text-gray-500">Urmărește artistul pentru a fi notificat când apar concerte noi.</p>' +
                '</div>';
        }
    },

    /**
     * Render about section with RO/EN tabs if both translations exist
     * Note: Biography content comes from trusted admin panel and may contain HTML formatting
     */
    renderAbout(about, translations) {
        var container = document.getElementById(this.elements.aboutCard);
        if (!container) return;

        var bioRo = translations?.ro || '';
        var bioEn = translations?.en || '';
        var hasBoth = bioRo && bioEn;

        if (hasBoth) {
            // Tabbed layout with RO and EN
            container.innerHTML =
                '<div class="flex gap-1 p-1 mb-5 bg-gray-100 rounded-xl justify-center" id="aboutTabs">' +
                    '<button onclick="ArtistPage.switchAboutTab(\'ro\')" class="about-tab px-4 py-2 text-sm font-semibold rounded-lg transition-all bg-white text-gray-900 shadow-sm" data-tab="ro">' +
                        '<span class="inline-block w-5 h-3.5 mr-1.5 align-middle rounded-sm overflow-hidden relative" style="top:-1px">' +
                            '<span class="absolute inset-x-0 top-0 h-1/3 bg-[#002B7F]"></span>' +
                            '<span class="absolute inset-x-0 top-1/3 h-1/3 bg-[#FCD116]"></span>' +
                            '<span class="absolute inset-x-0 bottom-0 h-1/3 bg-[#CE1126]"></span>' +
                        '</span>Română' +
                    '</button>' +
                    '<button onclick="ArtistPage.switchAboutTab(\'en\')" class="about-tab px-4 py-2 text-sm font-semibold rounded-lg transition-all text-gray-500 hover:text-gray-700" data-tab="en">' +
                        '<span class="inline-block w-5 h-3.5 mr-1.5 align-middle rounded-sm overflow-hidden relative" style="top:-1px">' +
                            '<svg viewBox="0 0 60 30" class="w-full h-full"><clipPath id="t"><rect width="60" height="30"/></clipPath><g clip-path="url(#t)"><rect width="60" height="30" fill="#00247D"/><path d="M0 0l60 30M60 0L0 30" stroke="#fff" stroke-width="6"/><path d="M0 0l60 30M60 0L0 30" clip-path="url(#t)" stroke="#CF142B" stroke-width="4"/><path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="10"/><path d="M30 0v30M0 15h60" stroke="#CF142B" stroke-width="6"/></g></svg>' +
                        '</span>English' +
                    '</button>' +
                '</div>' +
                '<div id="aboutContent-ro" class="text-base flex flex-col gap-y-2 leading-[1.8] text-gray-600 prose prose-sm max-w-none bg-white border border-gray-200 shadow-sm rounded-2xl p-4">' + bioRo + '</div>' +
                '<div id="aboutContent-en" class="hidden text-base flex flex-col gap-y-2 leading-[1.8] text-gray-600 prose prose-sm max-w-none bg-white border border-gray-200 shadow-sm rounded-2xl p-4">' + bioEn + '</div>';
        } else {
            // Single language
            container.innerHTML = about.map(function(text) {
                return '<div class="text-base flex flex-col gap-y-2 leading-[1.8] text-gray-600 mb-4 last:mb-0 prose prose-sm max-w-none bg-white border border-gray-200 shadow-sm rounded-2xl p-4">' + text + '</div>';
            }).join('');
        }
    },

    /**
     * Switch between RO/EN about tabs
     */
    switchAboutTab(lang) {
        // Update tab buttons
        document.querySelectorAll('#aboutTabs .about-tab').forEach(function(btn) {
            if (btn.dataset.tab === lang) {
                btn.className = 'px-4 py-2 text-sm font-semibold text-gray-900 transition-all bg-white rounded-lg shadow-sm about-tab';
            } else {
                btn.className = 'px-4 py-2 text-sm font-semibold text-gray-500 transition-all rounded-lg about-tab hover:text-gray-700';
            }
        });
        // Show/hide content
        var roContent = document.getElementById('aboutContent-ro');
        var enContent = document.getElementById('aboutContent-en');
        if (roContent) roContent.classList.toggle('hidden', lang !== 'ro');
        if (enContent) enContent.classList.toggle('hidden', lang !== 'en');
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
     * 1-2 videos: thumbnail cards in columns
     * 3-5 videos: player (3/4) + playlist sidebar (1/4)
     */
    renderYoutubeVideos(videos) {
        var section = document.getElementById(this.elements.youtubeVideosSection);
        var grid = document.getElementById(this.elements.youtubeVideosGrid);

        if (!section || !grid || !videos || videos.length === 0) return;

        var self = this;
        var validVideos = videos.map(function(video) {
            var videoId = self.extractYoutubeId(video.url);
            if (!videoId) return null;
            return { id: videoId, title: video.title || '', url: video.url };
        }).filter(Boolean);

        if (validVideos.length === 0) return;
        section.classList.remove('hidden');

        // Store for playlist switching
        this._videoList = validVideos;
        this._activeVideoIndex = 0;

        var count = validVideos.length;

        if (count === 1) {
            grid.className = 'max-w-4xl mx-auto';
            grid.innerHTML = self._videoCard(validVideos[0], 'large');
        } else if (count === 2) {
            grid.className = 'grid grid-cols-1 gap-4 md:grid-cols-2';
            grid.innerHTML = validVideos.map(function(v) {
                return self._videoCard(v, 'medium');
            }).join('');
        } else {
            // 3-5 videos: player + playlist layout
            self._renderPlayerPlaylist(grid, validVideos);
        }

        // Fetch real titles from YouTube oEmbed for videos without titles
        self._fetchVideoTitles(validVideos);
    },

    /**
     * Render player + playlist layout for 3-5 videos
     */
    _renderPlayerPlaylist(grid, videos) {
        var self = this;
        var count = videos.length;

        grid.className = 'grid grid-cols-1 gap-4 lg:grid-cols-4';

        // Main player (3/4)
        var firstTitle = self.escapeHtml(videos[0].title) || 'Se incarca...';
        var playerHtml = '<div class="lg:col-span-3">' +
            '<div class="overflow-hidden bg-gray-900 rounded-2xl">' +
                '<div class="relative aspect-video">' +
                    '<iframe id="videoPlayer" class="w-full h-full" ' +
                        'src="https://www.youtube.com/embed/' + videos[0].id + '?rel=0" ' +
                        'title="' + firstTitle + '" frameborder="0" ' +
                        'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ' +
                        'allowfullscreen></iframe>' +
                '</div>' +
                '<div class="px-5 py-4">' +
                    '<h3 id="videoPlayerTitle" class="text-lg font-semibold text-white truncate">' + firstTitle + '</h3>' +
                '</div>' +
            '</div>' +
        '</div>';

        // Playlist sidebar (1/4)
        var playlistHtml = '<div class="lg:col-span-1">' +
            '<div class="overflow-hidden bg-white border border-gray-200 rounded-2xl h-full flex flex-col">' +
                '<div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex-shrink-0">' +
                    '<span class="text-sm font-semibold text-gray-700">' + count + ' videoclipuri</span>' +
                '</div>' +
                '<div class="overflow-y-auto divide-y divide-gray-100 flex-1" id="videoPlaylist">';

        videos.forEach(function(v, i) {
            var thumbUrl = 'https://img.youtube.com/vi/' + v.id + '/mqdefault.jpg';
            var activeClass = i === 0 ? 'bg-primary/5 border-l-[3px] border-l-primary' : 'border-l-[3px] border-l-transparent hover:bg-gray-50';
            var displayTitle = self.escapeHtml(v.title) || 'Video ' + (i + 1);

            playlistHtml += '<div class="flex gap-3 p-3 transition-colors cursor-pointer playlist-item ' + activeClass + '" ' +
                'data-index="' + i + '" onclick="ArtistPage.playVideo(' + i + ')">' +
                '<div class="relative flex-shrink-0 overflow-hidden rounded-lg w-28 group">' +
                    '<img src="' + thumbUrl + '" alt="" class="object-cover w-full h-full aspect-video" loading="lazy">' +
                    '<div class="absolute inset-0 flex items-center justify-center transition-colors bg-black/30 group-hover:bg-black/40">' +
                        '<svg class="w-6 h-6 text-white drop-shadow" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
                    '</div>' +
                '</div>' +
                '<div class="flex-1 min-w-0 py-0.5">' +
                    '<p class="text-[13px] font-medium text-gray-900 line-clamp-2 leading-snug video-title-' + i + '">' + displayTitle + '</p>' +
                    '<span class="block mt-1 text-xs text-gray-400">YouTube</span>' +
                '</div>' +
            '</div>';
        });

        playlistHtml += '</div></div></div>';
        grid.innerHTML = playerHtml + playlistHtml;
    },

    /**
     * Fetch real video titles from YouTube oEmbed API
     */
    _fetchVideoTitles(videos) {
        var self = this;
        videos.forEach(function(v, i) {
            if (v.title) return; // Already has a title

            var oembedUrl = 'https://noembed.com/embed?url=https://www.youtube.com/watch?v=' + v.id;
            fetch(oembedUrl)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.title) return;

                    // Update stored title
                    v.title = data.title;
                    if (self._videoList && self._videoList[i]) {
                        self._videoList[i].title = data.title;
                    }

                    // Update playlist item title
                    var titleEl = document.querySelector('.video-title-' + i);
                    if (titleEl) titleEl.textContent = data.title;

                    // Update player title if this is the active video
                    if (self._activeVideoIndex === i) {
                        var playerTitle = document.getElementById('videoPlayerTitle');
                        if (playerTitle) playerTitle.textContent = data.title;
                    }

                    // Update thumbnail card title if in 1-2 video layout
                    var cardTitle = document.querySelector('[data-video-title-' + i + ']');
                    if (cardTitle) cardTitle.textContent = data.title;
                })
                .catch(function() {}); // Silently fail
        });
    },

    /**
     * Switch active video in the player (for playlist layout)
     */
    playVideo(index) {
        if (!this._videoList || !this._videoList[index]) return;

        var video = this._videoList[index];
        var iframe = document.getElementById('videoPlayer');
        var titleEl = document.getElementById('videoPlayerTitle');

        if (iframe) {
            iframe.src = 'https://www.youtube.com/embed/' + video.id + '?autoplay=1&rel=0';
        }
        if (titleEl) {
            titleEl.textContent = video.title || 'Video ' + (index + 1);
        }

        // Update active state in playlist
        this._activeVideoIndex = index;
        var items = document.querySelectorAll('#videoPlaylist .playlist-item');
        items.forEach(function(item, i) {
            if (i === index) {
                item.className = item.className
                    .replace('border-l-transparent', 'border-l-primary')
                    .replace('hover:bg-gray-50', '');
                if (!item.classList.contains('bg-primary/5')) {
                    item.classList.add('bg-primary/5');
                }
            } else {
                item.className = item.className
                    .replace('border-l-primary', 'border-l-transparent')
                    .replace('bg-primary/5', '');
                if (!item.classList.contains('hover:bg-gray-50')) {
                    item.classList.add('hover:bg-gray-50');
                }
            }
        });
    },

    /**
     * Build a single video thumbnail card (used for 1-2 videos)
     */
    _videoCard(video, size) {
        var thumbUrl = 'https://img.youtube.com/vi/' + video.id + '/maxresdefault.jpg';
        var thumbFallback = 'https://img.youtube.com/vi/' + video.id + '/hqdefault.jpg';
        var title = this.escapeHtml(video.title);
        var isLarge = size === 'large';

        return '<div class="relative overflow-hidden cursor-pointer group rounded-2xl bg-gray-900" onclick="ArtistPage.openVideoModal(\'' + video.id + '\', \'' + title.replace(/'/g, "\\'") + '\')">' +
            '<div class="aspect-video">' +
                '<img src="' + thumbUrl + '" alt="' + title + '" ' +
                    'class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105" loading="lazy" ' +
                    'onerror="this.src=\'' + thumbFallback + '\'">' +
            '</div>' +
            '<div class="absolute inset-0 transition-colors bg-gradient-to-t from-black/80 via-black/20 to-transparent group-hover:from-black/90"></div>' +
            '<div class="absolute inset-0 flex items-center justify-center">' +
                '<div class="flex items-center justify-center transition-all duration-300 rounded-full shadow-lg ' +
                    (isLarge ? 'w-20 h-20' : 'w-16 h-16') + ' bg-white/95 group-hover:scale-110 group-hover:bg-white group-hover:shadow-xl">' +
                    '<svg class="' + (isLarge ? 'w-8 h-8' : 'w-6 h-6') + ' ml-1 text-[#FF0000]" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>' +
                '</div>' +
            '</div>' +
            (title ? '<div class="absolute bottom-0 left-0 right-0 p-4 ' + (isLarge ? 'pb-5' : 'pb-4') + '">' +
                '<h3 class="font-semibold text-white ' + (isLarge ? 'text-lg' : 'text-[15px]') + ' line-clamp-2 drop-shadow-lg">' + title + '</h3>' +
            '</div>' : '') +
        '</div>';
    },

    /**
     * Open video in modal lightbox (used for 1-2 video layouts)
     */
    openVideoModal(videoId, title) {
        var modal = document.getElementById('videoModal');
        var iframe = document.getElementById('videoModalIframe');
        var titleEl = document.getElementById('videoModalTitle');

        if (!modal || !iframe) return;

        iframe.src = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0';
        if (titleEl) titleEl.textContent = title || '';

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    },

    /**
     * Close video modal
     */
    closeVideoModal(event) {
        if (event && event.target !== event.currentTarget) return;

        var modal = document.getElementById('videoModal');
        var iframe = document.getElementById('videoModalIframe');

        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        if (iframe) iframe.src = '';
        document.body.style.overflow = '';
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
                '<img src="' + self.escapeHtml(item.url) + '" alt="Gallery" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105" loading="lazy">' +
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
                    '<img src="' + self.escapeHtml(artist.image) + '" alt="' + self.escapeHtml(artist.name) + '" class="object-cover w-full h-full" loading="lazy">' +
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
