@php
    /** @var array $diff  EventLiveEditService::diffPending() */
    /** @var \App\Models\Event $event */
    $rows = $diff['rows'] ?? [];
    $submitted = $event->pending_changes_submitted_at;
@endphp

<style>
    .epd { font-size: 13px; line-height: 1.5; color: #374151; }
    .dark .epd { color: #d1d5db; }
    .epd-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-bottom: 12px; color: #6b7280; font-size: 12px; }
    .dark .epd-meta { color: #9ca3af; }
    .epd-note { padding: 8px 12px; border-radius: 8px; background: #fffbeb; color: #92400e; border: 1px solid #fde68a; margin-bottom: 12px; font-size: 12px; }
    .dark .epd-note { background: rgba(245, 158, 11, .12); color: #fcd34d; border-color: rgba(245, 158, 11, .3); }
    .epd-scroll { max-height: 62vh; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 10px; }
    .dark .epd-scroll { border-color: rgba(255, 255, 255, .1); }
    .epd table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .epd th { position: sticky; top: 0; z-index: 1; background: #f9fafb; text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
    .dark .epd th { background: #18181b; color: #9ca3af; border-color: rgba(255, 255, 255, .1); }
    .epd td { vertical-align: top; padding: 10px 12px; border-bottom: 1px solid #f3f4f6; word-wrap: break-word; overflow-wrap: anywhere; }
    .dark .epd td { border-color: rgba(255, 255, 255, .06); }
    .epd tr:last-child td { border-bottom: 0; }
    .epd-label { font-weight: 600; color: #111827; }
    .dark .epd-label { color: #f3f4f6; }
    .epd-old, .epd-new { border-radius: 6px; padding: 4px 8px; display: inline-block; max-width: 100%; }
    .epd-old { background: #fef2f2; color: #991b1b; text-decoration: line-through; }
    .epd-new { background: #f0fdf4; color: #166534; }
    .dark .epd-old { background: rgba(239, 68, 68, .12); color: #fca5a5; }
    .dark .epd-new { background: rgba(34, 197, 94, .12); color: #86efac; }
    .epd-empty { color: #9ca3af; font-style: italic; }
    .epd-text { max-height: 260px; overflow-y: auto; padding: 8px 10px; border-radius: 6px; background: #f9fafb; border: 1px solid #f3f4f6; }
    .dark .epd-text { background: rgba(255, 255, 255, .03); border-color: rgba(255, 255, 255, .06); }
    .epd-text del { background: #fee2e2; color: #991b1b; text-decoration: line-through; border-radius: 3px; }
    .epd-text ins { background: #dcfce7; color: #166534; text-decoration: none; border-radius: 3px; }
    .dark .epd-text del { background: rgba(239, 68, 68, .22); color: #fca5a5; }
    .dark .epd-text ins { background: rgba(34, 197, 94, .22); color: #86efac; }
    .epd-chip { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
    .epd-chip--add { background: #dcfce7; color: #166534; }
    .epd-chip--rem { background: #fee2e2; color: #991b1b; text-decoration: line-through; }
    .dark .epd-chip--add { background: rgba(34, 197, 94, .18); color: #86efac; }
    .dark .epd-chip--rem { background: rgba(239, 68, 68, .18); color: #fca5a5; }
    .epd-flag { display: block; margin-top: 4px; font-size: 11px; font-weight: 500; color: #b45309; }
    .dark .epd-flag { color: #fbbf24; }
    .epd-hint { display: block; margin-top: 4px; font-size: 11px; color: #6b7280; }
    .epd-tickets { width: 100%; }
    .epd-tickets td { padding: 4px 8px 4px 0; border: 0; }
    .epd-none { padding: 24px; text-align: center; color: #6b7280; }
</style>

<div class="epd">
    <div class="epd-meta">
        <span>Eveniment: <strong>{{ $event->getTranslation('title', 'ro') }}</strong></span>
        @if ($submitted)
            <span>Trimis de organizator: <strong>{{ $submitted->timezone('Europe/Bucharest')->format('d.m.Y H:i') }}</strong></span>
        @endif
        <span>{{ count($rows) }} {{ count($rows) === 1 ? 'modificare' : 'modificări' }}</span>
    </div>

    @unless ($diff['has_base'] ?? false)
        <div class="epd-note">
            Modificările au fost trimise înainte să se rețină starea evenimentului la trimitere, așa că sunt comparate cu varianta live de acum.
            Dacă evenimentul a fost editat din admin între timp, pot apărea și câmpuri pe care organizatorul nu le-a atins.
        </div>
    @endunless

    @if (empty($rows))
        <div class="epd-scroll"><div class="epd-none">Organizatorul nu a modificat nimic față de varianta live. La aprobare doar se închide cererea.</div></div>
    @else
        <div class="epd-scroll">
            <table>
                <colgroup>
                    <col style="width: 16%">
                    <col style="width: 42%">
                    <col style="width: 42%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Câmp</th>
                        <th>Acum (live)</th>
                        <th>După aprobare</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <span class="epd-label">{{ $row['label'] }}</span>
                                @if (!empty($row['conflict']))
                                    <span class="epd-flag">Modificat și din admin după trimitere — se va suprascrie</span>
                                @endif
                                @if (!empty($row['format_only']))
                                    <span class="epd-hint">Doar formatarea textului diferă</span>
                                @endif
                            </td>

                            @if ($row['type'] === 'text')
                                <td>
                                    @if ($row['before'] === '')
                                        <span class="epd-empty">gol</span>
                                    @else
                                        <div class="epd-text">{!! $row['before_html'] !!}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['after'] === '')
                                        <span class="epd-empty">gol (se șterge)</span>
                                    @else
                                        <div class="epd-text">{!! $row['after_html'] !!}</div>
                                    @endif
                                </td>
                            @elseif ($row['type'] === 'set')
                                <td colspan="2">
                                    @foreach ($row['removed'] as $name)
                                        <span class="epd-chip epd-chip--rem">{{ $name }}</span>
                                    @endforeach
                                    @foreach ($row['added'] as $name)
                                        <span class="epd-chip epd-chip--add">+ {{ $name }}</span>
                                    @endforeach
                                </td>
                            @elseif ($row['type'] === 'tickets')
                                <td><span class="epd-empty">—</span></td>
                                <td>
                                    <table class="epd-tickets">
                                        @foreach ($row['tickets'] as $t)
                                            <tr>
                                                <td><span class="epd-new">{{ $t['name'] }}</span></td>
                                                <td>{{ number_format((float) ($t['price'] ?? 0), 2, ',', '.') }} lei</td>
                                                <td>{{ !empty($t['quantity']) ? $t['quantity'] . ' buc.' : 'nelimitat' }}</td>
                                                <td>
                                                    @if (!empty($t['pos']['enabled']))
                                                        + variantă POS{{ !empty($t['pos']['price']) ? ' (' . number_format((float) $t['pos']['price'], 2, ',', '.') . ' lei)' : '' }}
                                                    @endif
                                                </td>
                                            </tr>
                                            @if (!empty($t['description']))
                                                <tr><td colspan="4" class="epd-hint" style="padding-top:0">{{ $t['description'] }}</td></tr>
                                            @endif
                                        @endforeach
                                    </table>
                                </td>
                            @else
                                <td>
                                    @if ($row['before'] === '')
                                        <span class="epd-empty">gol</span>
                                    @else
                                        <span class="epd-old">{{ $row['before'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['after'] === '')
                                        <span class="epd-empty">gol (se șterge)</span>
                                    @else
                                        <span class="epd-new">{{ $row['after'] }}</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
