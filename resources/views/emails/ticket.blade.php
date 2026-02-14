<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletul tău pentru {{ $eventTitle }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #181622 0%, #2d2a3e 100%); padding: 35px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700; letter-spacing: 0.5px;">Bilet Electronic</h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.7); font-size: 14px;">Confirmare achizitie bilet</p>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 35px 40px 0;">
                            <p style="margin: 0 0 8px; color: #374151; font-size: 15px; line-height: 1.6;">
                                Salut{{ $ticket->attendee_name ? ', ' . explode(' ', $ticket->attendee_name)[0] : '' }}!
                            </p>
                            <p style="margin: 0 0 25px; color: #374151; font-size: 15px; line-height: 1.6;">
                                Iti multumim pentru achizitie! Biletul tau este atasat la acest email in format PDF. Il poti printa sau il poti prezenta direct de pe telefon la intrarea in eveniment.
                            </p>
                        </td>
                    </tr>

                    <!-- Event Card -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f7ff; border-radius: 10px; border: 1px solid #e8e5f0;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <h2 style="margin: 0 0 15px; color: #181622; font-size: 20px; font-weight: 700;">{{ $eventTitle }}</h2>

                                        @if($event)
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            @if($event->event_date)
                                            <tr>
                                                <td style="padding: 5px 0; color: #4b5563; font-size: 14px; line-height: 1.5;">
                                                    <span style="display: inline-block; width: 20px; text-align: center; margin-right: 8px;">&#128197;</span>
                                                    {{ $event->event_date->translatedFormat('l, j F Y') }}
                                                </td>
                                            </tr>
                                            @endif
                                            @if($event->start_time)
                                            <tr>
                                                <td style="padding: 5px 0; color: #4b5563; font-size: 14px; line-height: 1.5;">
                                                    <span style="display: inline-block; width: 20px; text-align: center; margin-right: 8px;">&#128336;</span>
                                                    Ora {{ $event->start_time }}{{ $event->door_time ? ' (deschidere usi: ' . $event->door_time . ')' : '' }}
                                                </td>
                                            </tr>
                                            @endif
                                            @if($venueName)
                                            <tr>
                                                <td style="padding: 5px 0; color: #4b5563; font-size: 14px; line-height: 1.5;">
                                                    <span style="display: inline-block; width: 20px; text-align: center; margin-right: 8px;">&#128205;</span>
                                                    {{ $venueName }}{{ $event->venue?->city ? ', ' . $event->venue->city : '' }}
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- QR Code -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; border: 1px solid #e5e7eb;">
                                <tr>
                                    <td align="center" style="padding: 30px;">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($ticket->getVerifyUrl()) }}&color=181622&margin=0&format=png" alt="QR Code" style="width: 160px; height: 160px; margin-bottom: 12px;">
                                        <p style="margin: 0; font-family: 'Courier New', monospace; font-size: 22px; font-weight: bold; color: #181622; letter-spacing: 3px;">{{ $ticket->code }}</p>
                                        <p style="margin: 8px 0 0; font-size: 12px; color: #9ca3af;">Prezinta acest cod QR la intrare</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Ticket Details -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                        <span style="color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Tip bilet</span><br>
                                        <span style="color: #181622; font-size: 15px; font-weight: 600;">{{ $ticketTypeName ?: ($ticket->resolveTicketTypeName() ?: 'Standard') }}</span>
                                    </td>
                                </tr>
                                @if($ticket->seat_label)
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                        <span style="color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Loc</span><br>
                                        <span style="color: #181622; font-size: 15px; font-weight: 600;">{{ $ticket->seat_label }}</span>
                                    </td>
                                </tr>
                                @endif
                                @if($ticket->price)
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                        <span style="color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Pret</span><br>
                                        <span style="color: #181622; font-size: 15px; font-weight: 600;">{{ number_format((float) $ticket->price, 2, ',', '.') }} {{ $ticket->order?->currency ?? 'RON' }}</span>
                                    </td>
                                </tr>
                                @endif
                                @if($ticket->order?->order_number)
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <span style="color: #9ca3af; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Numar comanda</span><br>
                                        <span style="color: #181622; font-size: 15px; font-weight: 600;">{{ $ticket->order->order_number }}</span>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- Important Note -->
                    <tr>
                        <td style="padding: 0 40px 35px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fffbeb; border-radius: 8px; border: 1px solid #fde68a;">
                                <tr>
                                    <td style="padding: 15px 18px;">
                                        <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;">
                                            <strong>Important:</strong> Biletul tau este atasat la acest email in format PDF. Il poti printa sau il poti prezenta direct de pe telefon la intrarea in eveniment.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 25px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 5px; font-size: 12px; color: #9ca3af;">
                                Acest email a fost trimis ca urmare a achizitiei unui bilet.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                Daca ai intrebari, te rugam sa contactezi organizatorul evenimentului.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
