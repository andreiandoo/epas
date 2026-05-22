/**
 * bilete.online — Alpine.js shared stores
 * Defines stores used across pages (catalog search/filter state, etc.)
 * Must load BEFORE alpine.js itself (Alpine fires `alpine:init` synchronously).
 */
document.addEventListener('alpine:init', function () {
    // Shared catalog state for hero search box ↔ experiences filter ↔ chips
    window.Alpine.store('catalog', {
        query: '',
        category: 'all',
    });
});
