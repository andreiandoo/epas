@php
    $supportEmail = 'help@tixello.com';
    $supportPhone = '0040 750 29 29 62';
@endphp
<div class="m-0 border-t border-gray-200 fi-sidebar-footer dark:border-gray-700">
    <details class="group bg-emerald-50 dark:bg-emerald-900/20">
        <summary class="flex items-center justify-between px-4 py-2 cursor-pointer list-none select-none">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-700 dark:text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">Need Help?</span>
            </div>
            <svg class="w-3.5 h-3.5 text-emerald-700 dark:text-emerald-300 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </summary>
        <div class="px-4 pb-3 space-y-1.5">
            <a href="mailto:{{ $supportEmail }}"
                class="flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                {{ $supportEmail }}
            </a>
            @if($supportPhone)
            <a href="tel:{{ $supportPhone }}"
                class="flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                {{ $supportPhone }}
            </a>
            @endif
        </div>
    </details>
</div>
