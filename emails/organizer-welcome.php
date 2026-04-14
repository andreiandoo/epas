<!DOCTYPE html>
<html lang="ro" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Bine ai venit in comunitatea de organizatori <?= $site_name ?? 'Ambilet' ?>!</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8FAFC; -webkit-font-smoothing: antialiased;">

    <div style="display: none; max-height: 0; overflow: hidden;">Contul tau de organizator pe <?= $site_name ?? 'Ambilet' ?> este acum activ. Incepe sa vinzi bilete!</div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F8FAFC;">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" bgcolor="#1E293B" style="background-color: #1E293B; padding: 50px 30px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 16px;">
                                        <span style="color: #ffffff; font-size: 28px; font-weight: 800;"><?= strtoupper($site_name ?? 'AMBILET') ?></span>
                                        <span style="display: block; color: #A51C30; font-size: 14px; font-weight: 600; margin-top: 4px;">PENTRU ORGANIZATORI</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 800;">Bine ai venit!</h1>
                                        <p style="margin: 12px 0 0 0; color: rgba(255,255,255,0.8); font-size: 16px;">Contul tau de organizator este activ</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">

                            <h2 style="margin: 0 0 16px 0; color: #1E293B; font-size: 22px; font-weight: 700;">Salut, <?= $contact_name ?? 'prietene' ?>!</h2>

                            <p style="margin: 0 0 24px 0; color: #64748B; font-size: 16px; line-height: 1.6;">Suntem incantati sa avem <strong><?= $company_name ?? 'compania ta' ?></strong> in comunitatea de organizatori <?= $site_name ?? 'Ambilet' ?>! Esti pregatit sa organizezi evenimente memorabile?</p>

                            <!-- Stats Card -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #EEF2FF; border-radius: 16px;">
                                <tr>
                                    <td align="center" style="padding: 28px;">
                                        <span style="font-size: 40px; display: block;">🎯</span>
                                        <p style="margin: 8px 0 0 0; color: #3730A3; font-weight: 800; font-size: 20px;">Platforma #1 pentru bilete</p>
                                        <p style="margin: 8px 0 0 0; color: #4338CA; font-size: 14px;">Peste 500+ organizatori activi in Romania</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr><td style="padding: 24px 0;"><div style="height: 1px; background-color: #E2E8F0;"></div></td></tr>
                            </table>

                            <h2 style="margin: 0 0 20px 0; color: #1E293B; font-size: 18px; font-weight: 700;">Urmatorii pasi</h2>

                            <!-- Steps -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F8FAFC; border-radius: 12px;">
                                            <tr>
                                                <td style="padding: 16px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="width: 40px; height: 40px; background-color: #A51C30; border-radius: 10px; text-align: center; vertical-align: middle;">
                                                                <span style="color: #ffffff; font-weight: 700; font-size: 16px; line-height: 40px;">1</span>
                                                            </td>
                                                            <td style="padding-left: 12px;">
                                                                <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;">Creeaza primul eveniment</p>
                                                                <p style="margin: 4px 0 0 0; color: #64748B; font-size: 13px;">Adauga detalii, imagine si tipuri de bilete.</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F8FAFC; border-radius: 12px;">
                                            <tr>
                                                <td style="padding: 16px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="width: 40px; height: 40px; background-color: #A51C30; border-radius: 10px; text-align: center; vertical-align: middle;">
                                                                <span style="color: #ffffff; font-weight: 700; font-size: 16px; line-height: 40px;">2</span>
                                                            </td>
                                                            <td style="padding-left: 12px;">
                                                                <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;">Configureaza datele bancare</p>
                                                                <p style="margin: 4px 0 0 0; color: #64748B; font-size: 13px;">Adauga contul pentru primirea platilor.</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0 12px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F8FAFC; border-radius: 12px;">
                                            <tr>
                                                <td style="padding: 16px;">
                                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="width: 40px; height: 40px; background-color: #A51C30; border-radius: 10px; text-align: center; vertical-align: middle;">
                                                                <span style="color: #ffffff; font-weight: 700; font-size: 16px; line-height: 40px;">3</span>
                                                            </td>
                                                            <td style="padding-left: 12px;">
                                                                <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;">Publica si promoveaza</p>
                                                                <p style="margin: 4px 0 0 0; color: #64748B; font-size: 13px;">Evenimentul tau va fi vizibil pe <?= $site_name ?? 'Ambilet' ?>.</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 20px 0 32px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" bgcolor="#A51C30" style="background-color: #A51C30; border-radius: 12px;">
                                                    <a href="<?= $site_url ?? 'https://ambilet.ro' ?>/organizer/dashboard" target="_blank" style="display: inline-block; padding: 16px 32px; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px;">Acceseaza dashboard-ul</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr><td style="padding: 8px 0;"><div style="height: 1px; background-color: #E2E8F0;"></div></td></tr>
                            </table>

                            <h2 style="margin: 24px 0 16px 0; color: #1E293B; font-size: 18px; font-weight: 700;">De ce <?= $site_name ?? 'Ambilet' ?>?</h2>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 10px 0; vertical-align: top; width: 40px; font-size: 24px;">💰</td>
                                    <td style="padding: 10px 0 10px 12px;">
                                        <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;">Comisioane competitive</p>
                                        <p style="margin: 4px 0 0 0; color: #64748B; font-size: 13px;">De la 1% - cele mai mici din industrie</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; vertical-align: top; width: 40px; font-size: 24px;">📊</td>
                                    <td style="padding: 10px 0 10px 12px;">
                                        <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;">Rapoarte in timp real</p>
                                        <p style="margin: 4px 0 0 0; color: #64748B; font-size: 13px;">Urmareste vanzarile live</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; vertical-align: top; width: 40px; font-size: 24px;">🔒</td>
                                    <td style="padding: 10px 0 10px 12px;">
                                        <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;">Plati sigure si rapide</p>
                                        <p style="margin: 4px 0 0 0; color: #64748B; font-size: 13px;">Transfer in 2-3 zile lucratoare</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0; vertical-align: top; width: 40px; font-size: 24px;">📱</td>
                                    <td style="padding: 10px 0 10px 12px;">
                                        <p style="margin: 0; color: #1E293B; font-weight: 600; font-size: 15px;">Check-in mobil</p>
                                        <p style="margin: 4px 0 0 0; color: #64748B; font-size: 13px;">Scaneaza bilete direct de pe telefon</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; color: #64748B; font-size: 14px; line-height: 1.6;">Ai intrebari? Echipa noastra de suport este aici pentru tine: <a href="mailto:<?= $support_email ?? 'suport@ambilet.ro' ?>" style="color: #A51C30; text-decoration: none;"><?= $support_email ?? 'suport@ambilet.ro' ?></a></p>

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
