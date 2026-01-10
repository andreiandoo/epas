<?php
/**
 * Events Calendar Page
 * URL: /calendar
 */
require_once 'includes/config.php';

$pageTitle = 'Calendar Evenimente — ' . SITE_NAME;
$pageDescription = 'Planifică-ți experiențele. Vezi toate evenimentele într-un singur loc.';
$transparentHeader = true;
include 'includes/head.php';
include 'includes/header.php'; ?>

    <!-- Page Hero -->
    <section class="relative px-6 pt-40 pb-8 overflow-hidden bg-gradient-to-br from-slate-800 to-slate-900">
        <div class="absolute -top-24 -right-24 w-[400px] h-[400px] bg-[radial-gradient(circle,rgba(165,28,48,0.3)_0%,transparent_70%)] rounded-full"></div>
        <div class="relative z-10 flex flex-col items-center justify-between gap-6 px-6 mx-auto max-w-7xl md:flex-row">
            <div>
                <h1 class="mb-2 text-3xl font-extrabold text-white md:text-4xl">Calendar Evenimente</h1>
                <p class="text-base text-white/70">Planifică-ți experiențele. Vezi toate evenimentele într-un singur loc.</p>
            </div>
            <div class="flex gap-2 bg-white/10 p-1.5 rounded-xl" id="viewToggle">
                <button id="monthViewBtn" data-view="month" class="flex items-center gap-2 px-5 py-2.5 bg-white text-secondary rounded-lg text-sm font-semibold transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Lună
                </button>
                <button id="listViewBtn" data-view="list" class="flex items-center gap-2 px-5 py-2.5 bg-transparent text-white/60 rounded-lg text-sm font-semibold hover:text-white transition-all">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    Listă
                </button>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="px-6 py-8 mx-auto max-w-7xl">
        <!-- Calendar Controls -->
        <div class="flex flex-col items-center justify-between gap-4 mb-8 md:flex-row">
            <div class="flex items-center gap-4">
                <button id="prevMonthBtn" class="flex items-center justify-center transition-all bg-white border border-gray-200 w-11 h-11 rounded-xl text-muted hover:border-primary hover:text-primary">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                </button>
                <h2 id="monthTitle" class="text-2xl font-bold text-secondary">Ianuarie 2025</h2>
                <button id="nextMonthBtn" class="flex items-center justify-center transition-all bg-white border border-gray-200 w-11 h-11 rounded-xl text-muted hover:border-primary hover:text-primary">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
                <button id="todayBtn" class="px-5 py-2.5 bg-gray-100 rounded-xl text-gray-600 text-sm font-semibold hover:bg-gray-200 transition-all">Astăzi</button>
            </div>
            <div class="flex flex-col w-full gap-3 sm:flex-row md:w-auto">
                <select id="categoryFilter" class="px-4 py-3 pr-10 bg-white border border-gray-200 rounded-xl text-sm font-medium text-secondary cursor-pointer appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_12px_center] focus:outline-none focus:border-primary">
                    <option value="">Toate categoriile</option>
                </select>
                <select id="cityFilter" class="px-4 py-3 pr-10 bg-white border border-gray-200 rounded-xl text-sm font-medium text-secondary cursor-pointer appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_12px_center] focus:outline-none focus:border-primary">
                    <option value="">Toate orașele</option>
                </select>
            </div>
        </div>

        <!-- Calendar Grid (Month View) -->
        <div id="calendarView" class="mb-12 overflow-hidden bg-white border border-gray-200 rounded-2xl">
            <!-- Header -->
            <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Luni</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Marți</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Miercuri</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Joi</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Vineri</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Sâmbătă</div>
                <div class="p-4 text-xs font-bold tracking-wider text-center uppercase text-muted">Duminică</div>
            </div>
            <!-- Calendar Body -->
            <div class="grid grid-cols-7" id="calendarGrid">
                <!-- Calendar days will be populated by JavaScript -->
            </div>
            <!-- Legend -->
            <div class="flex flex-wrap items-center justify-center gap-6 p-5 border-t border-gray-200" id="calendarLegend">
                <!-- Legend will be populated by JavaScript -->
            </div>
        </div>

        <!-- List View (hidden by default) -->
        <div id="listView" class="hidden mb-12 space-y-4">
            <!-- Events list will be populated by JavaScript -->
        </div>

        <!-- Featured This Month -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-secondary">Recomandate luna aceasta</h2>
                <a href="/evenimente" class="flex items-center gap-1.5 text-primary text-sm font-semibold">
                    Vezi toate
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4" id="featuredEvents">
                <!-- Events will be populated by JavaScript -->
            </div>
        </section>

    </main>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>

    <script>
    var CalendarPage = {
        currentYear: new Date().getFullYear(),
        currentMonth: new Date().getMonth(),
        currentView: 'month',
        events: {},
        allEvents: [],
        categories: [],
        cities: [],
        selectedCategory: '',
        selectedCity: '',

        monthNames: ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'],
        monthNamesShort: ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],

        typeColors: {
            'Concert': 'bg-red-100 text-red-800',
            'Concerte': 'bg-red-100 text-red-800',
            'Festival': 'bg-blue-100 text-blue-800',
            'Festivaluri': 'bg-blue-100 text-blue-800',
            'Teatru': 'bg-purple-100 text-purple-800',
            'Opera': 'bg-purple-100 text-purple-800',
            'Stand-up': 'bg-amber-100 text-amber-800',
            'Comedy': 'bg-amber-100 text-amber-800',
            'Sport': 'bg-emerald-100 text-emerald-800',
            'default': 'bg-gray-100 text-gray-800'
        },

        async init() {
            this.bindEvents();
            await this.loadFilters();
            await this.loadEvents();
            this.renderLegend();
            this.updateMonthTitle();
        },

        bindEvents() {
            var self = this;

            // Month navigation
            document.getElementById('prevMonthBtn').addEventListener('click', function() { self.prevMonth(); });
            document.getElementById('nextMonthBtn').addEventListener('click', function() { self.nextMonth(); });
            document.getElementById('todayBtn').addEventListener('click', function() { self.goToToday(); });

            // View toggle
            document.getElementById('monthViewBtn').addEventListener('click', function() { self.setView('month'); });
            document.getElementById('listViewBtn').addEventListener('click', function() { self.setView('list'); });

            // Filters
            document.getElementById('categoryFilter').addEventListener('change', function(e) {
                self.selectedCategory = e.target.value;
                self.loadEvents();
            });
            document.getElementById('cityFilter').addEventListener('change', function(e) {
                self.selectedCity = e.target.value;
                self.loadEvents();
            });
        },

        async loadFilters() {
            try {
                // Load categories
                var catResponse = await AmbiletAPI.get('/event-categories');
                if (catResponse.success && catResponse.data && catResponse.data.categories) {
                    this.categories = catResponse.data.categories;
                    this.renderCategoryFilter();
                }

                // Load cities
                var cityResponse = await AmbiletAPI.get('/events/cities');
                if (cityResponse.success && cityResponse.data && cityResponse.data.cities) {
                    this.cities = cityResponse.data.cities;
                    this.renderCityFilter();
                }
            } catch (error) {
                console.error('Failed to load filters:', error);
            }
        },

        renderCategoryFilter() {
            var select = document.getElementById('categoryFilter');
            var html = '<option value="">Toate categoriile</option>';
            this.categories.forEach(function(cat) {
                html += '<option value="' + CalendarPage.escapeHtml(cat.name) + '">' + CalendarPage.escapeHtml(cat.name) + ' (' + cat.event_count + ')</option>';
            });
            select.innerHTML = html;
        },

        renderCityFilter() {
            var select = document.getElementById('cityFilter');
            var html = '<option value="">Toate orașele</option>';
            this.cities.forEach(function(city) {
                html += '<option value="' + CalendarPage.escapeHtml(city.name) + '">' + CalendarPage.escapeHtml(city.name) + ' (' + city.event_count + ')</option>';
            });
            select.innerHTML = html;
        },

        async loadEvents() {
            try {
                var firstDay = new Date(this.currentYear, this.currentMonth, 1);
                var lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);

                var params = new URLSearchParams({
                    from_date: firstDay.toISOString().split('T')[0],
                    to_date: lastDay.toISOString().split('T')[0],
                    per_page: 100
                });

                if (this.selectedCategory) {
                    params.set('category', this.selectedCategory);
                }
                if (this.selectedCity) {
                    params.set('city', this.selectedCity);
                }

                var response = await AmbiletAPI.get('/events?' + params.toString());

                if (response.success && response.data) {
                    this.allEvents = response.data;
                    this.events = {};

                    // Group events by date
                    response.data.forEach(function(event) {
                        // API returns: starts_at or event_date; name; venue (string) and city
                        var dateStr = event.starts_at ? event.starts_at.split('T')[0] : (event.event_date || null);
                        if (dateStr) {
                            if (!CalendarPage.events[dateStr]) {
                                CalendarPage.events[dateStr] = [];
                            }
                            CalendarPage.events[dateStr].push({
                                id: event.id,
                                name: event.name,
                                slug: event.slug,
                                type: event.category || 'Eveniment',
                                venue: (typeof event.venue === 'string' ? event.venue : event.venue?.name) || '',
                                city: event.city || '',
                                price_from: event.price_from
                            });
                        }
                    });

                    this.renderCalendar();
                    this.loadFeaturedEvents();

                    if (this.currentView === 'list') {
                        this.renderListView();
                    }
                }
            } catch (error) {
                console.error('Failed to load events:', error);
                this.renderCalendar();
            }
        },

        async loadFeaturedEvents() {
            try {
                var response = await AmbiletAPI.get('/events/featured?limit=4');
                if (response.success && response.data && response.data.events) {
                    this.renderFeaturedEvents(response.data.events);
                } else if (this.allEvents.length > 0) {
                    this.renderFeaturedEvents(this.allEvents.slice(0, 4));
                }
            } catch (error) {
                console.error('Failed to load featured events:', error);
                if (this.allEvents.length > 0) {
                    this.renderFeaturedEvents(this.allEvents.slice(0, 4));
                }
            }
        },

        renderCalendar() {
            var grid = document.getElementById('calendarGrid');
            if (!grid) return;

            var year = this.currentYear;
            var month = this.currentMonth;
            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var startDay = (firstDay.getDay() + 6) % 7;
            var today = new Date();

            var html = '';

            // Previous month days
            var prevMonthDays = new Date(year, month, 0).getDate();
            for (var i = startDay - 1; i >= 0; i--) {
                html += '<div class="min-h-[100px] md:min-h-[120px] p-2 border-r border-b border-gray-200 bg-gray-50">' +
                    '<div class="flex items-center justify-center w-8 h-8 mb-1 text-sm font-semibold text-gray-300 rounded-lg">' + (prevMonthDays - i) + '</div>' +
                '</div>';
            }

            // Current month days
            for (var day = 1; day <= lastDay.getDate(); day++) {
                var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                var isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
                var dayEvents = this.events[dateStr] || [];

                var dayClass = isToday ? 'bg-red-50' : 'bg-white hover:bg-gray-50';
                var numberClass = isToday ? 'bg-gradient-to-br from-primary to-primary-light text-white' : '';

                html += '<div class="' + dayClass + ' min-h-[100px] md:min-h-[120px] p-2 border-r border-b border-gray-200 cursor-pointer transition-colors">' +
                    '<div class="w-8 h-8 flex items-center justify-center text-sm font-semibold text-secondary rounded-lg mb-1.5 ' + numberClass + '">' + day + '</div>' +
                    '<div class="flex flex-col gap-1">';

                dayEvents.slice(0, 2).forEach(function(event) {
                    var colorClass = CalendarPage.typeColors[event.type] || CalendarPage.typeColors['default'];
                    html += '<a href="/bilete/' + CalendarPage.escapeHtml(event.slug) + '" class="block px-2 py-1 rounded-md text-[11px] font-semibold whitespace-nowrap overflow-hidden text-ellipsis ' + colorClass + ' hover:opacity-80">' + CalendarPage.escapeHtml(event.name) + '</a>';
                });

                if (dayEvents.length > 2) {
                    html += '<div class="text-[11px] font-semibold text-primary px-2 cursor-pointer" onclick="CalendarPage.showDayEvents(\'' + dateStr + '\')">+' + (dayEvents.length - 2) + ' mai multe</div>';
                }

                html += '</div></div>';
            }

            // Next month days
            var remainingDays = 42 - (startDay + lastDay.getDate());
            for (var j = 1; j <= remainingDays; j++) {
                html += '<div class="min-h-[100px] md:min-h-[120px] p-2 border-r border-b border-gray-200 bg-gray-50">' +
                    '<div class="flex items-center justify-center w-8 h-8 mb-1 text-sm font-semibold text-gray-300 rounded-lg">' + j + '</div>' +
                '</div>';
            }

            grid.innerHTML = html;
        },

        renderListView() {
            var container = document.getElementById('listView');
            if (!container) return;

            if (this.allEvents.length === 0) {
                container.innerHTML = '<div class="p-8 text-center bg-white border border-gray-200 rounded-2xl"><p class="text-gray-500">Nu există evenimente în această perioadă</p></div>';
                return;
            }

            var html = '';
            this.allEvents.forEach(function(event) {
                // API returns: starts_at or event_date; name; venue (string) and city; price_from
                var eventDate = event.starts_at || event.event_date;
                var date = new Date(eventDate);
                var dayNum = date.getDate();
                var monthName = CalendarPage.monthNamesShort[date.getMonth()];
                var colorClass = CalendarPage.typeColors[event.category] || CalendarPage.typeColors['default'];
                var eventVenue = (typeof event.venue === 'string' ? event.venue : event.venue?.name) || '';

                html += '<a href="/bilete/' + CalendarPage.escapeHtml(event.slug) + '" class="flex items-center gap-4 p-4 bg-white border border-gray-200 rounded-2xl hover:border-primary hover:shadow-md transition-all">' +
                    '<div class="flex flex-col items-center justify-center w-16 h-16 text-center bg-gradient-to-br from-primary to-primary-light rounded-xl">' +
                        '<div class="text-2xl font-bold text-white">' + dayNum + '</div>' +
                        '<div class="text-[10px] font-semibold text-white/80 uppercase">' + monthName + '</div>' +
                    '</div>' +
                    '<div class="flex-1">' +
                        '<span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-semibold mb-1 ' + colorClass + '">' + CalendarPage.escapeHtml(event.category || 'Eveniment') + '</span>' +
                        '<h3 class="text-base font-bold text-secondary">' + CalendarPage.escapeHtml(event.name) + '</h3>' +
                        '<p class="text-sm text-muted">' + CalendarPage.escapeHtml(eventVenue) + (event.city ? ', ' + CalendarPage.escapeHtml(event.city) : '') + '</p>' +
                    '</div>' +
                    '<div class="text-right">' +
                        (event.price_from ? '<div class="text-lg font-bold text-primary">de la ' + event.price_from + ' lei</div>' : '') +
                    '</div>' +
                '</a>';
            });

            container.innerHTML = html;
        },

        renderFeaturedEvents(events) {
            var container = document.getElementById('featuredEvents');
            if (!container || !events.length) return;

            var html = events.map(function(event) {
                // API returns: starts_at or event_date; name; venue (string) and city; image_url or image
                var eventDate = event.starts_at || event.event_date;
                var date = new Date(eventDate);
                var dayNum = date.getDate();
                var monthName = CalendarPage.monthNamesShort[date.getMonth()];
                var imageUrl = event.image_url || event.image || 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=200&fit=crop';
                var eventVenue = (typeof event.venue === 'string' ? event.venue : event.venue?.name) || '';

                return '<a href="/bilete/' + CalendarPage.escapeHtml(event.slug) + '" class="overflow-hidden transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-lg group">' +
                    '<div class="relative bg-center bg-cover h-36" style="background-image: url(\'' + CalendarPage.escapeHtml(imageUrl) + '\')">' +
                        '<div class="absolute px-3 py-2 text-center bg-white shadow-md top-3 left-3 rounded-xl">' +
                            '<div class="text-xl font-extrabold leading-none text-primary">' + dayNum + '</div>' +
                            '<div class="text-[10px] font-semibold text-muted uppercase">' + monthName + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="p-4">' +
                        '<span class="inline-block px-2.5 py-1 bg-red-50 rounded-md text-[11px] font-semibold text-primary mb-2">' + CalendarPage.escapeHtml(event.category || 'Eveniment') + '</span>' +
                        '<h3 class="mb-2 text-base font-bold text-secondary line-clamp-2">' + CalendarPage.escapeHtml(event.name) + '</h3>' +
                        '<p class="flex items-center gap-1.5 text-[13px] text-muted">' +
                            '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                                '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>' +
                                '<circle cx="12" cy="10" r="3"/>' +
                            '</svg>' +
                            CalendarPage.escapeHtml(eventVenue + (event.city ? ', ' + event.city : '')) +
                        '</p>' +
                    '</div>' +
                '</a>';
            }).join('');

            container.innerHTML = html;
        },

        renderLegend() {
            var container = document.getElementById('calendarLegend');
            if (!container) return;

            var html = '<div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 bg-red-100 rounded"></div>Concerte</div>' +
                '<div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 bg-blue-100 rounded"></div>Festivaluri</div>' +
                '<div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 bg-purple-100 rounded"></div>Teatru & Operă</div>' +
                '<div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 rounded bg-amber-100"></div>Stand-up</div>' +
                '<div class="flex items-center gap-2 text-sm text-muted"><div class="w-3 h-3 rounded bg-emerald-100"></div>Sport</div>';
            container.innerHTML = html;
        },

        updateMonthTitle() {
            var title = this.monthNames[this.currentMonth] + ' ' + this.currentYear;
            document.getElementById('monthTitle').textContent = title;
        },

        prevMonth() {
            this.currentMonth--;
            if (this.currentMonth < 0) {
                this.currentMonth = 11;
                this.currentYear--;
            }
            this.updateMonthTitle();
            this.loadEvents();
        },

        nextMonth() {
            this.currentMonth++;
            if (this.currentMonth > 11) {
                this.currentMonth = 0;
                this.currentYear++;
            }
            this.updateMonthTitle();
            this.loadEvents();
        },

        goToToday() {
            var today = new Date();
            this.currentYear = today.getFullYear();
            this.currentMonth = today.getMonth();
            this.updateMonthTitle();
            this.loadEvents();
        },

        setView(view) {
            this.currentView = view;
            var monthBtn = document.getElementById('monthViewBtn');
            var listBtn = document.getElementById('listViewBtn');
            var calendarView = document.getElementById('calendarView');
            var listView = document.getElementById('listView');

            if (view === 'month') {
                monthBtn.className = 'flex items-center gap-2 px-5 py-2.5 bg-white text-secondary rounded-lg text-sm font-semibold transition-all';
                listBtn.className = 'flex items-center gap-2 px-5 py-2.5 bg-transparent text-white/60 rounded-lg text-sm font-semibold hover:text-white transition-all';
                calendarView.classList.remove('hidden');
                listView.classList.add('hidden');
            } else {
                listBtn.className = 'flex items-center gap-2 px-5 py-2.5 bg-white text-secondary rounded-lg text-sm font-semibold transition-all';
                monthBtn.className = 'flex items-center gap-2 px-5 py-2.5 bg-transparent text-white/60 rounded-lg text-sm font-semibold hover:text-white transition-all';
                calendarView.classList.add('hidden');
                listView.classList.remove('hidden');
                this.renderListView();
            }
        },

        showDayEvents(dateStr) {
            var dayEvents = this.events[dateStr] || [];
            if (dayEvents.length === 0) return;

            // Navigate to first event or show modal with events
            if (dayEvents.length === 1) {
                window.location.href = '/bilete/' + dayEvents[0].slug;
            } else {
                // For multiple events, could show a modal - for now just go to events page with date filter
                window.location.href = '/evenimente?date=' + dateStr;
            }
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', function() { CalendarPage.init(); });
    </script>