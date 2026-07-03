@php
    // Paper sizes in mm — DomPDF handles the actual @page sizing via
    // setPaper() in the controller; these are used only for CSS box
    // sizing so the tile grid fills the printable area minus bleed.
    $paperMm = match ($paper) {
        'A3' => ['w' => 297, 'h' => 420],
        'A5' => ['w' => 148, 'h' => 210],
        default => ['w' => 210, 'h' => 297], // A4
    };
    if ($orientation === 'landscape') {
        [$paperMm['w'], $paperMm['h']] = [$paperMm['h'], $paperMm['w']];
    }
    $innerW = $paperMm['w'] - 2 * $bleedMm;
    $innerH = $paperMm['h'] - 2 * $bleedMm;
    $tileW = $innerW / $cols;
    $tileH = $innerH / $rows;

    // Event title (locale-aware fallback).
    $eventTitle = is_array($event->title)
        ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?? '')
        : ($event->title ?? '');

    // Event date/venue block.
    $eventDate = $event->event_date ? \Carbon\Carbon::parse($event->event_date)->translatedFormat('d F Y') : '';
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DejaVu Sans', sans-serif; }
        body { margin: 0; padding: 0; background: #fff; color: #1f2937; }

        .page {
            width: {{ $paperMm['w'] }}mm;
            height: {{ $paperMm['h'] }}mm;
            padding: {{ $bleedMm }}mm;
            page-break-after: always;
        }
        .page:last-child { page-break-after: auto; }

        .grid {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }
        .grid td {
            width: {{ $tileW }}mm;
            height: {{ $tileH }}mm;
            padding: 4mm;
            border: 0.4pt dashed #d1d5db;
            vertical-align: top;
        }

        .tile {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .tile-header {
            border-bottom: 1pt solid #4f46e5;
            padding-bottom: 2mm;
            margin-bottom: 3mm;
        }

        .watermark {
            font-size: 6pt;
            letter-spacing: 2pt;
            color: #4f46e5;
            text-transform: uppercase;
            font-weight: bold;
        }

        .event-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1f2937;
            margin-top: 1mm;
        }

        .event-meta {
            font-size: 7pt;
            color: #6b7280;
            margin-top: 1mm;
            line-height: 1.4;
        }

        .body-row {
            display: table;
            width: 100%;
            margin-top: 3mm;
        }

        .body-cell-left {
            display: table-cell;
            vertical-align: middle;
            width: 55%;
            padding-right: 3mm;
        }

        .body-cell-right {
            display: table-cell;
            vertical-align: middle;
            width: 45%;
            text-align: right;
        }

        .recipient-label {
            font-size: 6pt;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1pt;
        }

        .recipient-name {
            font-size: 9pt;
            font-weight: bold;
            color: #111827;
            margin-top: 0.5mm;
        }

        .code-label {
            font-size: 6pt;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1pt;
            margin-top: 2mm;
        }

        .code {
            font-family: 'Courier', monospace;
            font-size: 8pt;
            font-weight: bold;
            color: #111827;
            margin-top: 0.5mm;
            word-break: break-all;
        }

        .qr {
            width: 22mm;
            height: 22mm;
            display: block;
            margin-left: auto;
        }

        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 5.5pt;
            color: #9ca3af;
            border-top: 0.4pt dotted #d1d5db;
            padding-top: 1mm;
            text-align: center;
        }
    </style>
</head>
<body>
@foreach($pages as $pageInvites)
    <div class="page">
        <table class="grid">
            @for($r = 0; $r < $rows; $r++)
                <tr>
                    @for($c = 0; $c < $cols; $c++)
                        @php
                            $index = $r * $cols + $c;
                            $invite = $pageInvites[$index] ?? null;
                        @endphp
                        <td>
                            @if($invite)
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
                                <div class="tile">
                                    <div class="tile-header">
                                        <div class="watermark">Invitație</div>
                                        <div class="event-title">{{ $eventTitle }}</div>
                                        <div class="event-meta">
                                            @if($eventDate){{ $eventDate }}@endif
                                            @if($venueName)
                                                <br>{{ $venueName }}@if($venueCity), {{ $venueCity }}@endif
                                            @endif
                                        </div>
                                    </div>

                                    <div class="body-row">
                                        <div class="body-cell-left">
                                            @if($recipientName !== '')
                                                <div class="recipient-label">Invitat</div>
                                                <div class="recipient-name">{{ $recipientName }}</div>
                                            @endif
                                            <div class="code-label">Cod</div>
                                            <div class="code">{{ $invite->invite_code }}</div>
                                        </div>
                                        <div class="body-cell-right">
                                            <img class="qr" src="{{ $qrUrl }}" alt="QR">
                                        </div>
                                    </div>

                                    <div class="footer">Prezintă QR-ul la intrare</div>
                                </div>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endfor
        </table>
    </div>
@endforeach
</body>
</html>
