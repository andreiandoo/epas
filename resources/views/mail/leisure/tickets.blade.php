{{--
    Email tranzactional bilete leisure (Sf. Ana, alte locatii agrement).
    Locale-ul vine ca $locale (ro/hu/en); helper-ul $t($key) intoarce textul tradus.

    Variabile:
      $order, $tickets[], $issuer, $issuerSecondary, $eventName, $visitDate, $t($key), $locale

    Tickets shape:
      [{ code, ticket_type, service_category, issuing_company, qr_data_uri }]
      qr_data_uri = "data:image/png;base64,XXX" (generat de Job inainte de dispatch)
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $t('tickets_h') }} · {{ $eventName }}</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f4f6;color:#1f2937;">
    <table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;margin:0 auto;background:#fff;">
        {{-- Header brand --}}
        <tr>
            <td style="padding:24px;background:#1F4E37;color:#fff;">
                <div style="font-size:22px;font-weight:700;letter-spacing:0.02em;">🎟️ {{ $eventName }}</div>
                <div style="font-size:13px;opacity:0.85;margin-top:4px;">{{ $t('thanks') }} {{ $eventName }}</div>
            </td>
        </tr>

        {{-- Greeting + visit date --}}
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 16px 0;font-size:15px;">{{ $t('greeting') }}</p>
                <table cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
                    <tr>
                        <td style="padding:14px 18px;">
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;font-weight:600;">{{ $t('visit_date') }}</div>
                            <div style="font-size:18px;font-weight:700;color:#1F4E37;margin-top:2px;">{{ $visitDate }}</div>
                        </td>
                        <td style="padding:14px 18px;text-align:right;">
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;font-weight:600;">{{ $t('order_number') }}</div>
                            <div style="font-size:14px;color:#1f2937;margin-top:2px;font-family:monospace;">{{ $order->order_number ?? ('#' . $order->id) }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Tickets --}}
        <tr>
            <td style="padding:0 24px 24px 24px;">
                <h2 style="margin:0 0 12px 0;font-size:14px;text-transform:uppercase;letter-spacing:0.08em;color:#1f2937;">{{ $t('tickets_h') }}</h2>
                @foreach ($tickets as $tk)
                    @php
                        $tkIssuer = (($tk['issuing_company'] ?? 'primary') === 'secondary' && $issuerSecondary) ? $issuerSecondary : $issuer;
                    @endphp
                    <table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-bottom:14px;border:2px solid #1F4E37;border-radius:10px;">
                        <tr>
                            <td style="padding:18px;vertical-align:top;">
                                <div style="font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:0.05em;">{{ $tkIssuer['name'] ?? '' }}</div>
                                <div style="font-size:18px;font-weight:700;color:#1f2937;margin-top:4px;">{{ $tk['ticket_type'] ?? 'Bilet' }}</div>
                                <div style="font-size:11px;color:#6b7280;margin-top:8px;">{{ $t('code') }}:</div>
                                <div style="font-size:16px;font-weight:700;font-family:monospace;color:#1F4E37;letter-spacing:0.05em;">{{ $tk['code'] ?? '' }}</div>
                                <div style="font-size:11px;color:#6b7280;margin-top:10px;line-height:1.4;">{{ $t('show_at_entry') }}</div>
                            </td>
                            <td style="padding:18px;text-align:right;vertical-align:middle;width:140px;">
                                @if (!empty($tk['qr_data_uri']))
                                    <img src="{{ $tk['qr_data_uri'] }}" alt="QR {{ $tk['code'] }}" width="120" height="120" style="display:block;margin:0 auto;border:1px solid #e5e7eb;border-radius:4px;">
                                @else
                                    <div style="width:120px;height:120px;border:1px dashed #d1d5db;border-radius:4px;display:inline-block;text-align:center;line-height:120px;color:#9ca3af;font-size:11px;">QR</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                @endforeach
            </td>
        </tr>

        {{-- Issuer details footer --}}
        <tr>
            <td style="padding:0 24px 24px 24px;">
                <div style="font-size:11px;color:#6b7280;line-height:1.5;border-top:1px solid #e5e7eb;padding-top:16px;">
                    <strong>{{ $t('issued_by') }}:</strong> {{ $issuer['name'] ?? '' }}
                    @if (!empty($issuer['tax_id']))
                        · {{ $t('cui') }}: {{ $issuer['tax_id'] }}
                    @endif
                    @if (!empty($issuer['registration']))
                        · {{ $t('reg_com') }}: {{ $issuer['registration'] }}
                    @endif
                    @if (!empty($issuer['address']))
                        <br>{{ $issuer['address'] }}@if (!empty($issuer['city'])), {{ $issuer['city'] }}@endif
                    @endif
                </div>
            </td>
        </tr>

        {{-- Brand footer --}}
        <tr>
            <td style="padding:20px 24px;background:#f9fafb;text-align:center;border-top:1px solid #e5e7eb;">
                <div style="font-size:12px;color:#6b7280;">{{ $t('footer') }}</div>
                <div style="font-size:11px;color:#9ca3af;margin-top:6px;">{{ $t('questions') }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
