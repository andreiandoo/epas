<div class="fi-topbar-start flex items-center gap-3">
    {{-- Open Sidebar Button (visible when sidebar is closed) --}}
    <button
        x-data
        @click="$store.sidebar.open()"
        x-show="! $store.sidebar.isOpen"
        x-cloak
        type="button"
        class="fi-icon-btn fi-icon-btn-sm flex items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:text-gray-500 focus-visible:bg-gray-500/10 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:bg-gray-400/10"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    {{-- Close Sidebar Button (visible when sidebar is open) --}}
    <button
        x-data
        @click="$store.sidebar.close()"
        x-show="$store.sidebar.isOpen"
        x-cloak
        type="button"
        class="fi-icon-btn fi-icon-btn-sm flex items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:text-gray-500 focus-visible:bg-gray-500/10 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:bg-gray-400/10"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
