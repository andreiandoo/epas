(function() {
    // ==================== CONFIGURATION ====================
    const SEARCH_MIN_CHARS = 2;
    const SEARCH_DEBOUNCE_MS = 300;
    const SEARCH_API_URL = '/api/v1/public/search';

    // ==================== DOM ELEMENTS ====================
    const header = document.getElementById('header');
    const searchBtn = document.getElementById('searchBtn');
    const searchOverlay = document.getElementById('searchOverlay');
    const searchContainer = document.getElementById('searchContainer');
    const searchCloseBtn = document.getElementById('searchCloseBtn');
    const searchInput = document.getElementById('searchInput');
    const searchQuickLinks = document.getElementById('searchQuickLinks');
    const searchResults = document.getElementById('searchResults');
    const searchLoading = document.getElementById('searchLoading');
    const searchNoResults = document.getElementById('searchNoResults');
    const searchResultsContent = document.getElementById('searchResultsContent');
    const searchMinChars = document.getElementById('searchMinChars');
    const searchViewAll = document.getElementById('searchViewAll');
    const searchViewAllLink = document.getElementById('searchViewAllLink');

    // Section elements
    const searchEventsSection = document.getElementById('searchEventsSection');
    const searchEventsList = document.getElementById('searchEventsList');
    const searchEventsCount = document.getElementById('searchEventsCount');
    const searchArtistsSection = document.getElementById('searchArtistsSection');
    const searchArtistsList = document.getElementById('searchArtistsList');
    const searchArtistsCount = document.getElementById('searchArtistsCount');
    const searchLocationsSection = document.getElementById('searchLocationsSection');
    const searchLocationsList = document.getElementById('searchLocationsList');
    const searchLocationsCount = document.getElementById('searchLocationsCount');

    // ==================== SCROLL EFFECT ====================
    const isTransparentMode = header.dataset.transparent === 'true';
    const headerTopBar = document.getElementById('headerTopBar');
    const headerTimerBarRef = document.getElementById('headerTimerBar');
    const headerLogo = document.querySelector('.header-logo');
    const logoTextAm = document.getElementById('logoTextAm');
    const logoTextBilet = document.getElementById('logoTextBilet');
    const navBtns = document.querySelectorAll('.nav-btn');
    const actionBtns = document.querySelectorAll('.action-btn');
    const loginBtn = document.getElementById('loginBtn');
    const cartIcon = document.getElementById('cartIcon');

    function updateHeaderState(isScrolled) {
        if (isTransparentMode) {
            if (isScrolled) {
                header.classList.remove('bg-transparent', 'border-transparent', 'header-transparent');
                header.classList.add('bg-white', 'border-b', 'border-gray-200', 'shadow-lg');
                headerTopBar?.classList.add('hidden');
                headerLogo?.classList.remove('brightness-0', 'invert');
                if (logoTextAm) {
                    logoTextAm.classList.remove('text-white/85');
                    logoTextAm.classList.add('text-slate-800');
                }
                if (logoTextBilet) {
                    logoTextBilet.classList.remove('text-white');
                    logoTextBilet.classList.add('text-primary');
                }
                navBtns.forEach(btn => {
                    btn.classList.remove('text-white/90', 'hover:text-white', 'hover:bg-white/10');
                    btn.classList.add('text-gray-500', 'hover:text-gray-900', 'hover:bg-gray-100');
                });
                actionBtns.forEach(btn => {
                    btn.classList.remove('text-white/90', 'text-white', 'bg-white/10', 'hover:bg-white/10', 'hover:bg-white/20');
                    btn.classList.add('text-gray-500', 'bg-gray-100', 'hover:bg-gray-200', 'hover:text-gray-900');
                });
                if (loginBtn) {
                    loginBtn.classList.remove('border-white/30', 'text-white', 'hover:border-white', 'hover:bg-white/10');
                    loginBtn.classList.add('border-border', 'hover:border-primary', 'hover:text-primary');
                }
                if (cartIcon) {
                    cartIcon.classList.remove('text-white/90');
                    cartIcon.classList.add('text-gray-500');
                }
            } else {
                header.classList.add('bg-transparent', 'border-transparent', 'header-transparent');
                header.classList.remove('bg-white', 'border-b', 'border-gray-200', 'shadow-lg');
                if (!headerTimerBarRef || headerTimerBarRef.classList.contains('hidden')) {
                    headerTopBar?.classList.remove('hidden');
                }
                headerLogo?.classList.add('brightness-0', 'invert');
                if (logoTextAm) {
                    logoTextAm.classList.add('text-white/85');
                    logoTextAm.classList.remove('text-slate-800');
                }
                if (logoTextBilet) {
                    logoTextBilet.classList.add('text-white');
                    logoTextBilet.classList.remove('text-primary');
                }
                navBtns.forEach(btn => {
                    btn.classList.add('text-white/90', 'hover:text-white', 'hover:bg-white/10');
                    btn.classList.remove('text-gray-500', 'hover:text-gray-900', 'hover:bg-gray-100');
                });
                actionBtns.forEach(btn => {
                    btn.classList.add('text-white/90', 'bg-white/10', 'hover:bg-white/20');
                    btn.classList.remove('text-gray-500', 'bg-gray-100', 'hover:bg-gray-200', 'hover:text-gray-900');
                });
                if (loginBtn) {
                    loginBtn.classList.add('border-white/30', 'text-white', 'hover:border-white', 'hover:bg-white/10');
                    loginBtn.classList.remove('border-border', 'hover:border-primary', 'hover:text-primary');
                }
                if (cartIcon) {
                    cartIcon.classList.add('text-white/90');
                    cartIcon.classList.remove('text-gray-500');
                }
            }
        } else {
            header.classList.toggle('shadow-lg', isScrolled);
            if (!headerTimerBarRef || headerTimerBarRef.classList.contains('hidden')) {
                headerTopBar.classList.toggle('hidden', isScrolled);
            } else {
                headerTopBar.classList.add('hidden');
            }
        }
    }

    // Cache scroll state to avoid forced reflow when other code needs it
    var cachedIsScrolled = false;
    window.addEventListener('scroll', function() {
        cachedIsScrolled = window.scrollY > 50;
        updateHeaderState(cachedIsScrolled);
    }, {passive: true});

    // ==================== SEARCH PANEL ====================
    function openSearch() {
        searchOverlay.style.visibility = '';
        searchContainer.style.visibility = '';
        searchOverlay.classList.remove('opacity-0', 'invisible');
        searchOverlay.classList.add('opacity-100', 'visible');
        searchContainer.classList.remove('-translate-y-full');
        searchContainer.classList.add('translate-y-0', 'shadow-xl');
        searchInput.focus();
        document.body.style.overflow = 'hidden';
    }

    function closeSearch() {
        searchOverlay.classList.add('opacity-0', 'invisible');
        searchOverlay.classList.remove('opacity-100', 'visible');
        searchContainer.classList.add('-translate-y-full');
        searchContainer.classList.remove('translate-y-0', 'shadow-xl');
        document.body.style.overflow = '';
        searchInput.value = '';
        resetSearchUI();
    }

    searchBtn.addEventListener('click', openSearch);
    searchCloseBtn.addEventListener('click', closeSearch);
    searchOverlay.addEventListener('click', closeSearch);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !searchOverlay.classList.contains('invisible')) {
            closeSearch();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
    });

    // ==================== INSTANT SEARCH ====================
    var searchTimeout = null;
    var currentSearchQuery = '';

    function resetSearchUI() {
        if (searchQuickLinks) searchQuickLinks.classList.remove('hidden');
        searchResults.classList.add('hidden');
        searchMinChars.classList.add('hidden');
        searchLoading.classList.add('hidden');
        searchNoResults.classList.add('hidden');
        searchResultsContent.classList.add('hidden');
        searchViewAll.classList.add('hidden');
        searchEventsSection.classList.add('hidden');
        searchArtistsSection.classList.add('hidden');
        searchLocationsSection.classList.add('hidden');
    }

    function showLoading() {
        if (searchQuickLinks) searchQuickLinks.classList.add('hidden');
        searchMinChars.classList.add('hidden');
        searchResults.classList.remove('hidden');
        searchLoading.classList.remove('hidden');
        searchNoResults.classList.add('hidden');
        searchResultsContent.classList.add('hidden');
        searchViewAll.classList.add('hidden');
    }

    function showNoResults() {
        searchLoading.classList.add('hidden');
        searchNoResults.classList.remove('hidden');
        searchResultsContent.classList.add('hidden');
        searchViewAll.classList.add('hidden');
    }

    function showMinChars() {
        if (searchQuickLinks) searchQuickLinks.classList.add('hidden');
        searchResults.classList.add('hidden');
        searchMinChars.classList.remove('hidden');
    }

    function renderResults(data) {
        searchLoading.classList.add('hidden');
        searchNoResults.classList.add('hidden');

        var hasEvents = data.events && data.events.length > 0;
        var hasArtists = data.artists && data.artists.length > 0;
        var hasLocations = data.locations && data.locations.length > 0;

        if (!hasEvents && !hasArtists && !hasLocations) {
            showNoResults();
            return;
        }

        searchResultsContent.classList.remove('hidden');

        if (hasEvents) {
            searchEventsSection.classList.remove('hidden');
            searchEventsCount.textContent = '(' + data.events.length + ')';
            searchEventsList.innerHTML = data.events.slice(0, 4).map(function(event) {
                return '<a href="/bilete/' + event.slug + '" class="flex gap-3.5 p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition-all">' +
                    '<div class="flex-shrink-0 overflow-hidden bg-gray-100 rounded-lg w-14 h-14">' +
                        (event.image ? '<img src="' + escapeHtml(event.image) + '" alt="' + escapeHtml(event.name) + '" class="object-cover w-full h-full">' :
                        '<div class="flex items-center justify-center w-full h-full text-gray-400"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>') +
                    '</div>' +
                    '<div class="flex flex-col justify-center flex-1 min-w-0">' +
                        '<div class="text-sm font-bold text-gray-900 truncate">' + escapeHtml(event.name) + '</div>' +
                        '<div class="flex items-center gap-2 text-xs text-gray-500 mt-0.5">' +
                            '<span>' + escapeHtml(event.date || '') + '</span>' +
                            (event.venue ? '<span class="truncate">' + escapeHtml(event.venue) + '</span>' : '') +
                        '</div>' +
                        (event.price ? '<div class="mt-1 text-sm font-semibold text-emerald-500">de la ' + event.price + ' lei</div>' : '') +
                    '</div>' +
                '</a>';
            }).join('');
        } else {
            searchEventsSection.classList.add('hidden');
        }

        if (hasArtists) {
            searchArtistsSection.classList.remove('hidden');
            searchArtistsCount.textContent = '(' + data.artists.length + ')';
            searchArtistsList.innerHTML = data.artists.slice(0, 4).map(function(artist) {
                return '<a href="/artist/' + artist.slug + '" class="flex gap-3.5 p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition-all">' +
                    '<div class="flex-shrink-0 w-12 h-12 overflow-hidden bg-gray-100 rounded-full">' +
                        (artist.image ? '<img src="' + escapeHtml(artist.image) + '" alt="' + escapeHtml(artist.name) + '" class="object-cover w-full h-full">' :
                        '<div class="flex items-center justify-center w-full h-full text-gray-400"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></div>') +
                    '</div>' +
                    '<div class="flex flex-col justify-center flex-1 min-w-0">' +
                        '<div class="text-sm font-bold text-gray-900 truncate">' + escapeHtml(artist.name) + '</div>' +
                        '<div class="text-xs text-gray-500">' + escapeHtml(artist.genre || artist.type || '') + '</div>' +
                    '</div>' +
                '</a>';
            }).join('');
        } else {
            searchArtistsSection.classList.add('hidden');
        }

        if (hasLocations) {
            searchLocationsSection.classList.remove('hidden');
            searchLocationsCount.textContent = '(' + data.locations.length + ')';
            searchLocationsList.innerHTML = data.locations.slice(0, 4).map(function(location) {
                return '<a href="/locatie/' + location.slug + '" class="flex gap-3.5 p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition-all">' +
                    '<div class="flex-shrink-0 w-12 h-12 overflow-hidden bg-gray-100 rounded-lg">' +
                        (location.image ? '<img src="' + escapeHtml(location.image) + '" alt="' + escapeHtml(location.name) + '" class="object-cover w-full h-full">' :
                        '<div class="flex items-center justify-center w-full h-full text-gray-400"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>') +
                    '</div>' +
                    '<div class="flex flex-col justify-center flex-1 min-w-0">' +
                        '<div class="text-sm font-bold text-gray-900 truncate">' + escapeHtml(location.name) + '</div>' +
                        '<div class="text-xs text-gray-500 truncate">' + escapeHtml(location.address || location.city || '') + '</div>' +
                    '</div>' +
                '</a>';
            }).join('');
        } else {
            searchLocationsSection.classList.add('hidden');
        }

        var totalResults = (data.events ? data.events.length : 0) + (data.artists ? data.artists.length : 0) + (data.locations ? data.locations.length : 0);
        if (totalResults > 4) {
            searchViewAll.classList.remove('hidden');
            searchViewAllLink.href = '/cauta?q=' + encodeURIComponent(currentSearchQuery);
        } else {
            searchViewAll.classList.add('hidden');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function performSearch(query) {
        currentSearchQuery = query;

        if (query.length < SEARCH_MIN_CHARS) {
            if (query.length > 0) {
                showMinChars();
            } else {
                resetSearchUI();
            }
            return;
        }

        showLoading();

        fetch(SEARCH_API_URL + '?q=' + encodeURIComponent(query) + '&limit=5')
            .then(function(response) {
                if (!response.ok) throw new Error('Search failed');
                return response.json();
            })
            .then(function(data) {
                renderResults(data);
            })
            .catch(function(error) {
                console.error('Search error:', error);
                renderMockResults(query);
            });
    }

    function renderMockResults(query) {
        var mockData = {
            events: [
                { name: 'Concert ' + query, slug: 'concert-' + query.toLowerCase().replace(/\s+/g, '-'), date: '15 Ian 2025', venue: 'Sala Palatului', price: 150, image: null },
                { name: 'Festival ' + query + ' 2025', slug: 'festival-' + query.toLowerCase().replace(/\s+/g, '-') + '-2025', date: '20-22 Feb 2025', venue: 'Cluj-Napoca', price: 299, image: null }
            ],
            artists: [
                { name: query.charAt(0).toUpperCase() + query.slice(1), slug: 'artist-demo', genre: 'Pop/Rock', image: null }
            ],
            locations: [
                { name: 'Arena ' + query, slug: 'arena-demo', city: 'București', image: null }
            ]
        };
        if (query.toLowerCase().indexOf('concert') >= 0 || query.toLowerCase().indexOf('music') >= 0) {
            mockData.locations = [];
        }
        renderResults(mockData);
    }

    searchInput.addEventListener('input', function(e) {
        var query = e.target.value.trim();
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, SEARCH_DEBOUNCE_MS);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var query = searchInput.value.trim();
            if (query.length >= SEARCH_MIN_CHARS) {
                window.location.href = '/cauta?q=' + encodeURIComponent(query);
            }
        }
    });

    document.querySelectorAll('.search-suggestion').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var query = btn.dataset.query;
            searchInput.value = query;
            performSearch(query);
        });
    });

    // ==================== MOBILE MENU ====================
    var mobileMenuBtn = document.getElementById('mobileMenuBtn');
    var mobileMenu = document.getElementById('mobileMenu');

    mobileMenuBtn.addEventListener('click', function() {
        var isOpen = !mobileMenu.classList.contains('hidden');
        mobileMenu.classList.toggle('hidden', isOpen);
        mobileMenu.classList.toggle('block', !isOpen);
        var svg = mobileMenuBtn.querySelector('svg');
        if (!isOpen) {
            svg.innerHTML = '<path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />';
        } else {
            svg.innerHTML = '<path d="M11.25 7.75A.75.75 0 0 1 12 7h5.25a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75zM11.25 12a.75.75 0 0 1 .75-.75h5.25a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75zM6.75 15.5a.75.75 0 0 0 0 1.5h10.5a.75.75 0 0 0 0-1.5zM9.5 8a.75.75 0 0 0-1.28-.53l-2 2a.75.75 0 0 0 0 1.06l2 2A.75.75 0 0 0 9.5 12z" fill="currentColor"  class=""></path>';
        }
    });

    document.querySelectorAll('[data-dropdown]').forEach(function(item) {
        var trigger = item.querySelector('.mobile-nav-link');
        var dropdown = item.querySelector('.mobile-dropdown');
        var icon = trigger.querySelector('svg');
        trigger.addEventListener('click', function() {
            var isOpen = !dropdown.classList.contains('hidden');
            dropdown.classList.toggle('hidden', isOpen);
            dropdown.classList.toggle('block', !isOpen);
            icon.classList.toggle('rotate-180', !isOpen);
        });
    });

    // ==================== CART DRAWER ====================
    var cartBtn = document.getElementById('cartBtn');
    var cartDrawer = document.getElementById('cartDrawer');
    var cartOverlay = document.getElementById('cartOverlay');
    var cartCloseBtn = document.getElementById('cartCloseBtn');
    var cartBadge = document.getElementById('cartBadge');
    var cartDrawerCount = document.getElementById('cartDrawerCount');
    var cartEmpty = document.getElementById('cartEmpty');
    var cartItems = document.getElementById('cartItems');
    var cartFooter = document.getElementById('cartFooter');
    var cartSubtotal = document.getElementById('cartSubtotal');
    var CART_STORAGE_KEY = 'ambilet_cart';

    function getCart() {
        try {
            var cart = localStorage.getItem(CART_STORAGE_KEY);
            if (!cart) return { items: [] };
            var parsed = JSON.parse(cart);
            if (Array.isArray(parsed)) return { items: parsed };
            return parsed && parsed.items ? parsed : { items: [] };
        } catch (e) {
            return { items: [] };
        }
    }

    function saveCart(cart) {
        try {
            localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
            updateCartUI();
        } catch (e) {}
    }

    function openCartDrawer() {
        updateCartUI();
        cartOverlay.style.visibility = '';
        cartDrawer.style.visibility = '';
        cartOverlay.classList.remove('opacity-0', 'invisible');
        cartOverlay.classList.add('opacity-100', 'visible');
        cartDrawer.classList.remove('translate-x-full');
        cartDrawer.classList.add('translate-x-0', 'shadow-2xl');
        document.body.style.overflow = 'hidden';
    }

    function closeCartDrawer() {
        cartOverlay.classList.add('opacity-0', 'invisible');
        cartOverlay.classList.remove('opacity-100', 'visible');
        cartDrawer.classList.add('translate-x-full');
        cartDrawer.classList.remove('translate-x-0', 'shadow-2xl');
        document.body.style.overflow = '';
    }

    function calculateItemCommission(item) {
        var basePrice = item.ticketType ? item.ticketType.price : (item.price || 0);
        var commission = item.ticketType ? item.ticketType.commission : null;
        if (commission && commission.type) {
            var amount = 0;
            switch (commission.type) {
                case 'percentage': amount = basePrice * ((commission.rate || 0) / 100); break;
                case 'fixed': amount = commission.fixed || 0; break;
                case 'both': amount = (basePrice * ((commission.rate || 0) / 100)) + (commission.fixed || 0); break;
            }
            return { amount: amount, rate: commission.rate || 0, fixed: commission.fixed || 0, mode: commission.mode || 'included', type: commission.type };
        }
        var eventRate = item.event ? (item.event.commission_rate || 5) : 5;
        var eventMode = item.event ? (item.event.commission_mode || 'included') : 'included';
        return { amount: basePrice * (eventRate / 100), rate: eventRate, fixed: 0, mode: eventMode, type: 'percentage' };
    }

    function updateCartUI() {
        var cart = getCart();
        var items = cart.items || [];
        var itemCount = items.reduce(function(sum, item) { return sum + (item.quantity || 1); }, 0);
        var baseSubtotal = 0;
        var totalCommission = 0;

        items.forEach(function(item) {
            var price = item.ticketType ? item.ticketType.price : (item.price || 0);
            var qty = item.quantity || 1;
            baseSubtotal += price * qty;
            var commission = calculateItemCommission(item);
            if (commission.mode === 'added_on_top') totalCommission += commission.amount * qty;
        });

        var subtotal = baseSubtotal + totalCommission;

        if (itemCount > 0) {
            cartBadge.textContent = itemCount > 99 ? '99+' : itemCount;
            cartBadge.classList.remove('hidden');
            cartBadge.classList.add('flex');
        } else {
            cartBadge.classList.add('hidden');
            cartBadge.classList.remove('flex');
        }

        if (itemCount > 0) {
            cartDrawerCount.textContent = itemCount;
            cartDrawerCount.classList.remove('hidden');
        } else {
            cartDrawerCount.classList.add('hidden');
        }

        cartSubtotal.textContent = subtotal.toLocaleString('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' lei';

        if (items.length === 0) {
            cartEmpty.classList.remove('hidden');
            cartItems.classList.add('hidden');
            cartFooter.classList.add('hidden');
        } else {
            cartEmpty.classList.add('hidden');
            cartItems.classList.remove('hidden');
            cartFooter.classList.remove('hidden');
            renderCartItems(items);
        }
    }

    function renderCartItems(items) {
        cartItems.innerHTML = items.map(function(item, index) {
            var image = item.event ? (item.event.image || '') : (item.image || '');
            var ticketName = item.ticketType ? (item.ticketType.name || 'Bilet') : (item.name || 'Bilet');
            var eventName = item.event ? (item.event.title || item.event.name || '') : (item.eventName || '');
            var venueName = item.event ? (item.event.venue ? (item.event.venue.name || item.event.venue) : '') : '';
            var cityName = item.event ? (item.event.city ? (item.event.city.name || item.event.city) : '') : '';
            var locationText = [venueName, cityName].filter(Boolean).join(', ');
            var basePrice = item.ticketType ? (item.ticketType.price || 0) : (item.price || 0);
            var quantity = item.quantity || 1;
            var itemKey = item.key || index;
            var itemSeats = item.seats || [];
            var hasSeats = itemSeats.length > 0 || (item.seat_uids && item.seat_uids.length > 0);
            var eventSlug = item.event ? (item.event.slug || '') : '';
            var commission = calculateItemCommission(item);
            var displayPrice = basePrice;
            if (commission.mode === 'added_on_top') displayPrice = basePrice + commission.amount;

            var qtyHtml;
            if (hasSeats) {
                qtyHtml = '<span class="text-sm font-semibold">' + quantity + ' loc' + (quantity > 1 ? 'uri' : '') + '</span>' +
                    (eventSlug ? ' <a href="/bilete/' + escapeHtml(eventSlug) + '" class="text-xs font-semibold underline text-primary">Modifică</a>' : '');
            } else {
                qtyHtml = '<button type="button" class="flex items-center justify-center w-6 h-6 transition-colors bg-gray-100 rounded hover:bg-gray-200 cart-qty-btn" data-action="decrease" data-index="' + index + '"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></button>' +
                    '<span class="w-6 text-sm font-semibold text-center">' + quantity + '</span>' +
                    '<button type="button" class="flex items-center justify-center w-6 h-6 transition-colors bg-gray-100 rounded hover:bg-gray-200 cart-qty-btn" data-action="increase" data-index="' + index + '"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button>';
            }

            return '<div class="p-3 bg-white border border-gray-200 rounded-xl" data-cart-item="' + index + '" data-item-key="' + escapeHtml(String(itemKey)) + '">' +
                '<div class="flex gap-3">' +
                    '<div class="flex-shrink-0 w-16 h-16 overflow-hidden bg-gray-100 rounded-lg">' +
                        (image ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(ticketName) + '" class="object-cover w-full h-full">' :
                        '<div class="flex items-center justify-center w-full h-full text-gray-400"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></div>') +
                    '</div>' +
                    '<div class="flex-1 min-w-0">' +
                        '<h4 class="text-sm font-bold text-gray-900 truncate">' + escapeHtml(ticketName) + '</h4>' +
                        '<p class="text-xs text-gray-500 truncate">' + escapeHtml(eventName) + '</p>' +
                        (locationText ? '<p class="text-xs text-gray-400 truncate">' + escapeHtml(locationText) + '</p>' : '') +
                        '<div class="flex items-center justify-between mt-2">' +
                            '<div class="flex items-center gap-2">' + qtyHtml + '</div>' +
                            '<span class="font-bold text-primary">' + (displayPrice * quantity).toLocaleString('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' lei</span>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="self-start p-1 text-gray-400 transition-colors hover:text-red-500 cart-remove-btn" data-index="' + index + '" aria-label="Șterge"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>' +
                '</div>' +
            '</div>';
        }).join('');

        cartItems.querySelectorAll('.cart-qty-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                updateItemQuantity(parseInt(btn.dataset.index), btn.dataset.action);
            });
        });
        cartItems.querySelectorAll('.cart-remove-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                removeCartItem(parseInt(btn.dataset.index));
            });
        });
    }

    function updateItemQuantity(index, action) {
        var cart = getCart();
        var items = cart.items || [];
        if (index < 0 || index >= items.length) return;
        var item = items[index];
        var currentQty = item.quantity || 1;
        var newQty = action === 'increase' ? currentQty + 1 : currentQty - 1;

        // When quantity drops to 0, go through AmbiletCart.removeItem so seats get released
        if (newQty <= 0 && window.AmbiletCart && typeof window.AmbiletCart.removeItem === 'function' && item.key) {
            window.AmbiletCart.removeItem(item.key);
            updateCartUI();
            return;
        }

        if (newQty <= 0) {
            items.splice(index, 1);
        } else {
            item.quantity = newQty;
        }
        cart.items = items;
        cart.updatedAt = new Date().toISOString();
        saveCart(cart);
    }

    function removeCartItem(index) {
        var cart = getCart();
        var items = cart.items || [];
        if (index < 0 || index >= items.length) return;
        var item = items[index];

        // Delegate to AmbiletCart.removeItem so seats are released via API
        if (window.AmbiletCart && typeof window.AmbiletCart.removeItem === 'function' && item && item.key) {
            window.AmbiletCart.removeItem(item.key);
            updateCartUI();
            return;
        }

        items.splice(index, 1);
        cart.items = items;
        cart.updatedAt = new Date().toISOString();
        saveCart(cart);
    }

    cartBtn.addEventListener('click', openCartDrawer);
    cartCloseBtn.addEventListener('click', closeCartDrawer);
    cartOverlay.addEventListener('click', closeCartDrawer);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !cartOverlay.classList.contains('invisible')) closeCartDrawer();
    });

    // Defer initial cart UI update to avoid forced reflow during script init
    requestAnimationFrame(function() { updateCartUI(); });

    window.addEventListener('ambilet:cart:update', function() { updateCartUI(); });
    window.addEventListener('ambilet:cart:expired', function() { closeCartDrawer(); updateCartUI(); });
    window.addEventListener('ambilet:cart:clear', function() { updateCartUI(); });

    if (window.AmbiletCart) {
        window.AmbiletCart.openDrawer = openCartDrawer;
        window.AmbiletCart.closeDrawer = closeCartDrawer;
        window.AmbiletCart.updateUI = updateCartUI;
    } else {
        window.AmbiletCart = { openDrawer: openCartDrawer, closeDrawer: closeCartDrawer, updateUI: updateCartUI, getCart: getCart };
    }
    window.openCartDrawer = openCartDrawer;
    window.closeCartDrawer = closeCartDrawer;

    // ==================== HEADER CART TIMER BAR ====================
    (function initHeaderTimerBar() {
        var headerTopBar = document.getElementById('headerTopBar');
        var headerTimerBar = document.getElementById('headerTimerBar');
        var countdownEl = document.getElementById('headerTimerCountdown');
        if (!headerTopBar || !headerTimerBar || !countdownEl) return;

        var currentPath = window.location.pathname;
        if (currentPath === '/cos' || currentPath === '/finalizare') return;

        var timerInterval = null;
        var isRed = false;

        function showTimerBar() {
            headerTopBar.classList.add('hidden');
            headerTimerBar.classList.remove('hidden');
        }

        function showDefaultBar() {
            headerTimerBar.classList.add('hidden');
            if (!cachedIsScrolled && !isTransparentMode) headerTopBar.classList.remove('hidden');
        }

        function updateHeaderTimer() {
            var cart = [];
            try {
                var stored = localStorage.getItem('ambilet_cart');
                if (stored) { var parsed = JSON.parse(stored); cart = parsed.items || parsed || []; }
            } catch (e) { cart = []; }

            var savedEndTime = localStorage.getItem('cart_end_time');

            if (cart && cart.length > 0 && savedEndTime) {
                var endTime = parseInt(savedEndTime);
                var remaining = Math.max(0, endTime - Date.now());
                if (remaining > 0) {
                    showTimerBar();
                    var minutes = Math.floor(remaining / 60000);
                    var seconds = Math.floor((remaining % 60000) / 1000);
                    countdownEl.textContent = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                    if (remaining < 5 * 60 * 1000 && !isRed) {
                        isRed = true;
                        headerTimerBar.classList.remove('bg-warning/10', 'border-warning/20');
                        headerTimerBar.classList.add('bg-primary', 'border-primary');
                        countdownEl.classList.remove('text-warning');
                        countdownEl.classList.add('text-white');
                        var textSpan = document.getElementById('headerTimerText');
                        if (textSpan) { textSpan.classList.remove('text-secondary'); textSpan.classList.add('text-white/90'); }
                        var icon = document.getElementById('headerTimerIcon');
                        if (icon) { icon.classList.remove('text-warning'); icon.classList.add('text-white'); }
                        var link = headerTimerBar.querySelector('a.text-primary');
                        if (link) { link.classList.remove('text-primary'); link.classList.add('text-white'); }
                    }
                } else {
                    showDefaultBar();
                    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
                }
            } else {
                showDefaultBar();
                if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
            }
        }

        setTimeout(function() {
            updateHeaderTimer();
            var savedEndTime = localStorage.getItem('cart_end_time');
            var storedCart = localStorage.getItem('ambilet_cart');
            if (storedCart && savedEndTime) {
                try {
                    var parsed = JSON.parse(storedCart);
                    var cart = parsed.items || parsed || [];
                    if (cart && cart.length > 0) timerInterval = setInterval(updateHeaderTimer, 1000);
                } catch (e) {}
            }
        }, 100);

        window.addEventListener('ambilet:cart:update', function() {
            updateHeaderTimer();
            if (!timerInterval) {
                var storedCart = localStorage.getItem('ambilet_cart');
                if (storedCart) {
                    try {
                        var parsed = JSON.parse(storedCart);
                        var cart = parsed.items || parsed || [];
                        if (cart && cart.length > 0) timerInterval = setInterval(updateHeaderTimer, 1000);
                    } catch (e) {}
                }
            }
        });

        window.addEventListener('ambilet:cart:clear', function() {
            showDefaultBar();
            isRed = false;
            headerTimerBar.classList.remove('bg-primary', 'border-primary');
            headerTimerBar.classList.add('bg-warning/10', 'border-warning/20');
            countdownEl.classList.remove('text-white');
            countdownEl.classList.add('text-warning');
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        });

        window.addEventListener('ambilet:cart:expired', function() {
            showDefaultBar();
            isRed = false;
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        });
    })();
})();
