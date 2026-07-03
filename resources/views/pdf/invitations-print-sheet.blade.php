@php
    // Paper size in mm. DomPDF's actual @page comes from the controller.
    $paperMm = match ($paper) {
        'A3' => ['w' => 297, 'h' => 420],
        'A5' => ['w' => 148, 'h' => 210],
        default => ['w' => 210, 'h' => 297],
    };
    if ($orientation === 'landscape') {
        [$paperMm['w'], $paperMm['h']] = [$paperMm['h'], $paperMm['w']];
    }
    $innerW = $paperMm['w'] - 2 * $bleedXMm;
    $innerH = $paperMm['h'] - 2 * $bleedYMm;
    $tileW = $innerW / $cols;
    $tileH = $innerH / $rows;

    $useTemplate = !empty($renderedHtmls);

    // Fallback simple-layout metadata.
    $eventTitle = is_array($event->title)
        ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?? '')
        : ($event->title ?? '');
    $eventDate = $event->event_date
        ? \Carbon\Carbon::parse($event->event_date)->translatedFormat('d F Y')
        : '';
    if ($event->start_time) {
        $eventDate .= ' · ' . substr($event->start_time, 0, 5);
    }
    $venueName = $event->venue?->name ?? '';
    if (is_array($venueName)) {
        $venueName = $venueName['ro'] ?? $venueName['en'] ?? reset($venueName) ?? '';
    }
    $venueCity = $event->venue?->city ?? '';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        * { box-sizing: border-box; font-family: 'DejaVu Sans', sans-serif; }
        body { background: #fff; color: #1f2937; }

        /* Each .page fills the paper exactly. Absolute-positioned .tile
           children sit inside a positioned parent, so DomPDF only sees ONE
           block per page in the flow and paginates cleanly. */
        .page {
            width: {{ $paperMm['w'] }}mm;
            height: {{ $paperMm['h'] }}mm;
            position: relative;
            overflow: hidden;
        }
        .page + .page { page-break-before: always; }

        /* Simple-fallback tile styles (used when no template available). */
        .simple { width: 100%; height: 100%; padding: 4mm; position: relative; }
        .simple-header { border-bottom: 1pt solid #4f46e5; padding-bottom: 2mm; margin-bottom: 3mm; }
        .simple-watermark { font-size: 6pt; letter-spacing: 2pt; color: #4f46e5; text-transform: uppercase; font-weight: bold; }
        .simple-event-title { font-size: 10pt; font-weight: bold; color: #1f2937; margin-top: 1mm; }
        .simple-event-meta { font-size: 7pt; color: #6b7280; margin-top: 1mm; line-height: 1.4; }
        .simple-body { display: table; width: 100%; margin-top: 3mm; }
        .simple-left { display: table-cell; vertical-align: middle; width: 55%; padding-right: 3mm; }
        .simple-right { display: table-cell; vertical-align: middle; width: 45%; text-align: right; }
        .simple-recip-label { font-size: 6pt; color: #9ca3af; text-transform: uppercase; letter-spacing: 1pt; }
        .simple-recip-name { font-size: 9pt; font-weight: bold; color: #111827; margin-top: 0.5mm; }
        .simple-code-label { font-size: 6pt; color: #9ca3af; text-transform: uppercase; letter-spacing: 1pt; margin-top: 2mm; }
        .simple-code { font-family: 'Courier', monospace; font-size: 8pt; font-weight: bold; color: #111827; margin-top: 0.5mm; word-break: break-all; }
        .simple-qr { width: 22mm; height: 22mm; display: block; margin-left: auto; }
    </style>
</head>
<body>
@foreach($pages as $pageInvites)
    <div class="page">
        @foreach($pageInvites as $i => $invite)
            @php
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $tileLeft = $bleedXMm + $col * $tileW;
                $tileTop = $bleedYMm + $row * $tileH;
                $renderedHtml = $useTemplate ? ($renderedHtmls[$invite->id] ?? null) : null;
            @endphp
            <div style="position:absolute; left:{{ $tileLeft }}mm; top:{{ $tileTop }}mm; width:{{ $tileW }}mm; height:{{ $tileH }}mm; overflow:hidden; background:{{ $templateBg ?? '#ffffff' }}; border: 0.3pt dashed #d1d5db;">
                @if($renderedHtml)
                    {!! $renderedHtml !!}
                @else
                    @php
                        $recipient = is_array($invite->recipient) ? $invite->recipient : [];
                        $recipientName = trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''));
                        if ($recipientName === '') $recipientName = $recipient['name'] ?? '';
                        $qrData = $invite->qr_data ?: $invite->invite_code;
                        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                            'size' => '200x200',
                            'data' => $qrData,
                            'margin' => '2',
                            'ecc' => 'M',
                        ]);
                    @endphp
                    <div class="simple">
                        <div class="simple-header">
                            <div class="simple-watermark">Invitație</div>
                            <div class="simple-event-title">{{ $eventTitle }}</div>
                            <div class="simple-event-meta">
                                @if($eventDate){{ $eventDate }}@endif
                                @if($venueName)<br>{{ $venueName }}@if($venueCity), {{ $venueCity }}@endif @endif
                            </div>
                        </div>
                        <div class="simple-body">
                            <div class="simple-left">
                                @if($recipientName !== '')
                                    <div class="simple-recip-label">Invitat</div>
                                    <div class="simple-recip-name">{{ $recipientName }}</div>
                                @endif
                                <div class="simple-code-label">Cod</div>
                                <div class="simple-code">{{ $invite->invite_code }}</div>
                            </div>
                            <div class="simple-right">
                                <img class="simple-qr" src="{{ $qrUrl }}" alt="QR">
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
