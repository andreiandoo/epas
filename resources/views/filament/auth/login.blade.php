@php
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
@endphp

<div class="tx-auth-card">
    @if (filled($heading) || filled($subheading))
        <div class="tx-auth-card-head">
            @if (filled($heading))
                <h1 class="tx-auth-title">{{ $heading }}</h1>
            @endif

            @if (filled($subheading))
                <p class="tx-auth-subtitle">{{ $subheading }}</p>
            @endif
        </div>
    @endif

    {{ $this->content }}

    <x-filament-actions::modals />
</div>
