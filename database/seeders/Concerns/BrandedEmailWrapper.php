<?php

namespace Database\Seeders\Concerns;

use App\Models\MarketplaceClient;

/**
 * Shared HTML wrapper for marketplace email templates. Mirrors the layout
 * used by MarketplaceEmailTemplatesSeeder so newly seeded templates pick
 * up the same branding (logo, gradient header, footer) as the existing
 * customer transactional templates without having to duplicate the markup.
 */
trait BrandedEmailWrapper
{
    /**
     * Resolve marketplace branding bits — name, domain, logo, primary colour.
     *
     * @return array{name:string,domain:string,logo:string,primary:string,primary_dark:string,contact_email:string}
     */
    protected function brand(MarketplaceClient $marketplace): array
    {
        $name = $marketplace->public_name ?? $marketplace->name ?? 'Marketplace';
        $rawDomain = $marketplace->domain ?? 'ambilet.ro';
        $domain = preg_replace('#^https?://#', '', rtrim($rawDomain, '/'));
        return [
            'name' => $name,
            'domain' => $domain,
            'logo' => "https://{$domain}/assets/images/ambilet_logo.webp",
            'primary' => '#A51C30',
            'primary_dark' => '#8B1728',
            'contact_email' => $marketplace->contact_email ?? "contact@{$domain}",
        ];
    }

    /**
     * Wrap an HTML body in the standard branded shell (header + footer).
     */
    protected function wrap(array $brand, string $content, bool $showUnsubscribe = false): string
    {
        $name = $brand['name'];
        $domain = $brand['domain'];
        $logoUrl = $brand['logo'];
        $primary = $brand['primary'];
        $primaryDark = $brand['primary_dark'];

        $unsubscribeBlock = $showUnsubscribe ? <<<UNSUB
<p style="margin:8px 0 0;font-size:12px;">
<a href="https://{$domain}/newsletter-unsubscribe?email={{customer_email}}" style="color:#9ca3af;text-decoration:underline;">Dezabonare</a> de la notificările prin email
</p>
UNSUB : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table style="width:100%;background:#f3f4f6;padding:32px 16px;" cellpadding="0" cellspacing="0">
<tr><td align="center">
<table style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);" cellpadding="0" cellspacing="0">

<!-- HEADER -->
<tr>
<td style="background:linear-gradient(135deg,{$primary},{$primaryDark});padding:24px 32px;text-align:center;">
<img src="{$logoUrl}" alt="{$name}" style="height:36px;width:auto;" />
</td>
</tr>

<!-- CONTENT -->
<tr>
<td style="padding:32px;">
{$content}
</td>
</tr>

<!-- FOOTER -->
<tr>
<td style="background:#f9fafb;padding:24px 32px;border-top:1px solid #e5e7eb;">
<p style="margin:0;font-size:13px;color:#9ca3af;text-align:center;">
© {$name} · <a href="https://{$domain}" style="color:#9ca3af;text-decoration:none;">{$domain}</a>
</p>
{$unsubscribeBlock}
</td>
</tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Helper for the standard "label / value" detail table used across most
     * templates. Each row is [label, value]. Pass [] to skip.
     *
     * @param array<int, array{0:string,1:string}> $rows
     */
    protected function detailsTable(array $brand, string $heading, array $rows): string
    {
        if (empty($rows)) return '';
        $primary = $brand['primary'];
        $body = '';
        foreach ($rows as $i => [$label, $value]) {
            $bg = $i % 2 === 0 ? '#f9fafb' : '#ffffff';
            $body .= <<<TR
<tr style="background:{$bg};">
<td style="padding:10px 16px;font-size:14px;color:#6b7280;width:160px;">{$label}</td>
<td style="padding:10px 16px;font-size:14px;color:#1a1a1a;font-weight:600;">{$value}</td>
</tr>
TR;
        }
        return <<<HTML
<table style="width:100%;border-collapse:collapse;margin:0 0 24px;border-radius:8px;overflow:hidden;">
<tr style="background:{$primary};color:#fff;">
<td colspan="2" style="padding:12px 16px;font-weight:bold;font-size:14px;">{$heading}</td>
</tr>
{$body}
</table>
HTML;
    }
}
