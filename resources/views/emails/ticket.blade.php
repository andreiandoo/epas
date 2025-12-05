<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket for {{ $eventTitle }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #181622; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">Your E-Ticket</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Event Title -->
                            <h2 style="margin: 0 0 20px; color: #181622; font-size: 22px; font-weight: 600;">{{ $eventTitle }}</h2>

                            <!-- Event Details -->
                            @if($event)
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                        <span style="margin-right: 8px;">📅</span>
                                        {{ $event->event_date ? $event->event_date->format('l, d F Y') : 'TBA' }}
                                    </td>
                                </tr>
                                @if($event->start_time)
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                        <span style="margin-right: 8px;">🕐</span>
                                        {{ $event->start_time }}
                                    </td>
                                </tr>
                                @endif
                                @if($venueName)
                                <tr>
                                    <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                        <span style="margin-right: 8px;">📍</span>
                                        {{ $venueName }}{{ $event->venue?->city ? ', ' . $event->venue->city : '' }}
                                    </td>
                                </tr>
                                @endif
                            </table>
                            @endif

                            <!-- QR Code -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 8px; margin-bottom: 30px;">
                                <tr>
                                    <td align="center" style="padding: 30px;">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($ticket->code) }}&color=181622&margin=0" alt="QR Code" style="width: 180px; height: 180px; margin-bottom: 15px;">
                                        <p style="margin: 0; font-family: monospace; font-size: 20px; font-weight: bold; color: #181622; letter-spacing: 2px;">{{ $ticket->code }}</p>
                                        <p style="margin: 10px 0 0; font-size: 12px; color: #6b7280;">Present this QR code at the entrance</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Ticket Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="color: #6b7280; font-size: 13px;">Ticket Type</span><br>
                                        <span style="color: #181622; font-size: 15px; font-weight: 500;">{{ $ticket->ticketType?->name ?? 'N/A' }}</span>
                                    </td>
                                </tr>
                                @if($ticket->seat_label)
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                                        <span style="color: #6b7280; font-size: 13px;">Seat</span><br>
                                        <span style="color: #181622; font-size: 15px; font-weight: 500;">{{ $ticket->seat_label }}</span>
                                    </td>
                                </tr>
                                @endif
                            </table>

                            <!-- Note -->
                            <p style="margin: 0; padding: 15px; background-color: #fef3c7; border-radius: 6px; font-size: 13px; color: #92400e;">
                                <strong>Important:</strong> Your ticket is attached to this email as a PDF. You can print it or show it on your phone at the event entrance.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #6b7280;">
                                This email was sent because you purchased a ticket.<br>
                                If you have any questions, please contact the event organizer.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
