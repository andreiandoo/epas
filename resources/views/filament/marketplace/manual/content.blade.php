{{-- Page manual chapters (drawer + hub page). Chapter HTML comes from the markdown in resources/manual. --}}
@php
    $appliesLabels = [
        'standard' => 'Doar evenimente standard',
        'agrement' => 'Doar locații de agrement',
    ];
@endphp
@foreach ($manual['chapters'] as $chapter)
    <article class="epm-ch" id="epm-ch-{{ $chapter['id'] }}" data-epm-chapter="{{ $chapter['id'] }}" @if ($chapter['tab']) data-epm-tab="{{ $chapter['tab'] }}" @endif>
        <header class="epm-ch-head">
            @if ($chapter['chapter'] !== $chapter['title'])
                <p class="epm-ch-kicker">{{ $chapter['chapter'] }}</p>
            @endif
            <h2 class="epm-ch-title">{{ $chapter['title'] }}</h2>
            <p class="epm-ch-meta">
                @if (isset($appliesLabels[$chapter['applies_to']]))
                    <span class="epm-badge epm-badge--warn">{{ $appliesLabels[$chapter['applies_to']] }}</span>
                @endif
                @if ($chapter['tab'] && isset($manual['tabs'][$chapter['tab']]))
                    <span class="epm-badge">Tab: {{ $manual['tabs'][$chapter['tab']] }}</span>
                @endif
                @if ($chapter['updated'])
                    <span class="epm-updated">Actualizat {{ \Illuminate\Support\Carbon::parse($chapter['updated'])->format('d.m.Y') }}</span>
                @endif
            </p>
        </header>
        <div class="epm-prose">{!! $chapter['html'] !!}</div>
    </article>
@endforeach
<p class="epm-empty" data-epm-empty hidden>Nicio potrivire. Încearcă alt cuvânt, de exemplu „reducere”, „sold out” sau „comision”.</p>
