<!DOCTYPE html>
<html lang="ro" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Confirma adresa de email - <?= $site_name ?? 'Ambilet' ?></title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #F8FAFC; -webkit-font-smoothing: antialiased;">

    <div style="display: none; max-height: 0; overflow: hidden;">Confirma adresa de email pentru a activa contul tau <?= $site_name ?? 'Ambilet' ?>.</div>

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
                                        <span style="display: inline-block; background-color: rgba(255,255,255,0.2); border-radius: 50%; padding: 16px; font-size: 28px;">📧</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <h1 style="margin: 0; color: #ffffff; font-size: 26px; font-weight: 700;">Confirma adresa de email</h1>
                                        <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 16px;">Mai e un singur pas!</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">

                            <p style="margin: 0 0 8px 0; color: #64748B; font-size: 16px;">Salut, <?= $prenume ?? 'prietene' ?>!</p>
                            <p style="margin: 0 0 24px 0; color: #64748B; font-size: 16px; line-height: 1.6;">Multumim pentru inregistrare! Pentru a activa contul tau, te rugam sa confirmi adresa de email apasand butonul de mai jos.</p>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 24px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" bgcolor="#A51C30" style="background-color: #A51C30; border-radius: 12px;">
                                                    <a href="<?= $confirmation_link ?? '#' ?>" target="_blank" style="display: inline-block; padding: 18px 40px; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px;">Confirma adresa de email</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 16px 0; color: #64748B; font-size: 14px; line-height: 1.6;">Sau copiaza si lipeste acest link in browser:</p>
                            <p style="margin: 0 0 24px 0; color: #A51C30; font-size: 12px; word-break: break-all; background-color: #F8FAFC; padding: 12px; border-radius: 8px;"><?= $confirmation_link ?? '#' ?></p>

                            <!-- Divider -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr><td style="padding: 16px 0;"><div style="height: 1px; background-color: #E2E8F0;"></div></td></tr>
                            </table>

                            <!-- Info Box -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #FEF3C7; border-radius: 12px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 8px 0; color: #92400E; font-weight: 600; font-size: 14px;">⚠️ Link-ul expira in 24 de ore</p>
                                        <p style="margin: 0; color: #A16207; font-size: 13px;">Daca nu ai solicitat acest email, poti ignora acest mesaj in siguranta.</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; color: #64748B; font-size: 14px; line-height: 1.6;">Ai nevoie de ajutor? Contacteaza-ne la <a href="mailto:<?= $support_email ?? 'suport@ambilet.ro' ?>" style="color: #A51C30; text-decoration: none;"><?= $support_email ?? 'suport@ambilet.ro' ?></a></p>

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
