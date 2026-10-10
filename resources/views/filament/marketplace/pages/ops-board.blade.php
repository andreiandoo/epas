<x-filament-panels::page>
    {{-- Own stylesheet: the panel theme is prebuilt, so utility classes that
         are not already in it would silently render unstyled. --}}
    <style>
        .opsb { --opsb-line: #e5e7eb; --opsb-muted: #6b7280; --opsb-card: #fff; --opsb-text: #111827; --opsb-head: #f9fafb; }
        .dark .opsb { --opsb-line: #374151; --opsb-muted: #9ca3af; --opsb-card: #1f2937; --opsb-text: #f3f4f6; --opsb-head: #111827; }
        .opsb { color: var(--opsb-text); display: flex; flex-direction: column; gap: 1.25rem; }
        .opsb-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .opsb-seg { display: inline-flex; border: 1px solid var(--opsb-line); border-radius: .5rem; overflow: hidden; background: var(--opsb-card); }
        .opsb-seg button { padding: .4rem .9rem; font-size: .875rem; color: var(--opsb-muted); }
        .opsb-seg button + button { border-left: 1px solid var(--opsb-line); }
        .opsb-seg button.is-on { background: #2563eb; color: #fff; }
        .opsb-btn { padding: .4rem .7rem; font-size: .875rem; border: 1px solid var(--opsb-line); border-radius: .5rem; background: var(--opsb-card); color: var(--opsb-text); }
        .opsb-period { font-size: 1rem; font-weight: 600; min-width: 11rem; text-align: center; }
        .opsb-stats { display: flex; flex-wrap: wrap; gap: .5rem; margin-left: auto; }
        .opsb-stat { padding: .3rem .7rem; border-radius: 999px; font-size: .8125rem; font-weight: 600; border: 1px solid var(--opsb-line); background: var(--opsb-card); }
        .opsb-stat.is-red { color: #b91c1c; border-color: #fca5a5; background: #fef2f2; }
        .opsb-stat.is-amber { color: #92400e; border-color: #fcd34d; background: #fffbeb; }
        .opsb-stat.is-blue { color: #1d4ed8; border-color: #93c5fd; background: #eff6ff; }
        .opsb-zone { background: var(--opsb-card); border: 1px solid var(--opsb-line); border-radius: .75rem; overflow: hidden; }
        .opsb-zone-head { display: flex; align-items: baseline; gap: .6rem; padding: .75rem 1rem; border-bottom: 1px solid var(--opsb-line); background: var(--opsb-head); }
        .opsb-zone-head h3 { font-size: .95rem; font-weight: 600; }
        .opsb-zone-head span { font-size: .8125rem; color: var(--opsb-muted); }
        .opsb-scroll { overflow-x: auto; }
        .opsb-table { width: 100%; min-width: 60rem; border-collapse: collapse; font-size: .8125rem; }
        .opsb-table th { text-align: left; font-weight: 600; color: var(--opsb-muted); padding: .5rem .6rem; border-bottom: 1px solid var(--opsb-line); white-space: nowrap; }
        .opsb-table td { padding: .5rem .6rem; border-bottom: 1px solid var(--opsb-line); vertical-align: top; }
        .opsb-table tr:last-child td { border-bottom: 0; }
        .opsb-event { min-width: 15rem; }
        .opsb-event a { font-weight: 600; color: var(--opsb-text); }
        .opsb-event a:hover { text-decoration: underline; }
        .opsb-meta { color: var(--opsb-muted); margin-top: .15rem; }
        .opsb-tag { display: inline-block; margin-left: .35rem; padding: 0 .4rem; border-radius: .25rem; font-size: .6875rem; font-weight: 600; background: #fee2e2; color: #991b1b; }
        .opsb-filing { display: inline-block; margin-top: .15rem; color: var(--opsb-muted); }
        .opsb-filing.is-missing { color: #b45309; font-weight: 600; }
        .opsb-filing:hover { text-decoration: underline; }
        .opsb-tag.is-postponed { background: #e0e7ff; color: #3730a3; }
        .opsb-cell { display: block; width: 9.5rem; padding: .35rem .5rem; border-radius: .4rem; border: 1px solid transparent; line-height: 1.25; }
        .opsb-cell strong { display: block; font-weight: 600; }
        .opsb-cell small { display: block; font-size: .6875rem; opacity: .85; }
        a.opsb-cell:hover { filter: brightness(.96); }
        .opsb-na { color: var(--opsb-muted); border-color: var(--opsb-line); border-style: dashed; }
        .opsb-waiting { color: var(--opsb-muted); border-color: var(--opsb-line); }
        .opsb-todo, .opsb-tofile, .opsb-redo, .opsb-warn { background: #fffbeb; color: #92400e; border-color: #fcd34d; }
        .opsb-overdue { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
        .opsb-progress { background: #eff6ff; color: #1d4ed8; border-color: #93c5fd; }
        .opsb-generated { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .opsb-done { background: #dcfce7; color: #14532d; border-color: #86efac; }
        .opsb-filters { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; font-size: .8125rem; }
        .opsb-filters select { border: 1px solid var(--opsb-line); border-radius: .5rem; background: var(--opsb-card); color: var(--opsb-text); font-size: .8125rem; padding: .35rem 2rem .35rem .6rem; max-width: 16rem; }
        .opsb-filters label { display: inline-flex; align-items: center; gap: .35rem; color: var(--opsb-muted); cursor: pointer; }
        .opsb-group td { background: var(--opsb-head); font-weight: 600; color: var(--opsb-muted); }
        .opsb-link { margin-top: .2rem; font-size: .75rem; color: var(--opsb-muted); text-decoration: underline; }
        .opsb-history td { background: var(--opsb-head); }
        .opsb-history ul { display: grid; gap: .25rem; }
        .opsb-history time { display: inline-block; min-width: 8.5rem; color: var(--opsb-muted); }
        .opsb-empty { padding: 1.25rem 1rem; color: var(--opsb-muted); font-size: .875rem; }
    </style>

    @php
        $zones = [
            'backlog' => ['Din urmă', 'evenimente încheiate cu ceva încă deschis · urmărite de la ' . $trackFromLabel, 'Nicio restanță din perioadele anterioare.'],
            'period' => ['Evenimentele perioadei', $periodLabel, 'Niciun eveniment în această perioadă.'],
            'upcoming' => ['Urmează, cu scadență acum', 'evenimente de după perioadă, cu cererea de vizare scadentă în perioadă', null],
        ];
    @endphp

    <div class="opsb">
        <div class="opsb-bar">
            <div class="opsb-seg">
                <button type="button" wire:click="setPeriod('week')" @class(['is-on' => $this->period === 'week'])>Săptămână</button>
                <button type="button" wire:click="setPeriod('month')" @class(['is-on' => $this->period === 'month'])>Lună</button>
            </div>
            <button type="button" class="opsb-btn" wire:click="previous" aria-label="Perioada anterioară">←</button>
            <div class="opsb-period">{{ $periodLabel }}</div>
            <button type="button" class="opsb-btn" wire:click="next" aria-label="Perioada următoare">→</button>
            <button type="button" class="opsb-btn" wire:click="goToday">Azi</button>

            <div class="opsb-stats">
                <span class="opsb-stat is-red">{{ $board['counts']['overdue'] }} restante</span>
                <span class="opsb-stat is-amber">{{ $board['counts']['todo'] }} de făcut</span>
                <span class="opsb-stat is-blue">{{ $board['counts']['awaiting_payment'] }} așteaptă plata</span>
            </div>
        </div>

        <div class="opsb-filters">
            <select wire:model.live="organizer" aria-label="Organizator">
                <option value="">Toți organizatorii</option>
                @foreach($board['options']['organizers'] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="registry" aria-label="Primărie">
                <option value="">Toate primăriile</option>
                @foreach($board['options']['registries'] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <label><input type="checkbox" wire:model.live="onlyOpen"> Doar cu ceva de făcut</label>
            <label><input type="checkbox" wire:model.live="byRegistry"> Grupează pe primărie</label>
        </div>

        @foreach($zones as $key => $zone)
            @php
                [$heading, $hint, $emptyText] = $zone;
                $rows = $board[$key];
            @endphp
            @continue(empty($rows) && $emptyText === null)

            <div class="opsb-zone" wire:key="opsb-zone-{{ $key }}">
                <div class="opsb-zone-head">
                    <h3>{{ $heading }} ({{ count($rows) }})</h3>
                    <span>{{ $hint }}</span>
                </div>

                @if(empty($rows))
                    <div class="opsb-empty">{{ $emptyText }}</div>
                @else
                    <div class="opsb-scroll">
                        <table class="opsb-table">
                            <thead>
                                <tr>
                                    <th>Eveniment</th>
                                    @foreach($tasks as $taskLabel)
                                        <th>{{ $taskLabel }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @php $lastRegistry = false; @endphp
                                @foreach($rows as $row)
                                    @if($this->byRegistry && $row['registry'] !== $lastRegistry)
                                        @php $lastRegistry = $row['registry']; @endphp
                                        <tr class="opsb-group" wire:key="opsb-{{ $key }}-group-{{ $row['registry_id'] ?? 0 }}">
                                            <td colspan="{{ count($tasks) + 1 }}">{{ $row['registry'] ?? 'Fără registru fiscal' }}</td>
                                        </tr>
                                    @endif
                                    <tr wire:key="opsb-{{ $key }}-{{ $row['id'] }}">
                                        <td class="opsb-event">
                                            <a href="{{ $row['url'] }}">{{ $row['title'] }}</a>
                                            @if($row['cancelled'])<span class="opsb-tag">Anulat</span>@endif
                                            @if($row['postponed'])<span class="opsb-tag is-postponed">Amânat</span>@endif
                                            <div class="opsb-meta">
                                                {{ $row['date_label'] }}@if($row['place']) · {{ $row['place'] }}@endif
                                            </div>
                                            @if($row['organizer'])
                                                <div class="opsb-meta">{{ $row['organizer'] }}</div>
                                            @endif
                                            @if($row['filing'])
                                                <a href="{{ $row['filing']['url'] }}" @class(['opsb-filing', 'is-missing' => $row['filing']['missing']])>{{ $row['filing']['label'] }}</a>
                                            @endif
                                            <div>
                                                <button type="button" class="opsb-link" wire:click="toggleHistory({{ $row['id'] }})">
                                                    {{ $this->historyFor === $row['id'] ? 'Ascunde istoricul' : 'Istoric' }}
                                                </button>
                                            </div>
                                        </td>
                                        @foreach($tasks as $taskKey => $taskLabel)
                                            @php $cell = $row['cells'][$taskKey]; @endphp
                                            <td>
                                                @if($cell['url'])
                                                    <a href="{{ $cell['url'] }}" class="opsb-cell opsb-{{ $cell['state'] }}">
                                                        <strong>{{ $cell['label'] }}</strong>
                                                        @if($cell['detail'])<small>{{ $cell['detail'] }}</small>@endif
                                                    </a>
                                                @else
                                                    <span class="opsb-cell opsb-{{ $cell['state'] }}">
                                                        <strong>{{ $cell['label'] }}</strong>
                                                        @if($cell['detail'])<small>{{ $cell['detail'] }}</small>@endif
                                                    </span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                    @if($this->historyFor === $row['id'] && $history !== null)
                                        <tr class="opsb-history" wire:key="opsb-{{ $key }}-history-{{ $row['id'] }}">
                                            <td colspan="{{ count($tasks) + 1 }}">
                                                @if(empty($history))
                                                    Nimic înregistrat încă pe acest eveniment.
                                                @else
                                                    <ul>
                                                        @foreach($history as $entry)
                                                            <li><time>{{ $entry['at'] }}</time>{{ $entry['text'] }}@if($entry['by']) · {{ $entry['by'] }}@endif</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
