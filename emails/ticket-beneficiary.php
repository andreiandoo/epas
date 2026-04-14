<!DOCTYPE html>
<html lang="ro" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Biletul tau pentru <?= $event_name ?? '' ?> - <?= $site_name ?? 'Ambilet' ?></title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8FAFC; -webkit-font-smoothing: antialiased;">

    <div style="display: none; max-height: 0; overflow: hidden;">Ai primit un bilet pentru <?= $event_name ?? '' ?>! Prezinta acest email sau descarca PDF-ul.</div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F8FAFC;">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" bgcolor="#A51C30" style="background-color: #A51C30; padding: 40px 30px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 16px;">
                                        <span style="display: inline-block; background-color: rgba(255,255,255,0.2); border-radius: 50%; padding: 16px; font-size: 32px;">🎫</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700;">Ai primit un bilet!</h1>
                                        <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 16px;">Pregateste-te pentru <?= $event_name ?? 'eveniment' ?></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">

                            <p style="margin: 0 0 8px 0; color: #64748B; font-size: 16px;">Salut, <?= $beneficiary_name ?? 'prietene' ?>!</p>
                            <p style="margin: 0 0 24px 0; color: #64748B; font-size: 16px; line-height: 1.6;"><?= $buyer_name ?? 'Cineva' ?> ti-a cumparat un bilet pentru evenimentul de mai jos. Ne vedem acolo!</p>

                            <!-- Event Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F8FAFC; border-radius: 16px; overflow: hidden;">
                                <?php if (!empty($event_image)): ?>
                                <tr>
                                    <td>
                                        <img src="<?= $event_image ?>" alt="<?= $event_name ?? '' ?>" width="100%" style="display: block; max-height: 180px; object-fit: cover;">
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="padding: 24px;">
                                        <?php if (!empty($event_category)): ?>
                                        <p style="margin: 0 0 8px 0; color: #A51C30; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;"><?= $event_category ?></p>
                                        <?php endif; ?>
                                        <h2 style="margin: 0 0 16px 0; color: #1E293B; font-size: 22px; font-weight: 700;"><?= $event_name ?? 'Eveniment' ?></h2>

                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 8px 0; vertical-align: top; width: 32px; font-size: 18px;">📅</td>
                                                <td style="padding: 8px 0 8px 8px;">
                                                    <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;"><?= $event_date ?? '' ?></p>
                                                    <p style="margin: 2px 0 0 0; color: #64748B; font-size: 14px;"><?= $event_time ?? '' ?></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; vertical-align: top; width: 32px; font-size: 18px;">📍</td>
                                                <td style="padding: 8px 0 8px 8px;">
                                                    <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;"><?= $venue_name ?? '' ?></p>
                                                    <p style="margin: 2px 0 0 0; color: #64748B; font-size: 14px;"><?= $venue_address ?? '' ?></p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr><td style="padding: 24px 0;"><div style="height: 1px; background-color: #E2E8F0;"></div></td></tr>
                            </table>

                            <h2 style="margin: 0 0 16px 0; color: #1E293B; font-size: 18px; font-weight: 700;">🎫 Biletul tau</h2>

                            <!-- Ticket Card with QR -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 2px dashed #E2E8F0; border-radius: 12px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td style="vertical-align: top;">
                                                    <p style="margin: 0; color: #A51C30; font-weight: 700; font-size: 13px; text-transform: uppercase;"><?= $ticket_type ?? 'Bilet' ?></p>
                                                    <p style="margin: 8px 0 0 0; color: #1E293B; font-weight: 600; font-size: 18px;"><?= $beneficiary_name ?? '' ?></p>
                                                    <p style="margin: 4px 0 0 0; color: #64748B; font-size: 12px;"><?= $ticket_number ?? '' ?></p>
                                                    <?php if (!empty($seat_info)): ?>
                                                    <p style="margin: 8px 0 0 0; color: #1E293B; font-size: 14px;">Loc: <strong><?= $seat_info ?></strong></p>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if (!empty($qr_code)): ?>
                                                <td style="width: 100px; text-align: right;">
                                                    <img src="<?= $qr_code ?>" alt="QR Code" width="100" height="100" style="display: block;">
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Action Buttons -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 24px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <?php if (!empty($ticket_download_link)): ?>
                                                <td align="center" bgcolor="#A51C30" style="background-color: #A51C30; border-radius: 10px;">
                                                    <a href="<?= $ticket_download_link ?>" target="_blank" style="display: inline-block; padding: 14px 28px; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 14px;">📥 Descarca biletul PDF</a>
                                                </td>
                                                <?php endif; ?>
                                                <?php if (!empty($add_to_calendar_link)): ?>
                                                <td width="12"></td>
                                                <td align="center" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px;">
                                                    <a href="<?= $add_to_calendar_link ?>" target="_blank" style="display: inline-block; padding: 14px 28px; color: #1E293B; text-decoration: none; font-weight: 600; font-size: 14px;">📅 Adauga in calendar</a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr><td style="padding: 8px 0;"><div style="height: 1px; background-color: #E2E8F0;"></div></td></tr>
                            </table>

                            <!-- Important Info -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #EFF6FF; border-radius: 12px; margin-top: 16px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 12px 0; color: #1E40AF; font-weight: 700; font-size: 14px;">📋 Important de stiut</p>
                                        <p style="margin: 0 0 8px 0; color: #3B82F6; font-size: 14px;">• Prezinta codul QR din acest email sau din PDF</p>
                                        <p style="margin: 0 0 8px 0; color: #3B82F6; font-size: 14px;">• Vino cu 30 minute inainte de inceperea evenimentului</p>
                                        <p style="margin: 0; color: #3B82F6; font-size: 14px;">• Ai nevoie de un act de identitate valid</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Register CTA -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #FEF3C7; border-radius: 12px; margin-top: 16px;">
                                <tr>
                                    <td align="center" style="padding: 20px;">
                                        <span style="font-size: 24px;">🎁</span>
                                        <p style="margin: 8px 0 4px 0; color: #92400E; font-weight: 700; font-size: 16px;">Creaza-ti cont pe <?= $site_name ?? 'Ambilet' ?>!</p>
                                        <p style="margin: 0 0 12px 0; color: #A16207; font-size: 13px;">Primesti 100 puncte bonus si acces la bilete si oferte exclusive.</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="background-color: #ffffff; border: 1px solid #FCD34D; border-radius: 10px;">
                                                    <a href="<?= $site_url ?? 'https://ambilet.ro' ?>/register" target="_blank" style="display: inline-block; padding: 10px 20px; color: #92400E; text-decoration: none; font-weight: 600; font-size: 13px;">Creaza cont gratuit</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; color: #64748B; font-size: 14px; line-height: 1.6;">
                                Ai intrebari? Contacteaza-ne la <a href="mailto:<?= $support_email ?? 'suport@ambilet.ro' ?>" style="color: #A51C30; text-decoration: none;"><?= $support_email ?? 'suport@ambilet.ro' ?></a>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td bgcolor="#F8FAFC" style="background-color: #F8FAFC; padding: 30px; border-top: 1px solid #E2E8F0;">
                            <p style="margin: 0 0 8px 0; color: #94A3B8; font-size: 13px; text-align: center;">&copy; <?= date('Y') ?> <?= strtoupper($site_name ?? 'AMBILET') ?>. Toate drepturile rezervate.</p>
                            <p style="margin: 16px 0 0 0; color: #94A3B8; font-size: 13px; text-align: center;">
                                <a href="<?= $site_url ?? 'https://ambilet.ro' ?>/privacy" style="color: #94A3B8; text-decoration: underline;">Confidentialitate</a> ·
                                <a href="<?= $site_url ?? 'https://ambilet.ro' ?>/terms" style="color: #94A3B8; text-decoration: underline;">Termeni</a>
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
