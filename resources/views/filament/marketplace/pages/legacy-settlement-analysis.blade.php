@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $verdicts = [
        'ok' => ['Se poate rezolva', 'Toate deconturile vechi sunt pe organizatorul evenimentului, fără dubluri. După rezolvare soldul acestor evenimente e 0.', '#059669'],
        'warn' => ['Se poate rezolva, cu ajustări de verificat', 'La unele evenimente suma ciornei se schimbă cu peste ' . number_format($warnLimit, 0, ',', '.') . ' lei (marcate mai jos). Rezolvarea cere confirmare.', '#d97706'],
        'blocked' => ['Blocat', 'Sunt probleme de lămurit înainte de rezolvare (lista de mai jos). Nu se modifică nimic la acest organizator.', '#dc2626'],
        'settled' => ['Rezolvat', 'Nu mai are deconturi vechi deschise; au fost rezolvate.', '#2563eb'],
        'none' => ['Nimic de rezolvat', 'Nu are deconturi deschise pe evenimente încheiate până pe ' . $cutoff . '.', '#6b7280'],
    ];
    [$vTitle, $vText, $vColor] = $verdicts[$a['verdict']] ?? $verdicts['none'];
    $statusLabels = ['pending' => 'în așteptare', 'approved' => 'aprobat', 'processing' => 'în procesare'];
@endphp

<div class="ep-ls">
    <div class="ep-ls__verdict" style="border-color: {{ $vColor }}">
        <div class="ep-ls__verdict-title" style="color: {{ $vColor }}">{{ $vTitle }}</div>
        <div class="ep-ls__muted">{{ $vText }}</div>
        @if ($a['last_run'])
            <div class="ep-ls__muted ep-ls__small">
                Ultima acțiune: {{ $a['last_run']['action'] === 'settled' ? 'rezolvat' : 'rezolvare anulată' }}
                {{ $a['last_run']['at'] }}@if ($a['last_run']['by']) de {{ $a['last_run']['by'] }}@endif.
            </div>
        @endif
    </div>

    <div class="ep-ls__cards">
        <div class="ep-ls__card">
            <div class="ep-ls__label">Sold disponibil acum</div>
            <div class="ep-ls__value">{{ $fmt($a['balance']['available']) }} lei</div>
            <div class="ep-ls__muted ep-ls__small">în procesare {{ $fmt($a['balance']['pending']) }} · plătit {{ $fmt($a['balance']['paid']) }}</div>
        </div>
        <div class="ep-ls__card">
            <div class="ep-ls__label">Evenimente vechi cu deconturi deschise</div>
            <div class="ep-ls__value">{{ $a['totals']['events'] }}</div>
            <div class="ep-ls__muted ep-ls__small">{{ $a['totals']['payouts'] }} deconturi · ciorne {{ $fmt($a['totals']['draft_amount']) }} lei</div>
        </div>
        <div class="ep-ls__card">
            <div class="ep-ls__label">Se marchează plătit</div>
            <div class="ep-ls__value">{{ $fmt($a['totals']['settle_amount']) }} lei</div>
            <div class="ep-ls__muted ep-ls__small">ajustare față de ciorne {{ $fmt($a['totals']['settle_amount'] - $a['totals']['draft_amount']) }} lei</div>
        </div>
        <div class="ep-ls__card">
            <div class="ep-ls__label">Sold pe aceste evenimente</div>
            <div class="ep-ls__value">{{ $fmt($a['totals']['balance_before']) }} → {{ $fmt($a['totals']['balance_after']) }}</div>
            <div class="ep-ls__muted ep-ls__small">acum → după rezolvare</div>
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
                        <th>Decont</th>
                        <th class="num">Net</th>
                        <th class="num">Plătit deja</th>
                        <th class="num">Ciornă</th>
                        <th class="num">Se decontează</th>
                        <th class="num">Sold acum → după</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($a['events'] as $e)
                        <tr @class(['is-blocked' => ! empty($e['issues']), 'is-warn' => empty($e['issues']) && $e['warn']])>
                            <td>
                                <a href="{{ \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $e['event_id']]) }}" target="_blank" class="ep-ls__link">#{{ $e['event_id'] }} {{ $e['title'] }}</a>
                                <div class="ep-ls__muted ep-ls__small">final {{ $e['date'] ?? 'fără dată' }}</div>
                            </td>
                            <td>
                                @foreach ($e['payouts'] as $p)
                                    <div>
                                        <a href="{{ \App\Filament\Marketplace\Resources\PayoutResource::getUrl('view', ['record' => $p['id']]) }}" target="_blank" class="ep-ls__link">{{ $p['label'] }}</a>
                                        <span class="ep-ls__muted ep-ls__small">{{ $statusLabels[$p['status']] ?? $p['status'] }}, {{ $p['created_at'] }}@if ($p['organizer_id'] !== $a['organizer_id']), org #{{ $p['organizer_id'] }}@endif</span>
                                    </div>
                                @endforeach
                            </td>
                            <td class="num">{{ $fmt($e['net']) }}</td>
                            <td class="num">{{ $fmt($e['paid']) }}</td>
                            <td class="num">{{ $fmt($e['draft_amount']) }}</td>
                            <td class="num">
                                @if (empty($e['issues']))
                                    {{ $fmt($e['settle_amount']) }}
                                    @if (abs($e['adjustment']) >= 0.01)
                                        <div class="ep-ls__small" style="color: {{ $e['warn'] ? '#d97706' : '#6b7280' }}">{{ $e['adjustment'] > 0 ? '+' : '' }}{{ $fmt($e['adjustment']) }}</div>
                                    @endif
                                @else
                                    <span class="ep-ls__muted">—</span>
                                @endif
                            </td>
                            <td class="num">{{ $fmt($e['balance_before']) }} → {{ $fmt($e['balance_after']) }}</td>
                        </tr>
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
        Analizat {{ $a['analyzed_at'] }}. Net = vânzările evenimentului fără POS, cu vânzările importate din sistemul vechi.
        La rezolvare, ciorna primește suma „Se decontează” (netul minus ce s-a plătit deja), devine plătită cu data ciornei
        și referința „{{ \App\Services\Marketplace\LegacySettlementService::REFERENCE }}”. Fără email, fără PDF, fără compensare din avans.
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
    .ep-ls__link { color: rgb(var(--primary-600, 37 99 235)); }
    .ep-ls__link:hover { text-decoration: underline; }
</style>
