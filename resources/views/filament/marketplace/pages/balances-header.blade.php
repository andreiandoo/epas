<header class="fi-header ep-bal-header">
    <div>
        <h1 class="fi-header-heading">{{ $heading }}</h1>
    </div>

    <div class="ep-bal-cards">
        @foreach ($cards as $card)
            <div class="ep-bal-card">
                <div class="ep-bal-card__label">{{ $card['label'] }}</div>
                <div class="ep-bal-card__value" @if ($card['color']) style="color: {{ $card['color'] }}" @endif>
                    {{ number_format($card['value'], 2, ',', '.') }} RON
                </div>
            </div>
        @endforeach
    </div>

    <style>
        .ep-bal-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        .ep-bal-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .ep-bal-card { min-width: 11rem; padding: .75rem 1rem; border-radius: .75rem; background: #fff; border: 1px solid rgba(3, 7, 18, .08); box-shadow: 0 1px 2px rgba(0, 0, 0, .04); }
        .dark .ep-bal-card { background: rgba(255, 255, 255, .04); border-color: rgba(255, 255, 255, .1); }
        .ep-bal-card__label { font-size: .75rem; color: #6b7280; }
        .dark .ep-bal-card__label { color: #9ca3af; }
        .ep-bal-card__value { margin-top: .125rem; font-size: 1.125rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .dark .ep-bal-card__value[style*="#d97706"] { color: #fbbf24 !important; }
        .dark .ep-bal-card__value[style*="#059669"] { color: #34d399 !important; }
        @media (max-width: 640px) { .ep-bal-cards { grid-template-columns: 1fr; width: 100%; } }
    </style>
</header>
