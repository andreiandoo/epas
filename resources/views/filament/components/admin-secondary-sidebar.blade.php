<div
    id="ep-secondary-sidebar"
    x-data
    x-show="$store.secondarySidebar.open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="-translate-x-4 opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0 opacity-100"
    x-transition:leave-end="-translate-x-4 opacity-0"
    @keydown.escape.window="$store.secondarySidebar.close()"
    x-cloak
    class="ep-secondary-sidebar"
>
    {{-- Header --}}
    <div class="ep-secondary-sidebar-header">
        <div class="flex items-center gap-2">
            <span id="ep-secondary-sidebar-icon" class="flex-shrink-0">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z"/>
                </svg>
            </span>
            <span id="ep-secondary-sidebar-title" class="text-sm font-semibold text-gray-700 dark:text-gray-200"></span>
        </div>
        <button
            @click="$store.secondarySidebar.close()"
            class="ep-secondary-sidebar-close"
            title="Close"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Scrollable nav --}}
    <nav class="ep-secondary-sidebar-nav">

        {{-- PANEL: Marketplace --}}
        <div class="ep-secondary-sidebar-panel" data-ep-panel="marketplace" style="display: none;">
            <div class="ep-secondary-sidebar-section">
                <ul id="ep-admin-sidebar-marketplace-clone"></ul>
            </div>
        </div>

        {{-- PANEL: Tenants --}}
        <div class="ep-secondary-sidebar-panel" data-ep-panel="tenants" style="display: none;">
            <div class="ep-secondary-sidebar-section">
                <ul id="ep-admin-sidebar-tenants-clone"></ul>
            </div>
        </div>

        {{-- PANEL: Settings --}}
        <div class="ep-secondary-sidebar-panel" data-ep-panel="settings" style="display: none;">
            <div class="ep-secondary-sidebar-section">
                <ul id="ep-admin-sidebar-settings-clone"></ul>
            </div>
        </div>

        {{-- PANEL: Operational --}}
        <div class="ep-secondary-sidebar-panel" data-ep-panel="operational" style="display: none;">
            <div class="ep-secondary-sidebar-section">
                <ul id="ep-admin-sidebar-operational-clone"></ul>
            </div>
        </div>

        {{-- PANEL: Taxonomies --}}
        <div class="ep-secondary-sidebar-panel" data-ep-panel="taxonomies" style="display: none;">
            <div class="ep-secondary-sidebar-section">
                <ul id="ep-admin-sidebar-taxonomies-clone"></ul>
            </div>
        </div>

    </nav>
</div>
