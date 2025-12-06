@php
    use App\Models\Setting;
    use Illuminate\Support\Facades\Storage;

    $settings = Setting::current();
    $meta = $settings->meta ?? [];
    $isAdminPanel = request()->is('admin*');

    // Helper to get logo URL from stored value
    $getLogoUrl = function ($value) {
        if (empty($value)) return null;
        // Handle array (FileUpload can store as array)
        if (is_array($value)) {
            $value = reset($value);
        }
        if (empty($value)) return null;
        // Return storage URL
        return Storage::disk('public')->url($value);
    };

    // Get logos based on panel type
    $logoLightRaw = $isAdminPanel
        ? ($meta['logo_admin_light'] ?? null)
        : ($meta['logo_tenant_light'] ?? null);
    $logoDarkRaw = $isAdminPanel
        ? ($meta['logo_admin_dark'] ?? null)
        : ($meta['logo_tenant_dark'] ?? null);

    $logoLight = $getLogoUrl($logoLightRaw);
    $logoDark = $getLogoUrl($logoDarkRaw);

    $brandName = 'Tixello';
@endphp
@if($logoLight || $logoDark)
    {{-- Logo with dark mode support using inline styles for reliability --}}
    @if($logoLight && $logoDark)
        {{-- Both logos provided: show appropriate one based on theme --}}
        <img
            src="{{ $logoLight }}"
            alt="{{ $brandName }}"
            class="h-8 w-auto max-w-[180px] object-contain block dark:!hidden"
        >
        <img
            src="{{ $logoDark }}"
            alt="{{ $brandName }}"
            class="h-8 w-auto max-w-[180px] object-contain !hidden dark:!block"
        >
    @elseif($logoLight)
        {{-- Only light logo --}}
        <img
            src="{{ $logoLight }}"
            alt="{{ $brandName }}"
            class="h-8 w-auto max-w-[180px] object-contain"
        >
    @elseif($logoDark)
        {{-- Only dark logo --}}
        <img
            src="{{ $logoDark }}"
            alt="{{ $brandName }}"
            class="h-8 w-auto max-w-[180px] object-contain"
        >
    @endif
@else
    {{-- Fallback: Default icon and text --}}
    <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-8 h-8 bg-primary-500 rounded-lg">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
            </svg>
        </div>
        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $brandName }}</span>
    </div>
@endif
