@php
    use App\Models\Setting;

    $settings = Setting::current();
    $meta = $settings->meta ?? [];
    $isAdminPanel = request()->is('admin*');

    // Get logos based on panel type
    $logoLight = $isAdminPanel
        ? ($meta['logo_admin_light'] ?? null)
        : ($meta['logo_tenant_light'] ?? null);
    $logoDark = $isAdminPanel
        ? ($meta['logo_admin_dark'] ?? null)
        : ($meta['logo_tenant_dark'] ?? null);

    $panelLabel = $isAdminPanel ? 'Admin Panel' : 'Tenant Panel';
    $brandName = 'Tixello';
@endphp
<div class="fi-sidebar-brand flex items-center justify-center px-6 py-4 border-b border-gray-200 dark:border-gray-700">
    <a href="{{ filament()->getUrl() }}" class="flex items-center gap-3">
        @if($logoLight || $logoDark)
            {{-- Logo with dark mode support --}}
            @if($logoLight && $logoDark)
                {{-- Both logos provided: show appropriate one based on theme --}}
                <img
                    src="{{ asset('storage/' . $logoLight) }}"
                    alt="{{ $brandName }}"
                    class="h-10 w-auto max-w-[180px] object-contain dark:hidden"
                >
                <img
                    src="{{ asset('storage/' . $logoDark) }}"
                    alt="{{ $brandName }}"
                    class="h-10 w-auto max-w-[180px] object-contain hidden dark:block"
                >
            @elseif($logoLight)
                {{-- Only light logo --}}
                <img
                    src="{{ asset('storage/' . $logoLight) }}"
                    alt="{{ $brandName }}"
                    class="h-10 w-auto max-w-[180px] object-contain"
                >
            @elseif($logoDark)
                {{-- Only dark logo --}}
                <img
                    src="{{ asset('storage/' . $logoDark) }}"
                    alt="{{ $brandName }}"
                    class="h-10 w-auto max-w-[180px] object-contain"
                >
            @endif
        @else
            {{-- Fallback: Default icon and text --}}
            <div class="flex items-center justify-center w-10 h-10 bg-primary-500 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                </svg>
            </div>
            <div class="flex flex-col">
                <span class="text-xl font-bold text-gray-900 dark:text-white">{{ $brandName }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $panelLabel }}</span>
            </div>
        @endif
    </a>
</div>
