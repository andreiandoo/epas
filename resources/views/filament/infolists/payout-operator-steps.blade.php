@php
    /**
     * Pașii operatorului pentru un decont (MarketplacePayout::getOperatorSteps():
     * aprobă → decont → factură → factură POS). Se văd toți, în ordine: cei
     * făcuți bifați, următorul evidențiat cu explicația lui dedesubt. Secțiunea
     * dispare când nu mai rămâne nimic de făcut.
     */
    $record = $getRecord();
    $steps = array_values($record->getOperatorSteps());
    $total = count($steps);
    $doneCount = count(array_filter($steps, fn ($s) => !empty($s['done'])));
    $nextIndex = null;
    foreach ($steps as $i => $s) {
        if (empty($s['done'])) { $nextIndex = $i; break; }
    }
    $next = $nextIndex !== null ? $steps[$nextIndex] : null;

    // Butonul pasului: aceeași acțiune ca în pagină (header / Acțiuni).
    $nextAction = null;
    if ($next) {
        $actionName = [
            'approve' => 'approvePayoutStep',
            'generate_decont' => 'generate_decont',
            'generate_invoice' => 'generate_invoice',
            'generate_invoice_organizer' => 'generate_invoice_organizer',
        ][$next['key'] ?? ''] ?? null;
        $lw = $getLivewire();
        if ($actionName && is_object($lw) && method_exists($lw, 'getAction')) {
            try {
                $candidate = $lw->getAction($actionName, false);
                $nextAction = ($candidate && $candidate->isVisible()) ? $candidate : null;
            } catch (\Throwable $e) {
                $nextAction = null;
            }
        }
    }
@endphp

@if($next)
    <div style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;">
            @foreach($steps as $i => $step)
                @php
                    $isDone = !empty($step['done']);
                    $isNext = $i === $nextIndex;
                    $dotBg = $isDone ? '#059669' : ($isNext ? '#2563eb' : 'rgba(127,127,127,.18)');
                    $dotFg = ($isDone || $isNext) ? '#fff' : '#6b7280';
                @endphp
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:9999px;background:{{ $dotBg }};color:{{ $dotFg }};font-size:11px;font-weight:700;flex-shrink:0;">
                        @if($isDone)
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span style="font-size:13px;{{ $isNext ? 'font-weight:600;' : '' }}{{ $isDone ? 'color:#9ca3af;text-decoration:line-through;' : '' }}">{{ $step['label'] }}</span>
                </div>
                @if($i < $total - 1)
                    <span style="width:20px;height:1px;background:rgba(127,127,127,.3);"></span>
                @endif
            @endforeach
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 16px;padding:10px 12px;border-radius:8px;background:rgba(37,99,235,.07);border:1px solid rgba(37,99,235,.25);">
            <div style="min-width:0;flex:1 1 320px;">
                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#2563eb;">Următorul pas · {{ $doneCount }} din {{ $total }} făcuți</div>
                <div style="font-size:13px;font-weight:600;margin-top:2px;">{{ $next['label'] }}</div>
                @if(!empty($next['hint']))
                    <div style="font-size:12px;color:#6b7280;margin-top:2px;">{{ $next['hint'] }}</div>
                @endif
            </div>
            @if($nextAction)
                <div style="flex-shrink:0;">{{ $nextAction }}</div>
            @endif
        </div>
    </div>
@endif
