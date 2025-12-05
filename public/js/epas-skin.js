/**
 * EPAS Admin/Tenant Panel Global Search
 * Supports both admin and tenant panels with navigation search
 */

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('epas-global-search-input');
    const searchResults = document.getElementById('epas-search-results');

    if (!searchInput || !searchResults) {
        return; // Search not present on this page
    }

    let searchTimeout;
    const minSearchLength = 3;

    // Detect which panel we're in based on current URL
    function getSearchEndpoint() {
        const path = window.location.pathname;

        // Check if we're in tenant panel (URL format: /tenant/{id}/... or /tenant/{slug}/...)
        // Match any tenant identifier (numeric ID or slug)
        const tenantMatch = path.match(/^\/tenant\/([^\/]+)/);
        if (tenantMatch) {
            return `/api/search/tenant/${tenantMatch[1]}`;
        }

        // Default to admin panel
        return '/api/search/admin';
    }

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

        // Arrow key navigation
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const items = searchResults.querySelectorAll('a');
            const focused = searchResults.querySelector('a:focus');

            if (items.length === 0) return;

            if (!focused) {
                items[0].focus();
            } else {
                const index = Array.from(items).indexOf(focused);
                if (e.key === 'ArrowDown' && index < items.length - 1) {
                    items[index + 1].focus();
                } else if (e.key === 'ArrowUp' && index > 0) {
                    items[index - 1].focus();
                }
            }
        }

        // Enter key to follow link
        if (e.key === 'Enter') {
            const focused = searchResults.querySelector('a:focus');
            if (focused) {
                focused.click();
            }
        }
    });

    async function performSearch(query) {
        const endpoint = getSearchEndpoint();

        try {
            const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
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

        // Order of categories with icons
        const categories = {
            'pages': { label: 'Pages', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>' },
            'events': { label: 'Events', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>' },
            'venues': { label: 'Venues', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>' },
            'orders': { label: 'Orders', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>' },
            'tickets': { label: 'Tickets', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>' },
            'artists': { label: 'Artists', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>' },
            'tenants': { label: 'Tenants', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>' },
            'customers': { label: 'Customers', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>' },
            'users': { label: 'Users', icon: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>' }
        };

        for (const [key, category] of Object.entries(categories)) {
            if (data[key] && data[key].length > 0) {
                html += `<div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-700/50 dark:text-gray-400">${category.label}</div>`;

                data[key].forEach(item => {
                    const name = item.name || 'Unknown';
                    const subtitle = item.subtitle || '';
                    const url = item.url || '#';

                    html += `
                        <a href="${url}" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 focus:bg-gray-100 dark:focus:bg-gray-700 focus:outline-none">
                            <span class="text-gray-400">${category.icon}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate dark:text-white">${escapeHtml(name)}</div>
                                ${subtitle ? `<div class="text-xs text-gray-500 truncate dark:text-gray-400">${escapeHtml(subtitle)}</div>` : ''}
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
