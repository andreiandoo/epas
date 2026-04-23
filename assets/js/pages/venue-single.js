/**
 * Ambilet.ro - Venue Single Page Controller
 * Populates the layout defined in venue-single.php. Handles events list
 * (with category filter tabs), stats tiles, about + amenities block,
 * Google reviews grid (gated), quick-info sidebar, map, similar venues,
 * share dropdown, gallery lightbox and the mobile sticky CTA.
 *
 * Dependencies: AmbiletAPI, AmbiletUtils, AmbiletAuth
 */

const VenuePage = {
    venueSlug: '',
    venue: null,
    isFollowing: false,

    // Event filter state
    currentEventFilter: 'all',

    // Gallery lightbox state
    lightboxImages: [],
    lightboxCurrent: 0,

    // Share
    shareUrl: typeof window !== 'undefined' ? window.location.href : '',

    monthNames: ['IAN', 'FEB', 'MAR', 'APR', 'MAI', 'IUN', 'IUL', 'AUG', 'SEP', 'OCT', 'NOI', 'DEC'],
    weekdayNames: ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'],

    elements: {
        heroImage: 'heroImage',
        venueName: 'venueName',
        venueLocation: 'venueLocation',
        quickInfo: 'quickInfo',
        venueAddress: 'venueAddress',
        mapsLink: 'mapsLink',
        venueMap: 'venueMap',
        eventsList: 'eventsList',
        similarVenues: 'similarVenues',
        similarVenuesSection: 'similarVenuesSection',
        followBtn: 'follow-btn',
        followIcon: 'follow-icon',
        followText: 'follow-text'
    },

    async init() {
        this.venueSlug = window.VENUE_SLUG || '';
        if (!this.venueSlug) {
            this.showError('Locația nu a fost găsită');
            return;
        }

        this.shareUrl = window.location.href;
        this.bindGlobalHandlers();

        await this.loadVenueData();

        // Follow is a customer-only feature. Hide the button (and skip the
        // API round-trip) when the logged-in user is an organizer, since the
        // backend won't let them follow anyway.
        if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isOrganizer && AmbiletAuth.isOrganizer()) {
            const btn = document.getElementById(this.elements.followBtn);
            if (btn) btn.classList.add('hidden');
        } else {
            this.loadFollowStatus();
        }
    },

    bindGlobalHandlers() {
        const self = this;
        // Close share dropdown on outside click
        document.addEventListener('click', function (e) {
            const dd = document.getElementById('shareDropdown');
            const btn = document.getElementById('shareBtn');
            if (!dd || dd.classList.contains('hidden')) return;
            if (dd.contains(e.target) || (btn && btn.contains(e.target))) return;
            dd.classList.add('hidden');
        });
        // Keyboard: Esc closes lightbox / share; arrows navigate lightbox
        document.addEventListener('keydown', function (e) {
            const lightbox = document.getElementById('galleryLightbox');
            const lbOpen = lightbox && !lightbox.classList.contains('hidden');
            if (e.key === 'Escape') {
                if (lbOpen) self.closeLightbox();
                const dd = document.getElementById('shareDropdown');
                if (dd) dd.classList.add('hidden');
            }
            if (lbOpen) {
                if (e.key === 'ArrowRight') self.lightboxNext();
                if (e.key === 'ArrowLeft') self.lightboxPrev();
            }
        });
    },

    async loadVenueData() {
        try {
            const response = await AmbiletAPI.getVenue(this.venueSlug);
            if (response.success && response.data) {
                this.venue = this.transformApiData(response.data);
                this.render();
            } else {
                this.showError('Locația nu a fost găsită');
            }
        } catch (error) {
            console.error('Failed to load venue:', error);
            if (error.status === 404) {
                this.showError('Locația nu a fost găsită');
            } else {
                this.showError('Eroare la încărcarea datelor');
            }
        }
    },

    transformApiData(apiData) {
        if (!apiData) return null;

        const self = this;

        // Upcoming events (raw + formatted shape)
        const events = (apiData.upcoming_events || []).map(function (e) {
            const date = new Date(e.event_date || e.starts_at);
            const categoryName = (e.category && e.category.name) || e.category || '';
            const categorySlug = (e.category && e.category.slug) || self.slugify(categoryName);
            return {
                id: e.id,
                slug: e.slug,
                title: e.title || e.name,
                date: date,
                day: String(date.getDate()).padStart(2, '0'),
                monthShort: self.monthNames[date.getMonth()],
                weekday: self.weekdayNames[date.getDay()],
                time: e.start_time ? e.start_time.substring(0, 5) : null,
                categoryName: categoryName,
                categorySlug: categorySlug,
                priceFrom: e.price_from || e.min_price || 0,
                currency: e.currency || 'RON',
                image: e.hero_image_url || e.poster_url || e.image || null,
                isSoldOut: e.is_sold_out || false,
                isCancelled: e.is_cancelled || false
            };
        });

        // Category filter options derived from events
        const categoryCounts = {};
        events.forEach(function (ev) {
            if (!ev.categorySlug) return;
            if (!categoryCounts[ev.categorySlug]) {
                categoryCounts[ev.categorySlug] = { slug: ev.categorySlug, name: ev.categoryName, count: 0 };
            }
            categoryCounts[ev.categorySlug].count++;
        });
        const eventCategories = Object.values(categoryCounts);

        // Primary venue category (first one) — used as hero badge
        const primaryCategory = (apiData.categories && apiData.categories[0]) || null;

        // Address
        const addressParts = [apiData.address];
        if (apiData.city) addressParts.push(apiData.city);
        if (apiData.postal_code) addressParts.push(apiData.postal_code);
        const fullAddress = addressParts.filter(Boolean).join(', ');

        // Gallery — accept both facilities-style objects and plain strings
        const galleryUrls = (apiData.gallery || apiData.images || []).map(function (g) {
            if (!g) return null;
            if (typeof g === 'string') return g;
            return g.url || g.src || null;
        }).filter(Boolean);
        // Fall back to the cover image so the lightbox still shows something
        // when the venue didn't upload an explicit gallery.
        if (galleryUrls.length === 0 && apiData.cover_image) galleryUrls.push(apiData.cover_image);
        if (galleryUrls.length === 0 && apiData.image) galleryUrls.push(apiData.image);

        // Facilities (backend returns `facilities`); fall back to `amenities`.
        let amenities = [];
        if (Array.isArray(apiData.facilities) && apiData.facilities.length > 0) {
            amenities = apiData.facilities.map(function (f) {
                if (typeof f === 'string') return { label: f, icon: '' };
                return { label: f.label || f.name || '', icon: f.icon || '' };
            }).filter(function (f) { return !!f.label; });
        } else if (Array.isArray(apiData.amenities) && apiData.amenities.length > 0) {
            amenities = apiData.amenities.map(function (a) {
                return typeof a === 'string' ? { label: a, icon: '' } : { label: a.label || '', icon: a.icon || '' };
            });
        }

        // Similar venues
        const similarVenues = (apiData.similar_venues || []).map(function (v) {
            return {
                slug: v.slug,
                name: v.name,
                city: v.city || '',
                eventsCount: v.events_count || 0,
                image: v.image || null,
                categoryName: (v.categories && v.categories[0] && v.categories[0].name) || ''
            };
        });

        return {
            name: apiData.name || 'Locație',
            slug: apiData.slug,
            primaryCategory: primaryCategory,
            city: apiData.city || '',
            address: fullAddress,
            latitude: apiData.latitude || apiData.lat,
            longitude: apiData.longitude || apiData.lng,
            googleMapsUrl: apiData.google_maps_url || null,
            capacity: apiData.capacity ? Number(apiData.capacity).toLocaleString('ro-RO') : null,
            eventsCount: apiData.events_count || events.length,
            yearBuilt: apiData.established_at || apiData.year_built || null,
            image: apiData.cover_image || apiData.image || '/assets/images/default_venue.jpg',
            portrait: apiData.portrait || null,
            description: apiData.description || '',
            phone: apiData.phone || (apiData.contact && apiData.contact.phone) || '',
            email: apiData.email || (apiData.contact && apiData.contact.email) || '',
            website: apiData.website || (apiData.contact && apiData.contact.website) || '',
            facebook: (apiData.social && apiData.social.facebook) || apiData.facebook_url || '',
            instagram: (apiData.social && apiData.social.instagram) || apiData.instagram_url || '',
            tiktok: (apiData.social && apiData.social.tiktok) || apiData.tiktok_url || '',
            schedule: apiData.schedule || '',
            amenities: amenities,
            gallery: galleryUrls,
            events: events,
            eventCategories: eventCategories,
            googleReviews: apiData.google_reviews || null,
            similarVenues: similarVenues
        };
    },

    showError(message) {
        const skeleton = document.querySelector('.skeleton-hero');
        if (skeleton) skeleton.remove();
        const hero = document.getElementById('venueHero');
        if (hero) {
            hero.innerHTML =
                '<div class="flex items-center justify-center h-full bg-slate-100">' +
                    '<div class="text-center p-6">' +
                        '<svg class="w-16 h-16 mx-auto mb-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
                        '<h2 class="text-xl font-bold text-slate-700">' + this.escapeHtml(message) + '</h2>' +
                        '<a href="/locatii" class="inline-block px-6 py-3 mt-4 text-white rounded-lg bg-primary hover:bg-primary-dark">Vezi toate locațiile</a>' +
                    '</div>' +
                '</div>';
        }
    },

    /** Top-level render pipeline */
    render() {
        const venue = this.venue;
        if (!venue) return;

        this.renderHero(venue);
        this.renderStats(venue);
        this.renderAboutAndAmenities(venue);
        this.renderReviewsSection(venue.googleReviews);
        this.renderEventFilterTabs(venue.eventCategories, venue.events.length);
        this.renderEvents(venue.events, 'all');
        this.renderEventsSubheader(venue.events);
        this.renderQuickInfo(venue);
        this.renderAddress(venue);
        this.renderMap(venue);
        this.renderGalleryButton(venue.gallery);
        this.renderMobileStickyCta(venue.events);
        this.renderSimilarVenues(venue.similarVenues);
        this.setupShareLinks();
        this.setupAllEventsLink(venue.slug);

        // Page title
        document.title = venue.name + ' - ' + ((window.AMBILET_CONFIG && window.AMBILET_CONFIG.SITE_NAME) || 'AmBilet.ro');
    },

    /* ═════════════ HERO ═════════════ */
    renderHero(venue) {
        // Hero image — prefer portrait on narrow screens when provided
        const heroImg = document.getElementById(this.elements.heroImage);
        if (heroImg) {
            const isMobile = window.innerWidth < 768;
            heroImg.src = (isMobile && venue.portrait) ? venue.portrait : venue.image;
            heroImg.alt = venue.name;
            heroImg.classList.remove('hidden');
        }
        const skeleton = document.querySelector('.skeleton-hero');
        if (skeleton) skeleton.remove();

        this.setText(this.elements.venueName, venue.name);
        this.setText(this.elements.venueLocation, [venue.address || '', venue.city].filter(Boolean).join(' · ') || venue.city || '');

        // Category badge
        const catBadge = document.getElementById('venueCategoryBadge');
        const catLabel = document.getElementById('venueCategoryBadgeLabel');
        if (catBadge && catLabel) {
            if (venue.primaryCategory && venue.primaryCategory.name) {
                catLabel.textContent = venue.primaryCategory.name;
                catBadge.classList.remove('hidden');
            } else {
                catBadge.classList.add('hidden');
            }
        }

        // Open-status pill — hide entirely when we have no schedule data to
        // avoid showing "În funcție de evenimente" without any basis for it.
        const openStatus = document.getElementById('venueOpenStatus');
        const openStatusText = document.getElementById('venueOpenStatusText');
        if (openStatus) {
            if (venue.schedule) {
                if (openStatusText) openStatusText.textContent = venue.schedule;
                openStatus.classList.remove('hidden');
                openStatus.classList.add('flex');
            } else {
                openStatus.classList.add('hidden');
                openStatus.classList.remove('flex');
            }
        }

        // Rating pill in hero (gated on Google data)
        const ratingEl = document.getElementById('venueRatingHero');
        if (ratingEl) {
            const gr = venue.googleReviews;
            if (gr && gr.rating != null && gr.review_count != null && Number(gr.review_count) > 0) {
                this.setText('venueRatingHeroValue', Number(gr.rating).toFixed(1));
                this.setText('venueRatingHeroCount', this.formatNumber(Number(gr.review_count)));
                ratingEl.classList.remove('hidden');
                ratingEl.classList.add('flex');
            } else {
                ratingEl.classList.add('hidden');
                ratingEl.classList.remove('flex');
            }
        }
    },

    /* ═════════════ STATS TILES ═════════════ */
    renderStats(venue) {
        // Capacity + Events count are always shown (even with -)
        this.setText('statCapacity', venue.capacity || '-');
        this.setText('statEvents', venue.eventsCount ? this.formatNumber(venue.eventsCount) : '0');

        // Rating tile — gated on Google data
        const ratingTile = document.getElementById('statRatingTile');
        if (ratingTile) {
            const gr = venue.googleReviews;
            if (gr && gr.rating != null && gr.review_count != null && Number(gr.review_count) > 0) {
                this.setText('statRatingValue', Number(gr.rating).toFixed(1));
                this.setText('statRatingCount', this.formatNumber(Number(gr.review_count)) + ' recenzii');
                ratingTile.classList.remove('hidden');
                ratingTile.classList.add('flex');
            } else {
                ratingTile.classList.add('hidden');
                ratingTile.classList.remove('flex');
            }
        }

        // Year-built tile — only when we know the year
        const yearTile = document.getElementById('statYearTile');
        if (yearTile) {
            if (venue.yearBuilt) {
                this.setText('statYear', venue.yearBuilt);
                yearTile.classList.remove('hidden');
                yearTile.classList.add('flex');
            } else {
                yearTile.classList.add('hidden');
                yearTile.classList.remove('flex');
            }
        }
    },

    /* ═════════════ ABOUT + AMENITIES ═════════════ */
    renderAboutAndAmenities(venue) {
        const section = document.getElementById('aboutSection');
        const about = document.getElementById('venueAbout');
        const amenitiesBlock = document.getElementById('amenitiesBlock');
        const amenitiesGrid = document.getElementById('venueAmenities');
        if (!section) return;

        const hasDescription = !!venue.description;
        const hasAmenities = Array.isArray(venue.amenities) && venue.amenities.length > 0;

        if (!hasDescription && !hasAmenities) {
            section.classList.add('hidden');
            return;
        }
        section.classList.remove('hidden');

        if (about) {
            if (hasDescription) {
                const hasHtml = /<[a-z][\s\S]*>/i.test(venue.description);
                about.innerHTML = hasHtml
                    ? '<div class="prose prose-sm max-w-none text-slate-700">' + venue.description + '</div>'
                    : '<p class="whitespace-pre-line">' + this.escapeHtml(venue.description) + '</p>';
            } else {
                about.innerHTML = '';
            }
        }

        if (amenitiesBlock && amenitiesGrid) {
            if (hasAmenities) {
                const self = this;
                amenitiesGrid.innerHTML = venue.amenities.map(function (a) {
                    const iconPrefix = a.icon ? self.escapeHtml(a.icon) + ' ' : '';
                    return '<div class="flex items-center gap-2 text-sm text-slate-700 bg-slate-50 rounded-lg px-3 py-2">' +
                        '<svg class="w-3.5 h-3.5 text-emerald-600 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>' +
                        '<span>' + iconPrefix + self.escapeHtml(a.label) + '</span>' +
                    '</div>';
                }).join('');
                amenitiesBlock.classList.remove('hidden');
            } else {
                amenitiesBlock.classList.add('hidden');
            }
        }
    },

    /* ═════════════ REVIEWS ═════════════ */
    renderReviewsSection(gr) {
        const section = document.getElementById('reviewsSection');
        const grid = document.getElementById('reviewsGrid');
        const subheader = document.getElementById('reviewsSubheader');
        const seeAll = document.getElementById('reviewsSeeAllLink');
        if (!section || !grid) return;

        const reviews = (gr && Array.isArray(gr.reviews)) ? gr.reviews : [];
        const rating = gr && gr.rating != null ? Number(gr.rating) : null;
        const reviewCount = gr && gr.review_count != null ? Number(gr.review_count) : 0;

        if (!rating || reviews.length === 0) {
            section.classList.add('hidden');
            return;
        }
        section.classList.remove('hidden');

        if (subheader) {
            subheader.textContent = rating.toFixed(1) + ' din 5 · ' + this.formatNumber(reviewCount) + ' recenzii';
        }
        // "Vezi toate" always visible once we have reviews. Prefer an explicit
        // URL from the payload (reviews_url / url / place_url), fall back to a
        // Google Maps place link using place_id, finally to a Google search
        // by venue name + address so the user is never left without a way to
        // reach the full review list.
        if (seeAll) {
            let seeUrl = (gr && (gr.reviews_url || gr.url || gr.place_url)) || null;
            if (!seeUrl && gr && gr.place_id) {
                seeUrl = 'https://www.google.com/maps/place/?q=place_id:' + encodeURIComponent(gr.place_id);
            }
            if (!seeUrl && this.venue) {
                const q = [this.venue.name, this.venue.address].filter(Boolean).join(' ');
                seeUrl = 'https://www.google.com/search?q=' + encodeURIComponent(q + ' recenzii');
            }
            seeAll.href = seeUrl || '#';
            seeAll.classList.remove('hidden');
        }

        const self = this;
        const palette = [
            'from-pink-400 to-rose-500',
            'from-blue-400 to-indigo-500',
            'from-emerald-400 to-teal-500',
            'from-amber-400 to-orange-500',
            'from-violet-400 to-purple-500'
        ];

        // Pick 3 reviews at random from those scoring ≥ 4.5 — done client-side
        // so the Google Places cache doesn't need invalidation. If the
        // high-rated pool has fewer than 3 entries we pad with the next-best
        // ones by rating (still avoiding anything obviously negative).
        const highRated = reviews.filter(function (r) { return (Number(r.rating) || 0) >= 4.5; });
        let pool = self.shuffle(highRated.slice());
        if (pool.length < 3) {
            const padding = reviews
                .filter(function (r) { return (Number(r.rating) || 0) < 4.5; })
                .slice()
                .sort(function (a, b) { return (Number(b.rating) || 0) - (Number(a.rating) || 0); });
            pool = pool.concat(padding);
        }
        const displayReviews = pool.slice(0, 3);

        grid.innerHTML = displayReviews.map(function (r, i) {
            const name = r.author_name || r.author || 'Vizitator';
            const initials = self.buildInitials(name);
            const stars = self.buildStars(r.rating || 0);
            // Prefer an absolute date (unix `time` / `created_at` / `published_at`)
            // so reviews read as "12 apr. 2026" instead of "în ultima săptămână".
            // Fall back to the relative string only if no real date is available.
            const dateStr = self.formatReviewDate(r.time || r.created_at || r.published_at || r.relative_time_description);
            const text = r.text || r.comment || '';
            const gradient = palette[i % palette.length];
            return '<div class="border border-slate-200 rounded-xl p-4">' +
                '<div class="flex items-center justify-between mb-2">' +
                    '<div class="flex items-center gap-2">' +
                        '<div class="w-8 h-8 rounded-full bg-gradient-to-br ' + gradient + ' flex items-center justify-center text-white text-xs font-semibold">' + self.escapeHtml(initials) + '</div>' +
                        '<div>' +
                            '<div class="text-sm font-semibold text-slate-900">' + self.escapeHtml(name) + '</div>' +
                            (dateStr ? '<div class="text-xs text-slate-500">' + self.escapeHtml(dateStr) + '</div>' : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="text-amber-500 text-xs">' + stars + '</div>' +
                '</div>' +
                (text ? '<p class="text-sm text-slate-700 leading-relaxed">„' + self.escapeHtml(text) + '"</p>' : '') +
            '</div>';
        }).join('');
    },

    /* ═════════════ EVENTS ═════════════ */
    renderEventFilterTabs(categories, totalEvents) {
        const container = document.getElementById('eventFilterTabs');
        if (!container) return;
        // Hide the tab strip only when there are literally zero events; when
        // we have at least one categorised event, show "Toate (N)" + the
        // category tabs even if they're all the same category — the design
        // reference always surfaces the "Toate" tab as an anchor.
        if (!totalEvents || totalEvents === 0) {
            container.classList.add('hidden');
            container.classList.remove('flex');
            return;
        }
        container.classList.remove('hidden');
        container.classList.add('flex');

        const self = this;
        let html = '<button type="button" onclick="VenuePage.setEventFilter(\'all\')" data-event-filter="all" class="event-tab is-active relative px-4 py-2.5 text-sm font-semibold whitespace-nowrap transition">Toate (' + totalEvents + ')</button>';
        html += (categories || []).map(function (c) {
            return '<button type="button" onclick="VenuePage.setEventFilter(\'' + self.escapeAttr(c.slug) + '\')" data-event-filter="' + self.escapeAttr(c.slug) + '" class="event-tab text-slate-600 hover:text-slate-900 relative px-4 py-2.5 text-sm font-semibold whitespace-nowrap transition">' + self.escapeHtml(c.name) + ' (' + c.count + ')</button>';
        }).join('');
        container.innerHTML = html;
    },

    setEventFilter(slug) {
        this.currentEventFilter = slug;
        // Toggle active class on tabs
        document.querySelectorAll('.event-tab').forEach(function (btn) {
            if (btn.dataset.eventFilter === slug) {
                btn.classList.add('is-active');
                btn.classList.remove('text-slate-600', 'hover:text-slate-900');
            } else {
                btn.classList.remove('is-active');
                btn.classList.add('text-slate-600', 'hover:text-slate-900');
            }
        });
        this.renderEvents(this.venue.events, slug);
    },

    renderEvents(events, filterSlug) {
        const container = document.getElementById(this.elements.eventsList);
        const footer = document.getElementById('eventsFooter');
        const footerCount = document.getElementById('eventsFooterCount');
        if (!container) return;

        const filter = filterSlug || 'all';
        const filtered = (events || []).filter(function (e) {
            return filter === 'all' || e.categorySlug === filter;
        });

        if (filtered.length === 0) {
            container.innerHTML =
                '<div class="p-12 text-center">' +
                    '<div class="w-14 h-14 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">' +
                        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>' +
                    '</div>' +
                    '<h3 class="font-semibold text-slate-900 mb-1">' + (events && events.length ? 'Niciun eveniment în această categorie' : 'Nu există evenimente viitoare') + '</h3>' +
                    (events && events.length
                        ? '<p class="text-sm text-slate-500 mb-4">Încearcă o altă categorie.</p><button type="button" onclick="VenuePage.setEventFilter(\'all\')" class="text-sm font-semibold text-primary hover:text-primary-dark">← Vezi toate evenimentele</button>'
                        : '<p class="text-sm text-slate-500">Revino mai târziu pentru evenimente noi.</p>') +
                '</div>';
            if (footer) footer.classList.add('hidden');
            return;
        }

        const self = this;
        container.innerHTML = filtered.map(function (e, i) {
            return self.renderEventRow(e, i);
        }).join('');

        if (footer && footerCount) {
            const totalText = (events || []).length === filtered.length
                ? 'Afișate ' + filtered.length + ' din ' + (events || []).length + ' evenimente'
                : 'Afișate ' + filtered.length + ' din ' + (events || []).length;
            footerCount.textContent = totalText;
            footer.classList.remove('hidden');
        }
    },

    renderEventRow(e, idx) {
        const thumbClass = 'venue-thumb-' + ((idx % 6) + 1);
        const image = e.image
            ? '<img src="' + this.escapeAttr(e.image) + '" alt="" class="w-full h-full object-cover" loading="lazy">'
            : '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" opacity=".9"><rect x="3" y="5" width="18" height="14" rx="2"/></svg>';
        const categoryBadge = e.categoryName
            ? '<span class="text-xs font-semibold text-primary uppercase tracking-wider">' + this.escapeHtml(e.categoryName) + '</span>'
            : '';
        const timeBadge = e.time
            ? '<span class="flex items-center gap-1"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> ' + this.escapeHtml(e.time) + '</span>'
            : '';
        const priceBlock = e.isSoldOut
            ? '<div class="text-sm font-bold text-slate-400 line-through">SOLD OUT</div>'
            : (e.priceFrom > 0
                ? '<div class="text-[11px] text-slate-500 mb-0.5">de la</div>' +
                  '<div class="text-lg font-bold text-slate-900 mb-2">' + this.formatNumber(e.priceFrom) + ' ' + this.escapeHtml(e.currency || 'lei') + '</div>'
                : '<div class="text-lg font-bold text-emerald-600 mb-2">Gratuit</div>');
        const buyLabel = e.isSoldOut ? 'Detalii' : 'Cumpără';

        return '<a href="/bilete/' + this.escapeAttr(e.slug) + '" class="event-row flex items-center gap-4 p-4 md:p-5 border-l-4 border-transparent ' + (e.isCancelled ? 'opacity-60' : '') + '">' +
            '<div class="flex-none w-16 text-center bg-primary text-white rounded-lg overflow-hidden">' +
                '<div class="text-[10px] font-bold uppercase tracking-wider bg-primary-dark/90 py-1">' + this.escapeHtml(e.monthShort) + '</div>' +
                '<div class="text-2xl font-bold py-1.5">' + this.escapeHtml(e.day) + '</div>' +
                '<div class="text-[10px] font-medium pb-1.5 opacity-80">' + this.escapeHtml(e.weekday) + '</div>' +
            '</div>' +
            '<div class="' + thumbClass + ' flex-none w-20 h-20 rounded-lg hidden sm:flex items-center justify-center text-white overflow-hidden">' + image + '</div>' +
            '<div class="flex-1 min-w-0">' +
                (categoryBadge ? '<div class="flex items-center gap-2 mb-1">' + categoryBadge + '</div>' : '') +
                '<h3 class="font-semibold text-slate-900 mb-1 truncate">' + this.escapeHtml(e.title) + '</h3>' +
                '<div class="flex items-center gap-3 text-xs text-slate-500 flex-wrap">' + timeBadge + '</div>' +
            '</div>' +
            '<div class="flex-none text-right">' +
                priceBlock +
                '<div class="event-buy-btn inline-block px-4 py-2 bg-slate-100 text-slate-900 rounded-lg text-xs font-semibold transition">' + buyLabel + '</div>' +
            '</div>' +
        '</a>';
    },

    renderEventsSubheader(events) {
        const el = document.getElementById('eventsSubheader');
        if (!el) return;
        if (!events || events.length === 0) {
            el.textContent = 'Niciun eveniment programat';
            return;
        }
        const lastEvent = events[events.length - 1];
        const last = lastEvent && lastEvent.date instanceof Date && !isNaN(lastEvent.date)
            ? lastEvent.date.toLocaleDateString('ro-RO', { month: 'long', year: 'numeric' })
            : null;
        el.textContent = events.length + (events.length === 1 ? ' eveniment' : ' evenimente') + (last ? ' · până în ' + last : '');
    },

    setupAllEventsLink(venueSlug) {
        const link = document.getElementById('allEventsLink');
        if (link && venueSlug) {
            link.href = '/evenimente?locatie=' + encodeURIComponent(venueSlug);
        }
    },

    /* ═════════════ QUICK INFO (sidebar) ═════════════ */
    renderQuickInfo(venue) {
        const container = document.getElementById(this.elements.quickInfo);
        if (!container) return;

        const rows = [];

        if (venue.schedule) {
            rows.push(this.renderQuickInfoRow(
                '<svg class="mt-0.5 text-slate-400 flex-none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'Program',
                this.escapeHtml(venue.schedule),
                null
            ));
        }
        if (venue.phone) {
            rows.push(this.renderQuickInfoRow(
                '<svg class="mt-0.5 text-slate-400 flex-none group-hover:text-primary transition" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
                'Telefon',
                '<span class="text-sm text-primary font-semibold">' + this.escapeHtml(venue.phone) + '</span>',
                'tel:' + venue.phone.replace(/\s/g, '')
            ));
        }
        if (venue.email) {
            rows.push(this.renderQuickInfoRow(
                '<svg class="mt-0.5 text-slate-400 flex-none group-hover:text-primary transition" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
                'Email',
                '<span class="text-sm text-primary font-semibold truncate">' + this.escapeHtml(venue.email) + '</span>',
                'mailto:' + venue.email
            ));
        }
        if (venue.website) {
            const websiteUrl = venue.website.startsWith('http') ? venue.website : 'https://' + venue.website;
            const websiteDisplay = venue.website.replace(/^https?:\/\//, '').replace(/\/$/, '');
            rows.push(this.renderQuickInfoRow(
                '<svg class="mt-0.5 text-slate-400 flex-none group-hover:text-primary transition" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
                'Website',
                '<span class="text-sm text-primary font-semibold">' + this.escapeHtml(websiteDisplay) + '</span>',
                websiteUrl,
                '_blank'
            ));
        }

        // Social buttons — full-width rows with icon + platform label so each
        // row matches the phone/email/website pattern above it.
        const socialRow = function (href, label, iconSvg) {
            return '<a href="' + self.escapeAttr(href) + '" target="_blank" rel="noopener" class="group flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50 transition">' +
                '<span class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 group-hover:bg-primary group-hover:text-white transition flex-none">' + iconSvg + '</span>' +
                '<span class="text-sm font-semibold text-slate-900">' + label + '</span>' +
            '</a>';
        };
        const self = this;
        if (venue.facebook) {
            rows.push(socialRow(
                venue.facebook,
                'Facebook',
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'
            ));
        }
        if (venue.instagram) {
            rows.push(socialRow(
                venue.instagram,
                'Instagram',
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'
            ));
        }
        if (venue.tiktok) {
            rows.push(socialRow(
                venue.tiktok,
                'TikTok',
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.43v-7.15a8.16 8.16 0 005.58 2.19V11.2a4.85 4.85 0 01-3.77-1.74V6.69h3.77z"/></svg>'
            ));
        }

        if (rows.length === 0) {
            container.innerHTML = '<div class="p-5 text-sm italic text-slate-500">Informații de contact indisponibile</div>';
            return;
        }
        container.innerHTML = rows.join('');
    },

    renderQuickInfoRow(icon, label, valueHtml, href, target) {
        const tag = href ? 'a' : 'div';
        const hrefAttr = href ? ' href="' + this.escapeAttr(href) + '"' : '';
        const targetAttr = target ? ' target="' + this.escapeAttr(target) + '" rel="noopener"' : '';
        const hoverClass = href ? 'hover:bg-slate-50 transition group' : '';
        return '<' + tag + hrefAttr + targetAttr + ' class="px-5 py-3.5 flex items-start gap-3 ' + hoverClass + '">' +
            icon +
            '<div class="min-w-0 flex-1">' +
                '<div class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-0.5">' + label + '</div>' +
                '<div class="text-sm text-slate-900">' + valueHtml + '</div>' +
            '</div>' +
        '</' + tag + '>';
    },

    /* ═════════════ ADDRESS + MAP ═════════════ */
    renderAddress(venue) {
        const el = document.getElementById(this.elements.venueAddress);
        if (!el) return;
        el.innerHTML = this.escapeHtml(venue.address || venue.city || '').replace(/, /g, '<br>');

        const mapsLink = document.getElementById(this.elements.mapsLink);
        if (mapsLink) {
            if (venue.googleMapsUrl) {
                mapsLink.href = venue.googleMapsUrl;
            } else if (venue.latitude && venue.longitude) {
                mapsLink.href = 'https://www.google.com/maps/search/?api=1&query=' + venue.latitude + ',' + venue.longitude;
            } else if (venue.address) {
                mapsLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(venue.address);
            }
        }
    },

    renderMap(venue) {
        const mapContainer = document.getElementById(this.elements.venueMap);
        if (!mapContainer) return;

        let mapQuery = '';
        if (venue.latitude && venue.longitude) {
            mapQuery = venue.latitude + ',' + venue.longitude;
        } else if (venue.address) {
            mapQuery = encodeURIComponent(venue.address);
        }

        if (mapQuery) {
            mapContainer.innerHTML = '<iframe src="https://www.google.com/maps?q=' + mapQuery + '&output=embed" width="100%" height="100%" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
            mapContainer.classList.remove('flex', 'flex-col', 'items-center', 'justify-center');
        } else {
            mapContainer.innerHTML =
                '<svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
                '<p class="text-sm mt-2">Locație indisponibilă</p>';
        }
    },

    /* ═════════════ GALLERY ═════════════ */
    renderGalleryButton(gallery) {
        const btn = document.getElementById('galleryBtn');
        const countEl = document.getElementById('galleryCount');
        if (!btn || !countEl) return;
        if (!gallery || gallery.length === 0) {
            btn.classList.add('hidden');
            btn.classList.remove('flex');
            return;
        }
        this.lightboxImages = gallery.slice();
        countEl.textContent = gallery.length;
        btn.classList.remove('hidden');
        btn.classList.add('flex');
    },

    openLightbox(index) {
        const lb = document.getElementById('galleryLightbox');
        if (!lb || !this.lightboxImages.length) return;
        this.lightboxCurrent = index || 0;
        lb.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        this.renderLightboxImage();
        this.renderLightboxThumbs();
    },

    closeLightbox() {
        const lb = document.getElementById('galleryLightbox');
        if (!lb) return;
        lb.classList.add('hidden');
        document.body.style.overflow = '';
    },

    lightboxNext() {
        if (!this.lightboxImages.length) return;
        this.lightboxCurrent = (this.lightboxCurrent + 1) % this.lightboxImages.length;
        this.renderLightboxImage();
        this.renderLightboxThumbs();
    },

    lightboxPrev() {
        if (!this.lightboxImages.length) return;
        this.lightboxCurrent = (this.lightboxCurrent - 1 + this.lightboxImages.length) % this.lightboxImages.length;
        this.renderLightboxImage();
        this.renderLightboxThumbs();
    },

    renderLightboxImage() {
        const img = document.getElementById('lightboxImage');
        const idxEl = document.getElementById('lightboxIndex');
        const totalEl = document.getElementById('lightboxTotal');
        if (img) img.src = this.lightboxImages[this.lightboxCurrent] || '';
        if (idxEl) idxEl.textContent = this.lightboxCurrent + 1;
        if (totalEl) totalEl.textContent = this.lightboxImages.length;
    },

    renderLightboxThumbs() {
        const wrap = document.getElementById('lightboxThumbs');
        if (!wrap) return;
        const self = this;
        wrap.innerHTML = this.lightboxImages.map(function (src, i) {
            return '<button type="button" onclick="VenuePage.gotoLightbox(' + i + ')" class="lightbox-thumb flex-none w-16 h-16 md:w-20 md:h-20 rounded-lg overflow-hidden transition-all ' + (i === self.lightboxCurrent ? 'is-active' : 'opacity-50 hover:opacity-100') + '">' +
                '<img src="' + self.escapeAttr(src) + '" alt="" class="w-full h-full object-cover">' +
            '</button>';
        }).join('');
    },

    gotoLightbox(i) {
        this.lightboxCurrent = i;
        this.renderLightboxImage();
        this.renderLightboxThumbs();
    },

    /* ═════════════ MOBILE STICKY CTA ═════════════ */
    renderMobileStickyCta(events) {
        const bar = document.getElementById('mobileStickyCta');
        const label = document.getElementById('mobileCtaEventLabel');
        const link = document.getElementById('mobileCtaLink');
        if (!bar || !label || !link) return;
        if (!events || events.length === 0) {
            bar.classList.add('hidden');
            return;
        }
        const first = events[0];
        const when = first.day && first.monthShort ? first.day + ' ' + first.monthShort : '';
        const time = first.time ? ', ' + first.time : '';
        label.textContent = first.title + (when ? ' · ' + when + time : '');
        link.href = '/bilete/' + first.slug;
        bar.classList.remove('hidden');
    },

    /* ═════════════ SIMILAR VENUES ═════════════ */
    renderSimilarVenues(venues) {
        const container = document.getElementById(this.elements.similarVenues);
        const section = document.getElementById(this.elements.similarVenuesSection);
        if (!container || !section) return;
        if (!venues || venues.length === 0) {
            section.classList.add('hidden');
            return;
        }
        section.classList.remove('hidden');

        // Contextualise the title + subtitle. When the current venue's city
        // is known we scope the copy to that city ("în București") and use
        // the primary category in the subtitle ("Alte teatre din zona ta")
        // — falls back to generic copy otherwise.
        const titleEl = document.getElementById('similarVenuesTitle');
        const subtitleEl = document.getElementById('similarVenuesSubtitle');
        if (titleEl) {
            const city = this.venue && this.venue.city;
            titleEl.textContent = city ? 'Locații similare în ' + city : 'Locații similare';
        }
        if (subtitleEl) {
            const cat = this.venue && this.venue.primaryCategory && this.venue.primaryCategory.name;
            subtitleEl.textContent = cat ? 'Alte ' + cat.toLowerCase() + ' din zona ta' : 'Alte locații pe care le poți descoperi';
        }

        const self = this;
        container.innerHTML = venues.slice(0, 4).map(function (v, i) {
            const thumbClass = 'venue-thumb-' + ((i % 6) + 1);
            const imgHtml = v.image
                ? '<img src="' + self.escapeAttr(v.image) + '" alt="" class="absolute inset-0 w-full h-full object-cover">'
                : '';
            const badge = v.categoryName
                ? '<div class="absolute top-2 left-2 bg-white/95 backdrop-blur px-2 py-0.5 rounded text-[10px] font-semibold text-slate-800">' + self.escapeHtml(v.categoryName) + '</div>'
                : '';
            return '<a href="/locatie/' + self.escapeAttr(v.slug) + '" class="bg-white rounded-2xl venue-card-shadow venue-card-shadow-hover overflow-hidden group transition">' +
                '<div class="aspect-[4/3] ' + thumbClass + ' relative">' + imgHtml + badge + '</div>' +
                '<div class="p-3">' +
                    '<h3 class="font-semibold text-sm text-slate-900 group-hover:text-primary transition">' + self.escapeHtml(v.name) + '</h3>' +
                    '<div class="text-xs text-slate-500 mt-0.5">' + self.escapeHtml(v.city) + (v.eventsCount ? ' · ' + v.eventsCount + ' evenimente' : '') + '</div>' +
                '</div>' +
            '</a>';
        }).join('');
    },

    /* ═════════════ SHARE DROPDOWN ═════════════ */
    setupShareLinks() {
        const url = this.shareUrl;
        const urlEl = document.getElementById('shareUrl');
        if (urlEl) urlEl.textContent = url;

        const title = this.venue ? (this.venue.name + ' - AmBilet.ro') : 'AmBilet.ro';
        const text = this.venue ? ('Descoperă evenimentele de la ' + this.venue.name + ' pe AmBilet.ro') : '';

        const fb = document.getElementById('shareFacebook');
        const wa = document.getElementById('shareWhatsapp');
        const tw = document.getElementById('shareTwitter');
        const em = document.getElementById('shareEmail');
        if (fb) fb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
        if (wa) wa.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text + ' ' + url);
        if (tw) tw.href = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text);
        if (em) em.href = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(text + ' ' + url);
    },

    toggleShareDropdown(event) {
        if (event) event.stopPropagation();
        const dd = document.getElementById('shareDropdown');
        if (!dd) return;
        dd.classList.toggle('hidden');
    },

    copyShareLink() {
        const url = this.shareUrl;
        const self = this;
        const done = function () {
            const label = document.getElementById('shareCopyLabel');
            const hint = document.getElementById('shareCopyHint');
            const iconWrap = document.getElementById('shareCopyIconWrap');
            const icon = document.getElementById('shareCopyIcon');
            if (label) label.textContent = 'Link copiat!';
            if (hint) hint.textContent = 'Gata de distribuit';
            if (iconWrap) iconWrap.classList.add('bg-emerald-100');
            if (icon) icon.innerHTML = '<polyline points="20 6 9 17 4 12" stroke="#059669" stroke-width="3"/>';
            setTimeout(function () {
                if (label) label.textContent = 'Copiază link';
                if (hint) hint.textContent = 'Copiază URL-ul paginii';
                if (iconWrap) iconWrap.classList.remove('bg-emerald-100');
                if (icon) icon.innerHTML = '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>';
            }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done).catch(function () { self.fallbackCopy(url, done); });
        } else {
            self.fallbackCopy(url, done);
        }
    },

    fallbackCopy(text, done) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
    },

    /* ═════════════ FOLLOW ═════════════ */
    async loadFollowStatus() {
        if (!this.venueSlug) return;
        try {
            const response = await AmbiletAPI.checkVenueFavorite(this.venueSlug);
            if (response.success && response.data) {
                this.isFollowing = response.data.is_favorite;
                this.updateFollowButton();
            }
        } catch (e) {
            // Silently ignore - user not logged in
        }
    },

    async toggleFollow() {
        if (!this.venueSlug) return;
        if (!AmbiletAuth.isLoggedIn()) {
            window.location.href = '/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }
        try {
            const response = await AmbiletAPI.toggleVenueFavorite(this.venueSlug);
            if (response.success && response.data) {
                this.isFollowing = response.data.is_favorite;
                this.updateFollowButton();
            }
        } catch (e) {
            if (e && e.status === 401) {
                window.location.href = '/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            }
        }
    },

    updateFollowButton() {
        const btn = document.getElementById(this.elements.followBtn);
        const text = document.getElementById(this.elements.followText);
        if (!btn || !text) return;
        if (this.isFollowing) {
            btn.classList.remove('bg-white', 'hover:bg-slate-100', 'text-slate-900');
            btn.classList.add('bg-primary', 'text-white', 'hover:bg-primary-dark');
            text.textContent = 'Urmărești';
        } else {
            btn.classList.remove('bg-primary', 'text-white', 'hover:bg-primary-dark');
            btn.classList.add('bg-white', 'hover:bg-slate-100', 'text-slate-900');
            text.textContent = 'Urmărește locația';
        }
    },

    /* ═════════════ CONTACT MODAL ═════════════ */
    openContactModal() {
        const modal = document.getElementById('contactVenueModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        const nameEl = document.getElementById('contactVenueName');
        if (nameEl && this.venue) nameEl.textContent = this.venue.name;

        if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isLoggedIn()) {
            const user = AmbiletAuth.getUser();
            const form = document.getElementById('contactVenueForm');
            if (user && form) {
                if (user.name) form.querySelector('[name="name"]').value = user.name;
                if (user.email) form.querySelector('[name="email"]').value = user.email;
            }
        }
        const err = document.getElementById('contactFormError');
        const ok = document.getElementById('contactFormSuccess');
        if (err) err.classList.add('hidden');
        if (ok) ok.classList.add('hidden');
    },

    closeContactModal(event) {
        if (event && event.target !== event.currentTarget) return;
        const modal = document.getElementById('contactVenueModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.style.display = '';
        document.body.style.overflow = '';
    },

    async submitContactForm(event) {
        event.preventDefault();
        const form = document.getElementById('contactVenueForm');
        const submitBtn = document.getElementById('contactSubmitBtn');
        const errorEl = document.getElementById('contactFormError');
        const successEl = document.getElementById('contactFormSuccess');
        if (!form || !this.venueSlug) return;

        submitBtn.disabled = true;
        submitBtn.querySelector('span').textContent = 'Se trimite...';
        if (errorEl) errorEl.classList.add('hidden');
        if (successEl) successEl.classList.add('hidden');

        const data = {
            name: form.querySelector('[name="name"]').value.trim(),
            email: form.querySelector('[name="email"]').value.trim(),
            subject: form.querySelector('[name="subject"]').value.trim(),
            message: form.querySelector('[name="message"]').value.trim()
        };

        try {
            const response = await AmbiletAPI.post('/venues/' + this.venueSlug + '/contact', data);
            if (response.success) {
                if (successEl) {
                    successEl.textContent = 'Mesajul tău a fost trimis cu succes! Locația va reveni cu un răspuns.';
                    successEl.classList.remove('hidden');
                }
                form.reset();
                setTimeout(function () { VenuePage.closeContactModal(); }, 3000);
            } else if (errorEl) {
                errorEl.textContent = response.message || 'Eroare la trimiterea mesajului. Încearcă din nou.';
                errorEl.classList.remove('hidden');
            }
        } catch (e) {
            if (errorEl) {
                let msg = 'Eroare la trimiterea mesajului. Încearcă din nou.';
                if (e && e.data && e.data.message) msg = e.data.message;
                else if (e && e.message) msg = e.message;
                errorEl.textContent = msg;
                errorEl.classList.remove('hidden');
            }
        } finally {
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = 'Trimite mesajul';
        }
    },

    /* ═════════════ HELPERS ═════════════ */
    setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value == null ? '' : value;
    },
    escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    },
    escapeAttr(text) {
        return this.escapeHtml(text).replace(/"/g, '&quot;');
    },
    slugify(text) {
        return String(text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    },
    formatNumber(n) {
        const num = Number(n);
        if (!isFinite(num)) return '0';
        return num.toLocaleString('ro-RO');
    },
    buildInitials(name) {
        const parts = String(name || '').trim().split(/\s+/);
        if (parts.length === 0) return '?';
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    },
    buildStars(rating) {
        const r = Math.round(Number(rating) || 0);
        let out = '';
        for (let i = 1; i <= 5; i++) out += i <= r ? '★' : '☆';
        return out;
    },
    shuffle(arr) {
        // Fisher–Yates in place. Returns the same array for chaining.
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            const tmp = arr[i];
            arr[i] = arr[j];
            arr[j] = tmp;
        }
        return arr;
    },
    formatReviewDate(value) {
        if (!value) return '';
        // If already a human-readable string (e.g. "o săptămână în urmă"), return as-is
        if (typeof value === 'string' && !/^\d+$/.test(value) && !/^\d{4}-\d{2}-\d{2}/.test(value)) return value;
        // Unix timestamp (seconds) — Google Places returns integer
        let d;
        if (typeof value === 'number' || /^\d+$/.test(String(value))) {
            const n = Number(value);
            d = new Date(n > 9999999999 ? n : n * 1000);
        } else {
            d = new Date(value);
        }
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
    }
};

window.VenuePage = VenuePage;
