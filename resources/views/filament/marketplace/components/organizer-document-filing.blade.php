@php
    use App\Services\Marketplace\EventDocumentFilingService as Filing;

    $row = $status['row'];
    $filing = $row['filing'];
    $isLatest = ($row['document']['file_path'] ?? null) === $document->file_path;
    $badge = 'display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;';
@endphp

<div style="font-size:13px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <strong style="font-size:14px;">{{ $row['label'] }}</strong>
        @if($row['filed'])
            <span style="{{ $badge }}background:#dcfce7;color:#14532d;">Depus</span>
        @elseif($filing)
            <span style="{{ $badge }}background:#fef3c7;color:#92400e;">De redepus</span>
        @else
            <span style="{{ $badge }}background:#fef3c7;color:#92400e;">De depus</span>
        @endif
    </div>

    @if($filing)
        <div style="opacity:.75;font-size:12px;">
            {{ $row['filed'] ? 'depus' : 'versiunea anterioară depusă' }}
            {{ Filing::filedStamp($filing, $event) }}
            prin {{ $filing['method_label'] }}@if($filing['by']), de {{ $filing['by'] }}@endif
            @if($row['filed'] && $filing['registration_number'])
                · nr. înregistrare <strong>{{ $filing['registration_number'] }}</strong>
            @endif
        </div>
    @endif

    @if(!$isLatest)
        <div style="margin-top:4px;font-size:12px;color:#b45309;">
            Există o versiune mai nouă a acestui document. Depunerea se marchează pe ultima versiune.
        </div>
    @endif

    @include('filament.marketplace.components.document-filing-actions', [
        'event' => $event,
        'type' => $document->document_type,
        'row' => $row,
        'registry' => $status['registry'],
        'recipient' => $status['recipient'],
    ])
</div>
