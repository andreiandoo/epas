@php
    use Illuminate\Support\Facades\Auth;

    $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
    $marketplace = $marketplaceAdmin?->marketplaceClient;

    $supportEmail = $marketplace?->settings['support_email'] ?? $marketplace?->contact_email ?? 'support@tixello.com';
    $supportPhone = $marketplace?->contact_phone ?? null;
@endphp
<div class="m-0 border-t border-gray-200 fi-sidebar-footer dark:border-gray-700">
    <div class="flex flex-col p-4 gap-y-2 bg-emerald-50 dark:bg-emerald-900/20">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-700 dark:text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                    Need Help?
                </h4>
            </div>
        </div>
        <div class="mt-3 space-y-2">
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
    </div>
</div>
