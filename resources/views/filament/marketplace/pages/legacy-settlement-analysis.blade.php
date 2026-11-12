@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $verdicts = [
        'ok' => ['Se pot aproba', 'Ciornele vechi trec în „Aprobat”, cu suma adusă la netul evenimentului. Plata rămâne de finalizat manual, din pagina de sold.', '#059669'],
        'warn' => ['Se pot aproba, cu ajustări de verificat', 'La unele evenimente suma ciornei se schimbă cu peste ' . number_format($warnLimit, 0, ',', '.') . ' lei (marcate mai jos). Aprobarea cere confirmare.', '#d97706'],
        'blocked' => ['Blocat', 'Sunt probleme de lămurit înainte de aprobare (lista de mai jos). Nu se modifică nimic la acest organizator.', '#dc2626'],
        'settled' => ['Aprobat', 'Ciornele vechi au fost aprobate; plățile se finalizează din pagina de sold.', '#2563eb'],
        'none' => ['Nimic de aprobat', 'Nu are ciorne „În așteptare” pe evenimente încheiate până pe ' . $cutoff . '.', '#6b7280'],
    ];
    [$vTitle, $vText, $vColor] = $verdicts[$a['verdict']] ?? $verdicts['none'];
@endphp

<div class="ep-ls">
    <div class="ep-ls__verdict" style="border-color: {{ $vColor }}">
        <div class="ep-ls__verdict-title" style="color: {{ $vColor }}">{{ $vTitle }}</div>
        <div class="ep-ls__muted">{{ $vText }}</div>
        @if ($a['last_run'])
            <div class="ep-ls__muted ep-ls__small">
                Ultima acțiune: {{ $a['last_run']['action'] === 'settled' ? 'aprobat' : 'aprobare anulată' }}
                {{ $a['last_run']['at'] }}@if ($a['last_run']['by']) de {{ $a['last_run']['by'] }}@endif.
            </div>
        @endif
    </div>

    <div class="ep-ls__cards">
        <div class="ep-ls__card">
            <div class="ep-ls__label">Sold acum</div>
            <div class="ep-ls__value">{{ $fmt($a['balance']['available']) }} lei</div>
            <div class="ep-ls__muted ep-ls__small">în procesare {{ $fmt($a['balance']['pending']) }} · plătit {{ $fmt($a['balance']['paid']) }}</div>
        </div>
        <div class="ep-ls__card">
            <div class="ep-ls__label">Ciorne de aprobat</div>
            <div class="ep-ls__value">{{ $a['totals']['payouts'] }}</div>
            <div class="ep-ls__muted ep-ls__small">
                {{ $a['totals']['moved'] }} mutate de pe alt organizator · {{ $a['totals']['skipped'] }} dubluri sărite
            </div>
        </div>
        <div class="ep-ls__card">
            <div class="ep-ls__label">Intră „în procesare”</div>
            <div class="ep-ls__value">{{ $fmt($a['totals']['settle_amount']) }} lei</div>
            <div class="ep-ls__muted ep-ls__small">ciorne {{ $fmt($a['totals']['draft_amount']) }} · ajustare {{ $fmt($a['totals']['settle_amount'] - $a['totals']['draft_amount']) }}</div>
        </div>
        <div class="ep-ls__card">
            <div class="ep-ls__label">Sold disponibil pe aceste evenimente</div>
            <div class="ep-ls__value">{{ $fmt($a['totals']['balance_before']) }} → {{ $fmt($a['totals']['balance_after']) }}</div>
            <div class="ep-ls__muted ep-ls__small">acum → după aprobare</div>
        </div>
    </div>

    @if (! empty($a['issues']))
        <div class="ep-ls__issues">
            <div class="ep-ls__section">Probleme de lămurit ({{ count($a['issues']) }})</div>
            <ul>
                @foreach ($a['issues'] as $issue)
                    <li><strong>#{{ $issue['event_id'] }} {{ $issue['event'] }}:</strong> {{ $issue['text'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($a['events']))
        <div class="ep-ls__section">Evenimente vizate</div>
        <div class="ep-ls__table-wrap">
            <table class="ep-ls__table">
                <thead>
                    <tr>
                        <th>Eveniment</th>
                        <th>Ciornă</th>
                        <th class="num">Net</th>
                        <th class="num">Plătit / în procesare</th>
                        <th class="num">Ciornă</th>
                        <th class="num">Se aprobă</th>
                        <th class="num">Disponibil acum → după</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($a['events'] as $e)
                        @php($ap = $e['approve'])
                        <tr @class(['is-blocked' => ! empty($e['issues']), 'is-warn' => empty($e['issues']) && $e['warn']])>
                            <td>
                                <a href="{{ \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $e['event_id']]) }}" target="_blank" class="ep-ls__link">#{{ $e['event_id'] }} {{ $e['title'] }}</a>
                                <div class="ep-ls__muted ep-ls__small">final {{ $e['date'] ?? 'fără dată' }}</div>
                            </td>
                            <td>
                                @if ($ap)
                                    <a href="{{ \App\Filament\Marketplace\Resources\PayoutResource::getUrl('view', ['record' => $ap['id']]) }}" target="_blank" class="ep-ls__link">{{ $ap['label'] }}</a>
                                    <div class="ep-ls__muted ep-ls__small">creată {{ $ap['created_at'] }}</div>
                                    @if ($ap['from_organizer_id'] !== $a['organizer_id'])
                                        <div class="ep-ls__small" style="color: #2563eb">se mută de pe #{{ $ap['from_organizer_id'] }} {{ $ap['from_organizer'] }}</div>
                                    @endif
                                @else
                                    <span class="ep-ls__muted">—</span>
                                @endif
                                @foreach ($e['skipped'] as $s)
                                    <div class="ep-ls__muted ep-ls__small">sărită: {{ $s }}</div>
                                @endforeach
                            </td>
                            <td class="num">{{ $fmt($e['net']) }}</td>
                            <td class="num">{{ $fmt($e['paid']) }} / {{ $fmt($e['in_flight']) }}</td>
                            <td class="num">{{ $ap ? $fmt($ap['draft_amount']) : '—' }}</td>
                            <td class="num">
                                @if ($ap && empty($e['issues']))
                                    {{ $fmt($ap['amount']) }}
                                    @if (abs($ap['adjustment']) >= 0.01)
                                        <div class="ep-ls__small" style="color: {{ $e['warn'] ? '#d97706' : '#6b7280' }}">{{ $ap['adjustment'] > 0 ? '+' : '' }}{{ $fmt($ap['adjustment']) }}</div>
                                    @endif
                                @else
                                    <span class="ep-ls__muted">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $fmt($e['balance_before']) }} → {{ $fmt($e['balance_after']) }}</td>
                        </tr>
                        @if (! empty($e['compare']))
                            @php($c = $e['compare'])
                            <tr @class(['ep-ls__why-row', 'is-warn' => $e['warn']])>
                                <td colspan="7">
                                    <details class="ep-ls__why" @if ($e['warn']) open @endif>
                                        <summary>De unde vine diferența de {{ $ap['adjustment'] > 0 ? '+' : '' }}{{ $fmt($ap['adjustment']) }} lei</summary>
                                        <div class="ep-ls__why-grid">
                                            <table class="ep-ls__mini">
                                                <thead>
                                                    <tr><th></th><th class="num">Ciorna ({{ $c['draft']['created_at'] }})</th><th class="num">Acum</th><th class="num">Diferență</th></tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td>Bilete (fără POS)</td><td class="num">{{ $c['draft']['qty'] ?: '—' }}</td><td class="num">{{ $c['now']['qty'] }}</td><td class="num">{{ $c['draft']['qty'] ? $c['now']['qty'] - $c['draft']['qty'] : '—' }}</td></tr>
                                                    <tr><td>Vânzări brute</td><td class="num">{{ $fmt($c['draft']['gross']) }}</td><td class="num">{{ $fmt($c['now']['gross']) }}</td><td class="num">{{ $fmt($c['now']['gross'] - $c['draft']['gross']) }}</td></tr>
                                                    <tr><td>Comision Ambilet</td><td class="num">−{{ $fmt($c['draft']['commission']) }}<div class="ep-ls__muted ep-ls__small">{{ $c['draft']['rate'] !== null ? rtrim(rtrim(number_format($c['draft']['rate'], 2, ',', ''), '0'), ',') . '%' : '' }}</div></td><td class="num">−{{ $fmt($c['now']['commission']) }}<div class="ep-ls__muted ep-ls__small">{{ implode(', ', $c['now']['rates']) }}</div></td><td class="num">{{ $fmt($c['draft']['commission'] - $c['now']['commission']) }}</td></tr>
                                                    <tr><td>Reduceri</td><td class="num">—</td><td class="num">−{{ $fmt($c['now']['discount']) }}</td><td class="num">{{ $fmt(-$c['now']['discount']) }}</td></tr>
                                                    <tr class="is-total"><td>Net</td><td class="num">{{ $fmt($c['draft']['net']) }}</td><td class="num">{{ $fmt($c['now']['net']) }}</td><td class="num">{{ $fmt($c['now']['net'] - $c['draft']['net']) }}</td></tr>
                                                    <tr><td>Deja plătit / în procesare</td><td class="num"></td><td class="num">−{{ $fmt($c['paid']) }}</td><td class="num"></td></tr>
                                                    <tr class="is-total"><td>Se aprobă</td><td class="num">{{ $fmt($c['draft']['net']) }}</td><td class="num">{{ $fmt($c['target']) }}</td><td class="num">{{ $fmt($ap['adjustment']) }}</td></tr>
                                                </tbody>
                                            </table>
                                            <div>
                                                <div class="ep-ls__muted ep-ls__small">
                                                    Comenzi plătite acum: {{ $c['orders']['count'] }} ({{ $fmt($c['orders']['total']) }} lei),
                                                    din care POS {{ $c['orders']['pos_count'] }} ({{ $fmt($c['orders']['pos_total']) }} lei).
                                                    Rambursate / anulate: {{ $c['orders']['lost_count'] }} ({{ $fmt($c['orders']['lost_total']) }} lei).
                                                </div>
                                                @if (! empty($c['why']))
                                                    <ul class="ep-ls__why-list">
                                                        @foreach ($c['why'] as $line)
                                                            <li>{{ $line }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <div class="ep-ls__muted ep-ls__small">Diferență de rotunjire între calculul pe comenzi (ciorna) și cel pe bilete (acum).</div>
                                                @endif
                                            </div>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endif
                        @if (! empty($e['issues']))
                            <tr class="is-blocked">
                                <td colspan="7" class="ep-ls__small" style="color: #dc2626">{{ implode(' ', $e['issues']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="ep-ls__muted ep-ls__small">
        Analizat {{ $a['analyzed_at'] }}. Net = vânzările evenimentului fără POS, cu vânzările importate din sistemul vechi
        (după aprobare ele intră în sold, iar ciorna aprobată le acoperă).
        La aprobare, ciorna trece pe organizatorul evenimentului, primește suma „Se aprobă” (netul minus ce e deja plătit sau în procesare)
        și nota „{{ \App\Services\Marketplace\LegacySettlementService::NOTE }}”. Fără PDF și fără compensare din avans;
        PDF-ul se generează când se finalizează plata. Dublurile rămân „În așteptare”.
    </div>
</div>

<style>
    .ep-ls { display: flex; flex-direction: column; gap: 1rem; font-size: .875rem; }
    .ep-ls__verdict { padding: .75rem 1rem; border-left: 4px solid; border-radius: .5rem; background: rgba(107, 114, 128, .06); }
    .ep-ls__verdict-title { font-weight: 700; font-size: 1rem; }
    .ep-ls__muted { color: #6b7280; }
    .dark .ep-ls__muted { color: #9ca3af; }
    .ep-ls__small { font-size: .75rem; }
    .ep-ls__cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .75rem; }
    .ep-ls__card { padding: .75rem 1rem; border-radius: .75rem; border: 1px solid rgba(3, 7, 18, .08); }
    .dark .ep-ls__card { border-color: rgba(255, 255, 255, .1); }
    .ep-ls__label { font-size: .75rem; color: #6b7280; }
    .ep-ls__value { font-size: 1.05rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .ep-ls__section { font-weight: 600; margin-bottom: .25rem; }
    .ep-ls__issues { padding: .75rem 1rem; border-radius: .5rem; background: rgba(220, 38, 38, .06); }
    .ep-ls__issues ul { margin: 0; padding-left: 1.1rem; list-style: disc; }
    .ep-ls__issues li { margin: .25rem 0; }
    .ep-ls__table-wrap { overflow-x: auto; }
    .ep-ls__table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
    .ep-ls__table th { text-align: left; font-weight: 600; font-size: .75rem; color: #6b7280; padding: .4rem .5rem; border-bottom: 1px solid rgba(107, 114, 128, .25); white-space: nowrap; }
    .ep-ls__table td { padding: .4rem .5rem; border-bottom: 1px solid rgba(107, 114, 128, .12); vertical-align: top; }
    .ep-ls__table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ep-ls__table tr.is-blocked td { background: rgba(220, 38, 38, .05); }
    .ep-ls__table tr.is-warn td { background: rgba(217, 119, 6, .06); }
    .ep-ls__why summary { cursor: pointer; font-size: .75rem; font-weight: 600; color: #2563eb; }
    .ep-ls__why-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 1rem; margin-top: .5rem; }
    @media (max-width: 900px) { .ep-ls__why-grid { grid-template-columns: 1fr; } }
    .ep-ls__mini { width: 100%; border-collapse: collapse; font-size: .75rem; }
    .ep-ls__mini th { text-align: left; font-weight: 600; color: #6b7280; padding: .25rem .4rem; border-bottom: 1px solid rgba(107, 114, 128, .25); }
    .ep-ls__mini td { padding: .25rem .4rem; border-bottom: 1px solid rgba(107, 114, 128, .1); background: transparent !important; }
    .ep-ls__mini .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ep-ls__mini tr.is-total td { font-weight: 700; }
    .ep-ls__why-list { margin: .5rem 0 0; padding-left: 1.1rem; list-style: disc; font-size: .75rem; }
    .ep-ls__why-list li { margin: .2rem 0; }
    .ep-ls__link { color: rgb(37, 99, 235); }
    .ep-ls__link:hover { text-decoration: underline; }
</style>
