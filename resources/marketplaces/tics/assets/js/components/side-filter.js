/**
 * TICS.ro - Side Filter Component
 * Handles filter drawer and filter interactions
 */

// Mobile filter drawer functions
function openFiltersDrawer() {
    const overlay = document.getElementById('filterOverlay');
    const drawer = document.getElementById('filterDrawer');
    if (overlay && drawer) {
        overlay.classList.add('open');
        drawer.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeFiltersDrawer() {
    const overlay = document.getElementById('filterOverlay');
    const drawer = document.getElementById('filterDrawer');
    if (overlay && drawer) {
        overlay.classList.remove('open');
        drawer.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Price range functions
function updatePriceLabel(value) {
    const label = document.getElementById('priceLabel');
    const labelMobile = document.getElementById('priceLabelMobile');
    const text = value >= 1000 ? '1000+ RON' : `până la ${value} RON`;

    if (label) label.textContent = text;
    if (labelMobile) labelMobile.textContent = text;
}

function setQuickPrice(range) {
    const priceRange = document.getElementById('priceRange');
    const priceRangeMobile = document.getElementById('priceRangeMobile');

    const values = {
        'free': 0,
        '0-100': 100,
        '100-300': 300,
        '300-500': 500,
        '500+': 1000
    };

    const value = values[range] || 500;

    if (priceRange) {
        priceRange.value = value;
        updatePriceLabel(value);
    }
    if (priceRangeMobile) {
        priceRangeMobile.value = value;
    }

    // Update active state of quick price buttons
    document.querySelectorAll('[onclick^="setQuickPrice"]').forEach(btn => {
        const btnRange = btn.getAttribute('onclick').match(/'([^']+)'/)?.[1];
        if (btnRange === range) {
            btn.classList.remove('bg-gray-100', 'text-gray-600');
            btn.classList.add('bg-gray-900', 'text-white');
        } else {
            btn.classList.remove('bg-gray-900', 'text-white');
            btn.classList.add('bg-gray-100', 'text-gray-600');
        }
    });

    // Trigger filter update
    if (typeof TicsEventsPage !== 'undefined') {
        TicsEventsPage.filters.price = range;
        TicsEventsPage.applyFilters();
    }
}

// City search filter
function initCitySearch() {
    const searchInput = document.getElementById('citySearch');
    const cityList = document.getElementById('cityList');

    if (searchInput && cityList) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const cityOptions = cityList.querySelectorAll('.city-option');

            cityOptions.forEach(option => {
                const cityName = option.querySelector('span').textContent.toLowerCase();
                if (cityName.includes(query)) {
                    option.style.display = 'flex';
                } else {
                    option.style.display = 'none';
                }
            });
        });
    }
}

// Show all cities (expand list)
function showAllCities() {
    // TODO: Load all cities from API and show in modal
    console.log('Show all cities modal');
}

// Date picker
function openDatePicker() {
    // TODO: Implement date range picker
    console.log('Open date picker');
}

// Sync date checkboxes (single selection mode)
function syncDateFilters() {
    document.querySelectorAll('[data-date]').forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) {
                // Uncheck other date options
                document.querySelectorAll('[data-date]').forEach(otherCb => {
                    if (otherCb !== this) {
                        otherCb.checked = false;
                    }
                });

                // Update filter
                if (typeof TicsEventsPage !== 'undefined') {
                    TicsEventsPage.filters.date = this.dataset.date;
                    TicsEventsPage.applyFilters();
                }
            } else {
                // Clear date filter
                if (typeof TicsEventsPage !== 'undefined') {
                    TicsEventsPage.filters.date = '';
                    TicsEventsPage.applyFilters();
                }
            }
        });
    });
}

// Sync city checkboxes (single selection mode)
function syncCityFilters() {
    document.querySelectorAll('.city-cb').forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) {
                // Uncheck other city options
                document.querySelectorAll('.city-cb').forEach(otherCb => {
                    if (otherCb !== this) {
                        otherCb.checked = false;
                    }
                });

                // Update filter
                if (typeof TicsEventsPage !== 'undefined') {
                    TicsEventsPage.filters.city = this.value;
                    TicsEventsPage.applyFilters();
                }
            } else {
                // Clear city filter
                if (typeof TicsEventsPage !== 'undefined') {
                    TicsEventsPage.filters.city = '';
                    TicsEventsPage.applyFilters();
                }
            }
        });
    });
}

// Initialize filter events
function initFilterEvents() {
    // Filter button (mobile)
    const filterBtn = document.getElementById('filterBtn');
    if (filterBtn) {
        filterBtn.addEventListener('click', openFiltersDrawer);
    }

    // Overlay click to close
    const overlay = document.getElementById('filterOverlay');
    if (overlay) {
        overlay.addEventListener('click', closeFiltersDrawer);
    }

    // Price range inputs
    const priceRange = document.getElementById('priceRange');
    if (priceRange) {
        priceRange.addEventListener('input', (e) => updatePriceLabel(e.target.value));
        priceRange.addEventListener('change', () => {
            if (typeof TicsEventsPage !== 'undefined') {
                const value = parseInt(priceRange.value);
                let priceFilter = '';
                if (value === 0) priceFilter = 'free';
                else if (value <= 100) priceFilter = '0-100';
                else if (value <= 300) priceFilter = '100-300';
                else if (value <= 500) priceFilter = '300-500';
                else priceFilter = '500+';

                TicsEventsPage.filters.price = priceFilter;
                TicsEventsPage.applyFilters();
            }
        });
    }

    const priceRangeMobile = document.getElementById('priceRangeMobile');
    if (priceRangeMobile) {
        priceRangeMobile.addEventListener('input', (e) => {
            const label = document.getElementById('priceLabelMobile');
            if (label) {
                const value = e.target.value;
                label.textContent = value >= 1000 ? '1000+ RON' : `${value} RON`;
            }
        });
    }

    // Initialize sub-filters
    initCitySearch();
    syncDateFilters();
    syncCityFilters();
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initFilterEvents();
});

// Make functions globally available
window.openFiltersDrawer = openFiltersDrawer;
window.closeFiltersDrawer = closeFiltersDrawer;
window.updatePriceLabel = updatePriceLabel;
window.setQuickPrice = setQuickPrice;
window.showAllCities = showAllCities;
window.openDatePicker = openDatePicker;
