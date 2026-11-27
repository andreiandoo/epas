{{-- Event edit header: one meta line (status, place, date, organizer, series)
     and the operational alerts, shown on every tab. Own CSS: the panel
     theme is prebuilt. --}}
<style>
    .evh { --evh-muted: #6b7280; --evh-sep: #d1d5db; --evh-pill: #f3f4f6; display: flex; flex-direction: column; gap: .75rem; margin-top: .35rem; }
    .dark .evh { --evh-muted: #9ca3af; --evh-sep: #4b5563; --evh-pill: #1f2937; }
    .evh-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .6rem; font-size: .8125rem; font-weight: 400; color: var(--evh-muted); }
    .evh-meta a { color: inherit; }
    .evh-meta a:hover { text-decoration: underline; }
    .evh-sep { color: var(--evh-sep); }
    .evh-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; }
    .evh-pill { display: inline-flex; align-items: center; gap: .35rem; height: 1.375rem; padding: 0 .55rem; border-radius: 999px; font-size: .6875rem; font-weight: 600; letter-spacing: .03em; background: var(--evh-pill); color: var(--evh-muted); }
    .evh-pill i { width: .4rem; height: .4rem; border-radius: 999px; background: currentColor; }
    .evh-pill.is-on { background: rgba(16,185,129,.14); color: #059669; }
    .dark .evh-pill.is-on { color: #34d399; }
    .evh-pill.is-red { background: rgba(239,68,68,.14); color: #dc2626; }
    .dark .evh-pill.is-red { color: #fca5a5; }
    .evh-pill.is-amber { background: rgba(245,158,11,.14); color: #b45309; }
    .dark .evh-pill.is-amber { color: #fde68a; }
    .evh-alert { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .9rem; padding: .6rem .85rem; border-radius: .6rem; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.06); font-size: .8125rem; font-weight: 400; }
    .evh-alert-text { flex: 1 1 22rem; min-width: 0; }
    .evh-alert-text strong { font-weight: 600; color: #b91c1c; }
    .dark .evh-alert-text strong { color: #fecaca; }
    .evh-alert-text span { color: var(--evh-muted); }
    .evh-alert a { flex: none; display: inline-flex; align-items: center; height: 1.75rem; padding: 0 .75rem; border-radius: .5rem; border: 1px solid rgba(239,68,68,.35); color: #b91c1c; font-size: .75rem; font-weight: 600; }
    .dark .evh-alert a { color: #fecaca; }
    .evh-alert a:hover { background: rgba(239,68,68,.1); }
</style>

<div class="evh">
    <div class="evh-meta">
        @if($published)
            <span class="evh-pill is-on"><i></i>{{ __('Publicat') }}</span>
        @else
            <span class="evh-pill"><i></i>{{ __('Nepublicat') }}</span>
        @endif
        @if($status)
            <span @class(['evh-pill', 'is-red' => $status === 'cancelled', 'is-amber' => $status === 'postponed'])>{{ $statusLabel }}</span>
        @endif

        @foreach($parts as $i => $part)
            @if($i > 0)<span class="evh-sep">·</span>@endif
            @if(!empty($part['url']))
                <a href="{{ $part['url'] }}" target="_blank" rel="noopener" @class(['evh-mono' => $part['mono'] ?? false])>{{ $part['text'] }}</a>
            @else
                <span @class(['evh-mono' => $part['mono'] ?? false])>{{ $part['text'] }}</span>
            @endif
        @endforeach
    </div>

    @foreach($alerts as $alert)
        <div class="evh-alert" role="alert">
            <div class="evh-alert-text">
                <strong>{{ $alert['title'] ?? '' }}</strong>
                @if(!empty($alert['message']))<span>{{ $alert['message'] }}</span>@endif
            </div>
            @if(!empty($alert['action_url']))
                <a href="{{ $alert['action_url'] }}">{{ __('Rezolvă') }}</a>
            @endif
        </div>
    @endforeach
</div>
