@php
    $accent = $cancelled ? '#b91c1c' : '#b45309';
    $heading = 'color:#111827;font-size:15px;font-weight:700;margin:24px 0 8px;';
    $label = 'padding:7px 12px 7px 0;color:#6b7280;border-bottom:1px solid #f3f4f6;vertical-align:top;width:48%;';
    $value = 'padding:7px 0;color:#111827;border-bottom:1px solid #f3f4f6;font-weight:600;vertical-align:top;';
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>{{ $cancelled ? 'Eveniment anulat' : 'Eveniment amânat' }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr>
        <td style="padding:24px;">
            <div style="color:{{ $accent }};font-size:13px;font-weight:700;">{{ $cancelled ? 'EVENIMENT ANULAT' : 'EVENIMENT AMÂNAT' }}</div>
            <h1 style="color:#111827;font-size:21px;line-height:1.3;margin:6px 0 4px;">{{ $title }}</h1>
            <div style="color:#6b7280;">{{ $marketplaceName }} · {{ $occurredAt }}</div>

            <div style="{{ $heading }}">Cine a făcut acțiunea</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="{{ $label }}">Organizator</td><td style="{{ $value }}">{{ $actor['organizer'] ?? '-' }}</td></tr>
                @if (!empty($actor['member_name']) || !empty($actor['member_email']))
                    <tr><td style="{{ $label }}">Membru echipă</td><td style="{{ $value }}">{{ trim(($actor['member_name'] ?? '') . ' ' . ($actor['member_email'] ?? '')) }}</td></tr>
                @endif
                @if (!empty($actor['organizer_email']))
                    <tr><td style="{{ $label }}">Email organizator</td><td style="{{ $value }}">{{ $actor['organizer_email'] }}</td></tr>
                @endif
                @if (!empty($actor['organizer_phone']))
                    <tr><td style="{{ $label }}">Telefon organizator</td><td style="{{ $value }}">{{ $actor['organizer_phone'] }}</td></tr>
                @endif
            </table>

            <div style="{{ $heading }}">Eveniment</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="{{ $label }}">ID</td><td style="{{ $value }}">{{ $eventId }}</td></tr>
                <tr><td style="{{ $label }}">Data programată</td><td style="{{ $value }}">{{ trim(($startDate ?? '-') . ' ' . substr((string) $startTime, 0, 5)) }}</td></tr>
                @if ($venue)
                    <tr><td style="{{ $label }}">Locație</td><td style="{{ $value }}">{{ $venue }}</td></tr>
                @endif
                <tr><td style="{{ $label }}">Publicat</td><td style="{{ $value }}">{{ $isPublished ? 'Da' : 'Nu' }}</td></tr>
                <tr><td style="{{ $label }}">Zile până la eveniment</td><td style="{{ $value }}">{{ $daysLabel }}</td></tr>
                @if (!$cancelled)
                    <tr><td style="{{ $label }}">Data nouă</td><td style="{{ $value }}">{{ $postponedTo }}</td></tr>
                @endif
                <tr><td style="{{ $label }}">Motiv</td><td style="{{ $value }}">{{ $reason ?? 'nespecificat' }}</td></tr>
            </table>

            <div style="{{ $heading }}">Vânzări în momentul acțiunii</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="{{ $label }}">Bilete vândute</td><td style="{{ $value }}">{{ $stats['tickets_sold'] }}@if ($stats['capacity']) din {{ $stats['capacity'] }} ({{ $stats['sold_percent'] }}%)@endif</td></tr>
                <tr><td style="{{ $label }}">Din care plătite / invitații</td><td style="{{ $value }}">{{ $stats['tickets_paid'] }} / {{ $stats['invitations'] }}</td></tr>
                <tr><td style="{{ $label }}">Bilete scanate</td><td style="{{ $value }}">{{ $stats['tickets_used'] }}</td></tr>
                <tr><td style="{{ $label }}">Comenzi plătite</td><td style="{{ $value }}">{{ $stats['paid_orders'] }}</td></tr>
                <tr><td style="{{ $label }}">Încasări</td><td style="{{ $value }}">{{ $stats['revenue'] }}</td></tr>
                <tr><td style="{{ $label }}">Decontat deja organizatorului</td><td style="{{ $value }}">{{ $stats['paid_out_amount'] }}</td></tr>
                @if ($cancelled)
                    <tr><td style="{{ $label }}">Comenzi neplătite anulate acum</td><td style="{{ $value }}">{{ $stats['cancelled_pending_orders'] }}</td></tr>
                @endif
            </table>

            <div style="{{ $heading }}">Rambursări</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="{{ $label }}">Comenzi rambursate</td><td style="{{ $value }}">{{ $stats['refunded_orders'] }} ({{ $stats['refunded_tickets'] }} bilete)</td></tr>
                <tr><td style="{{ $label }}">Sumă rambursată</td><td style="{{ $value }}">{{ $stats['refunded_amount'] }}</td></tr>
                <tr><td style="{{ $label }}">Cereri în lucru</td><td style="{{ $value }}">{{ $stats['open_refunds'] }} ({{ $stats['open_refunds_amount'] }})</td></tr>
            </table>

            @if ($cancelled && $stats['paid_orders'] > 0)
                <p style="margin:20px 0 0;padding:12px;background:#fef2f2;color:#991b1b;border-radius:6px;">
                    Comenzile plătite nu se rambursează automat. Decizia se ia separat pentru fiecare comandă.
                </p>
            @endif

            <p style="margin:24px 0 0;">
                <a href="{{ $eventUrl }}" style="display:inline-block;padding:10px 16px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:700;">Deschide evenimentul</a>
            </p>
        </td>
    </tr>
</table>
</body>
</html>
