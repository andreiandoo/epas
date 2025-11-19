/**
 * EPAS Admin Panel Global Search
 */

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('epas-global-search-input');
    const searchResults = document.getElementById('epas-search-results');

    if (!searchInput || !searchResults) {
        return; // Search not present on this page
    }

    let searchTimeout;
    const minSearchLength = 2;

    searchInput.addEventListener('input', function (e) {
        const query = e.target.value.trim();

        // Clear previous timeout
        clearTimeout(searchTimeout);

        // Hide results if query too short
        if (query.length < minSearchLength) {
            searchResults.classList.add('hidden');
            return;
        }

        // Debounce search
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Close search results when clicking outside
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });

    // Handle keyboard navigation
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            searchResults.classList.add('hidden');
            searchInput.blur();
        }
    });

    async function performSearch(query) {
        try {
            const response = await fetch(`/admin/api/global-search?q=${encodeURIComponent(query)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });

            if (!response.ok) {
                console.error('Search failed:', response.statusText);
                return;
            }

            const data = await response.json();
            displayResults(data);
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    function displayResults(data) {
        if (!data || Object.keys(data).length === 0) {
            searchResults.innerHTML = '<div class="p-4 text-sm text-gray-500 dark:text-gray-400">No results found</div>';
            searchResults.classList.remove('hidden');
            return;
        }

        let html = '';

        // Order of categories
        const categories = {
            'venues': { label: 'Venues', icon: 'location', url: '/admin/venues' },
            'artists': { label: 'Artists', icon: 'users', url: '/admin/artists' },
            'tenants': { label: 'Tenants', icon: 'building', url: '/admin/tenants' },
            'customers': { label: 'Customers', icon: 'user', url: '/admin/customers' },
            'users': { label: 'Users', icon: 'user-circle', url: '/admin/users' }
        };

        for (const [key, category] of Object.entries(categories)) {
            if (data[key] && data[key].length > 0) {
                html += `<div class="epas-search-category">${category.label}</div>`;

                data[key].forEach(item => {
                    const name = item.name || item.public_name || item.email || 'Unknown';
                    const subtitle = item.email || item.city || '';

                    html += `
                        <a href="${category.url}/${item.id}/edit" class="epas-search-result-item block">
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(name)}</div>
                                    ${subtitle ? `<div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(subtitle)}</div>` : ''}
                                </div>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </a>
                    `;
                });
            }
        }

        if (html === '') {
            html = '<div class="p-4 text-sm text-gray-500 dark:text-gray-400">No results found</div>';
        }

        searchResults.innerHTML = html;
        searchResults.classList.remove('hidden');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
