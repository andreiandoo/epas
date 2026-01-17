@php
    use App\Models\MarketplaceClient;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Facades\Auth;

    $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
    $marketplace = $marketplaceAdmin?->marketplaceClient;

    $logoLight = null;
    $logoDark = null;
    $brandName = $marketplace?->name ?? 'Marketplace';

    if ($marketplace) {
        $settings = $marketplace->settings ?? [];
        $branding = $settings['branding'] ?? [];

        // Helper to get logo URL
        $getLogoUrl = function ($value) {
            if (empty($value)) return null;
            if (is_array($value)) {
                $value = reset($value);
            }
            if (empty($value)) return null;
            if (str_starts_with($value, 'http')) {
                return $value;
            }
            return Storage::disk('public')->url($value);
        };

        $logoLight = $getLogoUrl($branding['logo_light'] ?? $branding['logo_url'] ?? null);
        $logoDark = $getLogoUrl($branding['logo_dark'] ?? $branding['logo_url'] ?? null);
    }
@endphp
<style>
    /* Sidebar logo styling */
    .ep-sidebar-logo {
        padding: 1rem 1rem 0.75rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 0.5rem;
    }
    .ep-sidebar-logo-light { display: block; }
    .ep-sidebar-logo-dark { display: none; }
    .dark .ep-sidebar-logo-light { display: none; }
    .dark .ep-sidebar-logo-dark { display: block; }
</style>
<div class="ep-sidebar-logo">
    @if($logoLight || $logoDark)
        @if($logoLight && $logoDark)
            {{-- Both logos provided --}}
            <img
                src="{{ $logoLight }}"
                alt="{{ $brandName }}"
                class="ep-sidebar-logo-light h-8 w-auto max-w-full object-contain"
            >
            <img
                src="{{ $logoDark }}"
                alt="{{ $brandName }}"
                class="ep-sidebar-logo-dark h-8 w-auto max-w-full object-contain"
            >
        @elseif($logoLight)
            <img
                src="{{ $logoLight }}"
                alt="{{ $brandName }}"
                class="h-8 w-auto max-w-full object-contain"
            >
        @elseif($logoDark)
            <img
                src="{{ $logoDark }}"
                alt="{{ $brandName }}"
                class="h-8 w-auto max-w-full object-contain"
            >
        @endif
    @else
        {{-- Fallback: Default icon and text --}}
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-8 h-8 bg-emerald-500 rounded-lg flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <span class="text-base font-bold text-gray-900 dark:text-white truncate">{{ $brandName }}</span>
        </div>
    @endif
</div>
