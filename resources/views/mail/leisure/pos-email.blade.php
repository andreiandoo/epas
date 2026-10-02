{{--
    Emailurile metodei „Via email" din POS-ul leisure (App\Services\Leisure\LeisurePosEmail).

    $kind: 'payment' (link de plată) | 'paid' (bilete după plată) | 'free' (bilete cu valoare 0)
    $t = textele în limba comenzii (ro/hu/en); restul variabilelor vin gata formatate.

    Lines shape:   [{ name, qty, total, addons[] }]
    Tickets shape: [{ name, code, issuer, qr_url }]
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $venue }}</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f4f6;color:#1f2937;">
    <table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;margin:0 auto;background:#fff;">
        {{-- Header brand --}}
        <tr>
            <td style="padding:24px;background:#1F4E37;color:#fff;">
                <div style="font-size:22px;font-weight:700;letter-spacing:0.02em;">{{ $venue }}</div>
                <div style="font-size:13px;opacity:0.85;margin-top:4px;">{{ $kind === 'payment' ? $t['header_payment'] : $t['header_tickets'] }}</div>
            </td>
        </tr>

        {{-- Greeting + mesaj --}}
        <tr>
            <td style="padding:24px 24px 8px 24px;">
                <p style="margin:0 0 12px 0;font-size:16px;font-weight:600;">{{ $greeting }}</p>
                <p style="margin:0;font-size:15px;line-height:1.55;color:#374151;">{{ $intro }}</p>
            </td>
        </tr>

        {{-- Data vizitei + numar comanda --}}
        <tr>
            <td style="padding:16px 24px 0 24px;">
                <table cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
                    <tr>
                        <td style="padding:14px 18px;">
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;font-weight:600;">{{ $t['visit_date'] }}</div>
                            <div style="font-size:18px;font-weight:700;color:#1F4E37;margin-top:2px;">{{ $visitDate }}</div>
                        </td>
                        <td style="padding:14px 18px;text-align:right;">
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;font-weight:600;">{{ $t['order_number'] }}</div>
                            <div style="font-size:14px;color:#1f2937;margin-top:2px;font-family:monospace;">{{ $orderNumber }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Rezumat comanda --}}
        <tr>
            <td style="padding:20px 24px 0 24px;">
                <h2 style="margin:0 0 8px 0;font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#1f2937;">{{ $t['summary'] }}</h2>
                <table cellpadding="0" cellspacing="0" border="0" style="width:100%;font-size:14px;">
                    @foreach ($lines as $line)
                        <tr>
                            <td style="padding:6px 0;border-bottom:1px solid #f3f4f6;color:#374151;">
                                {{ $line['qty'] }} × {{ $line['name'] }}
                                @foreach ($line['addons'] as $addon)
                                    <div style="font-size:12px;color:#6b7280;">+ {{ $addon }}</div>
                                @endforeach
                            </td>
                            <td style="padding:6px 0;border-bottom:1px solid #f3f4f6;text-align:right;white-space:nowrap;vertical-align:top;">{{ $line['total'] }}</td>
                        </tr>
                    @endforeach
                    @if ($commission)
                        <tr>
                            <td style="padding:6px 0;color:#6b7280;">{{ $t['commission'] }}</td>
                            <td style="padding:6px 0;text-align:right;white-space:nowrap;color:#6b7280;">{{ $commission }}</td>
                        </tr>
                    @endif
                    @if ($kind !== 'free')
                        <tr>
                            <td style="padding:10px 0 0 0;font-size:16px;font-weight:700;">{{ $kind === 'payment' ? $t['total_due'] : $t['total_paid'] }}</td>
                            <td style="padding:10px 0 0 0;text-align:right;white-space:nowrap;font-size:16px;font-weight:700;color:#1F4E37;">{{ $total }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>

        @if ($kind === 'payment')
            {{-- Buton plata --}}
            <tr>
                <td style="padding:28px 24px 8px 24px;text-align:center;">
                    <a href="{{ $payUrl }}" style="display:inline-block;background:#1F4E37;color:#ffffff;font-size:17px;font-weight:700;padding:15px 34px;border-radius:10px;text-decoration:none;">{{ $payButton }}</a>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 24px 0 24px;">
                    <p style="margin:0 0 10px 0;font-size:14px;line-height:1.55;color:#374151;">{{ $t['payment_note'] }}</p>
                    @if ($validity)
                        <p style="margin:0 0 10px 0;font-size:13px;line-height:1.55;color:#6b7280;">{{ $validity }}</p>
                    @endif
                    <p style="margin:0;font-size:12px;line-height:1.5;color:#9ca3af;">{{ $t['link_fallback'] }}<br><a href="{{ $payUrl }}" style="color:#1F4E37;word-break:break-all;">{{ $payUrl }}</a></p>
                </td>
            </tr>
        @else
            {{-- Bilete --}}
            <tr>
                <td style="padding:24px 24px 0 24px;">
                    <h2 style="margin:0 0 6px 0;font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#1f2937;">{{ $t['tickets_h'] }}</h2>
                    <p style="margin:0 0 14px 0;font-size:13px;line-height:1.5;color:#6b7280;">{{ $t['show_qr'] }}</p>
                    @foreach ($tickets as $tk)
                        <table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-bottom:14px;border:2px solid #1F4E37;border-radius:10px;">
                            <tr>
                                <td style="padding:18px;vertical-align:top;">
                                    <div style="font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:0.05em;">{{ $tk['issuer'] }}</div>
                                    <div style="font-size:18px;font-weight:700;color:#1f2937;margin-top:4px;">{{ $tk['name'] }}</div>
                                    <div style="font-size:11px;color:#6b7280;margin-top:8px;">{{ $t['code'] }}:</div>
                                    <div style="font-size:16px;font-weight:700;font-family:monospace;color:#1F4E37;letter-spacing:0.05em;">{{ $tk['code'] }}</div>
                                </td>
                                <td style="padding:18px;text-align:right;vertical-align:middle;width:140px;">
                                    <img src="{{ $tk['qr_url'] }}" alt="QR {{ $tk['code'] }}" width="120" height="120" style="display:block;margin:0 auto;border:1px solid #e5e7eb;border-radius:4px;">
                                </td>
                            </tr>
                        </table>
                    @endforeach
                </td>
            </tr>
        @endif

        {{-- Incheiere --}}
        <tr>
            <td style="padding:20px 24px 24px 24px;">
                <p style="margin:0;font-size:15px;font-weight:600;color:#1F4E37;">{{ $closing }}</p>
                <p style="margin:4px 0 0 0;font-size:14px;color:#374151;">{{ $venue }}</p>
            </td>
        </tr>

        {{-- Issuer details footer --}}
        @if (!empty($issuer['name']))
            <tr>
                <td style="padding:0 24px 24px 24px;">
                    <div style="font-size:11px;color:#6b7280;line-height:1.5;border-top:1px solid #e5e7eb;padding-top:16px;">
                        <strong>{{ $t['issued_by'] }}:</strong> {{ $issuer['name'] }}
                        @if (!empty($issuer['tax_id']))
                            · {{ $t['cui'] }}: {{ $issuer['tax_id'] }}
                        @endif
                        @if (!empty($issuer['registration']))
                            · {{ $t['reg_com'] }}: {{ $issuer['registration'] }}
                        @endif
                        @if (!empty($issuer['address']))
                            <br>{{ $issuer['address'] }}@if (!empty($issuer['city'])), {{ $issuer['city'] }}@endif
                        @endif
                    </div>
                </td>
            </tr>
        @endif

        {{-- Brand footer --}}
        <tr>
            <td style="padding:20px 24px;background:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">
                <div style="font-size:12px;color:#6b7280;">{{ $t['footer'] }}</div>
                <div style="font-size:11px;color:#9ca3af;margin-top:6px;">{{ $t['questions'] }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
