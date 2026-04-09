/**
 * Leisure Venue page — calendar + ticket selector + parking form.
 * Depends on: AmbiletAPI, AmbiletCart (from scripts.php)
 */
(function () {
    'use strict';

    const DATA = window.__LEISURE_VENUE__;
    if (!DATA) return;

    const SLUG = DATA.slug;
    const EVENT = DATA.event;
    const CONFIG = DATA.venue_config || {};
    const MAX_ADVANCE = DATA.max_advance_days || 90;
    const PRICING_RULES = CONFIG.pricing_rules || [];
    const CLOSED_DATES = CONFIG.closed_dates || [];
    const SCHEDULE = CONFIG.operating_schedule || {};

    // Months in Romanian
    const MONTHS_RO = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie',
        'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];

    // State
    let currentMonth = new Date();
    currentMonth.setDate(1);
    let selectedDate = null;
    let monthCache = {};     // { 'YYYY-MM': { dates: { 'YYYY-MM-DD': { status, min_price } } } }
    let dateTickets = null;  // ticket types for selected date
    let quantities = {};     // { ticketTypeId: qty }
    let vehicleInfo = {};    // { ticketTypeId: { license_plate } }

    // DOM refs
    const $calDays = document.getElementById('cal-days');
    const $calLabel = document.getElementById('cal-month-label');
    const $calPrev = document.getElementById('cal-prev');
    const $calNext = document.getElementById('cal-next');
    const $noDate = document.getElementById('no-date-placeholder');
    const $loading = document.getElementById('tickets-loading');
    const $groups = document.getElementById('ticket-groups');
    const $summary = document.getElementById('cart-summary');
    const $summaryItems = document.getElementById('cart-items-summary');
    const $totalEl = document.getElementById('cart-total');
    const $addBtn = document.getElementById('add-to-cart-btn');
    const $dateBar = document.getElementById('selected-date-bar');
    const $dateLabel = document.getElementById('selected-date-label');
    const $mobileBar = document.getElementById('mobile-bottom-bar');
    const $mobileTotalEl = document.getElementById('mobile-total-amount');
    const $mobileAddBtn = document.getElementById('mobile-add-to-cart-btn');

    // ========== Calendar ==========

    function renderCalendar() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        $calLabel.textContent = MONTHS_RO[month] + ' ' + year;

        const firstDay = new Date(year, month, 1);
        let startDow = firstDay.getDay(); // 0=Sun
        startDow = startDow === 0 ? 6 : startDow - 1; // Convert to Mon=0

        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + MAX_ADVANCE);

        const monthKey = year + '-' + String(month + 1).padStart(2, '0');
        const monthData = monthCache[monthKey]?.dates || {};

        let html = '';

        // Empty cells before first day
        for (let i = 0; i < startDow; i++) {
            html += '<div class="p-2"></div>';
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            const dateObj = new Date(year, month, d);
            const isPast = dateObj < today;
            const isFuture = dateObj > maxDate;
            const isSelected = selectedDate === dateStr;
            const dayData = monthData[dateStr] || {};
            const status = dayData.status || (isPast ? 'past' : 'available');

            let cls = 'relative flex items-center justify-center w-full aspect-square rounded-lg text-sm font-medium transition cursor-pointer ';
            let dotColor = '';
            let clickable = true;

            if (isSelected) {
                cls += 'bg-primary text-white ring-2 ring-primary ring-offset-2';
            } else if (isPast || status === 'past') {
                cls += 'text-gray-300 cursor-default';
                clickable = false;
            } else if (isFuture) {
                cls += 'text-gray-300 cursor-default';
                clickable = false;
            } else if (status === 'closed') {
                cls += 'text-gray-400 bg-gray-50 cursor-default';
                dotColor = 'bg-gray-300';
                clickable = false;
            } else if (status === 'sold_out') {
                cls += 'text-red-400 bg-red-50 cursor-default';
                dotColor = 'bg-red-500';
                clickable = false;
            } else if (status === 'limited') {
                cls += 'text-secondary hover:bg-amber-50';
                dotColor = 'bg-amber-500';
            } else {
                cls += 'text-secondary hover:bg-green-50';
                dotColor = 'bg-green-500';
            }

            html += '<div class="' + cls + '"' +
                (clickable ? ' data-date="' + dateStr + '"' : '') +
                '>' + d;
            if (dotColor && !isSelected) {
                html += '<span class="absolute bottom-1 w-1.5 h-1.5 rounded-full ' + dotColor + '"></span>';
            }
            html += '</div>';
        }

        $calDays.innerHTML = html;

        // Bind click handlers
        $calDays.querySelectorAll('[data-date]').forEach(el => {
            el.addEventListener('click', () => selectDate(el.dataset.date));
        });
    }

    async function fetchMonthAvailability(monthStr) {
        if (monthCache[monthStr]) return monthCache[monthStr];

        try {
            const resp = await AmbiletAPI.get('event.dateAvailability', { slug: SLUG, month: monthStr });
            if (resp && resp.dates) {
                monthCache[monthStr] = resp;
                return resp;
            }
        } catch (e) {
            console.warn('Failed to fetch month availability:', e);
        }
        return { dates: {} };
    }

    async function loadMonth() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        const monthStr = year + '-' + String(month + 1).padStart(2, '0');

        // Render immediately with cached data (or no data)
        renderCalendar();

        // Fetch and re-render
        await fetchMonthAvailability(monthStr);
        renderCalendar();

        // Prefetch next month
        const nextMonth = new Date(year, month + 1, 1);
        const nextStr = nextMonth.getFullYear() + '-' + String(nextMonth.getMonth() + 1).padStart(2, '0');
        fetchMonthAvailability(nextStr);
    }

    $calPrev.addEventListener('click', () => {
        currentMonth.setMonth(currentMonth.getMonth() - 1);
        loadMonth();
    });

    $calNext.addEventListener('click', () => {
        currentMonth.setMonth(currentMonth.getMonth() + 1);
        loadMonth();
    });

    // ========== Date Selection & Ticket Loading ==========

    async function selectDate(dateStr) {
        selectedDate = dateStr;
        quantities = {};
        vehicleInfo = {};
        dateTickets = null;

        // Update calendar highlight
        renderCalendar();

        // Show date bar
        const d = new Date(dateStr + 'T00:00:00');
        const dayNames = ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă'];
        $dateLabel.textContent = dayNames[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS_RO[d.getMonth()] + ' ' + d.getFullYear();
        $dateBar.classList.remove('hidden');

        // Show loading
        $noDate.classList.add('hidden');
        $groups.classList.add('hidden');
        $summary.classList.add('hidden');
        $mobileBar.classList.add('hidden');
        $loading.classList.remove('hidden');

        try {
            const resp = await AmbiletAPI.get('event.dateAvailability', { slug: SLUG, date: dateStr });
            if (!resp || !resp.is_open) {
                $loading.classList.add('hidden');
                $noDate.classList.remove('hidden');
                $noDate.innerHTML = '<p class="text-gray-500">Această dată nu este disponibilă.</p>';
                return;
            }

            dateTickets = resp.ticket_types || [];
            renderTicketSelector();
        } catch (e) {
            console.error('Failed to load date availability:', e);
            $loading.classList.add('hidden');
            $noDate.classList.remove('hidden');
            $noDate.innerHTML = '<p class="text-red-500">Eroare la încărcarea disponibilității. Încearcă din nou.</p>';
        }
    }

    document.getElementById('change-date-btn')?.addEventListener('click', () => {
        // Scroll to calendar
        document.getElementById('cal-grid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // ========== Ticket Selector ==========

    function renderTicketSelector() {
        $loading.classList.add('hidden');

        if (!dateTickets || dateTickets.length === 0) {
            $groups.classList.add('hidden');
            $noDate.classList.remove('hidden');
            $noDate.innerHTML = '<p class="text-gray-500">Nu există bilete disponibile pentru această dată.</p>';
            return;
        }

        // Group ticket types by ticket_group
        const groups = {};
        dateTickets.forEach(tt => {
            const group = tt.group || 'Bilete';
            if (!groups[group]) groups[group] = [];
            groups[group].push(tt);
        });

        let html = '';

        for (const [groupName, tickets] of Object.entries(groups)) {
            html += '<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">';
            html += '<div class="px-5 py-3 bg-gray-50 border-b border-gray-100">';
            html += '<h3 class="text-sm font-bold text-secondary uppercase tracking-wide">' + escHtml(groupName) + '</h3>';
            html += '</div>';
            html += '<div class="divide-y divide-gray-50">';

            tickets.forEach(tt => {
                const available = tt.available;
                const isUnavailable = available !== null && available <= 0;
                const min = tt.min_per_order || 1;
                const max = tt.max_per_order || 10;
                const maxAllowed = available !== null ? Math.min(max, available) : max;
                const qty = quantities[tt.id] || 0;

                html += '<div class="px-5 py-4 flex items-center gap-4' + (isUnavailable ? ' opacity-50' : '') + '">';

                // Info
                html += '<div class="flex-1 min-w-0">';
                html += '<div class="font-semibold text-secondary">' + escHtml(tt.name) + '</div>';
                if (tt.description) {
                    html += '<div class="text-xs text-gray-500 mt-0.5">' + escHtml(tt.description) + '</div>';
                }
                if (min > 1) {
                    html += '<div class="text-xs text-amber-600 mt-0.5">Minim ' + min + ' bilete</div>';
                }
                html += '</div>';

                // Price
                html += '<div class="text-right shrink-0 mr-4">';
                if (tt.base_price !== tt.effective_price) {
                    html += '<div class="text-xs text-gray-400 line-through">' + formatPrice(tt.base_price) + '</div>';
                }
                html += '<div class="font-bold text-primary">' + formatPrice(tt.effective_price) + ' <span class="text-xs font-normal text-gray-400">' + (tt.currency || 'RON') + '</span></div>';
                html += '</div>';

                // Quantity selector
                if (isUnavailable) {
                    html += '<div class="text-sm font-medium text-red-500 shrink-0">Sold out</div>';
                } else {
                    html += '<div class="flex items-center gap-2 shrink-0">';
                    html += '<button class="qty-btn w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition disabled:opacity-30" data-tt="' + tt.id + '" data-dir="-1"' + (qty <= 0 ? ' disabled' : '') + '>−</button>';
                    html += '<span class="qty-val w-8 text-center font-semibold text-secondary tabular-nums" data-tt="' + tt.id + '">' + qty + '</span>';
                    html += '<button class="qty-btn w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition disabled:opacity-30" data-tt="' + tt.id + '" data-dir="1"' + (qty >= maxAllowed ? ' disabled' : '') + '>+</button>';
                    html += '</div>';
                }

                html += '</div>';

                // Parking: license plate input
                if (tt.requires_vehicle_info && qty > 0) {
                    for (let i = 0; i < qty; i++) {
                        const key = tt.id + '_' + i;
                        const val = vehicleInfo[key]?.license_plate || '';
                        html += '<div class="px-5 pb-3 flex items-center gap-2">';
                        html += '<svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>';
                        html += '<input type="text" class="vehicle-plate flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 uppercase" placeholder="Nr. înmatriculare (ex: B-123-XYZ)" data-tt="' + tt.id + '" data-idx="' + i + '" value="' + escHtml(val) + '">';
                        html += '</div>';
                    }
                }
            });

            html += '</div></div>';
        }

        $groups.innerHTML = html;
        $groups.classList.remove('hidden');

        // Bind quantity buttons
        $groups.querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const ttId = parseInt(btn.dataset.tt);
                const dir = parseInt(btn.dataset.dir);
                changeQuantity(ttId, dir);
            });
        });

        // Bind vehicle plate inputs
        $groups.querySelectorAll('.vehicle-plate').forEach(input => {
            input.addEventListener('input', () => {
                const key = input.dataset.tt + '_' + input.dataset.idx;
                if (!vehicleInfo[key]) vehicleInfo[key] = {};
                vehicleInfo[key].license_plate = input.value.toUpperCase().trim();
            });
        });

        updateSummary();
    }

    function changeQuantity(ttId, dir) {
        const tt = dateTickets.find(t => t.id === ttId);
        if (!tt) return;

        const min = tt.min_per_order || 1;
        const max = tt.max_per_order || 10;
        const maxAllowed = tt.available !== null ? Math.min(max, tt.available) : max;
        let qty = quantities[ttId] || 0;

        qty += dir;

        // Enforce min_per_order: if going from 0 to positive, jump to min
        if (dir > 0 && qty < min) qty = min;
        // If going down below min, go to 0
        if (dir < 0 && qty < min) qty = 0;
        // Cap at max
        if (qty > maxAllowed) qty = maxAllowed;
        if (qty < 0) qty = 0;

        quantities[ttId] = qty;

        // Clean up vehicle info for removed parking slots
        if (tt.requires_vehicle_info) {
            for (let i = qty; i < 20; i++) {
                delete vehicleInfo[ttId + '_' + i];
            }
        }

        renderTicketSelector();
    }

    // ========== Summary & Cart ==========

    function updateSummary() {
        const items = [];
        let total = 0;

        dateTickets?.forEach(tt => {
            const qty = quantities[tt.id] || 0;
            if (qty > 0) {
                const lineTotal = qty * tt.effective_price;
                total += lineTotal;
                items.push({ tt, qty, lineTotal });
            }
        });

        if (items.length === 0) {
            $summary.classList.add('hidden');
            $mobileBar.classList.add('hidden');
            $addBtn.disabled = true;
            if ($mobileAddBtn) $mobileAddBtn.disabled = true;
            return;
        }

        // Validate: parking tickets need license plates
        let valid = true;
        items.forEach(item => {
            if (item.tt.requires_vehicle_info) {
                for (let i = 0; i < item.qty; i++) {
                    const plate = vehicleInfo[item.tt.id + '_' + i]?.license_plate;
                    if (!plate || plate.length < 3) valid = false;
                }
            }
        });

        // Render items
        $summaryItems.innerHTML = items.map(item =>
            '<div class="flex justify-between text-gray-600">' +
            '<span>' + item.qty + '× ' + escHtml(item.tt.name) + '</span>' +
            '<span class="font-medium">' + formatPrice(item.lineTotal) + ' ' + (item.tt.currency || 'RON') + '</span>' +
            '</div>'
        ).join('');

        $totalEl.textContent = formatPrice(total) + ' RON';
        $summary.classList.remove('hidden');

        // Mobile bar
        $mobileTotalEl.textContent = formatPrice(total) + ' RON';
        $mobileBar.classList.remove('hidden');

        $addBtn.disabled = !valid;
        if ($mobileAddBtn) $mobileAddBtn.disabled = !valid;
    }

    function addToCart() {
        if (!selectedDate || !dateTickets) return;

        dateTickets.forEach(tt => {
            const qty = quantities[tt.id] || 0;
            if (qty <= 0) return;

            // Build vehicle_info for parking
            let vInfo = null;
            if (tt.requires_vehicle_info) {
                const plates = [];
                for (let i = 0; i < qty; i++) {
                    plates.push(vehicleInfo[tt.id + '_' + i]?.license_plate || '');
                }
                vInfo = { license_plates: plates };
            }

            // Add to AmbiletCart with meta
            if (typeof AmbiletCart !== 'undefined' && AmbiletCart.addItem) {
                AmbiletCart.addItem(
                    {
                        id: EVENT.id,
                        title: EVENT.name,
                        slug: EVENT.slug,
                        image: EVENT.image,
                        visit_date: selectedDate,
                    },
                    {
                        id: tt.id,
                        name: tt.name,
                        price: tt.effective_price,
                        originalPrice: tt.base_price !== tt.effective_price ? tt.base_price : null,
                        min_per_order: tt.min_per_order,
                        max_per_order: tt.max_per_order,
                        is_parking: tt.is_parking,
                        requires_vehicle_info: tt.requires_vehicle_info,
                    },
                    qty,
                    {
                        visit_date: selectedDate,
                        vehicle_info: vInfo,
                    }
                );
            }
        });

        // Redirect to cart
        window.location.href = '/cos';
    }

    $addBtn?.addEventListener('click', addToCart);
    $mobileAddBtn?.addEventListener('click', addToCart);

    // ========== Utils ==========

    function formatPrice(val) {
        return parseFloat(val).toFixed(2).replace(/\.00$/, '').replace('.', ',');
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ========== Init ==========

    loadMonth();

})();
