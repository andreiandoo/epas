@php
    use App\Models\Setting;
    use Illuminate\Support\Facades\Storage;

    $brand ??= [];

    // The brand column is always dark, so we want the light-on-dark logo
    // variant, falling back to the light-mode one, then to a wordmark.
    $resolveLogo = function ($value) {
        if (is_array($value)) {
            $value = reset($value);
        }

        return filled($value) ? Storage::disk('public')->url($value) : null;
    };

    $logo = null;

    try {
        $meta = Setting::current()->meta ?? [];
        $isAdminPanel = filament()->getId() === 'admin';

        $logo = $resolveLogo($isAdminPanel ? ($meta['logo_admin_dark'] ?? null) : ($meta['logo_tenant_dark'] ?? null))
            ?? $resolveLogo($isAdminPanel ? ($meta['logo_admin_light'] ?? null) : ($meta['logo_tenant_light'] ?? null));
    } catch (\Throwable $e) {
        // Settings are unavailable (fresh install, migration pending) —
        // the wordmark fallback below keeps the login screen usable.
        $logo = null;
    }
@endphp

<aside class="tx-auth-brand" aria-hidden="true">
    <div class="tx-auth-brand-logo">
        @if ($logo)
            <img src="{{ $logo }}" alt="Tixello">
        @else
            <span class="tx-auth-brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </span>
            <span class="tx-auth-brand-name">Tixello</span>
        @endif
    </div>

    <div class="tx-auth-brand-copy">
        @if (filled($brand['eyebrow'] ?? null))
            <p class="tx-auth-eyebrow">{{ $brand['eyebrow'] }}</p>
        @endif

        <h2 class="tx-auth-headline">{{ $brand['headline'] ?? '' }}</h2>

        @if (filled($brand['subcopy'] ?? null))
            <p class="tx-auth-subcopy">{{ $brand['subcopy'] }}</p>
        @endif
    </div>
</aside>
