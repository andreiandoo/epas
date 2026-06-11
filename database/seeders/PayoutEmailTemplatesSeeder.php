<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEmailTemplate;
use Database\Seeders\Concerns\BrandedEmailWrapper;
use Illuminate\Database\Seeder;

/**
 * Seeds the 5 organizer-facing payout templates per marketplace:
 *   payout_submitted  / payout_approved / payout_processing
 *   payout_completed  / payout_rejected
 *
 * MarketplacePayoutNotification looks these up by slug per marketplace
 * and falls back to the hardcoded MailMessage body only when the row is
 * missing — so reseeding never breaks production.
 *
 * Run:
 *   php artisan db:seed --class=PayoutEmailTemplatesSeeder
 */
class PayoutEmailTemplatesSeeder extends Seeder
{
    use BrandedEmailWrapper;

    public function run(): void
    {
        foreach (MarketplaceClient::all() as $marketplace) {
            $this->seedForMarketplace($marketplace);
        }
    }

    protected function seedForMarketplace(MarketplaceClient $marketplace): void
    {
        $brand = $this->brand($marketplace);
        $primary = $brand['primary'];
        $contact = $brand['contact_email'];

        $detailsRows = [
            ['Referință', '{{payout_reference}}'],
            ['Suma', '{{payout_amount}}'],
            ['Perioada', '{{payout_period}}'],
        ];

        $templates = [
            // --------------------------------------------------------
            'payout_submitted' => [
                'name' => 'Plată — cerere înregistrată',
                'subject' => 'Cerere de plată înregistrată — {{payout_reference}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Cerere de plată înregistrată',
                    'Cererea ta de plată a fost înregistrată cu succes.',
                    $detailsRows,
                    'O vom analiza și o vom procesa în cel mai scurt timp. Vei primi o notificare când cererea este aprobată sau dacă avem nevoie de informații suplimentare.',
                    null,
                    null,
                    $brand
                ),
            ],

            // --------------------------------------------------------
            'payout_approved' => [
                'name' => 'Plată — cerere aprobată',
                'subject' => 'Cererea de plată aprobată — {{payout_reference}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Cerere de plată aprobată ✅',
                    'Veste bună! Cererea ta de plată a fost aprobată.',
                    $detailsRows,
                    'Plata va fi inițiată în scurt timp. Vei primi o nouă notificare când transferul este finalizat.',
                    null,
                    null,
                    $brand
                ),
            ],

            // --------------------------------------------------------
            'payout_processing' => [
                'name' => 'Plată — în procesare',
                'subject' => 'Plata în procesare — {{payout_reference}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Plata ta este în procesare',
                    'Transferul a fost inițiat.',
                    $detailsRows,
                    'Te rugăm să acorzi 1-3 zile lucrătoare pentru ca suma să apară în contul tău bancar.',
                    null,
                    null,
                    $brand
                ),
            ],

            // --------------------------------------------------------
            'payout_completed' => [
                'name' => 'Plată — finalizată',
                'subject' => 'Plata finalizată — {{payout_reference}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Plata finalizată 🎉',
                    'Plata ta a fost finalizată cu succes!',
                    array_merge(
                        $detailsRows,
                        [['Referință plată', '{{payment_reference}}']]
                    ),
                    'Suma ar trebui să fie disponibilă în contul tău bancar. Mulțumim pentru parteneriat!',
                    null,
                    null,
                    $brand
                ),
            ],

            // --------------------------------------------------------
            'payout_rejected' => [
                'name' => 'Plată — cerere respinsă',
                'subject' => 'Cererea de plată respinsă — {{payout_reference}}',
                'category' => 'transactional',
                'content' => $this->intro(
                    'Cerere de plată respinsă',
                    'Din păcate, cererea ta de plată a fost respinsă.',
                    array_merge(
                        $detailsRows,
                        [['Motiv', '{{rejection_reason}}']]
                    ),
                    'Suma a fost returnată în soldul tău disponibil. Pentru întrebări, contactează echipa de suport la <a href="mailto:' . $contact . '" style="color:' . $primary . ';">' . $contact . '</a>.',
                    null,
                    null,
                    $brand
                ),
            ],
        ];

        foreach ($templates as $slug => $tpl) {
            MarketplaceEmailTemplate::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplace->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $tpl['name'],
                    'subject' => $tpl['subject'],
                    'body_html' => $this->wrap($brand, $tpl['content']),
                    'category' => $tpl['category'] ?? 'transactional',
                    'is_active' => true,
                    'is_default' => true,
                ]
            );
        }
    }

    /**
     * Standard "intro + details + outro [+ CTA]" structure.
     */
    protected function intro(
        string $heading,
        string $intro,
        array $rows,
        ?string $outro,
        ?string $ctaUrl,
        ?string $ctaLabel,
        array $brand
    ): string {
        $primary = $brand['primary'];
        $details = $this->detailsTable($brand, 'Detalii plată', $rows);
        $cta = ($ctaUrl && $ctaLabel)
            ? "<p style=\"text-align:center;margin:24px 0;\"><a href=\"{$ctaUrl}\" style=\"display:inline-block;padding:12px 28px;background:{$primary};color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;\">{$ctaLabel}</a></p>"
            : '';

        return <<<CONTENT
<h2 style="color:#1a1a1a;font-size:22px;margin:0 0 16px;">{$heading}</h2>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 12px;">Salut <strong>{{organizer_name}}</strong>,</p>
<p style="color:#4a4a4a;font-size:15px;line-height:1.6;margin:0 0 20px;">{$intro}</p>
{$details}
<p style="color:#4a4a4a;font-size:14px;line-height:1.6;margin:0 0 16px;">{$outro}</p>
{$cta}
CONTENT;
    }
}
