<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Newsletter — Single Featured Event (AmBilet v2 hero)" template
 * into marketplace_email_templates for the Ambilet marketplace. Appears in
 *   /marketplace/email-templates
 * and in the "Pornește de la un template" picker on
 *   /marketplace/newsletters/create
 *
 * The body_html is a static dark-wrapper / rounded white-card shell — the
 * actual hero (title + artist + details grid + image + CTA) is rendered
 * by the `featured_event` section with design_variant = 'v2' (added to
 * NewsletterResource).
 *
 *   php artisan db:seed --class=Database\\Seeders\\AmbiletNewsletterFeaturedEventV2TemplateSeeder
 */
class AmbiletNewsletterFeaturedEventV2TemplateSeeder extends Seeder
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
                'slug' => 'newsletter-featured-event-v2',
            ],
            [
                'name' => 'Newsletter — Eveniment featured (AmBilet v2 hero)',
                'category' => 'newsletter',
                'is_active' => true,
                'is_default' => false,
                'subject' => '{{event_title}} — îți recomandăm',
                'body_html' => $this->buildBodyHtml($client),
                'body_text' => $this->buildBodyText($client),
                'variables' => [
                    'event_title' => 'Numele evenimentului (apare în subject; după ce alegi evenimentul în secțiunea "Eveniment featured", se completează automat din nume).',
                    'unsubscribe_url' => 'Auto-generat la send time.',
                    'preferences_url' => 'Auto-generat la send time.',
                ],
            ]
        );

        $this->command->info("Newsletter featured-event v2 template seeded: {$tpl->name} (id={$tpl->id}, client={$client->id})");
    }

    private function buildBodyHtml(MarketplaceClient $client): string
    {
        $name = htmlspecialchars($client->name ?? 'AmBilet.ro', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="margin:0;padding:0;background:#0F172A;font-family:Arial,Helvetica,sans-serif;">
  <table bgcolor="#0F172A" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:0;background-color:#0F172A;">
    <tr>
      <td align="center" style="padding:20px 12px 8px 12px;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;border-collapse:collapse;">
          <tr>
            <td align="center" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:18px;color:#94A3B8;letter-spacing:0.6px;text-transform:uppercase;">
              {$name} îți recomandă
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!--
      ↓ ↓ ↓  Adaugă mai jos o secțiune de tip "Eveniment featured (single hero)"  ↓ ↓ ↓
      din meniul "Tip secțiune" al editorului de newsletter și setează
      "Design" = "AmBilet v2 (hero cu detalii)". Aceea va popula automat
      numele, data, ora, locația, orașul, imaginea și CTA-ul pentru
      evenimentul selectat.
    -->

    <tr>
      <td align="center" style="padding:24px 12px 32px 12px;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;border-collapse:collapse;">
          <tr>
            <td align="center" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#94A3B8;">
              Primești acest email pentru că te-ai abonat la noutățile {$name}.<br>
              <a href="{{unsubscribe_url}}" style="color:#CBD5E1;text-decoration:underline;">Dezabonare</a>
              &nbsp;|&nbsp;
              <a href="{{preferences_url}}" style="color:#CBD5E1;text-decoration:underline;">Preferințe</a>
            </td>
          </tr>
        </table>
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
