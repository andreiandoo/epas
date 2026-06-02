<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Newsletter — Single Featured Event (iabilet-style)" template
 * into marketplace_email_templates for the Ambilet marketplace. Appears in
 *   /marketplace/email-templates
 * and in the "Pornește de la un template" picker on
 *   /marketplace/newsletters/create
 *
 * The body_html is a static iabilet-inspired shell — header band, logo,
 * intro line, then a placeholder block where the admin drops a
 * `featured_event` section (added in NewsletterResource) that auto-fills
 * image / name / price / venue / city / link from the selected event.
 *
 *   php artisan db:seed --class=Database\\Seeders\\AmbiletNewsletterFeaturedEventTemplateSeeder
 */
class AmbiletNewsletterFeaturedEventTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $client = MarketplaceClient::query()
            ->where('domain', 'like', '%ambilet.ro%')
            ->orWhere('name', 'like', '%Ambilet%')
            ->first()
            ?? MarketplaceClient::orderBy('id')->first();

        if (!$client) {
            $this->command->warn('No MarketplaceClient found — skipping seeder.');
            return;
        }

        $tpl = MarketplaceEmailTemplate::updateOrCreate(
            [
                'marketplace_client_id' => $client->id,
                'slug' => 'newsletter-featured-event',
            ],
            [
                'name' => 'Newsletter — Eveniment featured (iabilet-style)',
                'category' => 'newsletter',
                'is_active' => true,
                'is_default' => false,
                'subject' => 'Îți recomandăm — eveniment featured',
                'body_html' => $this->buildBodyHtml($client),
                'body_text' => $this->buildBodyText($client),
                'variables' => [
                    'unsubscribe_url' => 'Auto-generat la send time.',
                    'preferences_url' => 'Auto-generat la send time.',
                ],
            ]
        );

        $this->command->info("Newsletter featured-event template seeded: {$tpl->name} (id={$tpl->id}, client={$client->id})");
    }

    private function buildBodyHtml(MarketplaceClient $client): string
    {
        $name = htmlspecialchars($client->name ?? 'AmBilet.ro', ENT_QUOTES, 'UTF-8');
        $domain = $client->domain ?? 'ambilet.ro';

        return <<<HTML
<div style="margin:0;padding:0;background:#F0F0F0;font-family:Arial,Helvetica,sans-serif;">
  <table bgcolor="#F0F0F0" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0 auto;">
    <tr>
      <td>
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="620" style="margin:0 auto;">
          <tr style="font-size:13px;">
            <td width="20">&nbsp;</td>
            <td height="20" style="color:#4C4C59;">{$name} îți recomandă cele mai tari concerte și evenimente</td>
            <td width="20">&nbsp;</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td>
        <table align="center" bgcolor="#ffffff" border="0" cellpadding="0" cellspacing="0" width="620" style="border-collapse:collapse;margin:0 auto;">
          <tr><td height="30">&nbsp;</td></tr>
          <tr>
            <td align="center" style="font-size:18px;color:#1f2937;font-weight:bold;">{$name} vă recomandă</td>
          </tr>
          <tr><td height="20">&nbsp;</td></tr>
        </table>
      </td>
    </tr>

    <!--
      ↓ ↓ ↓  Adaugă mai jos o secțiune de tip "Eveniment featured (single hero)"  ↓ ↓ ↓
      din meniul "Tip secțiune" al noului editor de newsletter. Aceea va popula
      automat: imagine, nume, preț, venue, oraș și link, conform evenimentului
      pe care îl selectezi.
    -->

    <tr>
      <td height="20" style="font-size:0;line-height:0;">&nbsp;</td>
    </tr>
    <tr>
      <td align="center" style="font-size:13px;color:#4C4C59;">© <span>{$name}</span></td>
    </tr>
    <tr>
      <td align="center" style="font-size:12px;color:#6b7280;padding:8px 0 24px 0;">
        Primești acest email pentru că te-ai abonat la noutățile {$name}.<br>
        <a href="{{unsubscribe_url}}" style="color:#9ca3af;text-decoration:underline;">Dezabonare</a>
        &nbsp;|&nbsp;
        <a href="{{preferences_url}}" style="color:#9ca3af;text-decoration:underline;">Preferințe</a>
      </td>
    </tr>
  </table>
</div>
HTML;
    }

    private function buildBodyText(MarketplaceClient $client): string
    {
        $name = $client->name ?? 'AmBilet.ro';
        return "{$name} îți recomandă cele mai tari concerte și evenimente.\n\n"
            . "Vezi detaliile evenimentului accesând link-ul din email.\n\n"
            . "Dezabonare: {{unsubscribe_url}}\n";
    }
}
