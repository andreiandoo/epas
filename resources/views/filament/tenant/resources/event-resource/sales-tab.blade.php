{{-- Tabul „Vânzări” al unui eveniment de tenant. Date: App\Support\Tenant\EventSalesSummary. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $s['currency'];
    $statusLabel = ['paid' => 'Plătită', 'confirmed' => 'Confirmată', 'completed' => 'Finalizată', 'pending' => 'În așteptare', 'cancelled' => 'Anulată', 'failed' => 'Eșuată', 'refunded' => 'Rambursată'];
    $statusStyle = [
        'paid' => 'background:#dcfce7;color:#166534;', 'confirmed' => 'background:#dcfce7;color:#166534;', 'completed' => 'background:#dcfce7;color:#166534;',
        'pending' => 'background:#fef3c7;color:#92400e;', 'cancelled' => 'background:#fee2e2;color:#991b1b;', 'failed' => 'background:#fee2e2;color:#991b1b;',
    ];
@endphp
<style>
    .tsv { --c-card: #ffffff; --c-line: #e5e7eb; --c-text: #111827; --c-muted: #4b5563; --c-soft: #f3f4f6; --c-accent: #4f46e5; display: grid; gap: 20px; color: var(--c-text); }
    .dark .tsv { --c-card: #1f2937; --c-line: #374151; --c-text: #f9fafb; --c-muted: #d1d5db; --c-soft: #374151; --c-accent: #818cf8; }
    .tsv-cards { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); }
    .tsv-card { background: var(--c-card); border: 1px solid var(--c-line); border-radius: 12px; padding: 16px 18px; }
    .tsv-card small { display: block; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--c-muted); }
    .tsv-card b { display: block; margin-top: 6px; font-size: 24px; font-weight: 700; line-height: 1.15; }
    .tsv-card span { display: block; margin-top: 4px; font-size: 13px; color: var(--c-muted); }
    .tsv h3 { font-size: 15px; font-weight: 600; margin-bottom: 10px; }
    .tsv table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .tsv th { text-align: left; padding: 8px 10px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--c-muted); border-bottom: 1px solid var(--c-line); }
    .tsv td { padding: 10px; border-bottom: 1px solid var(--c-line); vertical-align: middle; }
    .tsv .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .tsv-bar { height: 6px; border-radius: 3px; background: var(--c-soft); overflow: hidden; min-width: 90px; }
    .tsv-bar i { display: block; height: 6px; border-radius: 3px; background: var(--c-accent); }
    .tsv-days { display: flex; align-items: flex-end; gap: 6px; height: 120px; padding-top: 8px; }
    .tsv-day { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 6px; height: 100%; min-width: 0; }
    .tsv-day i { display: block; width: 100%; border-radius: 4px 4px 0 0; background: var(--c-accent); }
    .tsv-day em { font-style: normal; font-size: 10px; color: var(--c-muted); white-space: nowrap; }
    .tsv-pill { display: inline-block; padding: 3px 9px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
    .tsv a { color: var(--c-accent); font-weight: 600; text-decoration: none; }
    .tsv a:hover { text-decoration: underline; }
    .tsv-note { font-size: 13px; color: var(--c-muted); }
    .tsv-scroll { overflow-x: auto; }
</style>

<div class="tsv">
    @if(!$s['has_types'])
        <div class="tsv-card">Evenimentul nu are încă tipuri de bilet. Adaugă-le în tabul „Bilete” ca să poată începe vânzarea.</div>
    @else
        <div class="tsv-cards">
            <div class="tsv-card">
                <small>Bilete vândute</small>
                <b>{{ number_format($s['tickets'], 0, ',', '.') }}</b>
                <span>@if($s['capacity'] > 0)din {{ number_format($s['capacity'], 0, ',', '.') }} puse în vânzare @else fără limită de locuri @endif</span>
            </div>
            <div class="tsv-card">
                <small>Încasări</small>
                <b>{{ $money($s['revenue']) }}</b>
                <span>{{ $s['orders'] }} {{ $s['orders'] === 1 ? 'comandă plătită' : 'comenzi plătite' }}</span>
            </div>
            <div class="tsv-card">
                <small>Reduceri acordate</small>
                <b>{{ $money($s['discounts']) }}</b>
                <span>din coduri de reducere</span>
            </div>
            <div class="tsv-card">
                <small>Taxă de procesare</small>
                <b>{{ $money($s['fees']) }}</b>
                <span>{{ $s['fees'] > 0 ? 'plătită de cumpărători' : 'nu e mutată la cumpărător' }}</span>
            </div>
            <div class="tsv-card">
                <small>Comision Tixello</small>
                <b>{{ $money($s['commission']) }}</b>
                <span>{{ rtrim(rtrim(number_format($s['commission_rate'], 2, ',', '.'), '0'), ',') }}% din valoarea biletelor, estimat</span>
            </div>
        </div>

        <div class="tsv-card">
            <h3>Pe tipuri de bilet</h3>
            <div class="tsv-scroll">
                <table>
                    <thead><tr><th>Tip de bilet</th><th class="num">Preț</th><th class="num">Vândute</th><th>Ocupare</th><th class="num">Valoare</th></tr></thead>
                    <tbody>
                        @foreach($s['types'] as $t)
                            <tr>
                                <td>{{ $t['name'] }}@if($t['status'] !== 'active') <span class="tsv-note">· {{ $t['status'] === 'hidden' ? 'ascuns' : $t['status'] }}</span>@endif</td>
                                <td class="num">{{ $money($t['price']) }}</td>
                                <td class="num">{{ $t['sold'] }}@if($t['capacity']) / {{ $t['capacity'] }}@endif</td>
                                <td>@if($t['percent'] !== null)<div class="tsv-bar"><i style="width: {{ $t['percent'] }}%"></i></div>@else<span class="tsv-note">fără limită</span>@endif</td>
                                <td class="num">{{ $money($t['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="tsv-note" style="margin-top:10px">Valoarea e la prețul de listă: {{ $money($s['gross']) }} în total, înainte de reduceri.</p>
        </div>

        <div class="tsv-card">
            <h3>Încasări în ultimele 14 zile</h3>
            <div class="tsv-days">
                @foreach($s['days'] as $d)
                    <div class="tsv-day" title="{{ $d['label'] }}: {{ $money($d['total']) }} · {{ $d['orders'] }} comenzi">
                        <i style="height: {{ $d['height'] }}%; @if($d['total'] <= 0) opacity:.25; @endif"></i>
                        <em>{{ $d['label'] }}</em>
                    </div>
                @endforeach
            </div>
            <p class="tsv-note" style="margin-top:10px">
                @if($s['last_sale'])Ultima vânzare: {{ $s['last_sale'] }}.@else Încă nu există comenzi plătite.@endif
                @if($s['pending'] > 0) {{ $s['pending'] }} {{ $s['pending'] === 1 ? 'comandă e' : 'comenzi sunt' }} în așteptarea plății.@endif
            </p>
        </div>

        <div class="tsv-card">
            <h3>Ultimele comenzi</h3>
            @if(empty($s['recent']))
                <p class="tsv-note">Nicio comandă încă.</p>
            @else
                <div class="tsv-scroll">
                    <table>
                        <thead><tr><th>Comandă</th><th>Client</th><th>Data</th><th>Stare</th><th class="num">Total</th></tr></thead>
                        <tbody>
                            @foreach($s['recent'] as $o)
                                <tr>
                                    <td><a href="{{ url('/tenant/orders/' . $o['id']) }}">#{{ str_pad((string) $o['id'], 6, '0', STR_PAD_LEFT) }}</a></td>
                                    <td>{{ $o['name'] !== '' ? $o['name'] : $o['email'] }}@if($o['name'] !== '')<br><span class="tsv-note">{{ $o['email'] }}</span>@endif</td>
                                    <td>{{ $o['date'] }}</td>
                                    <td><span class="tsv-pill" style="{{ $statusStyle[$o['status']] ?? 'background:#e5e7eb;color:#374151;' }}">{{ $statusLabel[$o['status']] ?? $o['status'] }}</span></td>
                                    <td class="num">{{ $money($o['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p style="margin-top:12px"><a href="{{ url('/tenant/orders') }}">Toate comenzile</a></p>
            @endif
        </div>
    @endif
</div>
