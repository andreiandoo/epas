@php
    $livewire ??= null;

    $brand = (is_object($livewire) && method_exists($livewire, 'getBrand'))
        ? $livewire->getBrand()
        : [];

    $palette = $brand['palette'] ?? [];
@endphp

<x-filament-panels::layout.base :livewire="$livewire" class="tx-auth-body">
    <div
        class="tx-auth"
        style="
            --tx-bg-1: {{ $palette['bg1'] ?? '#0d0c18' }};
            --tx-bg-2: {{ $palette['bg2'] ?? '#191635' }};
            --tx-bg-3: {{ $palette['bg3'] ?? '#332c78' }};
            --tx-glow: {{ $palette['glow'] ?? '#6366f1' }};
            --tx-eyebrow: {{ $palette['eyebrow'] ?? '#a5b4fc' }};
        "
    >
        @include('filament.auth.partials.brand-panel', ['brand' => $brand])

        <main id="fi-main-content" tabindex="-1" class="tx-auth-form">
            <div class="tx-auth-form-inner">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-filament-panels::layout.base>
