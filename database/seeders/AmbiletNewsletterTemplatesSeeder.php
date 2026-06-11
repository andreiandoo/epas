<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the Ambilet marketplace's "single-event promo" newsletter
 * template (Dirty Shirt design) into marketplace_email_templates.
 *
 * The template appears in /marketplace/newsletters/create under the
 * "Pornește de la un template" picker. Selecting it copies the body
 * HTML into a body_sections HTML block which the admin then edits to
 * customise text + event details before sending.
 *
 * Placeholders embedded in the HTML use the {{...}} convention; the
 * NewsletterRenderer / MarketplaceEmailTemplate::replaceVariables()
 * will replace at send time anything that has a matching value passed
 * in the render data. Placeholders without a match are left as-is, so
 * the admin can either replace them manually inline in the HTML editor
 * or fill the corresponding event-scoped tokens.
 *
 * Idempotent: uses updateOrCreate keyed on (marketplace_client_id, slug).
 *
 *   php artisan db:seed --class=Database\\Seeders\\AmbiletNewsletterTemplatesSeeder
 */
class AmbiletNewsletterTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve the Ambilet marketplace client. Fall back to the first
        // marketplace if domain lookup misses on local dev DBs.
        $client = MarketplaceClient::query()
            ->where('domain', 'like', '%ambilet.ro%')
            ->orWhere('name', 'like', '%Ambilet%')
            ->first()
            ?? MarketplaceClient::orderBy('id')->first();

        if (!$client) {
            $this->command->warn('No MarketplaceClient found — skipping seeder.');
            return;
        }

        $bodyHtml = $this->buildBodyHtml();

        $tpl = MarketplaceEmailTemplate::updateOrCreate(
            [
                'marketplace_client_id' => $client->id,
                'slug' => 'newsletter-event-promo-dirty-shirt',
            ],
            [
                'name' => 'Newsletter — Concert / Eveniment (design Dirty Shirt)',
                'category' => 'newsletter',
                'is_active' => true,
                'is_default' => false,
                'subject' => '{{event_title}} — {{event_venue}} | AmBilet.ro',
                'body_html' => $bodyHtml,
                'body_text' => $this->buildBodyText(),
                'variables' => [
                    'event_title' => 'Numele evenimentului (ex: Dirty Shirt — Live la Daos Club)',
                    'event_venue' => 'Numele locației (ex: Daos Club)',
                    'event_category' => 'Eticheta de categorie (ex: Concert · Folk-Metal · Timișoara)',
                    'event_lead' => 'Paragraf scurt sub titlu (1-2 propoziții)',
                    'event_date' => 'Data afișată (ex: 13 Iunie 2026)',
                    'event_doors_start' => 'Porți / start (ex: 19:00 · 20:30)',
                    'event_price' => 'Prețul curent (ex: 75,00 lei)',
                    'event_price_old' => 'Prețul tăiat (opțional — lasă gol pentru a ascunde)',
                    'event_price_label' => 'Etichetă deasupra prețului (ex: Early bird · ultimele zile)',
                    'event_image_url' => 'URL imagine hero (1200×750 sau similar)',
                    'event_image_alt' => 'Descriere imagine pentru accesibilitate',
                    'event_url' => 'Link către pagina de cumpărare bilet (apare pe hero + CTA)',
                    'cta_label' => 'Text buton CTA (default: Cumpără bilet →)',
                    'about_paragraph_1' => 'Primul paragraf de descriere',
                    'about_paragraph_2' => 'Al doilea paragraf de descriere',
                    'preheader_text' => 'Preheader (apare la preview în clientul de email)',
                    'unsubscribe_url' => 'Auto-generat la send time',
                    'preferences_url' => 'Auto-generat la send time',
                ],
            ]
        );

        $this->command->info("Ambilet newsletter template seeded: {$tpl->name} (id={$tpl->id}, client={$client->id})");
    }

    /**
     * The Dirty Shirt design with event-specific text replaced by
     * {{...}} placeholders. Surrounding chrome (header / "Vezi toate
     * evenimentele" / trust badges / footer) stays hardcoded so the
     * admin only has to fill in the event details.
     */
    private function buildBodyHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ro">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>{{event_title}} | AmBilet.ro</title>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
    table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
    img { -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; display:block; }
    body { margin:0 !important; padding:0 !important; width:100% !important; }
    a { color:inherit; text-decoration:none; }
    @media screen and (max-width:600px) {
      .container { width:100% !important; max-width:100% !important; }
      .px-mobile { padding-left:24px !important; padding-right:24px !important; }
      .py-mobile { padding-top:32px !important; padding-bottom:32px !important; }
      .hero-title { font-size:32px !important; line-height:1.08 !important; letter-spacing:-0.6px !important; }
      .section-title { font-size:26px !important; line-height:1.15 !important; }
      .price-stack { display:block !important; width:100% !important; text-align:left !important; padding-bottom:18px !important; }
      .cta-stack { display:block !important; width:100% !important; text-align:left !important; }
      .hide-mobile { display:none !important; }
      .full-img { width:100% !important; height:auto !important; }
      .btn-mobile { display:block !important; width:100% !important; text-align:center !important; box-sizing:border-box; }
      .meta-stack { display:block !important; width:100% !important; padding:16px 0 !important; border-bottom:1px solid #E5E7EB !important; border-left:none !important; }
      .meta-stack:last-child { border-bottom:none !important; }
      .trust-stack { display:block !important; width:100% !important; text-align:center !important; padding:6px 0 !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background-color:#F4F4F6; font-family:'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color:#0F1B2D;">

  <!-- Preheader -->
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#F4F4F6;">
    {{preheader_text}}
  </div>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#F4F4F6;">
    <tr>
      <td align="center" style="padding:24px 12px;">

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="container" style="max-width:600px; background-color:#FFFFFF;">

          <!-- HEADER -->
          <tr>
            <td style="background-color:#0F1B2D; padding:22px 32px;" class="px-mobile">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td align="left" valign="middle">
                    <a href="https://ambilet.ro" target="_blank">
                      <img src="https://ambilet.ro/storage/email/logo-white.png" alt="AmBilet.ro" width="120" height="32" style="display:block; width:120px; height:auto; border:0;">
                    </a>
                  </td>
                  <td align="right" valign="middle" class="hide-mobile">
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#9BA3B0; font-size:11px; letter-spacing:1px;">Peste 500 evenimente în România</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- HERO IMAGE -->
          <tr>
            <td style="padding:0; font-size:0; line-height:0;">
              <a href="{{event_url}}" target="_blank">
                <img src="{{event_image_url}}" alt="{{event_image_alt}}" width="600" class="full-img" style="display:block; width:100%; max-width:600px; height:auto; border:0;">
              </a>
            </td>
          </tr>

          <!-- EVENT HEADER -->
          <tr>
            <td style="padding:44px 40px 0;" class="px-mobile py-mobile">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="background-color:#FFF1F3; padding:6px 12px; border-radius:20px;">
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#C8253E; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase;">{{event_category}}</span>
                  </td>
                </tr>
              </table>

              <h1 class="hero-title" style="margin:20px 0 16px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:42px; line-height:1.05; font-weight:800; color:#0F1B2D; letter-spacing:-1px;">
                {{event_title}}
              </h1>

              <p style="margin:0 0 32px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:16px; line-height:1.6; color:#4B5563;">
                {{event_lead}}
              </p>
            </td>
          </tr>

          <!-- META BAR -->
          <tr>
            <td style="padding:0 40px;" class="px-mobile">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#F9FAFB; border-radius:8px;">
                <tr>
                  <td class="meta-stack" style="padding:22px 24px; vertical-align:top; width:33.33%;">
                    <p style="margin:0 0 4px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:10px; letter-spacing:1.5px; text-transform:uppercase; color:#6B7280; font-weight:700;">Data</p>
                    <p style="margin:0; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:16px; color:#0F1B2D; font-weight:700;">{{event_date}}</p>
                  </td>
                  <td class="meta-stack" style="padding:22px 24px; vertical-align:top; width:33.33%; border-left:1px solid #E5E7EB;">
                    <p style="margin:0 0 4px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:10px; letter-spacing:1.5px; text-transform:uppercase; color:#6B7280; font-weight:700;">Porți / Start</p>
                    <p style="margin:0; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:16px; color:#0F1B2D; font-weight:700;">{{event_doors_start}}</p>
                  </td>
                  <td class="meta-stack" style="padding:22px 24px; vertical-align:top; width:33.33%; border-left:1px solid #E5E7EB;">
                    <p style="margin:0 0 4px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:10px; letter-spacing:1.5px; text-transform:uppercase; color:#6B7280; font-weight:700;">Locație</p>
                    <p style="margin:0; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:16px; color:#0F1B2D; font-weight:700;">{{event_venue}}</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- PRICE + CTA -->
          <tr>
            <td style="padding:32px 40px 0;" class="px-mobile">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td valign="middle" class="price-stack">
                    <p style="margin:0; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:13px; color:#6B7280; font-weight:500;">{{event_price_label}}</p>
                    <p style="margin:2px 0 0; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:28px; color:#0F1B2D; font-weight:800; letter-spacing:-0.5px;">
                      {{event_price}}
                      <span style="font-size:15px; color:#9CA3AF; font-weight:600; text-decoration:line-through; margin-left:8px;">{{event_price_old}}</span>
                    </p>
                  </td>
                  <td align="right" valign="middle" class="cta-stack">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td bgcolor="#C8253E" style="border-radius:8px;">
                          <a href="{{event_url}}" target="_blank" class="btn-mobile" style="display:inline-block; padding:16px 32px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:14px; font-weight:700; color:#FFFFFF; text-decoration:none; letter-spacing:0.2px;">
                            {{cta_label}}
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- DESCRIPTION -->
          <tr>
            <td style="padding:40px 40px 8px;" class="px-mobile">
              <p style="margin:0 0 14px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; color:#6B7280; font-weight:700;">Despre eveniment</p>
              <p style="margin:0 0 16px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:15px; line-height:1.7; color:#374151;">
                {{about_paragraph_1}}
              </p>
              <p style="margin:0; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:15px; line-height:1.7; color:#374151;">
                {{about_paragraph_2}}
              </p>
            </td>
          </tr>

          <!-- "VEZI TOATE EVENIMENTELE" — pink section -->
          <tr>
            <td style="background-color:#F8D6DC; padding:48px 40px;" class="px-mobile py-mobile" align="center">
              <p style="margin:0 0 12px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#8E1928; font-weight:700;">
                Pentru tine
              </p>
              <h2 class="section-title" style="margin:0 0 12px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:30px; line-height:1.15; font-weight:800; color:#0F1B2D; letter-spacing:-0.5px;">
                Nu rata niciun eveniment
              </h2>
              <p style="margin:0 0 28px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:15px; line-height:1.55; color:#5C1923;">
                Peste <strong style="color:#0F1B2D;">500 de evenimente</strong> active în toată România — concerte, teatru, festivaluri, stand-up și mai mult.
              </p>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                <tr>
                  <td bgcolor="#C8253E" style="border-radius:8px;" align="center">
                    <a href="https://ambilet.ro/" target="_blank" class="btn-mobile" style="display:inline-block; padding:15px 32px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:14px; font-weight:700; color:#FFFFFF; text-decoration:none; letter-spacing:0.2px;">
                      Vezi toate evenimentele &nbsp;→
                    </a>
                  </td>
                </tr>
              </table>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top:28px;">
                <tr>
                  <td style="padding:4px;"><a href="https://ambilet.ro/concerte" target="_blank" style="display:inline-block; padding:8px 14px; background-color:#FFFFFF; border-radius:18px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:12px; font-weight:600; color:#0F1B2D; text-decoration:none;">Concerte</a></td>
                  <td style="padding:4px;"><a href="https://ambilet.ro/teatru" target="_blank" style="display:inline-block; padding:8px 14px; background-color:#FFFFFF; border-radius:18px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:12px; font-weight:600; color:#0F1B2D; text-decoration:none;">Teatru</a></td>
                  <td style="padding:4px;"><a href="https://ambilet.ro/festivaluri" target="_blank" style="display:inline-block; padding:8px 14px; background-color:#FFFFFF; border-radius:18px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:12px; font-weight:600; color:#0F1B2D; text-decoration:none;">Festivaluri</a></td>
                  <td style="padding:4px;"><a href="https://ambilet.ro/stand-up" target="_blank" style="display:inline-block; padding:8px 14px; background-color:#FFFFFF; border-radius:18px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:12px; font-weight:600; color:#0F1B2D; text-decoration:none;">Stand-up</a></td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- TRUST BADGES -->
          <tr>
            <td style="background-color:#FFFFFF; padding:28px 40px;" class="px-mobile">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td class="trust-stack" valign="middle" width="33.33%" align="center" style="padding:0 8px;">
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#10B981; font-size:14px; font-weight:700;">✓</span>
                    &nbsp;
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#0F1B2D; font-size:13px; font-weight:600;">Plăți securizate</span>
                  </td>
                  <td class="trust-stack" valign="middle" width="33.33%" align="center" style="padding:0 8px;">
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#10B981; font-size:14px; font-weight:700;">✓</span>
                    &nbsp;
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#0F1B2D; font-size:13px; font-weight:600;">Bilete garantate</span>
                  </td>
                  <td class="trust-stack" valign="middle" width="33.33%" align="center" style="padding:0 8px;">
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#10B981; font-size:14px; font-weight:700;">✓</span>
                    &nbsp;
                    <span style="font-family:'Manrope', Helvetica, Arial, sans-serif; color:#0F1B2D; font-size:13px; font-weight:600;">Suport &lt; 24h</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="background-color:#0F1B2D; padding:36px 40px;" class="px-mobile" align="center">
              <a href="https://ambilet.ro" target="_blank">
                <img src="https://ambilet.ro/storage/email/logo-white.png" alt="AmBilet.ro" width="110" height="28" style="display:inline-block; width:110px; height:auto; border:0; margin-bottom:16px;">
              </a>
              <p style="margin:0 0 24px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:13px; color:#9BA3B0; line-height:1.5;">
                Platforma ta de încredere pentru bilete la evenimente.
              </p>
              <p style="margin:0 0 24px;">
                <a href="https://www.facebook.com/ambilet" style="font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:13px; color:#FFFFFF; text-decoration:none; font-weight:600; margin:0 12px;">Facebook</a>
                <span style="color:#3A4458;">·</span>
                <a href="https://www.instagram.com/ambilet" style="font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:13px; color:#FFFFFF; text-decoration:none; font-weight:600; margin:0 12px;">Instagram</a>
              </p>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="60" align="center" style="margin:0 auto;">
                <tr><td style="height:1px; background-color:#1F2D45; line-height:1px; font-size:0;">&nbsp;</td></tr>
              </table>
              <p style="margin:24px 0 12px; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:11px; line-height:1.6; color:#6B7587;">
                <a href="{{unsubscribe_url}}" style="color:#9BA3B0; text-decoration:underline;">Dezabonare</a> &nbsp;·&nbsp;
                <a href="{{preferences_url}}" style="color:#9BA3B0; text-decoration:underline;">Preferințe</a> &nbsp;·&nbsp;
                <a href="https://ambilet.ro/termeni-si-conditii" style="color:#9BA3B0; text-decoration:underline;">Termeni</a>
              </p>
              <p style="margin:0; font-family:'Manrope', Helvetica, Arial, sans-serif; font-size:10px; line-height:1.5; color:#4A5568;">
                © 2026 AmBilet.ro SRL &nbsp;·&nbsp; CUI 37653424
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
HTML;
    }

    private function buildBodyText(): string
    {
        return <<<'TEXT'
{{event_title}}
{{event_category}}

{{event_lead}}

Data: {{event_date}}
Porți / Start: {{event_doors_start}}
Locație: {{event_venue}}

{{event_price_label}}
{{event_price}} (înainte {{event_price_old}})

Cumpără bilet: {{event_url}}

DESPRE EVENIMENT
{{about_paragraph_1}}

{{about_paragraph_2}}

—
Pentru a vedea toate evenimentele: https://ambilet.ro
Dezabonare: {{unsubscribe_url}}
TEXT;
    }
}
